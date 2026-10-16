{* Maxmobiles: страница «Купленные товары» (dispatch=mm_cabinet.products) – товары из выданных заказов сайта и покупок
   в магазинах, по одному на товар, свежие покупки первыми. Данные готовит app/controllers/frontend/mm_cabinet.php
   (fn_mm_cab_purchased), оформление – секция 57 «Своего CSS» (.gm-pp*). *}
{capture name="mainbox_title"}Купленные товары{/capture}

<div class="gm-lk">
    {include file="views/mm_cabinet/components/nav.tpl"}

    <div class="gm-lk__main">
        {if $mm_cab.verify}{include file="views/mm_cabinet/components/verify.tpl"}{/if}

        {if $mm_cab.items}
            <div class="gm-lk__card gm-pp">
                {foreach from=$mm_cab.items item="item"}
                    <div class="gm-pp-item">
                        {if $item.url}<a class="gm-pp-item__pic" href="{$item.url}">{else}<span class="gm-pp-item__pic">{/if}{if $item.img}<img src="{$item.img}" alt="" loading="lazy" width="240" height="240">{/if}{if $item.url}</a>{else}</span>{/if}
                        {if $item.url}<a class="gm-pp-item__name" href="{$item.url}">{$item.name}</a>{else}<span class="gm-pp-item__name">{$item.name}</span>{/if}
                        <span class="gm-pp-item__meta">{$item.price}&nbsp;&#8381; &#183; {$item.when}{if $item.times > 1} &#183; покупок: {$item.times}{/if}</span>
                        <span class="gm-pp-item__btns">
                            {if $item.url}<a class="gm-pp-item__btn" href="{$item.url}">Купить снова</a>{else}<span class="gm-pp-item__off">Нет в продаже</span>{/if}
                            {if $item.review_url}<a class="gm-pp-item__btn gm-pp-item__btn--light" href="{$item.review_url}">Оценить</a>{elseif $item.reviewed}<span class="gm-pp-item__off">Отзыв оставлен</span>{/if}
                        </span>
                    </div>
                {/foreach}
            </div>
            {if !$mm_cab.complete}<p class="gm-pp-note">Список ещё пополняется покупками из магазинов &#8211; обновите страницу через минуту.</p>{/if}

            {if $mm_cab.pages > 1}
                <div class="gm-pager">
                    {if $mm_cab.prev_url}<a class="gm-pager__a" href="{$mm_cab.prev_url}">&#8592; Назад</a>{/if}
                    <span class="gm-pager__now">Страница {$mm_cab.page} из {$mm_cab.pages}</span>
                    {if $mm_cab.next_url}<a class="gm-pager__a" href="{$mm_cab.next_url}">Дальше &#8594;</a>{/if}
                </div>
            {/if}
        {else}
            <div class="gm-lk__card gm-bp-empty">
                <h2 class="gm-bp__h">Здесь появятся купленные товары</h2>
                <p>Товары попадают сюда после получения заказа &#8211; с сайта или из магазина Maxmobiles.</p>
                <a class="gm-bp-btn" href="{$mm_cab.catalog_url}">Перейти в каталог</a>
            </div>
        {/if}
    </div>
</div>
