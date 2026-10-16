<?php
/**
 * Maxmobiles: статистика своего поиска для админки (dispatch=mm_search.*, 2026-10-16). Только чтение таблиц
 * cscart_mm_search_log / _clicks / _events (их пишут products.pre.php, mm-search.php?ev=1 и хуки корзины и заказа модуля
 * my_changes). Работает в CS-Cart (db_get_*).
 *
 * «Один поиск» – пара «браузер (sid) + запрос после обработки (qn)» за период: обновления страницы, листание и смена
 * сортировки не размножают поиск, а клик, корзина и заказ честно относятся к этой паре.
 */

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/** Периоды отчётов: дней → подпись. */
function mm_stats_periods()
{
    return [1 => 'Сегодня', 7 => '7 дней', 30 => '30 дней', 90 => '90 дней', 180 => '180 дней'];
}

function mm_stats_from($days)
{
    return (int) $days <= 1 ? strtotime('today') : time() - (int) $days * 86400;
}

function mm_stats_pct($a, $b)
{
    return $b > 0 ? round(100 * $a / $b, 1) : 0;
}

/** Главные показатели за период. */
function mm_stats_overview($from)
{
    $o = [];
    $o['pages'] = (int) db_get_field('SELECT COUNT(*) FROM ?:mm_search_log WHERE ts >= ?i', $from);
    $pairs = db_get_row(
        'SELECT COUNT(DISTINCT sid, qn) searches, COUNT(DISTINCT sid) sessions, COUNT(DISTINCT qn) queries,'
        . ' AVG(ms) ms FROM ?:mm_search_log WHERE ts >= ?i', $from
    );
    $o['searches'] = (int) $pairs['searches'];
    $o['sessions'] = (int) $pairs['sessions'];
    $o['queries'] = (int) $pairs['queries'];
    $o['ms'] = round((float) $pairs['ms'], 1);
    // Нулевые показы: поиски, где ничего не нашлось (на первой странице)
    $o['zero'] = (int) db_get_field('SELECT COUNT(DISTINCT sid, qn) FROM ?:mm_search_log WHERE ts >= ?i AND page = 1 AND total = 0', $from);
    // Поиски с кликом по товару в результатах
    $o['clicked'] = (int) db_get_field(
        "SELECT COUNT(DISTINCT l.sid, l.qn) FROM ?:mm_search_clicks c JOIN ?:mm_search_log l ON l.id = c.search_id"
        . " WHERE c.source = 'results' AND c.ts >= ?i", $from
    );
    $with_results = $o['searches'] - $o['zero'];
    // Нулевые клики: нашли, показали, но не кликнули
    $o['noclick'] = max(0, $with_results - $o['clicked']);
    $o['ctr'] = mm_stats_pct($o['clicked'], $with_results);
    $o['zero_pct'] = mm_stats_pct($o['zero'], $o['searches']);
    $o['noclick_pct'] = mm_stats_pct($o['noclick'], $with_results);
    $o['pos'] = round((float) db_get_field("SELECT AVG(pos) FROM ?:mm_search_clicks WHERE source = 'results' AND pos > 0 AND ts >= ?i", $from), 1);
    $o['first_pos'] = mm_stats_pct(
        (int) db_get_field("SELECT COUNT(*) FROM ?:mm_search_clicks WHERE source = 'results' AND pos BETWEEN 1 AND 3 AND ts >= ?i", $from),
        (int) db_get_field("SELECT COUNT(*) FROM ?:mm_search_clicks WHERE source = 'results' AND pos > 0 AND ts >= ?i", $from)
    );
    // Корзина и заказы после поиска
    $o['cart'] = (int) db_get_field("SELECT COUNT(DISTINCT e.sid, l.qn) FROM ?:mm_search_events e JOIN ?:mm_search_log l ON l.id = e.search_id WHERE e.type = 'cart' AND e.ts >= ?i", $from);
    $o['orders'] = (int) db_get_field("SELECT COUNT(DISTINCT order_id) FROM ?:mm_search_events WHERE type = 'order' AND ts >= ?i", $from);
    $o['revenue'] = (float) db_get_field("SELECT SUM(qty * price) FROM ?:mm_search_events WHERE type = 'order' AND ts >= ?i", $from);
    // Купили именно тот товар, по которому кликнули в результатах поиска
    $o['bought_clicked'] = (int) db_get_field(
        "SELECT COUNT(DISTINCT e.order_id, e.product_id) FROM ?:mm_search_events e"
        . " JOIN ?:mm_search_clicks c ON c.sid = e.sid AND c.product_id = e.product_id AND c.source IN ('results', 'sg_product') AND c.ts <= e.ts AND c.ts > e.ts - 86400"
        . " WHERE e.type = 'order' AND e.ts >= ?i", $from
    );
    $o['revenue_fmt'] = number_format($o['revenue'], 0, ',', ' ');
    $o['cart_pct'] = mm_stats_pct($o['cart'], $o['searches']);
    $o['order_pct'] = mm_stats_pct($o['orders'], $o['searches']);
    // Как нашлось и с какого устройства
    $o['modes'] = db_get_hash_single_array('SELECT mode, COUNT(DISTINCT sid, qn) n FROM ?:mm_search_log WHERE ts >= ?i GROUP BY mode', ['mode', 'n'], $from);
    $o['devices'] = db_get_hash_single_array('SELECT device, COUNT(DISTINCT sid, qn) n FROM ?:mm_search_log WHERE ts >= ?i GROUP BY device', ['device', 'n'], $from);
    // Подсказки: что выбирают и сколько бросают ввод
    $o['suggest'] = db_get_hash_single_array("SELECT source, COUNT(*) n FROM ?:mm_search_clicks WHERE ts >= ?i AND source <> 'results' GROUP BY source", ['source', 'n'], $from);
    // По дням
    $o['days'] = db_get_array(
        "SELECT FROM_UNIXTIME(ts, '%Y-%m-%d') d, COUNT(DISTINCT sid, qn) searches, COUNT(DISTINCT CASE WHEN page = 1 AND total = 0 THEN CONCAT(sid, qn) END) zero"
        . ' FROM ?:mm_search_log WHERE ts >= ?i GROUP BY d ORDER BY d DESC LIMIT 31', $from
    );
    $clicked_days = db_get_hash_single_array(
        "SELECT FROM_UNIXTIME(l.ts, '%Y-%m-%d') d, COUNT(DISTINCT l.sid, l.qn) n FROM ?:mm_search_clicks c JOIN ?:mm_search_log l ON l.id = c.search_id"
        . " WHERE c.source = 'results' AND l.ts >= ?i GROUP BY d", ['d', 'n'], $from
    );
    foreach ($o['days'] as &$d) {
        $d['clicked'] = isset($clicked_days[$d['d']]) ? (int) $clicked_days[$d['d']] : 0;
        $d['ctr'] = mm_stats_pct($d['clicked'], (int) $d['searches'] - (int) $d['zero']);
        $d['zero_pct'] = mm_stats_pct((int) $d['zero'], (int) $d['searches']);
    }
    unset($d);

    return $o;
}

/**
 * Запросы за период: сколько раз искали, сколько нашлось, клики (CTR – доля поисков с кликом), среднее место клика,
 * корзины, заказы, выручка. $kind: all – все; zero – ничего не нашлось; noclick – нашлось, но без кликов.
 */
function mm_stats_queries($from, $kind = 'all', $sort = 'searches', $filter = '', $limit = 200)
{
    $rows = db_get_hash_array(
        'SELECT qn, MAX(q) q, COUNT(DISTINCT sid) searches, MAX(total) total, MIN(total) total_min, MAX(ts) last_ts,'
        . ' SUBSTRING_INDEX(GROUP_CONCAT(mode ORDER BY id DESC), \',\', 1) mode'
        . ' FROM ?:mm_search_log WHERE ts >= ?i AND page = 1 ?p GROUP BY qn',
        'qn', $from, $filter !== '' ? db_quote('AND qn LIKE ?l', '%' . $filter . '%') : ''
    );
    if (!$rows) {
        return [];
    }
    $clicks = db_get_hash_array(
        "SELECT l.qn, COUNT(DISTINCT l.sid) clicked, COUNT(*) clicks, AVG(NULLIF(c.pos, 0)) pos FROM ?:mm_search_clicks c"
        . " JOIN ?:mm_search_log l ON l.id = c.search_id WHERE c.source = 'results' AND c.ts >= ?i GROUP BY l.qn", 'qn', $from
    );
    $events = db_get_hash_array(
        "SELECT l.qn, COUNT(DISTINCT CASE WHEN e.type = 'cart' THEN e.sid END) carts, COUNT(DISTINCT CASE WHEN e.type = 'order' THEN e.order_id END) orders,"
        . " SUM(CASE WHEN e.type = 'order' THEN e.qty * e.price ELSE 0 END) revenue FROM ?:mm_search_events e JOIN ?:mm_search_log l ON l.id = e.search_id"
        . ' WHERE e.ts >= ?i GROUP BY l.qn', 'qn', $from
    );
    $out = [];
    foreach ($rows as $qn => $r) {
        $c = isset($clicks[$qn]) ? $clicks[$qn] : ['clicked' => 0, 'clicks' => 0, 'pos' => null];
        $e = isset($events[$qn]) ? $events[$qn] : ['carts' => 0, 'orders' => 0, 'revenue' => 0];
        $r['clicked'] = (int) $c['clicked'];
        $r['clicks'] = (int) $c['clicks'];
        $r['pos'] = $c['pos'] !== null ? round((float) $c['pos'], 1) : '';
        $r['ctr'] = (int) $r['total'] > 0 ? mm_stats_pct($r['clicked'], (int) $r['searches']) : 0;
        $r['carts'] = (int) $e['carts'];
        $r['orders'] = (int) $e['orders'];
        $r['revenue'] = (float) $e['revenue'];
        $r['last'] = date('d.m H:i', (int) $r['last_ts']);
        $r['revenue_fmt'] = $r['revenue'] ? number_format($r['revenue'], 0, ',', ' ') . ' ₽' : '';
        if ($kind === 'zero' && (int) $r['total'] > 0) { continue; }
        if ($kind === 'noclick' && ((int) $r['total'] === 0 || $r['clicked'] > 0)) { continue; }
        $out[] = $r;
    }
    $keys = ['searches' => 'searches', 'ctr' => 'ctr', 'total' => 'total', 'orders' => 'orders', 'revenue' => 'revenue', 'last' => 'last_ts', 'pos' => 'pos'];
    $k = isset($keys[$sort]) ? $keys[$sort] : 'searches';
    usort($out, function ($a, $b) use ($k) { return $b[$k] <=> $a[$k] ?: $b['searches'] <=> $a['searches']; });

    return array_slice($out, 0, (int) $limit);
}

/** Подсказки: что выбирают (варианты, товары, история) и брошенный ввод – по набранному тексту. */
function mm_stats_suggest($from, $limit = 100)
{
    return [
        'chosen' => db_get_array(
            "SELECT source, q, COUNT(*) n, COUNT(DISTINCT sid) people, ROUND(AVG(pos), 1) pos FROM ?:mm_search_clicks"
            . " WHERE ts >= ?i AND source IN ('sg_query', 'sg_history', 'sg_scope', 'sg_product', 'sg_all', 'sg_chip', 'chip') GROUP BY source, q ORDER BY n DESC LIMIT ?i", $from, (int) $limit
        ),
        'abandoned' => db_get_array(
            "SELECT LOWER(typed) typed, SUM(source = 'sg_none') empty, SUM(source = 'sg_exit') ignored, COUNT(DISTINCT sid) people, MAX(ts) last_ts FROM ?:mm_search_clicks"
            . " WHERE ts >= ?i AND source IN ('sg_exit', 'sg_none') GROUP BY LOWER(typed) ORDER BY people DESC, last_ts DESC LIMIT ?i", $from, (int) $limit
        ),
    ];
}

/** Заказы после поиска: что искали, по чему кликнули, что купили (купили ли кликнутый товар). */
function mm_stats_orders($from, $limit = 200)
{
    $rows = db_get_array(
        "SELECT e.order_id, e.product_id, e.qty, e.price, e.ts, l.q, d.product name,"
        . " (SELECT COUNT(*) FROM ?:mm_search_clicks c WHERE c.sid = e.sid AND c.product_id = e.product_id AND c.source IN ('results', 'sg_product') AND c.ts <= e.ts AND c.ts > e.ts - 86400) clicked"
        . " FROM ?:mm_search_events e LEFT JOIN ?:mm_search_log l ON l.id = e.search_id"
        . " LEFT JOIN ?:product_descriptions d ON d.product_id = e.product_id AND d.lang_code = 'ru'"
        . " WHERE e.type = 'order' AND e.ts >= ?i ORDER BY e.id DESC LIMIT ?i", $from, (int) $limit
    );
    foreach ($rows as &$r) { $r['when'] = date('d.m H:i', (int) $r['ts']); $r['price_fmt'] = number_format((float) $r['price'], 0, ',', ' '); }
    unset($r);

    return $rows;
}

/** Состояние индекса: что в индексе и когда собирался (таблица mm_status пишется скриптами /root/mm-search). */
function mm_stats_index($mc)
{
    $o = ['ok' => false];
    if (!$mc) {
        return $o;
    }
    $o['ok'] = true;
    $one = function ($sql) use ($mc) { $r = $mc->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x : []; };
    $x = $one('SELECT COUNT(*), SUM(in_stock), SUM(is_acc), SUM(demand > 0) FROM mm_products');
    $o['products'] = (int) ($x[0] ?? 0); $o['in_stock'] = (int) ($x[1] ?? 0); $o['acc'] = (int) ($x[2] ?? 0); $o['with_demand'] = (int) ($x[3] ?? 0);
    $o['no_img'] = (int) ($one("SELECT COUNT(*) FROM mm_products WHERE img = ''")[0] ?? 0);
    $o['services'] = (int) ($one('SELECT COUNT(*) FROM mm_products WHERE ANY(mods) = 10')[0] ?? 0);
    $o['used'] = (int) ($one('SELECT COUNT(*) FROM mm_products WHERE ANY(mods) = 8')[0] ?? 0);
    $o['suggest'] = (int) ($one('SELECT COUNT(*) FROM mm_suggest')[0] ?? 0);
    $o['categories'] = (int) ($one('SELECT COUNT(*) FROM mm_categories')[0] ?? 0);
    $o['runs'] = [];
    $r = $mc->query('SELECT id, kind, ts, seconds, items, note FROM mm_status ORDER BY ts DESC LIMIT 20');
    while ($r && ($y = $r->fetch_assoc())) { $y['when'] = date('d.m.Y H:i', (int) $y['ts']); $y['sec'] = number_format((float) $y['seconds'], 1, ',', ''); $o['runs'][] = $y; }

    return $o;
}

/**
 * Синонимы и закрепления из админки. После каждого изменения – выгрузка в app/mm_search/custom.json, его читает поиск
 * (mm_search_custom в lib.php): изменения действуют сразу, без пересборки индекса.
 */
function mm_stats_export_custom()
{
    $syn = db_get_hash_single_array('SELECT src, dst FROM ?:mm_search_synonyms WHERE active = 1', ['src', 'dst']);
    $pins = [];
    foreach (db_get_array('SELECT q, product_id FROM ?:mm_search_pins WHERE active = 1 ORDER BY q, position, id') as $p) {
        $pins[$p['q']][] = (int) $p['product_id'];
    }
    $file = __DIR__ . '/custom.json';
    $ok = @file_put_contents($file . '.tmp', json_encode(['synonyms' => $syn, 'pins' => $pins, 'updated' => time()], JSON_UNESCAPED_UNICODE)) !== false
        && @rename($file . '.tmp', $file);

    return $ok;
}

/** Свои синонимы: поиск по «что пишут» / «как в каталоге» / заметке и страницы по $per строк. */
function mm_stats_synonyms($find = '', $page = 1, $per = 25)
{
    $find = trim((string) $find);
    $where = $find !== '' ? db_quote('WHERE src LIKE ?l OR dst LIKE ?l OR note LIKE ?l', '%' . $find . '%', '%' . $find . '%', '%' . $find . '%') : '';
    $total = (int) db_get_field('SELECT COUNT(*) FROM ?:mm_search_synonyms ?p', $where);
    $pages = max(1, (int) ceil($total / $per));
    $page = max(1, min($pages, (int) $page));
    $rows = db_get_array('SELECT * FROM ?:mm_search_synonyms ?p ORDER BY active DESC, src LIMIT ?i, ?i', $where, ($page - 1) * $per, $per);
    foreach ($rows as &$r) { $r['when'] = $r['created'] ? date('d.m.Y', (int) $r['created']) : ''; }
    unset($r);

    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'find' => $find];
}

function mm_stats_synonym_save($src, $dst, $note = '')
{
    $src = mb_substr(mm_search_normalize($src), 0, 80);
    $dst = mb_substr(mm_search_display_norm($dst), 0, 120);
    if (mb_strlen($src) < 2 || $dst === '') {
        return ['ok' => false, 'message' => 'Укажите, что пишут покупатели (от 2 символов), и как это называется в каталоге.'];
    }
    if ($src === $dst) {
        return ['ok' => false, 'message' => 'Синоним совпадает с исходным словом.'];
    }
    db_query('REPLACE INTO ?:mm_search_synonyms ?e', ['src' => $src, 'dst' => $dst, 'active' => 1, 'created' => TIME, 'note' => mb_substr(trim((string) $note), 0, 120)]);

    return ['ok' => mm_stats_export_custom(), 'message' => "Синоним «{$src}» → «{$dst}» сохранён и уже работает в поиске."];
}

function mm_stats_synonym_toggle($id, $active)
{
    db_query('UPDATE ?:mm_search_synonyms SET active = ?i WHERE id = ?i', $active ? 1 : 0, (int) $id);
    mm_stats_export_custom();
}

function mm_stats_synonym_delete($id)
{
    db_query('DELETE FROM ?:mm_search_synonyms WHERE id = ?i', (int) $id);
    mm_stats_export_custom();
}

function mm_stats_pins()
{
    $rows = db_get_array(
        "SELECT p.*, d.product name FROM ?:mm_search_pins p LEFT JOIN ?:product_descriptions d ON d.product_id = p.product_id AND d.lang_code = 'ru'"
        . ' ORDER BY p.q, p.position, p.id'
    );
    foreach ($rows as &$r) { $r['when'] = $r['created'] ? date('d.m.Y', (int) $r['created']) : ''; }
    unset($r);

    return $rows;
}

function mm_stats_pin_add($q, $product_id)
{
    $qn = mb_substr(mm_search_normalize($q), 0, 120);
    $product_id = (int) $product_id;
    if ($qn === '' || !$product_id || !db_get_field('SELECT 1 FROM ?:products WHERE product_id = ?i AND company_id = 1', $product_id)) {
        return ['ok' => false, 'message' => 'Нужны запрос и товар Maxmobiles.'];
    }
    $pos = 1 + (int) db_get_field('SELECT MAX(position) FROM ?:mm_search_pins WHERE q = ?s', $qn);
    db_query('INSERT IGNORE INTO ?:mm_search_pins ?e', ['q' => $qn, 'product_id' => $product_id, 'position' => $pos, 'active' => 1, 'created' => TIME]);

    return ['ok' => mm_stats_export_custom(), 'message' => "Товар закреплён по запросу «{$qn}» и уже стоит в выдаче первым (после закреплённых раньше)."];
}

function mm_stats_pin_delete($id)
{
    db_query('DELETE FROM ?:mm_search_pins WHERE id = ?i', (int) $id);
    mm_stats_export_custom();
}

function mm_stats_pin_move($id, $dir)
{
    $p = db_get_row('SELECT * FROM ?:mm_search_pins WHERE id = ?i', (int) $id);
    if (!$p) { return; }
    $other = db_get_row('SELECT * FROM ?:mm_search_pins WHERE q = ?s AND position ' . ($dir < 0 ? '<' : '>') . ' ?i ORDER BY position ' . ($dir < 0 ? 'DESC' : 'ASC') . ' LIMIT 1', $p['q'], $p['position']);
    if ($other) {
        db_query('UPDATE ?:mm_search_pins SET position = ?i WHERE id = ?i', $other['position'], $p['id']);
        db_query('UPDATE ?:mm_search_pins SET position = ?i WHERE id = ?i', $p['position'], $other['id']);
        mm_stats_export_custom();
    }
}
