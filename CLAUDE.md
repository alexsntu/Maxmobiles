# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository structure

This repo contains two independent parts:

1. **`auction_bot/`** — Python Telegram bot for English-style auctions (aiogram 3 + SQLite)
2. **HTML folders** (`SEO-страницы/`, `Акции/`, `Блог/`, `Категории магазина/`, `Записаться на ремонт/`, `Новые категории ремонта/`) — static HTML pages for a phone repair shop (Service MM)

---

## Auction Bot

### Setup

```bash
cd auction_bot
pip install -r requirements.txt
cp .env.example .env   # fill in BOT_TOKEN, ADMIN_IDS, GROUP_ID (or GROUPS), TIMEZONE
```

### Run

```bash
cd auction_bot
python bot.py
```

### Environment variables

| Variable | Description |
|---|---|
| `BOT_TOKEN` | Telegram bot token from @BotFather |
| `ADMIN_IDS` | Comma-separated Telegram user IDs with admin rights |
| `GROUP_ID` | Single group/channel ID (negative number) |
| `GROUPS` | Multi-group format: `"Name1:ID1,Name2:ID2"` — overrides `GROUP_ID` |
| `TIMEZONE` | Default: `Europe/Moscow` |

### Architecture

**Entry point:** `bot.py` — initialises DB, creates Bot/Dispatcher, registers routers, starts APScheduler, restores active lots after restart.

**Data layer:** `database.py` — all SQLite queries via `aiosqlite`. Schema has three tables: `users`, `lots`, `bids`. Inline migrations run at startup (`ALTER TABLE ADD COLUMN`) so existing DBs are upgraded safely.

**Scheduler:** `scheduler.py` — one APScheduler `DateTrigger` job per lot, keyed `lot_close_{id}`. Anti-snipe reschedules (extends timer by 60 s) remove the old job and add a new one. On restart, `restore_active_lots()` re-schedules all active lots and immediately closes any that expired while offline.

**Handlers:**
- `handlers/common.py` — `/start`, `/help`
- `handlers/admin.py` — multi-step FSM (`NewLotStates`) for lot creation, `/lots`, `/stats <id>`, `/cancel <id>`, admin inline keyboard actions
- `handlers/bidding.py` — three bid entry points: quick-bid buttons (`quickbid:`), reply-to-lot-message with a number, custom amount via ForceReply DM; blitz purchase (two-step confirm); bid cancellation (leader only); info/rules popup; bid history popup

**Keyboards:** `keyboards.py` — all inline keyboards. `lot_keyboard()` builds the participant-facing keyboard dynamically based on `bid_variants` (1 or 3 quick-bid buttons), blitz availability (hidden after 10 bids), and whether bids exist (shows cancel button for leader).

**FSM states:** `states.py` — `NewLotStates` group with states for each step of lot creation wizard.

**Utils:** `utils.py` — message formatting helpers (`format_lot_message`, `format_winner_announcement`, `format_time_remaining`, `seconds_until`).

### Key business rules

- **Anti-snipe:** bid in last 60 s → extend `end_time` by 60 s and reschedule the APScheduler job
- **Blitz price:** instant-win button, disabled after 10 bids; two-step confirm in the group chat
- **Bid cancellation:** only the current leader can cancel their own top bid; price reverts to previous leader
- **Multi-group:** if `GROUPS` env var is set, admin chooses the target channel at lot creation time; `group_chat_id` is stored per lot
- **Parse mode:** all messages use HTML (not Markdown)
