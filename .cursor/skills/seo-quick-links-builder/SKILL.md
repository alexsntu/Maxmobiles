---
name: seo-quick-links-builder
description: Generates a SEO quick-links block (faceted navigation) for Maxmobiles category pages. Use when the user wants to add a quick links / fast navigation / tag cloud block to an iPhone, Mac, iPad, service or any other category page. Follows the quick-links-seo-block standard with ЧПУ URLs, proper anchor texts, and CSS from maxmobiles-styles.css.
---

# SEO Quick Links Builder — Maxmobiles

Generates a `<section class="mm-quick-links">` block for any Maxmobiles category page.
All links must use ЧПУ-URLs. No `rel="nofollow"`. Max 20 links, max 6 groups.

---

## Workflow (execute strictly in order)

### Step 1 — Request links from the user

**Always ask first — do NOT generate anything yet.**

Send this exact request to the user:

> Пришлите, пожалуйста, список всех ссылок, которые нужно добавить в блок быстрых ссылок.
>
> Формат — любой удобный, например:
> ```
> https://maxmobiles.ru/iphone/iphone-17-pro-max/ — iPhone 17 Pro Max
> https://maxmobiles.ru/iphone/pamyat-256gb/ — 256 ГБ
> https://maxmobiles.ru/airpods/ — AirPods
> ```
> Или просто список URL без подписей — названия определю самостоятельно.
>
> Также укажите: для какой страницы делаем блок (например, «категория iPhone» или «ремонт iPhone 17 Pro»)?

Wait for the user's response before proceeding.

---

### Step 2 — Parse & group the links

Parse each URL and its anchor text (label). If label is not provided — infer it from the URL slug.

**Automatic grouping rules:**

| URL pattern | Group |
|---|---|
| `.../iphone/iphone-*` | Модели |
| `.../iphone/pamyat-*` or `/memory-*` | Память |
| `.../iphone/tsvet-*` or `/color-*` | Цвет |
| `.../iphone/seriya-*` or model series | Серии |
| `.../apple-airpods/`, `.../apple-watch/`, `.../ipad/` | Смотрите также |
| `.../mac/`, `.../accessories/` | Смотрите также |
| `.../servis-apple/remont-iphone/remont-*` | Другие модели в сервисе |
| Other domain pages | Смотрите также |

**Duplicate check:**
- Compare each link with the cards already in `.mm-services-grid` on the target page (ask if unknown).
- Flag duplicates as ⚠️ — they must be removed from the block.

**Anchor text quality check** for each link:

| Check | Pass | Fail |
|---|---|---|
| Конкретность | «iPhone 17 Pro Max», «256 ГБ» | «устройство», «вариант» |
| Коммерческий сигнал | «MacBook Pro M4», «AirPods Pro 3» | «ноутбук», «наушники» |
| Запрещённые слова | — | «здесь», «нажмите», «смотреть», «подробнее» |

---

### Step 3 — SEO analysis & gap detection

After grouping, evaluate the link set for semantic completeness.

**For shop category pages — check these groups:**

| Group | Recommended if |
|---|---|
| Модели | Always — unless already in cards above |
| Смежные категории | Always — cross-sells (AirPods, Watch, чехлы) |
| Память | If CS-Cart has /pamyat-* pages |
| Цвет | If CS-Cart has /tsvet-* pages |
| Серии | If category has Pro / Air / base split |

**For service pages — check:**

| Group | Recommended if |
|---|---|
| Другие модели в сервисе | Always — cross-link to sibling repair pages |
| Типы ремонта | If repair type pages exist (ekran, akkumulyator...) |
| Смежные устройства | AirPods, Watch, iPad repair |

**Identify gaps** — links that are missing but would add semantic value.
Prepare a list of suggested additions (URL + anchor + reason).

---

### Step 4 — Present analysis to the user

Output a structured analysis report:

```
## Анализ ссылок для блока быстрых ссылок

### Принято к размещению: [N] ссылок в [N] группах

**Группа «Модели»** (N ссылок)
✅ iPhone 17 Pro Max → https://maxmobiles.ru/iphone/iphone-17-pro-max/
✅ iPhone 17 Pro → https://maxmobiles.ru/iphone/iphone-17-pro/

**Группа «Память»** (N ссылок)
✅ 256 ГБ → https://maxmobiles.ru/iphone/pamyat-256gb/

**Группа «Смотрите также»** (N ссылок)
✅ AirPods → https://maxmobiles.ru/airpods/

---

### ⚠️ Исключено (дубли из карточек выше): N ссылок
- [Модель] — уже есть в карточках моделей на странице

---

### 💡 Рекомендую добавить: N ссылок

1. **[Анкор]** → `[URL]`
   Причина: [почему это усилит семантику / какой запрос охватывает]

2. **[Анкор]** → `[URL]`
   Причина: ...

---

Подтвердите список или скажите «добавить предложенные» / «оставить как есть» — и я сразу сгенерирую блок.
```

**Wait for user confirmation before generating HTML.**

---

### Step 5 — Generate HTML block

After user confirms the final link list — generate the block.

Follow **all** rules in `.cursor/rules/quick-links-seo-block.mdc`.

**Structure:**

Все ссылки — плоский поток без групп и подписей:

```html
<section class="mm-quick-links mm-section" aria-labelledby="quick-links-heading">
  <h3 id="quick-links-heading" class="mm-section-title">[Заголовок]</h3>
  <nav aria-label="Быстрые ссылки по категориям" class="mm-quick-links-tags">
    <a href="[ЧПУ-URL]" class="mm-quick-link">[Анкор]</a>
    <a href="[ЧПУ-URL]" class="mm-quick-link">[Анкор]</a>
    <!-- все ссылки подряд, без div-обёрток и span-лейблов -->
  </nav>
</section>
```

**Заголовок H3 по типу страницы:**
- Категория магазина → «Быстрый выбор»
- Конкретная модель → «Смотрите также»
- Страница сервиса → «Другие устройства в сервисе»

**Порядок ссылок в потоке** (всегда соблюдать):
1. Модели
2. Серии
3. Память
4. Диагональ
5. Цвет
6. Смежные категории

---

### Step 6 — Self-check before outputting

- [ ] Нет `<style>` тегов — весь CSS в `maxmobiles-styles.css`
- [ ] Нет `rel="nofollow"` на ссылках
- [ ] Все ссылки — ЧПУ (нет GET-параметров)
- [ ] Нет `href="#"` — только реальные URL
- [ ] Нет дублей с карточками `.mm-services-grid`
- [ ] Нет эмодзи — только HTML-сущности если нужны символы
- [ ] Анкоры конкретные (без «здесь», «нажмите»)
- [ ] Максимум 20 ссылок, максимум 6 групп
- [ ] H3 (не H2, не H4)
- [ ] `aria-hidden="true"` на `.mm-quick-links-label`

---

### Step 7 — Output

Save the block as a **separate file** in the same folder as the category SEO block.
Naming: `[category]-quick-links.html` (e.g., `iphone-quick-links.html`, `mac-quick-links.html`).

**Never embed** the quick links block inside the SEO description file (`iphone-category.html` etc.).

Output the final HTML in a single code block — no commentary before or after.

---

## Reference

Full rules and CSS classes: `.cursor/rules/quick-links-seo-block.mdc`
CSS file: `maxmobiles-styles.css` — section 12 «Quick Links»
