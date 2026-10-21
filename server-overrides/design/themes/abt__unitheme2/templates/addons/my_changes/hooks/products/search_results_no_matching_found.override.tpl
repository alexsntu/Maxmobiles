{* Maxmobiles: страница «ничего не нашлось» своего поиска вместо стандартной заглушки (2026-10-21). Подменяет хук
   products:search_results_no_matching_found, только на своей витрине (company_id 1) и когда контроллер поиска
   (my_changes/controllers/frontend/products.pre.php) положил данные mm_empty (mm_search_empty в app/mm_search/lib.php).
   Пусто (чужая витрина, фильтры, сбой индекса) – подмена ничего не выводит, и тема показывает свою заглушку.
   Оформление – раздел MM-16 «Своего CSS» витрины (классы mm-ns*). *}
{if $runtime.company_id == 1 && $mm_empty}
{$e = $mm_empty}
<div class="mm-ns">
    <div class="mm-ns__head">
        <svg class="mm-ns__ico" viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M15.5 15.5 21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8.3 8.3l4.4 4.4M12.7 8.3l-4.4 4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <div>
            <div class="mm-ns__title">По запросу «{$e.q}» ничего не нашлось</div>
            <div class="mm-ns__tips">Проверьте, нет ли опечатки, или напишите проще – например, тип товара и бренд вместо полного названия модели.</div>
        </div>
    </div>
    {if $e.alts}
    <div class="mm-ns__block">
        <div class="mm-ns__h">Возможно, вы искали</div>
        <div class="mm-ns__chips">{foreach $e.alts as $a}{$u = $a.text|escape:url}<a class="mm-ns__chip mm-ns__chip--alt" href="{"products.search?search_performed=Y&q=`$u`"|fn_url}">{$a.text}<span>{$a.count}</span></a>{/foreach}</div>
    </div>
    {/if}
    {if $e.queries}
    <div class="mm-ns__block">
        <div class="mm-ns__h">Часто ищут</div>
        <div class="mm-ns__chips">{foreach $e.queries as $pq}{$u = $pq|escape:url}<a class="mm-ns__chip" href="{"products.search?search_performed=Y&q=`$u`"|fn_url}">{$pq}</a>{/foreach}</div>
    </div>
    {/if}
    {if $e.cats}
    <div class="mm-ns__block">
        <div class="mm-ns__h">Популярные разделы</div>
        <div class="mm-ns__cats">{foreach $e.cats as $c}<a class="mm-ns__cat" href="{$c.url}">{if $c.img}<img src="{$c.img}" alt="" loading="lazy">{else}<span class="mm-ns__noimg"></span>{/if}<span>{$c.name}</span></a>{/foreach}</div>
    </div>
    {/if}
    {if $e.products}
    <div class="mm-ns__block">
        <div class="mm-ns__h">Популярные товары</div>
        <div class="mm-ns__prods">{foreach $e.products as $p}<a class="mm-ns__prod" href="{$p.url}"><span class="mm-ns__pimg">{if $p.img}<img src="{$p.img}" alt="" loading="lazy">{/if}</span><span class="mm-ns__pname">{$p.name}</span><b class="mm-ns__price">{$p.price}</b></a>{/foreach}</div>
    </div>
    {/if}
    <div class="mm-ns__help">
        <div><b>Не нашли нужное?</b> Позвоните или напишите в чат – подберём замену или привезём под заказ.</div>
        <div class="mm-ns__help-btns"><a class="mm-ns__btn mm-ns__btn--main" href="tel:+78002508158">8 (800) 250-81-58</a><a class="mm-ns__btn" href="/kontakty-ru/" onclick="if (window.jivo_api) { jivo_api.open(); return false; }">Написать в чат</a></div>
    </div>
</div>
{/if}
