Semantic core cleanup progress (PixelPlus project 63602, maxmobiles.ru)

Source lists (700 total, combined in this order):
1. scratch_batch4_rest_uncertain.txt (655 queries)
2. scratch_batch3_applewatch_uncertain.txt (45 queries)

Progress log: scratch_v2_log.tsv (tab-separated: frequency<TAB>query, or RETRY<TAB>query for 429 failures)
- Already checked: 99 queries (chunks 0-3 of scratch_v2_chunk_N.json)
- 1 pending retry: "iphone 17 pro оранжевый купить"
- Remaining unchecked: starts at chunk 4 (scratch_v2_chunk_4.json onward, chunks 4-27, ~601 queries)

To resume: read scratch_v2_log.tsv to see what's done, continue with wordstat_bulk (quoted phrases, regions 977+959, delay_ms 2000) on scratch_v2_chunk_4.json through scratch_v2_chunk_27.json in order. Append each new result to scratch_v2_log.tsv in the same format.

When quota (429) hits again, stop, regenerate the deletion batch file from scratch_v2_log.tsv (freq <= 1 -> delete, freq > 1 -> keep), save to Downloads as PixelPlus_к_удалению_партияN.txt (increment N), and report to user.

Batch numbering so far delivered to user:
- Партия 1: 1759 (deep-tail combinatorial, file scratch_deep_tail.txt copied to Downloads)
- Партия 2: 24 (apple-prefix pattern, presented inline in chat)
- Партия 3: delivered from first 99 checked here (90 delete candidates) -> Downloads/PixelPlus_к_удалению_партия3.txt
- Next: Партия 4 (from chunks 4+ once quota allows)
