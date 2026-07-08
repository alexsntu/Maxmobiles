# -*- coding: utf-8 -*-
import openpyxl
from collections import defaultdict

path = r'c:\Users\Алекс\Downloads\positions-detail_maxmobiles_ru_Maxmobiles_2026_07_08.xlsx'
wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
ws = wb['Worksheet']

rows = list(ws.iter_rows(min_row=4, values_only=True))
print("data rows:", len(rows))

def to_pos(v):
    # Returns a real numeric position only when actually ranked within tracked window.
    # "-", "10+", "50+", None => not ranked (return None).
    if v is None:
        return None
    if isinstance(v, (int, float)):
        return int(v)
    s = str(v).strip()
    if s in ('-', '') or s.endswith('+'):
        return None
    try:
        return int(s)
    except Exception:
        return None

# columns: 0 Запрос,1 Группа,2 Частота,3 URL,4 ПС,5 pos1,6 relURL1,7 freq1,8 pos2,9 relURL2,10 freq2,11 avg
query_data = defaultdict(lambda: {"group": None, "freq_col": None, "engines": {}, "best_pos": None})

for r in rows:
    if not r or r[0] is None:
        continue
    q = str(r[0]).strip()
    group = str(r[1]).strip() if r[1] else None
    freq = r[2]
    engine = str(r[4]).strip() if r[4] else None
    pos1 = to_pos(r[5])
    pos2 = to_pos(r[8])
    avg = to_pos(r[11])

    qd = query_data[q]
    qd["group"] = group
    qd["freq_col"] = freq
    qd["engines"][engine] = {"pos1": r[5], "pos2": r[8], "avg": r[11]}

    for p in (pos1, pos2, avg):
        if p is not None:
            if qd["best_pos"] is None or p < qd["best_pos"]:
                qd["best_pos"] = p

print("unique queries in xlsx:", len(query_data))

no_reach = []  # never ranks in top tracked window on any engine/date
has_reach = []
for q, qd in query_data.items():
    if qd["best_pos"] is None:
        no_reach.append(q)
    else:
        has_reach.append(q)

print("no reach (never in top window, any engine/date):", len(no_reach))
print("has reach (ranked somewhere):", len(has_reach))

with open(r'C:\Users\Алекс\Service MM\scratch_no_reach.txt', 'w', encoding='utf-8') as f:
    for q in sorted(no_reach):
        freq = query_data[q]["freq_col"]
        group = query_data[q]["group"]
        f.write(f"{freq}\t{group}\t{q}\n")

with open(r'C:\Users\Алекс\Service MM\scratch_has_reach.txt', 'w', encoding='utf-8') as f:
    for q in sorted(has_reach, key=lambda x: query_data[x]["best_pos"]):
        f.write(f"{query_data[q]['best_pos']}\t{query_data[q]['freq_col']}\t{query_data[q]['group']}\t{q}\n")
