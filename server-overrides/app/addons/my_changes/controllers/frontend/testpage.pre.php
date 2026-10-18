<?php
/**
 * GOODMi, Maxmobiles, Xione: контроллер app/controllers/frontend/testpage.php (написан разработчиком сайта) – набор из 24
 * служебных команд без проверки доступа: любой посетитель мог вызвать index.php?dispatch=testpage.<команда>, а часть из них
 * пишет в базу (clearbonus, createid, pid, clearphone, review, art, imgs, mc…). С 2026-10-18 (решение пользователя: «testpage
 * убираем и останавливаем») из интернета не работает ни одна команда – «страница не найдена». С сервера (cron) работают только
 * две нужные:
 *   updateprice    – раз в час копирует цены товаров с других витрин на Xione;
 *   generatesearch – раз в 15 минут собирает mi92_s.yml/.xml – ленту товаров GOODMi, её забирает goods.ru (Мегамаркет).
 * Остальные (в т.ч. gettraidin – цены трейд-ина из Excel, последний раз 27.01.2025) не выполняются никак; библиотека
 * PHPExcel-1.8 и traidin.xlsx убраны из папки сайта в /root/backup-2026-10-18-pre-php83/removed/. Сам контроллер не меняли.
 * Лежит в модуле my_changes, потому что CS-Cart подключает pre-контроллеры только из модулей; действует на всех витринах.
 */

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if (PHP_SAPI !== 'cli' || !in_array((string) $mode, ['updateprice', 'generatesearch'], true)) {
    return [CONTROLLER_STATUS_NO_PAGE];
}
