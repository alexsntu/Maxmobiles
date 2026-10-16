{* Maxmobiles: свой поиск – раздел «Запросы» (dispatch=mm_search.queries). Данные – mm_stats_queries. *}
{capture name="mainbox"}
{include file="views/mm_search/components/tabs.tpl"}
<p class="mm-st-note">Все запросы покупателей за период. Нажмите на запрос, чтобы проверить, что и почему показывает поиск.</p>
<form action="{""|fn_url}" method="get" class="form-inline" style="margin-bottom:12px">
    <input type="hidden" name="dispatch" value="mm_search.queries"><input type="hidden" name="days" value="{$mm_days}"><input type="hidden" name="sort" value="{$mm_sort}">
    <input type="text" name="f" value="{$mm_filter}" placeholder="Фильтр по запросу" class="input-large"> <button type="submit" class="btn">Показать</button>
</form>
{include file="views/mm_search/components/queries_table.tpl" rows=$mm_rows sortable=true}
{/capture}
{include file="common/mainbox.tpl" title="Поиск Maxmobiles: Запросы" content=$smarty.capture.mainbox}
