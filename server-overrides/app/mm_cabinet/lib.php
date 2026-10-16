<?php
/**
 * Maxmobiles: личный кабинет – данные для страницы «Мои бонусы».
 *
 * Источники: API БонусПлюс (баланс, уровень, история операций), заказы сайта из базы магазина,
 * заказы покупателя из МойСклад (покупки в магазинах). Покупатель определяется по номеру телефона
 * из его профиля на сайте; номер из браузера не принимается.
 *
 * Файл не зависит от CS-Cart (кроме функций, куда передаются готовые строки заказов сайта) – его можно
 * проверять из командной строки. Ключи доступа лежат в secrets.php рядом и в репозиторий не попадают.
 * Совместимо с PHP 7.4.
 */

if (!defined('MM_CABINET_LIB')) {
    define('MM_CABINET_LIB', 1);
}

/**
 * Уровни программы Maxmobiles (страница https://maxmobiles.ru/bonusnaya-programma-ru/, 2026-10-14): порядок, признак
 * в названии карты БонусПлюс (key), подписи, картинка карты (прислал пользователь 2026-10-14, 685×395, webp в
 * images/mm_cabinet/). Если img пустой, карта рисуется стилями (views/mm_cabinet/bonuses.tpl, gm-bp-card--<key>).
 * url – страница программы (отдельных страниц уровней у Maxmobiles нет).
 */
function mm_cab_levels()
{
    $url = 'https://maxmobiles.ru/bonusnaya-programma-ru/';
    $img = 'https://maxmobiles.ru/images/mm_cabinet/';

    return [
        [
            'key' => 'pro', 'name' => 'Pro', 'pct' => 'до 5%', 'from' => 0, 'range' => 'с первой покупки',
            'url' => $url, 'img' => $img . 'card-pro.webp',
        ],
        [
            'key' => 'ultra', 'name' => 'Ultra', 'pct' => 'до 10%', 'from' => 1500000, 'range' => 'от 1 500 000 ₽',
            'url' => $url, 'img' => $img . 'card-ultra.webp',
        ],
    ];
}

/**
 * Каналы продаж МойСклад Maxmobiles, которые в кабинете не показываем (решения пользователя 2026-10-14):
 * «MaxMobiles-CS» – заказы этого сайта (их берём из базы магазина); «Гудми», «Xione», «Xione - CS» – не продажи.
 * Остальные каналы – покупки в магазинах. Сравнение без учёта регистра.
 */
function mm_cab_hidden_channels()
{
    return ['maxmobiles-cs', 'гудми', 'xione', 'xione - cs'];
}

function mm_cab_secrets()
{
    static $secrets = null;
    if ($secrets === null) {
        $file = __DIR__ . '/secrets.php';
        $secrets = is_file($file) ? (array) include $file : [];
    }

    return $secrets;
}

/** Только цифры номера; российский номер приводится к виду 7XXXXXXXXXX. Пустая строка, если номер непригоден. */
function mm_cab_phone($raw)
{
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if (strlen($digits) === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
        return '7' . substr($digits, 1);
    }
    if (strlen($digits) === 10) {
        return '7' . $digits;
    }

    return '';
}

/**
 * Запрос к внешнему сервису. Возвращает разобранный JSON или null при любой ошибке.
 * В $error кладётся разобранный ответ сервиса с кодом не 2xx (если это JSON) – чтобы отличить «сервис отказал
 * по делу» (например, клиент не найден) от сбоя.
 */
function mm_cab_http($url, array $headers, $post = null, $timeout = 8, &$error = null)
{
    $error = null;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
    ]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post));
    }
    $body = curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        return null;
    }
    $data = json_decode($body, true);
    if ($code < 200 || $code >= 300) {
        $error = is_array($data) ? $data : null;

        return null;
    }

    return is_array($data) ? $data : null;
}

/**
 * Кэш в файлах: ответ внешнего сервиса хранится $ttl секунд. Неудачный ответ (null) не кэшируется,
 * но если есть прежний, отдаётся он – страница не должна пустеть из-за сбоя сервиса.
 */
function mm_cab_cached($dir, $key, $ttl, $producer)
{
    $file = rtrim($dir, '/') . '/' . md5($key) . '.json';
    $old = null;
    if (is_file($file)) {
        $old = json_decode((string) @file_get_contents($file), true);
        if (is_array($old) && isset($old['t'], $old['d']) && (time() - (int) $old['t']) < $ttl) {
            return $old['d'];
        }
    }
    $fresh = call_user_func($producer);
    if ($fresh === null) {
        return is_array($old) && isset($old['d']) ? $old['d'] : null;
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($file, json_encode(['t' => time(), 'd' => $fresh]), LOCK_EX);

    return $fresh;
}

/** Дата БонусПлюс и МойСклад приходят без часового пояса: БонусПлюс – в UTC, МойСклад – по Москве. */
function mm_cab_date($value, $source_tz, $format = 'd.m.Y')
{
    if (!$value) {
        return '';
    }
    try {
        $dt = new DateTime($value, new DateTimeZone($source_tz));
        $dt->setTimezone(new DateTimeZone('Europe/Moscow'));

        return $dt->format($format);
    } catch (Exception $e) {
        return '';
    }
}

function mm_cab_ts($value, $source_tz)
{
    if (!$value) {
        return 0;
    }
    try {
        $dt = new DateTime($value, new DateTimeZone($source_tz));

        return $dt->getTimestamp();
    } catch (Exception $e) {
        return 0;
    }
}

function mm_cab_money($value)
{
    return number_format((float) $value, 0, ',', ' ');
}

/** «1 бонус», «2 бонуса», «5 бонусов». */
function mm_cab_plural($n, $one, $few, $many)
{
    $n = abs((int) $n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return $many;
    }
    if ($n1 > 1 && $n1 < 5) {
        return $few;
    }
    if ($n1 === 1) {
        return $one;
    }

    return $many;
}

/**
 * Баланс, уровень и история операций из БонусПлюс.
 * Возвращает null, если сервис недоступен; ['found' => false], если номера в программе нет.
 */
function mm_cab_bonusplus($phone, $history_limit = 60)
{
    $secrets = mm_cab_secrets();
    if (empty($secrets['bonusplus_auth']) || $phone === '') {
        return null;
    }
    $headers = ['Authorization: ' . $secrets['bonusplus_auth'], 'Content-Type: application/json', 'Accept: application/json'];
    $base = 'https://bonusplus.pro/api';

    $c = mm_cab_http($base . '/customer?phone=' . rawurlencode($phone), $headers, null, 8, $refusal);
    if ($c === null) {
        // Неизвестный номер БонусПлюс отдаёт не пустым ответом, а ошибкой 412 с кодом CUSTOMER_NOT_FOUND
        if (is_array($refusal) && isset($refusal['code']) && $refusal['code'] === 'CUSTOMER_NOT_FOUND') {
            return ['found' => false];
        }

        return null;
    }
    if (!isset($c['availableBonuses'])) {
        return ['found' => false];
    }

    $levels = mm_cab_levels();
    $card = mb_strtolower((string) (isset($c['discountCardName']) ? $c['discountCardName'] : ''));
    $current = 0;
    foreach ($levels as $i => $level) {
        if ($card !== '' && mb_strpos($card, $level['key']) !== false) {
            $current = $i;
        }
    }

    $spent = (float) (isset($c['purchasesTotalSum']) ? $c['purchasesTotalSum'] : 0);
    $to_next = (float) (isset($c['purchasesSumToNextCard']) ? $c['purchasesSumToNextCard'] : 0);
    $has_next = isset($levels[$current + 1]) && $to_next > 0;
    $percent = 100;
    if ($has_next) {
        $lower = (float) $levels[$current]['from'];
        $span = max(1, $spent + $to_next - $lower);
        $percent = (int) max(1, min(99, round(100 * max(0, $spent - $lower) / $span)));
    }

    $a = mm_cab_http($base . '/retail/bonusActivities', $headers, [
        'phone' => $phone, 'rowCount' => (int) $history_limit, 'startRow' => 0, 'sort' => 'desc',
    ]);
    $ops = [];
    $now = time();
    foreach ((array) (isset($a['bonusActivities']) ? $a['bonusActivities'] : []) as $row) {
        $amount = (float) (isset($row['amount']) ? $row['amount'] : 0);
        if (abs($amount) < 0.005) {
            continue;
        }
        $until = '';
        if ($amount > 0 && !empty($row['validUntil'])) {
            $until_ts = mm_cab_ts($row['validUntil'], 'UTC');
            $until = ($until_ts > $now ? 'действуют до ' : 'действовали до ') . mm_cab_date($row['validUntil'], 'UTC');
        }
        $name = trim((string) (isset($row['transactionName']) ? $row['transactionName'] : ''));
        $ops[] = [
            'name' => $name !== '' ? str_replace(' - ', ' – ', $name) : ($amount > 0 ? 'Начисление' : 'Списание'),
            'date' => mm_cab_date(isset($row['receiptDate']) ? $row['receiptDate'] : '', 'UTC'),
            'until' => $until,
            'plus' => $amount > 0,
            'amount' => mm_cab_money(abs($amount)),
            'purchase' => !empty($row['purchaseAmount']) ? mm_cab_money($row['purchaseAmount']) : '',
        ];
    }

    $balance = (float) $c['availableBonuses'];
    $expire = (float) (isset($c['nearestBonusesExpirationAmount']) ? $c['nearestBonusesExpirationAmount'] : 0);
    $debit_pct = (float) (isset($c['baseBonusDebitPercent']) ? $c['baseBonusDebitPercent'] : 0);

    return [
        'found' => true,
        // Номер карты лояльности – его называют или показывают штрих-кодом на кассе
        'card' => preg_replace('/\D+/', '', (string) (isset($c['discountCardNumber']) ? $c['discountCardNumber'] : '')),
        'balance' => mm_cab_money($balance),
        'balance_word' => mm_cab_plural($balance, 'бонус', 'бонуса', 'бонусов'),
        'pending' => mm_cab_money(isset($c['notActiveBonuses']) ? $c['notActiveBonuses'] : 0),
        'credited' => mm_cab_money(isset($c['totalBonusCredit']) ? $c['totalBonusCredit'] : 0),
        'expire_amount' => $expire > 0 ? mm_cab_money($expire) : '',
        'expire_date' => $expire > 0 && !empty($c['nearestBonusesExpirationDate']) ? (string) $c['nearestBonusesExpirationDate'] : '',
        'debit_pct' => $debit_pct > 0 ? (int) round($debit_pct) : 40,
        'level' => $levels[$current],
        'level_index' => $current,
        'next' => $has_next ? $levels[$current + 1] : null,
        'to_next' => mm_cab_money($to_next),
        'spent' => mm_cab_money($spent),
        'percent' => $percent,
        'levels' => $levels,
        'ops' => $ops,
    ];
}

/**
 * Покупки в магазинах: заказы покупателя из МойСклад по номеру телефона, без заказов сайта и чужих площадок.
 * Возвращает null, если сервис недоступен.
 */
function mm_cab_moysklad_orders($phone, $limit = 100)
{
    $secrets = mm_cab_secrets();
    if (empty($secrets['moysklad_auth']) || strlen($phone) !== 11) {
        return null;
    }
    $headers = ['Authorization: ' . $secrets['moysklad_auth'], 'Accept-Encoding: gzip'];
    $base = 'https://api.moysklad.ru/api/remap/1.2';
    $tail = substr($phone, 1);

    $agents = mm_cab_http($base . '/entity/counterparty?search=' . rawurlencode($tail) . '&limit=20', $headers);
    if ($agents === null) {
        return null;
    }

    $hidden = mm_cab_hidden_channels();
    $orders = [];
    $checked = 0;
    foreach ((array) (isset($agents['rows']) ? $agents['rows'] : []) as $agent) {
        // Поиск МойСклад ищет и по другим полям – берём только карточки, где совпал именно телефон
        $agent_phone = mm_cab_phone(isset($agent['phone']) ? $agent['phone'] : '');
        if ($agent_phone !== $phone || empty($agent['meta']['href'])) {
            continue;
        }
        if (++$checked > 6) {
            break;
        }
        $url = $base . '/entity/customerorder?filter=' . rawurlencode('agent=' . $agent['meta']['href'])
            . '&order=' . rawurlencode('moment,desc') . '&limit=' . (int) $limit . '&expand=salesChannel,state';
        $list = mm_cab_http($url, $headers, null, 12);
        if ($list === null) {
            continue;
        }
        foreach ((array) (isset($list['rows']) ? $list['rows'] : []) as $row) {
            $channel = isset($row['salesChannel']['name']) ? (string) $row['salesChannel']['name'] : '';
            if ($channel !== '' && in_array(mb_strtolower($channel), $hidden, true)) {
                continue;
            }
            $id = isset($row['id']) ? (string) $row['id'] : '';
            $orders[$id] = [
                'source' => 'store',
                'label' => 'Магазин',
                'number' => isset($row['name']) ? (string) $row['name'] : '',
                'ts' => mm_cab_ts(isset($row['moment']) ? $row['moment'] : '', 'Europe/Moscow'),
                'date' => mm_cab_date(isset($row['moment']) ? $row['moment'] : '', 'Europe/Moscow'),
                'sum' => mm_cab_money((isset($row['sum']) ? (float) $row['sum'] : 0) / 100),
                'status' => isset($row['state']['name']) ? (string) $row['state']['name'] : '',
                'url' => '',
            ];
        }
    }

    return array_values($orders);
}

/** Заказы сайта (готовые строки из базы магазина) приводятся к тому же виду, что и покупки из МойСклад. */
function mm_cab_site_orders(array $rows, array $own_ids)
{
    $orders = [];
    foreach ($rows as $row) {
        $id = (int) $row['order_id'];
        $orders[] = [
            'key' => 'w' . $id,
            'source' => 'site',
            'label' => 'Интернет-магазин',
            'number' => (string) $id,
            'ts' => (int) $row['timestamp'],
            'date' => date('d.m.Y', (int) $row['timestamp']),
            'sum' => mm_cab_money($row['total']),
            'status' => isset($row['status_name']) ? (string) $row['status_name'] : '',
            'own' => in_array($id, $own_ids, true),
            'id' => $id,
            'url' => '',
        ];
    }

    return $orders;
}

/** Общий список заказов: сайт и магазины вместе, новые сверху. */
function mm_cab_merge_orders(array $site, array $store, $limit)
{
    $all = array_merge($site, $store);
    usort($all, function ($a, $b) {
        if ($a['ts'] === $b['ts']) {
            return 0;
        }

        return $a['ts'] > $b['ts'] ? -1 : 1;
    });

    return $limit > 0 ? array_slice($all, 0, $limit) : $all;
}

/* ---------------------------------------------------------------------------------------------------------
 * Заказы МойСклад в своей таблице (?:mm_ms_orders). Сюда – только обращение к МойСклад и приведение строк;
 * запись в базу и чтение – в store.php. Таблицу наполняет ночное задание (mm_cabinet.sync_orders),
 * а при открытии страницы покупателя дозагружаются его свежие заказы.
 * ------------------------------------------------------------------------------------------------------- */

function mm_cab_cut($value, $length)
{
    return mb_substr(trim((string) $value), 0, $length);
}

/** Заказ МойСклад → строка таблицы. null, если у заказа нет покупателя с пригодным номером телефона. */
function mm_cab_ms_row(array $order, $known_phone = '', $doc = 'O')
{
    $phone = $known_phone !== '' ? $known_phone : mm_cab_phone(isset($order['agent']['phone']) ? $order['agent']['phone'] : '');
    if ($phone === '' || empty($order['id'])) {
        return null;
    }
    $agent_id = '';
    if (!empty($order['agent']['id'])) {
        $agent_id = (string) $order['agent']['id'];
    } elseif (!empty($order['agent']['meta']['href'])) {
        $agent_id = basename((string) $order['agent']['meta']['href']);
    }

    // Связанный документ: у продажи – заказ покупателя, у возврата – продажа (берём идентификатор из ссылки)
    $docs = mm_cab_ms_docs();
    $ref = isset($docs[$doc]) ? $docs[$doc]['ref'] : '';
    $ref_id = '';
    if ($ref !== '' && !empty($order[$ref])) {
        $ref_id = !empty($order[$ref]['id']) ? (string) $order[$ref]['id']
            : (!empty($order[$ref]['meta']['href']) ? basename((string) $order[$ref]['meta']['href']) : '');
    }

    return [
        'id' => (string) $order['id'],
        'doc' => isset($docs[$doc]) ? $doc : 'O',
        'ref_id' => preg_match('/^[0-9a-f-]{36}$/', $ref_id) ? $ref_id : '',
        'store' => mm_cab_cut(isset($order['retailStore']['name']) ? $order['retailStore']['name'] : '', 64),
        'agent_id' => mm_cab_cut($agent_id, 36),
        'phone' => $phone,
        'number' => mm_cab_cut(isset($order['name']) ? $order['name'] : '', 32),
        'moment' => substr((string) (isset($order['moment']) ? $order['moment'] : ''), 0, 19),
        'total' => round((isset($order['sum']) ? (float) $order['sum'] : 0) / 100, 2),
        'state' => mm_cab_cut(isset($order['state']['name']) ? $order['state']['name'] : '', 64),
        'channel' => mm_cab_cut(isset($order['salesChannel']['name']) ? $order['salesChannel']['name'] : '', 64),
        'external_code' => mm_cab_cut(isset($order['externalCode']) ? $order['externalCode'] : '', 64),
        'updated' => substr((string) (isset($order['updated']) ? $order['updated'] : ''), 0, 19),
    ];
}

function mm_cab_ms_headers()
{
    $secrets = mm_cab_secrets();

    return empty($secrets['moysklad_auth']) ? null : ['Authorization: ' . $secrets['moysklad_auth'], 'Accept-Encoding: gzip'];
}

/**
 * Документы МойСклад, которые загружаются в таблицу ?:mm_ms_orders (поле doc):
 * O – заказ покупателя (нужен для незавершённых и отменённых заказов);
 * D – отгрузка, R – розничная продажа: именно они считаются покупкой (решение 2026-10-13), могут быть привязаны
 *     к заказу (ref – ссылка на него) или быть самостоятельными;
 * X – возврат покупателя, Y – розничный возврат: ссылаются на продажу (ref), по ним покупка считается возвращённой.
 * expand – что МойСклад должен развернуть в ответе; контрагент (agent) добавляется отдельно, когда он неизвестен.
 */
function mm_cab_ms_docs()
{
    return [
        'O' => ['entity' => 'customerorder', 'expand' => 'salesChannel,state', 'ref' => ''],
        'D' => ['entity' => 'demand', 'expand' => 'salesChannel,state', 'ref' => 'customerOrder'],
        'R' => ['entity' => 'retaildemand', 'expand' => 'retailStore', 'ref' => 'customerOrder'],
        'X' => ['entity' => 'salesreturn', 'expand' => '', 'ref' => 'demand'],
        'Y' => ['entity' => 'retailsalesreturn', 'expand' => '', 'ref' => 'demand'],
    ];
}

/**
 * Страница общего списка документов вида $doc, изменённых начиная с $from (московское время), по возрастанию даты
 * изменения. Возвращает ['rows' => строки таблицы, 'count' => сколько документов пришло, 'last' => дата изменения
 * последнего] или null.
 */
function mm_cab_ms_page($from, $offset, $limit = 100, $doc = 'O')
{
    $headers = mm_cab_ms_headers();
    $docs = mm_cab_ms_docs();
    if ($headers === null || !isset($docs[$doc])) {
        return null;
    }
    $url = 'https://api.moysklad.ru/api/remap/1.2/entity/' . $docs[$doc]['entity'] . '?filter=' . rawurlencode('updated>=' . $from)
        . '&order=' . rawurlencode('updated,asc') . '&limit=' . (int) $limit . '&offset=' . (int) $offset
        . '&expand=' . trim('agent,' . $docs[$doc]['expand'], ',');
    $data = mm_cab_http($url, $headers, null, 40);
    if ($data === null || !isset($data['rows'])) {
        return null;
    }
    $rows = [];
    $last = '';
    foreach ($data['rows'] as $order) {
        $row = mm_cab_ms_row($order, '', $doc);
        if ($row !== null) {
            $rows[] = $row;
        }
        if (!empty($order['updated'])) {
            $last = substr((string) $order['updated'], 0, 19);
        }
    }

    return ['rows' => $rows, 'count' => count($data['rows']), 'last' => $last];
}

/**
 * Документы одного покупателя. Если его карточки в МойСклад уже известны ($agent_ids), берутся только документы,
 * изменённые с $since; иначе карточки ищутся по номеру телефона и документы берутся целиком (до 500 каждого вида
 * на карточку). $docs – какие виды брать; $full_docs – какие из них взять целиком, даже когда карточки известны
 * (первая загрузка продаж для покупателя, у которого раньше были только заказы).
 * Возвращает строки таблицы или null, если МойСклад недоступен.
 */
function mm_cab_ms_customer_rows($phone, array $agent_ids = [], $since = '', array $docs = ['O', 'D', 'R'], array $full_docs = [])
{
    $headers = mm_cab_ms_headers();
    if ($headers === null || strlen($phone) !== 11) {
        return null;
    }
    $base = 'https://api.moysklad.ru/api/remap/1.2';
    $known = mm_cab_ms_docs();

    if (!$agent_ids) {
        $since = '';
        $agents = mm_cab_http($base . '/entity/counterparty?search=' . rawurlencode(substr($phone, 1)) . '&limit=20', $headers);
        if ($agents === null) {
            return null;
        }
        foreach ((array) (isset($agents['rows']) ? $agents['rows'] : []) as $agent) {
            if (!empty($agent['id']) && mm_cab_phone(isset($agent['phone']) ? $agent['phone'] : '') === $phone) {
                $agent_ids[] = (string) $agent['id'];
            }
        }
    }

    $rows = [];
    foreach (array_slice(array_values(array_unique($agent_ids)), 0, 6) as $agent_id) {
        foreach ($docs as $doc) {
            if (!isset($known[$doc])) {
                continue;
            }
            $doc_since = in_array($doc, $full_docs, true) ? '' : $since;
            $filter = 'agent=' . $base . '/entity/counterparty/' . $agent_id . ($doc_since !== '' ? ';updated>=' . $doc_since : '');
            for ($offset = 0; $offset < 500; $offset += 100) {
                $url = $base . '/entity/' . $known[$doc]['entity'] . '?filter=' . rawurlencode($filter) . '&order=' . rawurlencode('moment,desc')
                    . '&limit=100&offset=' . $offset . ($known[$doc]['expand'] !== '' ? '&expand=' . $known[$doc]['expand'] : '');
                $list = mm_cab_http($url, $headers, null, 12);
                if ($list === null) {
                    return $rows ? array_values($rows) : null;
                }
                $page = (array) (isset($list['rows']) ? $list['rows'] : []);
                foreach ($page as $order) {
                    $order['agent'] = ['id' => $agent_id];
                    $row = mm_cab_ms_row($order, $phone, $doc);
                    if ($row !== null) {
                        $rows[$row['id']] = $row;
                    }
                }
                if (count($page) < 100) {
                    break;
                }
            }
        }
    }

    return array_values($rows);
}

/**
 * Статус заказа МойСклад в том виде, в каком его видит покупатель. Названия статусов в МойСклад сделаны для сотрудников
 * («Отмена: клиент передумал», «КОНТРОЛЬ!!!!!», «Перемещение АО-> Б.Муссон», «БМ готов к выдаче») – покупателю показываем
 * общий смысл. Таблица согласована с владельцем 2026-10-12; незнакомый статус показывается как есть.
 */
function mm_cab_status_public($status)
{
    // Статусы МойСклад Maxmobiles → понятные покупателю (решение пользователя 2026-10-14). Внутренние названия
    // («Тинькофф Муленко», «Тинькофф Андрей» – имена сотрудников) покупателю не показываются.
    $status = trim((string) $status);
    $low = mb_strtolower($status);
    if ($low === '') {
        return $status;
    }
    if (mm_cab_status_kind($status) === 'cancelled') {
        return 'Отменён';
    }
    if (mm_cab_status_kind($status) === 'received') {
        return 'Выдан';
    }
    if (mb_strpos($low, 'тинькофф') === 0 || mb_strpos($low, 'предоплата') === 0) {
        return 'Предоплата получена';
    }
    if (mb_strpos($low, 'кредит') === 0) {
        return 'Оформление кредита';
    }
    if (mb_strpos($low, 'готов к выдаче') !== false || mb_strpos($low, 'прозвонили') === 0) {
        return 'Готов к выдаче';
    }
    if (mb_strpos($low, 'заказан') === 0) {
        return 'Ожидает поступления';
    }
    if (mb_strpos($low, 'доставка') === 0) {
        return 'Доставляется';
    }
    if (mb_strpos($low, 'нет связи') === 0) {
        return 'Не смогли связаться';
    }
    if (mb_strpos($low, 'отложен') === 0) {
        return 'Отложен';
    }
    // Новый, Клиент думает, Требуется согласование, Уточняем возможность заказа, В процессе подготовки, Открыт, Обработан
    // и любой новый внутренний статус – «В обработке», чтобы служебные названия не попадали к покупателю
    return 'В обработке';
}

/** Строки таблицы → вид для страницы (покупки в магазинах). */
function mm_cab_store_display(array $db_rows)
{
    $orders = [];
    foreach ($db_rows as $row) {
        $orders[] = [
            'key' => isset($row['id']) ? 's' . $row['id'] : '',
            'ms_id' => isset($row['id']) ? (string) $row['id'] : '',
            'items_at' => isset($row['items_at']) ? (string) $row['items_at'] : '',
            'source' => 'store',
            'label' => 'Магазин',
            'number' => (string) $row['number'],
            'ts' => mm_cab_ts($row['moment'], 'Europe/Moscow'),
            'date' => mm_cab_date($row['moment'], 'Europe/Moscow'),
            'sum' => mm_cab_money($row['total']),
            // Отгрузка и розничная продажа – уже состоявшаяся покупка, своего статуса у них для покупателя нет
            'status' => isset($row['doc']) && $row['doc'] !== 'O'
                ? (!empty($row['returned']) && (float) $row['returned'] > 0 ? 'Получен, часть товаров возвращена' : 'Получен')
                : mm_cab_status_public($row['state']),
            'url' => '',
        ];
    }

    return $orders;
}

/**
 * Состав документа МойСклад (заказ, отгрузка, розничная продажа – по $doc): строки для таблицы ?:mm_ms_order_items.
 * null, если МойСклад недоступен.
 * Товар сайта потом находится по внешнему коду (карта обмена commerceml) или по коду товара.
 */
function mm_cab_ms_positions($order_id, $doc = 'O')
{
    $headers = mm_cab_ms_headers();
    $docs = mm_cab_ms_docs();
    if ($headers === null || !isset($docs[$doc]) || !preg_match('/^[0-9a-f-]{36}$/', (string) $order_id)) {
        return null;
    }
    $url = 'https://api.moysklad.ru/api/remap/1.2/entity/' . $docs[$doc]['entity'] . '/' . $order_id . '/positions?limit=100&expand=assortment';
    $data = mm_cab_http($url, $headers, null, 10);
    if ($data === null || !isset($data['rows'])) {
        return null;
    }
    $rows = [];
    foreach ($data['rows'] as $i => $position) {
        $item = isset($position['assortment']) && is_array($position['assortment']) ? $position['assortment'] : [];
        $rows[] = [
            'order_id' => (string) $order_id,
            'pos' => (int) $i,
            'ms_product_id' => mm_cab_cut(isset($item['id']) ? $item['id'] : '', 36),
            'ext_code' => mm_cab_cut(isset($item['externalCode']) ? $item['externalCode'] : '', 64),
            'code' => mm_cab_cut(isset($item['code']) ? $item['code'] : '', 64),
            'name' => mm_cab_cut(isset($item['name']) ? $item['name'] : '', 255),
            'qty' => round(isset($position['quantity']) ? (float) $position['quantity'] : 0, 3),
            'price' => round((isset($position['price']) ? (float) $position['price'] : 0) / 100, 2),
        ];
    }

    return $rows;
}

/** Количество без лишних нулей: 1, 2, 0,5. */
function mm_cab_qty($value)
{
    $value = (float) $value;

    return abs($value - round($value)) < 0.0005 ? (string) (int) round($value) : rtrim(rtrim(number_format($value, 3, ',', ''), '0'), ',');
}

/** «14 сентября», для прошлых лет – «14 сентября 2025». */
function mm_cab_date_ru($ts)
{
    $ts = (int) $ts;
    if ($ts <= 0) {
        return '';
    }
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $dt = new DateTime('@' . $ts);
    $dt->setTimezone(new DateTimeZone('Europe/Moscow'));
    $text = (int) $dt->format('j') . ' ' . $months[(int) $dt->format('n') - 1];

    return $dt->format('Y') === date('Y') ? $text : $text . ' ' . $dt->format('Y');
}

/** Заказ завершён (выдан, отменён, отказ) или ещё в работе – по тексту статуса сайта или МойСклад. */
function mm_cab_status_kind($status)
{
    $status = mb_strtolower(trim((string) $status));
    if ($status === '') {
        return 'active';
    }
    if (mb_strpos($status, 'выдан') === 0 || mb_strpos($status, 'выполнен') === 0 || mb_strpos($status, 'получен') === 0) {
        return 'received';
    }
    foreach (['отмен', 'аннулир', 'отказ', 'отклон', 'неудач', 'возврат'] as $word) {
        if (mb_strpos($status, $word) === 0) {
            return 'cancelled';
        }
    }
    // Maxmobiles: «YA Отменен» (отмена Яндекса) и «Данного товара нет на складе» – тоже отмена
    if (mb_strpos($status, 'отменен') !== false || mb_strpos($status, 'нет на складе') !== false) {
        return 'cancelled';
    }

    return 'active';
}

/** Дополняет заказы полями для страницы «Мои заказы»: вид статуса, год, дата словами. */
function mm_cab_decorate_orders(array $orders)
{
    foreach ($orders as $k => $order) {
        $kind = mm_cab_status_kind($order['status']);
        $orders[$k]['kind'] = $kind;
        $orders[$k]['done'] = $kind !== 'active';
        $orders[$k]['year'] = $order['ts'] > 0 ? mm_cab_date(date('Y-m-d H:i:s', $order['ts']), date_default_timezone_get(), 'Y') : '';
        $orders[$k]['when'] = mm_cab_date_ru($order['ts']);
        $orders[$k]['title'] = $order['status'] !== '' ? $order['status'] : 'Заказ принят';
    }

    return $orders;
}

/**
 * Отправка одного СМС через Таргет СМС (JSON API, вход по логину и паролю кабинета sms.targetsms.ru).
 * Ключи: targetsms_login, targetsms_password, targetsms_sender в secrets.php.
 * Возвращает ['ok' => bool, 'error' => текст для журнала (покупателю не показывается)].
 */
function mm_cab_sms_send($phone, $text)
{
    $secrets = mm_cab_secrets();
    foreach (['targetsms_login', 'targetsms_password', 'targetsms_sender'] as $key) {
        if (empty($secrets[$key])) {
            return ['ok' => false, 'error' => 'не задан ключ ' . $key];
        }
    }
    $data = mm_cab_http('https://sms.targetsms.ru/sendsmsjson.php', ['Content-Type: application/json; charset=utf-8'], [
        'security' => ['login' => $secrets['targetsms_login'], 'password' => $secrets['targetsms_password']],
        'type' => 'sms',
        'message' => [[
            'type' => 'sms',
            'sender' => $secrets['targetsms_sender'],
            'text' => (string) $text,
            'name_delivery' => 'Код подтверждения Maxmobiles',
            'abonent' => [['phone' => (string) $phone, 'number_sms' => '1']],
        ]],
    ], 10);
    if ($data === null) {
        return ['ok' => false, 'error' => 'сервис не ответил'];
    }
    if (!empty($data['error'])) {
        return ['ok' => false, 'error' => (string) $data['error']];
    }
    $sms = isset($data['sms'][0]) && is_array($data['sms'][0]) ? $data['sms'][0] : [];
    if (!empty($sms['id_sms']) && isset($sms['action']) && $sms['action'] === 'send') {
        return ['ok' => true, 'error' => ''];
    }

    return ['ok' => false, 'error' => isset($sms['action']) ? (string) $sms['action'] : 'неизвестный ответ'];
}

/** Номер для показа покупателю: +7 978 ***-**-85. */
function mm_cab_phone_mask($phone)
{
    $phone = (string) $phone;
    if (strlen($phone) !== 11) {
        return '';
    }

    return '+' . $phone[0] . ' ' . substr($phone, 1, 3) . ' ***-**-' . substr($phone, 9, 2);
}

/** Отпечаток кода подтверждения: сам код в базе не хранится. */
function mm_cab_code_hash($code, $user_id, $phone)
{
    $secrets = mm_cab_secrets();
    $salt = !empty($secrets['verify_salt']) ? (string) $secrets['verify_salt'] : 'gm-cab';

    return hash('sha256', $salt . '|' . (int) $user_id . '|' . $phone . '|' . $code);
}

/**
 * Включено ли подтверждение номера для покупателя. Ключи secrets.php:
 * phone_verify – true (для всех) или список user_id (проверка на тестовых записях); без ключа – выключено.
 */
function mm_cab_verify_enabled($user_id)
{
    $secrets = mm_cab_secrets();
    if (empty($secrets['phone_verify'])) {
        return false;
    }

    return $secrets['phone_verify'] === true || (is_array($secrets['phone_verify']) && in_array((int) $user_id, array_map('intval', $secrets['phone_verify']), true));
}

/**
 * Можно ли покупателю подтверждать номер самому, кодом из СМС (ключ phone_verify_sms в secrets.php).
 * Пока выключено, подтверждает только оператор интернет-магазина в админке (mm_cabinet.phones), а покупателю показывается, куда обратиться.
 */
function mm_cab_verify_sms_enabled()
{
    $secrets = mm_cab_secrets();

    return !empty($secrets['phone_verify_sms']);
}

/**
 * Покупал ли покупатель товар в магазинах: есть ли у его контрагентов МойСклад выданный заказ с этим товаром.
 * Товар ищется по внешним кодам (обмен с сайтом) и по артикулу, заказы – отбором «контрагент + товар».
 * Возвращает true/false, либо null, если МойСклад не ответил (такой ответ не кэшируется).
 */
function mm_cab_ms_bought(array $agent_ids, array $ext_codes, $code)
{
    $headers = mm_cab_ms_headers();
    $agent_ids = array_values(array_filter($agent_ids, function ($id) {
        return (bool) preg_match('/^[0-9a-f-]{36}$/', (string) $id);
    }));
    if ($headers === null || !$agent_ids) {
        return null;
    }
    $base = 'https://api.moysklad.ru/api/remap/1.2/entity/';
    $filters = [];
    foreach (array_slice(array_values(array_unique(array_filter($ext_codes, 'strlen'))), 0, 4) as $ext) {
        $filters[] = 'externalCode=' . $ext;
    }
    if ((string) $code !== '') {
        $filters[] = 'code=' . $code;
    }
    $hrefs = [];
    foreach ($filters as $filter) {
        $data = mm_cab_http($base . 'assortment?limit=5&filter=' . rawurlencode($filter), $headers, null, 10);
        if ($data === null || !isset($data['rows'])) {
            return null;
        }
        foreach ($data['rows'] as $row) {
            if (!empty($row['meta']['href'])) {
                $hrefs[$row['meta']['href']] = true;
            }
        }
    }
    if (!$hrefs) {
        return false;
    }
    $parts = [];
    foreach (array_slice($agent_ids, 0, 10) as $id) {
        $parts[] = 'agent=' . $base . 'counterparty/' . $id;
    }
    foreach (array_slice(array_keys($hrefs), 0, 6) as $href) {
        $parts[] = 'assortment=' . $href;
    }
    // Покупка – это отгрузка или розничная продажа с этим товаром (заказ покупателя сам по себе покупкой не считается)
    foreach (['demand', 'retaildemand'] as $entity) {
        $data = mm_cab_http($base . $entity . '?limit=1&filter=' . rawurlencode(implode(';', $parts)), $headers, null, 12);
        if ($data === null || !isset($data['rows'])) {
            return null;
        }
        if ($data['rows']) {
            return true;
        }
    }

    return false;
}

/**
 * Состав сразу многих заказов покупателя из МойСклад одним запросом (до 100 заказов на страницу) –
 * для раздела «Купленные товары». Возвращает ['size' => всего заказов, 'orders' => [id заказа => строки состава
 * в том же виде, что mm_cab_ms_positions]] либо null, если МойСклад не ответил.
 */
function mm_cab_ms_customer_items(array $agent_ids, $offset = 0, $doc = 'D')
{
    $docs = mm_cab_ms_docs();
    if (!isset($docs[$doc])) {
        return null;
    }
    $headers = mm_cab_ms_headers();
    $agent_ids = array_values(array_filter($agent_ids, function ($id) {
        return (bool) preg_match('/^[0-9a-f-]{36}$/', (string) $id);
    }));
    if ($headers === null || !$agent_ids) {
        return null;
    }
    $base = 'https://api.moysklad.ru/api/remap/1.2/entity/';
    $parts = [];
    foreach (array_slice($agent_ids, 0, 10) as $id) {
        $parts[] = 'agent=' . $base . 'counterparty/' . $id;
    }
    $url = $base . $docs[$doc]['entity'] . '?limit=100&offset=' . (int) $offset . '&order=moment,desc&expand=positions.assortment&filter='
        . rawurlencode(implode(';', $parts));
    $data = mm_cab_http($url, $headers, null, 25);
    if ($data === null || !isset($data['rows'])) {
        return null;
    }
    $orders = [];
    foreach ($data['rows'] as $order) {
        if (empty($order['id'])) {
            continue;
        }
        $rows = [];
        $positions = isset($order['positions']['rows']) && is_array($order['positions']['rows']) ? $order['positions']['rows'] : [];
        foreach ($positions as $i => $position) {
            $item = isset($position['assortment']) && is_array($position['assortment']) ? $position['assortment'] : [];
            $rows[] = [
                'order_id' => (string) $order['id'],
                'pos' => (int) $i,
                'ms_product_id' => mm_cab_cut(isset($item['id']) ? $item['id'] : '', 36),
                'ext_code' => mm_cab_cut(isset($item['externalCode']) ? $item['externalCode'] : '', 64),
                'code' => mm_cab_cut(isset($item['code']) ? $item['code'] : '', 64),
                'name' => mm_cab_cut(isset($item['name']) ? $item['name'] : '', 255),
                'qty' => round(isset($position['quantity']) ? (float) $position['quantity'] : 0, 3),
                'price' => round((isset($position['price']) ? (float) $position['price'] : 0) / 100, 2),
            ];
        }
        $orders[(string) $order['id']] = $rows;
    }

    return ['size' => isset($data['meta']['size']) ? (int) $data['meta']['size'] : count($orders), 'orders' => $orders];
}
