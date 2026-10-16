{* Maxmobiles: страница «Мои бонусы» (dispatch=mm_cabinet.bonuses). Данные готовит app/controllers/frontend/mm_cabinet.php,
   оформление – секция 44 «Своего CSS» (классы .gm-bp*). *}
{capture name="mainbox_title"}Мои бонусы{/capture}

{$b = $mm_cab.bonus}

<div class="gm-lk">
    {include file="views/mm_cabinet/components/nav.tpl"}

    <div class="gm-lk__main">
<div class="gm-bp">
    <div class="gm-bp__col">
    {if !$mm_cab.has_phone}
        <div class="gm-bp__card gm-bp-empty">
            <h2 class="gm-bp__h">Укажите номер телефона</h2>
            <p>Бонусы привязаны к номеру телефона. Добавьте его в профиль, и здесь появятся баланс, уровень и история начислений.</p>
            <a class="gm-bp-btn" href="{$mm_cab.profile_url}">Перейти в профиль</a>
        </div>
    {elseif $mm_cab.verify}
        {include file="views/mm_cabinet/components/verify.tpl"}
    {elseif !$b}
        <div class="gm-bp__card gm-bp-empty">
            <h2 class="gm-bp__h">Не удалось получить данные о бонусах</h2>
            <p>Сервис бонусной программы сейчас не отвечает. Обновите страницу через несколько минут &#8211; бонусы никуда не пропали.</p>
        </div>
    {elseif !$b.found}
        {* Карты в БонусПлюс нет. Если и покупок в магазинах (МойСклад) по номеру нет – это новый покупатель: карта появится с первой покупкой *}
        <div class="gm-bp__card gm-bp-empty">
            {if !$mm_cab.store_total}
                <h2 class="gm-bp__h">Совершите первую покупку &#8211; и вы станете участником бонусной программы</h2>
                <p>С первой покупкой на сайте или в магазине Maxmobiles вы получите статус <a href="{$mm_cab.program_url}">Pro</a>, и уже за эту покупку вам начислят бонусы: кешбэк до 5%. На первую покупку при регистрации &#8211; до 300 бонусов. После неё здесь появятся баланс бонусов, ваш уровень и история начислений.</p>
                <a class="gm-bp-btn" href="{$mm_cab.program_url}">О бонусной программе</a>
            {else}
                <h2 class="gm-bp__h">Вы ещё не участвуете в бонусной программе</h2>
                <p>По номеру телефона из вашего профиля карта не найдена. Зарегистрируйтесь в программе, чтобы получать кешбэк бонусами с каждой покупки.</p>
                <a class="gm-bp-btn" href="{$mm_cab.program_url}">Как вступить в программу</a>
            {/if}
        </div>
    {else}
        <div class="gm-bp-hero">
            <div class="gm-bp-hero__main">
                <div class="gm-bp-hero__label">Доступно для оплаты</div>
                <div class="gm-bp-hero__sum">{$b.balance} <small>{$b.balance_word}</small></div>
                <div class="gm-bp-hero__note">1 бонус = 1 рубль &#183; до {$b.debit_pct}% стоимости покупки</div>
            </div>
            <div class="gm-bp-hero__side">
                {if $b.level.img}
                    <img class="gm-bp-hero__card" src="{$b.level.img}" alt="Карта {$b.level.name}" width="300" height="173">
                {else}
                    {* Maxmobiles: пока нет картинки карты – карта рисуется стилями (класс gm-bp-card--pro / --ultra) *}
                    <div class="gm-bp-hero__card gm-bp-card gm-bp-card--{$b.level.key}" role="img" aria-label="Карта {$b.level.name}"><span class="gm-bp-card__brand">Maxmobiles</span><span class="gm-bp-card__name">{$b.level.name}</span></div>
                {/if}
                <div class="gm-bp-hero__info">
                    <div class="gm-bp-hero__status">Ваш уровень</div>
                    <div class="gm-bp-hero__level">{$b.level.name}</div>
                    <div class="gm-bp-hero__rate">Кешбэк {$b.level.pct} бонусами с покупок</div>
                </div>
            </div>
        </div>

        {* Карта лояльности: штрих-код (Code 128, SVG из fn_mm_cab_barcode_svg) и номер – показать или назвать на кассе,
           и кнопка «Добавить в Wallet» – электронная карта программы Maxmobiles в Apple Wallet / Google Wallet (ссылка со
           страницы бонусной программы, решение пользователя 2026-10-14: «и то и другое»). Оформление – секция MM-* (.gm-bp-code). *}
        <div class="gm-bp__card gm-bp-code">
            <div class="gm-bp-code__text">
                <h2 class="gm-bp__h">Карта лояльности</h2>
                <p class="gm-bp-note">{if $mm_cab.card_number}Покажите штрих-код на кассе или назовите номер карты &#8211; так продавец начислит или спишет бонусы. {/if}Добавьте карту в Wallet на телефоне &#8211; она всегда под рукой и показывает баланс.</p>
                <a class="gm-bp-code__wallet" href="https://k.bonusplus.pro/!16YTQBZ5" target="_blank" rel="noopener">Добавить карту в Wallet</a>
            </div>
            {if $mm_cab.card_number}
            <div class="gm-bp-code__box">
                {if $mm_cab.card_barcode}<div class="gm-bp-code__bars">{$mm_cab.card_barcode nofilter}</div>{/if}
                <div class="gm-bp-code__num">{$mm_cab.card_number}</div>
            </div>
            {/if}
        </div>

        <div class="gm-bp__card">
            <div class="gm-bp-stats">
                <div class="gm-bp-stat">
                    <div class="gm-bp-stat__v">{$b.pending}</div>
                    <div class="gm-bp-stat__l">ожидают начисления</div>
                </div>
                <div class="gm-bp-stat">
                    <div class="gm-bp-stat__v">{if $b.expire_amount}{$b.expire_amount}{else}0{/if}</div>
                    <div class="gm-bp-stat__l">{if $b.expire_date}сгорит {$b.expire_date}{else}сгорает в ближайшее время{/if}</div>
                </div>
                <div class="gm-bp-stat">
                    <div class="gm-bp-stat__v">{$b.credited}</div>
                    <div class="gm-bp-stat__l">начислено за всё время</div>
                </div>
            </div>
        </div>

        <div class="gm-bp__card">
            <h2 class="gm-bp__h">{if $b.next}Прогресс до следующего уровня{else}Ваш уровень{/if}</h2>
            <div class="gm-bp-prog__text">
                {if $b.next}
                    До уровня <b>{$b.next.name}</b> осталось купить на <b>{$b.to_next}&nbsp;&#8381;</b> &#183; уже куплено на {$b.spent}&nbsp;&#8381;
                {else}
                    У вас максимальный уровень программы &#8211; <b>{$b.level.name}</b>. Куплено на {$b.spent}&nbsp;&#8381;
                {/if}
            </div>
            <div class="gm-bp-prog__bar"><div class="gm-bp-prog__fill" style="width:{$b.percent}%"></div></div>
            <div class="gm-bp-levels">
                {foreach from=$b.levels item="level" key="i"}
                    <a class="gm-bp-level{if $i == $b.level_index} gm-bp-level--on{/if}" href="{$level.url}">
                        {if $level.img}
                            <img class="gm-bp-level__img" src="{$level.img}" alt="Уровень {$level.name}" loading="lazy" width="300" height="173">
                        {else}
                            <span class="gm-bp-level__img gm-bp-card gm-bp-card--{$level.key}" aria-hidden="true"><span class="gm-bp-card__brand">Maxmobiles</span><span class="gm-bp-card__name">{$level.name}</span></span>
                        {/if}
                        <span class="gm-bp-level__b">
                            <span class="gm-bp-level__n">{$level.name}</span>
                            <span class="gm-bp-level__pct">{$level.pct} <span>кешбэк</span></span>
                            <span class="gm-bp-level__p">{$level.range}</span>
                        </span>
                    </a>
                {/foreach}
            </div>
        </div>

        <div class="gm-bp__card">
            <h2 class="gm-bp__h">Начисления и списания</h2>
            {if $b.ops}
                <input type="checkbox" id="gm_bp_more" class="gm-bp-toggle">
                {foreach from=$b.ops item="op" name="ops"}
                    <div class="gm-bp-op{if !$op.plus} gm-bp-op--minus{/if}{if $smarty.foreach.ops.index >= 8} gm-bp-op--extra{/if}">
                        <div class="gm-bp-op__ic">{if $op.plus}+{else}&#8722;{/if}</div>
                        <div class="gm-bp-op__t">
                            <div class="gm-bp-op__n">{$op.name}</div>
                            <div class="gm-bp-op__d">{$op.date}{if $op.purchase} &#183; покупка на {$op.purchase}&nbsp;&#8381;{/if}{if $op.until} &#183; {$op.until}{/if}</div>
                        </div>
                        <div class="gm-bp-op__s">{if $op.plus}+{else}&#8722;{/if}{$op.amount}</div>
                    </div>
                {/foreach}
                {if $b.ops|count > 8}
                    <label for="gm_bp_more" class="gm-bp-more">Показать ещё</label>
                {/if}
            {else}
                <p class="gm-bp-note">Операций пока нет. Бонусы поступают через 14 дней после покупки.</p>
            {/if}
        </div>
    {/if}
    </div>

    <div class="gm-bp__col">
        <div class="gm-bp__card">
            <h2 class="gm-bp__h">Как это работает</h2>
            <ul class="gm-bp-how">
                <li>Бонусы начисляются за покупки на сайте и в магазинах Maxmobiles</li>
                <li>Поступают на счёт через 14 дней после покупки</li>
                <li>Бонусами можно оплатить до 40% стоимости покупки</li>
                <li>1 бонус = 1 рубль</li>
            </ul>
            <a class="gm-bp-link" href="{$mm_cab.program_url}">Условия бонусной программы</a>
        </div>

        <div class="gm-bp__card">
            <h2 class="gm-bp__h">Мои заказы</h2>
            {if $mm_cab.orders}
                {foreach from=$mm_cab.orders item="order"}
                    <div class="gm-bp-ord">
                        <div class="gm-bp-ord__main">
                            <div class="gm-bp-ord__n">{if $order.url}<a href="{$order.url}">&#8470;&nbsp;{$order.number}</a>{else}&#8470;&nbsp;{$order.number}{/if} от {$order.date}</div>
                            <div class="gm-bp-ord__st"><span class="gm-bp-chip{if $order.source == "store"} gm-bp-chip--store{/if}">{$order.label}</span>{if $order.status} {$order.status}{/if}</div>
                        </div>
                        <div class="gm-bp-ord__sum">{$order.sum}&nbsp;&#8381;</div>
                    </div>
                {/foreach}
                <a class="gm-bp-more" href="{$mm_cab.orders_url}">Все заказы</a>
            {else}
                <p class="gm-bp-note">Заказов пока нет.</p>
            {/if}
        </div>
    </div>
</div>
    </div>
</div>
