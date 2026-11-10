<?php
/**
 * Страница с товарами (поле «Товары на странице»): текст до «Далее» — над товарами, после — под товарами
 * (без «Далее» весь текст внизу); тексты — только на первой странице списка. Как прежний page.php темы.
 */
defined('ABSPATH') || exit;
$id   = memorial_plugin_legacy_post_id($block);
$mode = get_post_meta($id, '_mem_products', true);
if (!$mode) return;
$parts  = get_extended((string) get_post_field('post_content', $id));
$top    = $parts['extended'] !== '' ? $parts['main'] : '';
$bottom = $parts['extended'] !== '' ? $parts['extended'] : $parts['main'];
$fmt    = fn($html) => trim($html) === '' ? '' : apply_filters('the_content', $html);
$first  = max(1, (int) get_query_var('paged')) === 1;

if ($first && ($t = $fmt($top))) echo '<div class="cat-desc content">' . $t . '</div>';

if ($mode === 'all' && ($kids = memorial_plugin_menu_children($id))) {
    memorial_plugin_page_tiles($kids); // плитки = подпункты этой страницы в главном меню
} elseif ($mode === 'all') {
    $cats = get_terms(['taxonomy' => 'mem_cat', 'parent' => 0, 'hide_empty' => false]);
    if (!is_wp_error($cats) && $cats) memorial_plugin_cat_tiles($cats);
} else {
    memorial_plugin_products_grid((int) $mode, get_permalink($id));
}

if ($first && ($b = $fmt($bottom))) echo '<div class="content catalog__text">' . $b . '</div>';
