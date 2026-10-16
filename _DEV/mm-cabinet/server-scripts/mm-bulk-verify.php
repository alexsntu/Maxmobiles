<?php
/**
 * Maxmobiles: массовое подтверждение номеров старых покупателей (решение пользователя 2026-10-15: «делаем вариант А, как на
 * гудми»). Повторяет правила GOODMi 2026-10-12/13:
 *  1) note «по базе МойСклад» – номер есть в покупках МойСклад Maxmobiles (cscart_mm_ms_orders) и стоит только в одной
 *     включённой учётной записи покупателя Maxmobiles;
 *  2) note «зарегистрирован до 15.10.2026» – остальные включённые покупатели с номером, который стоит только в одной записи.
 * Не трогает: номера-дубли (несколько записей – разбираются отдельно), записи без номера, уже подтверждённые номера,
 * номера, подтверждённые у другой записи. Номер берётся как в fn_mm_cab_user_phone: поле phone, при пустом – телефон адреса профиля.
 *
 * php mm-bulk-verify.php           – пробный прогон: только счёт, ничего не меняет
 * php mm-bulk-verify.php --apply   – выполнить; до изменений строки таблицы сохраняются в before.json рядом со скриптом
 */
error_reporting(E_ERROR);
chdir("/var/www/maxmobiles/data/www/maxmobiles.ru");
define("BOOTSTRAP", 1); define("DIR_ROOT", getcwd()); define("PRODUCT_STATUS", "");
$config = []; @include "config.local.php";
$m = new mysqli($config["db_host"], $config["db_user"], $config["db_password"], $config["db_name"]); $m->set_charset("utf8"); $p = $config["table_prefix"];
$apply = in_array("--apply", $argv, true);

function norm($s) { $d = preg_replace("/\D+/", "", (string) $s); if (strlen($d) == 11 && ($d[0] == "7" || $d[0] == "8")) return "7" . substr($d, 1); if (strlen($d) == 10) return "7" . $d; return ""; }
function rows($m, $sql) { $r = $m->query($sql); if (!$r) { throw new Exception($m->error . " :: " . $sql); } $o = []; while ($x = $r->fetch_assoc()) $o[] = $x; return $o; }

// Номер каждой включённой записи покупателя Maxmobiles
$users = rows($m, "SELECT u.user_id, u.phone, (SELECT p.s_phone FROM {$p}user_profiles p WHERE p.user_id = u.user_id ORDER BY p.profile_id LIMIT 1) s_phone,"
    . " (SELECT p.b_phone FROM {$p}user_profiles p WHERE p.user_id = u.user_id ORDER BY p.profile_id LIMIT 1) b_phone"
    . " FROM {$p}users u WHERE u.company_id = 1 AND u.user_type = 'C' AND u.status = 'A'");
$by = []; $nophone = 0;
foreach ($users as $u) {
    $ph = norm($u["phone"]);
    if ($ph === "" && trim((string) $u["phone"]) === "") { $ph = norm($u["s_phone"] !== null && $u["s_phone"] !== "" ? $u["s_phone"] : $u["b_phone"]); }
    if ($ph === "") { $nophone++; continue; }
    $by[$ph][] = (int) $u["user_id"];
}
$ms = []; foreach (rows($m, "SELECT DISTINCT phone FROM {$p}mm_ms_orders WHERE phone <> ''") as $x) $ms[$x["phone"]] = 1;
$verified = []; foreach (rows($m, "SELECT user_id, phone FROM {$p}mm_phone_verify WHERE verified_at > 0") as $x) $verified[(int) $x["user_id"]] = $x["phone"];
$taken = array_flip(array_values($verified)); // номера, уже подтверждённые у кого-то

$plan = ["по базе МойСклад" => [], "зарегистрирован до 15.10.2026" => []]; $dups = 0; $dupRecs = 0; $skipVerified = 0; $skipTaken = 0;
foreach ($by as $ph => $ids) {
    $ph = (string) $ph; // номер-ключ массива PHP превращает в число – для сравнения со строкой из базы возвращаем строку
    if (count($ids) > 1) { $dups++; $dupRecs += count($ids); continue; }
    $id = $ids[0];
    if (isset($verified[$id]) && $verified[$id] === $ph) { $skipVerified++; continue; }
    if (isset($taken[$ph])) { $skipTaken++; continue; }
    $plan[isset($ms[$ph]) ? "по базе МойСклад" : "зарегистрирован до 15.10.2026"][$id] = $ph;
}
echo "Включённых покупателей Maxmobiles: ", count($users), ", без номера: $nophone\n";
echo "Номеров-дублей: $dups (записей в них $dupRecs) – не трогаем\n";
echo "Уже подтверждены: $skipVerified, номер подтверждён у другой записи: $skipTaken – не трогаем\n";
foreach ($plan as $note => $list) echo "Подтвердить «{$note}»: ", count($list), "\n";
if (!$apply) { echo "Пробный прогон – ничего не изменено. Для выполнения: --apply\n"; exit(0); }

$all = $plan["по базе МойСклад"] + $plan["зарегистрирован до 15.10.2026"];
$before = rows($m, "SELECT * FROM {$p}mm_phone_verify WHERE user_id IN (" . implode(",", array_map("intval", array_keys($all) ?: [0])) . ")");
file_put_contents(__DIR__ . "/before.json", json_encode(["time" => date("c"), "user_ids" => array_keys($all), "rows_before" => $before], JSON_UNESCAPED_UNICODE));
$now = time(); $done = 0;
$m->begin_transaction();
foreach ($plan as $note => $list) {
    foreach ($list as $id => $ph) {
        $sql = sprintf("INSERT INTO {$p}mm_phone_verify (user_id, phone, verified_at, verified_by, note) VALUES (%d, '%s', %d, 0, '%s')"
            . " ON DUPLICATE KEY UPDATE phone = VALUES(phone), verified_at = VALUES(verified_at), verified_by = 0, note = VALUES(note)",
            $id, $m->real_escape_string($ph), $now, $m->real_escape_string($note));
        if (!$m->query($sql)) { $m->rollback(); echo "ОШИБКА: ", $m->error, " – откат транзакции, ничего не изменено\n"; exit(1); }
        $done++;
    }
}
$m->commit();
echo "Выполнено: подтверждено $done записей (verified_at = $now). Откат – строки в before.json, новые записи: note in («по базе МойСклад», «зарегистрирован до 15.10.2026»).\n";
