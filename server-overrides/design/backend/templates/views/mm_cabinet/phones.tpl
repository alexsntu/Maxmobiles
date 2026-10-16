{* Maxmobiles: админка – ручное подтверждение номера телефона покупателя (dispatch=mm_cabinet.phones).
   Данные: $gm_q – строка поиска, $gm_users – найденные покупатели (fn_mm_cab_verify_search). *}
{capture name="mainbox"}
<p>Подтверждённый номер открывает покупателю в личном кабинете покупки в магазинах и бонусы. Подтверждайте вручную только после того, как убедились, что номер принадлежит покупателю (он рядом с телефоном или назвал данные заказа).</p>

<form action="{""|fn_url}" method="get" name="gm_phones_search" class="form-inline">
    <input type="hidden" name="dispatch" value="mm_cabinet.phones">
    <input type="text" name="q" value="{$gm_q}" placeholder="Телефон (от 4 цифр) или e-mail" class="input-xlarge" autofocus>
    <button type="submit" class="btn btn-primary">Найти</button>
</form>

{if $gm_q}
    {if $gm_users}
        <div class="table-responsive-wrapper">
        <table class="table table-middle table--relative table-responsive">
            <thead>
                <tr><th>Покупатель</th><th>Телефон</th><th>E-mail</th><th>Подтверждение</th><th>&nbsp;</th></tr>
            </thead>
            <tbody>
            {foreach from=$gm_users item="u"}
                <tr>
                    <td><a href="{"profiles.update?user_id=`$u.user_id`"|fn_url}">{$u.name|default:"без имени"}</a> <span class="muted">#{$u.user_id}</span></td>
                    <td>{$u.phone|default:"–"}</td>
                    <td>{$u.email}</td>
                    <td>{if $u.verified}<span class="label label-success">подтверждён</span> {$u.verified_when} <span class="muted">({$u.verified_how})</span>{elseif !$u.has_phone}<span class="muted">нет номера в профиле</span>{else}<span class="label">не подтверждён</span>{/if}</td>
                    <td class="right">
                        {if $u.has_phone}
                        <form action="{""|fn_url}" method="post" name="gm_phone_{$u.user_id}" style="margin:0">
                            <input type="hidden" name="dispatch" value="mm_cabinet.phones">
                            <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
                            <input type="hidden" name="q" value="{$gm_q}">
                            <input type="hidden" name="user_id" value="{$u.user_id}">
                            {if $u.verified}
                                <input type="hidden" name="action" value="reset">
                                <button type="submit" class="btn">Снять подтверждение</button>
                            {else}
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-primary">Подтвердить номер</button>
                            {/if}
                        </form>
                        {/if}
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
        </div>
    {else}
        <p class="no-items">Покупатели Maxmobiles не найдены. Введите не меньше 4 цифр номера или 3 символов e-mail.</p>
    {/if}
{/if}
{/capture}

{include file="common/mainbox.tpl" title="Подтверждение телефона покупателей Maxmobiles" content=$smarty.capture.mainbox}
