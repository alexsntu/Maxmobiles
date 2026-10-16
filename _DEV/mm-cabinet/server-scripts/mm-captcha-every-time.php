<?php
// Maxmobiles (копия gm-captcha-every-time.php GOODMi, 2026-10-15): показывать капчу при каждой проверке, а не один раз за сеанс (настройка «Проверка изображений →
// не показывать после первой успешной проверки», object_id 165). Только витрина Maxmobiles (company_id = 1, storefront_id = 1).
// php mm-captcha-every-time.php       – показать текущее;   php mm-captcha-every-time.php N – выключить правило (Y – вернуть)
chdir("/var/www/maxmobiles/data/www/maxmobiles.ru");
define("AREA", "A"); define("ACCOUNT_TYPE", "admin");
$_SERVER["HTTP_HOST"] = "maxmobiles.ru"; $_SERVER["REMOTE_ADDR"] = "127.0.0.1"; $_SERVER["REQUEST_METHOD"] = "GET";
require "init.php";
$obj = db_get_row("SELECT object_id, name, edition_type, value FROM ?:settings_objects WHERE object_id = 165");
echo "настройка: ", $obj["name"], ", общее значение: ", $obj["value"], ", где задаётся: ", $obj["edition_type"], "\n";
foreach (db_get_array("SELECT company_id, storefront_id, value FROM ?:settings_vendor_values WHERE object_id = 165 ORDER BY company_id") as $r) echo "  витрина ", $r["company_id"], ": ", $r["value"], "\n";
if (isset($argv[1]) && in_array($argv[1], ["Y", "N"], true)) {
    db_query("REPLACE INTO ?:settings_vendor_values ?e", ["object_id" => 165, "company_id" => 1, "storefront_id" => 1, "value" => $argv[1]]);
    \Tygh\Registry::cleanup();
    echo "записано для Maxmobiles: ", $argv[1], "\n";
    foreach (db_get_array("SELECT company_id, storefront_id, value FROM ?:settings_vendor_values WHERE object_id = 165 ORDER BY company_id") as $r) echo "  витрина ", $r["company_id"], ": ", $r["value"], "\n";
}
