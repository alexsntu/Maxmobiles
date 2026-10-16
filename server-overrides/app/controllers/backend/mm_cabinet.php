<?php
/**
 * Maxmobiles: личный кабинет – страница админки «Подтверждение телефона покупателей».
 * heaven.php?dispatch=mm_cabinet.phones[&q=номер или e-mail] – поиск покупателя Maxmobiles, подтверждение номера без СМС
 * (продавец или администратор проверил покупателя лично) и снятие подтверждения. Логика – в app/mm_cabinet/store.php,
 * шаблон – design/backend/templates/views/mm_cabinet/phones.tpl.
 * heaven.php?dispatch=mm_cabinet.blocked – «Заблокированные номера»: номера, на которые сайт перестал слать СМС после
 * неудачных попыток (fn_mm_cab_strike), с кнопкой снятия блокировки. Шаблон – views/mm_cabinet/blocked.tpl.
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

require_once Registry::get('config.dir.root') . '/app/mm_cabinet/store.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'blocked') {
    if (!empty($_REQUEST['phone'])) {
        $result = fn_mm_cab_unblock($_REQUEST['phone'], (int) $auth['user_id']);
        fn_set_notification($result['ok'] ? 'N' : 'E', $result['ok'] ? 'Готово' : 'Ошибка', $result['message']);
    }

    return [CONTROLLER_STATUS_OK, 'mm_cabinet.blocked'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = isset($_REQUEST['q']) ? (string) $_REQUEST['q'] : '';
    if ($mode === 'phones' && !empty($_REQUEST['user_id'])) {
        $user_id = (int) $_REQUEST['user_id'];
        $is_customer = (bool) db_get_field("SELECT 1 FROM ?:users WHERE user_id = ?i AND company_id = 1 AND user_type = 'C'", $user_id);
        if ($is_customer) {
            $result = isset($_REQUEST['action']) && $_REQUEST['action'] === 'reset'
                ? fn_mm_cab_verify_reset($user_id)
                : fn_mm_cab_verify_manual($user_id, (int) $auth['user_id']);
            fn_set_notification($result['ok'] ? 'N' : 'E', $result['ok'] ? 'Готово' : 'Ошибка', $result['message']);
        }
    }

    return [CONTROLLER_STATUS_OK, 'mm_cabinet.phones?q=' . urlencode($query)];
}

if ($mode === 'phones') {
    $query = isset($_REQUEST['q']) ? trim((string) $_REQUEST['q']) : '';
    Tygh::$app['view']->assign('gm_q', $query);
    Tygh::$app['view']->assign('gm_users', $query !== '' ? fn_mm_cab_verify_search($query) : []);
}

if ($mode === 'blocked') {
    Tygh::$app['view']->assign('gm_blocked', fn_mm_cab_blocked_list());
    Tygh::$app['view']->assign('gm_strikes', MM_CAB_STRIKES);
}
