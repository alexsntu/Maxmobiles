{* Таблица запросов: $rows (mm_stats_queries), $sortable – ссылки сортировки *}
<div class="table-responsive-wrapper">
<table class="table table-middle table--relative">
<thead><tr>
    <th>Запрос</th>
    <th class="right">{if $sortable}<a href="{"mm_search.`$mm_mode`?days=`$mm_days`&sort=searches&f=`$mm_filter`"|fn_url}">Искали</a>{else}Искали{/if}</th>
    <th class="right">{if $sortable}<a href="{"mm_search.`$mm_mode`?days=`$mm_days`&sort=total&f=`$mm_filter`"|fn_url}">Найдено</a>{else}Найдено{/if}</th>
    <th class="right">{if $sortable}<a href="{"mm_search.`$mm_mode`?days=`$mm_days`&sort=ctr&f=`$mm_filter`"|fn_url}">С кликом</a>{else}С кликом{/if}</th>
    <th class="right">{if $sortable}<a href="{"mm_search.`$mm_mode`?days=`$mm_days`&sort=pos&f=`$mm_filter`"|fn_url}">Место клика</a>{else}Место клика{/if}</th>
    <th class="right">Корзина</th>
    <th class="right">{if $sortable}<a href="{"mm_search.`$mm_mode`?days=`$mm_days`&sort=orders&f=`$mm_filter`"|fn_url}">Заказы</a>{else}Заказы{/if}</th>
    <th class="right">{if $sortable}<a href="{"mm_search.`$mm_mode`?days=`$mm_days`&sort=revenue&f=`$mm_filter`"|fn_url}">Выручка</a>{else}Выручка{/if}</th>
    <th>Как нашлось</th>
    <th class="right">{if $sortable}<a href="{"mm_search.`$mm_mode`?days=`$mm_days`&sort=last&f=`$mm_filter`"|fn_url}">Последний</a>{else}Последний{/if}</th>
</tr></thead>
<tbody>
{foreach $rows as $r}
<tr>
    <td><a href="{"mm_search.check"|fn_url}&amp;q={$r.q|escape:url}" title="Проверить запрос">{$r.q}</a></td>
    <td class="right">{$r.searches}</td>
    <td class="right">{if $r.total == 0}<span class="label label-important">0</span> <a href="{"mm_search.synonyms"|fn_url}&amp;src={$r.q|escape:url}" class="btn btn-small" title="Добавить синоним для этого запроса">+ синоним</a>{else}{$r.total}{/if}</td>
    <td class="right">{if $r.total > 0}{$r.clicked} <span class="muted">({$r.ctr}%)</span>{else}–{/if}</td>
    <td class="right">{$r.pos|default:"–"}</td>
    <td class="right">{$r.carts|default:"–"}</td>
    <td class="right">{$r.orders|default:"–"}</td>
    <td class="right">{$r.revenue_fmt|default:"–"}</td>
    <td><span class="muted">{$r.mode}</span></td>
    <td class="right muted">{$r.last}</td>
</tr>
{foreachelse}
<tr><td colspan="10" class="no-items">За этот период данных нет.</td></tr>
{/foreach}
</tbody>
</table>
</div>
