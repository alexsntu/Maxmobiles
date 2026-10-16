{* Maxmobiles (company_id = 1): левое меню личного кабинета на штатных страницах «Данные профиля», «Подробности заказа»
   и «Избранное» (в избранном – только на ПК, MM-6h). Копия хука GOODMi
   (addons/abt__unitheme2/hooks/wrapper/mainbox_general_title_wrapper.pre.tpl, там условие company_id = 2), ссылки – на mm_cabinet.
   Точка срабатывает у каждого блока с заголовком, поэтому условие строгое: витрина Maxmobiles, нужная страница и только
   основной блок страницы (тип main). Оформление – MM-6f (.gm-lk__side--std).
   Гостю на странице его заказа – карточка с приглашением зарегистрироваться (.gm-lk__side--guest, MM-6k). *}
{if $runtime.company_id == 1 && $auth.user_id && !isset($mm_lk_std_done) && isset($block.type) && $block.type == "main"
    && (($runtime.controller == "profiles" && $runtime.mode == "update") || ($runtime.controller == "orders" && $runtime.mode == "details")
        || ($runtime.controller == "wishlist" && $runtime.mode == "view"))}
{assign var="mm_lk_std_done" value=true scope="global"}
{$mm_lk_name = $auth.user_id|fn_get_user_name}
{if $runtime.controller == "orders"}{$mm_lk_active = "orders"}{elseif $runtime.controller == "wishlist"}{$mm_lk_active = "wishlist"}{else}{$mm_lk_active = "profile"}{/if}
<aside class="gm-lk__side gm-lk__side--std{if $mm_lk_active == "wishlist"} gm-lk__side--wishlist{/if}">
    <div class="gm-lk-user">
        <div class="gm-lk-user__ava">{$mm_lk_name|truncate:1:"":true|upper}</div>
        <div class="gm-lk-user__info">
            <div class="gm-lk-user__name">{$mm_lk_name}</div>
            <a class="gm-lk-user__edit" href="{"profiles.update"|fn_url}">Изменить профиль</a>
        </div>
    </div>
    <nav class="gm-lk-nav">
        <a class="gm-lk-nav__a gm-lk-nav__a--orders{if $mm_lk_active == "orders"} gm-lk-nav__a--on{/if}" href="{"mm_cabinet.orders"|fn_url}">Мои заказы</a>
        <a class="gm-lk-nav__a gm-lk-nav__a--purchased" href="{"mm_cabinet.products"|fn_url}">Купленные товары</a>
        <a class="gm-lk-nav__a gm-lk-nav__a--bonuses" href="{"mm_cabinet.bonuses"|fn_url}">Мои бонусы</a>
        <a class="gm-lk-nav__a gm-lk-nav__a--wishlist{if $mm_lk_active == "wishlist"} gm-lk-nav__a--on{/if}" href="{"wishlist.view"|fn_url}">Избранное</a>
        <a class="gm-lk-nav__a gm-lk-nav__a--compare" href="{"product_features.compare"|fn_url}">Сравнение</a>
        <a class="gm-lk-nav__a gm-lk-nav__a--profile{if $mm_lk_active == "profile"} gm-lk-nav__a--on{/if}" href="{"profiles.update"|fn_url}">Данные профиля</a>
        <a class="gm-lk-nav__a gm-lk-nav__a--out" href="{"auth.logout"|fn_url}" rel="nofollow">Выйти</a>
    </nav>
</aside>
{* Подтверждённый номер в профиле не правится (profiles.pre.php модуля my_changes выставляет $gm_phone_locked):
   поле телефона гасится стилями MM-6m, смена номера – на странице mm_cabinet.phone *}
{if $mm_lk_active == "profile" && $gm_phone_locked}
<div class="gm-phone-lock">Номер телефона подтверждён и защищён от изменений. <a href="{"mm_cabinet.phone"|fn_url}">Как сменить номер</a></div>
{/if}
{/if}
{if $runtime.company_id == 1 && !$auth.user_id && !isset($mm_lk_std_done) && isset($block.type) && $block.type == "main"
    && $runtime.controller == "orders" && $runtime.mode == "details"}
{assign var="mm_lk_std_done" value=true scope="global"}
{$mm_lk_ret = $config.current_url|urlencode}
<aside class="gm-lk__side gm-lk__side--std gm-lk__side--guest">
    <div class="gm-lk-guest">
        <div class="gm-lk-guest__title">Заказ оформлен без входа</div>
        <p class="gm-lk-guest__text">Зарегистрируйтесь &#8211; этот заказ появится в личном кабинете, а история покупок и бонусы будут под рукой.</p>
        <a class="gm-lk-guest__btn" href="{"profiles.add"|fn_url}" rel="nofollow">Зарегистрироваться</a>
        <a class="gm-lk-guest__link" href="{"auth.login_form?return_url=`$mm_lk_ret`"|fn_url}" rel="nofollow">Уже есть аккаунт &#8211; войти</a>
    </div>
</aside>
{/if}
