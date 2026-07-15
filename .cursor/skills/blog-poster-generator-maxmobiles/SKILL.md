---
name: blog-poster-generator-maxmobiles
description: Generates Apple-style blog post header images (871×580 px) for Maxmobiles.ru blog. Use when the user provides a blog article title and URL (and optional reference photo) to create a premium featured image following Apple design aesthetics—minimalist, clean typography, subtle gradients, and Maxmobiles branding elements.
---

# Blog Poster Generator for Maxmobiles

Generates high-quality header posters (871×580 px) for blog articles on maxmobiles.ru in Apple design style. No logo on poster.

## Quick Start

When the user provides:
- **Article title** (e.g., "Как выбрать iPhone 16 Pro")
- **Article URL** (e.g., `https://maxmobiles.ru/blog/kak-vybrat-iphone-16-pro`)
- **Optional reference photo** (screenshot from article, product photo, etc.)

Follow this workflow:

1. **Analyze the content**: Extract key themes and emotional tone from the article title
2. **Generate the poster**: Use `GenerateImage` with Apple-style visual language
3. **Save to blog folder**: Store in `Блог/[slug]-poster.png` using the article slug

## Design Standards

### Visual Style (Apple-inspired)
- **Color palette**: Neutral backgrounds (white, light gray, or subtle gradients), accents in product colors (space black, silver, gold, blue)
- **Typography**: Clean, modern sans-serif font with generous whitespace
- **Composition**: Minimalist layout, rule of thirds, asymmetric balance
- **Elements**: Subtle depth, soft shadows, rounded corners where appropriate
- **Imagery**: High-contrast, professional product photography or icons
- **NO branding**: No logo, watermark, or company name on the poster itself

### Dimensions
- **Size**: 871×580 px (16:10 ratio, optimized for blog featured images)
- **Safe zone**: Main content centered, 40px padding on all sides

## Generation Instructions for Agent

### Prompt Structure for GenerateImage

```
Title: [Article Title]
URL: [Article URL]
Slug: [Extracted slug from URL]

Design a premium blog header poster (871×580 px) in Apple design style for an article about "[Article Topic]". 

Key visual elements to include:
- [Theme-specific element 1]
- [Theme-specific element 2]
- Clean, minimalist composition with substantial whitespace
- Modern sans-serif typography
- Subtle depth through shadows or layering

Color guidance: [Color palette suggestion based on article topic]

The poster should feel premium, accessible, and professional—suitable for a Sevastopol Apple repair/retail expert (Maxmobiles). Do NOT include logo, watermark, or company name.

[If user provided reference photo: Reference aesthetic/style from attached image]
```

### Theme-Based Design Guidance

| Article Type | Color Accent | Visual Theme | Example Elements |
|---|---|---|---|
| iPhone reviews | Space black + gold | Device showcase | Clean iPhone silhouette, tech elements |
| Mac tips | Silver + blue | Workspace aesthetic | Keyboard, desk setup, productivity |
| iPad tutorials | Gold + white | Creative canvas | Apple Pencil, creative work samples |
| Apple Watch | Rose gold + teal | Activity/wellness | Watch face, activity rings |
| Repair guides | Space black + service blue | Technical clarity | Tool icons, repair process visual |
| Tips & tricks | Bright accent + white | Minimalist spotlight | Single feature highlight, clean lines |

## Output Handling

After generating the poster:

1. **Filename construction**: Use URL slug extracted from the article URL
   - Example: `https://maxmobiles.ru/blog/kak-vybrat-iphone-16-pro` → `kak-vybrat-iphone-16-pro-poster.png`

2. **Save location**: `Блог/[filename]`
   - Full path: `c:\Users\Алекс\Service MM\Блог\[filename]`

3. **Confirmation**: Inform user of saved path and dimensions

## Quality Checklist

Before delivering the poster:
- ✅ Dimensions exactly 871×580 px
- ✅ Apple-inspired design language applied
- ✅ Clear, readable typography
- ✅ No logo or watermark present
- ✅ Suitable as blog featured image
- ✅ File saved in correct directory with slug-based name

## Multi-Poster Workflow

If user requests multiple posters in sequence:

1. Generate first poster and confirm
2. User provides next title/URL
3. Generate and save
4. Repeat

For batch requests (5+ posters), confirm each generation before proceeding to next to avoid context drift.
