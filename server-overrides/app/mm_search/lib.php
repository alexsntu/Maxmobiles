<?php
/**
 * Maxmobiles: свой поиск по индексу Manticore `mm_products` (с 2026-10-16) – разбор запроса, синонимы, сортировка, подсказки.
 * Без зависимостей от CS-Cart: нужен только mysqli к 127.0.0.1:9306. Индекс собирают скрипты /root/mm-search/ (исходники –
 * _DEV/search/ репозитория), подсказки отдаёт /mm-search.php. Только витрина Maxmobiles; GOODMi своим поиском не пользуется.
 *
 * Порядок поиска: 1) все слова запроса (с синонимами и морфологией); 2) если пусто – нечёткий поиск Manticore (опечатки
 * и неверная раскладка ru/us); 3) если пусто – достаточно 60% слов запроса.
 * Сортировка: совпадение (название весит больше всего, слова подряд и в начале названия – выше) × наличие × аксессуар
 * (аксессуары ниже, если в запросе не просили аксессуар) × модификации и б/у × спрос за 90 дней (продажи МойСклад, до ×2,2) × накопленная популярность CS-Cart (до +30%).
 */

/**
 * Настройки поиска из админки (синонимы и закрепления): app/mm_search/custom.json, его пишет админка (mm_search.synonyms,
 * mm_search.pins) из таблиц cscart_mm_search_synonyms / _pins – поиск подхватывает изменения сразу, без пересборки индекса.
 */
function mm_search_custom()
{
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/custom.json';
        $c = is_file($f) ? (array) json_decode((string) file_get_contents($f), true) : [];
    }

    return $c;
}

/** Синонимы: что пишут покупатели → как это называется в каталоге. Ключи – в нижнем регистре, «ё» → «е». */
function mm_search_synonyms()
{
    static $all = null;
    if ($all !== null) { return $all; }
    $all = [
        'айфон' => 'iphone', 'айфоны' => 'iphone', 'ифон' => 'iphone',
        'эпл' => 'apple', 'эппл' => 'apple', 'эпел' => 'apple', 'апл' => 'apple', 'аппл' => 'apple', 'эпол' => 'apple',
        'воч' => 'watch', 'вотч' => 'watch', 'вач' => 'watch', 'часы apple' => 'apple watch', 'эпл вотч' => 'apple watch',
        'аирподс' => 'airpods', 'эйрподс' => 'airpods', 'аирподсы' => 'airpods', 'эирподс' => 'airpods', 'airpods' => 'airpods',
        'эирпод' => 'airpods', 'аирпод' => 'airpods', 'эйрпод' => 'airpods', 'эирподсы' => 'airpods', 'эйрподсы' => 'airpods',
        'аирпотс' => 'airpods', 'эирпотс' => 'airpods', 'airpod' => 'airpods', 'аирподы' => 'airpods', 'эирподы' => 'airpods',
        'макбук' => 'macbook', 'макбуки' => 'macbook', 'айпад' => 'ipad', 'айпэд' => 'ipad', 'аймак' => 'imac', 'мак мини' => 'mac mini',
        'джбл' => 'jbl', 'жбл' => 'jbl', 'самсунг' => 'samsung', 'сяоми' => 'xiaomi', 'ксиоми' => 'xiaomi', 'редми' => 'redmi',
        'пиксель' => 'pixel', 'гугл' => 'google', 'дайсон' => 'dyson', 'сони' => 'sony', 'хуавей' => 'huawei', 'хонор' => 'honor',
        'плейстейшн' => 'playstation', 'плейстейшен' => 'playstation', 'плэйстейшн' => 'playstation', 'пс5' => 'playstation 5',
        'ps5' => 'playstation 5', 'ps 5' => 'playstation 5', 'пс 5' => 'playstation 5', 'пс' => 'playstation', 'иксбокс' => 'xbox', 'хбокс' => 'xbox', 'нинтендо' => 'nintendo',
        'зарядка' => 'зарядное', 'зарядки' => 'зарядное', 'зарядку' => 'зарядное', 'наушники' => 'наушники', 'беспроводные наушники' => 'наушники',
        'про' => 'pro', 'макс' => 'max', 'мини' => 'mini', 'плюс' => 'plus', 'ультра' => 'ultra', 'эир' => 'air', 'эйр' => 'air',
        'гб' => 'gb', 'тб' => 'tb',
        'галакси' => 'galaxy', 'гэлакси' => 'galaxy', 'джибиэль' => 'jbl', 'джейбиэль' => 'jbl', 'маршал' => 'marshall', 'маршалл' => 'marshall',
        'хуавэй' => 'huawei', 'хуавей' => 'huawei', 'ксяоми' => 'xiaomi', 'поко' => 'poco', 'реалми' => 'realme', 'нотинг' => 'nothing',
        'бозе' => 'bose', 'боус' => 'bose', 'харман' => 'harman', 'кардон' => 'kardon', 'яндекс станция' => 'яндекс станция',
        'алиса' => 'яндекс станция', 'макбук эир' => 'macbook air', 'макбук про' => 'macbook pro', 'айпад про' => 'ipad pro', 'айпад эир' => 'ipad air',
        'пенсил' => 'pencil', 'эпл пенсил' => 'apple pencil', 'магсейф' => 'magsafe', 'эйрпод' => 'airpods',
        // 2026-10-16 (проверка по каталогу, как на GOODMi): русские написания слов из названий моделей
        'сериес' => 'series', 'сириес' => 'series', 'серия' => 'series', 'се' => 'se', 'нео' => 'neo', 'смарт' => 'smart', 'дисплей' => 'display',
        'студио' => 'studio', 'битс' => 'beats', 'нот' => 'note', 'ноут' => 'note', 'ми' => 'mi', 'кью' => 'q', 'актив' => 'active', 'эдж' => 'edge',
        'лайт' => 'lite', 'вакуум' => 'vacuum', 'бокс' => 'box', 'стик' => 'stick', 'турбо' => 'turbo', 'фолд' => 'fold', 'флип' => 'flip',
        'плей' => 'play', 'плэй' => 'play', 'свитч' => 'switch', 'дуалсенс' => 'dualsense', 'гармин' => 'garmin', 'гопро' => 'gopro',
    ];
    // Синонимы из админки (mm_search.synonyms → custom.json): добавляются к стартовому списку, при совпадении – главнее
    $custom = mm_search_custom();
    foreach (isset($custom['synonyms']) ? (array) $custom['synonyms'] : [] as $k => $v) {
        $k = mm_search_normalize($k);
        $v = mm_search_display_norm($v);
        if ($k !== '' && $v !== '') { $all[$k] = $v; }
    }

    return $all;
}

/** Слова-аксессуары: если их нет в запросе, аксессуары опускаются ниже устройств. */
function mm_search_acc_words()
{
    return ['чехол', 'чехлы', 'стекло', 'пленка', 'кабель', 'провод', 'ремешок', 'адаптер', 'переходник', 'держатель',
        'зарядное', 'зарядка', 'блок', 'кейс', 'сумка', 'защитное', 'case', 'cable', 'charger', 'strap', 'band', 'magsafe'];
}

/** Признаки товара из названия (индекс: mm_index_mods): слова запроса, которые их «разрешают». */
function mm_search_mods()
{
    return [1 => ['pro'], 2 => ['max'], 3 => ['plus', '+'], 4 => ['mini'], 5 => ['ultra'], 6 => ['air'], 7 => ['se'],
        8 => ['бу', 'б/у', 'идеальное', 'used'], 9 => ['правый', 'левый', 'кейс', 'запасной'],
        10 => ['замена', 'ремонт', 'устранение', 'диагностика', 'чистка', 'восстановление', 'прошивка', 'разблокировка', 'сервис', 'починить', 'поменять']];
}

/** Кириллица → латиница («эирпод» → «eirpod»): для названий брендов и моделей, набранных по-русски. */
function mm_search_translit($s)
{
    static $map = ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya'];
    return strtr($s, $map);
}

/**
 * Русские слова, которых нет ни в одном товаре (по статистике индекса), – латиницей: «наушники эирпод 5» → «наушники
 * eirpod 5»; дальше нечёткий поиск находит airpods. Известные слова («наушники») не трогаем. Без замен – ''.
 */
function mm_search_fix_unknown($mc, $norm)
{
    if (!preg_match('/[а-я]/u', $norm)) { return ''; }
    $r = $mc->query('CALL KEYWORDS(' . mm_search_q($mc, $norm) . ", 'mm_products', 1 AS stats)");
    $docs = [];
    while ($r && ($x = $r->fetch_assoc())) { $docs[$x['tokenized']] = (int) $x['docs']; }
    $changed = false; $out = [];
    foreach (explode(' ', $norm) as $t) {
        if (preg_match('/[а-я]/u', $t) && isset($docs[$t]) && $docs[$t] === 0) { $out[] = mm_search_translit($t); $changed = true; }
        else { $out[] = $t; }
    }
    return $changed ? implode(' ', $out) : '';
}

function mm_search_normalize($q)
{
    $q = mb_strtolower(trim((string) $q), 'UTF-8');
    $q = str_replace('ё', 'е', $q);
    $q = preg_replace('/[^\p{L}\p{N}\s.+\-]/u', ' ', $q);
    // Модели, набранные русскими буквами-двойниками латиницы: «а16» → «a16», «м4» → «m4» (в каталоге – латиница)
    // 2026-10-16: и буквами, которые звучат как латинские («ф7» → f7, «л10» → l10, «ж152» → j152) – только вплотную к цифрам
    $q = preg_replace_callback('/(?<!\p{L})[авекмнорстухiфлгдзпибж]{1,2}\d+[авекмнорстухфлгдзпибж]?(?!\p{L})|(?<!\p{L})\d+[авекмнорстухфлгдзпибж]{1,2}(?!\p{L})/u', function ($m) {
        return strtr($m[0], ['а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x',
            'ф' => 'f', 'л' => 'l', 'г' => 'g', 'д' => 'd', 'з' => 'z', 'п' => 'p', 'и' => 'i', 'б' => 'b', 'ж' => 'j']);
    }, $q);
    $q = preg_replace(['/(\d)([^\d\s.,])/u', '/([^\d\s.,])(\d)/u'], '$1 $2', $q); // «256гб» → «256 гб», «ps5» → «ps 5»
    return trim(preg_replace('/\s+/u', ' ', $q));
}

/** Запрос → выражение MATCH: каждое слово (или фраза из синонимов) – группа «(как ввели | как в каталоге)». */
function mm_search_match_expr($norm, &$words_out = null)
{
    $syn = mm_search_synonyms();
    $tokens = $norm === '' ? [] : explode(' ', $norm);
    // Предлоги не обязательны («чехол на 18», «стекло для iphone»): их почти нет в названиях товаров
    // «про» сюда не входит – это синоним «pro»
    $stop = ['на', 'для', 'в', 'во', 'с', 'со', 'и', 'к', 'по', 'от', 'из', 'под', 'без'];
    if (count($tokens) > 1) {
        $kept = array_values(array_filter($tokens, function ($t) use ($stop) { return !in_array($t, $stop, true); }));
        if ($kept) { $tokens = $kept; }
    }
    $groups = []; $words = [];
    $amb = ['c' => 's', 'b' => 'v', 'h' => 'n', 'p' => 'r', 'y' => 'u'];
    for ($i = 0; $i < count($tokens); $i++) {
        $hit = null;
        foreach ([3, 2, 1] as $len) { // сначала самые длинные фразы
            if ($i + $len > count($tokens)) { continue; }
            $phrase = implode(' ', array_slice($tokens, $i, $len));
            if (isset($syn[$phrase])) { $hit = [$phrase, $syn[$phrase], $len]; break; }
        }
        if ($hit) {
            $alts = array_unique([$hit[0], $hit[1]]);
            $groups[] = '(' . implode(' | ', array_map(function ($a) { return strpos($a, ' ') !== false ? '"' . mm_search_escape($a) . '"' : mm_search_escape($a); }, $alts)) . ')';
            $words = array_merge($words, explode(' ', $hit[1]));
            $i += $hit[2] - 1;
        } elseif (preg_match('/^[a-z]{1,2}$/', $tokens[$i]) && preg_match('/[cbhpy]/', $tokens[$i])
            && (isset($tokens[$i + 1]) && preg_match('/^\d+$/', $tokens[$i + 1]) || $i > 0 && preg_match('/^\d+$/', $tokens[$i - 1]))) {
            // Буквы модели рядом с номером, набранные русскими двойниками: «с» – C или S, «в» – B или V («вр2» – VR2),
            // «н» – H или N, «р» – P или R, «у» – Y или U
            $alts = [''];
            foreach (str_split($tokens[$i]) as $ch) {
                $opts = isset($amb[$ch]) ? [$ch, $amb[$ch]] : [$ch];
                $next = [];
                foreach ($alts as $a) { foreach ($opts as $o) { $next[] = $a . $o; } }
                $alts = array_slice($next, 0, 4);
            }
            $groups[] = '(' . implode(' | ', $alts) . ')';
            $words[] = $tokens[$i];
        } else {
            $groups[] = mm_search_escape($tokens[$i]);
            $words[] = $tokens[$i];
        }
    }
    $words_out = $words;
    return implode(' ', $groups);
}

/** Начальные формы слов запроса тем же лемматизатором, что и индекс («чехлы» → чехол, «наушников» → наушник). */
function mm_search_lemmas($mc, $text)
{
    $out = [];
    $t = trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', (string) $text));
    if ($t === '') { return $out; }
    $r = $mc->query('CALL KEYWORDS(' . mm_search_q($mc, $t) . ", 'mm_products')");
    while ($r && ($x = $r->fetch_assoc())) { if ($x['normalized'] !== '') { $out[] = ltrim($x['normalized'], '='); } }
    return $out;
}

/**
 * Правила «раздел запроса первым» и «тип товара в названии» (2026-10-16; сделаны для GOODMi по просьбе пользователя «на фонарик
 * надо в первую очередь выводить фонарики, а не зонты с фонариками, и т. п.», перенесены на Maxmobiles). Главное слово запроса –
 * первое русское слово (иначе первое слово): «чехол iphone 17» → чехол, «iphone 17» → iphone; к нему – его синоним («айфон» →
 * iphone). Раздел подходит, если запрос содержит все слова его названия (кроме общих: «и», «для», «аксессуары»…) и название
 * раздела начинается с главного слова: «iphone 17» → «iPhone 17», «чехол iphone 17» → «Чехлы для iPhone 17». Возвращает
 * ['cats' => id разделов, 'head' => crc32 начальных форм главного слова] – для сортировки в mm_search_raw.
 */
function mm_search_intent($mc, array $tokens, array $words)
{
    static $cats = null;
    $syn = mm_search_synonyms();
    if ($cats === null) {
        $cats = [];
        $r = $mc->query('SELECT id, lem, products FROM mm_categories WHERE products >= 2 LIMIT 2000');
        while ($r && ($x = $r->fetch_assoc())) { $cats[(int) $x['id']] = explode(' ', (string) $x['lem']); }
    }
    // Главное слово: то, с которого начинается название какого-нибудь раздела («юсб кабель» → кабель), иначе первое русское, иначе первое
    $firsts = [];
    foreach ($cats as $lem) { $firsts[$lem[0]] = 1; }
    $head_tok = '';
    foreach ($tokens as $t) {
        // только русские слова: в «стекло iphone 18» главное – стекло, а не iphone (раздел «iPhone …» начинается с iphone)
        if (!preg_match('/[а-я]/u', $t)) { continue; }
        $tl = mm_search_lemmas($mc, $t);
        if ($tl && isset($firsts[$tl[0]])) { $head_tok = $t; break; }
    }
    if ($head_tok === '') { foreach ($tokens as $t) { if (preg_match('/[а-я]/u', $t)) { $head_tok = $t; break; } } }
    // «apple» не главное слово (в «типе товара» индекса его нет): «apple pencil» → pencil, «apple watch» → watch
    if ($head_tok === '') { foreach ($tokens as $t) { if (!preg_match('/^\d+$/', $t) && $t !== 'apple' && $t !== 'эпл') { $head_tok = $t; break; } } }
    if ($head_tok === '') { return ['cats' => [], 'head' => []]; }
    $head = mm_search_lemmas($mc, $head_tok . (isset($syn[$head_tok]) && strpos($syn[$head_tok], ' ') === false ? ' ' . $syn[$head_tok] : ''));
    $q = array_flip(mm_search_lemmas($mc, implode(' ', array_unique(array_merge($tokens, $words)))));
    $generic = ['и', 'для', 'в', 'с', 'на', 'аксессуар', 'принадлежность', 'товар', 'все'];
    $ids = [];
    foreach ($cats as $id => $lem) {
        $need = array_values(array_diff($lem, $generic));
        if (!$need || !in_array($lem[0], $head, true)) { continue; }
        if (!array_diff($need, array_keys($q))) { $ids[] = $id; }
    }
    return ['cats' => $ids, 'head' => array_values(array_unique(array_map('crc32', $head)))];
}

function mm_search_escape($s)
{
    return preg_replace('/([\\\\()|\-!@~"&\/^$=<])/u', '\\\\$1', $s);
}

/**
 * Поиск. Возвращает ['mode' => как нашли, 'total' => сколько всего, 'items' => [['id','name','price','in_stock','is_acc','url','score']]].
 */
function mm_search_raw($mc, $query, $limit = 20, $offset = 0)
{
    $norm = mm_search_normalize($query);
    if ($norm === '') { return ['mode' => 'empty', 'total' => 0, 'items' => []]; }
    $words = [];
    $expr = mm_search_match_expr($norm, $words);
    $wants_acc = (bool) array_intersect($words, mm_search_acc_words());
    $acc_mul = $wants_acc ? '1.0' : '0.2'; // ниже, чем устройство не в наличии (0.4)
    // Модификации (Pro, Max…), «Идеальное БУ» и запчасти, о которых в запросе не просили, – ниже
    $mod_codes = []; $bu = ''; $spare = '';
    foreach (mm_search_mods() as $code => $allow) {
        if (array_intersect($words, $allow)) { continue; }
        if ($code <= 7) { $mod_codes[] = $code; } elseif ($code === 8) { $bu = ' * IF(IN(mods, 8), 0.8, 1.0)'; } elseif ($code === 9) { $spare .= ' * IF(IN(mods, 9), 0.12, 1.0)'; } else { $spare .= ' * IF(IN(mods, 10), 0.15, 1.0)'; }
    }
    $mods = $mod_codes ? ' * IF(IN(mods, ' . implode(',', $mod_codes) . '), 0.75, 1.0)' : '';
    // Для разбора в админке (mm_search_explain): что штрафовалось в этом запросе
    $penalized = ['acc' => !$wants_acc, 'mods' => $mod_codes, 'bu' => $bu !== '', 'spare' => strpos($spare, 'IN(mods, 9)') !== false, 'service' => strpos($spare, 'IN(mods, 10)') !== false];
    // Раздел запроса и тип товара (mm_search_intent): cb – товар из названного раздела, hb – слово запроса в начале названия
    $intent = mm_search_intent($mc, array_values(array_filter(explode(' ', $norm), function ($t) { return !in_array($t, ['на', 'для', 'в', 'во', 'с', 'со', 'и', 'к', 'по', 'от', 'из', 'под', 'без'], true); })), $words);
    // Раздел – любой из разделов товара, не только основной
    $cb = $intent['cats'] ? 'IN(cat_ids, ' . implode(',', $intent['cats']) . ')' : '0';
    $hb = $intent['head'] ? 'IN(head, ' . implode(',', $intent['head']) . ')' : '0';
    // Аксессуары, о которых в запросе не просили, всегда ниже устройств – даже если устройства нет в наличии, а аксессуар есть
    // (решение пользователя 2026-10-16: «по наличию аксессуары всегда проигрывали устройствам»)
    // Услуги ремонта / подарочные сертификаты (mods 10), о которых не просили, – ещё ниже аксессуаров; запчасти (mods 9) – как аксессуары
    // Аксессуар, названный в запросе по имени (главное слово – в начале его названия: «pencil» → «Стилус Apple Pencil», «хаб» → «USB-хаб»),
    // и есть искомый товар – его не опускаем
    $ab_acc = trim(($wants_acc ? '' : "(is_acc = 1 AND $hb = 0)") . (!empty($penalized['spare']) ? ($wants_acc ? '' : ' OR ') . 'IN(mods, 9)' : ''));
    $ab = 'IF(' . (!empty($penalized['service']) ? 'IN(mods, 10)' : '0') . ', 2, IF(' . ($ab_acc !== '' ? $ab_acc : '0') . ', 1, 0))';
    $penalized['cats'] = $intent['cats'];
    // Короткий запрос (до 3 слов: «нож», «фонарик», «стекло iphone 18») – тип товара ступенью сортировки; длинный, где названа
    // конкретная модель («колонка беспроводная harman kardon aura studio 5»), – только прибавкой ×1.3, чтобы точная модель не уступала
    $short = count(array_diff($tokens0 = explode(' ', $norm), ['на', 'для', 'в', 'во', 'с', 'со', 'и', 'к', 'по', 'от', 'из', 'под', 'без'])) <= 3;
    $hb_mul = $short || $hb === '0' ? '' : " * IF($hb, 1.3, 1.0)";
    // Названный тип товара всегда первым, даже без наличия (решение пользователя 2026-10-16: «тип товара всегда первым, как с
    // аксессуарами»): «фен dyson» – фены, а не выпрямитель в наличии. В длинном запросе с моделью тип – только по разделу
    // Ступень «тип товара»: из раздела запроса ИЛИ слово запроса в начале названия (iPhone 15 б/у – тот же тип, что новый iPhone 15).
    // Внутри – наличие, затем слово в начале названия. Manticore сортирует не больше чем по 5 признакам
    $tb = $short ? "IF(($cb) + ($hb) > 0, 1, 0)" : $cb;
    $select = "SELECT id, name, price, in_stock, is_acc, url, img, main_cat, WEIGHT() AS w, mods, demand, popularity, $ab AS ab, $tb AS tb, $cb AS cb, $hb AS hb, WEIGHT() * IF(in_stock = 1, 1.0, 0.4) * IF(is_acc = 1, $acc_mul, 1.0)$mods$bu$spare$hb_mul * (1 + LN(demand + 1) / 4) * (1 + LN(popularity + 1) / 40) AS score FROM mm_products";
    $opts = "OPTION ranker = expr('sum((4*lcs + 3*(min_hit_pos==1) + 2*exact_order)*user_weight)*1000 + bm25'),"
        . " field_weights = (title = 10, brand = 5, model = 4, category = 3, extra = 1), max_matches = 1000";
    // Начало последнего слова («сте» → «стекло»); нечёткий поиск по коротким словам – не больше одной ошибки
    $tokens = explode(' ', $norm);
    $last = end($tokens);
    $prefix = mb_strlen($last) >= 2 && !preg_match('/^\d+$/', $last) ? preg_replace('/' . preg_quote(mm_search_escape($last), '/') . '$/u', mm_search_escape($last) . '*', $expr) : '';
    $min_len = min(array_map('mb_strlen', $tokens));
    $translit = mm_search_fix_unknown($mc, $norm);
    // Неверная раскладка со знаками препинания («rf,tkm» → «кабель»): разбор выкидывает запятые, поэтому меняем раскладку
    // у исходного текста
    $layout_expr = '';
    if (preg_match('/^[a-z\[\];\',.`\s]+$/i', trim((string) $query)) && preg_match('/[\[\];\',.`]/', (string) $query)) {
        $layout_expr = mm_search_match_expr(mm_search_normalize(mm_search_switch_layout(mb_strtolower(trim((string) $query), 'UTF-8'))));
    }
    $distance = $min_len <= 4 ? 1 : 2;
    $tries = [
        ['all', "WHERE MATCH(" . mm_search_q($mc, $expr) . ")", $opts],
        ['prefix', $prefix !== '' && $prefix !== $expr ? "WHERE MATCH(" . mm_search_q($mc, $prefix) . ")" : '', $opts],
        ['fuzzy', "WHERE MATCH(" . mm_search_q($mc, $norm) . ")", $opts . ", fuzzy = 1, distance = $distance, layouts = 'us,ru'"],
        ['translit', $translit !== '' ? "WHERE MATCH(" . mm_search_q($mc, $translit) . ")" : '', $opts . ", fuzzy = 1, distance = 2"],
        ['layout', $layout_expr !== '' ? "WHERE MATCH(" . mm_search_q($mc, $layout_expr) . ")" : '', $opts],
        ['quorum', "WHERE MATCH(" . mm_search_q($mc, '"' . mm_search_escape($norm) . '"/' . max(1, (int) floor(count($words) * 0.6))) . ")", $opts],
    ];
    foreach ($tries as $t) {
        if ($t[1] === '') { continue; }
        // Сначала товары в наличии, потом остальные – везде (решение пользователя 2026-10-16); внутри – товары названного
        // в запросе раздела, затем со словом запроса в начале названия, затем по релевантности
        $sql = "$select {$t[1]} ORDER BY ab ASC, tb DESC, in_stock DESC, " . ($short ? 'hb DESC, ' : '') . "score DESC LIMIT " . (int) $offset . ', ' . (int) $limit . " {$t[2]}";
        $r = $mc->query($sql);
        if (!$r) { continue; }
        $items = [];
        while ($x = $r->fetch_assoc()) { $items[] = $x; }
        if (!$items) { continue; }
        $meta = []; $m = $mc->query('SHOW META');
        while ($m && ($y = $m->fetch_row())) { $meta[$y[0]] = $y[1]; }
        return ['mode' => $t[0], 'total' => isset($meta['total_found']) ? (int) $meta['total_found'] : count($items), 'items' => $items,
            'where' => $t[1], 'opts' => $t[2], 'norm' => $norm, 'expr' => $expr, 'translit' => $translit, 'penalized' => $penalized];
    }
    return ['mode' => 'none', 'total' => 0, 'items' => []];
}

function mm_search_q($mc, $s) { return "'" . $mc->real_escape_string($s) . "'"; }

/** Раскладка: текст, набранный не в той раскладке («ifhgd» ↔ «шарпв»), – в другую. */
function mm_search_switch_layout($s)
{
    $en = str_split("qwertyuiop[]asdfghjkl;'zxcvbnm,.`");
    $ru = preg_split('//u', 'йцукенгшщзхъфывапролджэячсмитьбюё', -1, PREG_SPLIT_NO_EMPTY);
    $map = array_combine($en, $ru) + array_combine($ru, $en);
    $out = '';
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) { $out .= isset($map[$ch]) ? $map[$ch] : $ch; }
    return $out;
}

/** Нижний регистр и пробелы, без разбиения чисел – для показа подсказок («iphone 17 pro max»). */
function mm_search_display_norm($s)
{
    $s = mb_strtolower(str_replace('ё', 'е', trim((string) $s)), 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}\s.+\-\/]/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/** Варианты запроса для подсказок из словаря mm_suggest: сначала начинающиеся с введённого, затем по весу. */
function mm_search_suggest_queries($mc, $q, $limit = 8)
{
    $norm = mm_search_display_norm($q);
    if ($norm === '') { return []; }
    $words = explode(' ', $norm);
    $last = array_pop($words);
    // Синонимы: целые слова – заменой («эпл» → apple), последнее недописанное – ещё и началом синонима («во» → watch*)
    $syn = mm_search_synonyms();
    $parts = [];
    foreach ($words as $w) {
        $parts[] = isset($syn[$w]) && strpos($syn[$w], ' ') === false ? '(' . mm_search_escape($w) . ' | ' . mm_search_escape($syn[$w]) . ')' : mm_search_escape($w);
    }
    $alts = [mm_search_escape($last) . '*'];
    if (mb_strlen($last) >= 2) {
        foreach ($syn as $k => $v) {
            if (strpos($v, ' ') === false && $k !== $v && mb_strpos($k, $last) === 0) { $alts[] = mm_search_escape($v) . '*'; }
        }
    }
    $alts = array_unique($alts);
    $parts[] = count($alts) > 1 ? '(' . implode(' | ', $alts) . ')' : $alts[0];
    $expr = implode(' ', $parts);
    // Для подсветки и «начинается с» – запрос с заменёнными синонимами целых слов
    $norm_syn = implode(' ', array_map(function ($w) use ($syn) { return isset($syn[$w]) ? $syn[$w] : $w; }, $words));
    $r = $mc->query('SELECT phrase, weight FROM mm_suggest WHERE MATCH(' . mm_search_q($mc, trim($expr)) . ') ORDER BY weight DESC LIMIT 60');
    $rows = [];
    while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
    $brand_typed = (bool) preg_match('/^(apple|эпл|эппл)/u', $norm);
    $seen = []; $out = [];
    foreach ($rows as $x) {
        $phrase = $x['phrase'];
        // «apple iphone 17» и «iphone 17» – одна подсказка (Baymard: без повторов), если бренд не ввели сами
        if (!$brand_typed && strpos($phrase, 'apple ') === 0) { $phrase = substr($phrase, 6); }
        if ($phrase === $norm || isset($seen[$phrase])) { continue; }
        $seen[$phrase] = 1;
        $starts = strpos($phrase, $norm) === 0;
        if (!$starts && $norm_syn !== '' && strpos($phrase, $norm_syn . ' ') === 0) { $starts = true; }
        $out[] = ['text' => $phrase, 'starts' => $starts, 'w' => (float) $x['weight'] * ($starts ? 3 : 1)];
    }
    usort($out, function ($a, $b) { return $b['w'] <=> $a['w']; });
    $out = array_slice($out, 0, $limit);
    foreach ($out as &$o) {
        // Выделяем дописанную часть, а не то, что человек уже ввёл
        $typed_len = strpos($o['text'], $norm) === 0 ? mb_strlen($norm) : ($o['starts'] ? mb_strlen($norm_syn) + 1 : 0);
        $o['typed'] = mb_substr($o['text'], 0, $typed_len);
        $o['rest'] = mb_substr($o['text'], $typed_len);
        unset($o['w'], $o['starts']);
    }
    unset($o);
    return $out;
}

/**
 * Всё для выпадающих подсказок: варианты запроса, «в категории» с числом товаров, до $products товаров и их общее число.
 * Товары подбираются по первой подсказке, если введённое – её начало («iph» → товары по «iphone 17»).
 */
function mm_search_suggest($mc, $q, $products = 4)
{
    $q = mb_substr(trim((string) $q), 0, 100);
    $queries = mm_search_suggest_queries($mc, $q);
    $layout = '';
    if (!$queries) {
        // Бренд по-русски без синонима («маршал») – варианты по латинскому написанию («marshal*» → marshall …)
        $fixed = mm_search_fix_unknown($mc, mm_search_display_norm($q));
        if ($fixed !== '') { $queries = mm_search_suggest_queries($mc, $fixed); }
    }
    if (!$queries && preg_match('/^[a-z\[\];\',.`\s]+$|^[а-яё\s]+$/iu', $q)) {
        $alt = mm_search_switch_layout(mb_strtolower($q, 'UTF-8'));
        $queries = mm_search_suggest_queries($mc, $alt);
        if ($queries) { $layout = $alt; }
    }
    // Товары – по самому запросу, если он полный и по нему нашлось («iphone 17» → iPhone 17, а не первая подсказка
    // «iphone 17 pro»); недописанное («iph», «эирп») – по первой подсказке, которая его продолжает
    $target = $layout !== '' ? $layout : $q;
    $found = mm_search($mc, $target, $products);
    if (($found['mode'] !== 'all' || !$found['items']) && $queries) {
        $target = $queries[0]['text'];
        $found = mm_search($mc, $target, $products);
    }
    $scopes = [];
    if (!empty($found['where'])) {
        // «В категории» – по 20 самым подходящим товарам (сумма их оценок), а не по числу товаров: иначе по «iph» первыми
        // были бы чехлы; число в подсказке – сколько товаров запроса в категории всего
        $top = mm_search($mc, $target, 20);
        $rank = [];
        foreach ($top['items'] as $it) { $rank[(int) $it['main_cat']] = (isset($rank[(int) $it['main_cat']]) ? $rank[(int) $it['main_cat']] : 0) + (float) $it['score']; }
        arsort($rank);
        $rank = array_slice($rank, 0, 2, true);
        $cats = [];
        if ($rank) {
            $r = $mc->query("SELECT main_cat, COUNT(*) c FROM mm_products {$found['where']} AND main_cat IN (" . implode(',', array_keys($rank)) . ") GROUP BY main_cat {$found['opts']}");
            while ($r && ($x = $r->fetch_row())) { $cats[(int) $x[0]] = (int) $x[1]; }
        }
        if ($cats) {
            $r = $mc->query('SELECT id, name, url FROM mm_categories WHERE id IN (' . implode(',', array_keys($cats)) . ')');
            while ($r && ($x = $r->fetch_assoc())) {
                $scopes[] = ['text' => $target, 'category' => $x['name'], 'url' => $x['url'], 'count' => $cats[(int) $x['id']], 'cat_id' => (int) $x['id']];
            }
            usort($scopes, function ($a, $b) use ($rank) { return $rank[$b['cat_id']] <=> $rank[$a['cat_id']]; });
        }
    }
    $items = [];
    foreach ($found['items'] as $it) {
        $items[] = ['id' => (int) $it['id'], 'name' => $it['name'], 'url' => $it['url'], 'img' => $it['img'], 'in_stock' => (int) $it['in_stock'],
            'price' => (float) $it['price'] > 0 ? number_format((float) $it['price'], 0, '', "\u{00A0}") . "\u{00A0}₽" : ''];
    }
    return ['q' => $q, 'layout' => $layout, 'target' => $target, 'queries' => $queries, 'scopes' => array_slice($scopes, 0, 2),
        'products' => $items, 'total' => (int) $found['total'], 'chips' => mm_search_chips($mc, $target), 'chips_base' => mm_search_display_norm($target)];
}

/** Пустое поле: «Часто ищут» – самые частые запросы покупателей, «Популярные товары» – с наибольшей выручкой за 90 дней, в наличии. */
function mm_search_popular($mc, $limit = 8)
{
    $out = [];
    $r = $mc->query('SELECT query FROM mm_queries ORDER BY freq DESC LIMIT ' . (int) $limit);
    while ($r && ($x = $r->fetch_row())) { $out[] = ['text' => $x[0], 'typed' => '', 'rest' => $x[0]]; }
    $items = [];
    // Без услуг ремонта (mods 10) – в «популярное» попадают только товары
    // По выручке за 90 дней (спрос × цена): наверху AirPods и iPhone, а не защитные стёкла, которых продают больше штук
    $r = $mc->query('SELECT id, name, url, img, price, in_stock, demand * price AS rev FROM mm_products WHERE in_stock = 1 AND ALL(mods) != 10 AND demand > 0 ORDER BY rev DESC LIMIT 4');
    while ($r && ($it = $r->fetch_assoc())) {
        $items[] = ['id' => (int) $it['id'], 'name' => $it['name'], 'url' => $it['url'], 'img' => $it['img'], 'in_stock' => 1,
            'price' => (float) $it['price'] > 0 ? number_format((float) $it['price'], 0, '', "\u{00A0}") . "\u{00A0}₽" : ''];
    }
    return ['q' => '', 'layout' => '', 'target' => '', 'queries' => $out, 'scopes' => [], 'products' => $items, 'total' => 0, 'popular' => true, 'chips' => []];
}

/**
 * Плашки-уточнения: следующее слово к запросу (как у Diginetica) – из словаря подсказок, по весу фраз, которые продолжают
 * запрос: «iphone» → 17, 16, pro…; «iphone 17» → pro, 256gb, esim… До $limit штук, без повторов слов запроса.
 */
function mm_search_chips($mc, $base, $limit = 6)
{
    $base = mm_search_display_norm($base);
    if ($base === '') { return []; }
    $r = $mc->query('SELECT phrase, weight FROM mm_suggest WHERE MATCH(' . mm_search_q($mc, '"' . mm_search_escape($base) . '"') . ') ORDER BY weight DESC LIMIT 300');
    $have = array_flip(explode(' ', $base));
    $next = [];
    while ($r && ($x = $r->fetch_assoc())) {
        $phrase = $x['phrase'];
        if (strpos($phrase, 'apple ' . $base . ' ') === 0) { $phrase = substr($phrase, 6); } // «apple iphone 17 pro» → «iphone 17 pro»
        if (strpos($phrase, $base . ' ') !== 0) { continue; }
        $w = explode(' ', substr($phrase, strlen($base) + 1))[0];
        if ($w === '' || isset($have[$w]) || mb_strlen($w) > 20 || strpos($w, '/') !== false) { continue; }
        $next[$w] = (isset($next[$w]) ? $next[$w] : 0) + (float) $x['weight'];
    }
    // Бренды аксессуаров (чехлы VLP, Moft…) – не уточнение к модели: убираем слова, которые в индексе встречаются только у аксессуаров
    if ($next) {
        $acc_only = [];
        foreach (array_slice(array_keys($next), 0, 20) as $w) {
            $q = mm_search_q($mc, '"' . mm_search_escape($base . ' ' . $w) . '"');
            $all = $mc->query("SELECT COUNT(*) FROM mm_products WHERE MATCH($q)");
            $dev = $mc->query("SELECT COUNT(*) FROM mm_products WHERE MATCH($q) AND is_acc = 0");
            $a = $all ? (int) $all->fetch_row()[0] : 0; $d = $dev ? (int) $dev->fetch_row()[0] : 0;
            if ($a > 0 && $d === 0 && !preg_match('/^\d/', $w)) { $acc_only[$w] = 1; }
        }
        $next = array_diff_key($next, $acc_only);
    }
    arsort($next);

    return array_slice(array_keys($next), 0, $limit);
}

/**
 * Категории, в которых нашлись товары запроса, – для плиток «Найдено в категориях» на странице результатов (как у
 * Diginetica): название, картинка, число товаров. Порядок – по сумме оценок найденных товаров (самые подходящие первыми,
 * а не самые многочисленные: иначе по «iphone» первыми были бы чехлы). Считается по основной категории товара.
 */
function mm_search_found_categories($mc, $q, $limit = 12)
{
    $found = mm_search($mc, $q, 1000);
    $count = []; $weight = [];
    foreach ($found['items'] as $k => $it) {
        $c = (int) $it['main_cat'];
        $count[$c] = (isset($count[$c]) ? $count[$c] : 0) + 1;
        // Вклад товара тем больше, чем выше он в выдаче (первые 60 – основной вес)
        $weight[$c] = (isset($weight[$c]) ? $weight[$c] : 0) + (float) $it['score'] * ($k < 60 ? 1 : 0.1);
    }
    if (!$count) { return ['total' => 0, 'categories' => []]; }
    arsort($weight);
    $ids = array_slice(array_keys($weight), 0, $limit);
    $info = [];
    $r = $mc->query('SELECT id, name, url, img FROM mm_categories WHERE id IN (' . implode(',', $ids) . ') LIMIT 100');
    while ($r && ($x = $r->fetch_assoc())) { $info[(int) $x['id']] = $x; }
    $out = [];
    foreach ($ids as $id) {
        if (!isset($info[$id])) { continue; }
        $out[] = ['id' => $id, 'name' => $info[$id]['name'], 'img' => $info[$id]['img'], 'count' => $count[$id]];
    }
    return ['total' => count($found['items']), 'categories' => $out];
}

/** Метка браузера покупателя для статистики (кука mm_sid ставит mm_search.js): 16 hex или ''. Без IP и личных данных. */
function mm_search_sid()
{
    $sid = isset($_COOKIE['mm_sid']) ? (string) $_COOKIE['mm_sid'] : '';
    return preg_match('/^[a-f0-9]{16}$/', $sid) ? $sid : '';
}

/** m – телефон, d – ПК (по заголовку браузера). */
function mm_search_device()
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    return preg_match('/Mobile|Android|iPhone|iPod/i', $ua) ? 'm' : 'd';
}

/**
 * Разбор запроса для админки (mm_search.check): как поиск понял запрос и почему товары стоят в таком порядке.
 * По каждому товару – совпадение слов (WEIGHT) и множители: наличие, аксессуар, модификации, б/у, запчасть, услуга,
 * спрос за 90 дней, накопленная популярность. Сортировка – сначала в наличии, затем по итоговой оценке.
 */
function mm_search_explain($mc, $q, $limit = 30)
{
    $f = mm_search($mc, $q, $limit);
    $kw = [];
    if (!empty($f['norm'])) {
        $r = $mc->query('CALL KEYWORDS(' . mm_search_q($mc, $f['norm']) . ", 'mm_products', 1 AS stats)");
        while ($r && ($x = $r->fetch_assoc())) { $kw[] = ['word' => $x['tokenized'], 'form' => $x['normalized'], 'docs' => (int) $x['docs']]; }
    }
    $syn = [];
    foreach (mm_search_synonyms() as $k => $v) {
        if ($k !== $v && preg_match('/(^| )' . preg_quote($k, '/') . '( |$)/u', isset($f['norm']) ? $f['norm'] : '')) { $syn[] = $k . ' → ' . $v; }
    }
    $pen = isset($f['penalized']) ? $f['penalized'] : [];
    $items = [];
    foreach ($f['items'] as $pos => $it) {
        $mods = array_map('intval', array_filter(explode(',', (string) $it['mods'])));
        $m = [];
        $m['наличие'] = (int) $it['in_stock'] ? 1.0 : 0.4;
        if (!empty($pen['acc']) && (int) $it['is_acc']) { $m['аксессуар'] = 0.2; }
        if (!empty($pen['mods']) && array_intersect($mods, $pen['mods'])) { $m['модификация'] = 0.75; }
        if (!empty($pen['bu']) && in_array(8, $mods, true)) { $m['б/у'] = 0.8; }
        if (!empty($pen['spare']) && in_array(9, $mods, true)) { $m['запчасть'] = 0.12; }
        if (!empty($pen['service']) && in_array(10, $mods, true)) { $m['услуга'] = 0.15; }
        $m['спрос 90 дн.'] = (float) number_format(1 + log(1 + (int) $it['demand']) / 4, 2, '.', '');
        $m['популярность'] = (float) number_format(1 + log(1 + (int) $it['popularity']) / 40, 2, '.', '');
        // Правила порядка (mm_search_intent) – не множители, а ступени сортировки после наличия: показываем словами
        $order = array_filter([!empty($it['cb']) ? 'раздел запроса' : '', !empty($it['hb']) ? 'тип товара в названии' : '']);
        if (!empty($it['pinned'])) { $m = ['закреплён в админке' => 1]; }
        $items[] = ['pos' => $pos + 1, 'id' => (int) $it['id'], 'name' => $it['name'], 'url' => $it['url'], 'in_stock' => (int) $it['in_stock'], 'pinned' => !empty($it['pinned']),
            'order' => $order ? 'выше: ' . implode(', ', $order) : '',
            'w' => (int) $it['w'], 'factors' => $m, 'score' => round((float) $it['score']), 'demand' => (int) $it['demand'], 'popularity' => (int) $it['popularity']];
    }
    $modes = ['all' => 'все слова (с синонимами и морфологией)', 'prefix' => 'по началу последнего слова', 'fuzzy' => 'с исправлением опечаток и раскладки',
        'translit' => 'русское название латиницей', 'quorum' => 'достаточно 60% слов', 'pinned' => 'только закреплённые товары', 'none' => 'ничего не нашлось', 'empty' => 'пустой запрос'];

    return ['q' => $q, 'norm' => isset($f['norm']) ? $f['norm'] : '', 'mode' => $f['mode'], 'mode_text' => isset($modes[$f['mode']]) ? $modes[$f['mode']] : $f['mode'],
        'expr' => isset($f['expr']) ? $f['expr'] : '', 'translit' => isset($f['translit']) ? $f['translit'] : '', 'total' => $f['total'],
        'keywords' => $kw, 'synonyms' => $syn, 'synonyms_text' => implode(', ', $syn), 'items' => $items];
}

/**
 * Поиск с закреплениями из админки: по точному запросу (после обработки) выбранные товары – первыми, в заданном порядке
 * (важнее правила «сначала в наличии» – это явный выбор магазина). Остальное – mm_search_raw.
 */
function mm_search($mc, $query, $limit = 20, $offset = 0)
{
    $f = mm_search_raw($mc, $query, $limit, $offset);
    $custom = mm_search_custom();
    $norm = mm_search_normalize($query);
    $pins = isset($custom['pins'][$norm]) ? array_values(array_map('intval', (array) $custom['pins'][$norm])) : [];
    if (!$pins || $offset > 0) {
        return $f;
    }
    $rows = [];
    $r = $mc->query('SELECT id, name, price, in_stock, is_acc, url, img, main_cat, 0 AS w, mods, demand, popularity, 0 AS ab, 0 AS tb, 0 AS cb, 0 AS hb, 0 AS score FROM mm_products WHERE id IN ('
        . implode(',', $pins) . ') LIMIT ' . count($pins));
    while ($r && ($x = $r->fetch_assoc())) { $x['pinned'] = 1; $rows[(int) $x['id']] = $x; }
    if (!$rows) {
        return $f;
    }
    $top = [];
    foreach ($pins as $id) { if (isset($rows[$id])) { $top[] = $rows[$id]; } }
    $rest = array_values(array_filter(isset($f['items']) ? $f['items'] : [], function ($x) use ($rows) { return !isset($rows[(int) $x['id']]); }));
    $added = count($top) - (count(isset($f['items']) ? $f['items'] : []) - count($rest));
    $f['items'] = array_slice(array_merge($top, $rest), 0, max($limit, count($top)));
    $f['total'] = (int) (isset($f['total']) ? $f['total'] : 0) + max(0, $added);
    $f['pinned'] = count($top);
    if (empty($f['mode']) || $f['mode'] === 'none') { $f['mode'] = 'pinned'; }

    return $f;
}
