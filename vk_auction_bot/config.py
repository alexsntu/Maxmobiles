import os
from dotenv import load_dotenv

load_dotenv()

VK_TOKEN: str = os.getenv("VK_TOKEN", "")
ADMIN_IDS: list[int] = [int(x) for x in os.getenv("ADMIN_IDS", "").split(",") if x.strip()]
GROUP_ID: int = int(os.getenv("GROUP_ID", "0"))
TIMEZONE: str = os.getenv("TIMEZONE", "Europe/Moscow")
ANTI_SNIPE_SECONDS: int = int(os.getenv("ANTI_SNIPE_SECONDS", "60"))
