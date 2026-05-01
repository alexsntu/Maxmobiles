from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton
from aiogram.utils.keyboard import InlineKeyboardBuilder


BLITZ_MAX_BIDS = 10  # Блиц доступен пока ставок меньше этого числа


def lot_keyboard(
    lot_id: int,
    min_step: int,
    blitz_price: int | None = None,
    bid_count: int = 0,
) -> InlineKeyboardMarkup:
    """
    Inline keyboard with quick-bid buttons.
    Increments: 1x, 2x, 5x, 10x, 20x min_step — displayed as '+N ₽'.
    Blitz button shown only when blitz_price is set and bid_count < BLITZ_MAX_BIDS.
    Bottom row: bid history and custom amount.
    """
    builder = InlineKeyboardBuilder()

    multipliers = [1, 2, 5, 10, 20]
    for m in multipliers:
        amount = min_step * m
        label = f"+{amount:,} ₽".replace(",", " ")
        builder.button(
            text=label,
            callback_data=f"quickbid:{lot_id}:{amount}",
        )

    builder.button(text="📊 Топ ставок", callback_data=f"history:{lot_id}")
    builder.button(text="👤 Моя ставка", callback_data=f"mybid:{lot_id}")

    row_layout = [3, 2, 2]

    # Блиц-кнопка — только если цена задана и ставок меньше лимита
    if blitz_price and bid_count < BLITZ_MAX_BIDS:
        builder.button(
            text=f"⚡ Купить сейчас — {blitz_price:,} ₽".replace(",", " "),
            callback_data=f"blitz:{lot_id}",
        )
        row_layout.append(1)

    builder.adjust(*row_layout)
    return builder.as_markup()


def confirm_lot_keyboard() -> InlineKeyboardMarkup:
    """Confirmation keyboard for the lot creation preview."""
    builder = InlineKeyboardBuilder()
    builder.button(text="✅ Опубликовать", callback_data="lot_confirm:yes")
    builder.button(text="❌ Отменить",    callback_data="lot_confirm:no")
    builder.adjust(2)
    return builder.as_markup()


def admin_lots_keyboard(lots: list[dict]) -> InlineKeyboardMarkup:
    """Keyboard listing active lots for the admin /lots command."""
    builder = InlineKeyboardBuilder()
    for lot in lots:
        builder.button(
            text=f"#{lot['id']} {lot['title']} — {lot['current_price']:,} ₽",
            callback_data=f"admin_lot:{lot['id']}",
        )
    builder.adjust(1)
    return builder.as_markup()


def admin_lot_actions_keyboard(lot_id: int) -> InlineKeyboardMarkup:
    """Per-lot admin action keyboard."""
    builder = InlineKeyboardBuilder()
    builder.button(text="📊 Ставки",       callback_data=f"admin_bids:{lot_id}")
    builder.button(text="🚫 Отменить лот", callback_data=f"admin_cancel:{lot_id}")
    builder.button(text="◀️ Назад",        callback_data="admin_back")
    builder.adjust(2, 1)
    return builder.as_markup()


def duration_keyboard() -> InlineKeyboardMarkup:
    """Quick-pick keyboard for lot duration."""
    builder = InlineKeyboardBuilder()
    durations = [
        ("30 мин",  "30"),
        ("1 час",   "60"),
        ("2 часа",  "120"),
        ("3 часа",  "180"),
        ("6 часов", "360"),
        ("12 часов","720"),
        ("24 часа", "1440"),
    ]
    for label, value in durations:
        builder.button(text=label, callback_data=f"duration:{value}")
    builder.adjust(3)
    return builder.as_markup()
