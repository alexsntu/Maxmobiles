#!/bin/zsh
# Выкладка стилей страницы категории Maxmobiles: секция MM-15 «Своего CSS» (= _DEV/category/MM-15-category.css) + очистка кэша.
# Секция MM-15 заменяется целиком (или добавляется в конец), остальные секции maxmobiles-styles.css не трогаются.
# Перед выкладкой – копия серверного файла в /root/gm-backups/2026-10-20-mm-category/<время>/.
set -e
ROOT=/Users/a0000/Документы/Cursor/Maxmobiles
cd $ROOT
python3 -I - maxmobiles-styles.css _DEV/category/MM-15-category.css <<'PY'
import sys
p,c=sys.argv[1:3]; s=open(p,encoding='utf-8').read(); css=open(c,encoding='utf-8').read().rstrip('\n')+'\n'
if not css.startswith('\n'): css='\n'+css
H='\n/* ================================================================\n   MM-'
i=s.find(H+'15. Страница категории')
if i<0:
    s=s.rstrip('\n')+'\n'+css
else:
    j=s.find(H,i+1)
    s=s[:i]+css+(s[j:] if j>=0 else '')
open(p,'w',encoding='utf-8').write(s)
PY
F="design/themes/abt__unitheme2/styles/data/Gray Maxmobiles - work new.css"
ssh -o BatchMode=yes goodmi "cat > /tmp/mm-styles-new.css" < maxmobiles-styles.css
ssh -o BatchMode=yes goodmi 'set -e
R=/var/www/maxmobiles/data/www/maxmobiles.ru; F="design/themes/abt__unitheme2/styles/data/Gray Maxmobiles - work new.css"
B=/root/gm-backups/2026-10-20-mm-category/css-$(date +%H%M%S)
/opt/php83/bin/php /root/gm-backups/2026-10-11-wishlist-height/lesstest.php /tmp/mm-styles-new.css /dev/null | grep -q "^OK" || { echo "CSS FAIL"; exit 1; }
mkdir -p "$B" && cp -p "$R/$F" "$B/"
cat /tmp/mm-styles-new.css > "$R/$F" && rm /tmp/mm-styles-new.css
echo "Откат: cp -p \"$B/Gray Maxmobiles - work new.css\" \"$R/$F\" + mm_cc.php" > "$B/ROLLBACK.txt"
su -s /bin/sh maxmobiles -c "/opt/php74/bin/php /var/www/maxmobiles/data/mm_cc.php"
echo "выложено, бэкап: $B"'
