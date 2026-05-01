import sys
sys.stdout.reconfigure(encoding='utf-8')

KEYWORDS = ['glazka-kamery', 'popala-voda', 'ustranenie-korotkogo', 'materinskoy-platy', 'peredney-kamery']

for logfile, label in [
    (r'c:\Users\Алекс\Service MM\Сервис i\замены_лог.txt', 'Гарантии'),
    (r'c:\Users\Алекс\Service MM\Сервис i\замены_лог_prices.txt', 'Prices'),
]:
    print(f'=== {label} ===')
    with open(logfile, encoding='utf-8') as f:
        lines = f.readlines()
    i = 0
    while i < len(lines):
        line = lines[i].strip()
        if line.startswith('СТАРЫЙ:') and any(kw in line for kw in KEYWORDS):
            new_line = lines[i+1].strip() if i+1 < len(lines) else ''
            print(f'  {line}')
            print(f'  {new_line}')
            print()
        i += 1
