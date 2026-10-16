{* Maxmobiles: левая колонка личного кабинета – имя покупателя и разделы. Данные: $mm_cab.nav (fn_mm_cab_nav). *}
<aside class="gm-lk__side">
    <div class="gm-lk-user">
        <div class="gm-lk-user__ava">{$mm_cab.nav.initial}</div>
        <div class="gm-lk-user__info">
            <div class="gm-lk-user__name">{$mm_cab.nav.name}</div>
            <a class="gm-lk-user__edit" href="{$mm_cab.nav.profile_url}">Изменить профиль</a>
        </div>
    </div>
    <nav class="gm-lk-nav">
        {foreach from=$mm_cab.nav.items item="item"}
            <a class="gm-lk-nav__a gm-lk-nav__a--{$item.key}{if $item.active} gm-lk-nav__a--on{/if}" href="{$item.url}">{$item.title}</a>
        {/foreach}
        <a class="gm-lk-nav__a gm-lk-nav__a--out" href="{$mm_cab.nav.logout_url}" rel="nofollow">Выйти</a>
    </nav>
</aside>
