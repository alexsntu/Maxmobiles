<?php
/**
 * Maxmobiles: свой поиск, этап 3 (2026-10-16) – штатная страница результатов (products.search) с товарами, найденными
 * Manticore (app/mm_search/lib.php), в порядке релевантности. Здесь – поиск и набор найденных товаров в Registry
 * 'runtime.mm_search'; подмена условия и сортировка «По релевантности» – хуки get_products_pre, get_products,
 * products_sorting в func.php модуля. Только витрина Maxmobiles, у всех покупателей с 2026-10-16 (Diginetica отключена).
 * Если индекс недоступен – страница работает штатно.
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

// Запрос пришёл в поле hint_q (CS-Cart cm-hint не вернул полю имя q) – считаем его запросом
if ($mode === 'search' && (int) Registry::get('runtime.company_id') === 1 && (!isset($_REQUEST['q']) || trim((string) $_REQUEST['q']) === '')
    && isset($_REQUEST['hint_q']) && trim((string) $_REQUEST['hint_q']) !== '') {
    $_REQUEST['q'] = (string) $_REQUEST['hint_q'];
    $_GET['q'] = $_REQUEST['q'];
}

if ($mode === 'search' && (int) Registry::get('runtime.company_id') === 1
    && isset($_REQUEST['q']) && trim((string) $_REQUEST['q']) !== ''
) {
    $mm_lib = Registry::get('config.dir.root') . '/app/mm_search/lib.php';
    try {
        if (is_file($mm_lib)) {
            require_once $mm_lib;
            // Запрос о магазине («рассрочка», «доставка», «адрес», название ТЦ…) – сразу на страницу магазина (2026-10-21);
            // в статистику – режимом redirect (адрес страницы – в поле shown)
            $mm_info = mm_search_info_page((string) $_REQUEST['q']);
            if ($mm_info) {
                try {
                    if (preg_grep('/^sid_customer_/', array_keys($_COOKIE)) && mm_search_sid() !== '') {
                        db_query('INSERT INTO ?:mm_search_log ?e', ['ts' => TIME, 'sid' => mm_search_sid(), 'source' => 'page',
                            'q' => mb_substr(trim((string) $_REQUEST['q']), 0, 120), 'qn' => mb_substr(mm_search_normalize((string) $_REQUEST['q']), 0, 120),
                            'mode' => 'redirect', 'total' => 1, 'cat' => 0, 'page' => 1, 'sort' => '', 'device' => mm_search_device(), 'ms' => 0, 'shown' => $mm_info['url']]);
                    }
                } catch (\Throwable $e) {
                }
                return [CONTROLLER_STATUS_REDIRECT, Registry::get('config.current_location') . $mm_info['url']];
            }
            // Индекс не отвечает – не держим обработчик PHP: 1 с на соединение, 3 с на ответ (2026-10-17: Manticore завис на 14 ч)
            $mm_mc = mysqli_init();
            $mm_mc->options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);
            $mm_mc->options(MYSQLI_OPT_READ_TIMEOUT, 3);
            @$mm_mc->real_connect('127.0.0.1', '', '', '', 9306);
            if (!$mm_mc->connect_errno) {
                $mm_mc->set_charset('utf8mb4');
                // Запрос = название раздела («ноутбуки», «iphone 17 pro max», «smeg») – сразу в раздел с фильтрами (2026-10-21);
                // не при уточнении по плитке раздела (mm_cat) и не при листании; в статистику – режимом category (адрес – в поле shown)
                if (empty($_REQUEST['mm_cat']) && empty($_REQUEST['page'])) {
                    $mm_cat_url = mm_search_category_page($mm_mc, (string) $_REQUEST['q']);
                    if ($mm_cat_url) {
                        try {
                            if (preg_grep('/^sid_customer_/', array_keys($_COOKIE)) && mm_search_sid() !== '') {
                                db_query('INSERT INTO ?:mm_search_log ?e', ['ts' => TIME, 'sid' => mm_search_sid(), 'source' => 'page',
                                    'q' => mb_substr(trim((string) $_REQUEST['q']), 0, 120), 'qn' => mb_substr(mm_search_normalize((string) $_REQUEST['q']), 0, 120),
                                    'mode' => 'category', 'total' => 1, 'cat' => 0, 'page' => 1, 'sort' => '', 'device' => mm_search_device(), 'ms' => 0, 'shown' => $mm_cat_url]);
                            }
                        } catch (\Throwable $e) {
                        }
                        return [CONTROLLER_STATUS_REDIRECT, $mm_cat_url];
                    }
                }
                $mm_t0 = microtime(true);
                $mm_found = mm_search($mm_mc, (string) $_REQUEST['q'], 1000);
                // Ответ не пришёл за 3 с (2006/2013 – соединение потеряно) – штатный поиск, см. catch ниже
                if (in_array($mm_mc->errno, [2006, 2013], true)) {
                    throw new \RuntimeException('manticore: ' . $mm_mc->error);
                }
                $mm_ms = (int) round((microtime(true) - $mm_t0) * 1000);
                // Плитка «Найдено в категориях» (mm_cat) – только товары этой категории (по основной категории товара)
                $mm_cat = isset($_REQUEST['mm_cat']) ? (int) $_REQUEST['mm_cat'] : 0;
                $mm_items = $mm_cat ? array_filter($mm_found['items'], function ($x) use ($mm_cat) { return (int) $x['main_cat'] === $mm_cat; }) : $mm_found['items'];
                Registry::set('runtime.mm_search', [
                    'q' => trim((string) $_REQUEST['q']),
                    'ids' => array_values(array_map(function ($x) { return (int) $x['id']; }, $mm_items)),
                    'mode' => $mm_found['mode'],
                    'cat' => $mm_cat,
                    'log_id' => 0,
                ]);
                // Ничего не нашлось – данные для своей страницы «ничего не нашлось» (шаблон-подмена хука
                // products:search_results_no_matching_found, 2026-10-21; стили – раздел MM-16 «Своего CSS»)
                if (!$mm_items && !$mm_cat) {
                    Tygh::$app['view']->assign('mm_empty', mm_search_empty($mm_mc, (string) $_REQUEST['q']));
                }
                // Статистика (cscart_mm_search_log): что искали, как нашли; какие товары показаны – допишет хук get_products_post.
                // Сбой записи не мешает поиску
                try {
                    // Нет cookie сессии сайта (её ставит первая же страница) – это бот, не покупатель: ищем, но в статистику
                    // не пишем (2026-10-18: бот без cookie на GOODMi – 49 поисков за 35 с, каждый как новый посетитель)
                    if (!preg_grep('/^sid_customer_/', array_keys($_COOKIE))) {
                        throw new \RuntimeException('no session cookie');
                    }
                    // Метка браузера – сразу на сервере, чтобы первый же поиск, клик и корзина связались между собой
                    if (mm_search_sid() === '') {
                        $_COOKIE['mm_sid'] = bin2hex(random_bytes(8));
                        setcookie('mm_sid', $_COOKIE['mm_sid'], ['expires' => time() + 31536000, 'path' => '/', 'secure' => true, 'samesite' => 'Lax']);
                    }
                    $mm_log_id = db_query('INSERT INTO ?:mm_search_log ?e', [
                        'ts' => TIME,
                        'sid' => mm_search_sid(),
                        'source' => 'page',
                        'q' => mb_substr(trim((string) $_REQUEST['q']), 0, 120),
                        'qn' => mb_substr(mm_search_normalize((string) $_REQUEST['q']), 0, 120),
                        'mode' => $mm_found['mode'],
                        'total' => count(Registry::get('runtime.mm_search.ids')),
                        'cat' => $mm_cat,
                        'page' => max(1, isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1),
                        'sort' => !empty($_REQUEST['sort_by']) ? mb_substr((string) $_REQUEST['sort_by'] . '-' . (isset($_REQUEST['sort_order']) ? (string) $_REQUEST['sort_order'] : ''), 0, 24) : 'relevance-asc',
                        'device' => mm_search_device(),
                        'ms' => min(65535, $mm_ms),
                        'shown' => '',
                    ]);
                    Registry::set('runtime.mm_search.log_id', (int) $mm_log_id);
                } catch (\Throwable $e) {
                }
                $_REQUEST['search_performed'] = 'Y';
                if (empty($_REQUEST['sort_by'])) {
                    $_REQUEST['sort_by'] = 'relevance';
                    $_REQUEST['sort_order'] = 'asc';
                }
                // «По релевантности» – в списке доступных сортировок только на этой странице
                Registry::set('settings.Appearance.available_product_list_sortings.relevance-asc', 'Y');
                Tygh::$app['view']->assign('settings', Registry::get('settings'));
                // Подпись пункта в выпадающем списке сортировки темы (abt__ut2.sort_by_<поле>_<порядок>) – без записи в
                // базу: дописываем в кэш переводов этой страницы (сначала читаем любую подпись, чтобы кэш загрузился)
                __('abt__ut2.sort_by_popularity_desc');
                $mm_lang = (array) Registry::get('lang_cache.' . CART_LANGUAGE);
                $mm_lang['abt__ut2.sort_by_relevance_asc'] = 'По релевантности';
                Registry::set('lang_cache.' . CART_LANGUAGE, $mm_lang);
            }
        }
    } catch (\Throwable $e) {
        // Индекс недоступен – штатный поиск
        Registry::del('runtime.mm_search');
    }
}
