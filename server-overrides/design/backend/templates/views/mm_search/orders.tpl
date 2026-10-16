{* Maxmobiles: свой поиск – заказы после поиска (dispatch=mm_search.orders). Данные – mm_stats_orders. *}
{capture name="mainbox"}
{include file="views/mm_search/components/tabs.tpl"}
<p class="mm-st-note">Заказы покупателей, которые искали на сайте за сутки до заказа. «Кликнули» – этот товар открывали из результатов поиска или подсказок.</p>
<table class="table table-middle">
<thead><tr><th>Когда</th><th>Заказ</th><th>Искали</th><th>Товар</th><th class="right">Кол-во</th><th class="right">Цена</th><th>Кликнули</th></tr></thead>
<tbody>
{foreach $mm_rows as $r}<tr>
    <td class="muted">{$r.when}</td>
    <td><a href="{"orders.details?order_id=`$r.order_id`"|fn_url}">#{$r.order_id}</a></td>
    <td><a href="{"mm_search.check"|fn_url}&amp;q={$r.q|escape:url}">{$r.q}</a></td>
    <td><a href="{"products.update?product_id=`$r.product_id`"|fn_url}">{$r.name|default:$r.product_id}</a></td>
    <td class="right">{$r.qty}</td>
    <td class="right">{$r.price_fmt} ₽</td>
    <td>{if $r.clicked}<span class="label label-success">да</span>{else}<span class="muted">нет</span>{/if}</td>
</tr>{foreachelse}<tr><td colspan="7" class="no-items">За этот период заказов после поиска нет.</td></tr>{/foreach}
</tbody></table>
{/capture}
{include file="common/mainbox.tpl" title="Поиск Maxmobiles: заказы после поиска" content=$smarty.capture.mainbox}
