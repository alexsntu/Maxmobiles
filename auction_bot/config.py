import os
from dotenv import load_dotenv

load_dotenv()


def _require(name: str) -> str:
    value = os.getenv(name)
    if not value:
        raise RuntimeError(f"Missing required environment variable: {name}")
    return value


BOT_TOKEN: str = _require("BOT_TOKEN")

ADMIN_IDS: list[int] = [
    int(uid.strip())
    for uid in _require("ADMIN_IDS").split(",")
    if uid.strip()
]

# ─── Каналы/группы для публикации лотов ───────────────────────────────────────
# Формат GROUPS: "Название1:ID1,Название2:ID2"
# Если GROUPS не задан — используется одиночный GROUP_ID (обратная совместимость)
_groups_raw = os.getenv("GROUPS", "").strip()
if _groups_raw:
    GROUPS: list[tuple[str, int]] = [
        (pair.split(":", 1)[0].strip(), int(pair.split(":", 1)[1].strip()))
        for pair in _groups_raw.split(",")
        if ":" in pair
    ]
else:
    _gid = os.getenv("GROUP_ID")
    if not _gid:
        raise RuntimeError("Missing required env var: GROUPS or GROUP_ID")
    GROUPS = [("Основной", int(_gid))]

# Первый канал из списка (обратная совместимость со старым GROUP_ID)
GROUP_ID: int = GROUPS[0][1]

# Множество всех ID каналов (для фильтрации входящих сообщений)
GROUP_IDS: set[int] = {gid for _, gid in GROUPS}

TIMEZONE: str = os.getenv("TIMEZONE", "Europe/Moscow")

# Anti-snipe: если ставка сделана менее чем за N секунд до конца — продлеваем
ANTI_SNIPE_SECONDS: int = 60
