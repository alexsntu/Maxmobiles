import json
import sys
from collections import defaultdict

path = r"C:\Users\Алекс\.claude\projects\C--Users-------Service-MM\37b12cfd-3e97-420f-84d5-6efcf85ed31f\tool-results\mcp-pixelplus-mcp-pixelplus_get_queries-1783497099162.txt"

with open(path, encoding="utf-8") as f:
    data = json.load(f)

print("Total rows:", len(data))

by_id = {}
groups_by_id = defaultdict(set)
for row in data:
    qid = row["id"]
    by_id[qid] = row
    groups_by_id[qid].add(row["group_id"])

print("Unique query ids:", len(by_id))

freq_buckets = defaultdict(int)
zero_freq = []
low_freq = []
for qid, row in by_id.items():
    freq = int(row["frequency"]) if row["frequency"] is not None else -1
    if freq == -1:
        freq_buckets["null"] += 1
    elif freq == 0:
        freq_buckets["0"] += 1
        zero_freq.append(row["query"])
    elif freq <= 10:
        freq_buckets["1-10"] += 1
        low_freq.append((row["query"], freq))
    elif freq <= 50:
        freq_buckets["11-50"] += 1
    elif freq <= 100:
        freq_buckets["51-100"] += 1
    else:
        freq_buckets["100+"] += 1

print("Frequency buckets:", dict(freq_buckets))
print("Zero-freq count:", len(zero_freq))
print("Low-freq (1-10) count:", len(low_freq))

with open(r"C:\Users\Алекс\Service MM\scratch_zero_freq.txt", "w", encoding="utf-8") as f:
    for q in sorted(set(zero_freq)):
        f.write(q + "\n")

with open(r"C:\Users\Алекс\Service MM\scratch_low_freq.txt", "w", encoding="utf-8") as f:
    for q, freq in sorted(set(low_freq), key=lambda x: x[1]):
        f.write(f"{freq}\t{q}\n")

# all unique queries with freq for reference
with open(r"C:\Users\Алекс\Service MM\scratch_all_queries.txt", "w", encoding="utf-8") as f:
    for qid, row in sorted(by_id.items(), key=lambda x: int(x[1]["frequency"]) if x[1]["frequency"] is not None else -1):
        f.write(f"{row['frequency']}\t{row['query']}\t{qid}\n")
