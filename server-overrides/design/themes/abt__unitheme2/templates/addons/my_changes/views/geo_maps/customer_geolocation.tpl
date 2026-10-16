{**
  Maxmobiles – выбор города вместо карты в диалоге «Местоположение покупателя» (аддон geo_maps).
  Копия ветки GOODMi из addons/geo_maps/views/geo_maps/customer_geolocation.tpl (тот же список городов,
  подтверждён пользователем 2026-10-14). Окно выводится одним файлом темы на все витрины, поэтому в файле GOODMi
  стоит развилка elseif по company_id == 1, подключающая этот файл; сам файл – только для Maxmobiles.
  Стили – «Свой CSS» Maxmobiles, секция MM-4 (классы gm-geo-picker*, синие цвета Maxmobiles).
  Как работают поиск и сохранение города – см. описание в начале файла GOODMi.
**}
<div class="gm-geo-picker">

  <div class="gm-geo-picker__search">
    <svg class="gm-geo-picker__search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.6"/><path d="M12.5 12.5L16 16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    <input type="text" class="gm-geo-picker__search-input" data-ca-gm-geo-search placeholder="Найдите свой город" autocomplete="off" aria-label="Поиск города">
  </div>

  <div class="gm-geo-picker__curated" data-ca-gm-geo-curated>
    <div class="gm-geo-picker__priority">
      <div class="gm-geo-picker__priority-label">Популярные города</div>
      <div class="gm-geo-picker__priority-list">
        <a href="#" class="gm-geo-picker__chip" data-city="Севастополь" data-state="SEV">Севастополь</a>
        <a href="#" class="gm-geo-picker__chip" data-city="Симферополь" data-state="KRY">Симферополь</a>
        <a href="#" class="gm-geo-picker__chip" data-city="Ялта" data-state="KRY">Ялта</a>
      </div>
    </div>

    <div class="gm-geo-picker__grid">
      <a href="#" class="gm-geo-picker__city" data-city="Алушта" data-state="KRY">Алушта</a>
      <a href="#" class="gm-geo-picker__city" data-city="Керчь" data-state="KRY">Керчь</a>
      <a href="#" class="gm-geo-picker__city" data-city="Судак" data-state="KRY">Судак</a>
      <a href="#" class="gm-geo-picker__city" data-city="Красноперекопск" data-state="KRY">Красноперекопск</a>
      <a href="#" class="gm-geo-picker__city" data-city="Феодосия" data-state="KRY">Феодосия</a>
      <a href="#" class="gm-geo-picker__city" data-city="Черноморское" data-state="KRY">Черноморское</a>
      <a href="#" class="gm-geo-picker__city" data-city="Евпатория" data-state="KRY">Евпатория</a>
      <a href="#" class="gm-geo-picker__city" data-city="Джанкой" data-state="KRY">Джанкой</a>
      <a href="#" class="gm-geo-picker__city" data-city="Москва" data-state="MOW">Москва</a>
      <a href="#" class="gm-geo-picker__city" data-city="Краснодар" data-state="KDA">Краснодар</a>
      <a href="#" class="gm-geo-picker__city" data-city="Санкт-Петербург" data-state="SPE">Санкт-Петербург</a>
      <a href="#" class="gm-geo-picker__city" data-city="Воронеж" data-state="VOR">Воронеж</a>
      <a href="#" class="gm-geo-picker__city" data-city="Новороссийск" data-state="KDA">Новороссийск</a>
    </div>
  </div>

  <div class="gm-geo-picker__results gm-geo-picker__is-hidden" data-ca-gm-geo-results></div>
  <div class="gm-geo-picker__note gm-geo-picker__is-hidden" data-ca-gm-geo-loading>Ищем города&#8230;</div>
  <div class="gm-geo-picker__note gm-geo-picker__is-hidden" data-ca-gm-geo-empty>Город не найден. Проверьте написание или выберите ближайший крупный город.</div>

  <a class="cm-dialog-closer gm-geo-picker__is-hidden" data-ca-gm-geo-closer aria-hidden="true"></a>
</div>
{literal}
<script>
(function (_, $) {
  var HIDDEN = 'gm-geo-picker__is-hidden';
  var MIN_QUERY = 2;
  var DEBOUNCE = 300;
  var FETCH_LIMIT = 200;
  var SHOW_LIMIT = 30;
  var HOME_STATES = { KRY: 1, SEV: 1 };

  $('.gm-geo-picker').not('[data-gm-geo-inited]').each(function () {
    var $root = $(this).attr('data-gm-geo-inited', '1');
    var $search = $root.find('[data-ca-gm-geo-search]');
    var $curated = $root.find('[data-ca-gm-geo-curated]');
    var $results = $root.find('[data-ca-gm-geo-results]');
    var $loading = $root.find('[data-ca-gm-geo-loading]');
    var $empty = $root.find('[data-ca-gm-geo-empty]');
    var $closer = $root.find('[data-ca-gm-geo-closer]');
    var timer = null;
    var lastQuery = '';

    function selectCity(city) {
      var name = city.value;
      var location = {
        country: city.country_code || 'RU',
        state_code: city.state_code || '',
        locality: name,
        locality_text: name,
        postal_code: String(city.zipcode || '').split(',')[0]
      };

      $.ceAjax('request', fn_url('geo_maps.set_location'), {
        method: 'post',
        data: { location: location, auto_detect: 0 },
        hidden: true,
        caching: false,
        callback: function (response) {
          if (!response.is_can_select_location) {
            return;
          }
          var $blocks = $('[data-ca-geo-map-location-element="location_block"]');
          try {
            sessionStorage.setItem('geo_maps_locator_location', JSON.stringify(location));
          } catch (e) {}
          $('[data-ca-geo-map-location-element="location"]').text(response.city);
          $blocks.data('caGeoMapLocationIsLocationDetected', true);
          $.ceEvent('trigger', 'ce:geomap:location_set_after', [location, $blocks, response, 0]);
          $closer.trigger('click');
        }
      });
    }

    // Поиск аддона ищет и по названию региона, поэтому сначала оставляем города,
    // чьё название начинается с запроса, и поднимаем наверх Крым и Севастополь.
    function rank(cities, query) {
      var q = query.toLowerCase().replace(/ё/g, 'е');
      var byName = $.grep(cities, function (city) {
        return String(city.value || '').toLowerCase().replace(/ё/g, 'е').indexOf(q) === 0;
      });
      var list = byName.length ? byName : cities;
      var home = $.grep(list, function (city) { return HOME_STATES[city.state_code]; });
      var rest = $.grep(list, function (city) { return !HOME_STATES[city.state_code]; });
      return home.concat(rest).slice(0, SHOW_LIMIT);
    }

    function renderResults(cities) {
      $results.empty();
      $empty.toggleClass(HIDDEN, cities.length > 0);

      $.each(cities, function (i, city) {
        var $item = $('<a href="#" class="gm-geo-picker__result"></a>');
        $('<span class="gm-geo-picker__result-city"></span>').text(city.value).appendTo($item);
        if (city.state) {
          $('<span class="gm-geo-picker__result-state"></span>').text(city.state).appendTo($item);
        }
        $item.on('click', function (e) {
          e.preventDefault();
          selectCity(city);
        });
        $results.append($item);
      });
    }

    $curated.on('click', '[data-city]', function (e) {
      e.preventDefault();
      selectCity({ country_code: 'RU', value: $(this).data('city'), state_code: $(this).data('state') });
    });

    $search.on('input', function () {
      var query = $.trim($(this).val());
      clearTimeout(timer);
      lastQuery = query;

      if (query.length < MIN_QUERY) {
        $curated.removeClass(HIDDEN);
        $results.addClass(HIDDEN).empty();
        $empty.addClass(HIDDEN);
        $loading.addClass(HIDDEN);
        return;
      }

      $curated.addClass(HIDDEN);
      $results.removeClass(HIDDEN);
      $empty.addClass(HIDDEN);
      $loading.removeClass(HIDDEN);

      timer = setTimeout(function () {
        $.ceAjax('request', fn_url('city.autocomplete_city'), {
          method: 'get',
          data: { q: query, check_country: 'RU', items_per_page: FETCH_LIMIT },
          hidden: true,
          caching: false,
          callback: function (response) {
            if (query !== lastQuery) {
              return;
            }
            $loading.addClass(HIDDEN);
            renderResults(rank((response && response.autocomplete) || [], query));
          }
        });
      }, DEBOUNCE);
    });
  });
})(Tygh, Tygh.$);
</script>
{/literal}
