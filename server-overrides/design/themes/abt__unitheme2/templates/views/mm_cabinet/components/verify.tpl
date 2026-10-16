{* Maxmobiles: блок «Подтвердите номер телефона» в личном кабинете. Данные: $mm_cab.verify (fn_mm_cab_verify_state):
   mask – номер со скрытыми цифрами, pending – код отправлен и ждёт ввода, wait – секунд до повторной отправки,
   sms – можно ли подтвердить самому кодом (иначе только через оператора интернет-магазина по звонку с подтверждаемого номера; при sms подсказка про оператора остаётся запасным путём), url – адрес обработчика (mm_cabinet.verify), back – на какую страницу вернуться,
   required – регистрация по номеру не завершена (до подтверждения сайт закрыт, см. before_dispatch в модуле gm_delivery_date). Оформление – секция 56 (.gm-vf). *}
{$v = $mm_cab.verify}
<div class="gm-lk__card gm-vf">
    <h2 class="gm-vf__h">{if $v.required}Завершите регистрацию: подтвердите номер телефона{else}Подтвердите номер телефона{/if}</h2>
    {if $v.required}
        <p class="gm-vf__t">Пока номер не подтверждён, заказы и остальные разделы сайта недоступны.</p>
    {/if}
    {if !$v.sms}
        <p class="gm-vf__t">Покупки в магазинах и бонусы привязаны к номеру <b>{$v.mask}</b>. Чтобы показать их только вам, номер нужно один раз подтвердить. Это делает оператор интернет-магазина: позвоните по номеру <a href="tel:+78002508158">8 (800) 250-81-58</a> с того телефона, который нужно подтвердить.</p>
        <div class="gm-vf__form">
            <a class="gm-vf__btn" href="tel:+78002508158">Позвонить 8 (800) 250-81-58</a>
        </div>
    {elseif $v.pending}
        <p class="gm-vf__t">Мы отправили СМС с кодом на <b>{$v.mask}</b>. Введите 4 цифры из сообщения.</p>
        <form class="gm-vf__form" action="{$v.url}" method="post" name="gm_vf_check">
            <input type="hidden" name="action" value="check">
            <input type="hidden" name="back" value="{$v.back}">
            <input class="gm-vf__code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="4" placeholder="0000" required>
            <button class="gm-vf__btn" type="submit">Подтвердить</button>
        </form>
        <form class="gm-vf__again" action="{$v.url}" method="post" name="gm_vf_again">
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="back" value="{$v.back}">
            <button class="gm-vf__link" type="submit">Отправить код ещё раз</button>
            <span class="gm-vf__hint">Повторная отправка возможна раз в минуту</span>
        </form>
    {else}
        <p class="gm-vf__t">Покупки в магазинах и бонусы привязаны к номеру <b>{$v.mask}</b>. Чтобы показать их только вам, подтвердите номер кодом из СМС &#8211; это нужно сделать один раз.</p>
        <form class="gm-vf__form" action="{$v.url}" method="post" name="gm_vf_send">
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="back" value="{$v.back}">
            <button class="gm-vf__btn" type="submit">Получить код</button>
        </form>
    {/if}
    {if $v.sms}
        <p class="gm-vf__hint">Не приходит СМС? Номер подтвердит оператор интернет-магазина: позвоните по номеру <a href="tel:+78002508158">8 (800) 250-81-58</a> с того телефона, который нужно подтвердить.</p>
    {/if}
    <p class="gm-vf__hint">Другой номер? <a href="{$mm_cab.profile_url}">Изменить в профиле</a></p>
</div>
