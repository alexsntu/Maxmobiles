<?php
/**
 * Maxmobiles: покупатель, зарегистрировавшийся по номеру телефона, не может оформить заказ, пока не подтвердит номер
 * (кодом из СМС или через оператора интернет-магазина). Вместо страницы оформления и при отправке заказа он попадает
 * в «Мои заказы», где ждёт блок подтверждения. Корзина и каталог не закрываются. Проверка – fn_mm_cab_verify_required
 * в app/mm_cabinet/store.php: действует только на записи, отмеченные при регистрации (must_verify), то есть созданные
 * формой регистрации с 2026-10-13. Лежит в модуле my_changes, потому что CS-Cart подключает pre-контроллеры
 * только из модулей. Только витрина Maxmobiles.
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if (($mode === 'checkout' || $mode === 'place_order') && !empty($auth['user_id']) && (int) Registry::get('runtime.company_id') === 1) {
    $gm_store = Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
    $gm_required = false;
    try {
        if (is_file($gm_store)) {
            require_once $gm_store;
            $gm_required = fn_mm_cab_verify_required((int) $auth['user_id']);
        }
    } catch (\Throwable $e) {
        // При сбое оформление заказа работает штатно
        $gm_required = false;
    }
    if ($gm_required) {
        fn_set_notification('W', 'Подтвердите номер телефона', 'Чтобы оформить заказ, подтвердите номер: введите код из СМС или позвоните оператору интернет-магазина по номеру 8 (800) 250-81-58 с этого телефона. Товары в корзине сохранятся.');

        return [CONTROLLER_STATUS_REDIRECT, 'mm_cabinet.orders'];
    }
}

/*
 * Подтверждённый номер нельзя сменить и через оформление заказа (2026-10-15): после заказа CS-Cart переносит данные
 * покупателя из корзины в профиль (fn_checkout_update_user_profile), а номер – ключ покупателя в МойСклад и БонусПлюс.
 * Вошедшему покупателю с подтверждённым номером номер из формы (и в корзине) подменяется номером профиля – так же, как
 * при сохранении профиля (profiles.pre.php). Гостей и неподтверждённые номера не трогаем. Поле на странице делает
 * недоступным auth.js по флагу $gm_phone_locked (хук index:scripts модуля my_changes).
 */
if (!empty($auth['user_id']) && (int) Registry::get('runtime.company_id') === 1) {
    $gm_store = Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
    try {
        if (is_file($gm_store)) {
            require_once $gm_store;
            if (fn_mm_cab_phone_locked((int) $auth['user_id'])) {
                $gm_phone = fn_mm_cab_user_phone((int) $auth['user_id']);
                $gm_current = (string) db_get_field('SELECT phone FROM ?:users WHERE user_id = ?i', (int) $auth['user_id']);
                if (mm_cab_phone($gm_current) !== $gm_phone) {
                    $gm_current = '+7(' . substr($gm_phone, 1, 3) . ')' . substr($gm_phone, 4, 3) . '-' . substr($gm_phone, 7, 2) . '-' . substr($gm_phone, 9, 2);
                }
                if (isset($_REQUEST['user_data']) && is_array($_REQUEST['user_data'])) {
                    foreach (['phone', 's_phone', 'b_phone'] as $gm_field) {
                        if (isset($_REQUEST['user_data'][$gm_field])) {
                            $_REQUEST['user_data'][$gm_field] = $gm_current;
                            $_POST['user_data'][$gm_field] = $gm_current;
                        }
                    }
                }
                if (isset(Tygh::$app['session']['cart']['user_data']) && is_array(Tygh::$app['session']['cart']['user_data'])) {
                    Tygh::$app['session']['cart']['user_data']['phone'] = $gm_current;
                }
                Tygh::$app['view']->assign('gm_phone_locked', true);
            }
        }
    } catch (\Throwable $e) {
        // Защита номера необязательна для оформления заказа: при сбое оно работает штатно
    }
}
