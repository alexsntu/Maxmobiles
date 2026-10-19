/*
 * Maxmobiles: карточка товара на шаблоне maxmobiles_card (модуль mm_delivery).
 * 1) отсчёт «Оформите в течение …» в блоке «Получение»; когда время вышло – блок пересчитывается на сервере;
 * 2) город в блоке «Получение» открывает окно выбора города (как в шапке); после выбора блок пересчитывается
 *    запросом mm_delivery.card (событие ce:geomap:location_set_after);
 * 3) на кнопках вариантов (SIM, память, цвет) – цена и зелёная точка «есть в магазине» (данные data-mm-variants);
 * 4) «Все характеристики» в блоке кратких характеристик прокручивает к полному разделу;
 * 5) «Свернуть» у описания и характеристик – обход ошибки модуля ab__hide_product_description (см. ниже).
 * Карточку при смене варианта CS-Cart перерисовывает целиком – всё повторяется по событию ce.commoninit.
 */
(function (_, $) {
    var timerId = null;

    function rub(n) {
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
    }

    function left(sec) {
        var m = Math.max(0, Math.floor(sec / 60)), h = Math.floor(m / 60);
        m %= 60;
        return (h ? h + ' ч ' : '') + m + ' мин';
    }

    function startTimer($card) {
        if (timerId) {
            clearInterval(timerId);
            timerId = null;
        }
        var $t = $card.find('.mm-dd__timer[data-left]');
        if (!$t.length) {
            return;
        }
        var end = Date.now() + parseInt($t.data('left'), 10) * 1000;
        var tick = function () {
            var sec = Math.round((end - Date.now()) / 1000);
            if (sec <= 0) {
                clearInterval(timerId);
                timerId = null;
                reload($card);
                return;
            }
            $t.find('b').text(left(sec));
        };
        tick();
        timerId = setInterval(tick, 20000);
    }

    function reload($card) {
        var pid = $card.data('mmProduct');
        if (!pid) {
            return;
        }
        // Метка времени в адресе: повторный GET с тем же адресом CS-Cart отдаёт из своего кэша ответов (caching: false не помогает),
        // и после второй смены города блок оставался со старым сроком
        $.ceAjax('request', fn_url('mm_delivery.card?product_id=' + pid + '&t=' + Date.now()), {
            method: 'get',
            hidden: true,
            caching: false,
            callback: function (data) {
                if (!data || !data.mm_dd || !data.mm_dd.dd) {
                    return;
                }
                $card.find('.js-mm-stock').html(data.mm_dd.stock);
                $card.find('.js-mm-dd').html(data.mm_dd.dd);
                startTimer($card);
            }
        });
    }

    function annotateVariants($card) {
        var raw = $card.attr('data-mm-variants');
        if (!raw) {
            return;
        }
        var map;
        try {
            map = JSON.parse(raw);
        } catch (e) {
            return;
        }
        $.each(map, function (key, v) {
            var parts = key.split('_');
            // Кнопки-надписи (SIM, память): label for="feature_<f>_variant_<v>_<product>"
            $card.find('label[for^="feature_' + parts[0] + '_variant_' + parts[1] + '_"]').each(function () {
                var $l = $(this);
                if ($l.find('.mm-vprice').length) {
                    return;
                }
                $l.addClass('mm-vopt').append('<small class="mm-vprice">' + rub(v[0]) + '</small>');
                if (v[1]) {
                    $l.append('<i class="mm-vstock" title="есть в магазине"></i>');
                }
            });
            // Кнопки-картинки (цвет): обёртка с картинкой det_img_image_feature_variant_<f>_<v>_<product>
            $card.find('[id*="image_feature_variant_' + parts[0] + '_' + parts[1] + '_"]').closest('.ty-product-options__image--wrapper').each(function () {
                var $w = $(this);
                if (v[1] && !$w.find('.mm-vstock').length) {
                    $w.append('<i class="mm-vstock" title="есть в магазине"></i>');
                }
                if (!$w.attr('data-mm-price')) {
                    $w.attr('data-mm-price', rub(v[0]));
                }
            });
        });
    }

    function init(context) {
        var $card = $('.mm-card', context || _.doc).first();
        if (!$card.length) {
            $card = $(context || _.doc).closest('.mm-card');
        }
        if (!$card.length) {
            return;
        }
        annotateVariants($card);
        startTimer($card);
        $('body').addClass('mm-card-page');
    }

    $(_.doc).on('click', '.mm-card .mm-dd__citybtn', function (e) {
        e.preventDefault();
        var $opener = $('#mm_card_city_opener');
        if (!$opener.length) {
            $opener = $('<a id="mm_card_city_opener" class="cm-dialog-opener cm-dialog-auto-size hidden" rel="nofollow"></a>')
                .attr('href', fn_url('geo_maps.customer_geolocation'))
                .attr('data-ca-target-id', 'mm_card_city_dialog')
                .attr('data-ca-dialog-title', 'Местоположение покупателя')
                .appendTo('body');
        }
        $opener.trigger('click');
    });

    // «Все характеристики» в блоке кратких характеристик – плавная прокрутка к полному разделу (на странице задан <base href>,
    // обычный переход по «#…» увёл бы на главную). Если раздел во вкладке и скрыт – сначала открываем вкладку.
    $(_.doc).on('click', '.mm-card .mm-keys__link', function (e) {
        e.preventDefault();
        var id = this.getAttribute('data-mm-target') || 'content_features',
            target = document.getElementById(id);
        if (target && !target.offsetParent) {
            var $tab = $('#' + id.replace(/^content_/, '') + ' > a, #' + id.replace(/^content_/, '')).first();
            if ($tab.length) {
                $tab.trigger('click');
            }
        }
        if (target) {
            target.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
    });


    // «Свернуть» у разделов «Описание» и «Характеристики» (модуль ab__hide_product_description, js/addons/…/ab__smc.js):
    // в модуле после раскрытия на кнопке остаются оба обработчика – «раскрыть» и «свернуть», и раскрытие побеждает,
    // раздел не сворачивается (так на всех страницах). В карточке перехватываем нажатие раньше модуля (фаза захвата)
    // и сворачиваем сами: возвращаем ограничение высоты и надпись, прокручиваем к началу раздела.
    // Повторное «Показать полностью» работает штатно через модуль.
    document.addEventListener('click', function (e) {
        var more = e.target && e.target.closest ? e.target.closest('.mm-card .ab-smc-more.ab-smc-opened') : null;
        if (!more) {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        var $more = $(more),
            $elem = $more.closest('.ab-smc-description'),
            spanClass = $more.find('span').first().attr('class') || 'ab-smc',
            moreTxt = $elem.attr('data-ab-smc-more') || 'Показать полностью';
        $elem.stop(true, true).removeClass('ab-smc-opened').css({height: ''});
        $elem.find('.ab-smc-button').removeClass('ab-smc-opened');
        $more.removeClass('ab-smc-opened').html('<span class="' + spanClass + '">' + moreTxt + '<i class="ab-smc-arrow"></i></span>');
        if ($elem.length && $elem[0].getBoundingClientRect().top < 0) {
            $elem[0].scrollIntoView({block: 'start'});
        }
    }, true);

    $.ceEvent('on', 'ce.commoninit', function (context) {
        init(context);
    });

    $.ceEvent('on', 'ce:geomap:location_set_after', function () {
        var $card = $('.mm-card').first();
        if ($card.length) {
            reload($card);
        }
    });
})(Tygh, Tygh.$);
