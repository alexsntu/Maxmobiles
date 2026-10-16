{* Maxmobiles (company_id = 1): сведения о покупателе в подробностях заказа – две плитки «как получить заказ» и «получатель»,
   как на GOODMi (там – ветка company_id = 2 в views/profiles/components/profiles_info.tpl). Подключается из того же общего
   файла темы (развилка company_id == 1). Адреса и часы своих пунктов – по номеру способа доставки (shipping_id, сверено
   с описаниями способов в админке 2026-10-15); у магазина в ТЦ «Детский мир» часы в описании не указаны – не показываем.
   Оформление – MM-6k (.gm-ord-info). *}
{$mm_points = [
    11 => ["title" => "Магазин Maxmobiles", "addr" => "г. Севастополь, пр-т Нахимова, 19 – ТЦ «Детский мир»", "hours" => ""],
    36 => ["title" => "Пункт выдачи Maxmobiles", "addr" => "г. Симферополь, Евпаторийское шоссе, 8 – ТЦ «Меганом»", "hours" => "Ежедневно с 10:00 до 21:00"],
    38 => ["title" => "Пункт выдачи Maxmobiles", "addr" => "г. Ялта, ул. Киевская, 6 – ТЦ «Дом Торговли»", "hours" => "Ежедневно с 10:00 до 20:00"],
    37 => ["title" => "Пункт выдачи Maxmobiles", "addr" => "г. Москва, ул. Барклая, 8 – БЦ «Рубин»", "hours" => "Ежедневно с 10:00 до 19:00"]
]}
{$mm_ship = []}
{foreach from=$order_info.shipping item="mm_s"}{if !$mm_ship}{$mm_ship = $mm_s}{/if}{/foreach}
{$mm_ship_id = $mm_ship.shipping_id|default:0}
{$mm_ship_name = $mm_ship.shipping|default:""}
{$mm_city = $order_info.s_city|default:""|trim}
{$mm_addr = $order_info.s_address|default:""|trim}
<div class="ty-profiles-info gm-ord-info">
    <div class="ty-profiles-info__item gm-ord-info__item">
        {if isset($mm_points[$mm_ship_id])}
            {$mm_p = $mm_points[$mm_ship_id]}
            <h5 class="ty-profiles-info__title">{$mm_p.title}</h5>
            <div class="ty-profiles-info__field">
                <span class="gm-ord-info__main">{$mm_p.addr}</span>
                {if $mm_p.hours}<span class="gm-ord-info__note">{$mm_p.hours}</span>{/if}
            </div>
        {elseif $mm_ship_name|strpos:"СДЭК" !== false}
            <h5 class="ty-profiles-info__title">Пункт выдачи СДЭК</h5>
            <div class="ty-profiles-info__field">
                {if $mm_city}<span class="gm-ord-info__main">{$mm_city}</span>{/if}
                <span class="gm-ord-info__note">Пункт выдачи и стоимость доставки подтвердит менеджер по телефону</span>
            </div>
        {elseif $mm_ship_name|mb_strtolower|strpos:"курьер" !== false}
            <h5 class="ty-profiles-info__title">Доставка курьером</h5>
            <div class="ty-profiles-info__field">
                {if $mm_city || $mm_addr}<span class="gm-ord-info__main">{$mm_city}{if $mm_city && $mm_addr}, {/if}{$mm_addr}</span>{/if}
                {if !$mm_addr}<span class="gm-ord-info__note">Адрес и время доставки уточнит менеджер по телефону</span>{/if}
            </div>
        {else}
            <h5 class="ty-profiles-info__title">Получение</h5>
            <div class="ty-profiles-info__field">
                <span class="gm-ord-info__main">{$mm_ship_name|default:"Способ получения уточнит менеджер"}</span>
                {if $mm_city}<span class="gm-ord-info__note">{$mm_city}{if $mm_addr}, {$mm_addr}{/if}</span>{/if}
            </div>
        {/if}
    </div>
    <div class="ty-profiles-info__item gm-ord-info__item">
        <h5 class="ty-profiles-info__title">Получатель</h5>
        <div class="ty-profiles-info__field">
            <span class="gm-ord-info__main">{$order_info.firstname} {$order_info.lastname}</span>
            {if $order_info.phone}<span class="gm-ord-info__note">{$order_info.phone}</span>{/if}
            {if $order_info.email && !$order_info.email|fn_checkout_is_email_address_fake}<span class="gm-ord-info__note">{$order_info.email}</span>{/if}
        </div>
    </div>
</div>
