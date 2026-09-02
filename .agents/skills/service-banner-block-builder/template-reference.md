# Таблица замен для баннер-блока

Эталон: `Новые категории ремонта\iphone-15-pro-max-block-1.html`

## Глобальные замены (replace_all)

| Что заменить | На что | Где |
|---|---|---|
| `mmb1` | новый префикс (напр. `mmi14`) | везде в файле |
| `Ремонт iPhone&nbsp;15&nbsp;Pro&nbsp;Max` | новый H1 с `&nbsp;` вместо пробелов | HTML |
| `Ремонт iPhone 15 Pro Max` | новый H1 обычный текст | JS, aria-label |

## Точечные замены

| Место в файле | Что | На что |
|---|---|---|
| `<h1 class="...">` | текст заголовка | **Заголовок** от пользователя |
| `<p class="mmb1-text">` | текст описания | **Текст** от пользователя |
| `<img src="...">` | URL картинки | **URL картинки** от пользователя |
| `<img alt="...">` | alt текст | `ЗАГОЛОВОК — Maxmobiles Севастополь` |
| `<select>` | список `<option>` | по именам услуг из массива SERVICES |
| `value="1079"` (page_id) | ID страницы | уточнить у пользователя |
| `form_values[742..746]` | ID полей формы | уточнить у пользователя |
| массив `SERVICES` в JS | все 4 услуги | данные от пользователя |
| `id="mmb1-modal-title"` текст | заголовок модалки | тот же H1 |

## Что НЕ менять

- Вся CSS секция (`.mmb1-cap-*`, `.mmb1-agree-*`, модальные стили)
- HTML капчи (`#mmb1-cap-field`, кнопки `.mmb1-cap-btn`)
- HTML чекбокса (`#mmb1-agree-wrap`)
- JS функции: `mmb1GenerateCaptcha`, маска телефона, `mmb1FetchUrl/ParsePrice/LoadPrices`
- CS-Cart атрибуты формы (`fake`, `dispatch[pages.send_form]`, `action`, `enctype`)
- Экран успеха (`#mmb1-success`)
- Trust-items в левой колонке (4 строки с иконками)
- Промо-иконки (green/blue/orange) — только тексты меняются через JS

## Пример заполнения массива SERVICES

```javascript
// Пользователь дал:
// - "Замена аккумулятора" / /batareja-zamena-iphone-14/ / 20 мин / 180 дней / (опция пустая)
// - "Замена дисплея (копия)" / /displey-zamena-iphone-14/ / 30 мин / 180 дней / Копия
// - "Замена дисплея (оригинал)" / /displey-zamena-iphone-14/ / 30 мин / 360 дней / Оригинал
// - "Замена слухового динамика" / /dinamik-vyzova-iphone-14/ / 30 мин / 360 дней / (опция пустая) / Цена: уточняйте

var SERVICES = [
  {
    name:     'Замена аккумулятора',
    price:    '— ₽',
    priceNum: '—',
    time:     '20 мин',
    warranty: 'Гарантия 180 дней',
    url:      '/batareja-zamena-iphone-14/',
    option:   ''
  },
  {
    name:     'Замена дисплея (копия)',
    price:    '— ₽',
    priceNum: '—',
    time:     '30 мин',
    warranty: 'Гарантия 180 дней',
    url:      '/displey-zamena-iphone-14/',
    option:   'Копия'
  },
  {
    name:     'Замена дисплея (оригинал)',
    price:    '— ₽',
    priceNum: '—',
    time:     '30 мин',
    warranty: 'Гарантия 360 дней',
    url:      '/displey-zamena-iphone-14/',
    option:   'Оригинал'
  },
  {
    name:     'Замена слухового динамика',
    price:    'Уточняйте',
    priceNum: 'Уточняйте',
    time:     '30 мин',
    warranty: 'Гарантия 360 дней',
    url:      '/dinamik-vyzova-iphone-14/',
    option:   '',
    askPrice: true
  }
];
```

Правила:

- Обычные услуги — `price: '— ₽'`, `priceNum: '—'`. JS перезапишет их при загрузке страницы.
- Услуги с пометкой пользователя «Цена: уточняйте» — `price: 'Уточняйте'`, `priceNum: 'Уточняйте'`, добавляется `askPrice: true`. JS-гидратация их **пропускает**.
- HTML-спан стартовой цены: для обычных — `<span id="ПРЕФИКС-price">—</span>`. Если первая услуга в SERVICES имеет `askPrice: true`, то в HTML сразу `<span id="ПРЕФИКС-price">Уточняйте</span>` и для рядом стоящего `<span class="ПРЕФИКС-price-currency">₽</span>` добавь `style="display: none;"`.

## Поведение `askPrice: true`

- В `mmb1ApplyPriceMap` первое правило: `if (s.askPrice) return;` — услуга не получает цену из API.
- В `updateUI` после `document.getElementById('ПРЕФИКС-price').textContent = s.priceNum;` добавлено переключение значка `₽`:

```javascript
var currency = document.querySelector('.mmb1-price-currency');
if (currency) currency.style.display = s.askPrice ? 'none' : '';
```

- В модалке `s.price` (= `'Уточняйте'`) уходит в скрытое поле формы — менеджер видит, что нужно согласовать цену по запросу клиента.
