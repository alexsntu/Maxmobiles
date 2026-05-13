"""
Bidding handler — accepts bids from group members.

Ways to bid:
  1. Quick-bid buttons (+N ₽) — instant, no PM needed
  2. "✏️ Своя сумма" button → ForceReply DM (lot_id embedded in message)
  3. Reply to the lot message with a plain number in the group
"""

import logging
from datetime import datetime, timedelta, timezone

from aiogram import Bot, F, Router
from aiogram.types import CallbackQuery, Message

import database as db
from config import ANTI_SNIPE_SECONDS, GROUP_ID, GROUP_IDS
from keyboards import blitz_confirm_keyboard, lot_keyboard
from utils import format_lot_message, seconds_until

logger = logging.getLogger(__name__)

# Telegram's hard limit for answerCallbackQuery text
_TG_ANSWER_LIMIT = 195

router = Router()


# ─── Core bid logic ───────────────────────────────────────────────────────────

async def _place_bid(
    bot: Bot,
    lot_id: int,
    user_id: int,
    username: str | None,
    full_name: str,
    amount: int,
    reply_target,
    is_callback: bool = False,
) -> None:
    """Validate bid, save to DB, update lot message, handle anti-snipe."""
    lot = await db.get_lot(lot_id)

    if lot is None:
        msg = "❌ Лот не найден."
        await (reply_target.answer(msg, show_alert=True) if is_callback else reply_target.answer(msg))
        return

    if lot["status"] != "active":
        msg = "⏹ Аукцион по этому лоту уже завершён."
        await (reply_target.answer(msg, show_alert=True) if is_callback else reply_target.answer(msg))
        return

    remaining = seconds_until(lot["end_time"])
    if remaining <= 0:
        msg = "⏹ Время аукциона истекло."
        await (reply_target.answer(msg, show_alert=True) if is_callback else reply_target.answer(msg))
        return

    if lot.get("winner_id") == user_id:
        msg = "⏳ Вы уже лидируете! Дождитесь ставки другого участника, чтобы сделать свою ставку снова."
        await (reply_target.answer(msg, show_alert=True) if is_callback else reply_target.answer(msg))
        return

    min_bid = lot["current_price"] + lot["min_step"]
    if amount < min_bid:
        msg = (
            f"❗ Ставка слишком мала.\n"
            f"Минимальная: {min_bid:,} ₽ "
            f"(текущая {lot['current_price']:,} + шаг {lot['min_step']:,} ₽)"
        )
        await (reply_target.answer(f"Минимальная ставка: {min_bid:,} ₽", show_alert=True)
               if is_callback else reply_target.answer(msg))
        return

    # Save
    await db.upsert_user(user_id, username, full_name)
    await db.add_bid(lot_id, user_id, amount)
    await db.update_lot_bid(lot_id, amount, user_id)

    # Anti-snipe
    extended = False
    if remaining < ANTI_SNIPE_SECONDS:
        new_end = datetime.now(tz=timezone.utc) + timedelta(seconds=ANTI_SNIPE_SECONDS)
        await db.extend_lot_time(lot_id, new_end)
        from scheduler import schedule_lot_close
        schedule_lot_close(lot_id, new_end, bot)
        extended = True

    # Update lot message
    lot_updated = await db.get_lot(lot_id)
    top_bids = await db.get_lot_bids(lot_id)
    bid_count = await db.count_bids(lot_id)
    caption = format_lot_message(lot_updated, top_bids=top_bids[:3], bid_count=bid_count)
    if extended:
        caption += f"\n⚡ <i>Таймер продлён на {ANTI_SNIPE_SECONDS} сек!</i>"

    lot_group_id = lot_updated.get("group_chat_id") or GROUP_ID
    try:
        await bot.edit_message_caption(
            chat_id=lot_group_id,
            message_id=lot_updated["group_message_id"],
            caption=caption,
            parse_mode="HTML",
            reply_markup=lot_keyboard(
                lot_id,
                lot_updated["min_step"],
                lot_updated.get("blitz_price"),
                bid_count,
                bid_variants=lot_updated.get("bid_variants") or 3,
                has_bids=bid_count > 0,
            ),
        )
    except Exception:
        pass

    # Confirm
    confirm = f"✅ Ставка {amount:,} ₽ принята!"
    if extended:
        confirm += f" ⚡ Таймер продлён."
    if is_callback:
        await reply_target.answer(confirm, show_alert=False)
    else:
        await reply_target.answer(f"✅ Ставка <b>{amount:,} ₽</b> принята!", parse_mode="HTML")


# ─── Way 1: Quick-bid buttons ─────────────────────────────────────────────────

@router.callback_query(F.data.startswith("quickbid:"))
async def quick_bid(callback: CallbackQuery, bot: Bot) -> None:
    _, lot_id_str, increment_str = callback.data.split(":")
    lot_id = int(lot_id_str)
    increment = int(increment_str)

    lot = await db.get_lot(lot_id)
    if lot is None or lot["status"] != "active":
        await callback.answer("Аукцион уже завершён.", show_alert=True)
        return

    if lot.get("winner_id") == callback.from_user.id:
        await callback.answer(
            "⏳ Вы уже лидируете! Дождитесь ставки другого участника, чтобы сделать свою ставку снова.",
            show_alert=True,
        )
        return

    amount = lot["current_price"] + increment

    await _place_bid(
        bot=bot,
        lot_id=lot_id,
        user_id=callback.from_user.id,
        username=callback.from_user.username,
        full_name=callback.from_user.full_name,
        amount=amount,
        reply_target=callback,
        is_callback=True,
    )


# ─── Way 2: Reply to lot message with number in group ─────────────────────────

@router.message(
    F.reply_to_message.photo.as_("photos"),
    F.text.regexp(r"^\s*\d[\d\s,]*$"),
)
async def bid_via_reply(message: Message, bot: Bot) -> None:
    if message.chat.id not in GROUP_IDS:
        return
    if message.from_user.is_bot:
        return

    replied_msg_id = message.reply_to_message.message_id
    active_lots = await db.get_active_lots()
    lot = next((l for l in active_lots if l["group_message_id"] == replied_msg_id), None)
    if lot is None:
        return

    try:
        amount = int(message.text.strip().replace(" ", "").replace(",", ""))
    except ValueError:
        return

    await _place_bid(
        bot=bot,
        lot_id=lot["id"],
        user_id=message.from_user.id,
        username=message.from_user.username,
        full_name=message.from_user.full_name,
        amount=amount,
        reply_target=message,
    )


# ─── Blitz purchase — шаг 1: запрос подтверждения ────────────────────────────

@router.callback_query(F.data.startswith("blitz:"))
async def blitz_purchase(callback: CallbackQuery, bot: Bot) -> None:
    lot_id = int(callback.data.split(":")[1])
    lot = await db.get_lot(lot_id)

    if lot is None or lot["status"] != "active":
        await callback.answer("Аукцион уже завершён.", show_alert=True)
        return

    blitz_price = lot.get("blitz_price")
    if not blitz_price:
        await callback.answer("Блиц-цена не установлена.", show_alert=True)
        return

    bid_count = await db.count_bids(lot_id)
    if bid_count >= 10:
        await callback.answer(
            "⚡ Блиц-цена недоступна — уже сделано 10 и более ставок.",
            show_alert=True,
        )
        return

    await callback.answer()

    try:
        await bot.send_message(
            chat_id=callback.from_user.id,
            text=(
                f"⚡ Вы нажали «Купить по блиц-цене».\n\n"
                f"Лот: <b>{lot['title']}</b>\n"
                f"Блиц-цена: <b>{blitz_price:,} ₽</b>\n\n"
                f"Нажмите кнопку ниже, чтобы подтвердить покупку. "
                f"Если вы нажали случайно — отмените кнопкой «Отмена»."
            ),
            parse_mode="HTML",
            reply_markup=blitz_confirm_keyboard(lot_id, callback.from_user.id),
        )
    except Exception:
        await callback.answer(
            "❌ Не получилось отправить личное сообщение.\n"
            "Пожалуйста, напишите боту в личку (/start) и повторите попытку.",
            show_alert=True,
        )


# ─── Blitz purchase — шаг 2: подтверждение ───────────────────────────────────

async def _execute_blitz(bot: Bot, lot_id: int, user_id: int, username: str | None, full_name: str) -> None:
    """Финальное выполнение блиц-покупки."""
    lot = await db.get_lot(lot_id)
    if lot is None or lot["status"] != "active":
        return

    blitz_price = lot["blitz_price"]

    await db.upsert_user(user_id, username, full_name)
    await db.add_bid(lot_id, user_id, blitz_price)
    await db.update_lot_bid(lot_id, blitz_price, user_id)
    await db.finish_lot(lot_id, user_id)

    from scheduler import scheduler, _job_id
    job = scheduler.get_job(_job_id(lot_id))
    if job:
        job.remove()

    from utils import format_winner_announcement
    lot_finished = await db.get_lot(lot_id)
    top_bids = await db.get_lot_bids(lot_id)
    final_bid_count = await db.count_bids(lot_id)
    caption = format_lot_message(lot_finished, top_bids=top_bids[:3], bid_count=final_bid_count)
    announcement = format_winner_announcement(
        lot["title"], user_id, full_name, username, blitz_price, is_blitz=True,
        top_bids=top_bids,
    )

    lot_group_id = lot.get("group_chat_id") or GROUP_ID
    if lot["group_message_id"]:
        try:
            await bot.edit_message_caption(
                chat_id=lot_group_id,
                message_id=lot["group_message_id"],
                caption=caption + f"\n\n{announcement}",
                parse_mode="HTML",
                reply_markup=None,
            )
        except Exception:
            pass

    try:
        await bot.send_message(lot_group_id, announcement, parse_mode="HTML")
    except Exception:
        pass

    try:
        await bot.send_message(
            user_id,
            f"⚡ <b>Вы купили лот по блиц-цене!</b>\n\n"
            f"Лот: <b>{lot['title']}</b>\n"
            f"Цена: <b>{blitz_price:,} ₽</b>\n\n"
            f"Мы скоро с вами свяжемся и скажем, как можно будет получить ваш лот.",
            parse_mode="HTML",
        )
    except Exception:
        pass

    from config import ADMIN_IDS
    for admin_id in ADMIN_IDS:
        try:
            await bot.send_message(
                admin_id,
                f"⚡ <b>Блиц-покупка!</b>\n\nЛот #{lot_id}: <b>{lot['title']}</b>\n"
                f"Покупатель: {full_name or username}\nЦена: <b>{blitz_price:,} ₽</b>",
                parse_mode="HTML",
            )
        except Exception:
            pass


@router.callback_query(F.data.startswith("blitz_confirm:"))
async def blitz_confirm_handler(callback: CallbackQuery, bot: Bot) -> None:
    parts = callback.data.split(":")
    lot_id = int(parts[1])
    authorized_user_id = int(parts[2]) if len(parts) > 2 else None

    if authorized_user_id and callback.from_user.id != authorized_user_id:
        await callback.answer("❌ Это подтверждение предназначено не для вас.", show_alert=True)
        return

    lot = await db.get_lot(lot_id)

    if lot is None or lot["status"] != "active":
        await callback.answer("Аукцион уже завершён.", show_alert=True)
        try:
            await callback.message.delete()
        except Exception:
            pass
        return

    await _execute_blitz(
        bot=bot,
        lot_id=lot_id,
        user_id=callback.from_user.id,
        username=callback.from_user.username,
        full_name=callback.from_user.full_name,
    )

    try:
        await callback.message.delete()
    except Exception:
        pass

    await callback.answer("⚡ Поздравляем! Лот ваш!", show_alert=True)


@router.callback_query(F.data.startswith("blitz_cancel_confirm:"))
async def blitz_cancel_handler(callback: CallbackQuery) -> None:
    parts = callback.data.split(":")
    authorized_user_id = int(parts[2]) if len(parts) > 2 else None

    if authorized_user_id and callback.from_user.id != authorized_user_id:
        await callback.answer("❌ Это подтверждение предназначено не для вас.", show_alert=True)
        return

    try:
        await callback.message.delete()
    except Exception:
        pass
    await callback.answer("↩️ Покупка отменена. Аукцион продолжается.", show_alert=False)


# ─── Cancel bid ───────────────────────────────────────────────────────────────

@router.callback_query(F.data.startswith("cancelbid:"))
async def cancel_bid_handler(callback: CallbackQuery, bot: Bot) -> None:
    lot_id = int(callback.data.split(":")[1])
    user_id = callback.from_user.id

    lot = await db.get_lot(lot_id)
    if lot is None or lot["status"] != "active":
        await callback.answer("Аукцион уже завершён.", show_alert=True)
        return

    # Проверяем, является ли пользователь текущим лидером
    if lot.get("winner_id") != user_id:
        await callback.answer(
            "❌ Вы не лидируете — ставку отменить нельзя.\n"
            "Отмена доступна только текущему лидеру торгов.",
            show_alert=True,
        )
        return

    removed = await db.cancel_top_bid(lot_id, user_id)
    if not removed:
        await callback.answer("У вас нет ставок по этому лоту.", show_alert=True)
        return

    # Обновляем сообщение лота
    lot_updated = await db.get_lot(lot_id)
    top_bids = await db.get_lot_bids(lot_id)
    bid_count = await db.count_bids(lot_id)
    caption = format_lot_message(lot_updated, top_bids=top_bids[:3], bid_count=bid_count)

    lot_group_id = lot_updated.get("group_chat_id") or GROUP_ID
    try:
        await bot.edit_message_caption(
            chat_id=lot_group_id,
            message_id=lot_updated["group_message_id"],
            caption=caption,
            parse_mode="HTML",
            reply_markup=lot_keyboard(
                lot_id,
                lot_updated["min_step"],
                lot_updated.get("blitz_price"),
                bid_count,
                bid_variants=lot_updated.get("bid_variants") or 3,
                has_bids=bid_count > 0,
            ),
        )
    except Exception:
        pass

    await callback.answer("↩️ Ваша ставка отменена.", show_alert=True)


# ─── Info / rules popup ───────────────────────────────────────────────────────

@router.callback_query(F.data.startswith("info:"))
async def show_info(callback: CallbackQuery) -> None:
    """Show auction rules for this lot.

    Short rules (≤ 195 chars) are shown as a Telegram popup.
    Long rules are sent as a regular reply message in the chat — no length limit.
    """
    try:
        lot_id = int(callback.data.split(":")[1])
        lot = await db.get_lot(lot_id)

        if lot is None:
            await callback.answer("Лот не найден.", show_alert=True)
            return

        rules = (lot.get("rules") or "").strip()

        if not rules:
            await callback.answer("ℹ️ Правила для этого лота не заданы.", show_alert=True)
            return

        if len(rules) <= _TG_ANSWER_LIMIT:
            # Укладывается в лимит — показываем попап
            await callback.answer(rules, show_alert=True)
        else:
            # Длинные правила — отправляем сообщением в чат (лимита нет)
            await callback.message.answer(
                f"ℹ️ <b>Правила лота #{lot_id}:</b>\n\n{rules}",
                parse_mode="HTML",
            )
            await callback.answer()   # убираем спиннер с кнопки
    except Exception:
        logger.exception("show_info error (callback_data=%s)", callback.data)
        await callback.answer("⚠️ Не удалось загрузить информацию.", show_alert=True)


# ─── My bid popup ─────────────────────────────────────────────────────────────

@router.callback_query(F.data.startswith("mybid:"))
async def my_bid(callback: CallbackQuery) -> None:
    """Show the user their own current highest bid for this lot."""
    try:
        lot_id = int(callback.data.split(":")[1])
        bid = await db.get_user_bid_for_lot(lot_id, callback.from_user.id)

        if bid is None:
            await callback.answer("У вас пока нет ставок по этому лоту.", show_alert=True)
            return

        lot = await db.get_lot(lot_id)
        current = lot["current_price"] if lot else 0
        is_leading = lot and lot.get("winner_id") == callback.from_user.id

        status = "🥇 Вы лидируете!" if is_leading else "📉 Вас перебили."
        text = (
            f"👤 Ваша ставка: {bid['amount']:,} ₽\n"
            f"🔝 Текущая цена: {current:,} ₽\n"
            f"{status}"
        )
        await callback.answer(text[:_TG_ANSWER_LIMIT], show_alert=True)
    except Exception:
        logger.exception("my_bid error (lot callback_data=%s)", callback.data)
        await callback.answer("⚠️ Не удалось загрузить данные.", show_alert=True)


# ─── Bid history popup ────────────────────────────────────────────────────────

@router.callback_query(F.data.startswith("history:"))
async def show_history(callback: CallbackQuery) -> None:
    try:
        lot_id = int(callback.data.split(":")[1])
        bids = await db.get_lot_bids(lot_id)
        if not bids:
            await callback.answer("Ставок ещё нет.", show_alert=True)
            return
        medals = ["🥇", "🥈", "🥉"]
        lines = []
        for i, b in enumerate(bids[:10]):
            name = b["full_name"] or b["username"] or str(b["user_id"])
            prefix = medals[i] if i < 3 else f"{i + 1}."
            lines.append(f"{prefix} {name} — {b['amount']:,} ₽")
        text = "\n".join(lines)
        await callback.answer(text[:_TG_ANSWER_LIMIT], show_alert=True)
    except Exception:
        logger.exception("show_history error (lot callback_data=%s)", callback.data)
        await callback.answer("⚠️ Не удалось загрузить историю.", show_alert=True)
