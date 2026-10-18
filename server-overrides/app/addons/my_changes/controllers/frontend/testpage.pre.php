<?php
/**
 * GOODMi, Maxmobiles, Xione: контроллер app/controllers/frontend/testpage.php (написан разработчиком сайта) – набор из 24
 * служебных команд без проверки доступа: любой посетитель мог вызвать index.php?dispatch=testpage.<команда>, а часть из них
 * пишет в базу (clearbonus, createid, pid, clearphone, review, art, imgs, mc…). С 2026-10-18 (решение пользователя: «testpage
 * убираем и останавливаем») не выполняется ни одна команда – ни из интернета, ни с сервера: «страница не найдена».
 * Тем же днём остановлены и последние задания cron, которые его вызывали (прежний crontab – /root/backup-2026-10-18-pre-php83/removed/):
 *   generatesearch – лента mi92_s.xml для goods.ru (Мегамаркет), давно не используется; файлы ленты – там же, megamarket-feed/;
 *   updateprice    – раз в час копировал цены с других витрин на Xione; пользователь напишет своё заново, цены Xione пока не обновляются.
 * gettraidin (цены трейд-ина из Excel, последний раз 27.01.2025) – библиотека PHPExcel-1.8 и traidin.xlsx убраны в removed/.
 * Сам контроллер testpage.php (и самописный payments.php – QR-оплата СБП, кредит MoneyCare, PayLate; не используется с 2024)
 * в тот же вечер убраны в /root/backup-2026-10-18-pre-php83/removed/ – этот файл оставлен как страховка, если их вернут.
 * Лежит в модуле my_changes, потому что CS-Cart подключает pre-контроллеры только из модулей; действует на всех витринах.
 */

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

return [CONTROLLER_STATUS_NO_PAGE];
