<?php
/**
 * Сравнение товаров (модуль ab__extended_comparison_wishlist): группировка списков «по разделам» –
 * вариант настройки «Группировать по» = gm_section. Штатный вариант main_category делит списки по главной
 * категории товара, а она у Maxmobiles – модель («iPhone 17 Pro Max»), у GOODMi – бренд или серия, поэтому
 * два телефона из соседних категорий сравнить было нельзя.
 *
 * Правило: товар попадает в группу из fn_my_changes_gm_compare_groups() своей витрины, если его главная категория
 * лежит внутри одной из её категорий (iPhone, MacBook…); иначе – в раздел второго уровня дерева («Ноутбуки»,
 * «Беспроводные наушники»), а если главная категория верхнего уровня – в неё саму.
 * Ключ списка – номер первой категории группы, ссылка вкладки – compare?category_id=<ключ>.
 * Кроме category_id понимает product_id: открывает список, в котором лежит этот товар (так ведёт кнопка
 * «Список сравнения» в уведомлении «Товар добавлен»).
 *
 * Класс подгружается автозагрузчиком из func.php модуля my_changes; модуль сравнения ищет его по названию варианта.
 */
namespace Ab_extendedComparisonWishlist\ComparisonLists;

use Tygh\Registry;

class GmSectionComparisonGrouper extends AComparisonGrouper
{
    public function groupComparison(&$products = [], $clear_original_compare = false)
    {
        $groups = fn_my_changes_gm_compare_product_groups((array) $products, (int) Registry::get('runtime.company_id'));

        $active = '';
        if (!empty($_REQUEST['category_id'])) {
            $active = (string) $_REQUEST['category_id'];
        } elseif (!empty($_REQUEST['product_id']) && isset($groups[(int) $_REQUEST['product_id']])) {
            $active = $groups[(int) $_REQUEST['product_id']]['key'];
        }

        $action = '';
        if (Registry::get('runtime.controller') == 'product_features' && Registry::get('runtime.mode') == 'compare' && Registry::get('runtime.action') != '') {
            $action = '.' . Registry::get('runtime.action');
        }

        foreach ($products as $index => $product_id) {
            if (!isset($groups[$product_id])) {
                continue;
            }
            $group = $groups[$product_id];
            $comparison_list = $this->getComparisonList($group['key']);
            if (is_null($comparison_list)) {
                $comparison_list = new ComparisonList(
                    $group['key'],
                    $group['title'],
                    'product_features.compare' . $action . '?category_id=' . $group['key'],
                    'categories.view?category_id=' . $group['link'],
                    $active !== '' && $group['key'] === $active
                );
                $this->addComparisonList($group['key'], $comparison_list);
            }
            $comparison_list->addProduct($product_id);
            if ($clear_original_compare && $active !== '' && $group['key'] !== $active) {
                unset($products[$index]);
            }
        }
        parent::groupComparison();

        return $this->getComparisonLists();
    }
}
