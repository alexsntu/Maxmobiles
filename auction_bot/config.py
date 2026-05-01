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

GROUP_ID: int = int(_require("GROUP_ID"))

TIMEZONE: str = os.getenv("TIMEZONE", "Europe/Moscow")

# Anti-snipe: если ставка сделана менее чем за N секунд до конца — продлеваем
ANTI_SNIPE_SECONDS: int = 60
