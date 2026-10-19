{* Product page component price *}

{if !$pp_compact_view}
    <div class="ut2-pb__price-wrap{if $settings.abt__ut2.products.view.show_brand_format[$settings.ab__device] === "logo"} brand-logo{/if}">
        {hook name="products:price_container"}
        <div class="ty-product-prices pr-{$settings.abt__ut2.product_list.price_display_format}{if $product.list_discount || $product.discount} pr-color{/if}">
            {if $smarty.capture.$old_price|trim || $smarty.capture.$clean_price|trim || $smarty.capture.$list_discount|trim}
                {hook name="products:main_price"}
                <div>
                    {if $smarty.capture.$price|trim}
                        {$smarty.capture.$price nofilter}
                    {/if}

                    <span>
                    {if $smarty.capture.$old_price|trim}
                        {$smarty.capture.$old_price nofilter}
                    {/if}

                    {if $smarty.capture.$list_discount|trim && $settings.abt__ut2.products.view.show_you_save[$settings.ab__device] === "short"}
                        {$smarty.capture.$list_discount nofilter}
                    {/if}
                    </span>
                </div>
                {if $smarty.capture.$list_discount|trim && $settings.abt__ut2.products.view.show_you_save[$settings.ab__device] === "full"}
                    {$smarty.capture.$list_discount nofilter}
                {/if}
                {/hook}
            {/if}

            {if $smarty.capture.$old_price|trim || $smarty.capture.$clean_price|trim || $smarty.capture.$list_discount|trim}
                {hook name="products:ut2_pb_old_price"}
                    {$smarty.capture.$clean_price nofilter}
                {/hook}

                {if $product.prices}
                    <div class="ut2__qty-discounts">{include file="views/products/components/products_qty_discounts.tpl"}</div>
                {/if}
            {/if}
        </div>
        {/hook}
    </div>
{else}
    <div class="ty-product-prices pr-{$settings.abt__ut2.product_list.price_display_format}{if $product.list_discount || $product.discount} pr-color{/if}">
        {if $smarty.capture.$old_price|trim || $smarty.capture.$clean_price|trim || $smarty.capture.$list_discount|trim}
            <div>
                {if $smarty.capture.$price|trim}
                    {$smarty.capture.$price nofilter}
                {/if}
                <span>
                {if $smarty.capture.$old_price|trim}
                    {$smarty.capture.$old_price nofilter}
                {/if}
                {if $smarty.capture.$list_discount|trim && $settings.abt__ut2.products.view.show_you_save[$settings.ab__device] === "short"}
                    {$smarty.capture.$list_discount nofilter}
                {/if}
                </span>
            </div>
            {if $smarty.capture.$list_discount|trim && $settings.abt__ut2.products.view.show_you_save[$settings.ab__device] === "full"}
                {$smarty.capture.$list_discount nofilter}
            {/if}
        {/if}
    </div>
{/if}
