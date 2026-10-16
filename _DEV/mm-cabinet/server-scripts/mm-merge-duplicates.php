<?php
/**
 * Maxmobiles (копия gm-merge-duplicates.php GOODMi, решение пользователя 2026-10-15 «номера дубли объединяем»): объединение записей покупателей с одним номером телефона.
 * Основная запись – та, в которую входили последней. На неё переносятся заказы сайта, отзывы, переписка, вход через
 * соцсети, избранное и корзина; номер подтверждается у основной; вторые записи ОТКЛЮЧАЮТСЯ (не удаляются).
 * Берутся только номера, которые есть в заказах МойСклад и у которых имена в записях совпадают хотя бы одним словом.
 * Баллы сайта не переносятся: они зеркалят БонусПлюс по номеру телефона.
 *
 * php mm-merge-duplicates.php                – пробный прогон по всем номерам, ничего не меняет
 * php mm-merge-duplicates.php --phones=7978..,7978..          – пробный прогон по указанным
 * php mm-merge-duplicates.php --phones=7978.. --apply         – выполнить для указанных
 * php mm-merge-duplicates.php --apply --all                   – выполнить для всех подходящих
 * Перед изменениями строки сохраняются в /root/gm-backups/2026-10-15-mm-merge/<номер>.json (для отката).
 */
error_reporting(E_ERROR);
chdir("/var/www/maxmobiles/data/www/maxmobiles.ru");
define("BOOTSTRAP", 1); define("DIR_ROOT", getcwd()); define("PRODUCT_STATUS", "");
$config = []; @include "config.local.php";
$m = new mysqli($config["db_host"], $config["db_user"], $config["db_password"], $config["db_name"]); $m->set_charset("utf8"); $p = $config["table_prefix"];
$opt = getopt("", ["phones::", "apply", "all", "quiet", "main::", "any-names", "no-ms"]);
// --main=registered – основной считать последнюю зарегистрированную запись (по умолчанию – с последним входом);
// --no-ms – не требовать номера в заказах МойСклад; --any-names – объединять и при разных именах
$by_reg = isset($opt["main"]) && $opt["main"] === "registered";
$apply = isset($opt["apply"]); $only = !empty($opt["phones"]) ? array_filter(explode(",", $opt["phones"])) : [];
if ($apply && !$only && !isset($opt["all"])) { echo "Для --apply укажите --phones=... или --all\n"; exit(1); }
$dir = "/root/gm-backups/2026-10-15-mm-merge";
if ($apply && !is_dir($dir) && !mkdir($dir, 0700, true)) { echo "Не удалось создать папку для файлов отката: $dir\n"; exit(1); }
if ($apply && !is_writable($dir)) { echo "Папка для файлов отката недоступна для записи: $dir\n"; exit(1); }

function norm($s) { $d = preg_replace("/\D+/", "", (string) $s); if (strlen($d) == 11 && ($d[0] == "7" || $d[0] == "8")) return "7" . substr($d, 1); if (strlen($d) == 10) return "7" . $d; return ""; }
function words($s) { preg_match_all("/[\p{L}\p{N}]+/u", str_replace("ё", "е", mb_strtolower((string) $s)), $w); return array_unique($w[0]); }
function rows($m, $sql) { $r = $m->query($sql); if (!$r) { throw new Exception($m->error . " :: " . $sql); } $o = []; while ($x = $r->fetch_assoc()) $o[] = $x; return $o; }
function run($m, $sql) { if (!$m->query($sql)) { throw new Exception($m->error . " :: " . $sql); } return $m->affected_rows; }

$by = [];
foreach (rows($m, "SELECT u.user_id,u.firstname,u.lastname,u.email,u.phone,u.status,u.timestamp,u.last_login,(SELECT COUNT(*) FROM {$p}orders o WHERE o.user_id=u.user_id AND o.company_id=1) orders FROM {$p}users u WHERE u.company_id=1 AND u.user_type=\"C\" AND u.status=\"A\" AND u.phone<>\"\"") as $x) {
    $ph = norm($x["phone"]); if ($ph !== "") $by[$ph][] = $x;
}
$ms = []; foreach (rows($m, "SELECT DISTINCT phone FROM {$p}mm_ms_orders WHERE phone<>\"\"") as $x) $ms[$x["phone"]] = 1;
ksort($by);
$stat = ["номеров" => 0, "объединено" => 0, "пропущено: нет в МойСклад" => 0, "пропущено: имена разные" => 0, "вторых записей" => 0, "заказов" => 0, "отзывов" => 0, "переписок" => 0, "соцсетей" => 0, "избранное/корзина" => 0, "не перенесено: соцсети" => 0, "не перенесено: избранное/корзина" => 0, "не перенесено: заказы" => 0];
foreach ($by as $ph => $list) {
    $ph = (string) $ph; // ключи-номера PHP хранит числами
    if (count($list) < 2 || ($only && !in_array($ph, $only, true))) continue;
    $stat["номеров"]++;
    usort($list, function ($a, $b) use ($by_reg) { return $by_reg ? [$b["timestamp"], $b["user_id"]] <=> [$a["timestamp"], $a["user_id"]] : [$b["last_login"], $b["orders"], $b["timestamp"]] <=> [$a["last_login"], $a["orders"], $a["timestamp"]]; });
    $main = $list[0]; $second = array_slice($list, 1); $M = (int) $main["user_id"];
    if (!isset($ms[$ph]) && !isset($opt["no-ms"])) { $stat["пропущено: нет в МойСклад"]++; continue; }
    $mw = words($main["firstname"] . " " . $main["lastname"]); $same = (bool) $mw;
    foreach ($second as $s) { if (!array_intersect($mw, words($s["firstname"] . " " . $s["lastname"]))) $same = false; }
    $names = "#" . $main["user_id"] . " " . trim($main["firstname"] . " " . $main["lastname"]) . " (создана " . date("d.m.Y", $main["timestamp"]) . ", заказов " . $main["orders"] . ")"; foreach ($second as $s) $names .= "  <-  #" . $s["user_id"] . " " . trim($s["firstname"] . " " . $s["lastname"]) . " (создана " . date("d.m.Y", $s["timestamp"]) . ", заказов " . $s["orders"] . ")";
    if (!$same && !isset($opt["quiet"])) echo "…", substr($ph, -4), ": ИМЕНА РАЗНЫЕ – ", $names, "\n";
    if (!$same && !isset($opt["any-names"])) { $stat["пропущено: имена разные"]++; continue; }
    $ids = implode(",", array_map(function ($s) { return (int) $s["user_id"]; }, $second));
    $before = [
        "phone" => $ph, "main" => $M, "second" => array_map("intval", explode(",", $ids)), "time" => date("c"),
        "users" => rows($m, "SELECT user_id,status FROM {$p}users WHERE user_id IN ($ids)"),
        "orders" => rows($m, "SELECT order_id,user_id FROM {$p}orders WHERE user_id IN ($ids)"),
        "product_reviews" => rows($m, "SELECT product_review_id,user_id FROM {$p}product_reviews WHERE user_id IN ($ids)"),
        "vendor_communications" => rows($m, "SELECT thread_id,user_id,last_message_user_id FROM {$p}vendor_communications WHERE user_id IN ($ids)"),
        "vendor_communication_messages" => rows($m, "SELECT message_id,user_id FROM {$p}vendor_communication_messages WHERE user_type=\"C\" AND user_id IN ($ids)"),
        "hybrid_auth_users" => rows($m, "SELECT * FROM {$p}hybrid_auth_users WHERE user_id IN ($ids)"),
        "user_session_products" => rows($m, "SELECT user_id,type,item_id,product_id,item_type FROM {$p}user_session_products WHERE user_type=\"R\" AND user_id IN ($ids)"),
        "mm_phone_verify" => rows($m, "SELECT * FROM {$p}mm_phone_verify WHERE user_id IN ($ids,$M)"),
    ];
    $stat["объединено"]++; $stat["вторых записей"] += count($second); $stat["заказов"] += count($before["orders"]); $stat["отзывов"] += count($before["product_reviews"]);
    $stat["переписок"] += count($before["vendor_communications"]); $stat["соцсетей"] += count($before["hybrid_auth_users"]); $stat["избранное/корзина"] += count($before["user_session_products"]);
    if (!isset($opt["quiet"])) {
        echo "…", substr($ph, -4), ": основная #$M (", trim($main["firstname"] . " " . $main["lastname"]), ", вход ", $main["last_login"] ? date("d.m.Y", $main["last_login"]) : "-", ") <- ";
        foreach ($second as $s) echo "#", $s["user_id"], " (вход ", $s["last_login"] ? date("d.m.Y", $s["last_login"]) : "-", ") ";
        echo "| заказов ", count($before["orders"]), ", отзывов ", count($before["product_reviews"]), ", соцсетей ", count($before["hybrid_auth_users"]), ", избр./корз. ", count($before["user_session_products"]), "\n";
    }
    if (!$apply) continue;
    // Без сохранённого прежнего состояния ничего не меняем (таблицы заказов на MyISAM – транзакцией не откатить)
    $json = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents("$dir/$ph.json", $json) !== strlen($json)) { echo "   ОШИБКА: не записан файл отката $dir/$ph.json, номер пропущен\n"; exit(1); }
    try {
        // updated_at не трогаем, чтобы обмен с МойСклад не счёл старые заказы изменёнными
        run($m, "UPDATE {$p}orders SET user_id=$M, updated_at=updated_at WHERE user_id IN ($ids) AND company_id=1");
        run($m, "UPDATE {$p}product_reviews SET user_id=$M WHERE user_id IN ($ids)");
        run($m, "UPDATE {$p}vendor_communication_messages SET user_id=$M WHERE user_type=\"C\" AND user_id IN ($ids)");
        run($m, "UPDATE {$p}vendor_communications SET last_message_user_id=$M WHERE last_message_user_type=\"C\" AND last_message_user_id IN ($ids)");
        run($m, "UPDATE {$p}vendor_communications SET user_id=$M WHERE user_id IN ($ids)");
        run($m, "UPDATE IGNORE {$p}hybrid_auth_users SET user_id=$M WHERE user_id IN ($ids)");
        run($m, "UPDATE IGNORE {$p}user_session_products SET user_id=$M WHERE user_type=\"R\" AND user_id IN ($ids)");
        run($m, "UPDATE {$p}mm_phone_verify SET phone=\"\", verified_at=0, verified_by=0 WHERE user_id IN ($ids)");
        run($m, "INSERT INTO {$p}mm_phone_verify (user_id,phone,verified_at,note) VALUES ($M,\"$ph\"," . time() . ",\"объединение дублей\") ON DUPLICATE KEY UPDATE note=IF(verified_at>0 AND phone=\"$ph\",note,\"объединение дублей\"), verified_at=IF(verified_at>0 AND phone=\"$ph\",verified_at," . time() . "), phone=\"$ph\"");
        run($m, "UPDATE {$p}users SET status=\"D\" WHERE user_id IN ($ids)");
        // Что не перенеслось из-за совпадений (у основной записи уже есть такая соцсеть или такой товар): остаётся у отключённой записи
        $left_h = (int) rows($m, "SELECT COUNT(*) c FROM {$p}hybrid_auth_users WHERE user_id IN ($ids)")[0]["c"];
        $left_w = (int) rows($m, "SELECT COUNT(*) c FROM {$p}user_session_products WHERE user_type=\"R\" AND user_id IN ($ids)")[0]["c"];
        $left_o = (int) rows($m, "SELECT COUNT(*) c FROM {$p}orders WHERE user_id IN ($ids)")[0]["c"];
        $stat["не перенесено: соцсети"] += $left_h; $stat["не перенесено: избранное/корзина"] += $left_w; $stat["не перенесено: заказы"] += $left_o;
        echo "   выполнено, копия: $dir/$ph.json", ($left_h || $left_w || $left_o) ? " | не перенесено: соцсетей $left_h, избр./корз. $left_w, заказов $left_o" : "", "\n";
    } catch (Exception $e) { echo "   ОШИБКА: ", $e->getMessage(), "\n"; exit(1); }
}
echo $apply ? "ВЫПОЛНЕНО\n" : "ПРОБНЫЙ ПРОГОН, изменений нет\n";
foreach ($stat as $k => $v) echo "  $k: $v\n";
