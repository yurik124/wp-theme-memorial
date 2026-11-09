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

/* ---------- Цена в редакторе товара ---------- */
add_action('add_meta_boxes', function () {
    add_meta_box('mem_price', 'Цена', 'memorial_plugin_price_box', 'mem_product', 'side', 'high');
    add_meta_box('mem_sub', 'Подпись под названием', function ($post) {
        echo '<input type="text" name="mem_sub" value="' . esc_attr(get_post_meta($post->ID, '_mem_sub', true)) . '" placeholder="например: высота 90 см · 700 × 2050 мм" style="width:100%">'
           . '<p class="description">Короткая строка в карточке: размер, материал, цвет. Текст в редакторе ниже необязателен: если он есть, у товара появится своя страница.</p>'
           . '<p style="margin-top:14px"><label><strong>Характеристики</strong> — по одной в строке, «Название: значение»<br>'
           . '<textarea name="mem_specs" rows="4" style="width:100%" placeholder="Ширина × высота: 700 × 2050 мм&#10;Профиль: 40 × 20 мм">' . esc_textarea(get_post_meta($post->ID, '_mem_specs', true)) . '</textarea></label></p>';
    }, 'mem_product', 'normal', 'high');
});

function memorial_plugin_price_box($post) {
    wp_nonce_field('mem_price', 'mem_price_nonce');
    $m = fn($k) => get_post_meta($post->ID, '_' . $k, true);
    ?>
    <p><label>Цена, ₽ (0 — «по запросу»)<br>
      <input type="number" min="0" step="1" name="mem_price" value="<?php echo esc_attr($m('mem_price')); ?>" style="width:100%"></label></p>
    <p><label>Старая цена, ₽<br>
      <input type="number" min="0" step="1" name="mem_price_old" value="<?php echo esc_attr($m('mem_price_old')); ?>" style="width:100%"></label></p>
    <p><label>Единица (необязательно)<br>
      <input type="text" name="mem_unit" value="<?php echo esc_attr($m('mem_unit')); ?>" placeholder="например: за метр" style="width:100%"></label></p>
    <p><label><input type="checkbox" name="mem_price_from" value="1" <?php checked($m('mem_price_from'), '1'); ?>> Показывать «от»</label></p>
    <p><label><input type="checkbox" name="mem_on_order" value="1" <?php checked($m('mem_on_order'), '1'); ?>> Под заказ</label></p>
    <?php
}

add_action('save_post_mem_product', function ($id) {
    if (!metadata_exists('post', $id, '_mem_price')) {
        add_post_meta($id, '_mem_price', 0, true); // нужно для сортировки
    }
    if (!isset($_POST['mem_price_nonce']) || !wp_verify_nonce($_POST['mem_price_nonce'], 'mem_price')) return;
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $id)) return;

    update_post_meta($id, '_mem_price', absint($_POST['mem_price'] ?? 0));
    foreach (['mem_sub', 'mem_unit'] as $k) {
        $v = sanitize_text_field(wp_unslash($_POST[$k] ?? ''));
        $v !== '' ? update_post_meta($id, '_' . $k, $v) : delete_post_meta($id, '_' . $k);
    }
    $sp = memorial_plugin_clean_specs(wp_unslash($_POST['mem_specs'] ?? ''));
    $sp !== '' ? update_post_meta($id, '_mem_specs', $sp) : delete_post_meta($id, '_mem_specs');
    $old = absint($_POST['mem_price_old'] ?? 0);
    $old ? update_post_meta($id, '_mem_price_old', $old) : delete_post_meta($id, '_mem_price_old');
    foreach (['mem_price_from', 'mem_on_order'] as $k) {
        update_post_meta($id, '_' . $k, empty($_POST[$k]) ? '' : '1');
    }
});

/* ---------- Колонки и фильтр в списке товаров ---------- */
add_filter('manage_mem_product_posts_columns', function ($cols) {
    $new = [];
    foreach ($cols as $k => $v) {
        if ($k === 'title') $new['mem_thumb'] = 'Фото';
        $new[$k] = $v;
        if ($k === 'title') $new['mem_price'] = 'Цена';
    }
    return $new;
});

add_action('manage_mem_product_posts_custom_column', function ($col, $id) {
    if ($col === 'mem_thumb') echo get_the_post_thumbnail($id, [50, 50]);
    if ($col === 'mem_price') echo wp_kses_post(memorial_plugin_price_html($id));
}, 10, 2);

add_action('restrict_manage_posts', function ($type) {
    if ($type !== 'mem_product') return;
    wp_dropdown_categories([
        'taxonomy' => 'mem_cat', 'name' => 'mem_cat', 'value_field' => 'slug',
        'show_option_all' => 'Все категории', 'hierarchical' => true,
        'selected' => sanitize_title($_GET['mem_cat'] ?? ''), 'hide_empty' => false,
    ]);
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
