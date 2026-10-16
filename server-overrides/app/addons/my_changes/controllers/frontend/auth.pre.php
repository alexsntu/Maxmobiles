<?php
/**
 * Maxmobiles: восстановление пароля по e-mail не работает для e-mail-заглушки (mm_cab_is_stub_email в
 * app/mm_cabinet/store.php): настоящего ящика за ней нет. Такого покупателя отправляем на
 * восстановление по СМС (mm_cabinet.recover). Лежит в модуле my_changes, потому что CS-Cart подключает
 * pre-контроллеры только из модулей. Только витрина Maxmobiles.
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if ($mode === 'recover_password' && $_SERVER['REQUEST_METHOD'] === 'POST' && (int) Registry::get('runtime.company_id') === 1
    && isset($_REQUEST['user_email'])) {
    $gm_store = Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
    $gm_stub = false;
    try {
        if (is_file($gm_store)) {
            require_once $gm_store;
            $gm_stub = mm_cab_is_stub_email($_REQUEST['user_email']);
        }
    } catch (\Throwable $e) {
        $gm_stub = false;
    }
    if ($gm_stub) {
        fn_set_notification('W', __('warning'), 'По этому адресу восстановить пароль нельзя. Восстановите его по номеру телефона – мы отправим код в СМС.');

        return [CONTROLLER_STATUS_REDIRECT, 'mm_cabinet.recover'];
    }
}
