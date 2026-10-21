{* Maxmobiles: свой поиск – кандидаты в синонимы из переформулировок (dispatch=mm_search.candidates, 2026-10-21). Данные – mm_stats_candidates;
   «Сделать синонимом» – POST action=accept (src, dst можно поправить), «Скрыть» – action=dismiss. *}
{capture name="mainbox"}
{include file="views/mm_search/components/tabs.tpl"}
<p class="mm-st-note">Покупатель искал «A», ничего не открыл, а в течение 3 минут искал «B» и выбрал товар – возможно, «A» и «B» значат одно и то же.
Сначала – где «A» нашёлся плохо. Проверьте пару (ссылки ведут в «Проверку запроса»), при необходимости поправьте слова и нажмите «Сделать синонимом» –
синоним сразу заработает в поиске. Не синоним (просто перешли к другому товару) – «Скрыть». Уточнения («iphone 17» → «iphone 17 pro») и смена модели
(отличаются только цифры) сюда не попадают.</p>
<table class="table table-middle">
<thead><tr><th>Искали «A»</th><th>Потом «B» (с кликом)</th><th>Как нашлось «A»</th><th>Посетителей</th><th>Синоним: что пишут → как в каталоге</th><th>&nbsp;</th></tr></thead>
<tbody>
{foreach $mm_rows as $r}<tr>
    <td><a href="{"mm_search.check"|fn_url}&amp;q={$r.a|escape:url}" target="_blank">{$r.a}</a></td>
    <td><a href="{"mm_search.check"|fn_url}&amp;q={$r.b|escape:url}" target="_blank">{$r.b}</a></td>
    <td class="mm-st-f">{$r.mode_text}</td>
    <td>{$r.visitors}</td>
    <td>
        <form action="{""|fn_url}" method="post" class="form-inline" style="margin:0">
            <input type="hidden" name="dispatch" value="mm_search.candidates"><input type="hidden" name="action" value="accept">
            <input type="hidden" name="days" value="{$mm_days}"><input type="hidden" name="a" value="{$r.a}"><input type="hidden" name="b" value="{$r.b}">
            <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
            <input type="text" name="src" value="{$r.src}" class="input-medium" required> → <input type="text" name="dst" value="{$r.dst}" class="input-medium" required>
            <button type="submit" class="btn btn-small btn-primary">Сделать синонимом</button>
        </form>
    </td>
    <td class="right">
        <form action="{""|fn_url}" method="post" style="margin:0;display:inline">
            <input type="hidden" name="dispatch" value="mm_search.candidates"><input type="hidden" name="action" value="dismiss">
            <input type="hidden" name="days" value="{$mm_days}"><input type="hidden" name="a" value="{$r.a}"><input type="hidden" name="b" value="{$r.b}">
            <input type="hidden" name="security_hash" value="{""|fn_generate_security_hash}">
            <button type="submit" class="btn btn-small">Скрыть</button>
        </form>
    </td>
</tr>{foreachelse}<tr><td colspan="6" class="no-items">За этот период кандидатов нет – попробуйте период длиннее.</td></tr>{/foreach}
</tbody></table>
{/capture}
{include file="common/mainbox.tpl" title="Поиск Maxmobiles: кандидаты в синонимы" content=$smarty.capture.mainbox}
