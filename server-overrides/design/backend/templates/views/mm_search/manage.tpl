{* Maxmobiles: свой поиск – обзор (dispatch=mm_search.manage). Данные – mm_stats_overview, mm_stats_queries. *}
{capture name="mainbox"}
{include file="views/mm_search/components/tabs.tpl"}
{$o = $mm_o}
<div class="mm-st-kpi">
    <div><b>{$o.searches}</b><span>поисков ({$o.sessions} покупателей, {$o.queries} разных запросов)</span></div>
    <div class="{if $o.zero_pct > 5}bad{/if}"><b>{$o.zero_pct}%</b><span>ничего не найдено ({$o.zero})</span></div>
    <div class="{if $o.noclick_pct > 50}bad{/if}"><b>{$o.noclick_pct}%</b><span>нашли, но не кликнули ({$o.noclick})</span></div>
    <div class="{if $o.ctr >= 50}good{/if}"><b>{$o.ctr}%</b><span>поисков с кликом по товару ({$o.clicked})</span></div>
    <div><b>{$o.pos|default:"–"}</b><span>среднее место клика; в первой тройке – {$o.first_pos}%</span></div>
    <div><b>{$o.cart_pct}%</b><span>поисков с корзиной ({$o.cart})</span></div>
    <div><b>{$o.orders}</b><span>заказов после поиска ({$o.order_pct}% поисков)</span></div>
    <div><b>{$o.revenue_fmt} ₽</b><span>выручка заказов после поиска</span></div>
    <div><b>{$o.bought_clicked}</b><span>куплен товар, по которому кликнули</span></div>
    <div><b>{$o.ms} мс</b><span>среднее время ответа поиска</span></div>
</div>
<p class="mm-st-note">Поиск – это «покупатель + запрос»: обновления страницы, листание и сортировка не считаются новыми поисками.</p>

<div class="mm-st-cols">
<div>
    <h4>Как нашлось</h4>
    <table class="table table-middle"><tbody>
    {foreach $o.modes as $m => $n}<tr><td>{if $m == "all"}все слова{elseif $m == "prefix"}по началу слова{elseif $m == "fuzzy"}опечатка / раскладка{elseif $m == "translit"}русское название латиницей{elseif $m == "quorum"}часть слов (60%){elseif $m == "none"}ничего не нашлось{else}{$m}{/if}</td><td class="right">{$n}</td></tr>{/foreach}
    </tbody></table>
    <h4>Устройства</h4>
    <table class="table table-middle"><tbody>
    {foreach $o.devices as $d => $n}<tr><td>{if $d == "m"}телефон{else}ПК{/if}</td><td class="right">{$n}</td></tr>{/foreach}
    </tbody></table>
    <h4>Подсказки</h4>
    <table class="table table-middle"><tbody>
    {foreach $o.suggest as $s => $n}<tr><td>{if $s == "sg_query"}выбрали вариант запроса{elseif $s == "sg_history"}выбрали из истории{elseif $s == "sg_scope"}выбрали «в категории»{elseif $s == "sg_product"}перешли к товару из подсказок{elseif $s == "sg_all"}«Все N товаров»{elseif $s == "tile"}плитка категории в результатах{elseif $s == "sg_chip"}плашка-уточнение в подсказках{elseif $s == "chip"}плашка «Уточнить» в результатах{elseif $s == "sg_exit"}набрали и ушли (подсказки были){elseif $s == "sg_none"}набрали и ушли (подсказок не было){else}{$s}{/if}</td><td class="right">{$n}</td></tr>{foreachelse}<tr><td class="no-items">Нет данных</td></tr>{/foreach}
    </tbody></table>
</div>
<div>
    <h4>По дням</h4>
    <table class="table table-middle">
    <thead><tr><th>День</th><th class="right">Поисков</th><th class="right">Ничего не найдено</th><th class="right">С кликом</th></tr></thead>
    <tbody>
    {foreach $o.days as $d}<tr><td>{$d.d}</td><td class="right">{$d.searches}</td><td class="right">{$d.zero_pct}%</td><td class="right">{$d.ctr}%</td></tr>{foreachelse}<tr><td colspan="4" class="no-items">Нет данных</td></tr>{/foreach}
    </tbody></table>
</div>
</div>

<h4>Частые запросы</h4>
{include file="views/mm_search/components/queries_table.tpl" rows=$mm_top sortable=false}
<div class="mm-st-cols">
<div><h4>Ничего не найдено</h4>{include file="views/mm_search/components/queries_table.tpl" rows=$mm_top_zero sortable=false}</div>
<div><h4>Нашли, но не кликнули</h4>{include file="views/mm_search/components/queries_table.tpl" rows=$mm_top_noclick sortable=false}</div>
</div>
{/capture}
{include file="common/mainbox.tpl" title="Поиск Maxmobiles" content=$smarty.capture.mainbox}
