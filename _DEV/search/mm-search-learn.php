<?php
/**
 * Maxmobiles: обучение своего поиска на действиях покупателей (2026-10-21, пользователь: «давай делай пункт 1 – обучение на кликах»).
 *
 * За 90 дней берёт из статистики поиска, какие товары покупатели выбирали по каждому запросу:
 *   – клик по товару на странице результатов (cscart_mm_search_clicks, source = results) – вес 1, по первым трём местам – 0.7
 *     (там кликают по инерции, такой клик меньше говорит о том, что товар нужен);
 *   – клик по товару в выпадающих подсказках (source = sg_product) – по тому, что человек успел ввести, вес как у клика;
 *   – товар из поиска положили в корзину (cscart_mm_search_events, type = cart) – вес 2; заказали (order) – вес 3.
 *     Только товары, которые этот поиск показал (поле shown журнала): заказ привязывается к последнему поиску в сессии
 *     целиком, со всей корзиной, – без проверки к «s 26 ultra» прилипали iPad и кабели из того же заказа.
 * От одного посетителя (кука mm_sid) по паре «запрос → товар» считается одно, самое сильное действие – повторные клики
 * одного человека не накручивают. Пара попадает в обучение, если её выбрали не меньше двух разных посетителей или был заказ;
 * по запросу – не больше 10 товаров.
 * Результат – app/mm_search/learned.json: {"built": время, "q": {запрос: {товар: множитель}}}; множитель
 * 1 + 0.4·ln(1 + вес), не больше 2. Поиск (mm_search_raw) умножает на него оценку товара по этому запросу – внутри тех же
 * ступеней порядка (аксессуары ниже устройств, наличие и т. п. не меняются). Ключ запроса – mm_search_normalize, как у закреплений.
 * Запуск раз в сутки (cron, /etc/cron.d/mm-search), секунды. Только чтение базы; файл пишется целиком через временный.
 */
error_reporting(E_ERROR);
chdir('/var/www/maxmobiles/data/www/maxmobiles.ru');
define('BOOTSTRAP', 1); define('DIR_ROOT', getcwd()); define('PRODUCT_STATUS', '');
require 'app/mm_search/lib.php';
$config = []; @include 'config.local.php';
$db = new mysqli($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name']);
$db->set_charset('utf8mb4');
$p = $config['table_prefix'];
$since = time() - 90 * 86400;
$out_file = getenv('LEARN_OUT') ?: 'app/mm_search/learned.json'; // LEARN_OUT – для проверки, не трогая сайт

// [запрос][товар][посетитель] = самое сильное действие
$act = [];
$add = function ($key, $pid, $sid, $w) use (&$act) {
    if ($key === '' || $pid <= 0 || $sid === '') { return; }
    if (!isset($act[$key][$pid][$sid]) || $act[$key][$pid][$sid] < $w) { $act[$key][$pid][$sid] = $w; }
    if ($w >= 3) { $act[$key][$pid]['#order'] = 1; }
};
$n_clicks = $n_events = 0;
$r = $db->query("SELECT l.qn, c.product_id, c.sid, c.pos FROM {$p}mm_search_clicks c JOIN {$p}mm_search_log l ON l.id = c.search_id
    WHERE c.source = 'results' AND c.ts > $since AND c.sid <> '' AND c.product_id > 0");
while ($r && ($x = $r->fetch_row())) { $add(mm_search_normalize($x[0]), (int) $x[1], $x[2], (int) $x[3] <= 3 ? 0.7 : 1.0); $n_clicks++; }
$r = $db->query("SELECT typed, product_id, sid, pos FROM {$p}mm_search_clicks
    WHERE source = 'sg_product' AND ts > $since AND sid <> '' AND product_id > 0 AND CHAR_LENGTH(typed) >= 3");
while ($r && ($x = $r->fetch_row())) { $add(mm_search_normalize($x[0]), (int) $x[1], $x[2], (int) $x[3] <= 3 ? 0.7 : 1.0); $n_clicks++; }
$r = $db->query("SELECT l.qn, e.product_id, e.sid, e.type FROM {$p}mm_search_events e JOIN {$p}mm_search_log l ON l.id = e.search_id
    WHERE e.ts > $since AND e.sid <> '' AND e.product_id > 0 AND e.type IN ('cart', 'order') AND FIND_IN_SET(e.product_id, l.shown) > 0");
while ($r && ($x = $r->fetch_row())) { $add(mm_search_normalize($x[0]), (int) $x[1], $x[2], $x[3] === 'order' ? 3.0 : 2.0); $n_events++; }

$learned = []; $pairs = 0;
foreach ($act as $key => $prods) {
    $rows = [];
    foreach ($prods as $pid => $by_sid) {
        $order = isset($by_sid['#order']); unset($by_sid['#order']);
        if (count($by_sid) < 2 && !$order) { continue; }
        $w = array_sum($by_sid);
        $rows[$pid] = min(2.0, round(1 + 0.4 * log(1 + $w), 2));
    }
    if (!$rows) { continue; }
    arsort($rows);
    $learned[$key] = array_slice($rows, 0, 10, true);
    $pairs += count($learned[$key]);
}
ksort($learned);
$tmp = $out_file . '.tmp';
file_put_contents($tmp, json_encode(['built' => time(), 'q' => $learned], JSON_UNESCAPED_UNICODE));
@chown($tmp, 'maxmobiles'); @chgrp($tmp, 'maxmobiles'); @chmod($tmp, 0644);
rename($tmp, $out_file);
printf("обучение: действий – кликов %d, корзина/заказ %d; запросов %d, пар «запрос → товар» %d\n", $n_clicks, $n_events, count($learned), $pairs);
