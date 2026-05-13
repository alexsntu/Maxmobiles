from datetime import datetime
from zoneinfo import ZoneInfo

from config import TIMEZONE


MEDALS = ["🥇", "🥈", "🥉"]


BLITZ_MAX_BIDS = 10  # должно совпадать с keyboards.py


def format_lot_message(lot: dict, top_bids: list[dict] | None = None, bid_count: int = 0) -> str:
    """Format the lot message text shown in the group."""
    end_time = datetime.fromisoformat(lot["end_time"])
    end_local = end_time.astimezone(ZoneInfo(TIMEZONE))
    time_str = end_local.strftime("%d.%m.%Y %H:%M")

    status_line = ""
    if lot["status"] == "finished":
        status_line = "\n\n🏁 <b>АУКЦИОН ЗАВЕРШЁН</b>"
    elif lot["status"] == "cancelled":
        status_line = "\n\n🚫 <b>АУКЦИОН ОТМЕНЁН</b>"

    desc = lot.get("description", "") or ""
    desc_block = f"{desc}\n\n" if desc else ""

    blitz = lot.get("blitz_price")
    blitz_available = blitz and bid_count < BLITZ_MAX_BIDS and lot.get("status") == "active"
    blitz_line = f"⚡ <b>Блиц-цена:</b> {blitz:,} ₽\n" if blitz_available else ""

    base = (
        f"🏷 <b>{lot['title']}</b>\n\n"
        f"{desc_block}"
        f"💰 <b>Стартовая цена:</b> {lot['start_price']:,} ₽\n"
        f"📈 <b>Минимальный шаг:</b> {lot['min_step']:,} ₽\n"
        f"{blitz_line}"
        f"🔝 <b>Текущая ставка:</b> {lot['current_price']:,} ₽\n"
        f"⏰ <b>Окончание:</b> {time_str}"
    )

    if top_bids:
        lines = []
        for i, b in enumerate(top_bids[:3]):
            name = b.get("full_name") or b.get("username") or f"#{b['user_id']}"
            medal = MEDALS[i] if i < len(MEDALS) else "•"
            lines.append(f"{medal} <b>{name}</b> — {b['amount']:,} ₽")
        base += "\n\n" + "\n".join(lines)

    return base + status_line


def tg_link(user_id: int, full_name: str | None, username: str | None) -> str:
    """Return an HTML link to a Telegram user."""
    display = full_name or username or f"#{user_id}"
    if username:
        return f'<a href="https://t.me/{username}">{display}</a>'
    return f'<a href="tg://user?id={user_id}">{display}</a>'


def format_winner_announcement(
    lot_title: str,
    winner_id: int | None,
    winner_full_name: str | None,
    winner_username: str | None,
    price: int,
    is_blitz: bool = False,
    top_bids: list[dict] | None = None,
) -> str:
    """Full group announcement text at auction end.

    top_bids — up to 3 top bids from db.get_lot_bids(); used to show
    2nd and 3rd place so the admin can contact them if the winner is unreachable.
    """
    if winner_id is None:
        return "😔 <b>Аукцион завершён.</b>\nСтавок не поступало — лот не продан."

    link = tg_link(winner_id, winner_full_name, winner_username)
    blitz_note = " по блиц-цене" if is_blitz else ""

    text = (
        f"🏆 <b>Аукцион завершён!</b>\n\n"
        f"Поздравляем победителя {link} — "
        f"его ставка сыграла и стала выигрышной{blitz_note}!\n\n"
        f"💸 Итоговая цена: <b>{price:,} ₽</b>\n\n"
        f"Мы скоро с вами свяжемся и скажем, как можно будет купить "
        f"выбранный лот по вашей цене."
    )

    # Show 2nd and 3rd place so admin has reserve contacts
    if top_bids and len(top_bids) > 1:
        reserve_lines = []
        for i, b in enumerate(top_bids[1:3], start=2):
            medal = MEDALS[i - 1] if i - 1 < len(MEDALS) else f"{i}."
            b_link = tg_link(b["user_id"], b.get("full_name"), b.get("username"))
            reserve_lines.append(f"{medal} {b_link} — {b['amount']:,} ₽")
        text += "\n\n<i>Резерв:</i>\n" + "\n".join(reserve_lines)

    return text


def format_winner_line(winner_id: int | None, winner_name: str | None, price: int) -> str:
    """Short winner line appended to the lot caption."""
    if winner_id is None:
        return "\n\n😔 <i>Ставок не поступало. Лот не продан.</i>"
    name = winner_name or f"пользователь #{winner_id}"
    return f"\n\n🏆 <b>Победитель:</b> {name}\n💸 <b>Итоговая цена:</b> {price:,} ₽"


def seconds_until(end_time_iso: str) -> float:
    end_time = datetime.fromisoformat(end_time_iso)
    now = datetime.now(tz=end_time.tzinfo)
    return (end_time - now).total_seconds()


def format_time_remaining(seconds: float) -> str:
    """Human-readable countdown string for lot end time."""
    if seconds <= 0:
        return "истекло"
    total = int(seconds)
    days = total // 86400
    hours = (total % 86400) // 3600
    minutes = (total % 3600) // 60
    secs = total % 60
    if days > 0:
        return f"{days} д {hours} ч {minutes} мин"
    if hours > 0:
        return f"{hours} ч {minutes} мин"
    if minutes > 0:
        return f"{minutes} мин {secs} сек"
    return f"{secs} сек"
