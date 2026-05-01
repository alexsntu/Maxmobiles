import sys, openpyxl
sys.stdout.reconfigure(encoding='utf-8')

wb = openpyxl.load_workbook(r'c:\Users\Алекс\Service MM\Сервис i\export_irepair.ru_Все ссылки1.xlsx')
ws = wb.active
headers = [cell.value for cell in ws[3]]
h1_idx = headers.index('H1')

working = {}
for row in ws.iter_rows(min_row=4, values_only=True):
    if row[1] and row[4] == 200:
        url = str(row[1]).rstrip('/')
        h1 = str(row[h1_idx]).strip() if row[h1_idx] else ''
        working[url] = h1

# Найти URL для popala-voda / vosstanovlenie для iPhone 11, 12, 14, 15, 16
print('=== popala-voda / vosstanovlenie-zashchity для iPhone ===')
for u, h in working.items():
    if ('popala' in u or 'vosstanovlenie-zashchity' in u or 'zhidkost' in u) and 'remont-iphone' in u:
        print(f'  {u}')
        print(f'     H1: {h}')

print('\n=== ustranenie-korotkogo для iPhone (все модели) ===')
for u, h in working.items():
    if 'ustranenie-korotkogo' in u and 'remont-iphone' in u:
        print(f'  {u}')
        print(f'     H1: {h}')

print('\n=== zamena-peredney vs zamena-perednej ===')
for u, h in working.items():
    if 'peredne' in u and 'kamery' in u and 'remont-iphone-11' in u:
        print(f'  {u}')
        print(f'     H1: {h}')
