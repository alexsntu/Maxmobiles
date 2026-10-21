<?php
/**
 * Maxmobiles: админка своего поиска (2026-10-16) – статистика для улучшения и обучения поиска, проверка запроса, индекс.
 * heaven.php?dispatch=mm_search.<раздел>[&days=7]:
 *   manage   – обзор: поиски, нулевые показы, нулевые клики, CTR, место клика, корзина и заказы после поиска, по дням;
 *   queries  – все запросы с показателями (сортировка sort=, фильтр f=);
 *   zero     – ничего не найдено; noclick – нашли, но не кликнули;
 *   suggest  – что выбирают в подсказках и брошенный ввод;
 *   orders   – заказы после поиска (купили ли кликнутый товар);
 *   check    – проверка запроса q=: как понят и почему товары стоят в таком порядке;
 *   candidates – кандидаты в синонимы из переформулировок (POST action=accept – сделать синонимом, dismiss – скрыть пару);
 *   synonyms – свои синонимы (POST action=save|toggle|delete); pins – закрепления товаров по запросу (action=add|delete|up|down);
 *              изменения выгружаются в app/mm_search/custom.json и сразу действуют в поиске;
 *   index    – состояние индекса и запуски сборки; POST action=rebuild – пересобрать (флаг, cron раз в минуту).
 * Расчёты – app/mm_search/stats.php, поиск – app/mm_search/lib.php; шаблоны – design/backend/templates/views/mm_search/.
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$mm_root = Registry::get('config.dir.root');
require_once $mm_root . '/app/mm_search/lib.php';
require_once $mm_root . '/app/mm_search/stats.php';

$mm_mc = @new mysqli('127.0.0.1', '', '', '', 9306);
if ($mm_mc->connect_errno) {
    $mm_mc = null;
} else {
    $mm_mc->set_charset('utf8mb4');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'index' && isset($_REQUEST['action']) && $_REQUEST['action'] === 'rebuild') {
        // Сборку запускает cron (root) раз в минуту, если есть флаг – у веб-сервера нет доступа к скриптам /root/mm-search
        $dir = $mm_root . '/var/mm_search';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/rebuild.flag', (string) time());
        fn_set_notification('N', 'Готово', 'Индекс пересоберётся в течение минуты. Обновите страницу, чтобы увидеть новый запуск.');
    }

    $act = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
    $res = null;
    if ($mode === 'synonyms') {
        if ($act === 'save') { $res = mm_stats_synonym_save((string) ($_REQUEST['src'] ?? ''), (string) ($_REQUEST['dst'] ?? ''), (string) ($_REQUEST['note'] ?? '')); }
        elseif ($act === 'toggle') { mm_stats_synonym_toggle((int) ($_REQUEST['id'] ?? 0), !empty($_REQUEST['active'])); }
        elseif ($act === 'delete') { mm_stats_synonym_delete((int) ($_REQUEST['id'] ?? 0)); $res = ['ok' => true, 'message' => 'Синоним удалён.']; }
    } elseif ($mode === 'candidates') {
        if ($act === 'accept') {
            $res = mm_stats_synonym_save((string) ($_REQUEST['src'] ?? ''), (string) ($_REQUEST['dst'] ?? ''),
                mb_substr('из переформулировки: «' . (string) ($_REQUEST['a'] ?? '') . '» → «' . (string) ($_REQUEST['b'] ?? '') . '»', 0, 120));
        } elseif ($act === 'dismiss') {
            mm_stats_candidate_dismiss((string) ($_REQUEST['a'] ?? ''), (string) ($_REQUEST['b'] ?? ''));
            $res = ['ok' => true, 'message' => 'Пара скрыта и больше не появится в кандидатах.'];
        }
    } elseif ($mode === 'pins') {
        if ($act === 'add') { $res = mm_stats_pin_add((string) ($_REQUEST['q'] ?? ''), (int) ($_REQUEST['product_id'] ?? 0)); }
        elseif ($act === 'delete') { mm_stats_pin_delete((int) ($_REQUEST['id'] ?? 0)); $res = ['ok' => true, 'message' => 'Закрепление снято.']; }
        elseif ($act === 'up' || $act === 'down') { mm_stats_pin_move((int) ($_REQUEST['id'] ?? 0), $act === 'up' ? -1 : 1); }
    }
    if ($res) {
        fn_set_notification($res['ok'] ? 'N' : 'E', $res['ok'] ? 'Готово' : 'Ошибка', $res['message']);
    }
    $back = $mode === 'pins' && !empty($_REQUEST['q']) ? 'mm_search.pins?q=' . urlencode((string) $_REQUEST['q']) : 'mm_search.' . $mode;
    if ($mode === 'candidates' && !empty($_REQUEST['days'])) { $back .= '?days=' . (int) $_REQUEST['days']; }
    // Синонимы: вернуться на ту же страницу списка и с тем же поиском
    if ($mode === 'synonyms' && (!empty($_REQUEST['find']) || !empty($_REQUEST['page']))) {
        $back .= '?find=' . urlencode((string) ($_REQUEST['find'] ?? '')) . '&page=' . (int) ($_REQUEST['page'] ?? 1);
    }

    return [CONTROLLER_STATUS_OK, $back];
}

$mm_days = isset($_REQUEST['days']) && isset(mm_stats_periods()[(int) $_REQUEST['days']]) ? (int) $_REQUEST['days'] : 7;
$mm_from = mm_stats_from($mm_days);
$view = Tygh::$app['view'];
$view->assign('mm_days', $mm_days);
$view->assign('mm_periods', mm_stats_periods());
$view->assign('mm_mode', $mode);
$view->assign('mm_tabs', [
    'manage' => 'Обзор', 'queries' => 'Запросы', 'zero' => 'Ничего не найдено', 'noclick' => 'Нашли, но не кликнули',
    'suggest' => 'Подсказки', 'orders' => 'Заказы после поиска', 'check' => 'Проверка запроса', 'candidates' => 'Кандидаты в синонимы', 'synonyms' => 'Синонимы',
    'pins' => 'Закрепления', 'index' => 'Индекс',
]);

if ($mode === 'manage') {
    $view->assign('mm_o', mm_stats_overview($mm_from));
    $view->assign('mm_top', mm_stats_queries($mm_from, 'all', 'searches', '', 15));
    $view->assign('mm_top_zero', mm_stats_queries($mm_from, 'zero', 'searches', '', 10));
    $view->assign('mm_top_noclick', mm_stats_queries($mm_from, 'noclick', 'searches', '', 10));
} elseif ($mode === 'queries' || $mode === 'zero' || $mode === 'noclick') {
    $sort = isset($_REQUEST['sort']) ? (string) $_REQUEST['sort'] : 'searches';
    $filter = isset($_REQUEST['f']) ? mb_substr(trim((string) $_REQUEST['f']), 0, 60) : '';
    $view->assign('mm_rows', mm_stats_queries($mm_from, $mode === 'queries' ? 'all' : $mode, $sort, $filter, 300));
    $view->assign('mm_sort', $sort);
    $view->assign('mm_filter', $filter);
} elseif ($mode === 'suggest') {
    $view->assign('mm_s', mm_stats_suggest($mm_from));
} elseif ($mode === 'orders') {
    $view->assign('mm_rows', mm_stats_orders($mm_from));
} elseif ($mode === 'check') {
    $q = isset($_REQUEST['q']) ? mb_substr(trim((string) $_REQUEST['q']), 0, 100) : '';
    $view->assign('mm_q', $q);
    if ($q !== '' && $mm_mc) {
        $view->assign('mm_e', mm_search_explain($mm_mc, $q, 30));
        $view->assign('mm_sg', mm_search_suggest($mm_mc, $q));
    }
    $view->assign('mm_engine', (bool) $mm_mc);
} elseif ($mode === 'candidates') {
    $view->assign('mm_rows', mm_stats_candidates($mm_from, 150));
} elseif ($mode === 'synonyms') {
    $mm_list = mm_stats_synonyms(isset($_REQUEST['find']) ? mb_substr((string) $_REQUEST['find'], 0, 60) : '', isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1, 25);
    $view->assign('mm_list', $mm_list);
    $view->assign('mm_rows', $mm_list['rows']);
    $view->assign('mm_src', isset($_REQUEST['src']) ? mb_substr((string) $_REQUEST['src'], 0, 80) : '');
    $view->assign('mm_builtin', count(mm_search_synonyms()) - count(db_get_fields('SELECT src FROM ?:mm_search_synonyms WHERE active = 1')));
} elseif ($mode === 'pins') {
    $q = isset($_REQUEST['q']) ? mb_substr(trim((string) $_REQUEST['q']), 0, 100) : '';
    $view->assign('mm_q', $q);
    $view->assign('mm_rows', mm_stats_pins());
    if ($q !== '' && $mm_mc) {
        $view->assign('mm_e', mm_search_explain($mm_mc, $q, 20));
    }
} elseif ($mode === 'index') {
    $view->assign('mm_i', mm_stats_index($mm_mc));
    $view->assign('mm_flag', is_file($mm_root . '/var/mm_search/rebuild.flag'));
} else {
    return [CONTROLLER_STATUS_NO_PAGE];
}
