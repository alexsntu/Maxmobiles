"""
Comment handler — processes bids placed as wall post comments.

Bid commands (write in the post comments):
  <number>        — place a bid (e.g. "1500")
  блиц / blitz    — instant blitz purchase (if available)
  отмена / cancel — cancel your top bid (leader only)
"""

import logging
import random
import re
from datetime import datetime, timedelta, timezone

import database as db
from config import ANTI_SNIPE_SECONDS, GROUP_ID
from utils import (
    BLITZ_MAX_BIDS,
    format_lot_post,
    format_winner_announcement,
    seconds_until,
    vk_mention,
)

logger = logging.getLogger(__name__)

_NUMBER_RE = re.compile(r"^\s*(\d[\d\s,]*)\s*$")


async def _get_user_name(api, vk_id: int) -> str:
    """Fetch user's full name from VK API."""
    try:
        users = await api.users.get(user_ids=[vk_id])
        if users:
            u = users[0]
            return f"{u.first_name} {u.last_name}".strip()
    except Exception:
        pass
    return f"id{vk_id}"


async def _reply_to_comment(api, post_id: int, comment_id: int, text: str) -> None:
    try:
        await api.wall.create_comment(
            owner_id=-GROUP_ID,
            post_id=post_id,
            reply_to_comment=comment_id,
            message=text,
            from_group=GROUP_ID,
        )
    except Exception:
        logger.exception("Failed to reply to comment %d", comment_id)


async def _update_post(api, lot: dict, top_bids: list[dict], bid_count: int, extra: str = "") -> None:
    post_text = format_lot_post(lot, top_bids=top_bids[:3], bid_count=bid_count)
    if lot.get("rules"):
        post_text += f"\n\n📋 Правила:\n{lot['rules']}"
    if extra:
        post_text += f"\n\n{extra}"
    try:
        await api.wall.edit(
            owner_id=-GROUP_ID,
            post_id=lot["wall_post_id"],
            message=post_text,
            attachments=lot["photo_att"],
        )
    except Exception:
        logger.exception("Failed to update wall post for lot #%d", lot["id"])


async def handle_comment(event: dict, bot) -> None:
    """
    Entry point called from bot.py on GroupEventType.WALL_REPLY_NEW.
    The event dict is the raw Long Poll object (the "object" field of the event).
    """
    api = bot.api

    # Extract fields — handle both flat and nested {"object": {...}} forms
    obj = event if "from_id" in event else event.get("object", event)

    from_id: int = obj.get("from_id", 0)
    post_id: int = obj.get("post_id", 0)
    comment_id: int = obj.get("id", 0)
    text: str = (obj.get("text") or "").strip()

    # Ignore bot's own comments
    if from_id < 0:
        return

    if not text or not post_id:
        return

    # Find which active lot this post belongs to
    active_lots = await db.get_active_lots()
    lot = next((l for l in active_lots if l.get("wall_post_id") == post_id), None)
    if lot is None:
        return  # comment on non-auction post — ignore

    lot_id = lot["id"]
    text_lower = text.lower()

    # ── Blitz purchase ────────────────────────────────────────────────────────
    if text_lower in ("блиц", "blitz", "блиц!", "blitz!"):
        await _handle_blitz(api, bot, lot, lot_id, from_id, post_id, comment_id)
        return

    # ── Bid cancellation ─────────────────────────────────────────────────────
    if text_lower in ("отмена", "cancel", "отмена!", "отменить", "отменить ставку"):
        await _handle_cancel_bid(api, lot, lot_id, from_id, post_id, comment_id)
        return

    # ── Number bid ────────────────────────────────────────────────────────────
    m = _NUMBER_RE.match(text)
    if m:
        try:
            amount = int(m.group(1).replace(" ", "").replace(",", ""))
        except ValueError:
            return
        await _handle_bid(api, bot, lot, lot_id, from_id, amount, post_id, comment_id)


# ─── Place bid ────────────────────────────────────────────────────────────────

async def _handle_bid(
    api, bot, lot: dict, lot_id: int,
    from_id: int, amount: int,
    post_id: int, comment_id: int,
) -> None:
    if lot["status"] != "active":
        await _reply_to_comment(api, post_id, comment_id, "⏹ Аукцион уже завершён.")
        return

    remaining = seconds_until(lot["end_time"])
    if remaining <= 0:
        await _reply_to_comment(api, post_id, comment_id, "⏹ Время аукциона истекло.")
        return

    if lot.get("winner_id") == from_id:
        await _reply_to_comment(
            api, post_id, comment_id,
            "⏳ Вы уже лидируете! Дождитесь ставки другого участника."
        )
        return

    min_bid = lot["current_price"] + lot["min_step"]
    if amount < min_bid:
        await _reply_to_comment(
            api, post_id, comment_id,
            f"❗ Ставка слишком мала. Минимальная: {min_bid:,} ₽ "
            f"(текущая {lot['current_price']:,} + шаг {lot['min_step']:,} ₽)".replace(",", " ")
        )
        return

    full_name = await _get_user_name(api, from_id)
    await db.upsert_user(from_id, full_name)
    await db.add_bid(lot_id, from_id, amount)
    await db.update_lot_bid(lot_id, amount, from_id)

    # Anti-snipe
    extended = False
    if remaining < ANTI_SNIPE_SECONDS:
        new_end = datetime.now(tz=timezone.utc) + timedelta(seconds=ANTI_SNIPE_SECONDS)
        await db.extend_lot_time(lot_id, new_end)
        from scheduler import schedule_lot_close
        schedule_lot_close(lot_id, new_end, bot)
        extended = True

    lot_updated = await db.get_lot(lot_id)
    top_bids = await db.get_lot_bids(lot_id)
    bid_count = await db.count_bids(lot_id)

    extra = f"⚡ Таймер продлён на {ANTI_SNIPE_SECONDS} сек!" if extended else ""
    await _update_post(api, lot_updated, top_bids, bid_count, extra)

    mention = vk_mention(from_id, full_name)
    confirm = f"✅ {mention}, ставка {amount:,} ₽ принята!".replace(",", " ")
    if extended:
        confirm += f" ⚡ Таймер продлён."
    await _reply_to_comment(api, post_id, comment_id, confirm)


# ─── Blitz purchase ────────────────────────────────────────────────────────────

async def _handle_blitz(api, bot, lot: dict, lot_id: int, from_id: int, post_id: int, comment_id: int) -> None:
    if lot["status"] != "active":
        await _reply_to_comment(api, post_id, comment_id, "⏹ Аукцион уже завершён.")
        return

    blitz_price = lot.get("blitz_price")
    if not blitz_price:
        await _reply_to_comment(api, post_id, comment_id, "❗ Блиц-цена для этого лота не установлена.")
        return

    bid_count = await db.count_bids(lot_id)
    if bid_count >= BLITZ_MAX_BIDS:
        await _reply_to_comment(
            api, post_id, comment_id,
            "⚡ Блиц-цена недоступна — уже сделано 10 и более ставок."
        )
        return

    full_name = await _get_user_name(api, from_id)
    await db.upsert_user(from_id, full_name)
    await db.add_bid(lot_id, from_id, blitz_price)
    await db.update_lot_bid(lot_id, blitz_price, from_id)
    await db.finish_lot(lot_id, from_id)

    from scheduler import scheduler, _job_id
    job = scheduler.get_job(_job_id(lot_id))
    if job:
        job.remove()

    lot_finished = await db.get_lot(lot_id)
    top_bids = await db.get_lot_bids(lot_id)
    bid_count_final = await db.count_bids(lot_id)

    announcement = format_winner_announcement(
        lot["title"], from_id, full_name, blitz_price,
        is_blitz=True, top_bids=top_bids,
    )

    post_text = format_lot_post(lot_finished, top_bids=top_bids[:3], bid_count=bid_count_final)
    if lot_finished.get("rules"):
        post_text += f"\n\n📋 Правила:\n{lot_finished['rules']}"
    post_text += f"\n\n{announcement}"

    try:
        await api.wall.edit(
            owner_id=-GROUP_ID,
            post_id=lot["wall_post_id"],
            message=post_text,
            attachments=lot["photo_att"],
        )
    except Exception:
        logger.exception("Failed to edit post after blitz for lot #%d", lot_id)

    try:
        await api.wall.create_comment(
            owner_id=-GROUP_ID,
            post_id=post_id,
            message=announcement,
            from_group=GROUP_ID,
        )
    except Exception:
        logger.exception("Failed to post blitz announcement for lot #%d", lot_id)

    # Notify admins via PM
    from config import ADMIN_IDS
    mention = vk_mention(from_id, full_name)
    for admin_id in ADMIN_IDS:
        try:
            await api.messages.send(
                user_id=admin_id,
                message=(
                    f"⚡ Блиц-покупка!\n\nЛот #{lot_id}: {lot['title']}\n"
                    f"Покупатель: {mention}\n"
                    f"Цена: {blitz_price:,} ₽".replace(",", " ")
                ),
                random_id=random.randint(1, 2**31),
            )
        except Exception:
            pass


# ─── Cancel top bid ────────────────────────────────────────────────────────────

async def _handle_cancel_bid(api, lot: dict, lot_id: int, from_id: int, post_id: int, comment_id: int) -> None:
    if lot["status"] != "active":
        await _reply_to_comment(api, post_id, comment_id, "⏹ Аукцион уже завершён.")
        return

    if lot.get("winner_id") != from_id:
        await _reply_to_comment(
            api, post_id, comment_id,
            "❌ Вы не лидируете — ставку отменить нельзя. Отмена доступна только текущему лидеру."
        )
        return

    removed = await db.cancel_top_bid(lot_id, from_id)
    if not removed:
        await _reply_to_comment(api, post_id, comment_id, "У вас нет ставок по этому лоту.")
        return

    lot_updated = await db.get_lot(lot_id)
    top_bids = await db.get_lot_bids(lot_id)
    bid_count = await db.count_bids(lot_id)
    await _update_post(api, lot_updated, top_bids, bid_count)

    full_name = await _get_user_name(api, from_id)
    mention = vk_mention(from_id, full_name)
    await _reply_to_comment(api, post_id, comment_id, f"↩️ {mention}, ваша ставка отменена. Аукцион продолжается.")
