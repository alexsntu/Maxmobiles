/**
 * Maxmobiles: выпадающие подсказки своего поиска в шапке (с 2026-10-16 – у всех покупателей Maxmobiles вместо Diginetica;
 * хук index:scripts модуля my_changes; Diginetica на Maxmobiles отключена совсем, см. common/scripts.tpl).
 * Данные – /mm-search.php?q=… (варианты запроса, «в категории», товары).
 *
 * Как устроено (по исследованиям Baymard и подсказкам Amazon/Apple/Ozon): слева варианты запроса с выделенной ДОПИСАННОЙ
 * частью, затем «в категории» с числом товаров; справа до 4 товаров с фото и ценой и «Все N товаров». Стрелки ↑↓ ходят
 * по пунктам по кругу, выбранный вариант запроса сразу попадает в поле; Enter открывает выбранное, Esc закрывает.
 * Фон страницы затемняется. Пустое поле – «Вы искали» и «Часто ищут». Оформление – секция MM-13 «Своего CSS» Maxmobiles.
 */
(function (_, $) {
  var URL = '/mm-search.php';
  var DELAY = 120;
  var panel = null, dim = null, input = null, form = null, typed = '', items = [], active = -1, timer = null, ctrl = null, last = null;

  // Статистика и обучение поиска: случайная метка браузера (кука mm_sid, без личных данных) и события на /mm-search.php?ev=1
  function sid() {
    var m = document.cookie.match(/(?:^|; )mm_sid=([a-f0-9]{16})/);
    if (m) { return m[1]; }
    var a = new Uint8Array(8), v = '';
    (window.crypto || window.msCrypto).getRandomValues(a);
    for (var k = 0; k < a.length; k++) { v += ('0' + a[k].toString(16)).slice(-2); }
    document.cookie = 'mm_sid=' + v + '; path=/; max-age=31536000; samesite=lax; secure';
    return v;
  }
  function track(ev) {
    try {
      sid();
      ev.device = window.innerWidth < 768 ? 'm' : 'd';
      var body = JSON.stringify(ev);
      if (navigator.sendBeacon) { navigator.sendBeacon(URL + '?ev=1', new Blob([body], {type: 'application/json'})); }
      else { fetch(URL + '?ev=1', {method: 'POST', body: body, keepalive: true, credentials: 'same-origin'}); }
    } catch (e) {}
  }
  var chosen = false, submitted = false, shownCount = 0, exitSent = '';

  // История поиска «Вы искали»: в браузере покупателя (localStorage), до 8 запросов, новые сверху, без повторов.
  // Запрос записывается при открытии страницы результатов (так учитываются Enter, клик по варианту и кнопка поиска).
  var HIST_KEY = 'mm_search_history', HIST_MAX = 8;
  function histGet() {
    try { var h = JSON.parse(window.localStorage.getItem(HIST_KEY) || '[]'); return $.isArray(h) ? h : []; } catch (e) { return []; }
  }
  function histSet(h) {
    try { if (h.length) { window.localStorage.setItem(HIST_KEY, JSON.stringify(h.slice(0, HIST_MAX))); } else { window.localStorage.removeItem(HIST_KEY); } } catch (e) {}
  }
  function histAdd(q) {
    q = $.trim(String(q || '')).replace(/\s+/g, ' ');
    if (!q || q.length > 100) { return; }
    var low = q.toLowerCase();
    histSet([q].concat($.grep(histGet(), function (x) { return String(x).toLowerCase() !== low; })));
  }
  function histDel(q) {
    histSet($.grep(histGet(), function (x) { return x !== q; }));
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; });
  }

  function ensure() {
    if (panel) { return; }
    dim = $('<div class="mm-sg-dim" hidden></div>').appendTo(document.body);
    panel = $('<div class="mm-sg" role="listbox" hidden></div>').appendTo(document.body);
    dim.on('mousedown', close);
    panel.on('mousedown', function (e) { e.preventDefault(); }); // не терять фокус поля при клике по подсказке
    panel.on('click', '.mm-sg__del', function (e) {
      e.preventDefault(); e.stopPropagation();
      histDel($(this).attr('data-del')); last = null; load('');
    });
    panel.on('click', '.mm-sg__clear', function (e) {
      e.preventDefault(); e.stopPropagation();
      histSet([]); last = null; load('');
    });
    panel.on('click', '.mm-sg__chip', function (e) {
      e.preventDefault();
      var v = $(this).attr('data-chip');
      track({source: 'sg_chip', q: v, typed: typed});
      input.val(v + ' '); typed = input.val(); last = null; load($.trim(v)); input[0].focus();
    });
    panel.on('click', '[data-i]', function (e) { e.preventDefault(); choose(+$(this).attr('data-i')); });
    panel.on('mousemove', '[data-i]', function () { mark(+$(this).attr('data-i'), false); });
    $(window).on('resize scroll', function () { if (!panel.prop('hidden')) { place(); } });
  }

  function place() {
    var r = input[0].getBoundingClientRect();
    var mobile = window.innerWidth < 768;
    var width = mobile ? window.innerWidth - 16 : Math.min(760, Math.max(r.width, 640));
    var left = mobile ? 8 : Math.max(8, Math.min(r.left, window.innerWidth - width - 8));
    // ПК и телефон: затемнение и панель – под белой строкой шапки с полем, а не вплотную к полю (иначе серая полоса наезжает
    // на шапку, а панель прилипает к полю), и с отступом 8 px
    var bottom = r.bottom;
    var row = input.closest('.container-fluid-row')[0];
    var rb = row ? row.getBoundingClientRect().bottom : 0;
    if (rb > bottom && rb - bottom < 60) { bottom = rb; }
    panel.css({top: Math.round(bottom + 8) + 'px', left: Math.round(left) + 'px', width: Math.round(width) + 'px'});
    dim.css({top: Math.round(bottom) + 'px'});
  }

  function render(d) {
    items = [];
    active = -1;
    var q = '', s = '', p = '';
    // Пустое поле: при истории «Часто ищут» короче (5), чтобы список не был длинным
    var queries = d.popular && histGet().length ? (d.queries || []).slice(0, 5) : (d.queries || []);
    $.each(queries, function (k, x) {
      var i = items.push({type: 'q', text: x.text, src: 'sg_query'}) - 1;
      q += '<a class="mm-sg__q" href="#" data-i="' + i + '" role="option"><span class="mm-sg__t">' + esc(x.typed) + '</span><b>' + esc(x.rest) + '</b></a>';
    });
    $.each(d.scopes || [], function (k, x) {
      var i = items.push({type: 'u', url: x.url, src: 'sg_scope', text: x.text + ' — ' + x.category}) - 1;
      s += '<a class="mm-sg__q mm-sg__q--scope" href="' + esc(x.url) + '" data-i="' + i + '" role="option"><span class="mm-sg__t">' + esc(x.text)
        + '</span> <i>— в ' + esc(x.category) + '</i><span class="mm-sg__n">' + x.count + '</span></a>';
    });
    $.each(d.products || [], function (k, x) {
      var i = items.push({type: 'u', url: x.url, src: 'sg_product', text: x.name, id: x.id}) - 1;
      p += '<a class="mm-sg__p" href="' + esc(x.url) + '" data-i="' + i + '" role="option">'
        + '<span class="mm-sg__img">' + (x.img ? '<img src="' + esc(x.img) + '" alt="" loading="lazy">' : '') + '</span>'
        + '<span class="mm-sg__pt"><span class="mm-sg__name">' + esc(x.name) + '</span>'
        + '<span class="mm-sg__price">' + esc(x.price) + '</span></span></a>'; // наличие в подсказках не показываем (решение пользователя)
    });
    if (d.total > (d.products || []).length) {
      var i2 = items.push({type: 'all', text: d.target, src: 'sg_all'}) - 1;
      p += '<a class="mm-sg__all" href="#" data-i="' + i2 + '">Все ' + d.total + ' ' + plural(d.total) + ' →</a>';
    }
    var h = '';
    if (d.popular) {
      // В виджете – не больше 4 последних запросов (пользователь: иначе занимает слишком много места)
      var hist = histGet().slice(0, 4);
      $.each(hist, function (k, x) {
        var i = items.push({type: 'q', text: x, src: 'sg_history'}) - 1;
        h += '<a class="mm-sg__q mm-sg__q--hist" href="#" data-i="' + i + '" role="option"><span class="mm-sg__t"></span><b>' + esc(x)
          + '</b><span class="mm-sg__del" data-del="' + esc(x) + '" title="Удалить из истории" aria-label="Удалить из истории">×</span></a>';
      });
      if (h) {
        h = '<div class="mm-sg__h mm-sg__h--hist">Вы искали<span class="mm-sg__clear" role="button">Очистить</span></div>' + h;
      }
    }
    // Плашки-уточнения: следующее слово к запросу (нажатие дописывает слово и обновляет подсказки, поиск не запускает)
    var c = '';
    if (!d.popular && d.chips && d.chips.length) {
      c = '<div class="mm-sg__chips">' + $.map(d.chips, function (w) {
        return '<button type="button" class="mm-sg__chip" data-chip="' + esc((d.chips_base ? d.chips_base + ' ' : '') + w) + '">' + esc(w) + '</button>';
      }).join('') + '</div>';
    }
    shownCount = items.length;
    if (!h && !q && !s && !p) { hide(); return; }
    panel.html(
      c + h
      + (d.popular && q ? '<div class="mm-sg__h">Часто ищут</div>' : '')
      + (d.layout ? '<div class="mm-sg__h">Показаны результаты для «' + esc(d.layout) + '»</div>' : '')
      + '<div class="mm-sg__cols' + (p ? '' : ' mm-sg__cols--one') + '"><div class="mm-sg__left">' + q + s + '</div>'
      + (p ? '<div class="mm-sg__right">' + (d.popular ? '<div class="mm-sg__h">Популярные товары</div>' : '') + p + '</div>' : '') + '</div>'
    );
    show();
  }

  function plural(n) {
    var a = n % 10, b = n % 100;
    return a === 1 && b !== 11 ? 'товар' : (a >= 2 && a <= 4 && (b < 12 || b > 14) ? 'товара' : 'товаров');
  }

  function show() { place(); panel.prop('hidden', false); dim.prop('hidden', false); $('body').addClass('mm-sg-open'); }
  function hide() { if (panel) { panel.prop('hidden', true); dim.prop('hidden', true); } $('body').removeClass('mm-sg-open'); active = -1; }
  function close() { hide(); }

  function mark(i, copy) {
    panel.find('[data-i]').removeClass('is-active');
    active = i;
    if (i < 0) { if (copy) { input.val(typed); } return; }
    panel.find('[data-i="' + i + '"]').addClass('is-active');
    if (copy && items[i].type === 'q') { input.val(items[i].text); } // выбранный вариант – сразу в поле (Baymard)
  }

  function choose(i) {
    var it = items[i];
    if (!it) { return; }
    chosen = true;
    track({source: it.src || 'sg_query', q: it.text || '', typed: typed, product_id: it.id || 0, pos: i + 1});
    if (it.type === 'u') { window.location.href = it.url; return; }
    if (it.type === 'q' || it.type === 'all') { input.val(it.text || input.val()); }
    hide();
    input.attr('name', 'q'); // имя поля, которое ждёт поиск (cm-hint мог переименовать его в hint_q)
    if (form[0]) { HTMLFormElement.prototype.submit.call(form[0]); }
  }

  function load(v) {
    if (v === last) { return; }
    last = v;
    if (ctrl && ctrl.abort) { ctrl.abort(); }
    ctrl = window.AbortController ? new AbortController() : null;
    fetch(URL + '?q=' + encodeURIComponent(v), ctrl ? {signal: ctrl.signal, credentials: 'omit'} : {credentials: 'omit'})
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && input && $.trim(input.val()) === $.trim(v) || d && d.popular) { render(d); } })
      .catch(function () {});
  }

  function bind($inp) {
    if ($inp.data('mmSg')) { return; }
    $inp.data('mmSg', 1).attr({autocomplete: 'off', spellcheck: 'false', 'aria-autocomplete': 'list'});
    $inp.on('focus mmsg:open', function () {
      ensure(); input = $inp; form = $inp.closest('form');
      typed = $inp.val(); last = null; load($.trim(typed));
    });
    $inp.on('input', function () {
      ensure(); input = $inp; form = $inp.closest('form');
      typed = $inp.val();
      clearTimeout(timer);
      timer = setTimeout(function () { load($.trim(typed)); }, DELAY);
    });
    $inp.on('keydown', function (e) {
      if (!panel || panel.prop('hidden')) { return; }
      var n = items.length;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        var next = e.key === 'ArrowDown' ? (active + 1 > n - 1 ? -1 : active + 1) : (active - 1 < -1 ? n - 1 : active - 1);
        mark(next, true);
      } else if (e.key === 'Enter' && active >= 0) {
        e.preventDefault();
        choose(active);
      } else if (e.key === 'Escape') {
        input.val(typed);
        hide();
      }
    });
    $inp.on('blur', function () {
      setTimeout(hide, 150);
      // Брошенный ввод: набрали от 3 символов, ничего не выбрали и не отправили поиск – sg_exit (подсказки были) / sg_none (пусто)
      var v = $.trim($inp.val());
      if (!chosen && !submitted && v.length >= 3 && v !== exitSent) {
        exitSent = v;
        track({source: shownCount ? 'sg_exit' : 'sg_none', q: v, typed: v, pos: shownCount});
      }
    });
    $inp.on('input', function () { chosen = false; });
    // Enter без выбора в подсказках: CS-Cart (cm-hint) мог оставить полю имя hint_q – тогда поиск ушёл бы без запроса
    $inp.closest('form').on('submit', function () {
      submitted = true;
      if ($.trim($inp.val()) !== '' && $inp.attr('name') !== 'q') { $inp.attr('name', 'q'); }
    });
  }

  function init(ctx) {
    // CS-Cart (cm-hint) переименовывает поле в hint_q, пока в нём подсказка-заглушка, – ищем любое текстовое поле формы
    $(ctx || document).find('form[name="search_form"] input[type="text"], form[name="search_form"] input[type="search"]').each(function () { bind($(this)); });
  }

  // Телефон: значок поиска в шапке открывает поле – сразу ставим в него курсор (и открываем «Часто ищут»), чтобы не
  // нажимать по полю второй раз. Фокус – прямо в обработчике нажатия: иначе iPhone не покажет клавиатуру; повтор через
  // 150 мс – если тема показывает поле с задержкой.
  function focusSearch() {
    var $inp = $('form[name="search_form"] input[type="text"]').filter(function () { return this.offsetParent !== null; }).first();
    if (!$inp.length) { return false; }
    if (document.activeElement !== $inp[0]) { $inp[0].focus(); }
    // Событие focus приходит не всегда (окно без фокуса) – открываем подсказки напрямую
    if (!panel || panel.prop('hidden')) { $inp.trigger('mmsg:open'); }
    return true;
  }
  $(document).on('click', '.ut2-btn-search', function () {
    focusSearch();
    setTimeout(focusSearch, 150);
  });

  // Страница результатов: плитки «Найдено в категориях» над товарами (как у Diginetica). Нажатие – те же результаты
  // только этой категории (&mm_cat=…, отбор делает controllers/frontend/products.pre.php модуля), «Все» – снять выбор.
  function categoryTiles(q) {
    // Сам список товаров – products_search_<номер блока> (не счётчик products_search_total_found_… в заголовке)
    var $list = $('[id^="products_search_"]').filter(function () { return /^products_search_\d+$/.test(this.id); }).first();
    if (!$list.length || $('.mm-cats').length) { return; }
    var url = new window.URL(window.location.href);
    var current = parseInt(url.searchParams.get('mm_cat') || '0', 10);
    fetch(URL + '?cats=1&q=' + encodeURIComponent(q), {credentials: 'omit'})
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.categories || d.categories.length < 2 && !current) { return; } // одна категория – выбирать нечего
        var link = function (cid) {
          var u = new window.URL(window.location.href);
          u.searchParams.delete('page');
          if (cid) { u.searchParams.set('mm_cat', cid); } else { u.searchParams.delete('mm_cat'); }
          return u.pathname + u.search;
        };
        var html = '';
        var tile = function (c, on) {
          return '<a class="mm-cats__i' + (on ? ' is-on' : '') + '" href="' + esc(link(on ? 0 : c.id)) + '"' + (on ? ' aria-current="true" title="Снять выбор"' : '') + '>'
            + '<span class="mm-cats__img">' + (c.img ? '<img src="' + esc(c.img) + '" alt="" loading="lazy">' : '') + '</span>'
            + '<span class="mm-cats__t"><b>' + esc(c.name) + '</b><span>' + c.count + ' ' + plural(c.count) + '</span></span>'
            + (on ? '<span class="mm-cats__x" aria-hidden="true">×</span>' : '') + '</a>';
        };
        // Выбранная категория – первой и выделенной (повторное нажатие снимает выбор), за ней «Все категории», потом остальные
        var chosen = $.grep(d.categories, function (c) { return c.id === current; })[0];
        if (chosen) { html += tile(chosen, true); }
        if (current) {
          html += '<a class="mm-cats__i mm-cats__i--all" href="' + esc(link(0)) + '"><span class="mm-cats__t"><b>Все категории</b><span>'
            + d.total + ' ' + plural(d.total) + '</span></span></a>';
        }
        $.each(d.categories, function (k, c) { if (c.id !== current) { html += tile(c, false); } });
        $list.before('<div class="mm-cats"><div class="mm-cats__h">Найдено в категориях</div><div class="mm-cats__wrap">'
          + '<button type="button" class="mm-cats__arr mm-cats__arr--prev" aria-label="Назад" hidden>‹</button>'
          + '<div class="mm-cats__row">' + html + '</div>'
          + '<button type="button" class="mm-cats__arr mm-cats__arr--next" aria-label="Дальше" hidden>›</button></div></div>');
        // ПК: стрелки листают ряд (колесом мыши вбок не прокрутить); видна стрелка, только если в ту сторону есть ещё плитки
        var $row = $('.mm-cats__row'), $prev = $('.mm-cats__arr--prev'), $next = $('.mm-cats__arr--next');
        var arrows = function () {
          var el = $row[0];
          $prev.prop('hidden', el.scrollLeft <= 2);
          $next.prop('hidden', el.scrollLeft + el.clientWidth >= el.scrollWidth - 2);
        };
        $prev.on('click', function () { $row[0].scrollBy({left: -$row[0].clientWidth * 0.8, behavior: 'smooth'}); });
        $next.on('click', function () { $row[0].scrollBy({left: $row[0].clientWidth * 0.8, behavior: 'smooth'}); });
        $row.on('scroll', arrows);
        $(window).on('resize', arrows);
        arrows();

      })
      .catch(function () {});
  }

  // Страница результатов: ряд «Уточнить» – плашки со следующим словом к запросу (как у Diginetica), над плитками категорий
  function refineChips(q) {
    var $list = $('[id^="products_search_"]').filter(function () { return /^products_search_\d+$/.test(this.id); }).first();
    if (!$list.length || $('.mm-refine').length) { return; }
    fetch(URL + '?chips=1&q=' + encodeURIComponent(q), {credentials: 'omit'})
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.chips || !d.chips.length) { return; }
        var html = $.map(d.chips, function (w) {
          var u = new window.URL(window.location.href);
          u.searchParams.set('q', (d.base ? d.base + ' ' : '') + w);
          u.searchParams.delete('page'); u.searchParams.delete('mm_cat');
          return '<a class="mm-refine__chip" href="' + esc(u.pathname + u.search) + '">' + esc(w) + '</a>';
        }).join('');
        var $block = $('<div class="mm-refine"><span class="mm-refine__h">Уточнить:</span>' + html + '</div>');
        var $cats = $('.mm-cats');
        if ($cats.length) { $cats.before($block); } else { $list.before($block); }
      })
      .catch(function () {});
    $(document).on('click', '.mm-refine__chip', function () { track({source: 'chip', q: $(this).text(), typed: q}); });
  }

  // Страница результатов: клик по товару (один раз на товар) – в статистику с запросом страницы; место считает сервер
  function resultClicks(q) {
    var seen = {};
    $(document).on('click', '[id^="products_search_"] a, [id^="products_search_"] button', function () {
      var $f = $(this).closest('form[name^="product_form_"]');
      if (!$f.length) { $f = $(this).closest('[class*="__item"]').find('form[name^="product_form_"]').first(); }
      var pid = $f.length ? parseInt(String($f.attr('name')).replace('product_form_', ''), 10) : 0;
      if (!pid || seen[pid]) { return; }
      seen[pid] = 1;
      var sp = new URLSearchParams(window.location.search);
      track({source: 'results', q: q, product_id: pid, per_page: parseInt(sp.get('items_per_page') || '16', 10) || 16});
    });
    $(document).on('click', '.mm-cats__i', function () {
      var m = String($(this).attr('href')).match(/mm_cat=(\d+)/);
      track({source: 'tile', q: q, product_id: m ? parseInt(m[1], 10) : 0});
    });
  }

  $(function () {
    init(document);
    // Страница результатов поиска – запрос в историю
    try {
      var sp = new URLSearchParams(window.location.search);
      if (sp.get('dispatch') === 'products.search' && sp.get('q')) { sid(); histAdd(sp.get('q')); refineChips(sp.get('q')); categoryTiles(sp.get('q')); resultClicks(sp.get('q')); }
    } catch (e) {}
  });
  $.ceEvent('on', 'ce.commoninit', function (ctx) { init(ctx); });
})(Tygh, Tygh.$);
