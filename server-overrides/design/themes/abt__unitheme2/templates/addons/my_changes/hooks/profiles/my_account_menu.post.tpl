{* Maxmobiles (company_id = 1): пункты «Мои заказы», «Купленные товары» и «Мои бонусы» в меню аккаунта – ведут на страницы
   кабинета Maxmobiles (mm_cabinet.orders, mm_cabinet.products, mm_cabinet.bonuses). Копия хука GOODMi
   (addons/abt__unitheme2/hooks/profiles/my_account_menu.post.tpl, там условие company_id = 2). Вошедшему показываются все
   пункты, штатные пункты заказов и бонусных баллов скрыты стилями (MM-6b, MM-6c). Гостю – только «Мои бонусы»:
   по нажатию магазин отправит его на страницу входа. *}
{if $runtime.company_id == 1}
{$dropdown_box_item_class = ($block.wrapper === "blocks/wrappers/onclick_dropdown.tpl") ? "ty-dropdown-box__item" : ""}
{if $auth.user_id}
<li class="ty-account-info__item {$dropdown_box_item_class} gm-acc-orders"><a class="ty-account-info__a" href="{"mm_cabinet.orders"|fn_url}" rel="nofollow">Мои заказы</a></li>
<li class="ty-account-info__item {$dropdown_box_item_class} gm-acc-purchased"><a class="ty-account-info__a" href="{"mm_cabinet.products"|fn_url}" rel="nofollow">Купленные товары</a></li>
{/if}
<li class="ty-account-info__item {$dropdown_box_item_class} gm-acc-bonus"><a class="ty-account-info__a" href="{"mm_cabinet.bonuses"|fn_url}" rel="nofollow">Мои бонусы{if $auth.user_id && $user_info.points > 0}<span class="ty-reward-points-count">{$user_info.points}</span>{/if}</a></li>
{/if}
