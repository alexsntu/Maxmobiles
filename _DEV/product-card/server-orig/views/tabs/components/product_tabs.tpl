{assign var="product_details_in_tab" value=$product_details_in_tab|default:$settings.Appearance.product_details_in_tab}
{capture name="tabsbox"}
    {foreach from=$tabs item="tab" key="tab_id"}
        {if $tab.show_in_popup !== "YesNo::YES"|enum && $tab.status === "A"}
            {assign var="tab_content_capture" value="tab_content_capture_`$tab_id`"}

            {capture name=$tab_content_capture}
                {if $tab.tab_type === "B"}
                    {render_block block_id=$tab.block_id dispatch="products.view" use_cache=false parse_js=false}
                {elseif $tab.tab_type === "T"}
                    {include file=$tab.template product_tab_id=$tab.html_id}
                {/if}
            {/capture}

            {hook name="tabs:ab__product_tabs_content"}
                {strip}
                <div id="content_{$tab.html_id}" class="ty-wysiwyg-content content-{$tab.html_id}"{if $smarty.capture.$tab_content_capture|trim} data-ca-accordion-is-active-scroll-to-elm=1{/if}>
                    {if $smarty.capture.$tab_content_capture|trim|strlen}
                        {hook name="tabs:ab__product_tabs_header"}
                        {if $product_details_in_tab === "YesNo::NO"|enum}
                            <div class="tab-list-title" id="{$tab.html_id}">{$tab.name}</div>
                        {/if}
                        {/hook}
                    {/if}
                    {$smarty.capture.$tab_content_capture nofilter}
                </div>
                {/strip}
            {/hook}
        {/if}
    {/foreach}
{/capture}

{hook name="tabs:ab__tabs"}
{capture name="tabsbox_content"}
    {if $product_details_in_tab === "YesNo::YES"|enum}
        {include file="common/tabsbox.tpl" content=$smarty.capture.tabsbox}
    {else}
        {$smarty.capture.tabsbox nofilter}
    {/if}
{/capture}
{/hook}