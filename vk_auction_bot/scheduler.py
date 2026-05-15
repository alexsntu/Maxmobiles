import logging
from datetime import datetime, timezone

from apscheduler.schedulers.asyncio import AsyncIOScheduler
from apscheduler.triggers.date import DateTrigger

import database as db
from config import GROUP_ID
from utils import format_lot_post, format_winner_announcement

logger = logging.getLogger(__name__)
scheduler = AsyncIOScheduler()


def _job_id(lot_id: int) -> str:
    return f"lot_close_{lot_id}"


def schedule_lot_close(lot_id: int, end_time: datetime, bot) -> None:
    job_id = _job_id(lot_id)
    existing = scheduler.get_job(job_id)
    if existing:
        existing.remove()
    scheduler.add_job(
        close_lot,
        trigger=DateTrigger(run_date=end_time),
        args=[lot_id, bot],
        id=job_id,
    )
    logger.info("Scheduled close for lot #%d at %s", lot_id, end_time)


async def close_lot(lot_id: int, bot) -> None:
    lot = await db.get_lot(lot_id)
    if not lot or lot["status"] != "active":
        return

    top_bids = await db.get_lot_bids(lot_id)
    bid_count = await db.count_bids(lot_id)
    winner_id = lot.get("winner_id")

    winner_name = None
    if winner_id:
        user = await db.get_user(winner_id)
        winner_name = user.get("full_name") if user else None

    await db.finish_lot(lot_id, winner_id)
    lot_finished = await db.get_lot(lot_id)

    if lot.get("wall_post_id"):
        post_text = format_lot_post(lot_finished, top_bids=top_bids[:3], bid_count=bid_count)
        try:
            await bot.api.wall.edit(
                owner_id=-GROUP_ID,
                post_id=lot["wall_post_id"],
                message=post_text,
                attachments=lot["photo_att"],
            )
        except Exception:
            logger.exception("Failed to edit wall post for lot #%d", lot_id)

        announcement = format_winner_announcement(
            lot["title"], winner_id, winner_name, lot["current_price"],
            top_bids=top_bids,
        )
        try:
            await bot.api.wall.create_comment(
                owner_id=-GROUP_ID,
                post_id=lot["wall_post_id"],
                message=announcement,
                from_group=GROUP_ID,
            )
        except Exception:
            logger.exception("Failed to post winner comment for lot #%d", lot_id)

    logger.info("Lot #%d closed. Winner: %s", lot_id, winner_id)


async def restore_active_lots(bot) -> None:
    """Re-schedule active lots after bot restart; immediately close overdue ones."""
    lots = await db.get_active_lots()
    now = datetime.now(tz=timezone.utc)
    for lot in lots:
        end_time = datetime.fromisoformat(lot["end_time"])
        if end_time.tzinfo is None:
            end_time = end_time.replace(tzinfo=timezone.utc)
        if end_time <= now:
            await close_lot(lot["id"], bot)
        else:
            schedule_lot_close(lot["id"], end_time, bot)
    logger.info("Restored %d active lot(s).", len(lots))
