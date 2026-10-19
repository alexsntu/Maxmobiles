{* Maxmobiles: скрипт карточки товара на шаблоне maxmobiles_card (отсчёт, цены вариантов, панель покупки, смена города) *}
{if $runtime.company_id == 1 && $runtime.controller == "products" && $runtime.mode == "view"}
    {script src="js/addons/mm_delivery/card.js"}
{/if}
