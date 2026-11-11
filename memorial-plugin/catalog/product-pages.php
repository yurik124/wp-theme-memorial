<?php
/**
 * Отдельные страницы товаров (/tovar/…) и страницы вложений (картинок).
 *
 * У большинства товаров нет описания — их страница это только фото, на неё не ведут ссылки, а поисковики
 * видят сотни «пустых» страниц. Режим «auto» (по умолчанию): страница есть только у товара с описанием;
 * остальные адреса ведут (301) на раздел каталога к этому товару, не попадают в карту сайта,
 * а карточка открывает фото крупно. Режим «all» — как раньше, страница у каждого товара.
 * Настройка — «Каталог → Страницы товаров» (опция mem_product_pages).
 */
defined('ABSPATH') || exit;

function memorial_plugin_product_pages_mode() {
    $m = get_option('mem_product_pages', 'auto');
    return in_array($m, ['auto', 'all'], true) ? $m : 'auto';
}

/** Есть ли у товара своя страница */
function memorial_plugin_product_has_page($id = 0) {
    $id = $id ?: get_the_ID();
    if (memorial_plugin_product_pages_mode() === 'all') return true;
    return trim(wp_strip_all_tags((string) get_post_field('post_content', $id))) !== '';
}

/** Якорь товара на странице раздела */
function memorial_plugin_product_anchor($id) {
    return 'p-' . (int) $id;
}

/** Куда ведёт товар без страницы: раздел каталога с этим товаром */
function memorial_plugin_product_section_url($id) {
    $cat = function_exists('memorial_plugin_product_cat') ? memorial_plugin_product_cat($id) : null;
    $base = '';
    if ($cat) {
        $pid = function_exists('memorial_plugin_term_page') ? memorial_plugin_term_page($cat->term_id) : 0;
        $base = $pid ? get_permalink($pid) : get_term_link($cat);
        if (is_wp_error($base)) $base = '';
    }
    if (!$base) $base = function_exists('memorial_plugin_catalog_url') ? memorial_plugin_catalog_url() : home_url('/');
    return preg_replace('~#.*$~', '', $base) . '#' . memorial_plugin_product_anchor($id); // вместо якоря раздела — якорь самого товара
}

/** Пустые товары (без описания) — для карты сайта */
function memorial_plugin_products_without_page() {
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = [];
    if (memorial_plugin_product_pages_mode() === 'all') return $ids;
    foreach (get_posts(['post_type' => 'mem_product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]) as $id) {
        if (!memorial_plugin_product_has_page($id)) $ids[] = $id;
    }
    return $ids;
}

/* Перенаправления */
add_action('template_redirect', function () {
    if (is_singular('mem_product') && !memorial_plugin_product_has_page(get_queried_object_id())) {
        wp_safe_redirect(memorial_plugin_product_section_url(get_queried_object_id()), 301, 'Memorial');
        exit;
    }
    // страница картинки (вложения): к записи, где она стоит, или к самому файлу
    if (is_attachment()) {
        $a = get_queried_object();
        $parent = $a && $a->post_parent ? get_post($a->post_parent) : null;
        $to = '';
        if ($parent && $parent->post_status === 'publish' && is_post_type_viewable($parent->post_type)) {
            $to = $parent->post_type === 'mem_product' && !memorial_plugin_product_has_page($parent->ID) ? memorial_plugin_product_section_url($parent->ID) : get_permalink($parent);
        }
        if (!$to) $to = wp_get_attachment_url($a->ID);
        if ($to) { wp_safe_redirect($to, 301, 'Memorial'); exit; }
    }
}, 1);

/* Карта сайта WordPress: без пустых товаров и без вложений */
add_filter('wp_sitemaps_posts_query_args', function ($args, $type) {
    if ($type === 'mem_product' && ($ex = memorial_plugin_products_without_page())) $args['post__not_in'] = array_merge($args['post__not_in'] ?? [], $ex);
    return $args;
}, 10, 2);
add_filter('wp_sitemaps_post_types', function ($types) { unset($types['attachment']); return $types; });

/* All in One SEO: те же исключения в его карте сайта */
add_filter('aioseo_sitemap_exclude_posts', function ($ids, $type = '') {
    return array_values(array_unique(array_merge((array) $ids, memorial_plugin_products_without_page())));
}, 10, 2);

/* Настройка: «Каталог → Страницы товаров» — форма DataForm (src/catalog, экран product-pages) */
add_action('admin_menu', function () {
    $hook = add_submenu_page('edit.php?post_type=mem_product', 'Страницы товаров', 'Страницы товаров', 'manage_options', 'mem-product-pages', function () {
        echo '<div id="memorial-plugin-catalog" class="wrap" data-screen="product-pages"></div>';
    });
    if ($hook && function_exists('memorial_plugin_catalog_views_assets')) add_action('load-' . $hook, 'memorial_plugin_catalog_views_assets');
}, 20);

/** Режим и счётчики для формы */
function memorial_plugin_product_pages_data() {
    $m = memorial_plugin_product_pages_mode();
    $total = (int) (wp_count_posts('mem_product')->publish ?? 0);
    return ['mode' => $m, 'total' => $total, 'withPage' => $m === 'all' ? $total : max(0, $total - count(memorial_plugin_products_without_page()))];
}

add_action('rest_api_init', function () {
    register_rest_route('memorial-plugin/v1', '/product-pages', [
        ['methods' => WP_REST_Server::READABLE, 'permission_callback' => fn() => current_user_can('manage_options'),
         'callback' => fn() => rest_ensure_response(memorial_plugin_product_pages_data())],
        ['methods' => WP_REST_Server::CREATABLE, 'permission_callback' => fn() => current_user_can('manage_options'),
         'args' => ['mode' => ['type' => 'string', 'enum' => ['auto', 'all'], 'required' => true]],
         'callback' => function (WP_REST_Request $request) {
             update_option('mem_product_pages', $request['mode'] === 'all' ? 'all' : 'auto');
             return rest_ensure_response(memorial_plugin_product_pages_data());
         }],
    ]);
});
