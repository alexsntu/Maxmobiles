{* Maxmobiles: общие части карточки товара для шаблонов maxmobiles_card.tpl (ПК) и maxmobiles_card_mobile.tpl (телефон).
   Параметр part: meta | bonus | buybox | extras. Данные – функции модуля mm_delivery (func.php).
   Стили – секция MM-14 «Своего CSS» Maxmobiles. *}

{if $part == "meta"}
    {* Рейтинг магазина вместо пустых звёзд; звёзды товара – только если у него есть отзывы *}
    <div class="mm-meta">
        {if $product.average_rating > 0}
            <span class="mm-meta__rating">{include file="blocks/product_templates/components/product_rating.tpl"}</span>
        {/if}
        <a class="mm-meta__store" href="https://yandex.ru/maps/org/237809440282" target="_blank" rel="noopener nofollow">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3 6.9 7.5.6-5.7 4.9 1.8 7.3L12 17.8 5.4 21.7l1.8-7.3L1.5 9.5 9 8.9z" fill="#ffb800"/></svg>
            <b>5,0</b> магазин на Яндекс Картах · 1000+ отзывов
        </a>
        {if $show_sku == "true" && $product.product_code|trim}
            <span class="mm-meta__sku">{$sku = "sku_`$obj_id`"}{$smarty.capture.$sku nofilter}</span>
        {/if}
    </div>
    {hook name="products:promo_text"}
        {if $product.promo_text}
            <div class="mm-note">{$product.promo_text nofilter}</div>
        {/if}
    {/hook}

{elseif $part == "bonus"}
    {$mm_bonus = $product|fn_mm_delivery_bonus}
    {if $mm_bonus.pro > 0}
        <details class="mm-bonus">
            <summary>
                <img src="/images/mm_cabinet/card-pro.webp" alt="Бонусная карта Maxmobiles" width="58" height="37" loading="lazy">
                <span class="mm-bonus__main"><b>+{$mm_bonus.pro|fn_mm_delivery_num} бонусов</b> на карту Maxmobiles<small>1 бонус = 1 ₽ · можно оплатить до 40% следующей покупки</small></span>
                <svg class="mm-bonus__arr" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="#6e6e73" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </summary>
            <div class="mm-bonus__body">
                <div class="mm-bonus__lv">
                    <div><img src="/images/mm_cabinet/card-pro.webp" alt="" width="64" height="41" loading="lazy"><span><b>Pro · {$mm_bonus.pro_pct}%</b>+{$mm_bonus.pro|fn_mm_delivery_num} бонусов</span></div>
                    <div><img src="/images/mm_cabinet/card-ultra.webp" alt="" width="64" height="41" loading="lazy"><span><b>Ultra · {$mm_bonus.ultra_pct}%</b>+{$mm_bonus.ultra|fn_mm_delivery_num} бонусов</span></div>
                </div>
                <ul>
                    <li>До <b>300 бонусов</b> на первую покупку при регистрации</li>
                    <li>Начисляются через 14 дней после покупки</li>
                    <li>Карта всегда в телефоне – Apple Wallet и Google Wallet</li>
                </ul>
                <a href="https://maxmobiles.ru/bonusnaya-programma-ru/">Как работает бонусная программа →</a>
            </div>
        </details>
    {/if}

{elseif $part == "buybox"}
    {* Наличие, кнопки CS-Cart (в корзину, быстрый заказ, избранное, сравнение), срок получения *}
    {$mm_dd = $product.product_id|fn_mm_delivery_card}
    <div class="mm-buy{if $mm_dd && !$mm_dd.in_stock} mm-buy--pre{/if}">
        <div class="js-mm-stock">{if $mm_dd}{$mm_dd.stock nofilter}{/if}</div>

        {if $capture_buttons}{capture name="buttons"}{/if}
        <div class="ut2-pb__button ty-product-block__button">
            {if $show_qty}
                <div class="ut2-qty__wrap {if $min_qty && $product.min_qty}min-qty{/if}">
                    {if $capture_options_vs_qty}{capture name="product_options"}{$smarty.capture.product_options nofilter}{/if}
                    {assign var="qty" value="qty_`$obj_id`"}
                    {$smarty.capture.$qty nofilter}
                    {assign var="min_qty" value="min_qty_`$obj_id`"}
                    {$smarty.capture.$min_qty nofilter}
                    {if $capture_options_vs_qty}{/capture}{/if}
                </div>
            {/if}
            {if $show_details_button}
                {include file="buttons/button.tpl" but_href="products.view?product_id=`$product.product_id`" but_text=__("view_details") but_role="submit"}
            {/if}

            {assign var="add_to_cart" value="add_to_cart_`$obj_id`"}
            {$smarty.capture.$add_to_cart nofilter}

            {assign var="list_buttons" value="list_buttons_`$obj_id`"}
            {$smarty.capture.$list_buttons nofilter}
        </div>
        {if $capture_buttons}{/capture}{/if}

        <div class="js-mm-dd">{if $mm_dd}{$mm_dd.dd nofilter}{/if}</div>
    </div>

{elseif $part == "extras"}
    {$mm_flags = $product.product_id|fn_mm_delivery_flags}
    {if $mm_flags.smartphone}
        <a class="mm-ti" href="https://maxmobiles.ru/apple-trade-in/trade-in-iphone/">
            <span class="mm-ti__ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12a8 8 0 0 1 14-5.3M20 12a8 8 0 0 1-14 5.3" stroke="#0071e3" stroke-width="1.8" stroke-linecap="round"/><path d="M18 3v4h-4M6 21v-4h4" stroke="#0071e3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span class="mm-ti__txt"><b>Trade-in: старый смартфон в зачёт</b><span>Меняйте старый iPhone на новый</span></span>
            <span class="mm-ti__go">Подробнее →</span>
        </a>
    {/if}
    {if $mm_flags.garantia}
        <a class="mm-gp" href="https://maxmobiles.ru/rasshirennyy-servis-garantiya-plyus/">
            <span class="mm-gp__ico"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z" fill="#e3c27a"/><path d="M12 8.5v7M8.5 12h7" stroke="#1d1d1f" stroke-width="2" stroke-linecap="round"/></svg></span>
            <span class="mm-gp__txt"><small>Гарантия+ · 12 месяцев</small><b>Сломался – получите новый такой же за 5 рабочих дней</b><span>Без ожидания ремонта · {$product.price|fn_mm_delivery_garantia_price|fn_mm_delivery_num} ₽ (10% от цены) · только вместе с покупкой</span></span>
            <svg class="mm-gp__go" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="#e3c27a" stroke-width="2.4" stroke-linecap="round"/></svg>
        </a>
    {/if}
    <ul class="mm-trust">
        <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z" stroke="#0071e3" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="#0071e3" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span><b>Гарантия</b>оригинальная техника</span></li>
        <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12V4h8l10 10-8 8z" stroke="#0071e3" stroke-width="1.8" stroke-linejoin="round"/><circle cx="7.5" cy="8.5" r="1.5" fill="#0071e3"/></svg><span><b>Лучшая цена</b>одна из самых выгодных</span></li>
        <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="7" width="13" height="10" rx="1.5" stroke="#0071e3" stroke-width="1.8"/><path d="M16 10h3l2 3v4h-5" stroke="#0071e3" stroke-width="1.8" stroke-linejoin="round"/></svg><span><b>СДЭК по России</b>и свой курьер по Крыму</span></li>
        <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s-6-5.6-6-11a6 6 0 0 1 12 0c0 5.4-6 11-6 11z" stroke="#0071e3" stroke-width="1.8"/><circle cx="12" cy="10" r="2.2" stroke="#0071e3" stroke-width="1.8"/></svg><span><b>С 2011 года</b>магазин в Севастополе</span></li>
    </ul>
    {* Блок мотивации (ab__motivation_block): у смартфонов в нём только Trade-in – его заменяет строка выше *}
    {if !$mm_flags.smartphone}
        {hook name="products:ab__motivation_block"}{/hook}
    {/if}

{/if}
