import sys, openpyxl
sys.stdout.reconfigure(encoding='utf-8')

# Ключевые слова в старом слаге → чего НЕ должно быть в новом
CHECKS = [
    ('glazka-kamery',  'glazka-kamery',  'замена глазка камеры'),
    ('popala-voda',    'popala',         'попала вода'),
    ('ustranenie-korotkogo', 'ustranenie-korotkogo', 'устранение КЗ'),
    ('zamena-materinskoy-platy', 'materinsk', 'замена МП'),
    ('zamena-peredney-kamery', 'peredne',  'замена передней камеры'),
]

# Посмотрим рабочие URL по каждому паттерну
wb = openpyxl.load_workbook(r'c:\Users\Алекс\Service MM\Сервис i\export_irepair.ru_Все ссылки1.xlsx')
ws = wb.active

# Заголовки: строка 3
headers = [cell.value for cell in ws[3]]
h1_col = None
for i, h in enumerate(headers):
    if h and 'H1' == str(h).strip():
        h1_col = i
        break
print(f'H1 column index: {h1_col}')
print(f'Headers sample: {headers[:25]}\n')

# Собираем все рабочие URL с H1
working_data = []  # (url, h1)
for row in ws.iter_rows(min_row=4, values_only=True):
    if row[1] and row[4] == 200:
        url = str(row[1]).rstrip('/')
        h1 = str(row[h1_col]).strip() if h1_col and row[h1_col] else ''
        if '/catalog/' in url:
            working_data.append((url, h1))

print(f'Working URL+H1 pairs: {len(working_data)}\n')

# Проверим каждый паттерн
for broken_kw, working_kw, label in CHECKS:
    print(f'=== {label} ({broken_kw}) ===')
    matches = [(u, h) for u, h in working_data if working_kw in u.split('/')[-1]]
    print(f'  Рабочих URL с "{working_kw}": {len(matches)}')
    for u, h in matches[:5]:
        print(f'    {u}')
        print(f'    H1: {h}')
    print()
