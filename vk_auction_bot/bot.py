import asyncio
import logging

from vkbottle import GroupEventType
from vkbottle.bot import Bot

from config import VK_TOKEN
from database import init_db
from scheduler import restore_active_lots, scheduler
from handlers import admin, comments

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)

bot = Bot(token=VK_TOKEN)

# Register admin message/FSM handlers
bot.labeler.load(admin.labeler)


@bot.on.raw_event(GroupEventType.WALL_REPLY_NEW, dataclass=dict)
async def on_wall_comment(event: dict) -> None:
    """New comment on a community wall post — potential bid."""
    try:
        await comments.handle_comment(event, bot)
    except Exception:
        logger.exception("Error in on_wall_comment")


@bot.on.raw_event(GroupEventType.MESSAGE_EVENT, dataclass=dict)
async def on_message_event(event: dict) -> None:
    """Inline keyboard callback from admin wizard."""
    try:
        obj = event if "user_id" in event else event.get("object", event)
        await admin.handle_callback(obj, bot)
    except Exception:
        logger.exception("Error in on_message_event")


async def main() -> None:
    await init_db()
    logger.info("Database initialised.")

    scheduler.start()
    logger.info("Scheduler started.")

    await restore_active_lots(bot)
    logger.info("Active lots restored.")

    logger.info("VK Auction Bot is running...")
    try:
        await bot.run_polling()
    finally:
        scheduler.shutdown(wait=False)


if __name__ == "__main__":
    asyncio.run(main())
