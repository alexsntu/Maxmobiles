"""
Admin handlers: lot creation wizard (FSM via PM) + /lots, /stats, /cancel commands.

Admin commands (send in PM to the community):
  newlot        — start lot creation wizard
  lots          — list active lots
  stats <N>     — statistics for lot #N
  cancel <N>    — cancel lot #N
"""

import json
import logging
import random
from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo

import aiohttp

import database as db
from config import ADMIN_IDS, GROUP_ID, TIMEZONE
from keyboards import (
    admin_lot_actions_keyboard,
    admin_lots_keyboard,
    confirm_lot_keyboard,
    duration_keyboard,
)
from states import NewLotStates, clear_data, get_data, update_data
from utils import format_lot_post, format_winner_announcement, seconds_until
from vkbottle.bot import BotLabeler, Message

logger = logging.getLogger(__name__)
labeler = BotLabeler()

MOSCOW_TZ = ZoneInfo("Europe/Moscow")


# ─── Helper ───────────────────────────────────────────────────────────────────

def _is_admin(user_id: int) -> bool:
    return user_id in ADMIN_IDS


async def _send(message: Message, text: str, keyboard: str | None = None) -> None:
    kwargs: dict = {"random_id": random.randint(1, 2**31), "message": text}
    if keyboard is not None:
        kwargs["keyboard"] = keyboard
    await message.ctx_api.messages.send(peer_id=message.peer_id, **kwargs)


async def _upload_photo_for_wall(api, owner_id: int, photo_id: int) -> str:
    """Download a photo from VK and re-upload it to the community wall album."""
    photos = await api.photos.get_by_id(photos=f"{owner_id}_{photo_id}")
    if not photos:
        raise ValueError("Photo not found")

    photo = photos[0]
    sizes = sorted(photo.sizes, key=lambda s: getattr(s, "width", 0) * getattr(s, "height", 0), reverse=True)
    photo_url = sizes[0].url

    upload_info = await api.photos.get_wall_upload_server(group_id=GROUP_ID)
    upload_url = upload_info.upload_url

    async with aiohttp.ClientSession() as session:
        async with session.get(photo_url) as resp:
            photo_bytes = await resp.read()

        form = aiohttp.FormData()
        form.add_field("photo", photo_bytes, filename="photo.jpg", content_type="image/jpeg")
        async with session.post(upload_url, data=form) as resp:
            result = await resp.json()

    saved = await api.photos.save_wall_photo(
        group_id=GROUP_ID,
        photo=result["photo"],
        server=result["server"],
        hash=result["hash"],
    )
    p = saved[0]
    return f"photo{p.owner_id}_{p.id}"


# ─── newlot ───────────────────────────────────────────────────────────────────

@labeler.message(text=["newlot", "/newlot", "новый лот", "новыйлот"])
async def cmd_newlot(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    clear_data(message.peer_id)
    await message.state_peer.set(NewLotStates.waiting_photo)
    await _send(message, "📸 Шаг 1/9. Отправьте фото товара.")


# ─── Photo ────────────────────────────────────────────────────────────────────

@labeler.message(state=NewLotStates.waiting_photo)
async def process_photo(message: Message) -> None:
    if not _is_admin(message.from_id):
        return

    photo_att = None
    for att in (message.attachments or []):
        if att.type.value == "photo":
            photo_att = att.photo
            break

    if photo_att is None:
        await _send(message, "❗ Пожалуйста, отправьте фото товара.")
        return

    update_data(message.peer_id,
                photo_owner_id=photo_att.owner_id,
                photo_id=photo_att.id)
    await message.state_peer.set(NewLotStates.waiting_title)
    await _send(message, "✏️ Шаг 2/9. Введите название лота (до 100 символов).")


# ─── Title ────────────────────────────────────────────────────────────────────

@labeler.message(state=NewLotStates.waiting_title)
async def process_title(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    title = (message.text or "").strip()
    if not title:
        await _send(message, "❗ Введите название текстом.")
        return
    if len(title) > 100:
        await _send(message, "❗ Название слишком длинное. Максимум 100 символов.")
        return
    update_data(message.peer_id, title=title)
    await message.state_peer.set(NewLotStates.waiting_description)
    await _send(message, "📝 Шаг 3/9. Введите описание лота.\nНапишите «-» чтобы оставить без описания.")


# ─── Description ──────────────────────────────────────────────────────────────

@labeler.message(state=NewLotStates.waiting_description)
async def process_description(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    desc = (message.text or "").strip()
    if not desc:
        await _send(message, "❗ Введите описание текстом или напишите «-».")
        return
    update_data(message.peer_id, description="" if desc == "-" else desc)
    await message.state_peer.set(NewLotStates.waiting_start_price)
    await _send(message, "💰 Шаг 4/9. Укажите стартовую цену в рублях (целое число).")


# ─── Start price ──────────────────────────────────────────────────────────────

@labeler.message(state=NewLotStates.waiting_start_price)
async def process_start_price(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    try:
        price = int((message.text or "").strip().replace(" ", "").replace(",", ""))
        if price <= 0:
            raise ValueError
    except ValueError:
        await _send(message, "❗ Укажите корректную цену — целое положительное число.")
        return
    update_data(message.peer_id, start_price=price)
    await message.state_peer.set(NewLotStates.waiting_min_step)
    await _send(message, "📈 Шаг 5/9. Укажите минимальный шаг ставки в рублях.")


# ─── Min step ─────────────────────────────────────────────────────────────────

@labeler.message(state=NewLotStates.waiting_min_step)
async def process_min_step(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    try:
        step = int((message.text or "").strip().replace(" ", "").replace(",", ""))
        if step <= 0:
            raise ValueError
    except ValueError:
        await _send(message, "❗ Укажите корректный шаг — целое положительное число.")
        return
    update_data(message.peer_id, min_step=step)
    await message.state_peer.set(NewLotStates.waiting_blitz_price)
    await _send(
        message,
        "⚡ Шаг 6/9. Укажите блиц-цену (купить сейчас).\n\n"
        "Участник купит лот мгновенно, написав «блиц» в комментарии.\n"
        "Кнопка исчезает после 10 ставок.\n\n"
        "Введите сумму в рублях или напишите «-» чтобы пропустить.",
    )


# ─── Blitz price ──────────────────────────────────────────────────────────────

@labeler.message(state=NewLotStates.waiting_blitz_price)
async def process_blitz_price(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    text = (message.text or "").strip()
    blitz_price = None
    if text != "-":
        try:
            blitz_price = int(text.replace(" ", "").replace(",", ""))
            if blitz_price <= 0:
                raise ValueError
        except ValueError:
            await _send(message, "❗ Укажите корректную сумму или напишите «-» чтобы пропустить.")
            return
    update_data(message.peer_id, blitz_price=blitz_price)
    await message.state_peer.set(NewLotStates.waiting_rules)
    await _send(
        message,
        "📋 Шаг 7/9. Введите правила аукциона.\n\n"
        "Этот текст будет добавлен в пост с лотом.\n\n"
        "Напишите «-» чтобы оставить без правил.",
    )


# ─── Rules ────────────────────────────────────────────────────────────────────

@labeler.message(state=NewLotStates.waiting_rules)
async def process_rules(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    rules = (message.text or "").strip()
    if not rules:
        await _send(message, "❗ Введите правила текстом или напишите «-».")
        return
    update_data(message.peer_id, rules=None if rules == "-" else rules)
    await message.state_peer.set(NewLotStates.waiting_duration)
    update_data(message.peer_id, _awaiting_cb="duration")
    await _send(
        message,
        "⏱ Шаг 8/9. Выберите длительность аукциона\nили введите число минут вручную:",
        keyboard=duration_keyboard(),
    )


# ─── Duration (text input — manual minutes) ────────────────────────────────────

@labeler.message(state=NewLotStates.waiting_duration)
async def process_duration_text(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    text = (message.text or "").strip()
    if not text.isdigit():
        await _send(message, "❗ Введите количество минут — целое положительное число.")
        return
    minutes = int(text)
    if minutes <= 0:
        await _send(message, "❗ Введите количество минут — целое положительное число.")
        return
    await _finalize_duration(message, minutes)


@labeler.message(state=NewLotStates.waiting_end_datetime)
async def process_end_datetime(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    raw = (message.text or "").strip()
    try:
        dt_naive = datetime.strptime(raw, "%d.%m.%Y %H:%M")
    except ValueError:
        await _send(message, "❗ Неверный формат. Введите дату и время так:\nДД.ММ.ГГГГ ЧЧ:ММ\n\nПример: 10.05.2026 18:30")
        return

    end_time = dt_naive.replace(tzinfo=MOSCOW_TZ).astimezone(timezone.utc)
    now = datetime.now(tz=timezone.utc)
    if end_time <= now:
        await _send(message, "❗ Это время уже прошло. Введите будущую дату и время по МСК.")
        return

    minutes = max(1, int((end_time - now).total_seconds() / 60))
    time_label = dt_naive.strftime("%d.%m.%Y %H:%M МСК")
    await _finalize_duration(message, minutes, end_time=end_time, time_label=time_label)


async def _finalize_duration(
    message: Message,
    minutes: int,
    end_time: datetime | None = None,
    time_label: str | None = None,
) -> None:
    if end_time is None:
        end_time = datetime.now(tz=timezone.utc) + timedelta(minutes=minutes)
    if time_label is None:
        time_label = f"{minutes} мин"

    update_data(message.peer_id,
                duration_minutes=minutes,
                end_time=end_time.isoformat(),
                time_label=time_label)
    await message.state_peer.set(NewLotStates.waiting_confirm)
    update_data(message.peer_id, _awaiting_cb="lot_confirm")

    data = get_data(message.peer_id)
    preview = _build_preview(data, time_label)
    await _send(message, preview, keyboard=confirm_lot_keyboard())


def _build_preview(data: dict, time_label: str | None = None) -> str:
    blitz = data.get("blitz_price")
    blitz_line = f"⚡ Блиц-цена: {blitz:,} ₽ (до 10 ставок)\n".replace(",", " ") if blitz else ""
    rules = data.get("rules")
    rules_line = f"📋 Правила: {rules[:80]}{'…' if len(rules) > 80 else ''}\n" if rules else ""
    tl = time_label or data.get("time_label", "?")
    return (
        "👀 Превью лота:\n\n"
        f"🏷 {data['title']}\n\n"
        f"{data.get('description', '') or '(без описания)'}\n\n"
        f"💰 Стартовая цена: {data['start_price']:,} ₽\n".replace(",", " ") +
        f"📈 Минимальный шаг: {data['min_step']:,} ₽\n".replace(",", " ") +
        f"{blitz_line}"
        f"{rules_line}"
        f"⏱ Завершение: {tl}\n\n"
        "Всё верно? Публикуем?"
    )


# ─── lots ─────────────────────────────────────────────────────────────────────

@labeler.message(text=["lots", "/lots", "лоты", "активные лоты"])
async def cmd_lots(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    lots = await db.get_active_lots()
    if not lots:
        await _send(message, "📭 Нет активных лотов.")
        return
    await _send(
        message,
        f"📋 Активные лоты ({len(lots)}):",
        keyboard=admin_lots_keyboard(lots),
    )


# ─── stats <N> ────────────────────────────────────────────────────────────────

@labeler.message(text_startswith=["stats ", "/stats ", "статистика "])
async def cmd_stats(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    parts = (message.text or "").split()
    if len(parts) < 2 or not parts[-1].isdigit():
        await _send(message, "Использование: stats <id лота>")
        return
    lot_id = int(parts[-1])
    text = await _build_stats_text(lot_id)
    await _send(message, text)


# ─── cancel <N> ───────────────────────────────────────────────────────────────

@labeler.message(text_startswith=["cancel ", "/cancel ", "отменить лот ", "отмена лота "])
async def cmd_cancel(message: Message) -> None:
    if not _is_admin(message.from_id):
        return
    parts = (message.text or "").split()
    if len(parts) < 2 or not parts[-1].isdigit():
        await _send(message, "Использование: cancel <id лота>")
        return
    lot_id = int(parts[-1])
    lot = await db.get_lot(lot_id)
    if not lot:
        await _send(message, f"Лот #{lot_id} не найден.")
        return
    if lot["status"] != "active":
        await _send(message, f"Лот #{lot_id} не активен (статус: {lot['status']}).")
        return

    await db.cancel_lot(lot_id)
    if lot.get("wall_post_id"):
        lot_updated = await db.get_lot(lot_id)
        try:
            await message.ctx_api.wall.edit(
                owner_id=-GROUP_ID,
                post_id=lot["wall_post_id"],
                message=format_lot_post(lot_updated),
                attachments=lot["photo_att"],
            )
        except Exception:
            pass

    from scheduler import scheduler, _job_id
    job = scheduler.get_job(_job_id(lot_id))
    if job:
        job.remove()

    await _send(message, f"✅ Лот #{lot_id} успешно отменён.")


# ─── Stats helper ─────────────────────────────────────────────────────────────

async def _build_stats_text(lot_id: int) -> str:
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
    gain_line = f"📈 Прирост: +{gain:,} ₽ (+{gain_pct}%)\n".replace(",", " ") if gain > 0 else ""

    winner_line = ""
    if lot.get("winner_id"):
        user = await db.get_user(lot["winner_id"])
        if user:
            winner_line = f"🏆 Победитель: {user['full_name'] or f'id{lot[\"winner_id\"]}'}\n"

    header = (
        f"📊 Статистика лота #{lot_id}\n"
        f"🏷 {lot['title']}\n\n"
        f"Статус: {status_label}\n"
        f"👥 Участников: {unique_count}\n"
        f"🔢 Всего ставок: {total_bids}\n"
        f"💰 Стартовая цена: {start:,} ₽\n".replace(",", " ") +
        f"🔝 Итоговая цена: {current:,} ₽\n".replace(",", " ") +
        gain_line +
        winner_line
    )

    if not bids_chrono:
        return header + "\nСтавок не было."

    medals = ["🥇", "🥈", "🥉"]
    sorted_by_amount = sorted(bids_chrono, key=lambda b: b["amount"], reverse=True)
    medal_map = {b["id"]: medals[i] if i < 3 else f"{i + 1}." for i, b in enumerate(sorted_by_amount)}

    lines = []
    for b in reversed(bids_chrono):
        name = b.get("full_name") or f"id{b['user_id']}"
        try:
            dt = datetime.fromisoformat(b["created_at"]).replace(tzinfo=timezone.utc)
            dt_msk = dt.astimezone(ZoneInfo(TIMEZONE))
            time_str = dt_msk.strftime("%d.%m %H:%M")
        except Exception:
            time_str = "—"
        medal = medal_map.get(b["id"], "•")
        lines.append(f"{medal} {name} — {b['amount']:,} ₽ · {time_str}".replace(",", " "))

    bids_block = "\nИстория ставок (новые → старые):\n" + "\n".join(lines)
    return header + bids_block


# ─── Inline keyboard callback dispatcher ──────────────────────────────────────

async def handle_callback(event: dict, bot) -> None:
    """
    Called from bot.py for GroupEventType.MESSAGE_EVENT.
    Routes callbacks from the admin wizard keyboards.
    """
    obj = event if "user_id" in event else event.get("object", event)
    user_id: int = obj.get("user_id", 0)
    peer_id: int = obj.get("peer_id", user_id)
    event_id: str = obj.get("event_id", "")
    conv_msg_id: int = obj.get("conversation_message_id", 0)
    raw_payload = obj.get("payload", {})

    payload: dict = raw_payload if isinstance(raw_payload, dict) else {}
    if isinstance(raw_payload, str):
        try:
            payload = json.loads(raw_payload)
        except Exception:
            pass

    if not _is_admin(user_id):
        return

    # Always answer to stop the loading spinner
    try:
        await bot.api.messages.send_message_event_answer(
            event_id=event_id,
            user_id=user_id,
            peer_id=peer_id,
        )
    except Exception:
        pass

    cmd = payload.get("cmd", "")
    data = get_data(peer_id)
    awaiting = data.get("_awaiting_cb", "")

    async def reply(text: str, keyboard: str | None = None) -> None:
        kw: dict = {"random_id": random.randint(1, 2**31), "message": text}
        if keyboard is not None:
            kw["keyboard"] = keyboard
        await bot.api.messages.send(peer_id=peer_id, **kw)

    # ── Duration picker ────────────────────────────────────────────────────────
    if cmd == "duration" and awaiting == "duration":
        value = payload.get("v")
        if value == "datetime":
            state_peer = await bot.state_dispenser.get(peer_id)
            await bot.state_dispenser.set(peer_id, NewLotStates.waiting_end_datetime)
            await reply(
                "📅 Шаг 8/9 — точное время.\n\n"
                "Введите дату и время завершения аукциона по московскому времени (МСК, UTC+3).\n\n"
                "Формат: ДД.ММ.ГГГГ ЧЧ:ММ\n"
                "Пример: 10.05.2026 18:30"
            )
            return

        minutes = int(value)
        end_time = datetime.now(tz=timezone.utc) + timedelta(minutes=minutes)
        time_label = f"{minutes} мин"
        update_data(peer_id,
                    duration_minutes=minutes,
                    end_time=end_time.isoformat(),
                    time_label=time_label,
                    _awaiting_cb="lot_confirm")
        await bot.state_dispenser.set(peer_id, NewLotStates.waiting_confirm)
        preview = _build_preview(get_data(peer_id), time_label)
        await reply(preview, keyboard=confirm_lot_keyboard())

    # ── Lot confirm ───────────────────────────────────────────────────────────
    elif cmd == "lot_confirm" and awaiting == "lot_confirm":
        if payload.get("v") == "no":
            await bot.state_dispenser.delete(peer_id)
            clear_data(peer_id)
            await reply("❌ Создание лота отменено.")
            return

        # Publish lot
        data = get_data(peer_id)
        await bot.state_dispenser.delete(peer_id)
        clear_data(peer_id)

        await reply("⏳ Публикую лот…")

        try:
            photo_att = await _upload_photo_for_wall(
                bot.api, data["photo_owner_id"], data["photo_id"]
            )
        except Exception as e:
            logger.exception("Photo upload failed")
            await reply(f"❌ Не удалось загрузить фото: {e}")
            return

        end_time = datetime.fromisoformat(data["end_time"])
        lot_id = await db.create_lot(
            title=data["title"],
            description=data.get("description", ""),
            photo_att=photo_att,
            start_price=data["start_price"],
            min_step=data["min_step"],
            end_time=end_time,
            created_by=user_id,
            blitz_price=data.get("blitz_price"),
            rules=data.get("rules"),
        )

        lot = await db.get_lot(lot_id)
        post_text = format_lot_post(lot)
        if lot.get("rules"):
            post_text += f"\n\n📋 Правила:\n{lot['rules']}"

        result = await bot.api.wall.post(
            owner_id=-GROUP_ID,
            message=post_text,
            attachments=photo_att,
            from_group=1,
            close_comments=0,
        )
        wall_post_id = result.post_id
        await db.set_lot_wall_post_id(lot_id, wall_post_id)

        # Pin the post so it's always visible
        try:
            await bot.api.wall.pin(owner_id=-GROUP_ID, post_id=wall_post_id)
        except Exception:
            pass

        from scheduler import schedule_lot_close
        schedule_lot_close(lot_id, end_time, bot)

        await reply(
            f"✅ Лот #{lot_id} опубликован!\n"
            f"Завершение через: {data.get('time_label', '?')}"
        )

    # ── Admin lot detail ───────────────────────────────────────────────────────
    elif cmd == "admin_lot":
        lot_id = payload.get("id", 0)
        lot = await db.get_lot(lot_id)
        if not lot:
            await reply("Лот не найден.")
            return
        bids = await db.get_lot_bids(lot_id)
        text = (
            f"Лот #{lot_id}: {lot['title']}\n\n"
            f"Статус: {lot['status']}\n"
            f"Текущая цена: {lot['current_price']:,} ₽\n".replace(",", " ") +
            f"Ставок: {len(bids)}"
        )
        await reply(text, keyboard=admin_lot_actions_keyboard(lot_id))

    # ── Admin stats ────────────────────────────────────────────────────────────
    elif cmd == "admin_stats":
        lot_id = payload.get("id", 0)
        text = await _build_stats_text(lot_id)
        await reply(text)

    # ── Admin cancel ───────────────────────────────────────────────────────────
    elif cmd == "admin_cancel":
        lot_id = payload.get("id", 0)
        lot = await db.get_lot(lot_id)
        if not lot or lot["status"] != "active":
            await reply("Лот не активен.")
            return

        await db.cancel_lot(lot_id)
        if lot.get("wall_post_id"):
            lot_updated = await db.get_lot(lot_id)
            try:
                await bot.api.wall.edit(
                    owner_id=-GROUP_ID,
                    post_id=lot["wall_post_id"],
                    message=format_lot_post(lot_updated),
                    attachments=lot["photo_att"],
                )
            except Exception:
                pass

        from scheduler import scheduler, _job_id
        job = scheduler.get_job(_job_id(lot_id))
        if job:
            job.remove()

        await reply(f"🚫 Лот #{lot_id} отменён.")

    # ── Back to lots list ──────────────────────────────────────────────────────
    elif cmd == "admin_back":
        lots = await db.get_active_lots()
        if not lots:
            await reply("📭 Нет активных лотов.")
        else:
            await reply(f"📋 Активные лоты ({len(lots)}):", keyboard=admin_lots_keyboard(lots))
