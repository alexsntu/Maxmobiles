{* Maxmobiles: страница «Восстановление пароля» по СМС (dispatch=mm_cabinet.recover), для невошедшего покупателя.
   Шаг 1 – номер телефона, шаг 2 – код из СМС и новый пароль. Работает для номера, указанного в учётной записи
   (подтверждение номера не нужно, 2026-10-15). Данные: $gm_rc (fn_mm_cab_recover_state): pending – код отправлен и ждёт ввода, mask – номер со скрытыми
   цифрами, sms – включена ли отправка СМС, url – обработчик, email_url – штатное восстановление по e-mail.
   Оформление – секция MM-7 (.gm-lk__card, .gm-vf). *}
{capture name="mainbox_title"}Восстановление пароля{/capture}
{$rc = $gm_rc}

<div class="gm-lk__card gm-vf gm-vf--plain">
    {if !$rc.sms}
        <h2 class="gm-vf__h">Восстановление по СМС недоступно</h2>
        <p class="gm-vf__t">Восстановить доступ поможет оператор интернет-магазина: позвоните по номеру <a href="tel:+78002508158">8 (800) 250-81-58</a> с телефона, который указан в вашей учётной записи.</p>
    {elseif $rc.pending}
        <h2 class="gm-vf__h">Введите код и новый пароль</h2>
        <p class="gm-vf__t">Мы отправили СМС с кодом на <b>{$rc.mask}</b>. Введите 4 цифры из сообщения и придумайте новый пароль &#8211; не короче 6 символов.</p>
        <form class="gm-vf__form" action="{$rc.url}" method="post" name="gm_rc_check">
            <input type="hidden" name="action" value="check">
            <input class="gm-vf__code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="4" placeholder="0000" required>
            <input class="gm-vf__phone" type="password" name="password1" autocomplete="new-password" placeholder="Новый пароль" minlength="6" maxlength="32" required>
            <input class="gm-vf__phone" type="password" name="password2" autocomplete="new-password" placeholder="Пароль ещё раз" minlength="6" maxlength="32" required>
            <button class="gm-vf__btn" type="submit">Сохранить и войти</button>
        </form>
        <form class="gm-vf__again" action="{$rc.url}" method="post" name="gm_rc_again">
            <input type="hidden" name="action" value="again">
            <button class="gm-vf__link" type="submit">Отправить код ещё раз</button>
            <span class="gm-vf__hint">Код действует 5 минут, повторная отправка возможна раз в минуту</span>
        </form>
        <form class="gm-vf__again" action="{$rc.url}" method="post" name="gm_rc_reset">
            <input type="hidden" name="action" value="reset">
            <button class="gm-vf__link" type="submit">Указать другой номер</button>
        </form>
    {else}
        <h2 class="gm-vf__h">Восстановление пароля по СМС</h2>
        <p class="gm-vf__t">Введите номер телефона, указанный в вашей учётной записи, &#8211; мы отправим на него СМС с кодом.</p>
        <form class="gm-vf__form" action="{$rc.url}" method="post" name="gm_rc_send">
            <input type="hidden" name="action" value="send">
            <input class="gm-vf__phone" type="tel" name="phone" inputmode="tel" autocomplete="tel" placeholder="+7 978 000-00-00" maxlength="20" required>
            <button class="gm-vf__btn" type="submit">Получить код</button>
            {* Проверка «я не робот»: показывается, если в настройках капчи включён сценарий «восстановление пароля» *}
            <div class="gm-vf__captcha" style="flex:1 1 100%">{include file="common/image_verification.tpl" option="recover" align="left"}</div>
        </form>
    {/if}
    <p class="gm-vf__hint">Не приходит СМС? Позвоните оператору интернет-магазина по номеру <a href="tel:+78002508158">8 (800) 250-81-58</a> с телефона, который указан в вашей учётной записи. Ещё можно <a href="{$rc.email_url}">восстановить пароль по e-mail</a> или <a href="{$rc.login_url}">вернуться ко входу</a>.</p>
</div>
