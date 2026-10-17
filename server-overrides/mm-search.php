<?php
/**
 * Maxmobiles: подсказки своего поиска (с 2026-10-16). Отдельный лёгкий файл без загрузки CS-Cart, чтобы отвечать за
 * миллисекунды: читает только индекс Manticore (127.0.0.1:9306) через app/mm_search/lib.php. Работает только на
 * maxmobiles.ru, на других доменах – 404.
 *
 * /mm-search.php?q=iph        – JSON для выпадающих подсказок (варианты запроса, «в категории», до 4 товаров, сколько всего);
 *                               пустой q – «Часто ищут»; &cats=1 – категории найденного для плиток на странице результатов
 * /mm-search.php?ev=1 (POST JSON) – события для статистики: клики, выбор в подсказках, плитки, брошенный ввод
 * С 2026-10-16 наш поиск у всех, Diginetica на Maxmobiles отключена; ?test= / ?diginetica= только стирают старые куки.
 */
$host = strtolower(preg_replace('/:\d+$/', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
if ($host !== 'maxmobiles.ru' && $host !== 'www.maxmobiles.ru') {
    http_response_code(404);
    exit;
}

// Скрипт и стили подсказок на время настройки отдаются отсюда без кэша (правки видны сразу, без очистки кэша CS-Cart);
// при включении для всех переедут в общий файл скриптов и «Свой CSS» Maxmobiles
if (isset($_GET['asset'])) {
    $files = ['js' => [__DIR__ . '/js/addons/my_changes/mm_search.js', 'application/javascript'], 'css' => [__DIR__ . '/app/mm_search/suggest.css', 'text/css']];
    if (!isset($files[$_GET['asset']]) || !is_file($files[$_GET['asset']][0])) { http_response_code(404); exit; }
    header('Content-Type: ' . $files[$_GET['asset']][1] . '; charset=utf-8');
    header('Cache-Control: no-store');
    readfile($files[$_GET['asset']][0]);
    exit;
}

// Прежние переключатели (?test=…, ?diginetica=…) больше ничего не включают: убираем их куки и ведём на главную
if (isset($_GET['test']) || isset($_GET['diginetica'])) {
    foreach (['mm_search_test', 'mm_diginetica'] as $c) {
        setcookie($c, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'samesite' => 'Lax']);
    }
    header('Cache-Control: no-store');
    header('Location: /', true, 302);
    exit;
}

// События из браузера для статистики и обучения поиска (cscart_mm_search_clicks): клики по товарам в результатах,
// выбор в подсказках, плитки категорий, брошенный ввод. Без IP и личных данных – только метка браузера mm_sid.
if (isset($_GET['ev']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Cache-Control: no-store');
    $sid = isset($_COOKIE['mm_sid']) && preg_match('/^[a-f0-9]{16}$/', (string) $_COOKIE['mm_sid']) ? (string) $_COOKIE['mm_sid'] : '';
    $e = json_decode((string) file_get_contents('php://input', false, null, 0, 4096), true);
    $sources = ['results', 'sg_query', 'sg_history', 'sg_scope', 'sg_product', 'sg_all', 'sg_chip', 'tile', 'chip', 'sg_exit', 'sg_none'];
    if ($sid === '' || !is_array($e) || !isset($e['source']) || !in_array($e['source'], $sources, true)) { http_response_code(204); exit; }
    define('BOOTSTRAP', 1);
    define('DIR_ROOT', __DIR__);
    // config.local.php включает E_ALL и ждёт PRODUCT_STATUS и $config['dir'] – без них каждое событие писало Notice в журнал
    define('PRODUCT_STATUS', '');
    $config = ['dir' => ['root' => __DIR__, 'var' => __DIR__ . '/var/', 'cache_misc' => __DIR__ . '/var/cache/misc/']];
    @include __DIR__ . '/config.local.php';
    error_reporting(E_ERROR);
    $db = @new mysqli($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name']);
    if ($db->connect_errno) { http_response_code(204); exit; }
    $db->set_charset('utf8mb4');
    $p = $config['table_prefix'];
    $cut = function ($v, $n) { return mb_substr(trim((string) $v), 0, $n); };
    $q = $cut(isset($e['q']) ? $e['q'] : '', 120);
    $pid = isset($e['product_id']) ? max(0, (int) $e['product_id']) : 0;
    $pos = isset($e['pos']) ? max(0, min(65535, (int) $e['pos'])) : 0;
    $search_id = 0;
    // Клик в результатах: к последнему поиску этого браузера с тем же запросом за полчаса; место – по сохранённому порядку показа
    if ($e['source'] === 'results' && $q !== '') {
        require_once __DIR__ . '/app/mm_search/lib.php';
        $st = $db->prepare("SELECT id, shown, page FROM {$p}mm_search_log WHERE sid = ? AND qn = ? AND ts > ? ORDER BY id DESC LIMIT 1");
        $qn = mm_search_normalize($q); $since = time() - 1800;
        $st->bind_param('ssi', $sid, $qn, $since); $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row) {
            $search_id = (int) $row['id'];
            $idx = array_search((string) $pid, explode(',', (string) $row['shown']), true);
            if ($idx !== false) { $pos = ($idx + 1) + (max(1, (int) $row['page']) - 1) * max(1, isset($e['per_page']) ? (int) $e['per_page'] : 16); }
        }
    }
    // Не больше 300 событий с одного браузера в час – защита таблицы от мусора
    $st = $db->prepare("SELECT COUNT(*) FROM {$p}mm_search_clicks WHERE sid = ? AND ts > ?");
    $since = time() - 3600; $st->bind_param('si', $sid, $since); $st->execute();
    if ((int) $st->get_result()->fetch_row()[0] < 300) {
        $st = $db->prepare("INSERT INTO {$p}mm_search_clicks (ts, sid, search_id, source, q, typed, product_id, pos, device) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ts = time(); $typed = $cut(isset($e['typed']) ? $e['typed'] : '', 120); $src = $e['source'];
        $dev = isset($e['device']) && $e['device'] === 'm' ? 'm' : 'd';
        $st->bind_param('isisssiis', $ts, $sid, $search_id, $src, $q, $typed, $pid, $pos, $dev);
        $st->execute();
    }
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$q = isset($_GET['q']) ? (string) $_GET['q'] : '';
if (mb_strlen($q) > 100) {
    echo '{"queries":[],"scopes":[],"products":[],"total":0}';
    exit;
}
require __DIR__ . '/app/mm_search/lib.php';
// Индекс не отвечает – не держим обработчик PHP: 1 с на соединение, 3 с на ответ (2026-10-17: Manticore завис на 14 ч)
$mc = mysqli_init();
$mc->options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);
$mc->options(MYSQLI_OPT_READ_TIMEOUT, 3);
@$mc->real_connect('127.0.0.1', '', '', '', 9306);
if ($mc->connect_errno) {
    http_response_code(503);
    echo '{"error":"search unavailable"}';
    exit;
}
$mc->set_charset('utf8mb4');
// Плашки-уточнения для страницы результатов
if (isset($_GET['chips'])) {
    echo json_encode(['base' => mm_search_display_norm($q), 'chips' => mm_search_chips($mc, $q, 8)], JSON_UNESCAPED_UNICODE);
    exit;
}
// Категории найденного – для плиток на странице результатов
if (isset($_GET['cats'])) {
    echo json_encode(mm_search_found_categories($mc, $q), JSON_UNESCAPED_UNICODE);
    exit;
}
// Пустое поле – «Часто ищут»
echo json_encode(trim($q) === '' ? mm_search_popular($mc) : mm_search_suggest($mc, $q), JSON_UNESCAPED_UNICODE);
