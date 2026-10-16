{*
  Maxmobiles (company_id = 1): кнопки «В избранное» и «Сравнить» у товара на странице корзины.
  Копия шаблона GOODMi (addons/abt__unitheme2/hooks/checkout/product_options.post.tpl) – у каждой витрины свой файл;
  лежит в модуле «Мои изменения» (my_changes), чтобы не трогать файл GOODMi.
  Файл подключается хуком checkout:product_options (views/checkout/components/cart_items.tpl).
  «В избранное» – POST-ссылка (cm-post) на wishlist.add с товаром и выбранными опциями;
  redirect_url + cm-ajax-full-render нужны, чтобы после добавления обновился счётчик в шапке;
  «Сравнить» – та же ссылка, что у штатной кнопки сравнения темы.
  Вид кнопок (квадратные значки, подпись скрыта) задаётся стилями корзины в «Своём CSS» Maxmobiles (секция MM-1).
  Остальные витрины без изменений.
*}
{if $runtime.company_id == 1 && $runtime.controller == "checkout" && $product.product_id && !$product.exclude_from_calculate|default:false}
    {$gm_pid = $product.product_id}
    {$gm_wl = "wishlist.add?product_data[`$gm_pid`][product_id]=`$gm_pid`&product_data[`$gm_pid`][amount]=1"}
    {$gm_opts = $product.extra.product_options|default:[]}
    {if $gm_opts}
        {foreach $gm_opts as $gm_oid => $gm_oval}
            {$gm_wl = "`$gm_wl`&product_data[`$gm_pid`][product_options][`$gm_oid`]=`$gm_oval`"}
        {/foreach}
    {/if}
    {$gm_back = $config.current_url|escape:url}
    {$gm_wl = "`$gm_wl`&redirect_url=`$gm_back`"}
    <div class="gm-cart-actions">
        <a class="cm-post cm-ajax cm-ajax-full-render gm-cart-action" rel="nofollow" title="В избранное"
           href="{$gm_wl|fn_url}"
           data-ca-target-id="cart_status*,wish_list*,account_info*,abt__ut2_wishlist_count">
            <i class="ut2-icon-baseline-favorite_line"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>В избранное
        </a>
        <a class="cm-ajax cm-ajax-full-render gm-cart-action" rel="nofollow" title="Сравнить"
           href="{"product_features.add_product?product_id=`$gm_pid`&redirect_url=`$gm_back`"|fn_url}"
           data-ca-target-id="comparison_list,account_info*,abt__ut2_compared_products">
            <i class="ut2-icon-addchart_black_line"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>Сравнить
        </a>
    </div>
{/if}
