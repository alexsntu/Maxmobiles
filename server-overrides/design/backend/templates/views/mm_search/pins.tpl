{* Maxmobiles: свой поиск – закрепления (dispatch=mm_search.pins[&q=]). Данные – mm_stats_pins, mm_search_explain; POST action=add|delete|up|down. *}
{capture name="mainbox"}
{include file="views/mm_search/components/tabs.tpl"}
<p class="mm-st-note">Закреплённые товары стоят первыми по точному запросу (после обработки: регистр, «ё», числа не важны) – в результатах и в подсказках.
Введите запрос, посмотрите текущую выдачу и закрепите нужный товар.</p>
<form action="{""|fn_url}" method="get" class="form-inline" style="margin-bottom:14px">
    <input type="hidden" name="dispatch" value="mm_search.pins">
    <input type="text" name="q" value="{$mm_q}" placeholder="Запрос, например: наушники" class="input-xlarge" required> <button type="submit" class="btn btn-primary">Показать выдачу</button>
</form>
{if $mm_e}
<h4>Выдача по «{$mm_q}» сейчас</h4>
<table class="table table-middle">
<thead><tr><th>#</th><th>Товар</th><th>&nbsp;</th></tr></thead>
<tbody>
{foreach $mm_e.items as $i}<tr>
    <td>{$i.pos}</td>
    <td><a href="{$i.url}" target="_blank">{$i.name}</a> <span class="muted">#{$i.id}</span>{if !$i.in_stock} <span class="label">нет в наличии</span>{/if}{if $i.pinned} <span class="label label-success">закреплён</span>{/if}</td>
    <td class="right">{if !$i.pinned}
        <form action="{""|fn_url}" method="post" style="margin:0">
            <input type="hidden" name="dispatch" value="mm_search.pins"><input type="hidden" name="action" value="add">
            <input type="hidden" name="q" value="{$mm_q}"><input type="hidden" name="product_id" value="{$i.id}">
            <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
            <button type="submit" class="btn btn-small">Закрепить наверх</button>
        </form>{/if}
    </td>
</tr>{foreachelse}<tr><td colspan="3" class="no-items">Ничего не найдено – закрепите товар по его ID ниже.</td></tr>{/foreach}
</tbody></table>
<form action="{""|fn_url}" method="post" class="form-inline" style="margin-bottom:20px">
    <input type="hidden" name="dispatch" value="mm_search.pins"><input type="hidden" name="action" value="add"><input type="hidden" name="q" value="{$mm_q}">
    <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
    Товара нет в выдаче? ID товара: <input type="text" name="product_id" value="" class="input-small" required> <button type="submit" class="btn">Закрепить</button>
</form>
{/if}
<h4>Все закрепления</h4>
<table class="table table-middle">
<thead><tr><th>Запрос</th><th>Место</th><th>Товар</th><th>Добавлено</th><th>&nbsp;</th></tr></thead>
<tbody>
{foreach $mm_rows as $r}<tr>
    <td><a href="{"mm_search.pins"|fn_url}&amp;q={$r.q|escape:url}">{$r.q}</a></td>
    <td>{$r.position}
        {foreach ["up" => "↑", "down" => "↓"] as $act => $arrow}
        <form action="{""|fn_url}" method="post" style="margin:0;display:inline">
            <input type="hidden" name="dispatch" value="mm_search.pins"><input type="hidden" name="action" value="{$act}"><input type="hidden" name="id" value="{$r.id}">
            <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}"><button type="submit" class="btn btn-small">{$arrow}</button>
        </form>{/foreach}
    </td>
    <td><a href="{"products.update?product_id=`$r.product_id`"|fn_url}">{$r.name|default:$r.product_id}</a></td>
    <td class="muted">{$r.when}</td>
    <td class="right">
        <form action="{""|fn_url}" method="post" style="margin:0">
            <input type="hidden" name="dispatch" value="mm_search.pins"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="{$r.id}">
            <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}"><button type="submit" class="btn btn-small">Снять</button>
        </form>
    </td>
</tr>{foreachelse}<tr><td colspan="5" class="no-items">Закреплений пока нет.</td></tr>{/foreach}
</tbody></table>
{/capture}
{include file="common/mainbox.tpl" title="Поиск Maxmobiles: закрепления" content=$smarty.capture.mainbox}
