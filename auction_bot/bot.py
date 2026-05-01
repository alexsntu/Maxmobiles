import asyncio
import logging

from aiogram import Bot, Dispatcher
from aiogram.client.default import DefaultBotProperties
from aiogram.enums import ParseMode
from aiogram.fsm.storage.memory import MemoryStorage

from aiogram.types import BotCommand, BotCommandScopeChat, BotCommandScopeDefault

from config import ADMIN_IDS, BOT_TOKEN
from database import init_db
from scheduler import restore_active_lots, scheduler
from handlers import common, admin, bidding

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)


async def main() -> None:
    await init_db()
    logger.info("Database initialised.")

    bot = Bot(
        token=BOT_TOKEN,
        default=DefaultBotProperties(parse_mode=ParseMode.HTML),
    )
    dp = Dispatcher(storage=MemoryStorage())

    # Register routers
    dp.include_router(common.router)
    dp.include_router(admin.router)
    dp.include_router(bidding.router)

    # Start scheduler
    scheduler.start()
    logger.info("Scheduler started.")

    # Restore jobs for lots that were active before restart
    await restore_active_lots(bot)
    logger.info("Active lots restored.")

    # Команды для обычных пользователей
    await bot.set_my_commands(
        [
            BotCommand(command="start", description="🏠 Главное меню"),
            BotCommand(command="help",  description="ℹ️ Справка"),
        ],
        scope=BotCommandScopeDefault(),
    )

    # Расширенные команды для администраторов
    admin_commands = [
        BotCommand(command="start",   description="🏠 Главное меню"),
        BotCommand(command="help",    description="ℹ️ Справка"),
        BotCommand(command="newlot",  description="➕ Создать новый лот"),
        BotCommand(command="lots",    description="📋 Активные лоты"),
        BotCommand(command="cancel",  description="🚫 Отменить лот"),
    ]
    for admin_id in ADMIN_IDS:
        try:
            await bot.set_my_commands(
                admin_commands,
                scope=BotCommandScopeChat(chat_id=admin_id),
            )
        except Exception:
            pass

    logger.info("Bot commands registered.")

    # Drop pending updates accumulated while bot was offline
    await bot.delete_webhook(drop_pending_updates=True)

    logger.info("Bot is running...")
    try:
        await dp.start_polling(bot)
    finally:
        scheduler.shutdown(wait=False)
        await bot.session.close()


if __name__ == "__main__":
    asyncio.run(main())
