import sys
sys.stdout.reconfigure(encoding='utf-8')

CHECKS = [
    # (ключ в СТАРЫЙ, ожидаемая подстрока в НОВЫЙ, не должна быть в НОВЫЙ)
    ('glazka-kamery',       'glazka-kamery',   'zadney-kamery',   'замена глазка камеры'),
    ('popala-voda',         'popala|vosstanov', None,              'попала вода'),
    ('ustranenie-korotkogo','ustranenie|remont',None,              'устранение КЗ'),
    ('zamena-peredney-kamery','perednej-kamery', 'mikrofona',      'замена передней камеры'),
    ('zamena-materinskoy-platy','materinsk',     None,             'замена МП'),
    ('zamena-korpusa',      'zamena-korpusa|zamena-materinsk', 'zamena-stekla|zamena-modema', 'замена корпуса'),
]

import re

for logfile, label in [
    (r'c:\Users\Алекс\Service MM\Сервис i\замены_лог.txt', 'Гарантии'),
    (r'c:\Users\Алекс\Service MM\Сервис i\замены_лог_prices.txt', 'Prices'),
]:
    print(f'\n{"="*60}')
    print(f'=== {label} ===')
    with open(logfile, encoding='utf-8') as f:
        content = f.read()
    
    blocks = content.split('\n\n')
    for check_old, expect_new, bad_new, label_check in CHECKS:
        wrong = []
        for block in blocks:
            lines = block.strip().split('\n')
            old_line = next((l for l in lines if l.startswith('СТАРЫЙ:')), '')
            new_line = next((l for l in lines if l.startswith('НОВЫЙ:')), '')
            if check_old not in old_line:
                continue
            old_url = old_line.replace('СТАРЫЙ: ', '').strip()
            new_url = new_line.replace('НОВЫЙ: ', '').strip()
            # Проверяем
            expect_ok = any(re.search(p, new_url) for p in expect_new.split('|')) if expect_new else True
            bad_ok    = not any(re.search(p, new_url) for p in bad_new.split('|')) if bad_new else True
            if not expect_ok or not bad_ok:
                wrong.append((old_url, new_url))
        
        if wrong:
            print(f'\n  ✗ {label_check}: {len(wrong)} неверных замен:')
            for o, n in wrong[:5]:
                print(f'     OLD: {o}')
                print(f'     NEW: {n}')
        else:
            count = sum(1 for b in blocks if check_old in (next((l for l in b.split('\n') if l.startswith('СТАРЫЙ:')), '')))
            print(f'  ✓ {label_check}: все {count} замен корректны')
