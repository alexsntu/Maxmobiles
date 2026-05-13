"""
APScheduler wrapper for lot auto-closing.

Each lot gets one DateTrigger job keyed by lot_id.
Re-scheduling (anti-snipe extension) removes the old job and adds a new one.
"""

from datetime import datetime
from typing import Optional

from apscheduler.schedulers.asyncio import AsyncIOScheduler
from apscheduler.triggers.date import DateTrigger
from aiogram import Bot

import database as db
from config import ADMIN_IDS, GROUP_ID
from keyboards import lot_keyboard
from utils import format_lot_message, format_winner_line, format_winner_announcement

scheduler = AsyncIOScheduler(timezone="UTC")


def _job_id(lot_id: int) -> str:
    return f"lot_close_{lot_id}"


def schedule_lot_close(lot_id: int, end_time: datetime, bot: Bot) -> None:
    """Schedule (or reschedule) the closing job for a lot."""
    job_id = _job_id(lot_id)

    # Remove existing job if any (e.g. anti-snipe reschedule)
    if scheduler.get_job(job_id):
        scheduler.remove_job(job_id)

    scheduler.add_job(
        _close_lot,
        trigger=DateTrigger(run_date=end_time),
        id=job_id,
        kwargs={"lot_id": lot_id, "bot": bot},
        misfire_grace_time=300,   # run even if up to 5 min late
    )


async def _close_lot(lot_id: int, bot: Bot) -> None:
    """Called by APScheduler when a lot's time expires."""
    lot = await db.get_lot(lot_id)
    if lot is None or lot["status"] != "active":
        return

    winner_id: Optional[int] = lot["winner_id"]
    winner_full_name: Optional[str] = None
    winner_username: Optional[str] = None

    if winner_id:
        user = await db.get_user(winner_id)
        if user:
            winner_full_name = user["full_name"]
            winner_username = user["username"]

    await db.finish_lot(lot_id, winner_id)

    lot_group_id = lot.get("group_chat_id") or GROUP_ID

    # Update the group message (caption = lot info + short winner line)
    lot_finished = await db.get_lot(lot_id)
    top_bids = await db.get_lot_bids(lot_id)
    final_bid_count = await db.count_bids(lot_id)
    caption = format_lot_message(lot_finished, top_bids=top_bids[:3], bid_count=final_bid_count) + format_winner_line(
        winner_id, winner_full_name or winner_username, lot["current_price"]
    )

    if lot["group_message_id"]:
        try:
            await bot.edit_message_caption(
                chat_id=lot_group_id,
                message_id=lot["group_message_id"],
                caption=caption,
                parse_mode="HTML",
                reply_markup=None,
            )
        except Exception:
            pass

    # Send full announcement as separate message in the group
    announcement = format_winner_announcement(
        lot["title"], winner_id, winner_full_name, winner_username, lot["current_price"],
        top_bids=top_bids,
    )
    try:
        await bot.send_message(lot_group_id, announcement, parse_mode="HTML")
    except Exception:
        pass

    # Notify winner in PM
    if winner_id:
        try:
            await bot.send_message(
                chat_id=winner_id,
                text=(
                    f"🏆 <b>Поздравляем! Вы выиграли аукцион!</b>\n\n"
                    f"Лот: <b>{lot['title']}</b>\n"
                    f"Итоговая цена: <b>{lot['current_price']:,} ₽</b>\n\n"
                    f"Мы скоро с вами свяжемся и скажем, как можно будет "
                    f"купить выбранный лот по вашей цене."
                ),
                parse_mode="HTML",
            )
        except Exception:
            pass

    # Notify all admins
    from utils import tg_link
    winner_display = winner_full_name or winner_username or str(winner_id)
    if winner_id:
        admin_text = (
            f"📦 <b>Аукцион завершён!</b>\n\n"
            f"Лот #{lot_id}: <b>{lot['title']}</b>\n"
            f"Итог: <b>{lot['current_price']:,} ₽</b>\n\n"
            f"🥇 {tg_link(winner_id, winner_full_name, winner_username)}"
        )
        if top_bids and len(top_bids) > 1:
            from utils import MEDALS
            for i, b in enumerate(top_bids[1:3], start=2):
                medal = MEDALS[i - 1] if i - 1 < len(MEDALS) else f"{i}."
                admin_text += f"\n{medal} {tg_link(b['user_id'], b.get('full_name'), b.get('username'))} — {b['amount']:,} ₽"
    else:
        admin_text = (
            f"📦 <b>Аукцион завершён!</b>\n\n"
            f"Лот #{lot_id}: <b>{lot['title']}</b>\n"
            f"Ставок не поступало. Лот не продан."
        )
    for admin_id in ADMIN_IDS:
        try:
            await bot.send_message(admin_id, admin_text, parse_mode="HTML")
        except Exception:
            pass


async def restore_active_lots(bot: Bot) -> None:
    """On bot startup: reschedule closing jobs for all still-active lots."""
    from datetime import timezone
    lots = await db.get_active_lots()
    now = datetime.now(tz=timezone.utc)
    for lot in lots:
        end_time = datetime.fromisoformat(lot["end_time"])
        if end_time.tzinfo is None:
            from zoneinfo import ZoneInfo
            end_time = end_time.replace(tzinfo=timezone.utc)
        if end_time <= now:
            # Already expired while bot was offline — close immediately
            await _close_lot(lot["id"], bot)
        else:
            schedule_lot_close(lot["id"], end_time, bot)
