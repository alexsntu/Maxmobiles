{* Maxmobiles: свой поиск – свои синонимы (dispatch=mm_search.synonyms). Данные – mm_stats_synonyms; сохранение – POST action=save|toggle|delete. *}
{capture name="mainbox"}
{include file="views/mm_search/components/tabs.tpl"}
<p class="mm-st-note">Что пишут покупатели → как это называется в каталоге. Работает сразу в подсказках и результатах, без пересборки индекса.
Встроенных синонимов: {$mm_builtin} (айфон → iphone, эпл → apple, пс5 → playstation 5 и т. п.); свои – главнее при совпадении.
Проверить, как поиск понял запрос, – в «Проверке запроса».</p>
<form action="{""|fn_url}" method="post" class="form-inline" style="margin-bottom:16px">
    <input type="hidden" name="dispatch" value="mm_search.synonyms"><input type="hidden" name="action" value="save">
    <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
    <input type="text" name="src" value="{$mm_src}" placeholder="Что пишут (например, эирпод)" class="input-large" required>
    → <input type="text" name="dst" value="" placeholder="Как в каталоге (например, airpods)" class="input-large" required>
    <input type="text" name="note" value="" placeholder="Заметка (необязательно)" class="input-medium">
    <button type="submit" class="btn btn-primary">Добавить</button>
</form>
{$l = $mm_list}
<form action="{""|fn_url}" method="get" class="form-inline" style="margin-bottom:10px">
    <input type="hidden" name="dispatch" value="mm_search.synonyms">
    <input type="text" name="find" value="{$l.find}" placeholder="Найти синоним: слово, перевод или заметка" class="input-xlarge"> <button type="submit" class="btn">Найти</button>
    {if $l.find}<a href="{"mm_search.synonyms"|fn_url}" class="btn btn-link">Сбросить</a>{/if}
    <span class="muted" style="margin-left:10px">{if $l.find}Найдено{else}Всего{/if}: {$l.total}</span>
</form>
<table class="table table-middle">
<thead><tr><th>Что пишут</th><th>Как в каталоге</th><th>Заметка</th><th>Добавлен</th><th>Работает</th><th>&nbsp;</th></tr></thead>
<tbody>
{foreach $mm_rows as $r}<tr{if !$r.active} class="muted"{/if}>
    <td><a href="{"mm_search.check"|fn_url}&amp;q={$r.src|escape:url}">{$r.src}</a></td>
    <td>{$r.dst}</td><td class="muted">{$r.note}</td><td class="muted">{$r.when}</td>
    <td>
        <form action="{""|fn_url}" method="post" style="margin:0;display:inline">
            <input type="hidden" name="dispatch" value="mm_search.synonyms"><input type="hidden" name="action" value="toggle"><input type="hidden" name="find" value="{$l.find}"><input type="hidden" name="page" value="{$l.page}"><input type="hidden" name="id" value="{$r.id}">
            <input type="hidden" name="active" value="{if $r.active}0{else}1{/if}"><input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
            <button type="submit" class="btn btn-small">{if $r.active}Выключить{else}Включить{/if}</button>
        </form>
    </td>
    <td class="right">
        <form action="{""|fn_url}" method="post" style="margin:0;display:inline">
            <input type="hidden" name="dispatch" value="mm_search.synonyms"><input type="hidden" name="action" value="delete"><input type="hidden" name="find" value="{$l.find}"><input type="hidden" name="page" value="{$l.page}"><input type="hidden" name="id" value="{$r.id}">
            <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
            <button type="submit" class="btn btn-small">Удалить</button>
        </form>
    </td>
</tr>{foreachelse}<tr><td colspan="6" class="no-items">Своих синонимов пока нет. Подсказки, что добавить, – в «Ничего не найдено» и «Подсказки → брошенный ввод».</td></tr>{/foreach}
</tbody></table>
{if $l.pages > 1}
<div class="pagination" style="margin:10px 0"><ul>
    {if $l.page > 1}<li><a href="{"mm_search.synonyms"|fn_url}&amp;find={$l.find|escape:url}&amp;page={$l.page - 1}">‹</a></li>{/if}
    {for $p = 1 to $l.pages}
        {if $p == $l.page}<li class="active"><span>{$p}</span></li>{else}<li><a href="{"mm_search.synonyms"|fn_url}&amp;find={$l.find|escape:url}&amp;page={$p}">{$p}</a></li>{/if}
    {/for}
    {if $l.page < $l.pages}<li><a href="{"mm_search.synonyms"|fn_url}&amp;find={$l.find|escape:url}&amp;page={$l.page + 1}">›</a></li>{/if}
</ul></div>
{/if}
{/capture}
{include file="common/mainbox.tpl" title="Поиск Maxmobiles: синонимы" content=$smarty.capture.mainbox}
