<?php
require '/var/www/maxmobiles/data/www/maxmobiles.ru/app/mm_search/lib.php';
$mc=new mysqli('127.0.0.1','','','',9306); $mc->set_charset('utf8mb4');
foreach (array_slice($argv,1) as $q) { $t=microtime(true); $r=mm_search($mc,$q,6); printf("== %s  [%s, всего %d, %d мс]\n", $q, $r['mode'], $r['total'], (microtime(true)-$t)*1000); foreach($r['items'] as $i) printf("   %s %s%s\n", $i['in_stock']?'+':'·', $i['name'], $i['is_acc']?'  (акс.)':''); }
