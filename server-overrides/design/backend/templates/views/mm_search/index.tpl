{* Maxmobiles: свой поиск – индекс (dispatch=mm_search.index). Данные – mm_stats_index; сборка – /root/mm-search (cron). *}
{capture name="mainbox"}
{include file="views/mm_search/components/tabs.tpl"}
{$i = $mm_i}
{if !$i.ok}<p class="text-error">Поисковый движок не отвечает (служба manticore на сервере).</p>{else}
<div class="mm-st-kpi">
    <div><b>{$i.products}</b><span>товаров в индексе</span></div>
    <div><b>{$i.in_stock}</b><span>в наличии</span></div>
    <div><b>{$i.with_demand}</b><span>со спросом за 90 дней (продажи МойСклад)</span></div>
    <div><b>{$i.acc}</b><span>аксессуаров</span></div>
    <div><b>{$i.used}</b><span>«Идеальное БУ»</span></div>
    <div><b>{$i.services}</b><span>услуг ремонта</span></div>
    <div class="{if $i.no_img}bad{/if}"><b>{$i.no_img}</b><span>без фото</span></div>
    <div><b>{$i.categories}</b><span>категорий</span></div>
    <div><b>{$i.suggest}</b><span>фраз в словаре подсказок</span></div>
</div>
<p class="mm-st-note">Индекс пересобирается сам каждые 2 часа (после пересчёта остатков), спрос за 90 дней – раз в сутки в 05:10.</p>
<form action="{""|fn_url}" method="post" style="margin-bottom:16px">
    <input type="hidden" name="dispatch" value="mm_search.index"><input type="hidden" name="action" value="rebuild">
    <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
    <button type="submit" class="btn btn-primary"{if $mm_flag} disabled{/if}>{if $mm_flag}Пересборка поставлена в очередь…{else}Пересобрать индекс сейчас{/if}</button>
</form>
<h4>Последние запуски</h4>
<table class="table table-middle">
<thead><tr><th>Когда</th><th>Что</th><th class="right">Секунд</th><th class="right">Записей</th><th>Итог</th></tr></thead>
<tbody>
{foreach $i.runs as $r}<tr><td>{$r.when}</td><td>{if $r.kind == "index"}индекс{elseif $r.kind == "demand"}спрос за 90 дней{else}{$r.kind}{/if}</td><td class="right">{$r.sec}</td><td class="right">{$r.items}</td><td class="muted">{$r.note}</td></tr>{foreachelse}<tr><td colspan="5" class="no-items">Запусков ещё не было</td></tr>{/foreach}
</tbody></table>
{/if}
{/capture}
{include file="common/mainbox.tpl" title="Поиск Maxmobiles: индекс" content=$smarty.capture.mainbox}
