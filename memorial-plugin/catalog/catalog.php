<?php
defined('ABSPATH') || exit;

/* Характеристики товаров (выводятся на странице товара). Новая характеристика — одна строка здесь. */
function memorial_plugin_attrs() {
    return apply_filters('memorial_plugin_attrs', [
        'material' => 'Материал',
        'color'    => 'Цвет',
        'size'     => 'Размер',
        'shape'    => 'Форма',
        'type'     => 'Тип',
    ]);
}

add_action('init', function () {
    register_post_type('mem_product', [
        'labels' => [
            'name' => 'Каталог', 'singular_name' => 'Товар', 'menu_name' => 'Каталог',
            'all_items' => 'Все товары', 'add_new' => 'Добавить товар',
            'add_new_item' => 'Новый товар', 'edit_item' => 'Редактировать товар',
        ],
        'public'       => true,
        'has_archive'  => 'catalog',
        'rewrite'      => ['slug' => 'tovar', 'with_front' => false],
        'menu_icon'    => 'dashicons-store',
        'menu_position'=> 5,
        'supports'     => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'],
        'show_in_rest' => true,
    ]);

    register_taxonomy('mem_cat', 'mem_product', [
        'labels' => ['name' => 'Категории', 'singular_name' => 'Категория', 'add_new_item' => 'Добавить категорию'],
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'catalog', 'with_front' => false, 'hierarchical' => true],
    ]);

    foreach (memorial_plugin_attrs() as $key => $label) {
        register_taxonomy('mem_' . $key, 'mem_product', [
            'labels'       => ['name' => $label, 'singular_name' => $label, 'add_new_item' => 'Добавить значение'],
            'hierarchical' => true,   // галочки в редакторе
            'public'       => false,
            'show_ui'      => false, // старые свойства; теперь характеристики настраиваются у типа товара
            'show_in_rest' => true,
            'rewrite'      => false,
            'query_var'    => false,
        ]);
    }
});

/** Характеристики товара: строки «Название: значение» → [[название, значение], …] */
function memorial_plugin_clean_specs($raw) {
    $lines = array_filter(array_map(fn($l) => trim(sanitize_text_field($l)), preg_split('/\r\n|\r|\n/', (string) $raw)));
    return implode("\n", $lines);
}
function memorial_plugin_product_specs($id) {
    $out = [];
    foreach (preg_split('/\n/', (string) get_post_meta($id, '_mem_specs', true)) as $l) {
        $l = trim($l);
        if ($l === '') continue;
        $out[] = strpos($l, ':') !== false ? array_map('trim', explode(':', $l, 2)) : ['', $l];
    }
    return $out;
}

/* Поля товара — в боковой панели блочного редактора (admin/editor-meta.php), список — admin/catalog-views.php */
add_action('save_post_mem_product', function ($id) {
    if (!metadata_exists('post', $id, '_mem_price')) {
        add_post_meta($id, '_mem_price', 0, true); // нужно для сортировки
    }
});

/* ---------- Цена ---------- */
function memorial_plugin_price_html($id) {
    $p = (int) get_post_meta($id, '_mem_price', true);
    if (!$p) return '<span class="price price--ask">Цена по запросу</span>';
    $old  = (int) get_post_meta($id, '_mem_price_old', true);
    $from = get_post_meta($id, '_mem_price_from', true) ? 'от ' : '';
    $unit = get_post_meta($id, '_mem_unit', true);
    $h = '<span class="price">' . $from . esc_html(memorial_plugin_rub($p)) . ($unit ? ' <small>' . esc_html($unit) . '</small>' : '') . '</span>';
    if ($old > $p) $h .= ' <s class="price-old">' . esc_html(memorial_plugin_rub($old)) . '</s>';
    return $h;
}

/* ---------- Сортировка ---------- */
function memorial_plugin_sorts() {
    return ['' => 'По умолчанию', 'price_asc' => 'Сначала дешевле', 'price_desc' => 'Сначала дороже', 'new' => 'Новинки'];
}

/** Порядок товаров по параметру ?sort= — для любого запроса товаров */
function memorial_plugin_apply_sort(array &$args) {
    switch (sanitize_key($_GET['sort'] ?? '')) {
        case 'price_asc':
        case 'price_desc':
            $args['meta_key'] = '_mem_price';
            $args['orderby'] = 'meta_value_num';
            $args['mem_price_sort'] = $_GET['sort'] === 'price_asc' ? 'ASC' : 'DESC';
            break;
        case 'new':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        default:
            $args['orderby'] = ['menu_order' => 'ASC', 'date' => 'DESC'];
    }
}

/* Запасной вариант: категории, не привязанные к странице, открываются по адресу /catalog/… */
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (!$q->is_post_type_archive('mem_product') && !$q->is_tax('mem_cat')) return;
    $args = ['posts_per_page' => 24];
    memorial_plugin_apply_sort($args);
    foreach ($args as $k => $v) $q->set($k, $v);
});

/* Товары «Цена по запросу» (цена 0) при сортировке по цене — всегда в конце списка */
add_filter('posts_orderby', function ($orderby, $q) {
    $dir = $q->get('mem_price_sort');
    if (!$dir) return $orderby;
    global $wpdb;
    $v = "CAST({$wpdb->postmeta}.meta_value AS SIGNED)";
    return "($v = 0) ASC, $v $dir, {$wpdb->posts}.post_date DESC";
}, 10, 2);

/* Товарам без цены проставляем 0 — иначе при сортировке по цене они пропадают из списка. Один раз. */
add_action('admin_init', function () {
    if (get_option('mem_price_meta_fixed')) return;
    $ids = get_posts(['post_type' => 'mem_product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1,
                      'meta_query' => [['key' => '_mem_price', 'compare' => 'NOT EXISTS']]]);
    foreach ($ids as $id) add_post_meta($id, '_mem_price', 0, true);
    update_option('mem_price_meta_fixed', 1, false);
});
