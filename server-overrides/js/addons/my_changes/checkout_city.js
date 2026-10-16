/*
 * Maxmobiles: оформление заказа – нажатие на город открывает то же окно выбора города, что и в шапке
 * (поиск + популярные города, addons/my_changes/views/geo_maps/customer_geolocation.tpl).
 * После выбора CS-Cart сам перезагружает оформление с новым городом (lite_checkout.js, событие
 * ce:geomap:location_set_after). На телефоне строки с городом в шапке нет, поэтому окно открывается
 * собственной скрытой ссылкой. С клавиатуры поле доступно как раньше – с подсказками под ним.
 * Копия обработчика GOODMi из js/addons/gm_delivery_date/func.js, без сроков доставки.
 */
(function (_, $) {
    $(_.doc).on('mousedown', '#litecheckout_form [data-ca-lite-checkout-element="city-autocomplete"]', function (e) {
        if (e.which > 1) {
            return;
        }
        var $opener = $('#mm_co_city_opener');
        if (!$opener.length) {
            $opener = $('<a id="mm_co_city_opener" class="cm-dialog-opener cm-dialog-auto-size hidden" rel="nofollow"></a>')
                .attr('href', fn_url('geo_maps.customer_geolocation'))
                .attr('data-ca-target-id', 'mm_co_city_dialog')
                .attr('data-ca-dialog-title', 'Местоположение покупателя')
                .appendTo('body');
        }
        e.preventDefault();
        $(this).trigger('blur');
        $opener.trigger('click');
    });
})(Tygh, Tygh.$);
