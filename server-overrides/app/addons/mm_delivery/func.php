<?php
/**
 * Maxmobiles: сроки получения и карточка товара (mm_delivery). Только витрина Maxmobiles (company_id = 1).
 * Правила сроков – lib/engine.php (исходник и проверки – _DEV/mm_delivery/ репозитория Maxmobiles, правила словами – RULES.md).
 * Вызывается из шаблонов blocks/product_templates/maxmobiles_card.tpl и components/maxmobiles_card_mobile.tpl
 * и из запроса mm_delivery.card (пересчёт при смене города). Любая ошибка – пустой результат, карточка остаётся без блока.
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

require_once __DIR__ . '/lib/engine.php';

/** Склад «Фирменный магазин Maxmobiles», Севастополь. */
const MM_DD_WAREHOUSE_ID = 27;

/** Разделы (id корня в id_path главной категории товара). */
function fn_mm_delivery_sections()
{
    return [
        // Trade-in – только смартфоны: iPhone, iPhone БУ, Android-смартфоны
        'smartphone' => [166, 413, 2129],
        // Гарантия+ – новые iPhone, Apple Watch, iPad, MacBook (Air, Pro, Neo), AirPods; не «Идеальное БУ» (327)
        'garantia' => [166, 174, 203, 225, 267, 2532, 323],
        'used' => [327],
        // Бонусы: группа программы лояльности → % Pro / Ultra (https://maxmobiles.ru/bonusnaya-programma-ru/)
        'bonus_accessories' => [234],
        'bonus_audio' => [323, 322],
    ];
}

function fn_mm_delivery_enabled()
{
    return (int) Registry::get('runtime.company_id') === 1;
}

/** Где покупатель (город из шапки, модуль geo_maps): [зона, название города]. Зона: sev | simf | yalta | crimea | ru | other. */
function fn_mm_delivery_zone()
{
    try {
        $loc = Tygh::$app['location']->getLocation()->toArray();
    } catch (\Throwable $e) {
        return ['other', ''];
    }
    $country = isset($loc['country']) ? strtoupper((string) $loc['country']) : '';
    $state = isset($loc['state']) ? strtoupper(trim((string) $loc['state'])) : '';
    $city = isset($loc['city']) ? trim((string) $loc['city']) : '';
    $c = mb_strtolower($city, 'UTF-8');

    if ($country !== '' && $country !== 'RU') {
        return ['other', $city];
    }
    if ($state === 'SEV' || $c === 'севастополь') {
        return ['sev', 'Севастополь'];
    }
    if ($c === 'симферополь') {
        return ['simf', 'Симферополь'];
    }
    if ($c === 'ялта') {
        return ['yalta', 'Ялта'];
    }
    if ($state === 'KRY') {
        return ['crimea', $city];
    }
    if ($state === '' && $city === '') {
        return ['other', ''];
    }

    return ['ru', $city];
}

/** Наличие: ['in_stock' => есть на складе в Севастополе, 'available' => можно купить (в наличии или предзаказ), 'amount' => шт.]. */
function fn_mm_delivery_stock($product_id)
{
    $p = db_get_row('SELECT amount, out_of_stock_actions, tracking FROM ?:products WHERE product_id = ?i', $product_id);
    if (!$p) {
        return ['in_stock' => false, 'available' => false, 'amount' => 0];
    }
    $wh = db_get_field('SELECT amount FROM ?:warehouses_products_amount WHERE product_id = ?i AND warehouse_id = ?i', $product_id, MM_DD_WAREHOUSE_ID);
    $amount = $wh !== null && $wh !== false && $wh !== '' ? (int) $wh : (int) $p['amount'];
    $in = $amount > 0;

    return ['in_stock' => $in, 'available' => $in || $p['out_of_stock_actions'] === 'B', 'amount' => max(0, $amount)];
}

/** id_path главной категории товара → массив id. */
function fn_mm_delivery_path($product_id)
{
    $path = db_get_field(
        'SELECT c.id_path FROM ?:products_categories pc INNER JOIN ?:categories c ON c.category_id = pc.category_id'
        . ' WHERE pc.product_id = ?i AND pc.link_type = ?s',
        $product_id,
        'M'
    );

    return $path ? array_map('intval', explode('/', $path)) : [];
}

/**
 * Что показывать в карточке: Trade-in, Гарантия+, группа бонусов.
 * @return array ['smartphone' => bool, 'garantia' => bool, 'bonus_pro' => %, 'bonus_ultra' => %]
 */
function fn_mm_delivery_flags($product_id)
{
    $empty = ['smartphone' => false, 'garantia' => false, 'bonus_pro' => 1, 'bonus_ultra' => 1.5];
    if (!fn_mm_delivery_enabled()) {
        return $empty;
    }
    try {
        $path = fn_mm_delivery_path((int) $product_id);
        $s = fn_mm_delivery_sections();
        $in = function ($key) use ($path, $s) {
            return (bool) array_intersect($path, $s[$key]);
        };
        $res = [
            'smartphone' => $in('smartphone'),
            'garantia' => $in('garantia') && !$in('used'),
            'bonus_pro' => 1,
            'bonus_ultra' => 1.5,
        ];
        if ($in('bonus_accessories')) {
            $res['bonus_pro'] = 4;
            $res['bonus_ultra'] = 6;
        } elseif ($in('bonus_audio')) {
            $res['bonus_ultra'] = 2;
        }

        return $res;
    } catch (\Throwable $e) {
        return $empty;
    }
}

/** Цена «10% от цены устройства» для Гарантии+, с округлением до рубля. */
function fn_mm_delivery_garantia_price($price)
{
    return (int) round((float) $price * 0.1);
}

/**
 * Цены и наличие вариантов (модуль product_variations) для подписи кнопок выбора:
 * ['<feature_id>_<variant_id>' => [цена, есть_в_магазине]]. Цена – базовая (product_prices, 1 шт., все покупатели).
 */
function fn_mm_delivery_variants(array $product)
{
    if (!fn_mm_delivery_enabled() || empty($product['variation_features_variants'])) {
        return [];
    }
    try {
        $map = [];
        foreach ($product['variation_features_variants'] as $feature) {
            if (empty($feature['variants'])) {
                continue;
            }
            foreach ($feature['variants'] as $variant) {
                $pid = !empty($variant['product']['product_id']) ? (int) $variant['product']['product_id'] : 0;
                if ($pid) {
                    $map[(int) $feature['feature_id'] . '_' . (int) $variant['variant_id']] = $pid;
                }
            }
        }
        if (!$map) {
            return [];
        }
        $ids = array_values(array_unique($map));
        $prices = db_get_hash_single_array(
            'SELECT product_id, MIN(price) AS price FROM ?:product_prices WHERE product_id IN (?n) AND lower_limit = 1 AND usergroup_id = 0 GROUP BY product_id',
            ['product_id', 'price'],
            $ids
        );
        $wh = db_get_hash_single_array(
            'SELECT product_id, amount FROM ?:warehouses_products_amount WHERE product_id IN (?n) AND warehouse_id = ?i',
            ['product_id', 'amount'],
            $ids,
            MM_DD_WAREHOUSE_ID
        );
        $out = [];
        foreach ($map as $key => $pid) {
            if (!isset($prices[$pid])) {
                continue;
            }
            $out[$key] = [(int) round((float) $prices[$pid]), isset($wh[$pid]) && (int) $wh[$pid] > 0 ? 1 : 0];
        }

        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

function fn_mm_delivery_rub($n)
{
    return number_format((int) $n, 0, '', ' ') . ' ₽';
}

/** «22 окт» */
function fn_mm_delivery_short(DateTimeImmutable $d)
{
    $m = [1 => 'янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

    return (int) $d->format('j') . ' ' . $m[(int) $d->format('n')];
}

/**
 * Блок «Получение» и метка наличия для карточки.
 * @return array ['stock' => html метки, 'dd' => html блока, 'bar' => текст для панели покупки на телефоне,
 *                'in_stock' => bool, 'available' => bool] или пустой массив
 */
function fn_mm_delivery_card($product_id, $now = null)
{
    if (!fn_mm_delivery_enabled() || (int) $product_id <= 0) {
        return [];
    }
    try {
        $st = fn_mm_delivery_stock((int) $product_id);
        if (!$st['available']) {
            return [];
        }
        $cfg = mm_dd_config();
        $now = $now ?: new DateTimeImmutable('now', new DateTimeZone($cfg['tz']));
        list($zone, $city) = fn_mm_delivery_zone();
        $in = $st['in_stock'];

        $stock = $in
            ? '<span class="mm-stock mm-stock--in"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12l4 4 10-10" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>В наличии в магазине</span>'
            : '<span class="mm-stock mm-stock--pre"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.2"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>Под заказ · на складе через 3–4 дня</span>';

        $e = mm_dd_estimate($now, $in, $zone, $cfg);
        $city_btn = '<button type="button" class="mm-dd__citybtn">' . htmlspecialchars($city !== '' ? $city : 'Выбрать город', ENT_QUOTES, 'UTF-8') . '</button>';
        $head_city = '<div class="mm-dd__city"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 21s-6-5.6-6-11a6 6 0 0 1 12 0c0 5.4-6 11-6 11z" stroke="#6e6e73" stroke-width="2"/></svg>Получение: ' . $city_btn . '</div>';
        if (!$e) {
            return ['stock' => $stock, 'dd' => '<div class="mm-dd">' . $head_city . '<div class="mm-dd__where">Выберите город, чтобы узнать срок получения</div></div>', 'bar' => '', 'in_stock' => $in, 'available' => true];
        }

        $when = mm_dd_label($now, $e['from'], $e['to']);
        $today = $now->setTime(0, 0, 0);
        $where = [
            'sev' => 'Магазин Maxmobiles · пр-т Нахимова 19, ТЦ «Детский мир» · ежедневно 10:00–20:00',
            'simf' => 'Пункт выдачи · ТЦ «Меганом» · привезёт наш курьер',
            'yalta' => 'Пункт выдачи · «Дом Торговли» · привезёт наш курьер',
            'crimea' => 'СДЭК · пункт выдачи или курьер',
            'ru' => 'СДЭК · пункт выдачи или курьер',
        ];
        $deadline = null;
        $path = [];
        $pre = [fn_mm_delivery_short($today->modify('+' . $cfg['preorder_days'][0] . ' day')), fn_mm_delivery_short($today->modify('+' . $cfg['preorder_days'][1] . ' day'))];
        $range = $e['from'] == $e['to'] ? fn_mm_delivery_short($e['from']) : fn_mm_delivery_short($e['from']) . ' – ' . fn_mm_delivery_short($e['to']);

        if ($e['kind'] === 'pickup') {
            $is_today = $in && $e['from'] == $today;
            $head = $in ? ($is_today ? 'Заберите <em>сегодня</em> до 20:00' : 'Заберите <em>' . $when . '</em> с 10:00') : 'Заберите <em>' . $when . '</em> в магазине';
            $bar = $in ? 'заберите ' . $when : 'в магазине ' . $when;
            if ($is_today) {
                $deadline = mm_dd_at($today, $cfg['pickup_cutoff']);
            }
            if (!$in) {
                $path = [['Заказ', 'сегодня', 1], ['Приедет в магазин', $pre[0] . ' – ' . $pre[1], 0], ['Забираете', 'с 10:00 до 20:00', 0]];
            }
        } elseif ($e['kind'] === 'run') {
            $head = 'Привезём <em>' . $when . '</em>, после 18:00';
            $bar = ($zone === 'simf' ? 'в Симферополе ' : 'в Ялте ') . $when;
            if ($in) {
                $deadline = $e['order_until'];
                $path = [['Заказ', 'до ' . mm_dd_label($now, $e['from'], $e['from']) . ' 11:00', 1], ['Рейс', mm_dd_label($now, $e['from'], $e['from']) . ', 11:00', 0], ['В пункте', 'после 18:00', 0]];
            } else {
                $path = [['Заказ', 'сегодня', 1], ['На складе', $pre[0] . ' – ' . $pre[1], 0], ['Рейс 11:00', 'вт или пт', 0], ['У вас', $range, 0]];
            }
        } else {
            $head = 'Доставка СДЭК <em>' . $when . '</em>';
            $bar = 'СДЭК ' . $when;
            $path = $in
                ? [['Заказ', 'сегодня', 1], ['Отправка', 'из Севастополя', 0], ['У вас', $range, 0]]
                : [['Заказ', 'сегодня', 1], ['На складе', $pre[0] . ' – ' . $pre[1], 0], ['СДЭК', implode('–', $cfg['cdek_days'][$zone]) . ' дн.', 0], ['У вас', $range, 0]];
        }

        $html = '<div class="mm-dd" data-zone="' . $zone . '">' . $head_city
            . '<div class="mm-dd__head">' . $head . '</div>'
            . '<div class="mm-dd__where">' . $where[$zone] . '</div>';
        // Отсчёт – только если до порога меньше суток; время считает скрипт по data-left (секунды на момент ответа сервера)
        if ($deadline && $deadline > $now && $deadline->getTimestamp() - $now->getTimestamp() < 86400) {
            $html .= '<div class="mm-dd__timer" data-left="' . ($deadline->getTimestamp() - $now->getTimestamp()) . '">'
                . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="13" r="8" stroke="#1d1d1f" stroke-width="2"/><path d="M12 9v4l2.5 2M9 2h6" stroke="#1d1d1f" stroke-width="2" stroke-linecap="round"/></svg>'
                . 'Оформите в течение <b></b></div>';
        }
        if ($path) {
            $html .= '<ol class="mm-path">';
            foreach ($path as $p) {
                $html .= '<li' . ($p[2] ? ' class="is-done"' : '') . '><b>' . $p[0] . '</b>' . $p[1] . '</li>';
            }
            $html .= '</ol>';
        }
        $html .= '</div>';

        return ['stock' => $stock, 'dd' => $html, 'bar' => $bar, 'in_stock' => $in, 'available' => true];
    } catch (\Throwable $e) {
        return [];
    }
}

/** То же, что fn_mm_delivery_variants, строкой JSON – для атрибута data-mm-variants в шаблоне. */
function fn_mm_delivery_variants_json(array $product)
{
    $v = fn_mm_delivery_variants($product);

    return $v ? json_encode($v) : '';
}

/** Бонусы за покупку: [Pro, Ultra] по цене и группе товара; Pro – из модуля бонусных баллов, если он посчитал. */
function fn_mm_delivery_bonus(array $product)
{
    $flags = fn_mm_delivery_flags(isset($product['product_id']) ? (int) $product['product_id'] : 0);
    $price = isset($product['price']) ? (float) $product['price'] : 0;
    $pro = !empty($product['points_info']['reward']['amount'])
        ? (int) $product['points_info']['reward']['amount']
        : (int) floor($price * $flags['bonus_pro'] / 100);

    return [
        'pro' => $pro,
        'ultra' => (int) floor($price * $flags['bonus_ultra'] / 100),
        'pro_pct' => $flags['bonus_pro'],
        'ultra_pct' => str_replace('.', ',', (string) $flags['bonus_ultra']),
    ];
}

/** 114800 → «114 800» (для шаблона: {$x|fn_mm_delivery_num}) */
function fn_mm_delivery_num($n)
{
    return number_format((int) round((float) $n), 0, '', ' ');
}
