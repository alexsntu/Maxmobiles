<?php
/**
 * Maxmobiles: сравнение своего поиска с Diginetica на реальных запросах покупателей (этап 1, 2026-10-16). Только чтение:
 * запросы к Manticore и к открытому поиску Diginetica (тот же публичный ключ, что у скрипта на сайте, лежит в dg.key).
 * php mm-search-compare.php queries.txt > compare.json   (queries.txt – «частота<TAB>запрос», по строке)
 */
error_reporting(E_ERROR);
require '/var/www/maxmobiles/data/www/maxmobiles.ru/app/mm_search/lib.php';
$mc = new mysqli('127.0.0.1', '', '', '', 9306);
$mc->set_charset('utf8mb4');
$key = trim((string) @file_get_contents(__DIR__ . '/dg.key'));
$stock = [];
$r = $mc->query('SELECT id, in_stock, is_acc FROM mm_products LIMIT 10000');
while ($r && ($x = $r->fetch_assoc())) { $stock[(int) $x['id']] = $x; }

function dg($q, $key)
{
    $url = 'https://sort.diginetica.net/search?st=' . rawurlencode($q) . '&apiKey=' . $key
        . '&strategy=advanced_xname,zero_queries&fullData=true&withCorrection=true&withFacets=false&regionId=global&size=10&offset=0'
        . '&showUnavailable=true&unavailableMultiplier=0.2&sort=DEFAULT';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $t = microtime(true);
    $d = json_decode((string) curl_exec($ch), true);
    $ms = (int) round((microtime(true) - $t) * 1000);
    curl_close($ch);
    if (!is_array($d)) { return null; }
    $items = [];
    foreach ((array) (isset($d['products']) ? $d['products'] : []) as $p) {
        $items[] = ['id' => (int) $p['id'], 'name' => (string) $p['name'], 'in_stock' => !empty($p['available']) ? 1 : 0];
    }
    return ['total' => (int) (isset($d['totalHits']) ? $d['totalHits'] : 0), 'correction' => isset($d['correction']) ? $d['correction'] : '', 'items' => $items, 'ms' => $ms];
}

$out = [];
foreach (file($argv[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    list($freq, $q) = explode("\t", $line, 2);
    $t = microtime(true);
    $mine = mm_search($mc, $q, 10);
    $ms = (int) round((microtime(true) - $t) * 1000);
    foreach ($mine['items'] as &$it) { $it = ['id' => (int) $it['id'], 'name' => $it['name'], 'in_stock' => (int) $it['in_stock'], 'is_acc' => (int) $it['is_acc']]; }
    unset($it);
    $d = dg($q, $key);
    if ($d) { foreach ($d['items'] as &$it) { $it['is_acc'] = isset($stock[$it['id']]) ? (int) $stock[$it['id']]['is_acc'] : -1; } unset($it); }
    $out[] = ['q' => $q, 'freq' => (int) $freq, 'mine' => $mine + ['ms' => $ms], 'dg' => $d];
    usleep(150000); // не нагружаем Diginetica
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
