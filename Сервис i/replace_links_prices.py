"""Замена 404-ссылок в div class=pricest.txt"""
import re, sys, openpyxl
sys.stdout.reconfigure(encoding='utf-8')
sys.path.insert(0, r'c:\Users\Алекс\Service MM\Сервис i')
from replace_links_core import find_replacement, extract_anchors, make_exact_replacement

INPUT_FILE  = r'c:\Users\Алекс\Service MM\Сервис i\div class=pricest.txt'
OUTPUT_FILE = r'c:\Users\Алекс\Service MM\Сервис i\div class=pricest_fixed.txt'
LOG_FILE    = r'c:\Users\Алекс\Service MM\Сервис i\замены_лог_prices.txt'
XLSX_FILE   = r'c:\Users\Алекс\Service MM\Сервис i\export_irepair.ru_Все ссылки1.xlsx'

wb = openpyxl.load_workbook(XLSX_FILE)
ws = wb.active
headers = [cell.value for cell in ws[3]]
h1_idx = headers.index('H1')

working_urls = []
url_to_h1 = {}
for row in ws.iter_rows(min_row=4, values_only=True):
    if row[1] and row[4] == 200:
        url = str(row[1]).rstrip('/')
        h1  = str(row[h1_idx]).strip() if row[h1_idx] else ''
        if ('/catalog/' in url and
                not any(url.endswith(ext) for ext in ['.css','.js','.png','.jpg','.jpeg','.gif','.webp','.ico'])):
            working_urls.append(url)
            url_to_h1[url] = h1

working_set = set(working_urls)
print(f'Рабочих URL в xlsx: {len(working_urls)}')

with open(INPUT_FILE, 'r', encoding='utf-8') as f:
    content = f.read()

anchor_texts = extract_anchors(content)
print(f'Якорных текстов в файле: {len(anchor_texts)}')

all_links = re.findall(r'href=["\'](.*?)["\'>]', content)
normalized_links = set()
for l in all_links:
    if l.startswith('http'):
        normalized_links.add(l.rstrip('/'))
    elif l.startswith('/') and l != '/':
        normalized_links.add('https://irepair.ru' + l.rstrip('/'))

broken = {u for u in normalized_links if u not in working_set}
print(f'Всего ссылок: {len(normalized_links)}, битых: {len(broken)}')

mapping, unmatched = {}, []
for broken_url in sorted(broken):
    replacement = find_replacement(broken_url, working_urls, working_set, url_to_h1, anchor_texts)
    if replacement and replacement != broken_url:
        mapping[broken_url] = replacement
    else:
        unmatched.append(broken_url)

print(f'Найдено замен: {len(mapping)}, не нашли: {len(unmatched)}')

new_content = content
replaced_count = 0
for broken_url, new_url in sorted(mapping.items(), key=lambda x: len(x[0]), reverse=True):
    relative = broken_url.replace('https://irepair.ru', '')
    new_relative = new_url.replace('https://irepair.ru', '')
    for old_v, new_v in [(broken_url, new_url), (relative, new_relative)]:
        new_content, cnt = make_exact_replacement(old_v, new_v, new_content)
        if cnt > 0:
            replaced_count += cnt
            break

print(f'Заменено вхождений: {replaced_count}')

with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
    f.write(new_content)
print(f'Файл сохранён: {OUTPUT_FILE}')

with open(LOG_FILE, 'w', encoding='utf-8') as f:
    f.write('=== ВЫПОЛНЕННЫЕ ЗАМЕНЫ ===\n\n')
    for old, new in sorted(mapping.items()):
        anchor = anchor_texts.get(old, '')
        h1     = url_to_h1.get(new, '')
        f.write(f'СТАРЫЙ: {old}\nАНКОР:  {anchor}\nНОВЫЙ:  {new}\nH1:     {h1}\n\n')
    f.write(f'\n=== НЕ НАЙДЕНЫ ЗАМЕНЫ ({len(unmatched)}) ===\n\n')
    for u in sorted(unmatched):
        f.write(f'{u}\n')
print(f'Лог сохранён: {LOG_FILE}')
