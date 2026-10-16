{* Maxmobiles: страница «Мои заказы» (dispatch=mm_cabinet.orders) – заказы сайта и покупки в магазинах одним списком,
   по образцу списка заказов Ozon: слева статус и дата, справа товары картинками с ценой.
   Данные готовит app/controllers/frontend/mm_cabinet.php, оформление – секция 46 «Своего CSS» (.gm-lk*, .gm-ord*). *}
{capture name="mainbox_title"}Мои заказы{/capture}

<div class="gm-lk">
    {include file="views/mm_cabinet/components/nav.tpl"}

    <div class="gm-lk__main">
        {if $mm_cab.verify}{include file="views/mm_cabinet/components/verify.tpl"}{/if}
        <div class="gm-lk__card gm-ord-filters">
            <div class="gm-seg">
                {foreach from=$mm_cab.tabs item="tab"}
                    <a class="gm-seg__a{if $tab.active} gm-seg__a--on{/if}" href="{$tab.url}">{$tab.title}{if $tab.count} <span>{$tab.count}</span>{/if}</a>
                {/foreach}
            </div>
            <div class="gm-chips">
                {foreach from=$mm_cab.sources item="chip"}
                    {if $chip.count || $chip.active}
                        <a class="gm-chips__a{if $chip.active} gm-chips__a--on{/if}" href="{$chip.url}">{$chip.title}</a>
                    {/if}
                {/foreach}
            </div>
            {if $mm_cab.years}
                <div class="gm-chips gm-chips--years">
                    {foreach from=$mm_cab.years item="chip"}
                        <a class="gm-chips__a{if $chip.active} gm-chips__a--on{/if}" href="{$chip.url}">{$chip.title}</a>
                    {/foreach}
                </div>
            {/if}
        </div>

        {if $mm_cab.orders}
            {foreach from=$mm_cab.orders item="order"}
                <div class="gm-ord gm-ord--{$order.kind}">
                    <div class="gm-ord__info">
                        <div class="gm-ord__title">{$order.title}</div>
                        <div class="gm-ord__sub">
                            {if $order.source == "store"}Покупка в магазине{else}Заказ на сайте{/if} от {$order.when}
                        </div>
                        <div class="gm-ord__meta">
                            {if $order.url}<a href="{$order.url}">&#8470;&nbsp;{$order.number}</a>{else}&#8470;&nbsp;{$order.number}{/if}
                            &#183; {$order.sum}&nbsp;&#8381;
                        </div>
                        {if $order.review_url || $order.url}
                            <div class="gm-ord__btns">
                                {if $order.review_url}<a class="gm-ord__btn" href="{$order.review_url}">Оценить товар</a>{/if}
                                {if $order.url}<a class="gm-ord__btn gm-ord__btn--light" href="{$order.url}">Подробнее</a>{/if}
                            </div>
                        {/if}
                    </div>
                    <div class="gm-ord__items">
                        {foreach from=$order.items item="item"}
                            {if $item.url}<a class="gm-ord-item" href="{$item.url}" title="{$item.name}">{else}<div class="gm-ord-item" title="{$item.name}">{/if}
                                <span class="gm-ord-item__pic">{if $item.img}<img src="{$item.img}" alt="" loading="lazy" width="120" height="120">{/if}</span>
                                <span class="gm-ord-item__price">{$item.price}&nbsp;&#8381;{if $item.qty != "1"} <i>&#215;&nbsp;{$item.qty}</i>{/if}</span>
                                <span class="gm-ord-item__name">{$item.name}</span>
                            {if $item.url}</a>{else}</div>{/if}
                        {foreachelse}
                            <div class="gm-ord__noitems">Состав заказа появится чуть позже</div>
                        {/foreach}
                    </div>
                </div>
            {/foreach}

            {if $mm_cab.pages > 1}
                <div class="gm-pager">
                    {if $mm_cab.prev_url}<a class="gm-pager__a" href="{$mm_cab.prev_url}">&#8592; Назад</a>{/if}
                    <span class="gm-pager__now">Страница {$mm_cab.page} из {$mm_cab.pages}</span>
                    {if $mm_cab.next_url}<a class="gm-pager__a" href="{$mm_cab.next_url}">Дальше &#8594;</a>{/if}
                </div>
            {/if}
        {else}
            <div class="gm-lk__card gm-bp-empty">
                <h2 class="gm-bp__h">{if $mm_cab.total}Здесь пока пусто{else}Заказов пока нет{/if}</h2>
                <p>{if $mm_cab.total}В этом разделе заказов нет &#8211; посмотрите соседнюю вкладку.{else}Когда вы оформите заказ на сайте или купите что-нибудь в магазине Maxmobiles по своему номеру телефона, он появится здесь.{/if}</p>
            </div>
        {/if}
    </div>
</div>
