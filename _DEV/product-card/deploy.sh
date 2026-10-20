#!/bin/zsh
# Выкладка карточки Maxmobiles (шаблоны maxmobiles_card*, модуль mm_delivery, секция MM-14 «Своего CSS») + очистка кэша.
# Перед выкладкой – копия текущих серверных файлов в /root/gm-backups/2026-10-19-mm-card/<время>/.
set -e
ROOT=/Users/a0000/Документы/Cursor/Maxmobiles
cd $ROOT
# MM-14 в общем файле стилей = _DEV/product-card/MM-14-card.css
python3 -I - maxmobiles-styles.css _DEV/product-card/MM-14-card.css <<'PY'
import sys
p,c=sys.argv[1:3]; s=open(p,encoding='utf-8').read(); css=open(c,encoding='utf-8').read()
H='\n/* ================================================================\n   MM-'
i=s.index(H+'14. Карточка товара'); j=s.find(H,i+1)
# только своя секция MM-14: следующая секция (MM-15 и дальше) остаётся
open(p,'w',encoding='utf-8').write(s[:i]+css.rstrip('\n')+'\n'+(s[j:] if j>=0 else ''))
PY
cp -R _DEV/mm_delivery/addon/. server-overrides/ && cp -R _DEV/product-card/new/. server-overrides/
STAGE=$(mktemp -d)
cp -R _DEV/mm_delivery/addon/. _DEV/product-card/new/. $STAGE/
mkdir -p "$STAGE/design/themes/abt__unitheme2/styles/data" && cp maxmobiles-styles.css "$STAGE/design/themes/abt__unitheme2/styles/data/Gray Maxmobiles - work new.css"
tar czf - -C $STAGE app design js | ssh -o BatchMode=yes goodmi 'set -e
R=/var/www/maxmobiles/data/www/maxmobiles.ru; B=/root/gm-backups/2026-10-19-mm-card/$(date +%H%M%S); N=$(mktemp -d)
cd $N && tar xzf -
/opt/php83/bin/php /root/gm-backups/2026-10-11-wishlist-height/lesstest.php "$N/design/themes/abt__unitheme2/styles/data/Gray Maxmobiles - work new.css" /dev/null | grep -q "^OK" || { echo "CSS FAIL"; exit 1; }
find . -name "*.php" -exec /opt/php74/bin/php -l {} \; | grep -v "^No syntax" && { echo "PHP FAIL"; exit 1; } || true
find . -type f | while read f; do if [ -e "$R/$f" ]; then mkdir -p "$B/$(dirname "$f")"; cp -p "$R/$f" "$B/$f"; fi; done
find . -type f | while read f; do if [ -e "$R/$f" ]; then cat "$f" > "$R/$f"; else install -D -o maxmobiles -g maxmobiles -m 644 "$f" "$R/$f"; fi; done
cd / && rm -rf $N
su -s /bin/sh maxmobiles -c "/opt/php74/bin/php /var/www/maxmobiles/data/mm_cc.php"
echo "выложено, бэкап: $B"'
rm -rf $STAGE
