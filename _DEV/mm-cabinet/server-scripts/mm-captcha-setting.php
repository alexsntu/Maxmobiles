<?php
// Maxmobiles (копия gm-captcha-setting.php GOODMi, 2026-10-15): проверка «я не робот» (Яндекс SmartCaptcha) по сценариям для витрины Maxmobiles (company_id = 1, storefront_id = 1).
// Пишет строку настройки напрямую через db_query CS-Cart (он сам сбрасывает кэш настроек). Штатный
// Settings::updateValueById для этой настройки не годится: 2026-10-13 он удалил строку витрины вместо обновления.
// php mm-captcha-setting.php                  – показать текущее
// php mm-captcha-setting.php register recover – оставить включёнными ТОЛЬКО эти сценарии
chdir("/var/www/maxmobiles/data/www/maxmobiles.ru");
define("AREA", "A"); define("ACCOUNT_TYPE", "admin");
$_SERVER["HTTP_HOST"] = "maxmobiles.ru"; $_SERVER["REMOTE_ADDR"] = "127.0.0.1"; $_SERVER["REQUEST_METHOD"] = "GET";
require "init.php";
$company = 1; $storefront = 1; $object_id = 6605;
$keys = ["login", "register", "recover", "checkout", "track_orders", "track_product_in_stock", "gift_certificates", "call_request", "form_builder", "polls", "discussion"];
$show = function ($title) use ($object_id, $company, $storefront, $keys) {
    $row = db_get_field("SELECT value FROM ?:settings_vendor_values WHERE object_id = ?i AND company_id = ?i AND storefront_id = ?i", $object_id, $company, $storefront);
    $val = $row === null || $row === "" || $row === false ? null : @unserialize((string) $row);
    echo $title, ": ";
    if (!is_array($val)) { echo "строки витрины нет (действует общая настройка)\n"; return; }
    foreach ($keys as $k) echo $k, "=", isset($val[$k]) && $val[$k] === "Y" ? "да" : "нет", " ";
    echo "\n";
};
$show("сейчас");
$turn = array_slice($argv, 1);
if (!$turn) exit(0);
$val = [];
foreach ($keys as $k) $val[$k] = in_array($k, $turn, true) ? "Y" : "";
foreach ($turn as $k) if (!in_array($k, $keys, true)) { echo "нет сценария $k\n"; exit(1); }
db_query("REPLACE INTO ?:settings_vendor_values ?e", ["object_id" => $object_id, "company_id" => $company, "storefront_id" => $storefront, "value" => serialize($val)]);
// Решает показ основная настройка CS-Cart «Проверка изображений → использовать для» (object_id 138, вид #M#a=Y&b=Y);
// настройка модуля выше – только для его страницы в админке, держим их одинаковыми
db_query("REPLACE INTO ?:settings_vendor_values ?e", ["object_id" => 138, "company_id" => $company, "storefront_id" => $storefront, "value" => "#M#" . implode("=Y&", $turn) . "=Y"]);
\Tygh\Registry::cleanup();
$show("стало ");
