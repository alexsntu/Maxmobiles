{assign var="image_size" value=$image_size|default:80}
{function name="feature_value"}
    {strip}
        {if $feature.features_hash && $feature.feature_type == "ProductFeatures::EXTENDED"|enum}
            {if $runtime.controller == "products" && $settings.abt__ut2.products.view.brand_link_behavior == "to_brand_page"}
                {$href = "product_features.view&variant_id=`$feature.variant_id`"}
            {else}
                {$href = "categories.view?category_id=`$product.main_category`&features_hash=`$feature.features_hash`"}
            {/if}
            <a href="{$href|fn_url}">
        {/if}
        {if $feature.feature_type == "ProductFeatures::DATE"|enum}
            <span class="ty-control-group"><span class="ty-product-feature__label"><em>{$feature.description nofilter}</em></span><span><em>{$feature.value_int|date_format:"`$settings.Appearance.date_format`"}{if $feature.suffix} <span class="ut2-product-feature__suffix">{$feature.suffix}</span>{/if}</em></span>
        {elseif $feature.feature_type == "ProductFeatures::MULTIPLE_CHECKBOX"|enum}
            <span class="ty-control-group f-variant">
            	<span class="ty-product-feature__label"><em>{$feature.description nofilter}</em></span>
                <span><em><span class="ut2-product-feature__prefix">{$feature.prefix}</span>
                {foreach from=$feature.variants item="fvariant" name="ffev"}
                   {$fvariant.variant|default:$fvariant.value}{if !$smarty.foreach.ffev.last}, {/if}
                {/foreach} <span class="ut2-product-feature__suffix">{$feature.suffix}</span>
                </em></span>
            </span>
        {elseif $feature.feature_type == "ProductFeatures::TEXT_SELECTBOX"|enum || $feature.feature_type == "ProductFeatures::NUMBER_SELECTBOX"|enum || $feature.feature_type == "ProductFeatures::EXTENDED"|enum}
        {if !$hide_name}
            <span class="ty-control-group"><span class="ty-product-feature__label"><em>{$feature.description nofilter}</em></span><span>
            <em>{if $feature.prefix}<span class="ut2-product-feature__prefix">{$feature.prefix}</span>{/if}{$feature.variant|default:$feature.value}{if $feature.suffix} <span class="ut2-product-feature__suffix">{$feature.suffix}</span>{/if}</em></span></span>
        {else}
            {if !$hide_label}<span><span>{__("brand")}: </span><em>{/if}{if $feature.prefix}<span class="ut2-product-feature__prefix">{$feature.prefix}</span>{/if}{$feature.variants[$feature.variant_id].variant|default:$feature.value}{if $feature.suffix} <span class="ut2-product-feature__suffix">{$feature.suffix}</span>{/if}{if !$hide_label}</em></span>{/if}
        {/if}
        {elseif $feature.feature_type == "ProductFeatures::SINGLE_CHECKBOX"|enum}
            <span class="ty-control-group"><span class="ty-product-feature__label"><em>{$feature.description}</em></span><span><em><i class="ty-compare-checkbox__icon ty-icon-ok"></i></em></span></span>
        {elseif $feature.feature_type == "ProductFeatures::NUMBER_FIELD"|enum}
            <span class="ty-control-group"><span class="ty-product-feature__label"><em>{$feature.description}</em></span><span>
        <em>{if $feature.prefix}<span class="ut2-product-feature__prefix">{$feature.prefix}</span>{/if}{$feature.value_int|floatval}{if $feature.suffix} <span class="ut2-product-feature__suffix">{$feature.suffix}</span>{/if}</em></span></span>
        {else}
            <span class="ty-control-group"><span class="ty-product-feature__label"><em>{$feature.description}</em></span><span>
        <em>{if $feature.prefix}<span class="ut2-product-feature__prefix">{$feature.prefix}</span>{/if}{$feature.value}{if $feature.suffix} <span class="ut2-product-feature__suffix">{$feature.suffix}</span>{/if}</em></span></span>
        {/if}
        {if $feature.feature_type == "ProductFeatures::EXTENDED"|enum && $feature.features_hash}
            </a>
        {/if}
    {/strip}
{/function}

{if $features}
    {strip}
        {if !$no_container}<div class="ut2-features-list">{/if}
        {foreach from=$features name=features_list item=feature}
            {if $feature_image}
                {assign var="obj_id" value=$feature.variant_id}

                {if $runtime.controller == "products" && $settings.abt__ut2.products.view.brand_link_behavior == "to_brand_page"}
                    {$href = "product_features.view&variant_id=`$feature.variant_id`"}
                {elseif $feature.features_hash}
                    {$href = "categories.view?category_id=`$product.main_category`&features_hash=`$feature.features_hash`"}
                {/if}

                {if $href}
                    <a href="{$href|fn_url}" title="{$feature.description}: {$feature.variant}">
                {/if}
                    {if $feature.variants[$feature.variant_id].image_pairs}
                        {include file="common/image.tpl" image_width=$image_size images=$feature.variants[$feature.variant_id].image_pairs no_ids=true}
                    {elseif $feature.variants[$feature.variant_id].image_pair}
                        {include file="common/image.tpl" image_width=$image_size images=$feature.variants[$feature.variant_id].image_pair no_ids=true}
                    {/if}
                {if $feature.features_hash || ($runtime.controller == "products" && $settings.abt__ut2.products.view.brand_link_behavior == "to_brand_page")}
                    </a>
                {/if}
            {else}
                {feature_value feature=$feature hide_name=$hide_name}{if !$smarty.foreach.features_list.last}{/if}
            {/if}
        {/foreach}
        {if !$no_container}</div>{/if}
    {/strip}
{/if}