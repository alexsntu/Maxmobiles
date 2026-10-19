{* Maxmobiles: карточка товара на телефоне (шаблон maxmobiles_card). Основа – components/abt__ut2_mobile_template.tpl
   (UniTheme2 4.19.1): тот же порядок частей, что на ПК (components/maxmobiles_card_parts.tpl). Панель покупки внизу экрана –
   штатная панель темы (решение владельца 2026-10-19), своей нет.
   Правки помечены «Maxmobiles:». После обновления темы сверять с abt__ut2_mobile_template.tpl. *}
{script src="js/tygh/exceptions.js"}

{assign var="pd_image_gallery_width" value=$settings.abt__ut2.products.view.image_width[$settings.ab__device]|default:430}
{assign var="pd_image_gallery_height" value=$settings.abt__ut2.products.view.image_height[$settings.ab__device]|default:430}

{$is_add_to_cart_mv=true}
{$abt__shareb_mute=false}
{if "MULTIVENDOR"|fn_allowed_for && ($product.master_product_id || !$product.company_id)}{$is_add_to_cart_mv=false}{/if}

{$mm_flags = $product.product_id|fn_mm_delivery_flags}
<div class="ut2-pb ut2-pb-mobile ty-product-block mm-card mm-card--mobile{if $mm_flags.smartphone} mm-card--smartphone{/if}" data-mm-product="{$product.product_id}" data-mm-variants="{$product|fn_mm_delivery_variants_json}">

    <div class="ut2-breadcrumbs__wrapper">
        {hook name="products:ut2_main_info_breadcrumbs"}
            {include file="common/breadcrumbs.tpl"}
        {/hook}
    </div>

    {if $product}

        <div class="ut2-pb__wrapper clearfix">
        {hook name="products:view_main_info"}

            {assign var="obj_id" value=$product.product_id}
            {include file="common/product_data.tpl" product=$product but_role="big" but_text=__("add_to_cart") product_labels_position="right-top" hide_qty_label=true}

            <div class="ut2-pb__img-wrapper">
                {hook name="products:image_wrap"}

                {hook name="products:ab__product_images_count"}
                    {$product_images_count = $product.image_pairs|@count}
                {/hook}

                {if !$no_images}
                    <div class="ut2-pb__img cm-reload-{$product.product_id} {if $settings.Appearance.thumbnails_gallery == "YesNo::YES"|enum}ut2-pb__as-gallery{else}ut2-pb__as-thumbs{/if}" data-ca-previewer="true" style="--pd-image-gallery-width:{$pd_image_gallery_width};--pd-image-gallery-height:{$pd_image_gallery_height};" id="product_images_{$product.product_id}_update">
                        {include file="views/products/components/product_images.tpl" image_width=$pd_image_gallery_width image_height=$pd_image_gallery_height  product=$product show_detailed_link="YesNo::YES"|enum lazy_load=false thumbnails_size=50}
					<!--product_images_{$product.product_id}_update--></div>
                {/if}
                {/hook}

                {hook name="products:ab__s_pictograms_pos_1"}{/hook}
            </div>

            <div class="ut2-pb__content">
                {assign var="form_open" value="form_open_`$obj_id`"}
                {$smarty.capture.$form_open nofilter}

                {assign var="old_price" value="old_price_`$obj_id`"}
                {assign var="price" value="price_`$obj_id`"}
                {assign var="clean_price" value="clean_price_`$obj_id`"}
                {assign var="list_discount" value="list_discount_`$obj_id`"}
                {assign var="discount_label" value="discount_label_`$obj_id`"}

                <div class="ut2-pb__inner-elements-wrap">
                    {if !$hide_title}
                        <h1 class="ut2-pb__title" {live_edit name="product:product:{$product.product_id}"}>{$product.product nofilter}</h1>
                    {/if}
                </div>
                {* Maxmobiles: рейтинг магазина, код, заметка; цена; бонусы *}
                {include file="blocks/product_templates/components/maxmobiles_card_parts.tpl" part="meta"}

                <div class="mm-price">{include file="blocks/product_templates/components/product_price.tpl"}</div>

                {hook name="products:ab__deal_of_the_day_product_view"}{/hook}

                {include file="blocks/product_templates/components/maxmobiles_card_parts.tpl" part="bonus"}

                {hook name="products:ab__s_pictograms_pos_2"}{/hook}

                {if $capture_options_vs_qty}{capture name="product_options"}{$smarty.capture.product_options nofilter}{/if}
                <div class="ut2-pb__options">
                    {assign var="product_options" value="product_options_`$obj_id`"}
                    {$smarty.capture.$product_options nofilter}
                </div>
                {if $capture_options_vs_qty}{/capture}{/if}

                {assign var="advanced_options" value="advanced_options_`$obj_id`"}
                {if $smarty.capture.$advanced_options}
                <div class="ut2-pb__advanced-options">
                    {if $capture_options_vs_qty}{capture name="product_options"}{$smarty.capture.product_options nofilter}{/if}
                    {$smarty.capture.$advanced_options nofilter}
                    {if $capture_options_vs_qty}{/capture}{/if}
                </div>
                {/if}

                {assign var="product_edp" value="product_edp_`$obj_id`"}
                {$smarty.capture.$product_edp nofilter}

                {* Maxmobiles: наличие, кнопки, срок получения *}
                {include file="blocks/product_templates/components/maxmobiles_card_parts.tpl" part="buybox"}

                {* Remove if using hook in motivation block *}
                {hook name="products:geo_maps"}{/hook}

                {assign var="form_close" value="form_close_`$obj_id`"}
                {$smarty.capture.$form_close nofilter}

                {* Maxmobiles: Trade-in, Гарантия+, доводы магазина (блок мотивации – внутри, кроме смартфонов) *}
                {include file="blocks/product_templates/components/maxmobiles_card_parts.tpl" part="extras"}

                {if $settings.abt__ut2.products.view.show_features[$settings.ab__device] === "YesNo::YES"|enum && $product.header_features}
                    <div class="ut2-pb__short-features mm-keys">
                                <div class="mm-keys__head">
                                    <span class="mm-keys__title">Характеристики</span>
                                    <a class="mm-keys__link" href="#content_features" data-mm-target="content_features">Все характеристики</a>
                                </div>
                                {include file="views/products/components/product_features_short_list.tpl" features=$product.header_features}
                            </div>
                {/if}

                {if $show_short_descr && strlen(trim($product.short_description))}
                    <div class="ut2-pb__short-descr" {live_edit name="product:short_description:{$product.product_id}"}>{$product.short_description nofilter}</div>
                {/if}

                {if $show_product_tabs}
                    {include file="views/tabs/components/product_popup_tabs.tpl"}
                    {$smarty.capture.popupsbox_content nofilter}
                {/if}

                {hook name="products:ab__vendor_block"}{/hook}
                {hook name="products:product_detail_bottom"}{/hook}

                {if $settings.abt__ut2.products.custom_block_id|intval}
                    {render_block block_id=$settings.abt__ut2.products.custom_block_id|intval dispatch="products.view"  use_cache=false parse_js=false}
                {/if}

                {hook name="products:product_tabs_pre"}
                    <div class="ut2-pb__tabs">
                        {if $show_product_tabs}
                            {hook name="products:product_tabs"}
                                {include file="views/tabs/components/product_tabs.tpl"}

                            {if $blocks.$tabs_block_id.properties.wrapper}
                                {include file=$blocks.$tabs_block_id.properties.wrapper content=$smarty.capture.tabsbox_content title=$blocks.$tabs_block_id.description}
                            {else}
                                {$smarty.capture.tabsbox_content nofilter}
                            {/if}
                            {/hook}
                        {/if}
                    </div>
                {/hook}

                {hook name="products:buy_together"}{/hook}
            </div>

        {/hook}
        </div>
    {/if}

    {if $smarty.capture.hide_form_changed == "YesNo::YES"|enum}
        {assign var="hide_form" value=$smarty.capture.orig_val_hide_form}
    {/if}

    {hook name="products:bottom_product_layer"}{/hook}
</div>

<div class="product-details">
</div>

{capture name="mainbox_title"}{assign var="details_page" value=true}{/capture}
