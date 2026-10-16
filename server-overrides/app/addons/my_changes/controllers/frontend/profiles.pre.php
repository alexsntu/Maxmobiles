<?php
/**
 * Maxmobiles: личный кабинет – подтверждённый номер телефона нельзя изменить прямо в профиле.
 * Лежит в модуле my_changes только потому, что CS-Cart подключает pre-контроллеры лишь из модулей;
 * логика – в app/mm_cabinet/store.php. При сохранении профиля (profiles.update) номер подменяется текущим,
 * при показе страницы в шаблон передаётся $gm_phone_locked (поле гасится стилями, рядом – ссылка на смену номера
 * через код из СМС: mm_cabinet.phone). Только витрина Maxmobiles и только вошедший покупатель.
 *
 * Регистрация (форма отправляется тем же profiles.update, покупатель ещё не вошёл): номер телефона обязателен и не
 * должен быть уже подтверждён в другой учётной записи – иначе вход по номеру стал бы неоднозначным; e-mail
 * необязателен – вместо пустого записывается e-mail-заглушка из номера, fn_mm_cab_stub_email (и при регистрации,
 * и при сохранении профиля). В форме профиля заглушка не показывается (js/addons/my_changes/auth.js). Отправка кода
 * подтверждения после регистрации – обработчик update_profile в func.php.
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if ($mode === 'update' && !empty($auth['user_id']) && (int) Registry::get('runtime.company_id') === 1) {
    $gm_store = Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
    if (is_file($gm_store)) {
        try {
            require_once $gm_store;
            // Поле e-mail в профиле пустое (заглушка в нём не показывается, либо покупатель стёр адрес): оставляем
            // прежнюю заглушку или записываем новую из номера; без номера – прежний адрес
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['user_data']['email'])
                && trim((string) $_REQUEST['user_data']['email']) === '' && mm_cab_verify_enabled((int) $auth['user_id'])) {
                $gm_email = (string) db_get_field('SELECT email FROM ?:users WHERE user_id = ?i', (int) $auth['user_id']);
                if (!mm_cab_is_stub_email($gm_email)) {
                    $gm_stub = fn_mm_cab_stub_email(fn_mm_cab_user_phone((int) $auth['user_id']), (int) $auth['user_id']);
                    $gm_email = $gm_stub !== '' ? $gm_stub : $gm_email;
                }
                $_REQUEST['user_data']['email'] = $gm_email;
                $_POST['user_data']['email'] = $gm_email;
            }
            if (fn_mm_cab_phone_locked((int) $auth['user_id'])) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['user_data']) && is_array($_REQUEST['user_data'])) {
                    $current = db_get_field('SELECT phone FROM ?:users WHERE user_id = ?i', (int) $auth['user_id']);
                    foreach (['phone', 's_phone', 'b_phone'] as $field) {
                        if (isset($_REQUEST['user_data'][$field])) {
                            $_REQUEST['user_data'][$field] = $current;
                            $_POST['user_data'][$field] = $current;
                        }
                    }
                } else {
                    Tygh::$app['view']->assign('gm_phone_locked', true);
                }
            }
        } catch (\Throwable $e) {
            // Защита номера необязательна для работы профиля: при сбое страница остаётся штатной
        }
    }
}

if ($mode === 'update' && empty($auth['user_id']) && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (int) Registry::get('runtime.company_id') === 1 && isset($_REQUEST['user_data']) && is_array($_REQUEST['user_data'])) {
    $gm_store = Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';
    $gm_error = '';
    try {
        if (is_file($gm_store)) {
            require_once $gm_store;
            // Работает, когда подтверждение номера включено для всех (phone_verify => true в secrets.php)
            if (mm_cab_verify_enabled(0)) {
                $gm_phone = mm_cab_phone(isset($_REQUEST['user_data']['phone']) ? $_REQUEST['user_data']['phone'] : '');
                if ($gm_phone === '') {
                    $gm_error = 'Укажите номер телефона полностью, например +7 978 000-00-00.';
                } elseif (fn_mm_cab_phone_owners($gm_phone)) {
                    $gm_error = 'Этот номер телефона уже зарегистрирован. Войдите по номеру и паролю или восстановите пароль по СМС на странице входа.';
                } else {
                    Registry::set('runtime.mm_reg_phone', $gm_phone);
                    if (trim(isset($_REQUEST['user_data']['email']) ? (string) $_REQUEST['user_data']['email'] : '') === '') {
                        $gm_email = fn_mm_cab_stub_email($gm_phone);
                        $_REQUEST['user_data']['email'] = $gm_email;
                        $_POST['user_data']['email'] = $gm_email;
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // При сбое проверки не регистрируем: иначе запись появилась бы без обязательного подтверждения номера
        $gm_error = 'Регистрация временно недоступна. Попробуйте через несколько минут или позвоните оператору интернет-магазина: 8 (800) 250-81-58.';
    }
    if ($gm_error !== '') {
        fn_set_notification('E', __('error'), $gm_error);
        fn_save_post_data('user_data');
        unset($_REQUEST['redirect_url']);

        return [CONTROLLER_STATUS_REDIRECT, 'profiles.add'];
    }
}
