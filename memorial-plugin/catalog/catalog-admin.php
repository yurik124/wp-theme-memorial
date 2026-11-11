<?php
/**
 * Настройки категории каталога: пропорции фото, колонки, порядок разделов, якорь.
 * Список товаров — admin/catalog-views.php.
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

/* Список товаров — DataViews (admin/catalog-views.php): цена, характеристики, категории, фото и порядок правятся там */
