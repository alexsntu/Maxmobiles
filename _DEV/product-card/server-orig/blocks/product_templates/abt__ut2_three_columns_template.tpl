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

    <div class="ut2-pb ty-product-block --three-columns{if $product_images_count < 1} --single{/if}" style="--pd-image-gallery-width: {$pd_image_gallery_width};--pd-image-gallery-height: {$pd_image_gallery_height}">

        <div class="ut2-breadcrumbs__wrapper">
            {hook name="products:ut2_main_info_breadcrumbs"}
                {include file="common/breadcrumbs.tpl"}
            {/hook}
        </div>

        {hook name="products:view_main_info"}
            {if $product}
                {assign var="obj_id" value=$product.product_id}
                {include file="common/product_data.tpl" product=$product but_role="big" but_text=__("add_to_cart") product_labels_position="right-top" hide_qty_label=true}

                <div class="ut2-pb__wrapper clearfix">
                    {assign var="form_open" value="form_open_`$obj_id`"}
                    {$smarty.capture.$form_open nofilter}

                    {assign var="old_price" value="old_price_`$obj_id`"}
                    {assign var="price" value="price_`$obj_id`"}
                    {assign var="clean_price" value="clean_price_`$obj_id`"}
                    {assign var="list_discount" value="list_discount_`$obj_id`"}
                    {assign var="discount_label" value="discount_label_`$obj_id`"}

                    <div class="ut2-pb__img-wrapper">
                        {hook name="products:image_wrap"}
                        {if !$no_images}
                            <div class="ut2-pb__img cm-reload-{$product.product_id} images-{$settings.abt__ut2.products[$settings.abt__details_layout].multiple_product_images[$settings.ab__device]}" data-ca-previewer="true" id="product_images_{$product.product_id}_update">
                                {include file="views/products/components/product_images.tpl" product=$product show_detailed_link="Y" image_width=$pd_image_gallery_width image_height=$pd_image_gallery_height lazy_load=false}

                                {hook name="products:ab__s_pictograms_pos_1"}{/hook}
                                <!--product_images_{$product.product_id}_update--></div>
                        {else}
                            {hook name="products:ab__s_pictograms_pos_1"}{/hook}
                        {/if}
                        {/hook}

                        <div class="ut2-pb__aside">

                            <div class="ut2-pb__title ut2-pb__title-wrap">
                                {if $settings.abt__ut2.general.brand_feature_id && $settings.abt__ut2.products.view.show_brand_format[$settings.ab__device] === "name"}
                                    {include file="blocks/product_templates/components/product_brand_logo_prepare.tpl"}
                                    {if $brand_feature}
                                        {hook name="products:brand"}
                                            <div class="ut2-pb__product-brand-name">
                                                {include file="views/products/components/product_features_short_list.tpl" features=array($brand_feature) no_container=true feature_image=false hide_name=true hide_label=true feature_link=true}
                                            </div>
                                        {/hook}
                                    {/if}
                                {/if}

                                {if !$hide_title}
                                    <h1 {live_edit name="product:product:{$product.product_id}"}>{$product.product nofilter}</h1>
                                {/if}

                                <div class="ut2-pb__rating">{include file="blocks/product_templates/components/product_rating.tpl"}</div>

                                <div class="ut2-pb__top-ss">
                                    {if $show_sku == "true" && $product.product_code|trim}
                                        <div class="ut2-pb__sku">
                                            {assign var="sku" value="sku_`$obj_id`"}
                                            {$smarty.capture.$sku nofilter}
                                        </div>
                                    {/if}
                                </div>
                            </div>

                            {hook name="products:ab__vendor_block"}{/hook}

                            {hook name="products:promo_text"}
                            {if $product.promo_text}
                                <div class="ut2-pb__note">
                                    {$product.promo_text nofilter}
                                </div>
                            {/if}
                            {/hook}

                            {if $settings.abt__ut2.general.brand_feature_id && $settings.abt__ut2.products.view.show_brand_format[$settings.ab__device] === "name"}
                                {include file="blocks/product_templates/components/product_brand_logo_prepare.tpl"}
                                {if $brand_feature}
                                    {hook name="products:brand"}
                                        <div class="ut2-pb__product-brand-name">
                                            {include file="views/products/components/product_features_short_list.tpl" features=array($brand_feature) no_container=true feature_image=false hide_name=true feature_link=true}
                                        </div>
                                    {/hook}
                                {/if}
                            {/if}

                            {if $capture_options_vs_qty}{capture name="product_options"}{$smarty.capture.product_options nofilter}{/if}
                            <div class="ut2-pb__options">
                                {assign var="product_options" value="product_options_`$obj_id`"}
                                {$smarty.capture.$product_options nofilter}
                            </div>
                            {if $capture_options_vs_qty}{/capture}{/if}

                            {assign var="product_edp" value="product_edp_`$obj_id`"}
                            {$smarty.capture.$product_edp nofilter}

                            {* Remove if using hook in motivation block *}
                            {hook name="products:geo_maps"}{/hook}

                            {hook name="products:ab__s_pictograms_pos_2"}{/hook}

                            {if $settings.abt__ut2.products.view.show_features[$settings.ab__device] === "YesNo::YES"|enum && $product.header_features}
                                <div class="ut2-pb__short-features">{include file="views/products/components/product_features_short_list.tpl" features=$product.header_features}</div>
                            {/if}

                            {if $show_short_descr && strlen(trim($product.short_description))}
                                <div class="ut2-pb__short-descr" {live_edit name="product:short_description:{$product.product_id}"}>{$product.short_description nofilter}</div>
                            {/if}
                        </div>
                    </div>
                    <div class="ut2-pb__content-wrapper">
                        <div class="ut2-pb__main-content-box shaded">

                            {if $settings.abt__ut2.general.brand_feature_id}
                                {include file="blocks/product_templates/components/product_brand_logo_prepare.tpl"}
                            {/if}

                            {include file="blocks/product_templates/components/product_price.tpl"}

                            {hook name="products:ab__deal_of_the_day_product_view"}{/hook}

                            <div class="ut2-pb__advanced-options">
                                {if $capture_options_vs_qty}{capture name="product_options"}{$smarty.capture.product_options nofilter}{/if}
                                {assign var="advanced_options" value="advanced_options_`$obj_id`"}
                                {$smarty.capture.$advanced_options nofilter}
                                {if $capture_options_vs_qty}{/capture}{/if}
                            </div>

                            {if $capture_buttons}{capture name="buttons"}{/if}
                            <div class="ut2-pb__button ty-product-block__button">
                                {if $show_qty}
                                    <div class="ut2-qty__wrap {if $min_qty && $product.min_qty}min-qty{/if}">
                                        {if $capture_options_vs_qty}{capture name="product_options"}{$smarty.capture.product_options nofilter}{/if}
                                        {assign var="qty" value="qty_`$obj_id`"}
                                        {$smarty.capture.$qty nofilter}

                                        {assign var="min_qty" value="min_qty_`$obj_id`"}
                                        {$smarty.capture.$min_qty nofilter}
                                        {if $capture_options_vs_qty}{/capture}{/if}
                                    </div>
                                {/if}
                                {if $show_details_button}
                                    {include file="buttons/button.tpl" but_href="products.view?product_id=`$product.product_id`" but_text=__("view_details") but_role="submit"}
                                {/if}

                                {assign var="add_to_cart" value="add_to_cart_`$obj_id`"}
                                {$smarty.capture.$add_to_cart nofilter}

                                {assign var="list_buttons" value="list_buttons_`$obj_id`"}
                                {$smarty.capture.$list_buttons nofilter}
                            </div>
                            {if $capture_buttons}{/capture}{/if}
                        </div>

                        {hook name="products:ab__motivation_block"}{/hook}
                    </div>

                    {hook name="products:product_form_close_tag"}
                    {$form_close="form_close_`$obj_id`"}
                    {$smarty.capture.$form_close nofilter}
                    {/hook}

                    {if $show_product_tabs}
                        {include file="views/tabs/components/product_popup_tabs.tpl"}
                        {$smarty.capture.popupsbox_content nofilter}
                    {/if}

                    <div class="ut2-pb__tabs-wrapper">

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
    {include file="blocks/product_templates/components/abt__ut2_mobile_template.tpl" features=$product.header_features}
{/if}