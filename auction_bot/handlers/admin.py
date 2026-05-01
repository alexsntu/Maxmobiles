from datetime import datetime, timedelta, timezone
from typing import Any

from aiogram import Bot, F, Router
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext
from aiogram.types import (
    CallbackQuery,
    InputMediaPhoto,
    Message,
)

import database as db
from config import ADMIN_IDS, GROUP_ID
from keyboards import (
    admin_lot_actions_keyboard,
    admin_lots_keyboard,
    confirm_lot_keyboard,
    duration_keyboard,
    lot_keyboard,
)
from states import NewLotStates
from utils import format_lot_message

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
    await state.set_state(NewLotStates.waiting_photo)
    await message.answer(
        "📸 <b>Шаг 1/6.</b> Отправьте фото товара.",
        parse_mode="HTML",
    )


@router.message(NewLotStates.waiting_photo, F.photo)
async def process_photo(message: Message, state: FSMContext) -> None:
    photo_id = message.photo[-1].file_id
    await state.update_data(photo_id=photo_id)
    await state.set_state(NewLotStates.waiting_title)
    await message.answer(
        "✏️ <b>Шаг 2/6.</b> Введите <b>название</b> лота (до 100 символов).",
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
        "📝 <b>Шаг 3/6.</b> Введите <b>описание</b> лота.\n"
        "Напишите <code>-</code> чтобы оставить без описания.",
        parse_mode="HTML",
    )


@router.message(NewLotStates.waiting_description, F.text)
async def process_description(message: Message, state: FSMContext) -> None:
    desc = message.text.strip()
    await state.update_data(description="" if desc == "-" else desc)
    await state.set_state(NewLotStates.waiting_start_price)
    await message.answer(
        "💰 <b>Шаг 4/6.</b> Укажите <b>стартовую цену</b> в рублях (целое число).",
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
        "📈 <b>Шаг 5/6.</b> Укажите <b>минимальный шаг ставки</b> в рублях.",
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
    await state.set_state(NewLotStates.waiting_blitz_price)
    await message.answer(
        "⚡ <b>Шаг 6/7.</b> Укажите <b>блиц-цену</b> (купить сейчас).\n\n"
        "Эта кнопка позволяет сразу выиграть лот по фиксированной цене "
        "и исчезает после 10 ставок.\n\n"
        "Введите сумму в рублях или напишите <code>-</code> чтобы пропустить.",
        parse_mode="HTML",
    )


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
    await state.set_state(NewLotStates.waiting_duration)
    await message.answer(
        "⏱ <b>Шаг 7/7.</b> Выберите или введите <b>длительность аукциона</b>.\n\n"
        "Можно ввести число минут вручную или выбрать готовый вариант:",
        parse_mode="HTML",
        reply_markup=duration_keyboard(),
    )


@router.callback_query(NewLotStates.waiting_duration, F.data.startswith("duration:"))
async def process_duration_callback(callback: CallbackQuery, state: FSMContext) -> None:
    minutes = int(callback.data.split(":")[1])
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


async def _finalize_duration(
    message: Message, state: FSMContext, minutes: int, edit: bool = False
) -> None:
    end_time = datetime.now(tz=timezone.utc) + timedelta(minutes=minutes)
    await state.update_data(duration_minutes=minutes, end_time=end_time.isoformat())
    await state.set_state(NewLotStates.waiting_confirm)

    data = await state.get_data()
    blitz = data.get("blitz_price")
    blitz_line = f"⚡ Блиц-цена: <b>{blitz:,} ₽</b> (до 10 ставок)\n" if blitz else ""

    preview_text = (
        "👀 <b>Превью лота:</b>\n\n"
        f"🏷 <b>{data['title']}</b>\n\n"
        f"{data.get('description', '') or '<i>Без описания</i>'}\n\n"
        f"💰 Стартовая цена: <b>{data['start_price']:,} ₽</b>\n"
        f"📈 Минимальный шаг: <b>{data['min_step']:,} ₽</b>\n"
        f"{blitz_line}"
        f"⏱ Длительность: <b>{minutes} мин</b>\n\n"
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

    lot_id = await db.create_lot(
        title=data["title"],
        description=data.get("description", ""),
        photo_id=data["photo_id"],
        start_price=data["start_price"],
        min_step=data["min_step"],
        end_time=end_time,
        created_by=callback.from_user.id,
        blitz_price=data.get("blitz_price"),
    )

    lot = await db.get_lot(lot_id)
    lot_text = format_lot_message(lot)

    sent = await bot.send_photo(
        chat_id=GROUP_ID,
        photo=data["photo_id"],
        caption=lot_text,
        parse_mode="HTML",
        reply_markup=lot_keyboard(lot_id, data["min_step"], data.get("blitz_price"), 0),
    )

    await db.set_lot_message_id(lot_id, sent.message_id)

    # Schedule closing job
    from scheduler import schedule_lot_close
    schedule_lot_close(lot_id, end_time, bot)

    await callback.message.edit_text(
        f"✅ Лот <b>#{lot_id}</b> опубликован в группе!\n"
        f"Окончание: <b>{data['duration_minutes']} мин</b>",
        parse_mode="HTML",
    )
    await callback.answer("Лот опубликован!")


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
    bids = await db.get_lot_bids(lot_id)
    if not bids:
        await callback.answer("Ставок ещё нет.", show_alert=True)
        return
    lines = [f"{i+1}. {b['full_name'] or b['username'] or b['user_id']} — {b['amount']:,} ₽"
             for i, b in enumerate(bids[:20])]
    await callback.answer("\n".join(lines), show_alert=True)


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
        cancelled_text = format_lot_message(lot_updated)
        try:
            await bot.edit_message_caption(
                chat_id=GROUP_ID,
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
