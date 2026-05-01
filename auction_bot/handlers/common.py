from aiogram import Router
from aiogram.filters import CommandStart, Command
from aiogram.types import Message

import database as db
from config import ADMIN_IDS

router = Router()


@router.message(CommandStart())
async def cmd_start(message: Message) -> None:
    await db.upsert_user(
        message.from_user.id,
        message.from_user.username,
        message.from_user.full_name,
    )
    is_admin = message.from_user.id in ADMIN_IDS
    admin_hint = "\n\n🔧 <b>Команды администратора:</b>\n/newlot — создать новый лот\n/lots — список активных лотов" if is_admin else ""

    await message.answer(
        "👋 Добро пожаловать в <b>Аукцион-бот</b>!\n\n"
        "Здесь проводятся открытые аукционы. Следите за публикациями в группе "
        "и делайте ставки прямо там.\n\n"
        "📌 <b>Как участвовать:</b>\n"
        "1. Откройте сообщение с лотом в группе\n"
        "2. Нажмите кнопку «🔨 Сделать ставку» или ответьте на сообщение числом\n"
        "3. Побеждает тот, кто предложил наибольшую цену к моменту окончания"
        + admin_hint,
        parse_mode="HTML",
    )


@router.message(Command("help"))
async def cmd_help(message: Message) -> None:
    is_admin = message.from_user.id in ADMIN_IDS
    admin_section = (
        "\n\n🔧 <b>Для администраторов:</b>\n"
        "/newlot — запустить мастер создания лота\n"
        "/lots — список активных лотов\n"
        "/cancel &lt;id&gt; — отменить лот по ID"
    ) if is_admin else ""

    await message.answer(
        "ℹ️ <b>Справка по Аукцион-боту</b>\n\n"
        "<b>Как делать ставки:</b>\n"
        "• Нажмите «🔨 Сделать ставку» в сообщении лота\n"
        "• Или ответьте (<i>Reply</i>) на сообщение лота, написав сумму числом\n\n"
        "<b>Правила:</b>\n"
        "• Ставка должна быть выше текущей цены + минимальный шаг\n"
        "• Если ставка сделана в последние 60 сек — таймер продлевается на 60 сек\n"
        "• Победитель объявляется автоматически по истечении времени"
        + admin_section,
        parse_mode="HTML",
    )
