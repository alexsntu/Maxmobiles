---
name: seo-shop-page-builder-v2
description: "[v2 — нейроответы] Generates SEO-optimized HTML category page blocks for the Maxmobiles online store (Apple specialist, Sevastopol). v2 changes: FAQ moved to Block 1 (always visible, not behind collapse), 5 FAQ questions (informational first), sameAs in LocalBusiness JSON-LD, HowTo JSON-LD (optional), concrete-numbers requirement in .mm-intro-text. Use when the user provides a product category or device model and asks to create/write/generate an SEO page, category block, or HTML content for product category sections."
---

# SEO Shop Page Builder v2 — Maxmobiles

> **Архитектурные изменения v2 относительно v1:**
> 1. **FAQ перенесён в Блок 1** (всегда виден) — убирает риск снижения веса FAQ за CSS-коллапсом для нейроответов
> 2. **Блок 2 коллапс** содержит только преимущества + CTA (без FAQ)
> 3. **5 вопросов FAQ** вместо 4 — добавлен информационный вопрос первым
> 4. **`sameAs`** в `LocalBusiness` JSON-LD — обязательно
> 5. **HowTo JSON-LD** — опциональный шаблон для «Как купить» / «Как сдать по трейд-ин»
> 6. **Конкретные числа** в `.mm-intro-text` — обязательная проверка

Generates a full CS-Cart HTML block for a **product category** page.
Always geo-bound to **Севастополь / Крым / Maxmobiles**. Delivery via СДЭК across Russia.

---

## Workflow (execute in order)

**Критическая цепочка без пропусков:** разбор запроса → **Step 1b (гарантия)** → анализ Step 2a–3c → **Step 4 запрос фото для карточек** → только затем Step 5 генерация HTML.

**Запрещено самовольно «закрывать» шаги:** агент **не имеет права** подставлять «Гарантия качества» или вариант карточек «только иконки», если пользователь **сам явно об этом не сказал** в текущем диалоге. Пока Step 1b не закрыт — **не генерировать** финальный HTML. Пока Step 4 не закрыт — **не вставлять** секцию `.mm-services-grid`.

---

### Step 1 — Parse the request

Extract from the user's message:
- **Category or model** (e.g., iPhone 16 Pro Max, MacBook Pro, AirPods Pro, iPad Air, Apple Watch)
- **Page type**: top-level category or specific model landing
- **URL slug** inferred (e.g., `iphone`, `mac/macbook-pro`, `airpods`)
- **Competitor URLs** — если предоставлены, отметить для Step 2b
- **Collapse** — управление двухблочной структурой:
  - Стандарт (по умолчанию): **Блок 1 (всегда видим)** = intro + trust strip + карточки + **FAQ**; **Блок 2 (коллапс)** = преимущества + CTA
  - Если пользователь говорит **«без коллапса»** или **«no collapse»** → оба блока выводятся как обычные `<div class="mm-block">` без `.mm-collapse-wrapper` и `<script>` toggle
- **HowTo** — нужна ли разметка для «Как купить» / «Как сдать по трейд-ин» (см. Step 3c)

> **[v2] FAQ всегда в Блоке 1.** Это отличие от v1, где FAQ был в Блоке 2 за коллапсом. Причина: поисковые AI-системы (Яндекс Нейро, Google AI Overviews) активно берут FAQ как источник нейроответов; CSS-скрытый контент получает меньший индексационный вес. Если пользователь явно просит вернуть FAQ в коллапс — выполнить, зафиксировать.

---

### Step 1a — Блок отзывов на главной

Если пользователь просит сгенерировать блок **витрины отзывов**, перед Step 2 запросить для каждого отзыва:
- `date`, `avatarUrl`, `source`, `authorName`, `reviewText`, `sourceReviewUrl`

Обеспечить **all-sources strip** (иконка + название + ссылка на `https://maxmobiles.ru/testimonials/`).

---

### Step 1b — Обязательный вопрос о гарантии

**Если пользователь не указал гарантию** — задай вопрос и **остановись**:

> «Какая гарантия на товары в этой категории? Например: "1 год", "6 месяцев", "2 года" — или "нет гарантии".»

**Исключение:** пользователь уже указал срок в первом сообщении, написал «нет гарантии», или явно разрешил дефолт.

| Ответ пользователя | Формулировка в блоке |
|---|---|
| «1 год», «2 года», «6 месяцев» | «Официальная гарантия [срок]» |
| «нет гарантии» | «Гарантия качества» |

Применять **единообразно**: лид-абзац, первый пункт преимуществ, FAQ вопрос 4 о гарантии.

---

### Step 2a — SERP & keyword research

Run **5 WebSearch queries in parallel**:

1. `"купить [Категория/Модель] Севастополь"` — local buy intent
2. `"купить [Модель] с доставкой по России СДЭК"` — delivery signals
3. `"[Модель] цена купить Maxmobiles Крым"` — commercial price + geo intent
4. `"[Модель] Trade-In кредит рассрочка Apple"` — purchase condition signals
5. `"купить Айфон [Категория] Севастополь"` — кириллический коммерческий интент

**[v2] Мультибренд — обязательная проверка перед запуском:**

Если в категории присутствует 2+ бренда (Samsung, Dyson, Marshall, JBL и т.п.) — **до генерации HTML** выполнить Step 2a-multi для каждого вторичного бренда.

### [v2] Step 2a-multi — SERP по вторичным брендам

**Когда:** страница содержит бренды помимо Apple.

Для каждого вторичного бренда — **2 WebSearch параллельно**:
1. `"купить [Бренд] [категория] Севастополь"` — конкурентная среда по бренду
2. `"[Бренд] официальный магазин Россия"` — есть ли у бренда свой интернет-магазин?

При 2 вторичных брендах — 4 запроса параллельно. При 3 — 6 запросов.

**Результат — таблица по каждому бренду:**

```
| Бренд | Офиц. магазин в топ-10 | Агрегаторов | Maxmobiles в топ-10 | Флаг |
|---|---|---|---|---|
| [Бренд] | да/нет → [домен] | N | да/нет | 🎯 / 👁 / — |
```

**Правила флага (применять перед Step 3 и Step 3b):**

| Условие | Флаг | Что делать с брендом |
|---|---|---|
| Есть офиц. магазин бренда в топ-10 | **—** | Бренд → description + H1 как ассортиментный факт, **не в title** |
| Нет офиц. магазина + 0–1 агрегатор | **🎯** | Бренд → кандидат в title |
| Нет офиц. магазина + 2–3 агрегатора | **👁** | Бренд → description |
| Нет офиц. магазина + 4+ агрегаторов | **—** | Не в title |

Известные официальные магазины вторичных брендов Maxmobiles (автоматический флаг —):

| Бренд | Официальный магазин |
|---|---|
| Samsung | `samsung.com/ru`, `samsung-shop.ru` |
| Dyson | `dyson.ru` |
| Marshall | `marshallheadphones.com/ru` |
| JBL | `ru.jbl.com` |

Если бренд есть в этом списке → SERP-запрос по нему можно не запускать, сразу присваивать флаг **—** и экономить запросы.

**Apple не является вторичным брендом** — Maxmobiles является Apple-специалистом, блокировка не применяется.

---

### Step 2c — Кириллические написания бренда

Для любой категории Apple / Samsung / других брендов — **обязательно** проверять кириллические LSI-варианты:

| Латиница | Кириллица | Альтернативные |
|---|---|---|
| iPhone | Айфон | Ифон |
| iPad | Айпад | — |
| MacBook | Макбук | Мак |
| AirPods | Эйрподс | Аирподс |
| Apple Watch | Эпл Вотч | — |
| Samsung | Самсунг | — |
| Galaxy | Галакси | — |
| Dyson | Дайсон | — |

**Правила использования:**
1. **JSON-LD `alternateName`** — массив с кириллическими вариантами
2. **Лид `.mm-intro-text`** — один раз в скобках: «iPhone (Айфон)»
3. **Один вопрос FAQ** — переформулировать с кириллическим брендом
4. **Одна карточка модели** — добавить «Айфон» / «Макбук» / «Самсунг» в описание (только одной)

---

### Step 2b — Competitor analysis (if URLs provided)

WebFetch каждого URL. Извлечь:

| Parameter | What to record |
|---|---|
| H1 / H2 headings | Keywords, geo |
| Models highlighted | Names, structure |
| Purchase conditions | Guarantee, delivery |
| FAQ topics | Questions they answer |
| LSI terms | Words not yet in our semantics |
| Weaknesses | Missing sections, no Schema |

Output Gap Analysis block перед генерацией HTML.

---

### Step 3 — Keyword matrix

Compile internally (NOT output to user):

| Cluster | Keywords |
|---------|----------|
| Основной запрос | купить [Категория] Севастополь, [Категория] цена Крым |
| Доставка | доставка СДЭК по России, самовывоз Севастополь |
| Условия | гарантия 1 год, Trade-In, кредит |
| LSI модели | конкретные модели из топа продаж |
| Доверие | с 2011 года, Apple-эксперт, авторизованный сервис Apple |
| **Кириллика** | **купить Айфон [Категория], Макбук Севастополь** |

Распределить: H2 (1 ключ) → лид (2–3, вкл. кириллику в скобках) → одна карточка (кириллика) → FAQ вопрос 1 или 2 (кириллика) → JSON-LD alternateName.

---

### Step 3b — GEO & AEO requirements

**GEO (Generative Engine Optimization)** — контент должен цитироваться AI-поисковиками:

#### GEO по AI-платформам

**Яндекс Нейро:**
- Генерирует ответы из Яндекс-индекса — страница должна быть в топ-10 Яндекса
- Поддерживает `speakable` — блоки `.mm-intro-text` и `.mm-faq` попадут в Нейро
- `LocalBusiness` с `sameAs` на Яндекс Карты (org ID 237809440282) → идентификация магазина

**ChatGPT Search:**
- Использует Bing-индекс → важны внешние ссылки и упоминания бренда
- Schema.org разметка поддерживается через Bing

**Perplexity:**
- Собственный краулер → предпочитает конкретные факты и чёткую структуру
- Цитирует списки, FAQ, числовые данные (рейтинг, годы работы, количество точек)

**Gemini / Google AI Overviews:**
- Работает на Google-индексе → HowTo и FAQ schema помогают попасть в AI Overviews
- `speakable` для Google Assistant

#### GEO requirements — лид `.mm-intro-text`

Лид **обязан содержать все 5 сущностей:**
1. Бренд: «Maxmobiles»
2. Город: «Севастополь» (или «в Крыму»)
3. Категория/модель
4. Действие: «купить», «в наличии», «фирменный Apple-магазин»
5. Условие покупки: «гарантия 1 год» или «доставка СДЭК»

- **[v2] Конкретные числа** в лиде — обязательно минимум одно:
  - «3 точки самовывоза» / «Севастополь, Симферополь, Ялта»
  - «с 2011 года» / «более 14 лет»
  - «365 дней гарантии» (вместо или вместе с «1 год»)
  - «доставка за 2–7 дней СДЭК» (если подтверждено)
- Каждый абзац самодостаточен — понятен без контекста
- Преимущества конкретны: не «быстрая доставка», а «доставка по России через СДЭК»
- `speakable.cssSelector`: `[".mm-intro-text", ".mm-faq"]` — обязательно
- `LocalBusiness.description` — 2–3 предложения со специализацией + гео + конкретный факт
- `WebPage.description` — прямой ответ на главный транзакционный запрос, НЕ начинать с «Maxmobiles»/«Мы»

**[v2] sameAs в LocalBusiness — обязательно:**

```json
"sameAs": ["https://yandex.ru/maps/org/237809440282"]
```

**AEO (Answer Engine Optimization)** — FAQ в Блоке 1 (всегда видим):

- Ровно **5 вопросов**, покрывающих 5 интентов:
  1. **[v2] Информационный** — «Что важно учесть при выборе [категория]?» *(добавлен в v2)*
  2. **Сравнение моделей** — «Чем [Модель A] отличается от [Модель B]?»
  3. **Доставка** — «Как купить [категория] с доставкой по России?»
  4. **Гарантия** — «Какая гарантия на [категория] в Maxmobiles?»
  5. **Трейд-ин** — «Как сдать старое устройство по Trade-In в Maxmobiles?»
- Первое предложение каждого ответа = прямой ответ
- Длина ответа: **40–80 слов** — оптимум для Featured Snippet и нейроответа
- Вопрос 1 (информационный) и вопрос 2 (сравнение) — **приоритет для нейроответов** (AI-системы берут их чаще коммерческих)
- Один вопрос из пяти переформулировать с кириллическим написанием бренда
- В HTML-секции FAQ **не использовать** `itemscope`, `itemtype`, `itemprop` — FAQPage разметка не применяется

> **Почему информационный вопрос первым:** AI-системы чаще цитируют начало списка FAQ. Информационный вопрос («как выбрать») попадает в нейроответ гораздо чаще транзакционного («как купить»).

---

### [v2] Step 3c — HowTo JSON-LD (опционально)

**Когда добавлять:** пользователь запросил HowTo в Step 1 ИЛИ FAQ содержит ответ о процедуре покупки или трейд-ин.

HowTo-разметка помогает AI-поисковикам цитировать пошаговые инструкции. Добавляется в `[slug]-jsonld.html` как отдельный `<script>`.

**Триггер A — «Как купить с доставкой»:**

```json
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "Как купить [категория] в Maxmobiles с доставкой по России",
  "description": "Покупка [категория] в Maxmobiles с доставкой СДЭК или самовывозом в Крыму.",
  "step": [
    {
      "@type": "HowToStep",
      "position": 1,
      "name": "Выберите модель",
      "text": "Откройте каталог Maxmobiles на maxmobiles.ru, выберите нужную модель из наличия или позвоните 8-800-250-81-58."
    },
    {
      "@type": "HowToStep",
      "position": 2,
      "name": "Оформите заказ",
      "text": "Добавьте товар в корзину, укажите адрес доставки СДЭК или выберите самовывоз в Севастополе, Симферополе или Ялте."
    },
    {
      "@type": "HowToStep",
      "position": 3,
      "name": "Получите покупку с гарантией",
      "text": "Доставка СДЭК по всей России — 2–7 дней. Самовывоз в Севастополе, Симферополе, Ялте. Гарантия 1 год, авторизованный сервис Apple."
    }
  ]
}
```

**Триггер B — «Как сдать по трейд-ин»:**

```json
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "Как сдать старое устройство по Trade-In в Maxmobiles",
  "description": "Trade-In в Maxmobiles: оцените старое устройство и получите скидку на новый Apple.",
  "step": [
    {
      "@type": "HowToStep",
      "position": 1,
      "name": "Оцените устройство",
      "text": "Принесите старый смартфон, планшет или MacBook в Maxmobiles — пр-т Нахимова 19, ТЦ «Детский мир», Севастополь. Оценка бесплатна."
    },
    {
      "@type": "HowToStep",
      "position": 2,
      "name": "Выберите новое устройство",
      "text": "Получите скидку на любой товар Maxmobiles в размере оценочной стоимости сданного устройства."
    },
    {
      "@type": "HowToStep",
      "position": 3,
      "name": "Оформите покупку",
      "text": "Доплатите разницу наличными или картой, получите новый Apple с гарантией 1 год."
    }
  ]
}
```

Адаптировать `name` и `step[].text` под конкретную категорию. Добавить в `[slug]-jsonld.html` после остальных schema-блоков.

---

### Step 4 — ОБЯЗАТЕЛЬНЫЙ запрос фото для карточек (блокирующий шаг)

**Когда выполнять:** сразу после Step 3c — когда ясен список карточек (3 или 6). **До этого шага не начинать** вёрстку `.mm-services-grid`.

Вывести сообщение **строго** в таком формате:

> Для блока карточек (highlights grid) нужны изображения — **всего [N] карточек**:
>
> 1. **[Название карточки 1]** — пришлите **прямой URL** картинки
> 2. **[Название карточки 2]** — …
>
> Пришлите **N URL в том же порядке**, или напишите **«пропустить»** — тогда все карточки с иконками.

**Запрещено:**
- Самостоятельно брать ссылки с `maxmobiles.ru/images/...` без явного решения пользователя
- Использовать URL из других файлов репозитория без подтверждения
- Верстать иконки без явного «пропустить»

**Если пользователь прислал URL:** использовать Вариант А (`mm-service-card-img`). Первое фото: `fetchpriority="high"` без `loading="lazy"`. Остальные: `loading="lazy"`.

**Если «пропустить»:** использовать Вариант Б (`mm-service-card-icon` с SVG-символом из таблицы иконок).

Карточки в `.mm-services-grid` — **без** `itemscope itemtype="https://schema.org/Product"` и без `itemprop`.

---

### Step 5 — Generate HTML

Следовать всем правилам `seo--for-shop-html-block.mdc`.

**Два файла на каждую страницу:**

| Файл | Куда в CS-Cart | Что содержит |
|---|---|---|
| `[slug].html` | Wysiwyg-описание категории | Только HTML (Блок 1 + Блок 2 + `<script>` collapse) |
| `[slug]-jsonld.html` | Дизайн → Макеты → HTML-блок | Только JSON-LD (отдельные `<script>` per type, без `@graph`) |

---

#### [v2] Структура Блока 1 — SEO-блок + FAQ (всегда видимый)

```html
<!-- Блок 1: Всегда видимый — intro + trust strip + карточки + FAQ -->
<div class="mm-block" itemscope itemtype="https://schema.org/WebPage">

  <section class="mm-section mm-intro" aria-labelledby="[id]-heading">
    <!-- H2 + .mm-intro-text (5 сущностей + конкретное число) -->
    <!-- Бейдж рейтинга сразу после .mm-intro-text — ссылка на отзывы, ОДНОЙ строкой текста -->
    <a class="mm-stat-badge" href="https://yandex.ru/maps/org/maxmobiles_ru/237809440282/reviews/" target="_blank" rel="nofollow noopener" aria-label="Отзывы о Maxmobiles на Яндекс Картах">Яндекс Карты &#8212; 5.0 &#8212; 1000+ отзывов</a>
  </section>

  <div class="mm-trust-strip" role="list" aria-label="Преимущества Maxmobiles">
    <!-- 4 trust items -->
  </div>

  <section class="mm-section" aria-labelledby="models-heading">
    <!-- h3 + mm-section-lead + mm-services-scroll-wrap → mm-services-grid (3 или 6 карточек) -->
  </section>

  <!-- [v2] FAQ в Блоке 1 — всегда виден, не за коллапсом -->
  <section class="mm-faq" aria-labelledby="faq-heading">
    <h3 id="faq-heading" class="mm-section-title">Вопросы о [категория] в Maxmobiles</h3>

    <!-- Вопрос 1: информационный (новый в v2) -->
    <div class="mm-faq-item">
      <p class="mm-faq-question">Что важно учесть при выборе [категория]?</p>
      <div class="mm-faq-answer">
        <p>[Прямой ответ: 40–80 слов. Ключевые параметры выбора — без воды.]</p>
      </div>
    </div>

    <!-- Вопрос 2: сравнение моделей (с кириллическим вариантом если нужно) -->
    <div class="mm-faq-item">
      <p class="mm-faq-question">Чем [Модель A] отличается от [Модель B]?</p>
      <div class="mm-faq-answer">
        <p>[Прямой ответ: конкретные технические отличия.]</p>
      </div>
    </div>

    <!-- Вопрос 3: доставка -->
    <div class="mm-faq-item">
      <p class="mm-faq-question">Как купить [категория] с доставкой по России?</p>
      <div class="mm-faq-answer">
        <p>[Прямой ответ: оформить на maxmobiles.ru или по телефону 8-800-250-81-58, доставка СДЭК, самовывоз в Севастополе, Симферополе, Ялте.]</p>
      </div>
    </div>

    <!-- Вопрос 4: гарантия -->
    <div class="mm-faq-item">
      <p class="mm-faq-question">Какая гарантия на [категория] в Maxmobiles?</p>
      <div class="mm-faq-answer">
        <p>[Прямой ответ с формулировкой из Step 1b. Упомянуть: авторизованный сервисный центр Apple.]</p>
      </div>
    </div>

    <!-- Вопрос 5: трейд-ин -->
    <div class="mm-faq-item">
      <p class="mm-faq-question">Как сдать старое устройство по Trade-In в Maxmobiles?</p>
      <div class="mm-faq-answer">
        <p>[Прямой ответ: принести в точку → оценка → скидка на новое. Адрес: пр-т Нахимова 19, ТЦ «Детский мир».]</p>
      </div>
    </div>

  </section>

</div><!-- /.mm-block (SEO + FAQ: всегда видимы) -->
```

> **Правило:** все 5 вопросов FAQ — целиком в Блоке 1. Не переносить FAQ в Блок 2. FAQ не использует `itemscope`, `itemtype`, `itemprop`.

---

#### [v2] Структура Блока 2 — коллапс (преимущества + CTA)

```html
<!-- Блок 2: Коллапс — только преимущества + CTA (FAQ перенесён в Блок 1) -->
<div class="mm-collapse-wrapper">
  <div class="mm-collapse-content">
    <div class="mm-block">  <!-- без itemscope! -->

      <section class="mm-section" aria-labelledby="advantages-heading">
        <h3 id="advantages-heading" class="mm-section-title">Почему выбирают Maxmobiles</h3>
        <ul class="mm-advantages-list" role="list">
          <!-- 5 пунктов: гарантия + доставка + трейд-ин + кредит+бонусы + Яндекс-виджет -->
        </ul>
      </section>

      <!-- CTA -->
      <div class="mm-cta">
        <div class="mm-cta-text">
          <h3>Нужна помощь с выбором?</h3>
          <p>Наши консультанты помогут подобрать [категория] под ваши задачи и бюджет.</p>
        </div>
        <div class="mm-cta-actions">
          <a href="tel:+78002508158" class="mm-btn mm-btn-primary"
             aria-label="Позвонить в Maxmobiles">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 2h3l1.5 3.5-1.5 1a8 8 0 0 0 3.5 3.5l1-1.5L14 10v3a1 1 0 0 1-1 1C5.5 14 2 7.5 2 3a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            8 (800) 250-81-58
          </a>
          <a href="mailto:store@maxmobiles.ru" class="mm-btn mm-btn-secondary"
             aria-label="Написать в Maxmobiles">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M2 5l6 5 6-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            store@maxmobiles.ru
          </a>
        </div>
      </div>

    </div><!-- /.mm-block -->
    <div class="mm-collapse-fade"></div>
  </div><!-- /.mm-collapse-content -->

  <div class="mm-collapse-trigger">
    <button class="mm-collapse-btn" onclick="mmBlockToggle(this)" type="button" aria-expanded="false">
      <span class="mm-collapse-btn-text">Читать подробнее</span>
      <span class="mm-collapse-chevron" aria-hidden="true">
        <svg width="14" height="9" viewBox="0 0 14 9" fill="none">
          <path d="M1 1.5L7 7.5L13 1.5" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
    </button>
  </div>
</div><!-- /.mm-collapse-wrapper -->

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
  var fade    = content.querySelector('.mm-collapse-fade');
  var label   = btn.querySelector('.mm-collapse-btn-text');
  var wrapper = trigger.parentElement;

  if (content.dataset.mmState !== 'expanded') {
    content.style.transition = 'max-height 0.55s cubic-bezier(0.4, 0, 0.2, 1)';
    content.style.maxHeight  = content.scrollHeight + 'px';
    content.dataset.mmState  = 'expanded';
    if (fade) { fade.style.transition = 'opacity 0.25s ease'; fade.style.opacity = '0'; }
    btn.classList.add('mm-is-expanded');
    btn.setAttribute('aria-expanded', 'true');
    if (label) label.textContent = 'Скрыть';
    setTimeout(function () {
      if (content.dataset.mmState === 'expanded') {
        content.style.transition = '';
        content.style.maxHeight  = 'none';
      }
    }, 580);
  } else {
    content.style.transition = '';
    content.style.maxHeight  = content.scrollHeight + 'px';
    content.dataset.mmState  = 'collapsing';
    void content.offsetHeight;
    content.style.transition = 'max-height 0.55s cubic-bezier(0.4, 0, 0.2, 1)';
    content.style.maxHeight  = '350px';
    content.dataset.mmState  = 'collapsed';
    if (fade) { fade.style.transition = 'opacity 0.3s ease 0.2s'; fade.style.opacity = '1'; }
    btn.classList.remove('mm-is-expanded');
    btn.setAttribute('aria-expanded', 'false');
    if (label) label.textContent = 'Читать подробнее';
    setTimeout(function () {
      wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 120);
  }
}
</script>
```

> **Важно:** этот `<script>` идёт строго после `</div><!-- /.mm-collapse-wrapper -->` в конце файла `[slug].html`. Использовать этот шаблон дословно — не упрощать. Ранее использовавшийся вариант через `classList.contains('mm-is-expanded')` не работает в CS-Cart.

---

#### Блок 1 — детальные правила

Содержит строго:
- **Intro**: `<section class="mm-section mm-intro" aria-labelledby="...">` → H2 + `.mm-intro-text`
- **Trust strip**: `<div class="mm-trust-strip" role="list">` → 4 иконки с подписями
- **Highlights grid**: строго **3 или 6** `<article>` карточек; `<h3 class="mm-section-title">` + `<p class="mm-section-lead">`; сетка в `.mm-services-scroll-wrap` с кнопками `mm-scroll-btn--prev` / `mm-scroll-btn--next`
- **[v2] FAQ**: `<section class="mm-faq">` → 5 вопросов (см. структуру выше)

> **Правило карточек:** 3 или 6 — целиком в Блоке 1. Не разрезать. Внутренние ссылки в карточках обязательны.

**Highlights grid — HTML-обёртка со скроллером:**

```html
<div class="mm-services-scroll-wrap">
  <button class="mm-scroll-btn mm-scroll-btn--prev" aria-label="Предыдущие модели" type="button">&#8592;</button>
  <div class="mm-services-grid" role="list">
    <!-- 3 или 6 article.mm-service-card -->
  </div>
  <button class="mm-scroll-btn mm-scroll-btn--next" aria-label="Следующие модели" type="button">&#8594;</button>
</div>
```

**JS для скроллера** — в `<script>` блоке в конце файла рядом с `mmBlockToggle`:

```javascript
(function () {
  var wrap = document.querySelector('.mm-services-scroll-wrap');
  if (!wrap) return;
  var grid = wrap.querySelector('.mm-services-grid');
  var btnPrev = wrap.querySelector('.mm-scroll-btn--prev');
  var btnNext = wrap.querySelector('.mm-scroll-btn--next');
  function isMobile() { return window.innerWidth <= 640; }
  function getScrollAmount() {
    var card = grid.querySelector('.mm-service-card');
    return card ? card.offsetWidth + 12 : 272;
  }
  function updateArrows() {
    if (!isMobile()) return;
    var atStart = grid.scrollLeft <= 4;
    var atEnd = grid.scrollLeft >= grid.scrollWidth - grid.clientWidth - 4;
    btnPrev.style.opacity = atStart ? '0.35' : '1';
    btnNext.style.opacity = atEnd ? '0.35' : '1';
    btnPrev.style.pointerEvents = atStart ? 'none' : '';
    btnNext.style.pointerEvents = atEnd ? 'none' : '';
  }
  if (btnPrev) btnPrev.addEventListener('click', function () { grid.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' }); });
  if (btnNext) btnNext.addEventListener('click', function () { grid.scrollBy({ left: getScrollAmount(), behavior: 'smooth' }); });
  grid.addEventListener('scroll', updateArrows);
  window.addEventListener('resize', updateArrows);
  updateArrows();
}());
```

#### Блок 2 — детальные правила (обновлены для v2)

Содержит строго:
- **Advantages**: `<ul class="mm-advantages-list" role="list">` → ровно 5 пунктов (4 преимущества + Яндекс-виджет)
- **CTA**: dark gradient block, 2 кнопки — телефон 8-800 + email
- **FAQ отсутствует** — он в Блоке 1

**Последний пункт `.mm-advantages-list` — Яндекс-виджет:**

```html
<li class="mm-advantage-item" role="listitem">
  <span class="mm-advantage-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><polygon points="10,2 12.5,7.5 18.5,8.2 14,12.5 15.5,18.5 10,15.5 4.5,18.5 6,12.5 1.5,8.2 7.5,7.5"/></svg></span>
  <div class="mm-advantage-body">
    <strong>Более 1000 отзывов на Яндексе</strong> &#8212; Maxmobiles работает с 2011 года и является старейшим Apple-экспертом в Крыму.
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:10px;padding:8px 12px;background:#F9F9F9;border-radius:10px;border:1px solid #EEEEEE;">
      <iframe src="https://yandex.ru/sprav/widget/rating-badge/237809440282?type=rating"
              width="150" height="50" frameborder="0"
              title="Рейтинг Maxmobiles на Яндекс Картах" loading="lazy"
              style="display:block;border:none;flex-shrink:0;"></iframe>
      <a href="https://yandex.ru/maps/org/maxmobiles_ru/237809440282/reviews/"
         target="_blank" rel="nofollow noopener"
         style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#FF6900;color:#fff;border-radius:8px;font-weight:600;font-size:14px;text-decoration:none;white-space:nowrap;"
         aria-label="Посмотреть все отзывы о Maxmobiles на Яндексе">Посмотреть все отзывы &#8594;</a>
    </div>
  </div>
</li>
```

**Ссылки в пунктах `.mm-advantages-list`:**

| Пункт | URL |
|---|---|
| Гарантия | `https://maxmobiles.ru/garantiya/` |
| Доставка | `https://maxmobiles.ru/oplata-i-dostavka/` |
| Trade-In | `https://maxmobiles.ru/apple-trade-in/` |
| Кредит и рассрочка | `https://maxmobiles.ru/kredit/` |
| Бонусная программа | `https://maxmobiles.ru/bonusnaya-programma-ru/` |

**Запрещено:** «беспроцентная рассрочка» — только «кредит и рассрочка».

#### [v2] JSON-LD — шаблоны для `[slug]-jsonld.html`

Четыре отдельных `<script>` блока (без `@graph`):

**Блок 1 — LocalBusiness с sameAs:**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "Maxmobiles",
  "url": "https://maxmobiles.ru/[slug]/",
  "telephone": "+78002508158",
  "email": "store@maxmobiles.ru",
  "description": "Maxmobiles — старейший Apple-эксперт в Крыму с 2011 года. Официальный магазин iPhone, Mac, iPad, AirPods в Севастополе. Самовывоз в Севастополе, Симферополе, Ялте. Доставка СДЭК по всей России. Авторизованный сервисный центр Apple, трейд-ин, кредит.",
  "sameAs": [
    "https://yandex.ru/maps/org/237809440282"
  ],
  "alternateName": ["Магазин Apple Maxmobiles", "Магазин Айфон Севастополь", "[Категория] Apple Крым", "Купить Айфон Севастополь"],
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "пр-т Нахимова 19, ТЦ «Детский мир»",
    "addressLocality": "Севастополь",
    "addressRegion": "Республика Крым",
    "addressCountry": "RU"
  },
  "openingHours": "Mo-Su 10:00-20:00",
  "areaServed": [
    { "@type": "City", "name": "Севастополь" },
    { "@type": "City", "name": "Симферополь" },
    { "@type": "City", "name": "Ялта" },
    { "@type": "State", "name": "Крым" },
    { "@type": "Country", "name": "Россия" }
  ],
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": 5.0,
    "reviewCount": 1000,
    "bestRating": 5,
    "worstRating": 1
  },
  "foundingDate": "2011",
  "priceRange": "₽₽₽"
}
</script>
```

**Блок 2 — WebPage с speakable и dateModified:**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "[H2 заголовок категории]",
  "description": "[WebPage.description — прямой ответ, НЕ начинать с «Maxmobiles»/«Мы», содержит конкретное число]",
  "url": "https://maxmobiles.ru/[slug]/",
  "datePublished": "[ГГГГ-ММ-ДД]",
  "dateModified": "[ГГГГ-ММ-ДД]",
  "inLanguage": "ru",
  "isPartOf": {
    "@type": "WebSite",
    "name": "Maxmobiles",
    "url": "https://maxmobiles.ru"
  },
  "speakable": {
    "@type": "SpeakableSpecification",
    "cssSelector": [".mm-intro-text", ".mm-faq"]
  }
}
</script>
```

**Блок 3 — HowTo (если Step 3c активирован):**

```html
<!-- [v2] HowTo — добавить если Step 3c активирован -->
<!-- <script type="application/ld+json">{ "@context": ..., "@type": "HowTo", ... }</script> -->
```

**Блок 4 — ItemList (опционально, при перечислении моделей):**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "[Название категории] в Maxmobiles",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "[Модель 1]", "url": "[URL]" },
    { "@type": "ListItem", "position": 2, "name": "[Модель 2]", "url": "[URL]" },
    { "@type": "ListItem", "position": 3, "name": "[Модель 3]", "url": "[URL]" }
  ]
}
</script>
```

> **Заполнить один раз:** `datePublished/dateModified` — дата публикации/обновления. `reviewCount` — обновлять при изменении числа отзывов.

---

### Step 6 — Self-check before outputting

**Два файла:**
- [ ] Сгенерировано **два файла**: `[slug].html` и `[slug]-jsonld.html`
- [ ] В `[slug].html` **нет** `<script type="application/ld+json">`
- [ ] В `[slug]-jsonld.html` нет `@graph`

**[v2] Двухблочная структура — обновлено:**
- [ ] **Блок 1** содержит: intro-секция + trust strip + highlights grid + **FAQ (5 вопросов)**
- [ ] **Блок 2** содержит: преимущества + CTA — **без FAQ**
- [ ] `itemscope itemtype="https://schema.org/WebPage"` только на `.mm-block` Блока 1
- [ ] В Блоке 2 `.mm-block` **нет** `itemscope`/`itemtype`/`itemprop`
- [ ] При collapse ON — Блок 2 обёрнут в `.mm-collapse-wrapper`, Блок 1 — нет
- [ ] Текст кнопки: `«Читать подробнее»` / JS восстанавливает при сворачивании

**Structure & code:**
- [ ] Step 1b выполнен: пользователь ответил по гарантии
- [ ] Step 4 выполнен: пользователь прислал URL или написал «пропустить»
- [ ] Нет `<style>` тегов
- [ ] Нет цен в ₽
- [ ] Иконки — только inline SVG (не HTML-сущности Unicode выше U+00FF)
- [ ] Карточек ровно 3 или 6
- [ ] Карточки без `itemscope itemtype="https://schema.org/Product"` и без `itemprop`
- [ ] Каждая карточка имеет `.mm-service-link` с валидным URL
- [ ] Каждый `.mm-advantage-item` содержит `<a class="mm-advantage-link">` на соответствующую страницу
- [ ] Слова «беспроцентная рассрочка» отсутствуют
- [ ] Гарантия из Step 1b применена единообразно: лид + преимущества + FAQ вопрос 4
- [ ] Сетка в `.mm-services-scroll-wrap` с кнопками
- [ ] JS скроллера добавлен рядом с `mmBlockToggle`
- [ ] Карточки с фото: первый `<img>` имеет `fetchpriority="high"` без `loading="lazy"`
- [ ] CTA кнопка 1: `href="tel:+78002508158"`
- [ ] CTA кнопка 2: `href="mailto:store@maxmobiles.ru"`
- [ ] H1 absent (только H2 → H3 иерархия)
- [ ] Intro-секция: `<section class="mm-section mm-intro">`, не `<header>`

**[v2] GEO checklist — обновлено:**
- [ ] Лид `.mm-intro-text` содержит все 5 сущностей: бренд + город + категория + действие + условие
- [ ] **[v2] Лид содержит конкретное число** (3 точки / с 2011 года / 365 дней / и т.д.)
- [ ] `LocalBusiness.description` — 2–3 предложения, не просто название магазина
- [ ] **[v2] `LocalBusiness.sameAs`** содержит `"https://yandex.ru/maps/org/237809440282"`
- [ ] `JSON-LD aggregateRating`: `ratingValue` и `reviewCount` — числа без кавычек
- [ ] `WebPage.description` — прямой ответ, **НЕ начинается с «Maxmobiles»/«Мы»**
- [ ] **[v2] `WebPage.description` содержит конкретное число**
- [ ] `speakable.cssSelector` = `[".mm-intro-text", ".mm-faq"]`
- [ ] Преимущества конкретны («через СДЭК», «гарантия 1 год»)

**[v2] AEO checklist — обновлено:**
- [ ] FAQ обёрнут в `<section class="mm-faq">` и находится в **Блоке 1** (не в Блоке 2)
- [ ] **5 вопросов** — информационный + сравнение + доставка + гарантия + трейд-ин
- [ ] Первый вопрос — информационный («Что важно учесть при выборе...»)
- [ ] Первое предложение каждого ответа = прямой ответ
- [ ] Длина каждого ответа: 40–80 слов
- [ ] В HTML FAQ нет `itemscope`, `itemtype`, `itemprop`
- [ ] Один вопрос FAQ переформулирован с кириллическим брендом
- [ ] **[v2] Если Step 3c активирован** — HowTo JSON-LD добавлен в `[slug]-jsonld.html`

**Кириллические LSI-запросы:**
- [ ] JSON-LD `LocalBusiness.alternateName` содержит кириллические варианты
- [ ] Лид `.mm-intro-text` содержит «iPhone (Айфон)» или аналог для нужной категории
- [ ] Один вопрос FAQ — с кириллическим написанием бренда/устройства
- [ ] Одна карточка — кириллический вариант в описании
- [ ] Кириллика отсутствует в `alt` и `title` изображений

**E-E-A-T checklist:**
- [ ] **Experience**: «с 2011 года», «более 14 лет», конкретные факты
- [ ] **Expertise**: «авторизованный сервисный центр Apple», технические детали → выгоды
- [ ] **Authoritativeness**: «старейший Apple-эксперт в Крыму», рейтинг «5.0 / 1000+ отзывов»
- [ ] **Trustworthiness**: гарантия 1 год, трейд-ин, бонусная программа, реальный адрес

**Виджет отзывов:**
- [ ] Последний пункт `.mm-advantages-list` содержит `<iframe>` виджет Яндекс рейтинга (ID `237809440282`)
- [ ] Рядом — ссылка с `rel="nofollow noopener"` на reviews

---

### Step 6b — CSS Validation

Проверь каждый CSS-класс против `maxmobiles-styles.css`. Разрешены только классы из белого списка:

```
Основа:        mm-block
Intro:         mm-intro · mm-intro-text · mm-stat-badge
               (mm-badge-item · mm-badge-sep — НЕ использовать, см. правило .mm-stat-badge ниже)
Trust strip:   mm-trust-strip · mm-trust-item · mm-trust-icon · mm-trust-label
Section:       mm-section · mm-section-title · mm-section-lead
Cards:         mm-services-scroll-wrap · mm-scroll-btn · mm-scroll-btn--prev · mm-scroll-btn--next
               mm-services-grid · mm-service-card · mm-service-card-icon · mm-service-card-img · mm-service-link
Advantages:    mm-advantages-list · mm-advantage-item · mm-advantage-icon
               mm-advantage-num · mm-advantage-body · mm-advantage-link
Highlight box: mm-highlight · mm-highlight-icon · mm-highlight-body
FAQ:           mm-faq · mm-faq-item · mm-faq-question · mm-faq-answer
CTA:           mm-cta · mm-cta-text · mm-cta-actions · mm-btn · mm-btn-primary · mm-btn-secondary
HowTo:         mm-howto-steps · mm-howto-step · mm-howto-step-body
Quick links:   mm-quick-links · mm-quick-links-tags · mm-quick-link
               mm-ql-hidden · mm-ql-trigger · mm-ql-btn · mm-ql-chevron
Collapse:      mm-collapse-wrapper · mm-collapse-content · mm-collapse-fade
               mm-collapse-trigger · mm-collapse-btn · mm-collapse-chevron · mm-is-expanded
               mm-collapse-btn-text
Repair photo:  mm-repair-photo
```

**Критические правила:**

| Элемент | Обязательно | Запрещено |
|---|---|---|
| CTA заголовок | `<div class="mm-cta-text"><h3>` | standalone `<h2>` внутри `.mm-cta` |
| FAQ вопрос | `<p class="mm-faq-question">` | `<h4>` без этого класса |
| Заголовок карточки | `<h3>` внутри `.mm-service-card` | `<h4>` |
| Пункт преимущества | `<li class="mm-advantage-item">` + icon + body | `<li><strong>...</strong> текст</li>` |
| Intro-секция | `<section class="mm-section mm-intro" aria-labelledby="...">` | `<header class="mm-intro">` |
| Trust strip элемент | `<div class="mm-trust-item" role="listitem">` | `<span class="mm-trust-item">` |
| Collapse state | `data-mmState` | `data-gm-state` или `data-mm-state` (без кавычек) |
| Список преимуществ | `<ul class="mm-advantages-list" role="list">` | `<ul>` без `role="list"` |
| Бейдж рейтинга | `<a class="mm-stat-badge" href="https://yandex.ru/maps/org/maxmobiles_ru/237809440282/reviews/" target="_blank" rel="nofollow noopener">` с текстом ОДНОЙ строкой через `&#8212;` | `<div class="mm-stat-badge">` без ссылки; вложенные `<span class="mm-badge-item">`/`<span class="mm-badge-sep">` — на мобильном `.mm-stat-badge:has(.mm-badge-sep)` в `maxmobiles-styles.css` принудительно ставит бейдж в столбик |

---

### Step 7 — Output

Output **two** code blocks:

1. **`[slug].html`** — HTML-контент (Блок 1 с FAQ + Блок 2 + collapse script). Без JSON-LD.
2. **`[slug]-jsonld.html`** — только JSON-LD (LocalBusiness + WebPage + опционально HowTo + ItemList).

Сохранить оба файла в `SEO-страницы/`. В чат — только одна строка подтверждения: пути к файлам и краткая сводка (H2, description).

---

## SVG-иконки для блоков Maxmobiles

**Критическое ограничение CS-Cart:** TinyMCE декодирует HTML-сущности до сохранения — символы выше U+00FF заменяются на `???`. Использовать **только inline SVG**.

### Trust strip (24×24, fill="#0071e3")

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

**Trade-In — стрелки:**
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

### Иконки для `.mm-advantages-list` (20×20, currentColor)

**Галочка (гарантия, кредит):**
```html
<span class="mm-advantage-icon" aria-hidden="true">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="3,10 8,16 17,5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
</span>
```

**Молния (доставка):**
```html
<span class="mm-advantage-icon" aria-hidden="true">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><polygon points="11,2 5,11 10,11 9,18 15,9 10,9"/></svg>
</span>
```

**Trade-In — стрелки:**
```html
<span class="mm-advantage-icon" aria-hidden="true">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 6h12M14 6l-3-3m3 3l-3 3M18 14H6m0 0l3-3m-3 3l3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
</span>
```

**Звезда (рейтинг):**
```html
<span class="mm-advantage-icon" aria-hidden="true">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><polygon points="10,2 12.5,7.5 18.5,8.2 14,12.5 15.5,18.5 10,15.5 4.5,18.5 6,12.5 1.5,8.2 7.5,7.5"/></svg>
</span>
```

### Кнопки CTA (16×16, currentColor)

**Телефон:**
```html
<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 2h3l1.5 3.5-1.5 1a8 8 0 0 0 3.5 3.5l1-1.5L14 10v3a1 1 0 0 1-1 1C5.5 14 2 7.5 2 3a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
```

**Email:**
```html
<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M2 5l6 5 6-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
```

**Правила SVG:**
- `xmlns="http://www.w3.org/2000/svg"` обязателен
- `aria-hidden="true"` на `<span>` или на `<svg>` (не дублировать)
- Trust strip: `fill="#0071e3"`; advantages и кнопки: `stroke="currentColor"` / `fill="currentColor"`

---

## Brand constants

```
Name:         Maxmobiles  (never MaxMobiles or MAXMOBILES)
Free phone:   8 (800) 250-81-58  /  href="tel:+78002508158"
Store phone:  +7 (978) 222-01-23 /  href="tel:+79782220123"
Email:        store@maxmobiles.ru
Address:      пр-т Нахимова 19, ТЦ «Детский мир», Севастополь
Hours:        ежедневно 10:00–20:00
Site:         https://maxmobiles.ru
Delivery:     СДЭК по всей России
Pickup:       Севастополь · Симферополь · Ялта · Москва
Pickup count: 3 точки (без Москвы) в Крыму
Rating:       5.0 / 1000+ отзывов (Яндекс Карты, org ID 237809440282)
Yandex Maps:  https://yandex.ru/maps/org/237809440282
Since:        2011
Speciality:   старейший Apple-эксперт в Крыму; авторизованный сервисный центр Apple
```

---

## URL patterns

```
iPhone:       https://maxmobiles.ru/iphone/
iPad:         https://maxmobiles.ru/ipad/
Mac:          https://maxmobiles.ru/mac/macbook-pro/
Watch:        https://maxmobiles.ru/watch/
AirPods:      https://maxmobiles.ru/airpods/
Android:      https://maxmobiles.ru/android-smartfony/
Dyson:        https://maxmobiles.ru/krasota-i-zdorove-ru/dyson/
Apple БУ:     https://maxmobiles.ru/apple-idealnoe-bu/
Сервис:       https://maxmobiles.ru/servis-apple/
Игры:         https://maxmobiles.ru/vse-dlya-igr/
Гарантия:     https://maxmobiles.ru/garantiya/
Доставка:     https://maxmobiles.ru/oplata-i-dostavka/
Trade-In:     https://maxmobiles.ru/apple-trade-in/
Кредит:       https://maxmobiles.ru/kredit/
Бонусы:       https://maxmobiles.ru/bonusnaya-programma-ru/
```

---

## Highlights grid

**iPhone** → топ 3: новейший Pro Max, Pro, base; топ 6 + больше моделей
**Mac** → MacBook Pro, MacBook Air, Mac mini (3 карточки); + iMac, Mac Studio, MacBook Neo для 6
**iPad** → iPad Pro, iPad Air, iPad mini (3 карточки)
**Apple Watch** → Watch Ultra, Series 11, SE (3 карточки)
**AirPods** → AirPods Pro 3, AirPods 4, AirPods Max (3 карточки)
**Samsung/Android** → Z Fold, Z Flip, S-series (3 карточки)
**Dyson** → топ 3 по категории (пылесос, фен, стайлер)
**Apple БУ** → iPhone БУ, MacBook БУ, iPad БУ (3 карточки)
**Игры** → топ 3 по категории

Выбирать 3 карточки если 3–4 ключевых модели; 6 карточек если 5+.

---

## Trust strip

```html
<span class="mm-trust-label"><strong>Гарантия 1 год</strong> на новую технику</span>
```

| Слот | `<strong>` текст | Обычный текст |
|---|---|---|
| Щит (SVG) | Гарантия 1 год | на новую технику |
| Грузовик (SVG) | Доставка СДЭК | по всей России |
| Стрелки (SVG) | Trade-In | сдайте старое |
| Дом (SVG) | Maxmobiles | с 2011 года |

---

## Reference

Full HTML structure standard: `.cursor/rules/seo--for-shop-html-block.mdc`
CSS file: `maxmobiles-styles.css`
Meta tags (v2): `seo-meta-builder-v2` skill
Original skill (v1): `seo-shop-page-builder` skill
