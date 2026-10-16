<?php
/**
 * Maxmobiles (копия _DEV/search-gm/gm-bench.php GOODMi): проверка поиска на запросах, построенных по своему каталогу (2026-10-16; пока отчёты Diginetica недоступны –
 * пользователь: «потестируй их поиск и нашу версию, для нахождения слабых мест и закрыть их»). Про каждый запрос известно,
 * что правильно:
 *   cat   – название раздела («фонарики», «роботы пылесосы»): первый товар и ≥3 из 5 – из этого раздела;
 *   model – модель латиницей и по-русски («redmi note 15 pro» / «редми нот 15 про»): товар этой модели в первой тройке;
 *   type  – тип + бренд («пылесос dreame» / «пылесос дриме»): первый товар – из раздела и этого бренда.
 * Сравнение: наш поиск (app/mm_search/lib.php) и открытый поиск Diginetica (ключ сайта, dg.key). Только чтение.
 * php mm-bench.php [lib.php] > bench.json   (по умолчанию – библиотека сайта); --no-dg – без Diginetica
 */
error_reporting(E_ERROR);
$lib = isset($argv[1]) && substr($argv[1], -4) === '.php' ? $argv[1] : '/var/www/maxmobiles/data/www/maxmobiles.ru/app/mm_search/lib.php';
require $lib;
$use_dg = !in_array('--no-dg', $argv, true);
$key = trim((string) @file_get_contents(__DIR__ . '/dg.key'));
$mc = new mysqli('127.0.0.1', '', '', '', 9306);
$mc->set_charset('utf8mb4');

// Товары и разделы из своего индекса
$prod = [];
$r = $mc->query('SELECT id, name, main_cat, cat_ids, in_stock, is_acc, mods FROM mm_products LIMIT 10000 OPTION max_matches = 10000');
while ($x = $r->fetch_assoc()) { $prod[(int) $x['id']] = $x; }
$cats = [];
$r = $mc->query('SELECT id, name, lem, products FROM mm_categories LIMIT 2000');
while ($x = $r->fetch_assoc()) { $cats[(int) $x['id']] = $x; }

// Как покупатели пишут латинские слова по-русски
$ru = ['xiaomi' => 'сяоми', 'redmi' => 'редми', 'poco' => 'поко', 'note' => 'нот', 'pro' => 'про', 'max' => 'макс', 'ultra' => 'ультра',
    'plus' => 'плюс', 'lite' => 'лайт', 'mini' => 'мини', 'air' => 'эир', 'pad' => 'пад', 'watch' => 'вотч', 'band' => 'бэнд', 'smart' => 'смарт',
    'buds' => 'бадс', 'active' => 'актив', 'play' => 'плэй', 'dreame' => 'дриме', 'roborock' => 'роборок', 'samsung' => 'самсунг',
    'galaxy' => 'галакси', 'mijia' => 'миджия', 'amazfit' => 'амазфит', 'ecoflow' => 'экофлоу', 'jbl' => 'джбл', 'honor' => 'хонор',
    'huawei' => 'хуавей', 'realme' => 'реалми', 'oneplus' => 'ванплас', 'apple' => 'эпл', 'iphone' => 'айфон', 'ipad' => 'айпад',
    'airpods' => 'аирподс', 'sony' => 'сони', 'google' => 'гугл', 'pixel' => 'пиксель', 'tv' => 'тв', 'box' => 'бокс', 'stick' => 'стик',
    'robot' => 'робот', 'vacuum' => 'вакуум', 'baseus' => 'бейсус', 'ugreen' => 'югрин', 'nillkin' => 'нилкин', 'aqara' => 'акара',
    'yeelight' => 'йилайт', 'ninebot' => 'найнбот', 'huohou' => 'хуохоу', 'viomi' => 'виоми', 'laifen' => 'лайфен', 'dyson' => 'дайсон',
    'marshall' => 'маршал', 'hoco' => 'хоко', 'qcy' => 'кьюсиай', 'haylou' => 'хайлоу', 'nothing' => 'нотинг', 'phone' => 'фон', 'neo' => 'нео',
    'macbook' => 'макбук', 'pencil' => 'пенсил', 'magsafe' => 'магсейф', 'series' => 'сериес', 'playstation' => 'плейстейшн', 'dualsense' => 'дуалсенс',
    'xbox' => 'иксбокс', 'nintendo' => 'нинтендо', 'switch' => 'свитч', 'imac' => 'аймак', 'studio' => 'студио', 'display' => 'дисплей', 'beats' => 'битс',
    'harman' => 'харман', 'kardon' => 'кардон', 'bose' => 'бозе', 'garmin' => 'гармин', 'insta360' => 'инста360', 'gopro' => 'гопро', 'dji' => 'дджи',
    'turbo' => 'турбо', 'edge' => 'эдж', 'fold' => 'фолд', 'flip' => 'флип', 'gt' => 'гт', 'se' => 'се', 'fe' => 'фе', 'ai' => 'аи'];
$letter = ['a' => 'а', 'c' => 'с', 'e' => 'е', 'k' => 'к', 'm' => 'м', 'o' => 'о', 'p' => 'р', 't' => 'т', 'x' => 'х', 'f' => 'ф', 'l' => 'л',
    's' => 'с', 'g' => 'г', 'd' => 'д', 'z' => 'з', 'b' => 'б', 'h' => 'н', 'y' => 'у', 'i' => 'и', 'r' => 'р', 'v' => 'в', 'n' => 'н', 'u' => 'у', 'w' => 'в', 'j' => 'ж', 'q' => 'к'];
function to_ru($s, $ru, $letter)
{
    $out = [];
    foreach (explode(' ', $s) as $w) {
        if (isset($ru[$w])) { $out[] = $ru[$w]; continue; }
        // буква + цифры («x8», «f7», «s25», «17t») – русскими буквами, как набирают с русской раскладкой
        if (preg_match('/^([a-z]{1,2})(\d+)([a-z]?)$/', $w, $m) && !in_array($m[1] . $m[3], ['gb', 'tb', 'mm', 'w', 'mah'], true)) {
            $out[] = strtr($m[1], $letter) . $m[2] . strtr($m[3], $letter); continue;
        }
        if (preg_match('/^(\d+)([a-z])$/', $w, $m) && !in_array($m[2], ['g', 'w'], true)) { $out[] = $m[1] . strtr($m[2], $letter); continue; }
        $out[] = $w;
    }
    return implode(' ', $out);
}

$tests = [];
$seen_cat = [];
// 1) Разделы
foreach ($cats as $id => $c) {
    if ((int) $c['products'] < 3 || preg_match('/архив|уценка|подарочн|сервисный|ремонт|замена|бу$|идеальное/ui', $c['name'])) { continue; }
    $q = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($c['name'], 'UTF-8')));
    if (isset($seen_cat[$q])) { continue; } // одинаковые названия («iPhone 17 Pro Max» у смартфонов, чехлов, стёкол) – один запрос
    $seen_cat[$q] = 1;
    $same = [];
    foreach ($cats as $id2 => $c2) { if (mb_strtolower($c2['name'], 'UTF-8') === mb_strtolower($c['name'], 'UTF-8')) { $same[] = (string) $id2; } }
    $tests[] = ['kind' => 'cat', 'q' => $q, 'cat' => $id, 'same' => $same];
}
// 2) Модели: латинская часть названия устройства до памяти / цвета («Redmi Note 15 Pro 4G»)
$stop = '/^(\d+\/\d+|\d+(gb|tb|mb)|black|white|blue|green|gray|grey|silver|gold|pink|purple|red|yellow|orange|titanium|global|eu|ru|cn|\(.*)$/i';
$fam = [];
foreach ($prod as $id => $p) {
    if ((int) $p['is_acc'] || preg_match('/(^|,)(8|9|10)(,|$)/', (string) $p['mods'])) { continue; }
    if (!preg_match('/[A-Za-z]/', $p['name'])) { continue; }
    $toks = preg_split('/\s+/u', trim(preg_replace('/^[^A-Za-z0-9]*/u', '', preg_replace('/^[\p{Cyrillic}\s\-]+/u', '', $p['name']))));
    $f = [];
    foreach ($toks as $t) {
        if (preg_match($stop, $t) || preg_match('/[\p{Cyrillic}]/u', $t)) { break; }
        $f[] = mb_strtolower($t, 'UTF-8');
        if (count($f) >= 5) { break; }
    }
    if (count($f) < 2) { continue; }
    $key2 = implode(' ', $f);
    if (!isset($fam[$key2])) { $fam[$key2] = ['ids' => [], 'stock' => 0, 'cat' => (int) $p['main_cat']]; }
    $fam[$key2]['ids'][] = $id;
    $fam[$key2]['stock'] += (int) $p['in_stock'];
}
foreach ($fam as $f => $x) {
    if (!$x['stock']) { continue; }
    $tests[] = ['kind' => 'model', 'q' => $f, 'ids' => $x['ids']];
    $rq = to_ru($f, $ru, $letter);
    if ($rq !== $f) { $tests[] = ['kind' => 'model_ru', 'q' => $rq, 'ids' => $x['ids'], 'latin' => $f]; }
}
// 3) Тип + бренд: первое слово раздела + бренд (первое латинское слово названия), где таких товаров ≥ 2 в наличии
$pair = [];
foreach ($prod as $id => $p) {
    if (!(int) $p['in_stock'] || !isset($cats[(int) $p['main_cat']]) || (int) $p['is_acc']) { continue; }
    if (!preg_match('/\b([A-Za-z][A-Za-z0-9]+)\b/u', $p['name'], $m)) { continue; }
    $brand = mb_strtolower($m[1], 'UTF-8');
    $type = explode(' ', (string) $cats[(int) $p['main_cat']]['lem'])[0];
    if ($type === '' || preg_match('/^[a-z0-9]/', $type)) { continue; }
    $k = $type . '|' . $brand . '|' . $p['main_cat'];
    $pair[$k] = (isset($pair[$k]) ? $pair[$k] : 0) + 1;
}
foreach ($pair as $k => $n) {
    if ($n < 2) { continue; }
    list($type, $brand, $cat) = explode('|', $k);
    $tests[] = ['kind' => 'type', 'q' => $type . ' ' . $brand, 'cat' => (int) $cat, 'brand' => $brand];
    if (isset($ru[$brand])) { $tests[] = ['kind' => 'type_ru', 'q' => $type . ' ' . $ru[$brand], 'cat' => (int) $cat, 'brand' => $brand]; }
}

function dg_search($q, $key)
{
    $url = 'https://sort.diginetica.net/search?st=' . rawurlencode($q) . '&apiKey=' . $key
        . '&strategy=advanced_xname,zero_queries&fullData=true&withCorrection=true&withFacets=false&regionId=global&size=5&offset=0'
        . '&showUnavailable=true&unavailableMultiplier=0.2&sort=DEFAULT';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $d = json_decode((string) curl_exec($ch), true);
    curl_close($ch);
    if (!is_array($d)) { return null; }
    return array_map(function ($p) { return (int) $p['id']; }, array_slice((array) (isset($d['products']) ? $d['products'] : []), 0, 5));
}
function judge($t, $ids, $prod)
{
    if (!$ids) { return 0; }
    if ($t['kind'] === 'cat') {
        $in = 0;
        // товар раздела – если раздел среди любых его разделов (основной у товара может быть другим)
        $inc = function ($id) use ($prod, $t) { return isset($prod[$id]) && array_intersect(isset($t['same']) ? $t['same'] : [(string) $t['cat']], explode(',', (string) $prod[$id]['cat_ids'])); };
        foreach ($ids as $id) { if ($inc($id)) { $in++; } }
        return $inc($ids[0]) ? 1 : 0; // первый товар – из раздела (дальше могут идти б/у и другие товары в наличии)
    }
    if ($t['kind'] === 'model' || $t['kind'] === 'model_ru') {
        return array_intersect(array_slice($ids, 0, 3), $t['ids']) ? 1 : 0;
    }
    $p = isset($prod[$ids[0]]) ? $prod[$ids[0]] : null;
    return $p && in_array((string) $t['cat'], explode(',', (string) $p['cat_ids']), true) && stripos($p['name'], $t['brand']) !== false ? 1 : 0;
}

$res = [];
foreach ($tests as $t) {
    $f = mm_search($mc, $t['q'], 5);
    $ours = array_map(function ($x) { return (int) $x['id']; }, $f['items']);
    $dg = $use_dg && $key !== '' ? dg_search($t['q'], $key) : null;
    if ($use_dg) { usleep(120000); }
    $t['ours'] = judge($t, $ours, $prod);
    $t['dg'] = $dg === null ? null : judge($t, $dg, $prod);
    $t['mode'] = $f['mode'];
    $t['ours_top'] = isset($ours[0], $prod[$ours[0]]) ? $prod[$ours[0]]['name'] : '';
    $t['dg_top'] = $dg && isset($prod[$dg[0]]) ? $prod[$dg[0]]['name'] : '';
    unset($t['ids']);
    $res[] = $t;
}
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
