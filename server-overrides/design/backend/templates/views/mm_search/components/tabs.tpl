{* Maxmobiles: свой поиск – разделы админки и выбор периода (общая шапка страниц mm_search.*) *}
<style>{literal}
.mm-st-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px}
.mm-st-tabs a{padding:6px 12px;border:1px solid #d7dbe0;border-radius:16px;color:#333;text-decoration:none}
.mm-st-tabs a.on{border-color:#0071e3;background:#0071e3;color:#fff}
.mm-st-per{margin:0 0 16px}.mm-st-per a{margin-right:10px}.mm-st-per a.on{font-weight:bold;color:#000;text-decoration:none}
.mm-st-kpi{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin:0 0 20px}
.mm-st-kpi div{padding:12px 14px;border:1px solid #e3e6ea;border-radius:10px;background:#fff}
.mm-st-kpi b{display:block;font-size:22px;line-height:1.2}.mm-st-kpi span{font-size:12px;color:#777}
.mm-st-kpi .bad b{color:#c0392b}.mm-st-kpi .good b{color:#1e8e3e}
.mm-st-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:20px}
.mm-st-note{color:#777;font-size:12px;margin:4px 0 12px}
.mm-st-f{white-space:nowrap;font-size:12px;color:#555}
{/literal}</style>
<div class="mm-st-tabs">
    {foreach $mm_tabs as $k => $t}
        <a href="{"mm_search.`$k`?days=`$mm_days`"|fn_url}" class="{if $mm_mode == $k}on{/if}">{$t}</a>
    {/foreach}
</div>
{if $mm_mode != "check" && $mm_mode != "index"}
<div class="mm-st-per">Период:
    {foreach $mm_periods as $d => $t}
        <a href="{"mm_search.`$mm_mode`?days=`$d`"|fn_url}" class="{if $mm_days == $d}on{/if}">{$t}</a>
    {/foreach}
</div>
{/if}
