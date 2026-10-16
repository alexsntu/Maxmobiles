{* Maxmobiles: страница «Номер телефона» (dispatch=mm_cabinet.phone) – состояние подтверждения и смена номера.
   Подтверждённый номер в профиле напрямую не правится (profiles.pre.php модуля my_changes) и сам покупатель его
   не меняет (2026-10-15): номер – ключ покупателя в МойСклад и БонусПлюс, меняет администратор по заявке на
   store@maxmobiles.ru (звонок на текущий номер или письмо с e-mail из профиля). Неподтверждённый номер можно заменить
   через код из СМС, отправленный на новый номер. Данные: $mm_cab.state (fn_mm_cab_verify_state), $mm_cab.email –
   e-mail профиля без заглушки, $mm_cab.url – обработчик mm_cabinet.verify. Оформление – .gm-vf в «Своём CSS» Maxmobiles. *}
{capture name="mainbox_title"}Номер телефона{/capture}
{$st = $mm_cab.state}

<div class="gm-lk">
    {include file="views/mm_cabinet/components/nav.tpl"}

    <div class="gm-lk__main">
        <div class="gm-lk__card gm-vf gm-vf--plain">
            <h2 class="gm-vf__h">{if $mm_cab.has_phone}Ваш номер: {$st.mask}{else}Номер не указан{/if}</h2>
            <p class="gm-vf__t">
                {if $st.verified}Номер подтверждён. К нему привязаны покупки в магазинах и бонусы.
                {elseif $mm_cab.has_phone}Номер не подтверждён &#8211; покупки в магазинах и бонусы скрыты.{if $st.sms} Подтвердить его можно на странице <a href="{"mm_cabinet.orders"|fn_url}">«Мои заказы»</a>.{/if}
                {else}Укажите номер, чтобы видеть покупки в магазинах и бонусы.{/if}
            </p>
        </div>

        {if $st.verified}
        <div class="gm-lk__card gm-vf gm-vf--plain">
            <h2 class="gm-vf__h">Как сменить номер</h2>
            <p class="gm-vf__t">По номеру мы находим ваши покупки в магазинах и бонусы, поэтому сменить его можно только через интернет-магазин. Мы изменим номер на сайте, в магазинах и в бонусной программе &#8211; покупки и бонусы сохранятся.</p>
            <p class="gm-vf__t"><b>Текущий номер у вас.</b> Напишите на <a href="mailto:store@maxmobiles.ru">store@maxmobiles.ru</a> текущий и новый номер. Мы перезвоним на текущий номер, подтвердим заявку и сменим номер.</p>
            {if $mm_cab.email}
            <p class="gm-vf__t"><b>Доступа к текущему номеру нет.</b> Напишите на <a href="mailto:store@maxmobiles.ru">store@maxmobiles.ru</a> с адреса, указанного в профиле (<b>{$mm_cab.email|escape}</b>), и укажите новый номер &#8211; мы сменим его по этому письму.</p>
            {/if}
        </div>
        {elseif !$st.sms}
        <div class="gm-lk__card gm-vf gm-vf--plain">
            <h2 class="gm-vf__h">Подтвердить номер</h2>
            <p class="gm-vf__t">Подтвердить номер может оператор интернет-магазина: позвоните по номеру <a href="tel:+78002508158">8 (800) 250-81-58</a> с того телефона, который нужно подтвердить.</p>
            <div class="gm-vf__form">
                <a class="gm-vf__btn" href="tel:+78002508158">Позвонить 8 (800) 250-81-58</a>
            </div>
        </div>
        {else}
        <div class="gm-lk__card gm-vf gm-vf--plain">
            {if $st.change_phone}
                <h2 class="gm-vf__h">Введите код</h2>
                <p class="gm-vf__t">Мы отправили СМС с кодом на новый номер <b>{$st.change_mask}</b>. Введите 4 цифры из сообщения &#8211; после этого номер изменится.</p>
                <form class="gm-vf__form" action="{$mm_cab.url}" method="post" name="gm_vf_check">
                    <input type="hidden" name="action" value="check">
                    <input type="hidden" name="back" value="phone">
                    <input class="gm-vf__code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="4" placeholder="0000" required>
                    <button class="gm-vf__btn" type="submit">Подтвердить</button>
                </form>
                <p class="gm-vf__hint">Код действует 5 минут. Ошиблись номером? Введите его заново ниже.</p>
            {else}
                <h2 class="gm-vf__h">{if $mm_cab.has_phone}Изменить номер{else}Указать номер{/if}</h2>
                <p class="gm-vf__t">Введите новый номер &#8211; мы отправим на него СМС с кодом. Номер в профиле изменится после ввода кода.</p>
            {/if}
            <form class="gm-vf__form" action="{$mm_cab.url}" method="post" name="gm_vf_change">
                <input type="hidden" name="action" value="change">
                <input type="hidden" name="back" value="phone">
                <input class="gm-vf__phone" type="tel" name="new_phone" inputmode="tel" autocomplete="tel" placeholder="+7 978 000-00-00" maxlength="20" required>
                <button class="gm-vf__btn{if $st.change_phone} gm-vf__btn--ghost{/if}" type="submit">Получить код</button>
            </form>
            <p class="gm-vf__hint">Не приходит СМС? Номер подтвердит оператор интернет-магазина: позвоните по номеру <a href="tel:+78002508158">8 (800) 250-81-58</a> с того телефона, который нужно подтвердить.</p>
        </div>
        {/if}
    </div>
</div>
