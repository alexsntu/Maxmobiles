---
name: seo-shop-page-builder
description: Generates SEO-optimized HTML category page blocks for the Maxmobiles online store (Sevastopol Apple shop). Use when the user provides a product category or device model and asks to create/write/generate an SEO page, category block, shop page, or HTML content for product category sections (NOT repair/service). Performs competitor research, GEO/AEO keyword collection, and outputs a ready-to-paste CS-Cart HTML block following the seo--for-shop-html-block standard.
---

# SEO Shop Page Builder — Maxmobiles

Generates a full CS-Cart HTML block for a **product category** page. Always geo-bound to **Севастополь / Maxmobiles**. Delivery via СДЭК across Russia.

## Workflow (execute in order)

### Step 1 — Parse the request

Extract from the user's message:
- **Category or model** (e.g., iPhone 16 Pro Max, MacBook Pro, AirPods Pro, Watch Series 10)
- **Page type**: top-level category (iPhone, Mac, Watch) or specific model landing
- **URL slug** inferred (e.g., `iphone`, `iphone/iphone-16-pro-max`, `mac/macbook-pro`)
- **Competitor URLs** — if provided, flag for Step 2b analysis
- **Collapse** — whether to wrap the block in the collapse/expand mechanism:
  - If the user says **«с коллапсом»**, **«с раскрытием»**, or **«collapse»** → use collapse wrapper
  - If the user says **«без коллапса»**, **«без раскрытия»**, or **«no collapse»** → output `.mm-block` directly, no wrapper, no `<script>`
  - If **not specified** → use collapse by default

### Step 2a — SERP & keyword research

Run **4 WebSearch queries in parallel**:

1. `"купить [Категория/Модель] Севастополь"` — local buy intent
2. `"купить [Модель] с доставкой по России СДЭК"` — delivery signals
3. `"[Модель] цена купить официальный магазин Крым"` — commercial price + geo intent
4. `"[Модель] Trade-In рассрочка кредит"` — purchase condition signals

**Goal:** Identify commercial LSI terms, FAQ seeds, trust signals, how competitors structure category pages. **Do NOT use informational queries** (reviews, specs, comparisons) — this is a shop page with commercial intent only.

### Step 2b — Competitor analysis (if URLs provided)

For each URL provided by the user — run `WebFetch` and extract:

| Parameter | What to record |
|---|---|
| H1 / H2 headings | Keywords, geo mentions |
| Models / subcategories highlighted | Names, structure |
| Purchase conditions | Guarantee, delivery, installment |
| FAQ topics | Questions they answer |
| LSI terms | Words not yet in our semantics |
| Weaknesses | Missing sections, no Schema, poor structure |

Output a Gap Analysis block **before** generating HTML:

```
## Gap Analysis: [Category]

### Конкурент 1: [domain]
- Модели: ...
- LSI-находки: ...
- Условия: ...
- Слабо: ...

### Наши преимущества:
✅ ...
⚠️ Добавить в контент: «...»
```

### Step 3 — Keyword matrix

Compile internally (do NOT output to user):

| Cluster | Keywords |
|---------|----------|
| Основной запрос | купить [Категория] Севастополь, [Категория] цена Крым |
| Доставка | доставка СДЭК по России, самовывоз Севастополь |
| Условия | гарантия 1 год, Trade-In, рассрочка, кредит |
| LSI модели | конкретные модели из топа продаж категории |
| Доверие | с 2011 года, официальный магазин, 15 лет опыта |

Distribute: H2 (1 ключ) → лид-абзац (2–3) → карточки (1/карточка) → FAQ (вопросы = запросы).

### Step 3b — GEO & AEO requirements

**GEO (Generative Engine Optimization)** — контент должен цитироваться AI-поисковиками (ChatGPT Search, Gemini, Perplexity):

- Лид `.mm-intro-text` обязан содержать все **5 сущностей**:
  1. Бренд: «Maxmobiles»
  2. Город: «Севастополь» (или «в Крыму»)
  3. Категория/модель: «iPhone 16 Pro Max», «MacBook Pro» и т.д.
  4. Действие: «купить», «в наличии», «официальный магазин»
  5. Условие покупки: «гарантия 1 год» или «доставка СДЭК»
- Каждый абзац самодостаточен — понятен без контекста (AI вырезает куски дословно)
- Преимущества конкретны: не «быстрая доставка», а «доставка по России через СДЭК»
- `LocalBusiness.description` — 2–3 предложения со специализацией + гео, не просто название
- `WebPage.description` — прямой ответ на главный транзакционный запрос страницы
- `speakable.cssSelector`: `[".mm-intro-text", ".mm-faq"]` — обязательно

**AEO (Answer Engine Optimization)** — FAQ должен попадать в Featured Snippets и голосовой поиск:

- Ровно **4 вопроса**, покрывающих 4 покупательских интента:
  1. **Доставка** — «Как купить [Модель] с доставкой по России?»
  2. **Гарантия** — «Какая гарантия на [Модель] в Maxmobiles?»
  3. **Trade-In** — «Можно ли сдать старый iPhone в зачёт нового?»
  4. **Выбор модели** — «Чем [Модель A] отличается от [Модель B]?»
- Первое предложение каждого ответа = прямой ответ (не вводное слово, не «мы»)
- Длина ответа: **40–80 слов** — оптимум для Featured Snippet
- JSON-LD `FAQPage` содержит **те же 4 вопроса**, что и HTML (строго совпадают)
- JSON-LD `FAQPage` всегда содержит поле `"name"` с человекочитаемым заголовком FAQ
- `FAQPage` используется только в JSON-LD; в HTML FAQ не добавлять `itemscope/itemtype/itemprop`

### Step 4 — Image audit

Count sections that benefit from a photo (hero, lifestyle, highlight).

**Always ask the user:**

> Для страницы потребуется [N] изображений:
> 1. **[Секция]** — [описание нужного фото, 1 строка]
> …
>
> Пришлите ссылки на каждое фото (или напишите «пропустить»).

Wait for response. If "пропустить" — use `src="#"` with `<!-- TODO: добавить фото -->`.

### Step 5 — Generate HTML

Follow **all** rules in `.cursor/rules/seo--for-shop-html-block.mdc` strictly.

**Two-block structure (split):**

**Block 1 — SEO core (always visible, no collapse):**
- `<div class="mm-block" itemscope itemtype="https://schema.org/WebPage">`
- **Intro**: H2 («Купить [Категория] в Севастополе — Maxmobiles») + `.mm-intro-text`
- **Trust strip**: 4 icons — гарантия 1 год, доставка СДЭК, Trade-In, магазин с 2011
- **Highlights grid**: strictly **3 or 6** `<article>` cards with `.mm-service-link` internal URLs (all cards in Block 1, never split)

**Block 2 — user content (collapse, initial height 350px — only if collapse enabled):**
- `<div class="mm-block">` without itemscope
- **Advantages**: `.mm-advantages-list` — why buy at Maxmobiles
- **FAQ**: 4 questions in plain HTML (`.mm-faq`) without schema microdata
- **CTA**: dark gradient block, 2 buttons (phone 8-800 + email)

**Collapse condition (from Step 1):**
- **Collapse ON** (default): wrap Block 2 in `.mm-collapse-wrapper → .mm-collapse-content`, add `.mm-collapse-fade`, `.mm-collapse-trigger`, `<script>` at the end. Block 1 is always visible.
- **Collapse OFF**: output Block 2 content directly after Block 1, no wrapper, no `<script>`

Required output order:
1. **JSON-LD** — `@graph`: LocalBusiness + WebPage + FAQPage (+ ItemList if listing models)
2. **Block 1** — `div.mm-block[itemscope]` → Intro + Trust strip + Highlights grid
3. **Block 2** — `.mm-collapse-wrapper → .mm-collapse-content → div.mm-block` (or plain `.mm-block` if collapse OFF) → Advantages + FAQ + CTA
4. **`<script>`** — collapse init at **350px** + `mmBlockToggle` function (only if collapse ON)

**Collapse HTML structure:**

`.mm-collapse-fade` должен быть **внутри** `.mm-collapse-content` (он `position:absolute` относительно него). `max-height` задаётся через JS IIFE — **не** инлайн в HTML:

```html
<div class="mm-collapse-wrapper">
  <div class="mm-collapse-content">
    <div class="mm-block">
      <!-- Advantages + FAQ + CTA -->
    </div>
    <div class="mm-collapse-fade"></div>  <!-- ВНУТРИ content, после mm-block -->
  </div>
  <div class="mm-collapse-trigger">
    <button class="mm-collapse-btn" type="button" aria-expanded="false" onclick="mmBlockToggle(this)">
      <span>Читать полностью</span>
      <svg class="mm-collapse-chevron" ...>...</svg>
    </button>
  </div>
</div>
```

**Collapse `<script>` — правильный паттерн (после закрывающего `</div>` обёртки):**

```html
<script>
(function () {
  var allContents = document.querySelectorAll('.mm-collapse-content');
  var content = allContents[allContents.length - 1];
  if (!content) return;
  content.style.maxHeight = '350px';
  content.dataset.mmState = 'collapsed';
  var fade = content.querySelector('.mm-collapse-fade');
  if (fade) fade.style.opacity = '1';
})();

function mmBlockToggle(btn) {
  var trigger = btn.parentElement;
  var content = trigger.previousElementSibling;
  var fade = content.querySelector('.mm-collapse-fade');
  var label = btn.querySelector('span');
  var wrapper = trigger.parentElement;

  if (content.dataset.mmState !== 'expanded') {
    content.style.transition = 'max-height 0.55s cubic-bezier(0.4, 0, 0.2, 1)';
    content.style.maxHeight = content.scrollHeight + 'px';
    content.dataset.mmState = 'expanded';
    if (fade) { fade.style.transition = 'opacity 0.25s ease'; fade.style.opacity = '0'; }
    btn.classList.add('mm-is-expanded');
    btn.setAttribute('aria-expanded', 'true');
    if (label) label.textContent = 'Свернуть';
    setTimeout(function () {
      if (content.dataset.mmState === 'expanded') {
        content.style.transition = '';
        content.style.maxHeight = 'none';
      }
    }, 580);
  } else {
    content.style.transition = '';
    content.style.maxHeight = content.scrollHeight + 'px';
    content.dataset.mmState = 'collapsing';
    void content.offsetHeight;
    content.style.transition = 'max-height 0.55s cubic-bezier(0.4, 0, 0.2, 1)';
    content.style.maxHeight = '350px';
    content.dataset.mmState = 'collapsed';
    if (fade) { fade.style.transition = 'opacity 0.3s ease 0.2s'; fade.style.opacity = '1'; }
    btn.classList.remove('mm-is-expanded');
    btn.setAttribute('aria-expanded', 'false');
    if (label) label.textContent = 'Читать полностью';
    setTimeout(function () {
      wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 120);
  }
}
</script>
```

Ключевые правила:
1. `mm-collapse-fade` — **внутри** `mm-collapse-content` (не снаружи)
2. `mm-is-expanded` — на **`btn`** (CSS: `.mm-collapse-btn.mm-is-expanded .mm-collapse-chevron`)
3. `max-height` и `fade.style.opacity = '1'` — инициализировать через IIFE при загрузке
4. `<script>` идёт **после** `</div>` обёртки, внутри того же HTML-блока CS-Cart

**Internal linking (обязательно):**
Each model/subcategory card **must** include a `.mm-service-link` pointing to its catalog page:
```html
<a href="https://maxmobiles.ru/[slug]/" class="mm-service-link"
   aria-label="Купить [Модель] в Maxmobiles">Выбрать модель &#8594;</a>
```
If the URL is unknown at generation time — use `href="#"` with `<!-- TODO: вставить URL -->`.
URLs are built from the **URL patterns** section of the brand constants below.

### Step 6 — Self-check before outputting

**Block split:**
- [ ] Block 1 contains: intro + trust strip + highlights grid (all cards, no split)
- [ ] Block 2 contains: advantages + FAQ + CTA
- [ ] Block 1 is NOT wrapped in `.mm-collapse-wrapper`
- [ ] If collapse ON: Block 2 is wrapped in `.mm-collapse-wrapper` with initial height **350px** (not 400px)
- [ ] `itemscope itemtype="https://schema.org/WebPage"` only on Block 1's `.mm-block`
- [ ] Block 2's `.mm-block` has NO `itemscope`/`itemtype`/`itemprop`

**Structure & code:**
- [ ] No `<style>` tags inside the block
- [ ] No concrete prices in ₽
- [ ] Все иконки — **inline SVG** (не HTML-сущности Unicode). TinyMCE CS-Cart декодирует сущности до сохранения; БД заменяет любые символы выше U+00FF на `???`. Это касается всех `&#x1F...`, `&#x2714;`, `&#x260E;` и т.п. В прозе допустимы текстовые сущности: `&#8212;` `&#8594;` `&#8243;` `&#183;`
- [ ] `fetchpriority="high"` on first `<img>`, `loading="lazy"` on all others
- [ ] Cards count is exactly 3 or 6
- [ ] Every card has a `.mm-service-link` with a valid internal URL
- [ ] В карточках нет `Product` microdata (`itemscope/itemtype=\"https://schema.org/Product\"` и `itemprop=*`)
- [ ] CTA uses store phone 8-800-250-81-58 and store@maxmobiles.ru
- [ ] H1 absent (only H2 → H3 hierarchy)

**GEO checklist:**
- [ ] Лид `.mm-intro-text` содержит все 5 сущностей: бренд + город + категория/модель + действие + условие покупки
- [ ] `LocalBusiness.description` — 2–3 предложения, не просто название магазина
- [ ] `WebPage.description` — прямой ответ на главный транзакционный запрос
- [ ] `speakable.cssSelector` = `[".mm-intro-text", ".mm-faq"]`
- [ ] Преимущества конкретны (не «быстро», а «через СДЭК»; не «официально», а «гарантия 1 год»)

**AEO checklist:**
- [ ] FAQ обёрнут в `<section class="mm-faq" ...>`
- [ ] 4 вопроса покрывают 4 интента: доставка · гарантия · Trade-In · выбор модели
- [ ] В JSON-LD `FAQPage` заполнено поле `"name"`
- [ ] Первое предложение каждого ответа = прямой ответ
- [ ] Длина каждого ответа: 40–80 слов
- [ ] JSON-LD FAQPage содержит те же 4 вопроса, что и HTML (строго совпадают)
- [ ] В HTML FAQ нет `itemscope`, `itemtype`, `itemprop` (во избежание дубля `FAQPage`)

**E-E-A-T checklist:**
- [ ] **Experience**: упомянут реальный опыт — «с 2011 года», «15 лет», конкретные цифры (клиенты, устройства)
- [ ] **Expertise**: лид или блок преимуществ содержит экспертный факт — авторизованный сервис, проверка качества, технические детали → выгоды
- [ ] **Authoritativeness**: присутствует статусный сигнал — «старейший Apple-эксперт в Крыму», рейтинг «4.9 / 120+ отзывов», официальный магазин
- [ ] **Trustworthiness**: минимум 2 доверительных сигнала — гарантия 1 год, Trade-In, рассрочка, реальный адрес/контакты
- [ ] Ни один E-E-A-T сигнал не выглядит как рекламный лозунг — все встроены в полезный контент

### Step 6b — CSS Validation (обязательно перед выводом)

Проверь каждый CSS-класс в сгенерированном HTML против `maxmobiles-styles.css`.
**Разрешены только классы из этого списка.** Если использован класс не из списка — сообщи пользователю и исправь.

**Белый список классов `maxmobiles-styles.css`:**

```
Основа:        mm-block
Intro:         mm-intro · mm-intro-text · mm-stat-badge · mm-badge-item · mm-badge-sep
Trust strip:   mm-trust-strip · mm-trust-item · mm-trust-icon · mm-trust-label
Section:       mm-section · mm-section-title · mm-section-lead
Cards:         mm-services-grid · mm-service-card · mm-service-card-icon · mm-service-link
Advantages:    mm-advantages-list · mm-advantage-item · mm-advantage-icon
               mm-advantage-num · mm-advantage-body
Highlight box: mm-highlight · mm-highlight-icon · mm-highlight-body
FAQ:           mm-faq · mm-faq-item · mm-faq-question · mm-faq-answer
CTA:           mm-cta · mm-cta-text · mm-cta-actions · mm-btn · mm-btn-primary · mm-btn-secondary
HowTo:         mm-howto-steps · mm-howto-step · mm-howto-step-body
Quick links:   mm-quick-links · mm-quick-links-tags · mm-quick-link
Collapse:      mm-collapse-wrapper · mm-collapse-content · mm-collapse-fade
               mm-collapse-trigger · mm-collapse-btn · mm-collapse-chevron · mm-is-expanded
```

**Критические правила, нарушение которых = баг:**

| Элемент | Обязательно | Запрещено |
|---|---|---|
| CTA заголовок | `<div class="mm-cta-text"><h3>` | standalone `<h2>` внутри `.mm-cta` |
| FAQ вопрос | `<p class="mm-faq-question">` | `<h4>` без этого класса |
| Заголовок карточки | `<h3>` внутри `.mm-service-card` | `<h4>` (не стилизован отдельно) |
| Пункт преимущества | `<li class="mm-advantage-item">` + `mm-advantage-icon` + `mm-advantage-body` | `<li><strong>...</strong> текст</li>` |
| Trust label | `<strong>Жирное</strong> обычный текст` | plain текст без `<strong>` |
| Секция-обёртка | `class="mm-section"` | `mm-services-section`, `mm-advantages-section` и любые другие самодельные классы |

**Если найден незарегистрированный класс** — перед выводом HTML сообщи:
> ⚠️ Класс `[имя]` отсутствует в `maxmobiles-styles.css` — заменён на `[правильный класс]`.

### Step 7 — Output

Сохранить финальный HTML в файл `SEO-страницы/[slug].html` (где `[slug]` — URL-slug страницы, например `macbook-neo`, `iphone-16-pro`). В чат выводить только одну строку подтверждения: путь к файлу и краткую сводку (H1, title, кол-во FAQ-вопросов).

---

## Brand constants (always use verbatim)

```
Name:        Maxmobiles
Free phone:  8 (800) 250-81-58  /  href="tel:+78002508158"
Store phone: +7 (978) 222-01-23 /  href="tel:+79782220123"
Address:     пр-т Нахимова 19, ТЦ «Детский мир», Севастополь
Hours:       ежедневно 10:00–20:00
Email:       store@maxmobiles.ru
Site:        https://maxmobiles.ru
Delivery:    СДЭК по всей России
Pickup:      Севастополь · Симферополь · Ялта · Москва
Rating:      4.9 / 120 отзывов
Since:       2011
```

## URL patterns

```
Category:  https://maxmobiles.ru/iphone/
Model:     https://maxmobiles.ru/iphone/iphone-16-pro-max/
Mac:       https://maxmobiles.ru/mac/macbook-pro/
Watch:     https://maxmobiles.ru/watch/
AirPods:   https://maxmobiles.ru/airpods/
```

## Highlights grid — what to include per category

**iPhone** → top 3: newest Pro Max, Pro, base model; or top 6 if large lineup  
**Mac** → MacBook Pro, MacBook Air, Mac mini (3 cards); add iMac, Mac Studio, MacBook Neo for 6  
**iPad** → iPad Pro, iPad Air, iPad mini (3 cards); add iPad + 2 accessories for 6  
**Apple Watch** → Watch Ultra, Series 11, SE (3 cards)  
**AirPods** → AirPods Pro 3, AirPods 4, AirPods Max (3 cards)  
**Samsung** → top 3 series: Z Fold, Z Flip, S-series  

Choose 3 if category has 3–4 key models; choose 6 if 5+.

## Trust strip — recommended for shop

Always use the two-level label structure — CSS `.mm-trust-label strong` bolds the first line:

```html
<span class="mm-trust-label"><strong>Гарантия 1 год</strong> на новую технику</span>
```

| Slot | `<strong>` text | Plain suffix |
|---|---|---|
| Щит (SVG) | Гарантия 1 год | на новую технику |
| Грузовик (SVG) | Доставка СДЭК | по всей России |
| Стрелки (SVG) | Trade-In | сдайте старое |
| Дом (SVG) | Maxmobiles | с 2011 года |

## Иконки — SVG-стандарт (обязательно)

**Критическое ограничение CS-Cart:** TinyMCE декодирует HTML-сущности в Unicode до сохранения. БД заменяет любые символы выше U+00FF на `???` — включая `&#x1F6E1;`, `&#x2714;`, `&#x260E;` и т.п. **Единственное надёжное решение — inline SVG** (содержит только ASCII).

Допустимы в тексте прозы (не как иконки): `&#8212;` (—), `&#8594;` (→), `&#8243;` (″), `&#183;` (·), `&#x7C;` (|).

### Trust strip (24×24)

**Гарантия — щит:**
```html
<span class="mm-trust-icon" aria-hidden="true">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M12 2L4 6v6c0 4.4 3.4 8.5 8 9.9 4.6-1.4 8-5.5 8-9.9V6L12 2z" fill="#0071e3"/>
    <path d="M8.5 12l2.5 2.5 4.5-4.5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</span>
```

**Доставка — грузовик:**
```html
<span class="mm-trust-icon" aria-hidden="true">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect x="1" y="6" width="13" height="10" rx="1.5" fill="#0071e3"/>
    <path d="M14 10h4l3 3v4h-7V10z" fill="#0071e3"/>
    <circle cx="5.5" cy="18" r="2" fill="#1d1d1f"/><circle cx="18.5" cy="18" r="2" fill="#1d1d1f"/>
    <circle cx="5.5" cy="18" r="1" fill="#fff"/><circle cx="18.5" cy="18" r="1" fill="#fff"/>
  </svg>
</span>
```

**Trade-In — стрелки обмена:**
```html
<span class="mm-trust-icon" aria-hidden="true">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M17 2l4 4-4 4" stroke="#0071e3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M3 11V9a4 4 0 014-4h14" stroke="#0071e3" stroke-width="2" stroke-linecap="round"/>
    <path d="M7 22l-4-4 4-4" stroke="#0071e3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M21 13v2a4 4 0 01-4 4H3" stroke="#0071e3" stroke-width="2" stroke-linecap="round"/>
  </svg>
</span>
```

**Maxmobiles с 2011 — дом:**
```html
<span class="mm-trust-icon" aria-hidden="true">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M3 10l9-7 9 7v10a2 2 0 01-2 2H5a2 2 0 01-2-2V10z" fill="#0071e3"/>
    <path d="M9 22V14h6v8" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</span>
```

### Advantages (20×20, currentColor)

**Галочка (гарантия, доставка, кредит):**
```html
<span class="mm-advantage-icon" aria-hidden="true">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="3,10 8,16 17,5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
</span>
```

**Trade-In (стрелки):**
```html
<span class="mm-advantage-icon" aria-hidden="true">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 6h12M14 6l-3-3m3 3l-3 3M18 14H6m0 0l3-3m-3 3l3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
</span>
```

**Звезда (опыт/рейтинг):**
```html
<span class="mm-advantage-icon" aria-hidden="true">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><polygon points="10,2 12.5,7.5 18.5,8.2 14,12.5 15.5,18.5 10,15.5 4.5,18.5 6,12.5 1.5,8.2 7.5,7.5"/></svg>
</span>
```

### CTA кнопки (16×16, currentColor)

**Телефон:**
```html
<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 2h3l1.5 3.5-1.5 1a8 8 0 0 0 3.5 3.5l1-1.5L14 10v3a1 1 0 0 1-1 1C5.5 14 2 7.5 2 3a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
```

**Email:**
```html
<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M2 5l6 5 6-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
```

**Правила SVG-иконок:**
- Атрибут `xmlns="http://www.w3.org/2000/svg"` обязателен — без него CS-Cart не рендерит SVG
- `aria-hidden="true"` на `<span>` или на `<svg>` (не дублировать на обоих)
- Цвет: `fill="#0071e3"` для trust strip; `stroke="currentColor"` / `fill="currentColor"` для advantages и кнопок

## Reference

Full HTML structure standard: `.cursor/rules/seo--for-shop-html-block.mdc`
