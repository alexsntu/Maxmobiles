---
name: seo-blog-article-builder-maxmobiles
description: Перерабатывает сырую HTML-статью блога в SEO/GEO/AEO/E-E-A-T оптимизированный HTML-блок для блога Maxmobiles (maxmobiles.ru). Использовать для категорий: Лайфхаки и инструкции, Статьи, Новости. Применяет классы из maxmobiles-styles.css, JSON-LD BlogPosting/BreadcrumbList/FAQPage, Key Facts, TOC, FAQ, CTA и Author box.
---

# SEO Blog Article Builder — Maxmobiles

Transforms a raw blog article into a fully optimized HTML block for Maxmobiles.

## Mandatory preload (before any generation)

Always read and strictly follow these sources first:
- `.cursor/rules/seo--for-blog-article-maxmobiles.mdc`
- `ARTICLE-STANDARD.md` from this skill folder
- `maxmobiles-styles.css`

If any instruction conflicts, prioritize:
1. `seo--for-blog-article-maxmobiles.mdc`
2. `ARTICLE-STANDARD.md`
3. This `SKILL.md`

## Workflow

### Step 1 — Parse the article

Extract:
- Headline
- H2 sections
- Date
- Author (default: «Команда Maxmobiles»)
- Images
- Body text
- Category hints

Fix during transformation:
- Add missing H2 `id`
- Remove `<style>` tags
- Replace raw emoji with HTML entities
- Ensure images are wrapped with `<figure>`
- Ensure all images have unique `alt` and `title`
- Ensure all images have inline style:
  `style="max-width: 100%; height: auto; border-radius: 24px; display: block;"`
- First image rule: `fetchpriority="high"` and no `loading="lazy"`
- Other images rule: `loading="lazy"`

### Step 1b — Ask required metadata and wait

Always ask user for:
1. URL slug
2. Publication date (`YYYY-MM-DD`)
3. Category from allowed list (`Лайфхаки и инструкции`, `Статьи`, `Новости`)
4. Optional internal links list (`Анкор -> URL`) or `нет`

### Step 2 — Build structure

Output order:
1. JSON-LD `<script type="application/ld+json">`
2. `<div class="mm-block mm-article" itemscope itemtype="https://schema.org/Article">`
3. Header tags (`Блог Maxmobiles` + category)
4. Intro `.mm-intro-text`
5. Key Facts `.mm-article-key-facts` (3-5 items)
6. TOC `.mm-article-toc` (only if 4+ H2)
7. Body `.mm-article-body`
8. Optional section `Что выбрать в каталоге Maxmobiles`
9. FAQ `.mm-faq` (4 Q&A)
10. CTA `.mm-cta`
11. Author box `.mm-article-author` (always last)

Canonical markup defaults (must match current production-ready articles):
- Catalog links section class: `mm-section mm-catalog-links`
- FAQ heading: `h3` with `id="faq-heading"`
- FAQ question tag: `h4.mm-faq-question`
- Author wrapper: `<div class="mm-article-author" role="contentinfo" ...>`

H1 is never printed in HTML (managed by CS-Cart separately).

### Step 3 — Category mapping

- `Лайфхаки и инструкции` -> `https://maxmobiles.ru/blog/layfhaki-i-instrukcii/`
- `Статьи` -> `https://maxmobiles.ru/blog/stati/`
- `Новости` -> `https://maxmobiles.ru/blog/novosti-produkcii-apple/`

If category is outside this list, ask user to choose from allowed values.

### Step 4 — GEO/AEO/E-E-A-T

- Use self-sufficient paragraphs
- First sentence of each FAQ answer must be direct
- FAQ format is strict: 4 questions, each answer 40-80 words
- Keep FAQ wording identical in HTML and JSON-LD `FAQPage`
- Avoid short one-line answers; expand with concrete context
- Weave commercial trust signals naturally: гарантия 1 год, Trade-In, рассрочка, сервисный центр
- Mention geo naturally: Севастополь, Крым, доставка по Крыму и России
- When mentioning company experience, always use: `с 2011 года`
- Include at least one E-E-A-T rich block in article body or conclusion

### Step 5 — Styling constraints

- Use only `mm-*` classes from `maxmobiles-styles.css`
- No inline `<style>`
- No raw emoji
- Never invent custom classes that are absent in `maxmobiles-styles.css`
- Before final output, run a class whitelist self-check against `maxmobiles-styles.css`
- Ensure optional catalog section uses `mm-catalog-links` for consistent link styling

### Step 6 — Save result to file

After generating final HTML, save as:
- Folder: `Блог`
- Filename: `<slug>.html`
- Content: JSON-LD script + full HTML block

If file exists, overwrite unless user asks to keep both versions.

### Step 7 — Chat output

Do not print full HTML in chat.
Return only short confirmation with exact saved file path.

## Reference

Use `ARTICLE-STANDARD.md` from this skill folder.

