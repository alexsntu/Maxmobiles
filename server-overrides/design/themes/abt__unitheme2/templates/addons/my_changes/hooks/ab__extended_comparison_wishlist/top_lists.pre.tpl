{*
  Maxmobiles (company_id = 1): страница сравнения всегда открывается на одной категории.
  Копия шаблона GOODMi (addons/abt__unitheme2/hooks/ab__extended_comparison_wishlist/top_lists.pre.tpl),
  лежит в модуле «Мои изменения» (my_changes), чтобы не трогать файл GOODMi.
  Модуль ab__extended_comparison_wishlist без параметра category_id показывает все товары
  вперемешку и не выделяет ни одну вкладку. Если категорий несколько и ни одна не выбрана,
  сразу переходим на первую. Скрипт помечен data-no-defer, чтобы CS-Cart не переносил его
  в конец страницы и смешанный список не успевал показаться. Проверка адреса защищает от
  зацикливания. Файл подключается хуком ab__extended_comparison_wishlist:top_lists
  (overrides/views/product_features/compare.tpl). Остальные витрины без изменений.
*}
{if $runtime.company_id == 1 && $ab__ecw_compare_lists}
    {$gm_cmp_active = false}
    {$gm_cmp_first = ""}
    {foreach $ab__ecw_compare_lists as $gm_cmp_list}
        {if $gm_cmp_list->isActive()}
            {$gm_cmp_active = true}
        {/if}
        {if !$gm_cmp_first}
            {$gm_cmp_first = $gm_cmp_list->getHref()|fn_url}
        {/if}
    {/foreach}
    {if !$gm_cmp_active && $gm_cmp_first}
        <script data-no-defer>
            if (!/[?&]category_id=/.test(window.location.search)) {
                window.location.replace("{$gm_cmp_first|escape:javascript nofilter}");
            }
        </script>
    {/if}
{/if}
