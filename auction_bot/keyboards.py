from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton
from aiogram.utils.keyboard import InlineKeyboardBuilder


BLITZ_MAX_BIDS = 10  # Блиц доступен пока ставок меньше этого числа


def lot_keyboard(
    lot_id: int,
    min_step: int,
    blitz_price: int | None = None,
    bid_count: int = 0,
    bid_variants: int = 3,
    has_bids: bool = False,
) -> InlineKeyboardMarkup:
    """
    Inline keyboard with quickbid buttons.

    bid_variants=1 — only one button (Сделать ставку + min_step).
    bid_variants=3 — three buttons (×1, ×2, ×5 min_step).
    Blitz button shown only when blitz_price is set and bid_count < BLITZ_MAX_BIDS.
    Cancel button shown only when has_bids=True (someone is leading).
    Bottom row: 'Моя ставка'.
    """
    builder = InlineKeyboardBuilder()

    multipliers = [1, 2, 5] if bid_variants >= 3 else [1]
    for m in multipliers:
        amount = min_step * m
        label = f"🔨 Сделать ставку +{amount:,} ₽".replace(",", " ")
        builder.button(
            text=label,
            callback_data=f"quickbid:{lot_id}:{amount}",
        )

    builder.button(text="👤 Моя ставка", callback_data=f"mybid:{lot_id}")

    row_layout = [len(multipliers), 1]

    # Блиц-кнопка — только если цена задана и ставок меньше лимита
    if blitz_price and bid_count < BLITZ_MAX_BIDS:
        builder.button(
            text=f"⚡ Купить сейчас — {blitz_price:,} ₽".replace(",", " "),
            callback_data=f"blitz:{lot_id}",
        )
        row_layout.append(1)

    # Кнопка отмены ставки — только если есть хотя бы одна ставка
    if has_bids:
        builder.button(text="↩️ Отменить свою ставку", callback_data=f"cancelbid:{lot_id}")
        row_layout.append(1)

    builder.adjust(*row_layout)
    return builder.as_markup()


def blitz_confirm_keyboard(lot_id: int, user_id: int) -> InlineKeyboardMarkup:
    """Confirmation keyboard shown before completing a blitz purchase.

    user_id is embedded in callback_data so that only the initiating user
    can confirm or cancel — even if the DM is somehow forwarded.
    """
    builder = InlineKeyboardBuilder()
    builder.button(text="⚡ Купить по блиц-цене", callback_data=f"blitz_confirm:{lot_id}:{user_id}")
    builder.button(text="❌ Отмена",               callback_data=f"blitz_cancel_confirm:{lot_id}:{user_id}")
    builder.adjust(1)
    return builder.as_markup()


def group_select_keyboard(groups: list[tuple[str, int]]) -> InlineKeyboardMarkup:
    """Step keyboard: choose which channel/group to publish the lot in."""
    builder = InlineKeyboardBuilder()
    for name, gid in groups:
        builder.button(text=f"📢 {name}", callback_data=f"group:{gid}")
    builder.adjust(1)
    return builder.as_markup()


def bid_variants_keyboard() -> InlineKeyboardMarkup:
    """Step keyboard: choose how many bid-increment buttons to show on the lot."""
    builder = InlineKeyboardBuilder()
    builder.button(text="1️⃣  Один вариант (только минимальный шаг)",   callback_data="bidvariants:1")
    builder.button(text="3️⃣  Три варианта (×1, ×2, ×5 от шага)",       callback_data="bidvariants:3")
    builder.adjust(1)
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
    builder.button(text="📊 Статистика",   callback_data=f"admin_bids:{lot_id}")
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
    builder.button(text="📅 Точная дата и время", callback_data="duration:datetime")
    builder.adjust(3, 3, 1, 1)
    return builder.as_markup()
