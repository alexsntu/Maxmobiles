# Шаблон строки таблицы

## Строка БЕЗ «от» (from: false)

```html
<tr>
  <td>
    <a class="mmb2-svc-name" href="URL" target="_blank" rel="noopener">НАЗВАНИЕ</a>
    <span class="mmb2-svc-time">ВРЕМЯ</span>
  </td>
  <td>
    <div class="mmb2-price-wrap">
      <span class="mmb2-price" id="mmb2-pN">—&nbsp;₽</span>
    </div>
  </td>
  <td>
    <span class="mmb2-desc">ОПИСАНИЕ</span>
  </td>
  <td><button class="mmb2-btn" type="button" onclick="mmb2OpenModal('НАЗВАНИЕ','mmb2-pN')">Оставить заявку</button></td>
</tr>
```

## Строка С «от» (from: true)

```html
<tr>
  <td>
    <a class="mmb2-svc-name" href="URL" target="_blank" rel="noopener">НАЗВАНИЕ</a>
    <span class="mmb2-svc-time">ВРЕМЯ</span>
  </td>
  <td>
    <div class="mmb2-price-wrap">
      <span class="mmb2-price" id="mmb2-pN">—&nbsp;₽</span>
      <div class="mmb2-price-from-label">зависит от запчасти</div>
    </div>
  </td>
  <td>
    <span class="mmb2-desc">ОПИСАНИЕ</span>
  </td>
  <td><button class="mmb2-btn" type="button" onclick="mmb2OpenModal('НАЗВАНИЕ','mmb2-pN')">Оставить заявку</button></td>
</tr>
```

## Объект в JS массиве ROWS

```javascript
// from: false
{ id: 'mmb2-pN', url: '/путь-без-домена/', option: '',       from: false },

// from: true, с опцией
{ id: 'mmb2-pN', url: '/путь-без-домена/', option: 'Копия',  from: true  },
```

## Подсказки

- `N` — порядковый индекс с 0: `mmb2-p0`, `mmb2-p1`, `mmb2-p2` …
- Если две услуги ведут на **одну и ту же URL** — JS сделает один XHR-запрос (кэш), различие по `option`.
- Если `option` пустая — берётся первая цена на странице (`.ty-price-num`).
- Текст `зависит от запчасти` можно заменить на `зависит от повреждения` для услуг типа чистки после воды.
