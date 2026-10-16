/*
 * Maxmobiles: ссылки на страницу сравнения открывают саму страницу, а не окно «Списки сравнений».
 * Модуль ab__extended_comparison_wishlist вешает на все ссылки на страницу сравнения (значок в шапке, нижнее меню
 * телефона, кнопка «Список сравнения» в уведомлении «Товар добавлен») открытие окна со списками; из уведомления оно
 * вставало поверх ещё открытого уведомления. Здесь клик по такой ссылке перехватывается раньше обработчика модуля
 * (фаза перехвата на document) и ссылка открывается как обычная: страница сравнения сама выбирает первый список,
 * между списками переключают вкладки на странице. Кнопка в уведомлении ведёт сразу на список только что добавленного
 * товара (?product_id=…, его понимает группировка «По разделам»; модуль кладёт новый товар в начало списка).
 * Ссылки внутри самой страницы сравнения и окна списков не трогаются.
 * Копия для GOODMi – js/addons/gm_delivery_date/compare_notice.js.
 */
(function (_) {
    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || link.closest('.ab-ec-comparison, #account_info_ab__extended_comparison_wishlist_groups, .ab-ec-comparison-modal')) {
            return;
        }
        var ecw = _.ab__ecw || {};
        var compare_url = (ecw.runtime && ecw.runtime.features_compare_url) || fn_url('product_features.compare');
        var href = link.getAttribute('href') || '';
        if (href.split('?')[0] !== compare_url.split('?')[0]) {
            return;
        }
        // Обработчик модуля (окно со списками) не получает клик; переход выполняем сами, не полагаясь на штатный
        event.stopImmediatePropagation();
        event.preventDefault();
        var target = href;
        if (link.closest('.cm-notification-content, .notification-content-extended')) {
            var list = Array.isArray(ecw.comparison_list) ? ecw.comparison_list : [];
            target = compare_url;
            if (list.length) {
                target += (compare_url.indexOf('?') === -1 ? '?' : '&') + 'product_id=' + encodeURIComponent(list[0]);
            }
        }
        window.location.href = target;
    }, true);
})(Tygh);
