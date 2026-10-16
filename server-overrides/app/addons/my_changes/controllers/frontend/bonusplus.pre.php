<?php
/**
 * GOODMi и Maxmobiles: служебные команды контроллера app/controllers/frontend/bonusplus.php (написан разработчиком сайта)
 * были доступны любому посетителю по адресу index.php?dispatch=bonusplus.<команда>. Среди них – удаление и создание
 * подписок на вебхуки БонусПлюс (ajdel, aj1, aj), массовый обход покупателей с запросами в БонусПлюс (bonus), перезапись
 * баллов из файла (bonusfile), вывод полных данных заказа (orderinfo). С 2026-10-15 (решение пользователя) они работают
 * только при запуске с сервера (cron: bonusplus.clearphone) и для вошедшего администратора; остальным – «страница не найдена».
 * Открытой остаётся только ajax – проверка баланса по номеру (js/tygh/bonus.js). Вебхуки БонусПлюс приходят в отдельные
 * файлы bonusplus.php и bonuspluscard.php в корне сайта и этой проверкой не затрагиваются. Сам контроллер не меняли.
 * Лежит в модуле my_changes, потому что CS-Cart подключает pre-контроллеры только из модулей; действует на всех витринах.
 */

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$mc_bp_closed = ['aj1', 'ajdel', 'aj', 'orderinfo', 'bonus', 'bonusfile', 'clearphone'];

if (in_array((string) $mode, $mc_bp_closed, true)
    && PHP_SAPI !== 'cli'
    && !(isset($auth['user_type']) && $auth['user_type'] === 'A' && !empty($auth['user_id']))
) {
    return [CONTROLLER_STATUS_NO_PAGE];
}
