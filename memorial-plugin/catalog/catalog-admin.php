<?php
/**
 * Удобства каталога в админке:
 * — у категории: пропорции фото, колонки, порядок разделов;
 * — в списке товаров: вкладки по категориям, цена и характеристики правятся прямо в списке, перетаскивание для порядка;
 * — на вкладке категории: «Загрузить фото» → сразу несколько новых товаров.
 */
defined('ABSPATH') || exit;

/* ---------- Настройки категории ---------- */
function memorial_plugin_cat_fields($term = null) {
    $v = fn($k) => $term ? get_term_meta($term->term_id, $k, true) : '';
    $ratio = $v('mem_ratio');
    $rows = [
        ['mem_ratio', 'Пропорции фото', '<select name="mem_ratio" id="mem_ratio"><option value="">Как у родительской категории</option>'
            . implode('', array_map(fn($k, $l) => '<option value="' . esc_attr($k) . '"' . selected($ratio, $k, false) . '>' . esc_html($l) . '</option>', array_keys(memorial_plugin_ratios()), memorial_plugin_ratios()))
            . '</select>', 'Как обрезать фото в карточках. «Целиком» — фото не обрезается, по краям белые поля.'],
        ['mem_cols', 'Колонок на компьютере', '<input type="number" min="2" max="6" name="mem_cols" id="mem_cols" value="' . esc_attr($v('mem_cols')) . '" placeholder="4" style="width:6em">', 'На телефоне всегда 2.'],
        ['mem_order', 'Порядок раздела', '<input type="number" name="mem_order" id="mem_order" value="' . esc_attr($v('mem_order')) . '" placeholder="0" style="width:6em">', 'Разделы на странице идут по возрастанию этого числа.'],
        ['mem_anchor', 'Якорь раздела', '<input type="text" name="mem_anchor" id="mem_anchor" value="' . esc_attr($v('mem_anchor')) . '" placeholder="например: korziny">', 'Для ссылок вида #korziny. Можно не заполнять.'],
    ];
    foreach ($rows as [$k, $label, $input, $help]) {
        if ($term) {
            echo '<tr class="form-field"><th scope="row"><label for="' . $k . '">' . esc_html($label) . '</label></th><td>' . $input . '<p class="description">' . esc_html($help) . '</p></td></tr>';
        } else {
            echo '<div class="form-field"><label for="' . $k . '">' . esc_html($label) . '</label>' . $input . '<p>' . esc_html($help) . '</p></div>';
        }
    }
    wp_nonce_field('mem_cat', 'mem_cat_nonce');
}
add_action('mem_cat_add_form_fields', fn() => memorial_plugin_cat_fields());
add_action('mem_cat_edit_form_fields', fn($t) => memorial_plugin_cat_fields($t));

function memorial_plugin_cat_save($id) {
    if (!isset($_POST['mem_cat_nonce']) || !wp_verify_nonce($_POST['mem_cat_nonce'], 'mem_cat') || !current_user_can('manage_categories')) return;
    $vals = [
        'mem_ratio'  => array_key_exists($_POST['mem_ratio'] ?? '', memorial_plugin_ratios()) ? $_POST['mem_ratio'] : '',
        'mem_cols'   => ($c = absint($_POST['mem_cols'] ?? 0)) ? min(6, max(2, $c)) : '',
        'mem_order'  => ($_POST['mem_order'] ?? '') === '' ? '' : (int) $_POST['mem_order'],
        'mem_anchor' => sanitize_title($_POST['mem_anchor'] ?? ''),
    ];
    foreach ($vals as $k => $v) $v === '' ? delete_term_meta($id, $k) : update_term_meta($id, $k, $v);
}
add_action('created_mem_cat', 'memorial_plugin_cat_save');
add_action('edited_mem_cat', 'memorial_plugin_cat_save');

/* ---------- Список товаров: цена и характеристики правятся прямо в списке ---------- */
add_filter('manage_mem_product_posts_columns', function ($cols) {
    $new = [];
    foreach ($cols as $k => $v) {
        if ($k === 'date') continue;
        if ($k === 'mem_price') { $new['mem_edit_price'] = 'Цена'; $new['mem_edit_specs'] = 'Подпись и характеристики'; continue; }
        $new[$k] = $v;
    }
    return $new;
}, 20);

add_action('manage_mem_product_posts_custom_column', function ($col, $id) {
    $m = fn($k) => (string) get_post_meta($id, '_' . $k, true);
    if ($col === 'mem_edit_price') {
        echo '<div class="mem-ie" data-id="' . (int) $id . '">'
           . '<input type="number" min="0" step="1" data-f="price" value="' . esc_attr($m('mem_price') ?: '') . '" placeholder="по запросу" aria-label="Цена, ₽"> ₽'
           . '<label><input type="checkbox" data-f="from" value="1"' . checked($m('mem_price_from'), '1', false) . '> «от»</label>'
           . '<input type="text" data-f="unit" value="' . esc_attr($m('mem_unit')) . '" placeholder="единица: за метр" aria-label="Единица">'
           . '<span class="mem-ie__st" aria-live="polite"></span></div>';
        // данные для «Свойств»
        printf('<span hidden class="mem-qe" data-price="%s" data-sub="%s" data-unit="%s" data-from="%s"></span>',
            esc_attr($m('mem_price')), esc_attr($m('mem_sub')), esc_attr($m('mem_unit')), esc_attr($m('mem_price_from')));
    }
    if ($col === 'mem_edit_specs') {
        echo '<div class="mem-ie" data-id="' . (int) $id . '">'
           . '<input type="text" data-f="sub" value="' . esc_attr($m('mem_sub')) . '" placeholder="подпись: цвет, материал…" aria-label="Подпись">'
           . '<textarea data-f="specs" rows="2" placeholder="Ширина × высота: 700 × 2050 мм&#10;Профиль: 40 × 20 мм" aria-label="Характеристики">' . esc_textarea($m('mem_specs')) . '</textarea>'
           . '<span class="mem-ie__st" aria-live="polite"></span></div>';
    }
}, 20, 2);

/* Сохранение поля из списка */
add_action('wp_ajax_mem_inline', function () {
    check_ajax_referer('mem_sort');
    $id = absint($_POST['id'] ?? 0);
    if (!$id || get_post_type($id) !== 'mem_product' || !current_user_can('edit_post', $id)) wp_send_json_error('Нет прав');
    $f = sanitize_key($_POST['f'] ?? '');
    $v = wp_unslash($_POST['v'] ?? '');
    switch ($f) {
        case 'price': update_post_meta($id, '_mem_price', absint($v)); break;
        case 'from':  update_post_meta($id, '_mem_price_from', $v ? '1' : ''); break;
        case 'unit':
        case 'sub':   $v = sanitize_text_field($v); $v !== '' ? update_post_meta($id, '_mem_' . $f, $v) : delete_post_meta($id, '_mem_' . $f); break;
        case 'specs': $v = memorial_plugin_clean_specs($v); $v !== '' ? update_post_meta($id, '_mem_specs', $v) : delete_post_meta($id, '_mem_specs'); break;
        default: wp_send_json_error('Поле?');
    }
    wp_send_json_success();
});

/* Вкладки категорий над списком товаров */
function memorial_plugin_cat_count($term_id) {
    return (int) (new WP_Query(['post_type' => 'mem_product', 'post_status' => ['publish', 'draft', 'pending', 'private'], 'fields' => 'ids', 'posts_per_page' => 1,
        'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $term_id, 'include_children' => true]]]))->found_posts;
}

add_action('all_admin_notices', function () {
    $s = get_current_screen();
    if (!$s || $s->id !== 'edit-mem_product') return;
    $base = admin_url('edit.php?post_type=mem_product');
    $cur = get_term_by('slug', sanitize_title($_GET['mem_cat'] ?? ''), 'mem_cat');
    $top = $cur ? ($cur->parent ? (get_ancestors($cur->term_id, 'mem_cat')[count(get_ancestors($cur->term_id, 'mem_cat')) - 1] ?? $cur->term_id) : $cur->term_id) : 0;
    $tops = get_terms(['taxonomy' => 'mem_cat', 'parent' => 0, 'hide_empty' => false]);
    echo '<nav class="nav-tab-wrapper mem-tabs">';
    echo '<a class="nav-tab' . ($cur ? '' : ' nav-tab-active') . '" href="' . esc_url($base) . '">Все</a>';
    foreach (is_wp_error($tops) ? [] : $tops as $t) {
        echo '<a class="nav-tab' . ($top === $t->term_id ? ' nav-tab-active' : '') . '" href="' . esc_url(add_query_arg('mem_cat', $t->slug, $base)) . '">'
           . esc_html($t->name) . ' <span class="count">' . memorial_plugin_cat_count($t->term_id) . '</span></a>';
    }
    echo '</nav>';
    if ($top && ($kids = memorial_plugin_sorted_children($top))) {
        $tt = get_term($top, 'mem_cat');
        echo '<p class="mem-subtabs"><a href="' . esc_url(add_query_arg('mem_cat', $tt->slug, $base)) . '"' . ($cur->term_id === $top ? ' class="is-on"' : '') . '>Все разделы</a>';
        foreach ($kids as $k) echo '<a href="' . esc_url(add_query_arg('mem_cat', $k->slug, $base)) . '"' . ($cur->term_id === $k->term_id ? ' class="is-on"' : '') . '>' . esc_html($k->name) . ' <span>' . memorial_plugin_cat_count($k->term_id) . '</span></a>';
        echo '</p>';
    }
    if ($cur) {
        // Загрузка фото → новые товары в этой категории
        echo '<div class="mem-add"><strong>Добавить товары в «' . esc_html($cur->name) . '»:</strong> '
           . '<button type="button" class="button button-primary" id="mem-add-pick" data-cat="' . (int) $cur->term_id . '">Загрузить фото</button> '
           . '<span class="mem-add__names">названия: <label><input type="radio" name="mem_add_names" value="num" checked> по номерам, начало <input type="text" id="mem-add-prefix" value="№ " size="4"></label> '
           . '<label><input type="radio" name="mem_add_names" value="file"> из названия файла</label></span>'
           . '<span class="mem-add__st" aria-live="polite"></span>'
           . '<br><span class="description">Можно выбрать сразу много фото или перетащить их с компьютера в окно выбора. Для каждого фото появится товар — сразу впишите цену и характеристики в списке, всё сохраняется само.</span></div>';
    }
    if (!empty($_GET['mem_new'])) {
        echo '<div class="notice notice-success"><p>Новые товары: впишите цены и характеристики — сохраняется само, без кнопки. '
           . '<a href="' . esc_url(remove_query_arg('mem_new')) . '">Показать все товары раздела →</a></p></div>';
    }
});

/* Только что добавленные товары — отдельным списком */
add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query() || $q->get('post_type') !== 'mem_product' || empty($_GET['mem_new'])) return;
    $q->set('post__in', array_map('absint', explode(',', (string) $_GET['mem_new'])));
});

/* Создать товары из фото (кнопка «Загрузить фото» на вкладке категории) */
add_action('wp_ajax_mem_bulk_add', function () {
    check_ajax_referer('mem_sort');
    if (!current_user_can('edit_posts')) wp_send_json_error('Нет прав');
    $cat = absint($_POST['cat'] ?? 0);
    $ids = array_filter(array_map('absint', (array) ($_POST['ids'] ?? [])));
    if (!$cat || !$ids || !term_exists($cat, 'mem_cat')) wp_send_json_error('Нет категории или фото');
    $existing = get_posts(['post_type' => 'mem_product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
                           'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $cat, 'include_children' => false]]]);
    $order = 0; $num = 0;
    $prefix = trim(sanitize_text_field(wp_unslash($_POST['prefix'] ?? '')));
    foreach ($existing as $e) {
        $order = max($order, (int) get_post_field('menu_order', $e));
        if (preg_match('/(\d+)\s*$/u', get_the_title($e), $mm)) $num = max($num, (int) $mm[1]);
    }
    $made = [];
    foreach ($ids as $att) {
        if (get_post_type($att) !== 'attachment') continue;
        $title = ($_POST['names'] ?? 'num') === 'file' ? (get_the_title($att) ?: 'Товар') : trim($prefix . ' ' . (++$num));
        $title = str_replace('№ ', "№\u{00A0}", $title);
        $id = wp_insert_post(['post_type' => 'mem_product', 'post_status' => 'publish', 'post_title' => $title, 'menu_order' => ++$order]);
        if (is_wp_error($id) || !$id) continue;
        set_post_thumbnail($id, $att);
        wp_set_object_terms($id, [$cat], 'mem_cat');
        update_post_meta($id, '_mem_price', 0);
        $made[] = $id;
    }
    wp_send_json_success(['ids' => $made]);
});

/* «Свойства» (быстрое редактирование): цена, «от», единица, подпись */
add_action('quick_edit_custom_box', function ($col, $type) {
    if ($type !== 'mem_product' || $col !== 'mem_price') return;
    wp_nonce_field('mem_qe', 'mem_qe_nonce'); ?>
    <fieldset class="inline-edit-col-right mem-qe-box"><div class="inline-edit-col">
      <label><span class="title">Цена, ₽</span><span class="input-text-wrap"><input type="number" min="0" name="mem_qe_price"></span></label>
      <label><span class="title">Единица</span><span class="input-text-wrap"><input type="text" name="mem_qe_unit" placeholder="за метр"></span></label>
      <label><span class="title">Подпись</span><span class="input-text-wrap"><input type="text" name="mem_qe_sub"></span></label>
      <label class="alignleft"><input type="checkbox" name="mem_qe_from" value="1"> <span class="checkbox-title">Цена «от»</span></label>
    </div></fieldset>
    <?php
}, 10, 2);

add_action('save_post_mem_product', function ($id) {
    if (!isset($_POST['mem_qe_nonce']) || !wp_verify_nonce($_POST['mem_qe_nonce'], 'mem_qe') || !current_user_can('edit_post', $id)) return;
    update_post_meta($id, '_mem_price', absint($_POST['mem_qe_price'] ?? 0));
    update_post_meta($id, '_mem_price_from', empty($_POST['mem_qe_from']) ? '' : '1');
    foreach (['sub', 'unit'] as $k) {
        $v = sanitize_text_field(wp_unslash($_POST['mem_qe_' . $k] ?? ''));
        $v !== '' ? update_post_meta($id, '_mem_' . $k, $v) : delete_post_meta($id, '_mem_' . $k);
    }
}, 20);

/* В админке товары по умолчанию в том же порядке, что на сайте */
add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query() || $q->get('post_type') !== 'mem_product' || !empty($_GET['orderby'])) return;
    $q->set('orderby', ['menu_order' => 'ASC', 'date' => 'ASC']);
});

/* Перетаскивание строк: сохраняем порядок */
add_action('wp_ajax_mem_sort', function () {
    check_ajax_referer('mem_sort');
    $ids = array_map('absint', (array) ($_POST['ids'] ?? []));
    $off = absint($_POST['offset'] ?? 0);
    foreach ($ids as $i => $id) {
        if ($id && in_array(get_post_type($id), ['mem_product', 'mem_work'], true) && current_user_can('edit_post', $id)) {
            wp_update_post(['ID' => $id, 'menu_order' => $off + $i + 1]);
        }
    }
    wp_send_json_success();
});

add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'mem_product') return;
    $dir = MEMORIAL_PLUGIN_DIR; $uri = MEMORIAL_PLUGIN_URL;
    if ($hook === 'edit.php') wp_enqueue_media();
    wp_enqueue_script('mem-catalog-admin', $uri . '/assets/js/catalog-admin.js', ['jquery', 'jquery-ui-sortable'], filemtime($dir . '/assets/js/catalog-admin.js'), true);
    $per = (int) get_user_option('edit_mem_product_per_page') ?: 20;
    wp_localize_script('mem-catalog-admin', 'memCat', [
        'nonce'  => wp_create_nonce('mem_sort'),
        'offset' => (max(1, (int) ($_GET['paged'] ?? 1)) - 1) * $per,
        'sortable' => $hook === 'edit.php' && !empty($_GET['mem_cat']) && empty($_GET['orderby']),
    ]);
    wp_add_inline_style('wp-admin', '.post-type-mem_product .column-mem_thumb{width:60px}.post-type-mem_product .column-mem_thumb img{width:50px;height:50px;object-fit:cover;border-radius:4px}'
        . '.mem-sortable #the-list tr{cursor:move}.mem-sortable #the-list tr.ui-sortable-helper{background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.15)}'
        . '.mem-hint{background:#fff;border-left:4px solid #b89b5e;padding:8px 12px;margin:10px 0}'
        . '.mem-tabs{margin:12px 0 0}.mem-tabs .count{color:#8c8f94;font-weight:400;font-size:12px}'
        . '.mem-subtabs{margin:10px 0 0;display:flex;flex-wrap:wrap;gap:6px}.mem-subtabs a{padding:4px 10px;border:1px solid #c3c4c7;border-radius:999px;text-decoration:none;background:#fff}'
        . '.mem-subtabs a.is-on{background:#1d2327;border-color:#1d2327;color:#fff}.mem-subtabs span{color:#8c8f94;font-size:12px}'
        . '.mem-add{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:10px 14px;margin:12px 0;line-height:2.2}.mem-add__names{margin-left:10px;color:#50575e}.mem-add__st{margin-left:10px;font-weight:600}'
        . '.post-type-mem_product .column-mem_edit_price{width:190px}.post-type-mem_product .column-mem_edit_specs{width:34%}'
        . '.mem-ie{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px}.mem-ie input[type=number]{width:110px}.mem-ie input[type=text],.mem-ie textarea{width:100%}'
        . '.mem-ie textarea{font-size:12px;line-height:1.4}.mem-ie__st{font-size:12px;color:#00a32a;min-height:1em}.mem-ie__st.is-err{color:#d63638}'
        . '.mem-ie .is-saving{background:#fcf9e8}');
});

/* Подсказка над списком */
add_action('admin_notices', function () {
    $s = get_current_screen();
    if (!$s || $s->id !== 'edit-mem_product') return;
    if (!empty($_GET['mem_cat']) && empty($_GET['orderby']) && empty($_GET['mem_new'])) {
        echo '<div class="mem-hint">Цену, подпись и характеристики меняйте прямо в списке — сохраняется само. Порядок на сайте — перетаскиванием строк за фото или название.</div>';
    }
});

