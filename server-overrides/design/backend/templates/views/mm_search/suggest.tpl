{* Maxmobiles: свой поиск – подсказки (dispatch=mm_search.suggest). Данные – mm_stats_suggest. *}
{capture name="mainbox"}
{include file="views/mm_search/components/tabs.tpl"}
<div class="mm-st-cols">
<div>
    <h4>Что выбирают в подсказках</h4>
    <table class="table table-middle">
    <thead><tr><th>Что</th><th>Вариант / товар</th><th class="right">Раз</th><th class="right">Людей</th><th class="right">Место</th></tr></thead>
    <tbody>
    {foreach $mm_s.chosen as $r}<tr><td class="mm-st-f">{if $r.source == "sg_query"}запрос{elseif $r.source == "sg_history"}история{elseif $r.source == "sg_scope"}в категории{elseif $r.source == "sg_product"}товар{elseif $r.source == "sg_chip"}плашка в подсказках{elseif $r.source == "chip"}плашка «Уточнить»{else}все товары{/if}</td><td>{$r.q}</td><td class="right">{$r.n}</td><td class="right">{$r.people}</td><td class="right">{$r.pos}</td></tr>{foreachelse}<tr><td colspan="5" class="no-items">Нет данных</td></tr>{/foreach}
    </tbody></table>
</div>
<div>
    <h4>Брошенный ввод</h4>
    <p class="mm-st-note">Набрали от 3 символов, ничего не выбрали и не искали. «Пусто» – подсказок не было совсем (нужен синоним или товар).</p>
    <table class="table table-middle">
    <thead><tr><th>Набрали</th><th class="right">Пусто</th><th class="right">Подсказки были</th><th class="right">Людей</th></tr></thead>
    <tbody>
    {foreach $mm_s.abandoned as $r}<tr><td><a href="{"mm_search.check"|fn_url}&amp;q={$r.typed|escape:url}">{$r.typed}</a></td><td class="right">{if $r.empty}<span class="label label-important">{$r.empty}</span> <a href="{"mm_search.synonyms"|fn_url}&amp;src={$r.typed|escape:url}" class="btn btn-small">+ синоним</a>{else}0{/if}</td><td class="right">{$r.ignored}</td><td class="right">{$r.people}</td></tr>{foreachelse}<tr><td colspan="4" class="no-items">Нет данных</td></tr>{/foreach}
    </tbody></table>
</div>
</div>
{/capture}
{include file="common/mainbox.tpl" title="Поиск Maxmobiles: подсказки" content=$smarty.capture.mainbox}
