from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo

from aiogram import Bot, F, Router
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext
from aiogram.types import (
    CallbackQuery,
    InputMediaPhoto,
    Message,
)

import database as db
from config import ADMIN_IDS, GROUP_ID, GROUPS
from keyboards import (
    admin_lot_actions_keyboard,
    admin_lots_keyboard,
    bid_variants_keyboard,
    confirm_lot_keyboard,
    duration_keyboard,
    group_select_keyboard,
    lot_keyboard,
)
from states import NewLotStates
from utils import format_lot_message, format_time_remaining

MOSCOW_TZ = ZoneInfo("Europe/Moscow")

router = Router()

# ─── Middleware: only admins ───────────────────────────────────────────────────

def _is_admin(user_id: int) -> bool:
    return user_id in ADMIN_IDS


def _admin_only(message: Message) -> bool:
    return _is_admin(message.from_user.id)


# ─── /newlot ──────────────────────────────────────────────────────────────────

@router.message(Command("newlot"))
async def cmd_newlot(message: Message, state: FSMContext) -> None:
    if not _admin_only(message):
        return
    await state.clear()

    if len(GROUPS) > 1:
        await state.set_state(NewLotStates.waiting_group)
        names = "\n".join(f"• {name}" for name, _ in GROUPS)
        await message.answer(
            f"📢 <b>В каком канале публикуем лот?</b>\n\n{names}",
            parse_mode="HTML",
            reply_markup=group_select_keyboard(GROUPS),
        )
    else:
        await state.update_data(target_group_id=GROUPS[0][1], target_group_name=GROUPS[0][0])
        await state.set_state(NewLotStates.waiting_photo)
        await message.answer("📸 <b>Шаг 1/9.</b> Отправьте фото товара.", parse_mode="HTML")


@router.callback_query(NewLotStates.waiting_group, F.data.startswith("group:"))
async def process_group(callback: CallbackQuery, state: FSMContext) -> None:
    group_id = int(callback.data.split(":")[1])
    group_name = next((name for name, gid in GROUPS if gid == group_id), str(group_id))
    await state.update_data(target_group_id=group_id, target_group_name=group_name)
    await state.set_state(NewLotStates.waiting_photo)
    await callback.message.edit_text(
        f"📢 Канал: <b>{group_name}</b>\n\n"
        "📸 <b>Шаг 1/9.</b> Отправьте фото товара.",
        parse_mode="HTML",
    )
    await callback.answer()


@router.message(NewLotStates.waiting_photo, F.photo)
async def process_photo(message: Message, state: FSMContext) -> None:
    photo_id = message.photo[-1].file_id
    await state.update_data(photo_id=photo_id)
    await state.set_state(NewLotStates.waiting_title)
    await message.answer(
        "✏️ <b>Шаг 2/9.</b> Введите <b>название</b> лота (до 100 символов).",
        parse_mode="HTML",
    )


@router.message(NewLotStates.waiting_photo)
async def process_photo_wrong(message: Message) -> None:
    await message.answer("❗ Пожалуйста, отправьте <b>фото</b> товара.", parse_mode="HTML")


@router.message(NewLotStates.waiting_title, F.text)
async def process_title(message: Message, state: FSMContext) -> None:
    title = message.text.strip()
    if len(title) > 100:
        await message.answer("❗ Название слишком длинное. Максимум 100 символов.")
        return
    await state.update_data(title=title)
    await state.set_state(NewLotStates.waiting_description)
    await message.answer(
        "📝 <b>Шаг 3/9.</b> Введите <b>описание</b> лота.\n"
        "Напишите <code>-</code> чтобы оставить без описания.",
        parse_mode="HTML",
    )


@router.message(NewLotStates.waiting_description, F.text)
async def process_description(message: Message, state: FSMContext) -> None:
    desc = message.text.strip()
    await state.update_data(description="" if desc == "-" else desc)
    await state.set_state(NewLotStates.waiting_start_price)
    await message.answer(
        "💰 <b>Шаг 4/9.</b> Укажите <b>стартовую цену</b> в рублях (целое число).",
        parse_mode="HTML",
    )


@router.message(NewLotStates.waiting_start_price, F.text)
async def process_start_price(message: Message, state: FSMContext) -> None:
    try:
        price = int(message.text.strip().replace(" ", "").replace(",", ""))
        if price <= 0:
            raise ValueError
    except ValueError:
        await message.answer("❗ Укажите корректную цену — целое положительное число.")
        return
    await state.update_data(start_price=price)
    await state.set_state(NewLotStates.waiting_min_step)
    await message.answer(
        "📈 <b>Шаг 5/9.</b> Укажите <b>минимальный шаг ставки</b> в рублях.\n\n"
        "<i>(Для режима фиксированной цены этот шаг не используется — можно ввести любое число.)</i>",
        parse_mode="HTML",
    )


@router.message(NewLotStates.waiting_min_step, F.text)
async def process_min_step(message: Message, state: FSMContext) -> None:
    try:
        step = int(message.text.strip().replace(" ", "").replace(",", ""))
        if step <= 0:
            raise ValueError
    except ValueError:
        await message.answer("❗ Укажите корректный шаг — целое положительное число.")
        return
    await state.update_data(min_step=step)
    await state.set_state(NewLotStates.waiting_bid_variants)
    await message.answer(
        "🎯 <b>Шаг 6/9.</b> Сколько вариантов ставки показывать участникам?",
        parse_mode="HTML",
        reply_markup=bid_variants_keyboard(),
    )


@router.callback_query(NewLotStates.waiting_bid_variants, F.data.startswith("bidvariants:"))
async def process_bid_variants(callback: CallbackQuery, state: FSMContext) -> None:
    bid_variants = int(callback.data.split(":")[1])   # 1 или 3
    await state.update_data(bid_variants=bid_variants)
    await state.set_state(NewLotStates.waiting_blitz_price)
    await callback.message.edit_text(
        "⚡ <b>Шаг 7/9.</b> Укажите <b>блиц-цену</b> (купить сейчас).\n\n"
        "Эта кнопка позволяет сразу выиграть лот по фиксированной цене "
        "и исчезает после 10 ставок.\n\n"
        "Введите сумму в рублях или напишите <code>-</code> чтобы пропустить.",
        parse_mode="HTML",
    )
    await callback.answer()


@router.message(NewLotStates.waiting_blitz_price, F.text)
async def process_blitz_price(message: Message, state: FSMContext) -> None:
    text = message.text.strip()
    blitz_price = None
    if text != "-":
        try:
            blitz_price = int(text.replace(" ", "").replace(",", ""))
            if blitz_price <= 0:
                raise ValueError
        except ValueError:
            await message.answer("❗ Укажите корректную сумму или напишите <code>-</code> чтобы пропустить.", parse_mode="HTML")
            return
    await state.update_data(blitz_price=blitz_price)
    await state.set_state(NewLotStates.waiting_rules)
    await message.answer(
        "📋 <b>Шаг 8/9.</b> Введите <b>правила аукциона</b>.\n\n"
        "Этот текст участники увидят при нажатии кнопки «ℹ️ Инфо» под лотом.\n\n"
        "Напишите <code>-</code> чтобы оставить без правил.",
        parse_mode="HTML",
    )


@router.message(NewLotStates.waiting_rules, F.text)
async def process_rules(message: Message, state: FSMContext) -> None:
    rules = message.text.strip()
    await state.update_data(rules=None if rules == "-" else rules)
    await state.set_state(NewLotStates.waiting_duration)
    await message.answer(
        "⏱ <b>Шаг 9/9.</b> Выберите или введите <b>длительность аукциона</b>.\n\n"
        "Можно ввести число минут вручную или выбрать готовый вариант:",
        parse_mode="HTML",
        reply_markup=duration_keyboard(),
    )


@router.callback_query(NewLotStates.waiting_duration, F.data.startswith("duration:"))
async def process_duration_callback(callback: CallbackQuery, state: FSMContext) -> None:
    value = callback.data.split(":")[1]
    if value == "datetime":
        await state.set_state(NewLotStates.waiting_end_datetime)
        await callback.message.edit_text(
            "📅 <b>Шаг 9/9 — точное время.</b>\n\n"
            "Введите дату и время завершения аукциона по <b>московскому времени (МСК, UTC+3)</b>.\n\n"
            "Формат: <code>ДД.ММ.ГГГГ ЧЧ:ММ</code>\n"
            "Пример: <code>10.05.2026 18:30</code>",
            parse_mode="HTML",
        )
        await callback.answer()
        return

    minutes = int(value)
    await _finalize_duration(callback.message, state, minutes, edit=True)
    await callback.answer()


@router.message(NewLotStates.waiting_duration, F.text)
async def process_duration_text(message: Message, state: FSMContext) -> None:
    try:
        minutes = int(message.text.strip())
        if minutes <= 0:
            raise ValueError
    except ValueError:
        await message.answer("❗ Введите количество минут — целое положительное число.")
        return
    await _finalize_duration(message, state, minutes)


@router.message(NewLotStates.waiting_end_datetime, F.text)
async def process_end_datetime(message: Message, state: FSMContext) -> None:
    raw = message.text.strip()
    try:
        dt_naive = datetime.strptime(raw, "%d.%m.%Y %H:%M")
    except ValueError:
        await message.answer(
            "❗ Неверный формат. Введите дату и время так:\n"
            "<code>ДД.ММ.ГГГГ ЧЧ:ММ</code>\n\n"
            "Пример: <code>10.05.2026 18:30</code>",
            parse_mode="HTML",
        )
        return

    end_time = dt_naive.replace(tzinfo=MOSCOW_TZ).astimezone(timezone.utc)
    now = datetime.now(tz=timezone.utc)
    if end_time <= now:
        await message.answer(
            "❗ Это время уже прошло. Введите будущую дату и время по МСК."
        )
        return

    minutes = max(1, int((end_time - now).total_seconds() / 60))
    time_label = dt_naive.strftime("%d.%m.%Y %H:%M МСК")
    await _finalize_duration(message, state, minutes, edit=False,
                             end_time=end_time, time_label=time_label)


async def _finalize_duration(
    message: Message,
    state: FSMContext,
    minutes: int,
    edit: bool = False,
    end_time: datetime | None = None,
    time_label: str | None = None,
) -> None:
    if end_time is None:
        end_time = datetime.now(tz=timezone.utc) + timedelta(minutes=minutes)
    if time_label is None:
        time_label = f"{minutes} мин"

    await state.update_data(duration_minutes=minutes, end_time=end_time.isoformat())
    await state.set_state(NewLotStates.waiting_confirm)

    data = await state.get_data()
    blitz = data.get("blitz_price")
    blitz_line = f"⚡ Блиц-цена: <b>{blitz:,} ₽</b> (до 10 ставок)\n" if blitz else ""
    rules = data.get("rules")
    rules_line = f"📋 Правила: <i>{rules[:80]}{'…' if len(rules) > 80 else ''}</i>\n" if rules else ""
    bid_variants = data.get("bid_variants", 3)
    variants_line = f"🎯 Вариантов ставки: <b>{bid_variants}</b>\n"
    group_name = data.get("target_group_name", "")
    group_line = f"📢 Канал: <b>{group_name}</b>\n" if group_name and len(GROUPS) > 1 else ""

    preview_text = (
        "👀 <b>Превью лота:</b>\n\n"
        f"🏷 <b>{data['title']}</b>\n\n"
        f"{data.get('description', '') or '<i>Без описания</i>'}\n\n"
        f"💰 Стартовая цена: <b>{data['start_price']:,} ₽</b>\n"
        f"📈 Минимальный шаг: <b>{data['min_step']:,} ₽</b>\n"
        f"{variants_line}"
        f"{blitz_line}"
        f"{rules_line}"
        f"⏱ Завершение: <b>{time_label}</b>\n"
        f"{group_line}\n"
        "Всё верно? Публикуем?"
    )

    if edit:
        await message.edit_text(preview_text, parse_mode="HTML", reply_markup=confirm_lot_keyboard())
    else:
        await message.answer(preview_text, parse_mode="HTML", reply_markup=confirm_lot_keyboard())


@router.callback_query(NewLotStates.waiting_confirm, F.data == "lot_confirm:no")
async def confirm_no(callback: CallbackQuery, state: FSMContext) -> None:
    await state.clear()
    await callback.message.edit_text("❌ Создание лота отменено.")
    await callback.answer()


@router.callback_query(NewLotStates.waiting_confirm, F.data == "lot_confirm:yes")
async def confirm_yes(callback: CallbackQuery, state: FSMContext, bot: Bot) -> None:
    data = await state.get_data()
    await state.clear()

    end_time = datetime.fromisoformat(data["end_time"])
    bid_variants = data.get("bid_variants", 3)
    target_group_id = data.get("target_group_id") or GROUP_ID
    target_group_name = data.get("target_group_name", "")

    lot_id = await db.create_lot(
        title=data["title"],
        description=data.get("description", ""),
        photo_id=data["photo_id"],
        start_price=data["start_price"],
        min_step=data["min_step"],
        end_time=end_time,
        created_by=callback.from_user.id,
        blitz_price=data.get("blitz_price"),
        rules=data.get("rules"),
        bid_variants=bid_variants,
        group_chat_id=target_group_id,
    )

    lot = await db.get_lot(lot_id)
    lot_text = format_lot_message(lot)

    sent = await bot.send_photo(
        chat_id=target_group_id,
        photo=data["photo_id"],
        caption=lot_text,
        parse_mode="HTML",
        reply_markup=lot_keyboard(
            lot_id,
            data["min_step"],
            data.get("blitz_price"),
            0,
            bid_variants=bid_variants,
        ),
    )

    await db.set_lot_message_id(lot_id, sent.message_id)

    # Schedule closing job
    from scheduler import schedule_lot_close
    schedule_lot_close(lot_id, end_time, bot)

    channel_hint = f" ({target_group_name})" if target_group_name else ""
    await callback.message.edit_text(
        f"✅ Лот <b>#{lot_id}</b> опубликован{channel_hint}!\n"
        f"Завершение: <b>{data.get('duration_minutes')} мин</b>",
        parse_mode="HTML",
    )
    await callback.answer("Лот опубликован!")


# ─── Stats helper ─────────────────────────────────────────────────────────────

async def _build_stats_text(lot_id: int) -> str:
    """Build a full statistics message for a lot (works for any status)."""
    lot = await db.get_lot(lot_id)
    if not lot:
        return f"❌ Лот #{lot_id} не найден."

    bids_chrono = await db.get_lot_bids_chrono(lot_id)
    unique_count = await db.get_unique_bidder_count(lot_id)
    total_bids = len(bids_chrono)

    status_map = {"active": "🟢 Активен", "finished": "✅ Завершён", "cancelled": "🚫 Отменён"}
    status_label = status_map.get(lot["status"], lot["status"])

    start = lot["start_price"]
    current = lot["current_price"]
    gain = current - start
    gain_pct = round(gain / start * 100) if start else 0
    gain_line = (
        f"📈 Прирост: <b>+{gain:,} ₽ (+{gain_pct}%)</b>\n".replace(",", " ")
        if gain > 0 else ""
    )

    # Winner info
    winner_line = ""
    if lot.get("winner_id"):
        user = await db.get_user(lot["winner_id"])
        if user:
            name = user.get("full_name") or user.get("username") or f"#{lot['winner_id']}"
            winner_line = f"🏆 Победитель: <b>{name}</b>\n"

    header = (
        f"📊 <b>Статистика лота #{lot_id}</b>\n"
        f"🏷 {lot['title']}\n\n"
        f"Статус: {status_label}\n"
        f"👥 Участников: <b>{unique_count}</b>\n"
        f"🔢 Всего ставок: <b>{total_bids}</b>\n"
        f"💰 Стартовая цена: <b>{start:,} ₽</b>\n".replace(",", " ") +
        f"🔝 Итоговая цена: <b>{current:,} ₽</b>\n".replace(",", " ") +
        gain_line +
        winner_line
    )

    if not bids_chrono:
        return header + "\n<i>Ставок не было.</i>"

    medals = ["🥇", "🥈", "🥉"]
    # Sort by amount desc to assign medals
    sorted_by_amount = sorted(bids_chrono, key=lambda b: b["amount"], reverse=True)
    medal_map = {b["id"]: medals[i] if i < 3 else f"{i + 1}." for i, b in enumerate(sorted_by_amount)}

    lines = []
    for b in reversed(bids_chrono):   # newest first in the list
        name = b.get("full_name") or b.get("username") or f"#{b['user_id']}"
        try:
            dt = datetime.fromisoformat(b["created_at"]).replace(tzinfo=timezone.utc)
            dt_msk = dt.astimezone(MOSCOW_TZ)
            time_str = dt_msk.strftime("%d.%m %H:%M")
        except Exception:
            time_str = "—"
        medal = medal_map.get(b["id"], "•")
        lines.append(f"{medal} <b>{name}</b> — {b['amount']:,} ₽ · {time_str}".replace(",", " "))

    bids_block = "\n📋 <b>История ставок (новые → старые):</b>\n" + "\n".join(lines)
    return header + bids_block


# ─── /lots ────────────────────────────────────────────────────────────────────

@router.message(Command("lots"))
async def cmd_lots(message: Message) -> None:
    if not _admin_only(message):
        return
    lots = await db.get_active_lots()
    if not lots:
        await message.answer("📭 Нет активных лотов.")
        return
    await message.answer(
        f"📋 <b>Активные лоты ({len(lots)}):</b>",
        parse_mode="HTML",
        reply_markup=admin_lots_keyboard(lots),
    )


@router.callback_query(F.data.startswith("admin_lot:"))
async def admin_lot_detail(callback: CallbackQuery) -> None:
    if not _is_admin(callback.from_user.id):
        await callback.answer("⛔ Нет доступа.", show_alert=True)
        return
    lot_id = int(callback.data.split(":")[1])
    lot = await db.get_lot(lot_id)
    if not lot:
        await callback.answer("Лот не найден.", show_alert=True)
        return

    bids = await db.get_lot_bids(lot_id)
    text = (
        f"<b>Лот #{lot_id}: {lot['title']}</b>\n\n"
        f"Статус: <b>{lot['status']}</b>\n"
        f"Текущая цена: <b>{lot['current_price']:,} ₽</b>\n"
        f"Ставок: <b>{len(bids)}</b>"
    )
    await callback.message.edit_text(
        text,
        parse_mode="HTML",
        reply_markup=admin_lot_actions_keyboard(lot_id),
    )
    await callback.answer()


@router.callback_query(F.data.startswith("admin_bids:"))
async def admin_lot_bids(callback: CallbackQuery) -> None:
    if not _is_admin(callback.from_user.id):
        await callback.answer("⛔ Нет доступа.", show_alert=True)
        return
    lot_id = int(callback.data.split(":")[1])
    text = await _build_stats_text(lot_id)
    await callback.message.answer(text, parse_mode="HTML")
    await callback.answer()


@router.callback_query(F.data.startswith("admin_cancel:"))
async def admin_cancel_lot(callback: CallbackQuery, bot: Bot) -> None:
    if not _is_admin(callback.from_user.id):
        await callback.answer("⛔ Нет доступа.", show_alert=True)
        return
    lot_id = int(callback.data.split(":")[1])
    lot = await db.get_lot(lot_id)
    if not lot or lot["status"] != "active":
        await callback.answer("Лот не активен.", show_alert=True)
        return

    await db.cancel_lot(lot_id)

    # Update group message
    if lot["group_message_id"]:
        lot_updated = await db.get_lot(lot_id)
        lot_group_id = lot.get("group_chat_id") or GROUP_ID
        cancelled_text = format_lot_message(lot_updated)
        try:
            await bot.edit_message_caption(
                chat_id=lot_group_id,
                message_id=lot["group_message_id"],
                caption=cancelled_text,
                parse_mode="HTML",
            )
        except Exception:
            pass

    await callback.message.edit_text(f"🚫 Лот #{lot_id} отменён.")
    await callback.answer("Лот отменён.")


@router.callback_query(F.data == "admin_back")
async def admin_back(callback: CallbackQuery) -> None:
    if not _is_admin(callback.from_user.id):
        await callback.answer()
        return
    lots = await db.get_active_lots()
    if not lots:
        await callback.message.edit_text("📭 Нет активных лотов.")
    else:
        await callback.message.edit_text(
            f"📋 <b>Активные лоты ({len(lots)}):</b>",
            parse_mode="HTML",
            reply_markup=admin_lots_keyboard(lots),
        )
    await callback.answer()


# ─── /stats <id> ──────────────────────────────────────────────────────────────

@router.message(Command("stats"))
async def cmd_stats(message: Message) -> None:
    if not _admin_only(message):
        return
    parts = message.text.split()
    if len(parts) < 2 or not parts[1].isdigit():
        await message.answer("Использование: /stats &lt;id лота&gt;", parse_mode="HTML")
        return
    lot_id = int(parts[1])
    text = await _build_stats_text(lot_id)
    await message.answer(text, parse_mode="HTML")


# ─── /cancel <id> ─────────────────────────────────────────────────────────────

@router.message(Command("cancel"))
async def cmd_cancel(message: Message, bot: Bot) -> None:
    if not _admin_only(message):
        return
    parts = message.text.split()
    if len(parts) < 2 or not parts[1].isdigit():
        await message.answer("Использование: /cancel &lt;id лота&gt;", parse_mode="HTML")
        return

    lot_id = int(parts[1])
    lot = await db.get_lot(lot_id)
    if not lot:
        await message.answer(f"Лот #{lot_id} не найден.")
        return
    if lot["status"] != "active":
        await message.answer(f"Лот #{lot_id} не активен (статус: {lot['status']}).")
        return

    await db.cancel_lot(lot_id)

    if lot["group_message_id"]:
        lot_updated = await db.get_lot(lot_id)
        try:
            await bot.edit_message_caption(
                chat_id=GROUP_ID,
                message_id=lot["group_message_id"],
                caption=format_lot_message(lot_updated),
                parse_mode="HTML",
            )
        except Exception:
            pass

    await message.answer(f"✅ Лот #{lot_id} успешно отменён.")
