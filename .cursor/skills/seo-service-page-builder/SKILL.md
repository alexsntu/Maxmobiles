---
name: seo-service-page-builder
description: Generates SEO-optimized HTML service page blocks for Maxmobiles (Sevastopol Apple repair center). Use when the user provides a repair category or device model and asks to create/write/generate an SEO page, service block, repair page, or HTML content for the service section. Performs competitor research, LSI keyword collection, and outputs a ready-to-paste CS-Cart HTML block following the seo--for-service-html-block standard.
---

# SEO Service Page Builder — Maxmobiles

Generates a full CS-Cart HTML block for a repair category page. Always geo-bound to **Севастополь / Maxmobiles**.

## Workflow (execute in order)

### Step 1 — Parse the request

Extract from the user's message:
- **Device + model** (e.g., iPhone 16 Pro Max, MacBook Pro 14, iPad Air)
- **Service type** if specified (e.g., замена дисплея, замена аккумулятора, or general ремонт)
- **URL slug** inferred from model (e.g., `remont-iphone-16-pro-max`)

### Step 2 — Competitor & SERP research

Run **4 WebSearch queries in parallel**:

1. `"ремонт [Устройство] Севастополь" site:ru` — local competitors
2. `"ремонт [Устройство]" сервисный центр` — top national service centers (ifix, pedant, re:Store, etc.)
3. `"[Устройство] ремонт цена Крым OR Севастополь"` — LSI + pricing signals
4. `"[Устройство] неисправности замена [основная деталь]"` — LSI: failure types, parts, symptoms

**Goal:** Identify:
- What sections top-5 results use (services list, FAQ topics, trust blocks, guarantees)
- What LSI terms appear repeatedly (failure symptoms, part names, brand terms)
- What questions users ask (FAQ seeds)
- How competitors structure their H2/H3 hierarchy

### Step 3 — Keyword matrix

After research, compile internally (do NOT output to user):

| Cluster | Keywords |
|---------|----------|
| Основной запрос | ремонт [Устройство] Севастополь, сервис [Устройство] Севастополь |
| Услуги | замена дисплея, замена аккумулятора, замена стекла, замена кнопки … |
| Симптомы | не включается, разбит экран, не держит заряд … |
| Доверие | гарантия, оригинальные запчасти, сертифицированный мастер |
| Гео-хвост | пр-т Нахимова, Севастополь, Крым |

Distribute naturally across: H2 (1 ключ), лид-абзац (2–3 ключа), карточки услуг (1 ключ/карточка), FAQ (вопросы = запросы).

### Step 4 — Image audit

Before generating HTML, determine which images would strengthen the page:

Count the sections that benefit from a photo:
- Hero / intro visual (мастер за работой или устройство крупным планом)
- "Как проходит ремонт" (процесс или схема)
- Преимущества / Trust (интерьер сервиса, сертификаты)

**Always ask the user:**

> Для страницы потребуется [N] изображений:
> 1. **[Секция]** — [описание нужного фото, 1 строка]
> 2. **[Секция]** — [описание нужного фото]
> …
>
> Пришлите ссылки на каждое фото (или напишите «пропустить», если картинок нет).

Wait for the user's response before generating HTML. If user says "пропустить" — generate HTML without any `<img>` tags (skip image sections entirely).

### Step 5 — Generate HTML

Follow **all** rules in `.cursor/rules/seo--for-service-html-block.mdc` strictly.

**Two-block structure (split):**

**Block 1 — SEO core (always visible, no collapse):**
- `<div class="mm-block" itemscope itemtype="https://schema.org/LocalBusiness">` with meta tags
- **Intro**: H2 (main keyword + Севастополь) + `.mm-intro-text` (2–3 sentences, LSI-rich) + `.mm-stat-badge`
- **Trust strip**: 4 icons — срок гарантии, опыт с 2011, оригинальные запчасти, диагностика
- **Services grid**: strictly 3 or 6 `<article>` cards with `itemprop` (all cards in Block 1, never split); **NO icons** (`mm-service-card-icon`) in service cards — icons are used only in trust strip

**Block 2 — user content (collapse, initial height 350px):**
- `<div class="mm-block">` without itemscope
- **HowTo steps** (if "Как проходит ремонт" block is included): `.mm-howto-steps`
- **Advantages / Highlight**: `.mm-advantages-list` or `.mm-highlight` block
- **FAQ**: 4 questions in plain HTML (`.mm-faq`) without FAQ microdata in HTML
- **CTA**: dark gradient block, 2 buttons (Telegram bot + phone)

Required output order:
1. **JSON-LD** — `@graph`: LocalBusiness + WebPage + FAQPage (+ HowTo if HowTo steps present)
2. **Block 1** — `div.mm-block[itemscope]` → Intro + Trust strip + Services grid
3. **Block 2** — `.mm-collapse-wrapper → .mm-collapse-content → div.mm-block` → HowTo + Advantages + FAQ + CTA
4. **`<script>`** — years counter + collapse init at 350px + `mmBlockToggle` function

### Step 6 — Self-check before outputting

**Block split:**
- [ ] Block 1 contains: intro + trust strip + services grid (all cards, no split)
- [ ] Block 2 contains: HowTo (if present) + advantages + FAQ + CTA
- [ ] Block 1 is NOT wrapped in `.mm-collapse-wrapper`
- [ ] Block 2 is wrapped in `.mm-collapse-wrapper` with initial height **350px** (not 400px)
- [ ] `itemscope itemtype="https://schema.org/LocalBusiness"` only on Block 1's `.mm-block`
- [ ] Block 2's `.mm-block` has NO `itemscope`/`itemtype`/`itemprop`

**Structure & code:**
- [ ] No `<style>` tags inside the block
- [ ] No concrete prices in ₽
- [ ] No emoji — only HTML entities
- [ ] `fetchpriority="high"` on first `<img>`, `loading="lazy"` on all others
- [ ] Cards count is exactly 3 or 6
- [ ] В карточках нет `Product` microdata (`itemscope/itemtype=\"https://schema.org/Product\"` и `itemprop=*`)
- [ ] JSON-LD FAQPage matches HTML FAQ content
- [ ] В JSON-LD `FAQPage` заполнено поле `"name"` (человекочитаемый заголовок FAQ)
- [ ] FAQ schema only in JSON-LD; HTML FAQ has no `itemscope/itemtype/itemprop`
- [ ] All hrefs use correct phone / Telegram from brand rules
- [ ] H1 absent (only H2 → H3 hierarchy)

**E-E-A-T checklist** (сервисные страницы — повышенный приоритет, близко к YMYL):
- [ ] **Experience**: в лиде или trust strip упомянут реальный опыт — «с 2011 года», сколько устройств отремонтировано, конкретные мастера/сертификаты
- [ ] **Expertise**: описание услуг содержит профессиональные детали — диагностика, оригинальные/сертифицированные запчасти, технология ремонта; избегай абстракций вроде «качественно и быстро»
- [ ] **Authoritativeness**: присутствует авторитетный сигнал — «авторизованный сервисный центр Apple», рейтинг «4.9 / 120+ отзывов», «старейший сервис Apple в Крыму»
- [ ] **Trustworthiness**: минимум 2 доверительных сигнала — гарантия на ремонт (срок), оригинальные запчасти, реальный адрес/контакты/часы работы
- [ ] E-E-A-T сигналы интегрированы в полезный контент, не выглядят как отдельный «рекламный» блок

### Step 7 — Output

Output **only** the final HTML in a single code block. No commentary before or after.

---

## Brand constants (always use these verbatim)

```
Name:     Maxmobiles
Phone:    +7-978-820-30-01
href tel: tel:+79788203001
Address:  пр-т Нахимова 19, Севастополь
Hours:    ежедневно 10:00–20:00
Telegram: https://t.me/maxmobiles_service_bot
Site:     https://maxmobiles.ru
Rating:   4.9 / 120 отзывов
Since:    2011
```

## URL pattern

```
https://maxmobiles.ru/servis-apple/remont-[device]/remont-[model-slug]/
```

Examples:
- `remont-iphone/remont-iphone-16-pro-max/`
- `remont-macbook/remont-macbook-pro-14/`
- `remont-ipad/remont-ipad-air-m2/`

## Services to include per device type

**iPhone any model** → always offer: замена дисплея, замена аккумулятора, замена стекла + 3 more from: замена кнопки питания, замена разъёма зарядки, замена камеры, восстановление после воды

**MacBook** → замена дисплея, замена аккумулятора, ремонт клавиатуры + 3 more from: замена SSD, переустановка macOS, ремонт материнской платы

**iPad** → замена стекла / тачскрина, замена аккумулятора, замена дисплея

**Apple Watch** → замена дисплея, замена аккумулятора, замена стекла

Choose 3 if device has 3–4 primary failures; choose 6 if 5+ common failures.

## Reference

Full HTML structure standard: `.cursor/rules/seo--for-service-html-block.mdc`
