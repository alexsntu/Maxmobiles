<?php
/**
 * Maxmobiles (company_id = 1): сборка поискового индекса Manticore `mm_products` из базы CS-Cart (этап 1 своего поиска,
 * 2026-10-16). Только чтение базы магазина; пишет только в Manticore (127.0.0.1:9306). Товары GOODMi и Xione в индекс не
 * попадают: берутся только включённые товары витрины 1, у которых есть включённая категория витрины 1.
 *
 * Поля (вес задаётся в запросе): title – название; brand – бренд; model – модель, серия, тип устройства, память, цвет;
 * category – названия категорий с родителями; extra – артикул, дополнительное название (ab__emd_alternative_name),
 * короткое имя, название с отделёнными числами («256Gb» → «256 gb»), чтобы «256» находило «256Gb».
 * Атрибуты: цена, остаток, наличие, спрос за 90 дней (demand.json), популярность (product_popularity.total – как сортировка каталога «по популярности»), признак аксессуара, категории, адрес.
 *
 * php mm-search-index.php            – пересобрать индекс целиком (новая таблица mm_products_new → переименование)
 */
error_reporting(E_ERROR);
chdir('/var/www/maxmobiles/data/www/maxmobiles.ru');
define('BOOTSTRAP', 1); define('DIR_ROOT', getcwd()); define('PRODUCT_STATUS', '');
$config = []; @include 'config.local.php';
$db = new mysqli($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name']);
$db->set_charset('utf8');
$p = $config['table_prefix'];
$mc = new mysqli('127.0.0.1', '', '', '', 9306);
$mc->set_charset('utf8mb4');
const COMPANY = 1;
require '/var/www/maxmobiles/data/www/maxmobiles.ru/app/mm_search/lib.php';

function rows($db, $sql) { $r = $db->query($sql); if (!$r) { throw new Exception($db->error . ' :: ' . substr($sql, 0, 200)); } $o = []; while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; }
function mq($mc, $sql) { if (!$mc->query($sql)) { throw new Exception('manticore: ' . $mc->error . ' :: ' . substr($sql, 0, 200)); } }
function esc($mc, $s) { return "'" . $mc->real_escape_string((string) $s) . "'"; }
/**
 * Начальные формы слов (лемматизатор Manticore той же таблицы, что ищет): «Фонарики» → [фонарик], «Ножей» → [нож].
 * Нужны для правил «раздел запроса первым» и «тип товара в названии» (2026-10-16, перенесено с GOODMi по просьбе пользователя).
 */
function lemmas($mc, $text, $table)
{
    $out = [];
    $t = trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower(str_replace('ё', 'е', (string) $text), 'UTF-8')));
    if ($t === '') { return $out; }
    $r = $mc->query("CALL KEYWORDS('" . $mc->real_escape_string($t) . "', '$table')");
    while ($r && ($x = $r->fetch_assoc())) { if ($x['normalized'] !== '') { $out[] = ltrim($x['normalized'], '='); } }
    return $out;
}
function split_num($s) { return trim(preg_replace('/\s+/u', ' ', preg_replace(['/(\d)([^\d\s.,])/u', '/([^\d\s.,])(\d)/u'], '$1 $2', (string) $s))); }

$t0 = microtime(true);

/**
 * Признаки из названия для сортировки (коды – в mm_search_mods() поискового скрипта): 1 pro, 2 max, 3 plus, 4 mini,
 * 5 ultra, 6 air, 7 se, 8 «Идеальное БУ», 9 запчасть (левый/правый наушник, отдельный зарядный кейс), 10 услуга ремонта.
 */
function mm_index_mods($title)
{
    $t = ' ' . mb_strtolower(str_replace('ё', 'е', (string) $title), 'UTF-8') . ' ';
    $map = [1 => '/\bpro\b/u', 2 => '/\bmax\b/u', 3 => '/\bplus\b|\+/u', 4 => '/\bmini\b/u', 5 => '/\bultra\b/u', 6 => '/\bair\b/u',
        7 => '/\bse\b/u', 8 => '/идеальное бу|\bб\/?у\b/u', 9 => '/правый|левый|зарядный кейс|кейс для беспроводных/u'];
    $out = [];
    foreach ($map as $code => $re) { if (preg_match($re, $t)) { $out[] = $code; } }
    return $out;
}

// Категории витрины 1: названия и цепочки родителей
$cats = [];
foreach (rows($db, "SELECT c.category_id, c.id_path, c.status, d.category FROM {$p}categories c JOIN {$p}category_descriptions d ON d.category_id = c.category_id AND d.lang_code = 'ru' WHERE c.company_id = " . COMPANY) as $c) {
    $cats[(int) $c['category_id']] = $c;
}
$cat_text = function ($cid) use ($cats) {
    if (!isset($cats[$cid])) { return null; }
    $names = [];
    foreach (explode('/', $cats[$cid]['id_path']) as $id) {
        if (!isset($cats[(int) $id]) || $cats[(int) $id]['status'] !== 'A') { return null; } // скрытый родитель – категория не видна
        $names[] = $cats[(int) $id]['category'];
    }
    return $names;
};

// Категории аксессуаров: по названию любой из цепочки (чехлы, стекла, кабели, ремешки…)
// 2026-10-16: и «Аксессуары для iPad / MacBook …» (клавиатуры, стилусы) – как на GOODMi
$acc_re = '/чехл|чехол|стекл|плёнк|пленк|кабел|ремеш|ремни|адаптер|переходник|держател|подставк|защит|сумк|рюкзак|зарядн|блок питания|кейс|аксессуары для/ui';

$products = rows($db, "SELECT p.product_id, p.product_code, p.amount, p.timestamp, d.product, d.ab__emd_alternative_name alt, d.ab__spt_short_name spt
    FROM {$p}products p JOIN {$p}product_descriptions d ON d.product_id = p.product_id AND d.lang_code = 'ru'
    WHERE p.company_id = " . COMPANY . " AND p.status = 'A'");
$ids = array_map(function ($x) { return (int) $x['product_id']; }, $products);
$in = implode(',', $ids ?: [0]);

$links = [];
foreach (rows($db, "SELECT product_id, category_id, link_type FROM {$p}products_categories WHERE product_id IN ($in)") as $l) {
    $links[(int) $l['product_id']][] = $l;
}
$prices = [];
foreach (rows($db, "SELECT product_id, MIN(price) price FROM {$p}product_prices WHERE product_id IN ($in) AND lower_limit = 1 AND usergroup_id = 0 GROUP BY product_id") as $x) {
    $prices[(int) $x['product_id']] = (float) $x['price'];
}
$pop = [];
foreach (rows($db, "SELECT product_id, total FROM {$p}product_popularity WHERE product_id IN ($in)") as $x) {
    // Та же «популярность», по которой каталог сортирует «по популярности»: CS-Cart копит 3×просмотр + 5×в корзину
    // − 5×из корзины + 10×покупка (POPULARITY_* в config.php) – решение пользователя 2026-10-16
    $pop[(int) $x['product_id']] = max(0, (int) $x['total']);
}
// Главное фото: первая уже нарезанная сайтом миниатюра 100–180 пикселей, иначе исходное
$img = [];
$root = '/var/www/maxmobiles/data/www/maxmobiles.ru/images/';
foreach (rows($db, "SELECT l.object_id, i.image_path, l.detailed_id FROM {$p}images_links l JOIN {$p}images i ON i.image_id = l.detailed_id
    WHERE l.object_type = 'product' AND l.type = 'M' AND l.object_id IN ($in)") as $x) {
    $rel = 'detailed/' . floor((int) $x['detailed_id'] / 1000) . '/' . $x['image_path'];
    $url = '';
    foreach (['100/100', '125/125', '150/150', '180/180', '400/400'] as $size) {
        if (is_file($root . 'thumbnails/' . $size . '/' . $rel)) { $url = '/images/thumbnails/' . $size . '/' . $rel; break; }
    }
    if ($url === '' && is_file($root . $rel)) { $url = '/images/' . $rel; }
    $img[(int) $x['object_id']] = $url;
}

// Спрос за 90 дней (продажи МойСклад, последний месяц вдвое) – собирает mm-search-demand.php раз в сутки
$demand = [];
foreach ((array) json_decode((string) @file_get_contents('/root/mm-search/demand.json'), true) as $pid => $d) {
    $demand[(int) $pid] = (int) round(isset($d['w']) ? $d['w'] : 0);
}
$seo = [];
foreach (rows($db, "SELECT object_id, name FROM {$p}seo_names WHERE type = 'p' AND company_id = " . COMPANY . " AND lang_code = 'ru' AND object_id IN ($in)") as $x) {
    $seo[(int) $x['object_id']] = $x['name'];
}
// Характеристики, полезные для поиска
$feat = [];
$feat_names = ['Бренд' => 'brand', 'Модель' => 'model', 'Серия' => 'model', 'Тип устройства' => 'model', 'Тип' => 'model', 'Объем встроенной памяти' => 'model', 'Цвет' => 'model'];
foreach (rows($db, "SELECT v.product_id, fd.description fname, COALESCE(NULLIF(vd.variant, ''), v.value) val
    FROM {$p}product_features_values v
    JOIN {$p}product_features_descriptions fd ON fd.feature_id = v.feature_id AND fd.lang_code = 'ru'
    LEFT JOIN {$p}product_feature_variant_descriptions vd ON vd.variant_id = v.variant_id AND vd.lang_code = 'ru'
    WHERE v.lang_code = 'ru' AND v.product_id IN ($in) AND TRIM(fd.description) IN ('" . implode("','", array_keys($feat_names)) . "')") as $x) {
    if (trim((string) $x['val']) === '') { continue; }
    $feat[(int) $x['product_id']][$feat_names[trim($x['fname'])]][] = trim($x['val']);
}

mq($mc, 'DROP TABLE IF EXISTS mm_products_new');
mq($mc, "CREATE TABLE mm_products_new (
    title text, brand text, model text, category text, extra text,
    name string, url string, price float, amount int, in_stock bool, popularity bigint, is_acc bool,
    cat_ids multi, main_cat int, added timestamp, mods multi, demand int, img string, head multi
) morphology = 'lemmatize_ru,stem_en' min_infix_len = '2' index_exact_words = '1' expand_keywords = '0'");

$batch = []; $n = 0; $skipped = 0; $sugg_items = [];
foreach ($products as $x) {
    $id = (int) $x['product_id'];
    $cat_names = []; $cat_ids = []; $main = 0;
    foreach (isset($links[$id]) ? $links[$id] : [] as $l) {
        $names = $cat_text((int) $l['category_id']);
        if ($names === null) { continue; } // категория чужой витрины или скрыта
        $cat_ids[] = (int) $l['category_id'];
        $cat_names = array_merge($cat_names, $names);
        if ($l['link_type'] === 'M' || !$main) { $main = (int) $l['category_id']; }
    }
    if (!$cat_ids) { $skipped++; continue; } // товар не виден ни в одной категории витрины
    $cat_names = array_values(array_unique($cat_names));
    $f = isset($feat[$id]) ? $feat[$id] : [];
    $brand = implode(' ', array_unique(isset($f['brand']) ? $f['brand'] : []));
    $model = implode(' ', array_unique(isset($f['model']) ? $f['model'] : []));
    // Аксессуар – по самой вложенной (основной) категории: «Аксессуары → Наушники» аксессуаром не считается
    $main_names = $cat_text($main);
    $is_acc = $main_names && preg_match($acc_re, end($main_names)) ? 1 : 0;
    $extra = implode(' ', array_filter([$x['product_code'], $x['alt'], $x['spt'], split_num($x['product'])]));
    $mods = mm_index_mods($x['product']);
    // Услуга ремонта – всё из раздела «Сервисный центр» (названия услуг слишком разные, чтобы узнавать их по словам)
    if (preg_match('/сервисный центр|^ремонт/ui', implode('|', $cat_names))) { $mods[] = 10; }
    $url = isset($seo[$id]) ? 'https://maxmobiles.ru/' . $seo[$id] . '/' : 'https://maxmobiles.ru/index.php?dispatch=products.view&product_id=' . $id;
    // «Тип товара» – начальные формы первых двух слов названия (crc32): «Чехол для iPhone 17» → чехол, для;
    // «Apple iPhone 17» → apple, iphone (по запросу «iphone 17» телефон выше чехла)
    $head = [];
    // Слово «Apple» не считаем: у «Apple iPhone Air» тип товара – iphone, air, у «Наушники Apple AirPods Pro 3» – наушник, airpods
    // (иначе по «air» iPhone Air уступал MacBook Air, а по «airpods» AirPods Pro 3 – AirPods 5)
    // До трёх первых слов (без «Apple»), но не дальше предлога: «Чехол для iPhone 17» → чехол; «Игровая консоль Sony PlayStation 5» → игровой, консоль, sony
    $hw = [];
    foreach (array_slice(preg_split('/[\s\-]+/u', trim(preg_replace('/(^|\s)apple(?=\s)/iu', ' ', trim($x['product'])))), 0, 3) as $w) {
        if (in_array(mb_strtolower($w, 'UTF-8'), ['для', 'с', 'со', 'на', 'под', 'в', 'к', 'от', 'из', 'и'], true)) { break; }
        $hw[] = $w;
    }
    foreach (array_slice(lemmas($mc, split_num(implode(' ', $hw)), 'mm_products_new'), 0, 5) as $lw) { $head[] = crc32($lw); }
    $batch[] = sprintf('(%d,%s,%s,%s,%s,%s,%s,%s,%F,%d,%d,%d,%d,(%s),%d,%d,(%s),%d,%s,(%s))', $id,
        // разделы – и с числами отдельно («iPhone 17e» → … 17 e), как делится запрос
        esc($mc, $x['product']), esc($mc, $brand), esc($mc, $model), esc($mc, implode(' ', $cat_names) . ' ' . split_num(implode(' ', $cat_names))), esc($mc, $extra),
        esc($mc, $x['product']), esc($mc, $url), isset($prices[$id]) ? $prices[$id] : 0, (int) $x['amount'], (int) $x['amount'] > 0 ? 1 : 0,
        isset($pop[$id]) ? $pop[$id] : 0, $is_acc, implode(',', array_unique($cat_ids)), $main, (int) $x['timestamp'], implode(',', $mods ?: [0]), isset($demand[$id]) ? $demand[$id] : 0, esc($mc, isset($img[$id]) ? $img[$id] : ''), implode(',', array_unique($head) ?: [0]));
    $sugg_items[] = ['title' => $x['product'], 'model' => isset($f['model']) ? $f['model'] : [], 'demand' => isset($demand[$id]) ? $demand[$id] : 0,
        'pop' => isset($pop[$id]) ? $pop[$id] : 0, 'stock' => (int) $x['amount'] > 0, 'service' => in_array(10, $mods, true)];
    if (count($batch) >= 200) {
        mq($mc, 'INSERT INTO mm_products_new (id,title,brand,model,category,extra,name,url,price,amount,in_stock,popularity,is_acc,cat_ids,main_cat,added,mods,demand,img,head) VALUES ' . implode(',', $batch));
        $n += count($batch); $batch = [];
    }
}
if ($batch) {
    mq($mc, 'INSERT INTO mm_products_new (id,title,brand,model,category,extra,name,url,price,amount,in_stock,popularity,is_acc,cat_ids,main_cat,added,mods,demand,img,head) VALUES ' . implode(',', $batch));
    $n += count($batch);
}
// Подмена без простоя: старая таблица удаляется только после того, как новая собрана
mq($mc, 'DROP TABLE IF EXISTS mm_products');
mq($mc, 'ALTER TABLE mm_products_new RENAME mm_products');
// Категории для подсказок: название, путь, адрес, сколько товаров (заполняется по индексу товаров)
$cat_count = [];
$r = $mc->query('SELECT main_cat, COUNT(*) c FROM mm_products GROUP BY main_cat LIMIT 2000');
while ($r && ($y = $r->fetch_row())) { $cat_count[(int) $y[0]] = (int) $y[1]; }
$cseo = [];
foreach (rows($db, "SELECT object_id, name FROM {$p}seo_names WHERE type = 'c' AND company_id = " . COMPANY . " AND lang_code = 'ru'") as $y) { $cseo[(int) $y['object_id']] = $y['name']; }
mq($mc, 'DROP TABLE IF EXISTS mm_categories');
// Картинки категорий (для плиток «Найдено в категориях» на странице результатов) – как у товаров: готовая миниатюра или исходник
$cimg = [];
foreach (rows($db, "SELECT l.object_id, i.image_path, l.detailed_id FROM {$p}images_links l JOIN {$p}images i ON i.image_id = l.detailed_id
    WHERE l.object_type = 'category' AND l.type = 'M' AND l.object_id IN (" . implode(',', array_keys($cat_count) ?: [0]) . ")") as $y) {
    $rel = 'detailed/' . floor((int) $y['detailed_id'] / 1000) . '/' . $y['image_path'];
    $u = '';
    foreach (['100/100', '120/120', '125/125', '150/150', '180/180', '200/200', '400/400'] as $size) {
        if (is_file($root . 'thumbnails/' . $size . '/' . $rel)) { $u = '/images/thumbnails/' . $size . '/' . $rel; break; }
    }
    if ($u === '' && is_file($root . $rel)) { $u = '/images/' . $rel; }
    $cimg[(int) $y['object_id']] = $u;
}
mq($mc, "CREATE TABLE mm_categories (title text, name string, path string, url string, products int, img string, lem string) morphology = 'lemmatize_ru,stem_en' min_infix_len = '2'");
$rows_c = [];
foreach ($cat_count as $cid => $cnt) {
    $names = $cat_text($cid);
    if (!$names) { continue; }
    // Адрес категории на Maxmobiles – цепочка имён всех разделов: /aksessuary-ru/chekhly-i-kejsy/…/chehly-dlya-iphone-15-pro/
    $chain = [];
    foreach (explode('/', $cats[$cid]['id_path']) as $pid) { $chain[] = isset($cseo[(int) $pid]) ? $cseo[(int) $pid] : null; }
    $url = !in_array(null, $chain, true) ? 'https://maxmobiles.ru/' . implode('/', $chain) . '/' : 'https://maxmobiles.ru/index.php?dispatch=categories.view&category_id=' . $cid;
    // Начальные формы слов названия раздела («Чехлы для iPhone 17» → чехол для iphone 17) – для правила «раздел запроса первым»
    $lem = implode(' ', lemmas($mc, split_num(end($names)), 'mm_products'));
    $rows_c[] = sprintf('(%d,%s,%s,%s,%s,%d,%s,%s)', $cid, esc($mc, implode(' ', $names)), esc($mc, end($names)), esc($mc, implode(' › ', $names)), esc($mc, $url), $cnt, esc($mc, isset($cimg[$cid]) ? $cimg[$cid] : ''), esc($mc, $lem));
}
if ($rows_c) { mq($mc, 'INSERT INTO mm_categories (id,title,name,path,url,products,img,lem) VALUES ' . implode(',', $rows_c)); }

// «Часто ищут»: до появления своего журнала запросов – частые запросы покупателей из журналов сайта (queries.txt)
mq($mc, 'DROP TABLE IF EXISTS mm_queries');
mq($mc, "CREATE TABLE mm_queries (q text, query string, freq int) min_prefix_len = '1' dict = 'keywords'");
$rows_q = []; $qid = 0;
foreach ((array) @file('/root/mm-search/queries.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) < 2 || mb_strlen(trim($parts[1])) < 2) { continue; }
    $rows_q[] = sprintf('(%d,%s,%s,%d)', ++$qid, esc($mc, $parts[1]), esc($mc, $parts[1]), (int) $parts[0]);
}
if ($rows_q) { mq($mc, 'INSERT INTO mm_queries (id,q,query,freq) VALUES ' . implode(',', $rows_q)); }

// Словарь подсказок: начала названий товаров (от первого слова и от первого латинского – «чехол для iphone 17», «iphone 17 pro»),
// модели, категории и реальные запросы. Фраза берётся, если встречается хотя бы у двух товаров, или это модель, или запрос.
// Вес – спрос за 90 дней, популярность и наличие товаров с этой фразой; запросы покупателей весят по частоте.
$stop_end = ['для', 'с', 'и', 'в', 'на', 'из', 'без', 'по', 'от', 'к', '-', '+', '/', 'со'];
$cand = [];
$add = function ($phrase, $w, $force = false) use (&$cand, $stop_end) {
    $phrase = mm_search_display_norm($phrase);
    $words = $phrase === '' ? [] : explode(' ', $phrase);
    if (!$words || count($words) > 6 || mb_strlen($phrase) < 2 || in_array(end($words), $stop_end, true)) { return; }
    if (!isset($cand[$phrase])) { $cand[$phrase] = ['w' => 0.0, 'n' => 0, 'force' => false]; }
    $cand[$phrase]['w'] += $w; $cand[$phrase]['n']++;
    if ($force) { $cand[$phrase]['force'] = true; }
};
foreach ($sugg_items as $it) {
    if ($it['service']) { continue; } // услуги ремонта в подсказки не берём
    $w = 1 + 3 * log(1 + $it['demand']) + 0.3 * log(1 + $it['pop']) + ($it['stock'] ? 1 : 0);
    $tokens = explode(' ', mm_search_display_norm(preg_replace('/\(.*$/u', '', $it['title']))); // без скобок с уточнениями
    $starts = [0];
    $latin = 0;
    foreach ($tokens as $i => $t) { if ($i > 0 && preg_match('/^[a-z]/', $t)) { $starts[] = $i; if (++$latin >= 2) { break; } } }
    foreach (array_unique($starts) as $st) {
        $seen = [];
        for ($len = 1; $len <= 6 && $st + $len <= count($tokens); $len++) {
            $ph = implode(' ', array_slice($tokens, $st, $len));
            if (!isset($seen[$ph])) { $seen[$ph] = 1; $add($ph, $w); }
        }
    }
    foreach ($it['model'] as $m) { if (preg_match('/[a-zа-я]/iu', $m) && mb_strlen($m) <= 40) { $add($m, $w, true); } }
}
foreach ($cat_count as $cid => $cnt) { $names = $cat_text($cid); if ($names) { $add(end($names), 2 + log(1 + $cnt), true); } }
foreach ((array) @file('/root/mm-search/queries.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $parts = explode("\t", $line, 2);
    // Запрос покупателя – лучшая подсказка (так подсказывают Amazon и Ozon): весит заметно больше фраз из названий
    if (count($parts) === 2) { $add($parts[1], 25 * log(1 + (int) $parts[0]), true); }
}
mq($mc, 'DROP TABLE IF EXISTS mm_suggest');
mq($mc, "CREATE TABLE mm_suggest (q text, phrase string, weight float, products int) min_prefix_len = '1' dict = 'keywords'");
$rows_s = []; $sid = 0;
foreach ($cand as $ph => $c) {
    if ($c['n'] < 2 && !$c['force']) { continue; }
    if (preg_match('/^\d+$/', $ph)) { continue; } // одно число – не подсказка
    $rows_s[] = sprintf('(%d,%s,%s,%F,%d)', ++$sid, esc($mc, $ph), esc($mc, $ph), $c['w'], $c['n']);
    if (count($rows_s) >= 500) { mq($mc, 'INSERT INTO mm_suggest (id,q,phrase,weight,products) VALUES ' . implode(',', $rows_s)); $rows_s = []; }
}
if ($rows_s) { mq($mc, 'INSERT INTO mm_suggest (id,q,phrase,weight,products) VALUES ' . implode(',', $rows_s)); }

// Статус для админки (mm_search.index): история запусков в Manticore, таблица не пересоздаётся
mq($mc, "CREATE TABLE IF NOT EXISTS mm_status (kind string, ts timestamp, seconds float, items int, note text stored)");
mq($mc, sprintf("INSERT INTO mm_status (id, kind, ts, seconds, items, note) VALUES (%d, 'index', %d, %F, %d, %s)", (int) (microtime(true) * 1000), time(), microtime(true) - $t0, $n,
    esc($mc, sprintf('товаров %d, пропущено без категории %d, категорий %d, подсказок %d, частых запросов %d', $n, $skipped, count($rows_c), $sid, count($rows_q)))));

printf("Готово: в индексе %d товаров, пропущено без видимой категории %d, категорий %d, частых запросов %d, подсказок %d, за %.1f с\n", $n, $skipped, count($rows_c), count($rows_q), $sid, microtime(true) - $t0);
