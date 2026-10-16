<?php
/**
 * Maxmobiles: личный кабинет.
 * index.php?dispatch=mm_cabinet.bonuses – страница «Мои бонусы»: баланс, уровень и история операций из БонусПлюс,
 * последние заказы (сайт и магазины).
 * index.php?dispatch=mm_cabinet.orders[&src=site|store][&page=N] – «Мои заказы»: заказы сайта и покупки в магазинах
 * одним списком, с составом и ссылками на товары. Только витрина Maxmobiles и только вошедший покупатель; номер телефона берётся
 * из его профиля в базе, из запроса не принимается. Логика – в app/mm_cabinet/lib.php и store.php,
 * шаблон – design/themes/abt__unitheme2/templates/views/mm_cabinet/bonuses.tpl.
 * index.php?dispatch=mm_cabinet.products[&page=N] – «Купленные товары»: товары из выданных заказов сайта и магазинов.
 * POST index.php?dispatch=mm_cabinet.verify (action=send|check, code, back) – подтверждение номера телефона кодом из СМС.
 * Пока номер не подтверждён (и подтверждение включено ключом phone_verify в secrets.php), страницы не показывают данные,
 * найденные по номеру: покупки в магазинах, заказы без входа и бонусы.
 * index.php?dispatch=mm_cabinet.auth_status&key=...[&hours=1..24] – сводка по СМС и попыткам входа в JSON для оповещений (ключ
 * status_key в secrets.php; без верного ключа – «страница не найдена»).
 * index.php?dispatch=mm_cabinet.recover – восстановление пароля кодом из СМС (для невошедшего покупателя): номер,
 * затем код и новый пароль. Работает только для подтверждённого номера; номер между шагами хранится в сессии.
 *
 * php index.php --dispatch=mm_cabinet.sync_orders [--minutes=50] [--docs=O,X,Y,D,R] [--history=1] – загрузка заказов, отгрузок,
 * розничных продаж и возвратов МойСклад в таблицу ?:mm_ms_orders
 * (только из командной строки; ночное задание в cron). Первый запуск проходит всю историю, дальше – изменённое.
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if ($mode === 'auth_status') {
    require_once Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
    $gm_secrets = mm_cab_secrets();
    $gm_key = isset($_REQUEST['key']) ? (string) $_REQUEST['key'] : '';
    if (empty($gm_secrets['status_key']) || $gm_key === '' || !hash_equals((string) $gm_secrets['status_key'], $gm_key)) {
        return [CONTROLLER_STATUS_NO_PAGE];
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(fn_mm_cab_auth_status(isset($_REQUEST['hours']) ? (int) $_REQUEST['hours'] : 1), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($mode === 'recover') {
    // Восстановление пароля по СМС: шаг 1 – номер, шаг 2 – код из СМС и новый пароль
    if ((int) Registry::get('runtime.company_id') !== 1) {
        return [CONTROLLER_STATUS_NO_PAGE];
    }
    if (!empty($auth['user_id'])) {
        return [CONTROLLER_STATUS_REDIRECT, 'profiles.update'];
    }
    require_once Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
    $rc_phone = isset(Tygh::$app['session']['mm_recover_phone']) ? (string) Tygh::$app['session']['mm_recover_phone'] : '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
        $result = null;
        if ($action === 'send') {
            // Проверка «я не робот» (сценарий recover в настройках капчи); при отказе она сама показывает сообщение
            if (fn_image_verification('recover', $_REQUEST) === false) {
                return [CONTROLLER_STATUS_REDIRECT, 'mm_cabinet.recover'];
            }
            $new_phone = mm_cab_phone(isset($_REQUEST['phone']) ? $_REQUEST['phone'] : '');
            $result = fn_mm_cab_recover_send($new_phone);
            if ($result['ok']) {
                Tygh::$app['session']['mm_recover_phone'] = $new_phone;
            }
        } elseif ($action === 'again' && $rc_phone !== '') {
            $result = fn_mm_cab_recover_send($rc_phone);
        } elseif ($action === 'check' && $rc_phone !== '') {
            // Пароли берём из $_POST: в $_REQUEST CS-Cart хранит их уже обработанными
            $result = fn_mm_cab_recover_check(
                $rc_phone,
                isset($_REQUEST['code']) ? $_REQUEST['code'] : '',
                isset($_POST['password1']) ? (string) $_POST['password1'] : '',
                isset($_POST['password2']) ? (string) $_POST['password2'] : ''
            );
            if ($result['ok']) {
                unset(Tygh::$app['session']['mm_recover_phone']);
                Tygh::$app['session']->regenerateID();
                // Остальные сессии покупателя CS-Cart завершит сам: у них устарела отметка смены пароля
                if (fn_login_user($result['user_id'], true) !== LOGIN_STATUS_OK) {
                    fn_set_notification('W', 'Пароль изменён', 'Войти автоматически не получилось. Войдите по номеру телефона и новому паролю.');

                    return [CONTROLLER_STATUS_REDIRECT, 'auth.login_form'];
                }
                fn_set_notification('N', 'Готово', $result['message']);

                return [CONTROLLER_STATUS_REDIRECT, 'mm_cabinet.orders'];
            }
        } elseif ($action === 'reset') {
            unset(Tygh::$app['session']['mm_recover_phone']);
        }
        if ($result) {
            fn_set_notification($result['ok'] ? 'N' : 'E', $result['ok'] ? 'Готово' : 'Ошибка', $result['message']);
        }

        return [CONTROLLER_STATUS_REDIRECT, 'mm_cabinet.recover'];
    }

    $rc_state = fn_mm_cab_recover_state($rc_phone);
    Tygh::$app['view']->assign('gm_rc', $rc_state + [
        'url' => fn_url('mm_cabinet.recover'),
        'email_url' => fn_url('auth.recover_password'),
        'login_url' => fn_url('auth.login_form'),
    ]);
    fn_add_breadcrumb('Восстановление пароля');
}

if ($mode === 'phone') {
    // Страница «Номер телефона»: состояние подтверждения и смена номера через код из СМС
    if ((int) Registry::get('runtime.company_id') !== 1) {
        return [CONTROLLER_STATUS_NO_PAGE];
    }
    if (empty($auth['user_id'])) {
        return [CONTROLLER_STATUS_REDIRECT, 'auth.login_form?return_url=' . urlencode(Registry::get('config.current_url'))];
    }
    require_once Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
    $user_id = (int) $auth['user_id'];
    if (!mm_cab_verify_enabled($user_id)) {
        return [CONTROLLER_STATUS_REDIRECT, 'profiles.update'];
    }
    $phone = fn_mm_cab_user_phone($user_id);
    // Подтверждённый номер покупатель не меняет сам (2026-10-15): номер – ключ покупателя в МойСклад и БонусПлюс,
    // меняет администратор по заявке на store@maxmobiles.ru; e-mail из профиля нужен для подсказки, заглушку не показываем
    $email = (string) db_get_field('SELECT email FROM ?:users WHERE user_id = ?i', $user_id);
    Tygh::$app['view']->assign('mm_cab', [
        'nav' => fn_mm_cab_nav($user_id, 'profile'),
        'profile_url' => fn_url('profiles.update'),
        'has_phone' => $phone !== '',
        'state' => fn_mm_cab_verify_state($user_id, $phone),
        'url' => fn_url('mm_cabinet.verify'),
        'email' => mm_cab_is_stub_email($email) ? '' : $email,
    ]);
    fn_add_breadcrumb('Номер телефона');
}

if ($mode === 'bonuses' || $mode === 'orders' || $mode === 'products') {
    if ((int) Registry::get('runtime.company_id') !== 1) {
        return [CONTROLLER_STATUS_NO_PAGE];
    }
    if (empty($auth['user_id'])) {
        return [
            CONTROLLER_STATUS_REDIRECT,
            'auth.login_form?return_url=' . urlencode(Registry::get('config.current_url')),
        ];
    }

    require_once Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';

    $user_id = (int) $auth['user_id'];
    $phone = fn_mm_cab_user_phone($user_id);
    // Данные по номеру показываем только после подтверждения номера кодом из СМС
    $verify = null;
    $trusted_phone = $phone;
    if ($phone !== '' && mm_cab_verify_enabled($user_id)) {
        $state = fn_mm_cab_verify_state($user_id, $phone);
        if (!$state['verified']) {
            $trusted_phone = '';
            // required – регистрация по номеру не завершена: до подтверждения открыта только эта страница
            $verify = $state + ['url' => fn_url('mm_cabinet.verify'), 'back' => $mode, 'required' => fn_mm_cab_verify_required($user_id)];
        }
    }
    $store_orders = [];
    $site_orders = [];
    if ($trusted_phone !== '') {
        fn_mm_cab_refresh_cached($trusted_phone);
    }
    if ($mode !== 'products') {
        $store_orders = $trusted_phone !== '' ? mm_cab_store_display(fn_mm_cab_store_get($trusted_phone)) : [];
        $site_orders = fn_mm_cab_site_orders($user_id, $trusted_phone);
    }
    $nav = [
        'verify' => $verify,
        'nav' => fn_mm_cab_nav($user_id, $mode === 'products' ? 'purchased' : $mode),
        'bonuses_url' => fn_url('mm_cabinet.bonuses'),
        'orders_url' => fn_url('mm_cabinet.orders'),
        'profile_url' => fn_url('profiles.update'),
        'program_url' => 'https://maxmobiles.ru/bonusnaya-programma-ru/',
    ];
}

if ($mode === 'verify') {
    if ((int) Registry::get('runtime.company_id') !== 1) {
        return [CONTROLLER_STATUS_NO_PAGE];
    }
    $back = isset($_REQUEST['back']) && in_array($_REQUEST['back'], ['bonuses', 'phone'], true) ? $_REQUEST['back'] : 'orders';
    if (empty($auth['user_id'])) {
        return [CONTROLLER_STATUS_REDIRECT, 'auth.login_form?return_url=' . urlencode('mm_cabinet.' . $back)];
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
        $user_id = (int) $auth['user_id'];
        $phone = fn_mm_cab_user_phone($user_id);
        if (mm_cab_verify_enabled($user_id)) {
            $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
            $state = fn_mm_cab_verify_state($user_id, $phone);
            $result = null;
            $locked_msg = 'Подтверждённый номер меняет интернет-магазин по заявке: напишите на store@maxmobiles.ru.';
            if ($action === 'change') {
                // Смена номера: код уходит на новый номер, в профиль он попадёт только после ввода кода.
                // Только для неподтверждённого номера – подтверждённый меняет администратор (см. mode=phone)
                $new_phone = mm_cab_phone(isset($_REQUEST['new_phone']) ? $_REQUEST['new_phone'] : '');
                if ($state['verified']) {
                    $result = ['ok' => false, 'message' => $locked_msg];
                } elseif ($new_phone === '') {
                    $result = ['ok' => false, 'message' => 'Введите номер полностью, например +7 978 000-00-00.'];
                } elseif ($new_phone === $phone) {
                    $result = ['ok' => false, 'message' => 'Это ваш текущий номер.'];
                } else {
                    $result = fn_mm_cab_verify_send($user_id, $new_phone);
                }
            } elseif ($action === 'check') {
                $target = $state['change_phone'] !== '' ? $state['change_phone'] : $phone;
                if ($state['verified'] && $target !== $phone) {
                    // Код на новый номер, отправленный до запрета, подтверждённый номер не заменяет
                    $result = ['ok' => false, 'message' => $locked_msg];
                } else {
                    $result = fn_mm_cab_verify_check($user_id, $target, isset($_REQUEST['code']) ? $_REQUEST['code'] : '');
                }
                if ($result['ok'] && $target !== $phone) {
                    fn_mm_cab_set_user_phone($user_id, $target);
                    $result['message'] = 'Номер изменён и подтверждён.';
                }
            } elseif (!$state['verified']) {
                $result = fn_mm_cab_verify_send($user_id, $phone);
            }
            if ($result) {
                fn_set_notification($result['ok'] ? 'N' : 'E', $result['ok'] ? 'Готово' : 'Ошибка', $result['message']);
            }
        }
    }

    return [CONTROLLER_STATUS_REDIRECT, 'mm_cabinet.' . $back];
}

if ($mode === 'products') {
    // «Купленные товары»: при первом показе догружаем состав покупок в магазинах из МойСклад
    $complete = true;
    if ($trusted_phone !== '') {
        try {
            $complete = fn_mm_cab_store_load_items($trusted_phone);
        } catch (\Throwable $e) {
            $complete = false;
        }
    }
    $list = fn_mm_cab_purchased($user_id, $trusted_phone);
    $per_page = 24;
    $pages = max(1, (int) ceil(count($list) / $per_page));
    $page = isset($_REQUEST['page']) ? max(1, min($pages, (int) $_REQUEST['page'])) : 1;

    Tygh::$app['view']->assign('mm_cab', $nav + [
        'items' => array_slice($list, ($page - 1) * $per_page, $per_page),
        'total' => count($list),
        'complete' => $complete,
        'page' => $page,
        'pages' => $pages,
        'prev_url' => $page > 1 ? fn_url('mm_cabinet.products' . ($page > 2 ? '?page=' . ($page - 1) : '')) : '',
        'next_url' => $page < $pages ? fn_url('mm_cabinet.products?page=' . ($page + 1)) : '',
        'catalog_url' => fn_url(''),
    ]);

    fn_add_breadcrumb('Купленные товары');
}

if ($mode === 'bonuses') {
    $bonus = null;
    if ($trusted_phone !== '') {
        $cache_dir = rtrim(Registry::get('config.dir.cache_misc'), '/') . '/mm_cabinet';
        try {
            $bonus = mm_cab_cached($cache_dir, 'bp_' . $phone, 300, function () use ($phone) {
                return mm_cab_bonusplus($phone);
            });
        } catch (\Throwable $e) {
            // Внешний сервис необязателен: при сбое страница показывает то, что есть
        }
    }

    // Карта лояльности: номер группами по четыре цифры и штрих-код для показа на кассе
    $card = is_array($bonus) && !empty($bonus['found']) && !empty($bonus['card']) ? (string) $bonus['card'] : '';

    Tygh::$app['view']->assign('mm_cab', $nav + [
        'has_phone' => $phone !== '',
        'card_number' => $card !== '' ? trim(chunk_split($card, 4, ' ')) : '',
        'card_barcode' => $card !== '' ? fn_mm_cab_barcode_svg($card) : '',
        'bonus' => $bonus,
        'orders' => mm_cab_merge_orders($site_orders, $store_orders, 8),
        'orders_total' => count($site_orders) + count($store_orders),
        // Покупки в магазинах (МойСклад): без них и без карты БонусПлюс покупатель считается новым
        'store_total' => count($store_orders),
    ]);

    fn_add_breadcrumb('Мои бонусы');
}

if ($mode === 'orders') {
    // Отборы: актуальные или завершённые, источник (сайт или магазин), год – для завершённых
    $all = mm_cab_decorate_orders(mm_cab_merge_orders($site_orders, $store_orders, 0));
    $count = ['active' => 0, 'done' => 0];
    foreach ($all as $order) {
        $count[$order['done'] ? 'done' : 'active']++;
    }
    $tab = isset($_REQUEST['tab']) && in_array($_REQUEST['tab'], ['active', 'done'], true)
        ? $_REQUEST['tab'] : ($count['active'] > 0 ? 'active' : 'done');
    $src = isset($_REQUEST['src']) && in_array($_REQUEST['src'], ['site', 'store'], true) ? $_REQUEST['src'] : 'all';
    $year = isset($_REQUEST['year']) && preg_match('/^20\d\d$/', (string) $_REQUEST['year']) ? (string) $_REQUEST['year'] : '';

    $years = [];
    $src_count = ['all' => 0, 'site' => 0, 'store' => 0];
    $list = [];
    foreach ($all as $order) {
        if ($order['done'] !== ($tab === 'done')) {
            continue;
        }
        $src_count['all']++;
        $src_count[$order['source']]++;
        if ($src !== 'all' && $order['source'] !== $src) {
            continue;
        }
        if ($tab === 'done' && $order['year'] !== '') {
            $years[$order['year']] = true;
        }
        if ($tab === 'done' && $year !== '' && $order['year'] !== $year) {
            continue;
        }
        $list[] = $order;
    }
    krsort($years);

    $per_page = 10;
    $pages = max(1, (int) ceil(count($list) / $per_page));
    $page = isset($_REQUEST['page']) ? max(1, min($pages, (int) $_REQUEST['page'])) : 1;
    $shown = array_slice($list, ($page - 1) * $per_page, $per_page);

    // Состав показанных заказов: заказы сайта – из базы магазина, покупки в магазинах – из таблицы (с дочитыванием)
    $site_ids = [];
    $store_ids = [];
    foreach ($shown as $order) {
        if ($order['source'] === 'site') {
            $site_ids[] = $order['id'];
        } elseif ($order['ms_id'] !== '') {
            $store_ids[$order['ms_id']] = $order['items_at'];
        }
    }
    $site_items = fn_mm_cab_site_items($site_ids);
    $store_items = [];
    try {
        $store_items = fn_mm_cab_store_items($store_ids);
    } catch (\Throwable $e) {
    }
    foreach ($shown as $k => $order) {
        if ($order['source'] === 'site') {
            $items = isset($site_items[$order['id']]) ? $site_items[$order['id']] : [];
        } else {
            $items = isset($store_items[$order['ms_id']]) ? $store_items[$order['ms_id']] : [];
        }
        $shown[$k]['items'] = $items;
        // «Оценить товар» – у полученных заказов, ведёт в отзывы первого товара, который есть на сайте
        $shown[$k]['review_url'] = '';
        if ($order['kind'] === 'received') {
            foreach ($items as $item) {
                if ($item['review_url'] !== '') {
                    $shown[$k]['review_url'] = $item['review_url'];
                    break;
                }
            }
        }
    }

    // Ссылки отборов: каждый параметр меняется отдельно, остальные сохраняются
    $link = function (array $change) use ($tab, $src, $year) {
        $params = array_merge(['tab' => $tab, 'src' => $src, 'year' => $year, 'page' => 1], $change);
        if ($params['tab'] !== 'done') {
            $params['year'] = '';
        }
        $query = [];
        foreach (['tab', 'src', 'year', 'page'] as $name) {
            $value = (string) $params[$name];
            if ($value === '' || ($name === 'src' && $value === 'all') || ($name === 'page' && $value === '1')) {
                continue;
            }
            $query[] = $name . '=' . $value;
        }

        return fn_url('mm_cabinet.orders' . ($query ? '?' . implode('&', $query) : ''));
    };
    $year_links = [['title' => 'Все годы', 'url' => $link(['year' => '']), 'active' => $year === '']];
    foreach (array_keys($years) as $y) {
        $year_links[] = ['title' => (string) $y, 'url' => $link(['year' => (string) $y]), 'active' => $year === (string) $y];
    }

    Tygh::$app['view']->assign('mm_cab', $nav + [
        'has_phone' => $phone !== '',
        'orders' => $shown,
        'total' => count($all),
        'tabs' => [
            ['title' => 'Актуальные', 'count' => $count['active'], 'url' => $link(['tab' => 'active']), 'active' => $tab === 'active'],
            ['title' => 'Завершённые', 'count' => $count['done'], 'url' => $link(['tab' => 'done']), 'active' => $tab === 'done'],
        ],
        'sources' => [
            ['title' => 'Все', 'count' => $src_count['all'], 'url' => $link(['src' => 'all']), 'active' => $src === 'all'],
            ['title' => 'Интернет-магазин', 'count' => $src_count['site'], 'url' => $link(['src' => 'site']), 'active' => $src === 'site'],
            ['title' => 'Магазины', 'count' => $src_count['store'], 'url' => $link(['src' => 'store']), 'active' => $src === 'store'],
        ],
        'years' => $tab === 'done' && count($years) > 1 ? $year_links : [],
        'page' => $page,
        'pages' => $pages,
        'prev_url' => $page > 1 ? $link(['page' => $page - 1]) : '',
        'next_url' => $page < $pages ? $link(['page' => $page + 1]) : '',
    ]);

    fn_add_breadcrumb('Мои заказы');
}

if ($mode === 'sync_orders') {
    if (PHP_SAPI !== 'cli') {
        return [CONTROLLER_STATUS_NO_PAGE];
    }
    require_once Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
    @set_time_limit(0);

    $lock_dir = rtrim(Registry::get('config.dir.cache_misc'), '/') . '/mm_cabinet';
    if (!is_dir($lock_dir)) {
        @mkdir($lock_dir, 0775, true);
    }
    $lock = @fopen($lock_dir . '/sync.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        echo "Загрузка уже идёт\n";
        exit(0);
    }

    $minutes = isset($_REQUEST['minutes']) ? max(1, min(600, (int) $_REQUEST['minutes'])) : 50;
    // Виды документов: заказы, возвраты (их мало), отгрузки, розничные продажи. --docs=R,D – только указанные
    $titles = ['O' => 'заказы', 'X' => 'возвраты покупателей', 'Y' => 'розничные возвраты', 'D' => 'отгрузки', 'R' => 'розничные продажи'];
    $docs = array_keys($titles);
    if (!empty($_REQUEST['docs'])) {
        $docs = array_values(array_intersect(explode(',', strtoupper((string) $_REQUEST['docs'])), $docs));
    }
    // --history=1 – догрузка старой истории продаж и возвратов (до MM_CAB_SALES_FROM), основные отметки не трогает
    $history = !empty($_REQUEST['history']);
    $deadline = time() + $minutes * 60;
    $failed = false;
    foreach ($docs as $doc) {
        $left = $deadline - time();
        if ($left < 20) {
            echo date('H:i:s'), ' ', $titles[$doc], ': время вышло, продолжим в следующий запуск', "\n";
            continue;
        }
        $stat = fn_mm_cab_store_sync($left, function ($s) use ($titles, $doc) {
            echo date('H:i:s'), ' ', $titles[$doc], ': страниц ', $s['pages'], ', документов ', $s['orders'], ', записано ', $s['stored'], ', отметка ', $s['cursor'], "\n";
        }, $doc, $history);
        echo date('Y-m-d H:i:s'), ' ', $titles[$doc], $history ? ' (старая история)' : '', ' – итог: страниц ', $stat['pages'], ', документов ', $stat['orders'], ', записано ', $stat['stored'],
            ', отметка ', $stat['cursor'], $stat['done'] ? ', дошли до конца' : ', не закончено', $stat['error'] !== '' ? ', ошибка: ' . $stat['error'] : '', "\n";
        $failed = $failed || $stat['error'] !== '';
    }

    flock($lock, LOCK_UN);
    fclose($lock);
    exit($failed ? 1 : 0);
}
