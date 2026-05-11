# Article Standard — Maxmobiles Blog

Reference for HTML templates, JSON-LD, entity table, and brand constants.

---

## Brand Constants

```
Brand:       Maxmobiles
Phone:       8 (800) 250-81-58  /  href="tel:+78002508158"
Blog URL:    https://maxmobiles.ru/blog/
Site:        https://maxmobiles.ru
Logo:        https://maxmobiles.ru/images/logos/maxmobiles-logo.png
Since:       2011
Address:     Россия, Севастополь, пр-т Нахимова 19
Speciality:  старейший Apple-эксперт в Крыму
Email:       store@maxmobiles.ru
```

---

## Allowed Blog Categories

- `Лайфхаки и инструкции` -> `https://maxmobiles.ru/blog/layfhaki-i-instrukcii/`
- `Статьи` -> `https://maxmobiles.ru/blog/stati/`
- `Новости` -> `https://maxmobiles.ru/blog/novosti-produkcii-apple/`

---

## JSON-LD Template — BlogPosting

```json
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "BlogPosting",
      "@id": "https://maxmobiles.ru/blog/SLUG/#article",
      "headline": "ЗАГОЛОВОК СТАТЬИ",
      "description": "ОПИСАНИЕ 150-160 СИМВОЛОВ",
      "datePublished": "YYYY-MM-DD",
      "dateModified": "YYYY-MM-DD",
      "inLanguage": "ru",
      "url": "https://maxmobiles.ru/blog/SLUG/",
      "keywords": ["Apple", "Maxmobiles", "Севастополь", "Крым"],
      "author": {
        "@type": "Organization",
        "name": "Maxmobiles",
        "url": "https://maxmobiles.ru"
      },
      "publisher": {
        "@type": "Organization",
        "name": "Maxmobiles",
        "url": "https://maxmobiles.ru",
        "logo": {
          "@type": "ImageObject",
          "url": "https://maxmobiles.ru/images/logos/maxmobiles-logo.png"
        }
      },
      "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": "https://maxmobiles.ru/blog/SLUG/"
      },
      "articleSection": "КАТЕГОРИЯ",
      "speakable": {
        "@type": "SpeakableSpecification",
        "cssSelector": [".mm-intro-text", ".mm-article-key-facts", ".mm-faq"]
      }
    },
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Главная", "item": "https://maxmobiles.ru/" },
        { "@type": "ListItem", "position": 2, "name": "Блог Maxmobiles", "item": "https://maxmobiles.ru/blog/" },
        { "@type": "ListItem", "position": 3, "name": "КАТЕГОРИЯ", "item": "URL_КАТЕГОРИИ" },
        { "@type": "ListItem", "position": 4, "name": "ЗАГОЛОВОК", "item": "https://maxmobiles.ru/blog/SLUG/" }
      ]
    },
    {
      "@type": "FAQPage",
      "mainEntity": []
    }
  ]
}
</script>
```

---

## FAQ Format (AEO)

- Exactly 4 questions in `<section class="mm-faq">`.
- Each answer starts with a direct answer sentence.
- Each answer length: **40-80 words**.
- Questions and answers must match JSON-LD `FAQPage` exactly.

Canonical FAQ HTML (use by default):

```html
<section class="mm-faq" aria-labelledby="faq-heading">
  <h3 id="faq-heading">Частые вопросы</h3>
  <div class="mm-faq-item">
    <h4 class="mm-faq-question">Вопрос?</h4>
    <p class="mm-faq-answer">Ответ...</p>
  </div>
</section>
```

Recommended intent coverage:
1. Main topic question
2. Release/date question
3. Compatibility/limitations question
4. Purchase intent question (Maxmobiles, delivery, guarantee, Trade-In)

---

## Catalog Links Block (internal linking)

When user provides internal links, append this block at the end of `.mm-article-body`:

```html
<section class="mm-section mm-catalog-links" aria-labelledby="catalog-links-heading">
  <h3 id="catalog-links-heading">Что выбрать в каталоге Maxmobiles</h3>
  <ul>
    <li><a href="https://maxmobiles.ru/iphone/">iPhone</a></li>
  </ul>
</section>
```

`mm-catalog-links` class is mandatory for consistent styling with published articles.

---

## Author Block (final section)

Keep author block as the last node in `.mm-block mm-article`:

```html
<div class="mm-article-author" role="contentinfo" aria-label="Об авторе">
  <div class="mm-article-author-icon" aria-hidden="true">&#x270D;</div>
  <div class="mm-article-author-body">
    <p><strong>Команда Maxmobiles</strong> ...</p>
  </div>
</div>
```

---

## HTML Entity Table

| Symbol | Entity |
|---|---|
| ✔ | `&#x2714;` |
| ☎ | `&#x260E;` |
| ✉ | `&#x2709;` |
| 💡 | `&#x1F4A1;` |
| 👤 | `&#x1F464;` |
| — | `&#8212;` |
| ₽ | `&#8381;` |

