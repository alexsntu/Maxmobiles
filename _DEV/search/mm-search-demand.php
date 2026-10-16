<?php
/**
 * Maxmobiles: спрос на товары за последние 90 дней для сортировки своего поиска (2026-10-16, пользователь: «не всю
 * популярность за всё время, а за периоды, например 3 месяца – тогда популярность будет отражать спрос»).
 *
 * 1) Продажи из МойСклад Maxmobiles за 90 дней: розничные продажи (retaildemand) и отгрузки (demand), кроме каналов других
 *    витрин (Гудми, Xione). Товар МойСклад сопоставляется с товаром сайта по коду (code = product_code). Последние 30 дней
 *    считаются вдвое. Только чтение МойСклад, страницы по 100 документов с паузой – лимит запросов общий с обменом сайта.
 * 2) Ежедневный снимок накопленной популярности CS-Cart (product_popularity.total) – из разницы снимков позже считаются
 *    просмотры и добавления в корзину за период (снимки в pop-snap/, хранятся 120 дней).
 * Результат – demand.json рядом со скриптом: {product_id: {"q90": штук за 90 дней, "q30": за 30, "w": взвешенно}}.
 * Запуск раз в сутки (cron), около 1 минуты.
 */
error_reporting(E_ERROR);
chdir('/var/www/maxmobiles/data/www/maxmobiles.ru');
define('BOOTSTRAP', 1); define('DIR_ROOT', getcwd()); define('PRODUCT_STATUS', '');
require 'app/mm_cabinet/lib.php';
$config = []; @include 'config.local.php';
$db = new mysqli($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name']);
$db->set_charset('utf8');
$p = $config['table_prefix'];
$t_start = microtime(true);
$h = mm_cab_ms_headers();
if (!$h) { fwrite(STDERR, "нет ключа МойСклад\n"); exit(1); }

$codes = [];
$r = $db->query("SELECT product_id, product_code FROM {$p}products WHERE company_id = 1 AND product_code <> ''");
while ($x = $r->fetch_row()) { $codes[$x[1]] = (int) $x[0]; }

$hidden = ['гудми', 'xione', 'xione - cs'];
$from90 = date('Y-m-d 00:00:00', time() - 90 * 86400);
$from30 = date('Y-m-d H:i:s', time() - 30 * 86400);
$demand = []; $docs = 0; $pos_total = 0; $pos_matched = 0;
foreach (['retaildemand', 'demand'] as $entity) {
    for ($offset = 0; $offset < 20000; $offset += 100) {
        $url = 'https://api.moysklad.ru/api/remap/1.2/entity/' . $entity . '?filter=' . rawurlencode('moment>=' . $from90)
            . '&limit=100&offset=' . $offset . '&expand=positions.assortment,salesChannel';
        $page = null;
        for ($try = 0; $try < 5 && $page === null; $try++) {
            if ($try) { sleep(15); }
            $page = mm_cab_http($url, $h, null, 60);
        }
        if ($page === null) { fwrite(STDERR, "МойСклад не ответил ($entity, offset $offset) – спрос не обновлён\n"); exit(1); }
        $rows = isset($page['rows']) ? $page['rows'] : [];
        foreach ($rows as $doc) {
            $channel = mb_strtolower(trim((string) (isset($doc['salesChannel']['name']) ? $doc['salesChannel']['name'] : '')));
            if (in_array($channel, $hidden, true)) { continue; }
            $docs++;
            $recent = (string) $doc['moment'] >= $from30;
            foreach ((array) (isset($doc['positions']['rows']) ? $doc['positions']['rows'] : []) as $pos) {
                $code = isset($pos['assortment']['code']) ? (string) $pos['assortment']['code'] : '';
                $pos_total++;
                if ($code === '' || !isset($codes[$code])) { continue; }
                $pos_matched++;
                $pid = $codes[$code];
                $qty = max(0, (float) $pos['quantity']);
                if (!isset($demand[$pid])) { $demand[$pid] = ['q90' => 0, 'q30' => 0]; }
                $demand[$pid]['q90'] += $qty;
                if ($recent) { $demand[$pid]['q30'] += $qty; }
            }
        }
        if (count($rows) < 100) { break; }
        sleep(1);
    }
}
foreach ($demand as $pid => $d) { $demand[$pid]['w'] = $d['q90'] + $d['q30']; } // последний месяц – вдвое
file_put_contents(__DIR__ . '/demand.json.tmp', json_encode($demand));
rename(__DIR__ . '/demand.json.tmp', __DIR__ . '/demand.json');

// Ежедневный снимок накопленной популярности
$snap_dir = __DIR__ . '/pop-snap';
@mkdir($snap_dir, 0700);
$snap = [];
$r = $db->query("SELECT pp.product_id, pp.total FROM {$p}product_popularity pp JOIN {$p}products pr ON pr.product_id = pp.product_id AND pr.company_id = 1");
while ($x = $r->fetch_row()) { $snap[(int) $x[0]] = (int) $x[1]; }
file_put_contents($snap_dir . '/' . date('Y-m-d') . '.json', json_encode($snap));
foreach (glob($snap_dir . '/*.json') as $f) { if (filemtime($f) < time() - 120 * 86400) { unlink($f); } }

// Статистика поиска хранится 180 дней (решение 2026-10-16): старше – удаляем
$cut = time() - 180 * 86400;
foreach (['mm_search_log', 'mm_search_clicks', 'mm_search_events'] as $t) { $db->query("DELETE FROM {$p}{$t} WHERE ts < $cut"); }

// Статус для админки (mm_search.index)
$mc = @new mysqli('127.0.0.1', '', '', '', 9306);
if (!$mc->connect_errno) {
    $mc->set_charset('utf8mb4');
    $mc->query("CREATE TABLE IF NOT EXISTS mm_status (kind string, ts timestamp, seconds float, items int, note text stored)");
    $note = $mc->real_escape_string(sprintf('продаж МойСклад %d, позиций %d, сопоставлено с сайтом %d', $docs, $pos_total, $pos_matched));
    $mc->query(sprintf("INSERT INTO mm_status (id, kind, ts, seconds, items, note) VALUES (%d, 'demand', %d, %F, %d, '%s')", (int) (microtime(true) * 1000), time(), microtime(true) - $t_start, count($demand), $note));
}

printf("Готово: документов %d, позиций %d, сопоставлено с сайтом %d, товаров со спросом %d; снимок популярности %d товаров\n",
    $docs, $pos_total, $pos_matched, count($demand), count($snap));
