{* Maxmobiles: админка – заблокированные номера телефонов (dispatch=mm_cabinet.blocked).
   Данные: $gm_blocked – номера (fn_mm_cab_blocked_list): pretty, when, blocked_reason, users, events; $gm_strikes – порог. *}
{capture name="mainbox"}
<p>На эти номера сайт перестал отправлять СМС: по номеру {$gm_strikes} раз подряд ввели неверный код. Покупатель видит просьбу позвонить оператору интернет-магазина. Снимайте блокировку после того, как убедились, что звонит владелец номера; подтвердить ему номер без СМС можно на странице <a href="{"mm_cabinet.phones"|fn_url}">«Подтверждение номера»</a>.</p>

{if $gm_blocked}
    <div class="table-responsive-wrapper">
    <table class="table table-middle table--relative table-responsive">
        <thead>
            <tr><th>Номер</th><th>Заблокирован</th><th>Причина</th><th>Покупатель</th><th>Последние события</th><th>&nbsp;</th></tr>
        </thead>
        <tbody>
        {foreach from=$gm_blocked item="b"}
            <tr>
                <td class="nowrap"><strong>{$b.pretty}</strong></td>
                <td class="nowrap">{$b.when}</td>
                <td>{$b.blocked_reason}</td>
                <td>
                    {foreach from=$b.users item="u"}
                        <div><a href="{"profiles.update?user_id=`$u.user_id`"|fn_url}">{$u.name|default:"без имени"}</a> <span class="muted">#{$u.user_id}{if $u.email}, {$u.email}{/if}</span></div>
                    {foreachelse}
                        <span class="muted">нет записи с этим номером</span>
                    {/foreach}
                </td>
                <td>{foreach from=$b.events item="e"}<div class="muted">{$e}</div>{/foreach}</td>
                <td class="right">
                    <form action="{""|fn_url}" method="post" name="gm_unblock_{$b.phone}" style="margin:0">
                        <input type="hidden" name="dispatch" value="mm_cabinet.blocked">
                        <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
                        <input type="hidden" name="phone" value="{$b.phone}">
                        <button type="submit" class="btn btn-primary">Разблокировать</button>
                    </form>
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    </div>
{else}
    <p class="no-items">Заблокированных номеров нет.</p>
{/if}
{/capture}

{include file="common/mainbox.tpl" title="Заблокированные номера телефонов Maxmobiles" content=$smarty.capture.mainbox}
