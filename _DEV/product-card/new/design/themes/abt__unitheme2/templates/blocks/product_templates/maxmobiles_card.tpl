{** template-description:Maxmobiles_card **}
{* Maxmobiles: карточка товара (2026-10-19). Основа – abt__ut2_bigpicture_flat_template.tpl (UniTheme2 4.19.1), на ПК три колонки:
   галерея | название, варианты, характеристики | цена, бонусы, покупка и срок получения, Trade-in, Гарантия+, доводы магазина. Части – components/maxmobiles_card_parts.tpl, данные – модуль mm_delivery,
   стили – секция MM-14 «Своего CSS» Maxmobiles, скрипт – js/addons/mm_delivery/card.js.
   Все штатные части CS-Cart (форма товара, варианты, кнопки, вкладки, хуки модулей) сохранены.
   Классы ut2-big-image --flat оставлены – сетку и скрипты темы берём у этого шаблона. Правки помечены «Maxmobiles:».
   После обновления темы сверять с abt__ut2_bigpicture_flat_template.tpl. Телефон – components/maxmobiles_card_mobile.tpl. *}
{script src="js/tygh/exceptions.js"}

{if $settings.ab__device !== "mobile"}

    {assign var="pd_image_gallery_width" value=$settings.abt__ut2.products.view.image_width[$settings.ab__device]|default:$settings.Thumbnails.product_details_thumbnail_width|default:430}
    {assign var="pd_image_gallery_height" value=$settings.abt__ut2.products.view.image_height[$settings.ab__device]|default:$settings.Thumbnails.product_details_thumbnail_height|default:430}

    {$is_add_to_cart_mv=true}
    {$abt__shareb_mute=true}
    {if "MULTIVENDOR"|fn_allowed_for && ($product.master_product_id || !$product.company_id)}{$is_add_to_cart_mv=false}{/if}

    {hook name="products:ab__product_images_count"}
        {$product_images_count = $product.image_pairs|@count}
    {/hook}

    {* Maxmobiles: класс mm-card (+ mm-card--smartphone – баннер Trade-in только у смартфонов), номер товара и цены вариантов для скрипта *}
    {$mm_flags = $product.product_id|fn_mm_delivery_flags}
    <div class="ut2-pb ty-product-block ut2-big-image --flat mm-card{if $mm_flags.smartphone} mm-card--smartphone{/if}{if $product_images_count < 1} --single{/if}" data-mm-product="{$product.product_id}" data-mm-variants="{$product|fn_mm_delivery_variants_json}" style="--pd-image-gallery-width: {$pd_image_gallery_width};--pd-image-gallery-height: {$pd_image_gallery_height}">

        <div class="ut2-breadcrumbs__wrapper">
            {hook name="products:ut2_main_info_breadcrumbs"}
                {include file="common/breadcrumbs.tpl"}
            {/hook}
        </div>

        {hook name="products:view_main_info"}
            {if $product}
                {assign var="obj_id" value=$product.product_id}
                {include file="common/product_data.tpl" product=$product but_role="big" but_text=__("add_to_cart") product_labels_position="right-top" hide_qty_label=true}

                {assign var="old_price" value="old_price_`$obj_id`"}
                {assign var="price" value="price_`$obj_id`"}
                {assign var="clean_price" value="clean_price_`$obj_id`"}
                {assign var="list_discount" value="list_discount_`$obj_id`"}
                {assign var="discount_label" value="discount_label_`$obj_id`"}

                <div class="ut2-pb__wrapper clearfix">
                <div class="ut2-pb__section-combined">

                    {* Maxmobiles: левая колонка – галерея *}
                    <div class="ut2-pb__img-wrapper mm-card__gallery">
                        {hook name="products:image_wrap"}
                        {if !$no_images}
                            <div class="ut2-pb__img cm-reload-{$product.product_id} images-{$settings.abt__ut2.products.abt__ut2_bigpicture_flat_template.multiple_product_images[$settings.ab__device]}" data-ca-previewer="true" id="product_images_{$product.product_id}_update">
                                {include file="views/products/components/product_images.tpl" product=$product show_detailed_link="Y" image_width=$pd_image_gallery_width image_height=$pd_image_gallery_height lazy_load=false}

                                {hook name="products:ab__s_pictograms_pos_1"}{/hook}
                                <!--product_images_{$product.product_id}_update--></div>
                        {else}
                            {hook name="products:ab__s_pictograms_pos_1"}{/hook}
                        {/if}
                        {/hook}
                    </div>

                    {* Maxmobiles: на ПК – две колонки внутри формы товара: средняя (название, варианты, характеристики)
                       и правая (цена, бонусы, покупка и срок получения, Trade-in, Гарантия+, доводы). Форма охватывает обе,
                       поэтому варианты и кнопки работают как в штатном шаблоне. *}
                    <div class="ut2-pb__content-wrapper mm-card__info">
                        {hook name="products:ab__vendor_block"}{/hook}

                        <div class="ut2-pb__main-content-box mm-card__main">
                        {assign var="form_open" value="form_open_`$obj_id`"}
                        {$smarty.capture.$form_open nofilter}

                        <div class="mm-col-mid">
                            <div class="ut2-pb__title ut2-pb__title-wrap">
                                {if !$hide_title}
                                    <h1 {live_edit name="product:product:{$product.product_id}"}>{$product.product nofilter}</h1>
                                {/if}
                            </div>
                            {include file="blocks/product_templates/components/maxmobiles_card_parts.tpl" part="meta"}

                            {if $capture_options_vs_qty}{capture name="product_options"}{$smarty.capture.product_options nofilter}{/if}
                            <div class="ut2-pb__options">
                                {assign var="product_options" value="product_options_`$obj_id`"}
                                {$smarty.capture.$product_options nofilter}
                            </div>
                            {if $capture_options_vs_qty}{/capture}{/if}

                            {* Штатные дополнительные поля (бонусные баллы модуля скрыты стилями – их показывает блок бонусов) *}
                            <div class="ut2-pb__advanced-options">
                                {if $capture_options_vs_qty}{capture name="product_options"}{$smarty.capture.product_options nofilter}{/if}
                                {assign var="advanced_options" value="advanced_options_`$obj_id`"}
                                {$smarty.capture.$advanced_options nofilter}
                                {if $capture_options_vs_qty}{/capture}{/if}
                            </div>

                            {assign var="product_edp" value="product_edp_`$obj_id`"}
                            {$smarty.capture.$product_edp nofilter}

                            {* Remove if using hook in motivation block *}
                            {hook name="products:geo_maps"}{/hook}
                            {hook name="products:ab__s_pictograms_pos_2"}{/hook}

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
                        </div>

                        <div class="mm-col-side">
                            <div class="mm-price">
                                {if $settings.abt__ut2.general.brand_feature_id}
                                    {include file="blocks/product_templates/components/product_brand_logo_prepare.tpl"}
                                {/if}
                                {include file="blocks/product_templates/components/product_price.tpl"}
                            </div>
                            {hook name="products:ab__deal_of_the_day_product_view"}{/hook}

                            {include file="blocks/product_templates/components/maxmobiles_card_parts.tpl" part="bonus"}
                            {include file="blocks/product_templates/components/maxmobiles_card_parts.tpl" part="buybox"}
                            {include file="blocks/product_templates/components/maxmobiles_card_parts.tpl" part="extras"}
                        </div>

                        {hook name="products:product_form_close_tag"}
                        {$form_close="form_close_`$obj_id`"}
                        {$smarty.capture.$form_close nofilter}
                        {/hook}

                        {if $show_product_tabs}
                            {include file="views/tabs/components/product_popup_tabs.tpl"}
                            {$smarty.capture.popupsbox_content nofilter}
                        {/if}
                        </div>
                    </div>
                </div>

                    <div class="ut2-pb__tabs-wrapper mm-card__below">

                        {if $settings.abt__ut2.products.custom_block_id|intval}
                            <div class="ut2-pb__custom-block">
                                {render_block block_id=$settings.abt__ut2.products.custom_block_id|intval dispatch="products.view" use_cache=false parse_js=false}
                            </div>
                        {/if}

                        {hook name="products:buy_together"}{/hook}

                        {hook name="products:product_tabs_pre"}
                            <div class="ut2-pb__tabs{if $settings.Appearance.product_details_in_tab === "YesNo::NO"|enum} tabs-list{/if}">
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
                    </div>

                    {hook name="products:product_detail_bottom"}{/hook}

                </div>
            {/if}
        {/hook}

        {if $smarty.capture.hide_form_changed == "Y"}
            {assign var="hide_form" value=$smarty.capture.orig_val_hide_form}
        {/if}

        {hook name="products:bottom_product_layer"}{/hook}
    </div>

    <div class="product-details">
    </div>

    {capture name="mainbox_title"}{assign var="details_page" value=true}{/capture}
{else}
    {include file="blocks/product_templates/components/maxmobiles_card_mobile.tpl" features=$product.header_features}
{/if}
