{* Maxmobiles (копия хука GOODMi из addons/gm_delivery_date/hooks): заказ в админке – над данными покупателя в боковой колонке: телефон из заказа и отметка, подтверждён ли
   он в личном кабинете Maxmobiles (fn_my_changes_mm_phone_mark). Только заказы витрины Maxmobiles. *}
{if $order_info.company_id == 1 && $order_info.phone}
    {$mm_mark = $order_info.phone|fn_my_changes_mm_phone_mark}
    {if $mm_mark}
    <div class="sidebar-row">
        <h6>Телефон покупателя</h6>
        <p><bdi>{$order_info.phone}</bdi>
            {if $mm_mark == "Y"}<span class="label label-success">подтверждён</span>{else}<span class="label label-important">не подтверждён</span>{/if}
        </p>
        {if $mm_mark == "N"}<p class="muted">Подтвердить вручную: <a href="{"mm_cabinet.phones?q=`$order_info.phone|regex_replace:"/\D+/":""`"|fn_url}">«Подтверждение номера»</a></p>{/if}
    </div>
    {/if}
{/if}
