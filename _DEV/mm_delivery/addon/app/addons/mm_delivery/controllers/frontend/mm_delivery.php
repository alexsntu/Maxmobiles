<?php
/**
 * Maxmobiles: блок «Получение» карточки товара – пересчёт после смены города (только AJAX).
 * GET index.php?dispatch=mm_delivery.card&product_id=N → { mm_dd: { stock, dd, bar } }
 */

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

// Запрос только читает сессию: не сохраняем её, чтобы фоновый ответ не затёр более новую копию (как у gm_delivery_date)
Tygh::$app['session']->writability_handlers[] = function () {
    return false;
};

if ($mode === 'card') {
    $res = [];
    if (defined('AJAX_REQUEST') && fn_mm_delivery_enabled()) {
        $card = fn_mm_delivery_card(isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0);
        if ($card) {
            $res = ['stock' => $card['stock'], 'dd' => $card['dd'], 'bar' => $card['bar']];
        }
    }
    if (defined('AJAX_REQUEST')) {
        Tygh::$app['ajax']->assign('mm_dd', $res ?: new \stdClass());
    }
    exit;
}

return [CONTROLLER_STATUS_NO_PAGE];
