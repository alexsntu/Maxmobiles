<?php
/**
 * Сроки получения товара Maxmobiles – расчёт без CS-Cart (правила – RULES.md). PHP 7.4+.
 * mm_dd_estimate($now, $in_stock, $zone) → ['kind' => 'pickup'|'run'|'cdek', 'from' => дата, 'to' => дата, ...] или null.
 */

function mm_dd_config()
{
    return [
        'tz' => 'Europe/Moscow',
        'store_open' => '10:00',
        'store_close' => '20:00',
        'pickup_cutoff' => '19:00',     // самовывоз «сегодня», если заказ строго раньше
        'run_days' => [2, 5],           // рейсы в Симферополь и Ялту: вторник, пятница (1 = пн … 7 = вс)
        'run_departure' => '11:00',     // машина выезжает; заказ строго раньше – успевает на этот рейс
        'run_arrival' => '18:00',       // товар в пункте выдачи
        'preorder_days' => [3, 4],      // предзаказ: товар на складе в Севастополе через 3–4 дня
        'cdek_days' => ['crimea' => [3, 5], 'ru' => [5, 8]],
        'holidays' => [],               // 'mm-dd': магазин закрыт, рейса нет
    ];
}

function mm_dd_at(DateTimeImmutable $day, $hhmm)
{
    list($h, $m) = array_map('intval', explode(':', $hhmm));

    return $day->setTime($h, $m, 0);
}

function mm_dd_is_holiday(DateTimeImmutable $day, array $cfg)
{
    return in_array($day->format('m-d'), $cfg['holidays'], true);
}

/** Ближайший рабочий день магазина, начиная с $day включительно. */
function mm_dd_open_day(DateTimeImmutable $day, array $cfg)
{
    $d = $day->setTime(0, 0, 0);
    while (mm_dd_is_holiday($d, $cfg)) {
        $d = $d->modify('+1 day');
    }

    return $d;
}

/** Ближайший день рейса, начиная с $day включительно. */
function mm_dd_run_from(DateTimeImmutable $day, array $cfg)
{
    $d = $day->setTime(0, 0, 0);
    for ($i = 0; $i < 60; $i++) {
        if (in_array((int) $d->format('N'), $cfg['run_days'], true) && !mm_dd_is_holiday($d, $cfg)) {
            return $d;
        }
        $d = $d->modify('+1 day');
    }

    return $d;
}

/**
 * @param DateTimeImmutable $now      текущее время
 * @param bool              $in_stock true – есть на складе в Севастополе, false – предзаказ
 * @param string            $zone     sev | simf | yalta | crimea | ru
 */
function mm_dd_estimate(DateTimeImmutable $now, $in_stock, $zone, array $cfg = null)
{
    $cfg = $cfg ?: mm_dd_config();
    $now = $now->setTimezone(new DateTimeZone($cfg['tz']));
    $today = $now->setTime(0, 0, 0);
    $res = ['zone' => $zone, 'in_stock' => (bool) $in_stock];

    if ($zone === 'crimea' || $zone === 'ru') {
        list($a, $b) = $cfg['cdek_days'][$zone];
        if (!$in_stock) {
            $a += $cfg['preorder_days'][0];
            $b += $cfg['preorder_days'][1];
        }

        return $res + ['kind' => 'cdek', 'days' => [$a, $b], 'from' => $today->modify("+$a day"), 'to' => $today->modify("+$b day")];
    }

    if ($zone === 'sev') {
        if ($in_stock) {
            $day = $now < mm_dd_at($today, $cfg['pickup_cutoff']) ? $today : $today->modify('+1 day');
            $day = mm_dd_open_day($day, $cfg);

            return $res + ['kind' => 'pickup', 'from' => $day, 'to' => $day,
                'time_from' => $day == $today ? null : $cfg['store_open'], 'time_to' => $day == $today ? $cfg['store_close'] : null];
        }
        $from = mm_dd_open_day($today->modify('+' . $cfg['preorder_days'][0] . ' day'), $cfg);
        $to = mm_dd_open_day($today->modify('+' . $cfg['preorder_days'][1] . ' day'), $cfg);

        return $res + ['kind' => 'pickup', 'from' => $from, 'to' => $to, 'time_from' => $cfg['store_open'], 'time_to' => null];
    }

    if ($zone === 'simf' || $zone === 'yalta') {
        if ($in_stock) {
            $run = mm_dd_run_from($today, $cfg);
            if ($run == $today && $now >= mm_dd_at($today, $cfg['run_departure'])) {
                $run = mm_dd_run_from($today->modify('+1 day'), $cfg);
            }
            $from = $to = $run;
        } else {
            // В день прихода на склад машина уже уехала – ближайший рейс после дня прихода
            $from = mm_dd_run_from($today->modify('+' . ($cfg['preorder_days'][0] + 1) . ' day'), $cfg);
            $to = mm_dd_run_from($today->modify('+' . ($cfg['preorder_days'][1] + 1) . ' day'), $cfg);
        }

        return $res + ['kind' => 'run', 'from' => $from, 'to' => $to, 'time_from' => $cfg['run_arrival'], 'time_to' => null,
            'order_until' => $in_stock ? mm_dd_at($from, $cfg['run_departure']) : null];
    }

    return null;
}

/** «сегодня» / «завтра» / «в пятницу, 24 октября» / «24–28 октября» – подпись даты или диапазона. */
function mm_dd_label(DateTimeImmutable $now, DateTimeImmutable $from, DateTimeImmutable $to)
{
    $months = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $wd = [1 => 'пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'];
    $today = $now->setTime(0, 0, 0);
    $one = function (DateTimeImmutable $d) use ($today, $months, $wd) {
        $diff = (int) $today->diff($d->setTime(0, 0, 0))->format('%r%a');
        if ($diff === 0) {
            return 'сегодня';
        }
        if ($diff === 1) {
            return 'завтра';
        }

        return $wd[(int) $d->format('N')] . ', ' . (int) $d->format('j') . ' ' . $months[(int) $d->format('n')];
    };
    if ($from == $to) {
        return $one($from);
    }
    if ($from->format('n') === $to->format('n')) {
        return (int) $from->format('j') . '–' . (int) $to->format('j') . ' ' . $months[(int) $to->format('n')];
    }

    return (int) $from->format('j') . ' ' . $months[(int) $from->format('n')] . ' – ' . (int) $to->format('j') . ' ' . $months[(int) $to->format('n')];
}
