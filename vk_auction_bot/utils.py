from datetime import datetime
from zoneinfo import ZoneInfo

from config import TIMEZONE

MEDALS = ["🥇", "🥈", "🥉"]
BLITZ_MAX_BIDS = 10


def vk_mention(vk_id: int, name: str) -> str:
    """Clickable VK user mention in post/comment text."""
    return f"[id{vk_id}|{name}]"


def format_lot_post(lot: dict, top_bids: list[dict] | None = None, bid_count: int = 0) -> str:
    """Format lot text for a VK wall post (plain text + emoji, no HTML)."""
    end_time = datetime.fromisoformat(lot["end_time"])
    end_local = end_time.astimezone(ZoneInfo(TIMEZONE))
    time_str = end_local.strftime("%d.%m.%Y %H:%M")

    status_line = ""
    if lot["status"] == "finished":
        status_line = "\n\n🏁 АУКЦИОН ЗАВЕРШЁН"
    elif lot["status"] == "cancelled":
        status_line = "\n\n🚫 АУКЦИОН ОТМЕНЁН"

    desc = lot.get("description", "") or ""
    desc_block = f"{desc}\n\n" if desc else ""

    blitz = lot.get("blitz_price")
    blitz_available = blitz and bid_count < BLITZ_MAX_BIDS and lot.get("status") == "active"
    blitz_line = f"⚡ Блиц-цена: {blitz:,} ₽ — напишите «блиц» в комментарии\n".replace(",", " ") if blitz_available else ""

    base = (
        f"🏷 {lot['title']}\n\n"
        f"{desc_block}"
        f"💰 Стартовая цена: {lot['start_price']:,} ₽\n".replace(",", " ") +
        f"📈 Минимальный шаг: {lot['min_step']:,} ₽\n".replace(",", " ") +
        f"{blitz_line}"
        f"🔝 Текущая ставка: {lot['current_price']:,} ₽\n".replace(",", " ") +
        f"⏰ Окончание: {time_str}\n\n"
        f"💬 Делайте ставки в комментариях — напишите сумму числом!"
    )

    if top_bids:
        lines = []
        for i, b in enumerate(top_bids[:3]):
            name = b.get("full_name") or f"id{b['user_id']}"
            medal = MEDALS[i] if i < len(MEDALS) else "•"
            lines.append(f"{medal} {name} — {b['amount']:,} ₽".replace(",", " "))
        base += "\n\n" + "\n".join(lines)

    return base + status_line


def format_winner_announcement(
    lot_title: str,
    winner_id: int | None,
    winner_name: str | None,
    price: int,
    is_blitz: bool = False,
    top_bids: list[dict] | None = None,
) -> str:
    """Full group announcement at auction end."""
    if winner_id is None:
        return "😔 Аукцион завершён.\nСтавок не поступало — лот не продан."

    mention = vk_mention(winner_id, winner_name or f"id{winner_id}")
    blitz_note = " по блиц-цене" if is_blitz else ""

    text = (
        f"🏆 Аукцион завершён!\n\n"
        f"Поздравляем победителя {mention} — "
        f"его ставка сыграла и стала выигрышной{blitz_note}!\n\n"
        f"💸 Итоговая цена: {price:,} ₽\n\n".replace(",", " ") +
        f"Мы скоро свяжемся с вами и скажем, как забрать выигранный лот."
    )

    if top_bids and len(top_bids) > 1:
        reserve_lines = []
        for i, b in enumerate(top_bids[1:3], start=2):
            medal = MEDALS[i - 1] if i - 1 < len(MEDALS) else f"{i}."
            b_mention = vk_mention(b["user_id"], b.get("full_name") or f"id{b['user_id']}")
            reserve_lines.append(f"{medal} {b_mention} — {b['amount']:,} ₽".replace(",", " "))
        text += "\n\nРезерв:\n" + "\n".join(reserve_lines)

    return text


def seconds_until(end_time_iso: str) -> float:
    end_time = datetime.fromisoformat(end_time_iso)
    now = datetime.now(tz=end_time.tzinfo)
    return (end_time - now).total_seconds()


def format_time_remaining(seconds: float) -> str:
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
