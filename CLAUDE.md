# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository structure

This repo contains three independent parts:

1. **`auction_bot/`** — Python Telegram bot for English-style auctions (aiogram 3 + SQLite)
2. **`vk_auction_bot/`** — Python VK bot with the same auction logic (vkbottle + SQLite)
3. **HTML folders** (`SEO-страницы/`, `Акции/`, `Блог/`, `Категории магазина/`, `Записаться на ремонт/`, `Новые категории ремонта/`) — static HTML pages for a phone repair shop (Service MM / Maxmobiles)

---

## Telegram Auction Bot (`auction_bot/`)

### Setup & run

```bash
cd auction_bot
pip install -r requirements.txt
cp .env.example .env   # fill in BOT_TOKEN, ADMIN_IDS, GROUP_ID (or GROUPS), TIMEZONE
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

**Data layer:** `database.py` — all SQLite queries via `aiosqlite`. Schema: `users`, `lots`, `bids`. Inline migrations run at startup (`ALTER TABLE ADD COLUMN`) so existing DBs are upgraded safely.

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

---

## VK Auction Bot (`vk_auction_bot/`)

Same auction logic as the Telegram bot, adapted for VK communities.

### Setup & run

```bash
cd vk_auction_bot
pip install -r requirements.txt
cp .env.example .env   # fill in VK_TOKEN, ADMIN_IDS, GROUP_ID, TIMEZONE
python bot.py
```

### Environment variables

| Variable | Description |
|---|---|
| `VK_TOKEN` | Community token (Управление → Настройки → Работа с API) |
| `ADMIN_IDS` | Comma-separated VK user IDs with admin rights |
| `GROUP_ID` | Community ID — **positive** number (no minus) |
| `TIMEZONE` | Default: `Europe/Moscow` |
| `ANTI_SNIPE_SECONDS` | Default: `60` |

### Architecture differences from Telegram bot

- **Lots = VK wall posts.** Each lot is published as a post; `post_id` stored in DB.
- **Bids = wall post comments.** `handlers/comments.py` processes new comments via `WALL_REPLY_NEW` event.
- **No aiogram FSM.** State is stored in in-memory dicts in `states.py` (`_fsm_data`, `_fsm_state`).
- **Photo upload:** admin sends photo URL in the wizard; `aiohttp` downloads and re-uploads to VK via `photos.saveWallPhoto`.
- **Admin interface:** admin sends commands as DMs to the community page, not to a separate bot account.
- **VK keyboards:** `keyboards.py` returns VK inline keyboard JSON dicts (not aiogram `InlineKeyboardMarkup`).
- **Callback routing:** `MESSAGE_EVENT` from VK is routed manually in `bot.py` → `admin.handle_callback()`.

---

## HTML pages (Maxmobiles / Service MM)

All HTML files are standalone fragments inserted into CS-Cart CMS. Not full documents — no `<html>`/`<body>` wrapper.

### CS-Cart critical constraints

These are non-obvious bugs and limitations that apply to every HTML file:

- **No `<style>` tags** — CS-Cart strips them. All CSS lives in `maxmobiles-styles.css`, loaded once site-wide via Design → Themes → Custom CSS.
- **No emoji or Unicode > U+00FF** — TinyMCE decodes HTML entities to Unicode before save; MySQL then replaces any character above U+00FF with `???`. This kills `&#x1F6E1;`, `&#x2714;`, `&#x260E;`, etc. **Use inline SVG for all icons.**
- **No JSON-LD in wysiwyg** — CS-Cart duplicates every `<script>` tag from the wysiwyg editor twice, causing duplicate structured data and GSC errors. JSON-LD goes in a separate Layout block (Design → Layouts → HTML block), which renders exactly once.
- **Product microdata on cards is forbidden** — adding `itemscope itemtype="https://schema.org/Product"` to `.mm-service-card` triggers Google's requirement for `offers`/`review`/`aggregateRating`, causing critical GSC errors.
- **FAQPage JSON-LD not used** — same duplication reason. FAQ content is indexed via HTML + `speakable`.
- **No concrete prices in ₽** in HTML or JSON-LD — prices become stale and mislead.

### Two-file output pattern

Every generated page produces two files:

| File | Placement in CS-Cart | Content |
|---|---|---|
| `SEO-страницы/[slug].html` | Category description (wysiwyg) | HTML blocks only |
| `SEO-страницы/[slug]-jsonld.html` | Design → Layouts → HTML block | JSON-LD `<script>` tags only |

Each JSON-LD type (`LocalBusiness`, `WebPage`, `ItemList`, `HowTo`) goes in a separate `<script>` tag — never combined into `@graph` (conflicts with CMS-generated schemas like BreadcrumbList).

### Page structure (shop category pages)

Two-block split is the core SEO architecture:

**Block 1 — always visible** (`div.mm-block[itemscope]`):
- Intro: H2 + `.mm-intro-text` (all 5 GEO entities required)
- Trust strip: 4 inline SVG icons — guarantee, СДЭК delivery, Trade-In, store since 2011
- Highlights grid: exactly 3 or 6 `<article>` cards, each with a `.mm-service-link`

**Block 2 — collapse** (`.mm-collapse-wrapper → .mm-collapse-content → div.mm-block`):
- Advantages list `.mm-advantages-list`
- FAQ section `.mm-faq` (4 questions in v1, 6–8 in v2)
- CTA block `.mm-cta` with phone and email buttons

**Collapse/toggle pattern:** inline `<script>` IIFE after the wrapper sets `max-height: 350px` and `data-mmState`. `mmBlockToggle()` function handles expand/collapse with CSS transitions. `transform` on hover is forbidden — causes a Chrome bug with `overflow: hidden`. Do **not** put scripts in the theme's Custom JS.

### CSS design system

All styles scoped to `.mm-block`. Classes are whitelisted in `maxmobiles-styles.css` — use only registered classes. Key namespaces: `mm-block`, `mm-intro`, `mm-trust-strip`, `mm-services-grid`, `mm-service-card`, `mm-advantages-list`, `mm-faq`, `mm-cta`, `mm-collapse-*`.

Allowed text entities in prose (not as icons): `&#8212;` (—), `&#8594;` (→), `&#8243;` (″), `&#183;` (·), `&#8381;` (₽), `&#x7C;` (|).

### File output rule

**Always save generated HTML to a file immediately** — never output only in chat. The `save-output-to-file` rule applies to all skills (`alwaysApply: true`).

---

## Cursor skills & rules

The Cursor IDE uses two extension systems: rules (`.cursor/rules/*.mdc`) and skills (`.cursor/skills/*/SKILL.md`). Skills are the primary generation workflows.

### Skills reference

| Skill | Purpose |
|---|---|
| `seo-meta-builder` | H1 + title + description for any store page (v1) |
| `seo-meta-builder-v2` | Same + full JSON-LD (LocalBusiness with sameAs/geo/aggregateRating, WebPage with speakable/dateModified, NLQ-optimized description) |
| `seo-shop-page-builder` | Full HTML category block for product pages (v1, 4 FAQ) |
| `seo-shop-page-builder-v2` | Same + 6–8 NLQ FAQ questions, HowTo schema, full LocalBusiness JSON-LD |
| `seo-service-page-builder` | HTML block for repair/service pages |
| `seo-blog-article-builder-maxmobiles` | Transforms raw article HTML into SEO-optimized blog block |
| `seo-quick-links-builder` | Quick-links tag strip for category pages |
| `service-banner-block-builder` | Hero banner for repair category pages |
| `service-info-block-builder` | Info/specs block for repair pages |
| `service-price-table-builder` | Price table for repair services |
| `service-price-hydration` | Updates prices in existing price table HTML |
| `blog-poster-generator-maxmobiles` | Blog post cover image descriptions |

v1 and v2 skills are independent — they can be compared by generating the same page with each.

### Rules reference

Rules in `.cursor/rules/` are standards documents referenced by skills:
- `seo--for-shop-html-block.mdc` — full HTML structure standard for shop pages (used by shop-page-builder)
- `seo--for-service-html-block.mdc` — structure standard for service/repair pages
- `seo--meta-tags.mdc` — meta tag format standard (used by meta-builder)
- `seo--for-blog-article-maxmobiles.mdc` — blog article standard
- `save-output-to-file.mdc` — `alwaysApply: true` — always save to file
- `service-price-table-block.mdc`, `service-banner-block.mdc`, etc. — component standards

### Brand constants (use verbatim everywhere)

```
Store name:   Maxmobiles  (never MaxMobiles or MAXMOBILES)
Site:         https://maxmobiles.ru
Free phone:   8 (800) 250-81-58  /  tel:+78002508158
Store phone:  +7 (978) 222-01-23 /  tel:+79782220123
Email:        store@maxmobiles.ru
Address:      пр-т Нахимова 19, ТЦ «Детский мир», Севастополь
Hours:        ежедневно 10:00–20:00
Since:        2011
Yandex Maps:  https://yandex.ru/maps/org/237809440282
Rating:       5.0 / 1000+ отзывов (Яндекс Карты, org ID 237809440282)
Delivery:     СДЭК по всей России
Pickup:       Севастополь · Симферополь · Ялта · Москва
Speciality:   старейший Apple-эксперт в Крыму; авторизованный сервисный центр Apple
```

---

## Claude Code commands

Project-level commands live in `.claude/commands/`. Invoke with `/command-name [args]`.

| Command | What it does |
|---|---|
| `/meta [category or model]` | Runs `seo-meta-builder-v2`: SERP research → Gap Analysis → H1 + title variants + description with NLQ pattern + full JSON-LD block |

Example: `/meta iPhone 16 Pro Max`, `/meta AirPods Pro 3`, `/meta Ремонт MacBook Севастополь`
