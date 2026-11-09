<?php
/**
 * Типы товаров = категории верхнего уровня каталога («Гробы», «Венки», «Кресты», «Памятники»…).
 * У каждого типа — свой набор характеристик (текст, число с единицей, выбор из списка) и свои категории (подкатегории).
 * Значения характеристик товара хранятся строками «Название: значение» в _mem_specs — их и показывает карточка на сайте.
 */
defined('ABSPATH') || exit;

/** Тип товара: верхняя категория, к которой относится категория $term_id */
function memorial_plugin_type_root($term_id) {
    $anc = get_ancestors((int) $term_id, 'mem_cat', 'taxonomy');
    return $anc ? (int) end($anc) : (int) $term_id;
}

/** Тип товара по товару */
function memorial_plugin_product_type($post_id) {
    $terms = wp_get_object_terms($post_id, 'mem_cat', ['fields' => 'ids']);
    return (!is_wp_error($terms) && $terms) ? memorial_plugin_type_root($terms[0]) : 0;
}

/**
 * Характеристики типа: [['label' => 'Высота', 'type' => 'number', 'unit' => 'см', 'options' => []], …]
 * Если ещё не настроены — собираются из уже заполненных характеристик товаров этого типа (как текстовые).
 */
function memorial_plugin_type_fields($type_id, $guess = true) {
    $raw = get_term_meta($type_id, 'mem_fields', true);
    $f = is_string($raw) ? json_decode($raw, true) : $raw;
    if (is_array($f) && $f) return array_values(array_filter(array_map('memorial_plugin_clean_field', $f)));
    if (!$guess) return [];
    $labels = [];
    foreach (get_posts(['post_type' => 'mem_product', 'post_status' => 'any', 'posts_per_page' => 200, 'fields' => 'ids',
                        'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $type_id, 'include_children' => true]]]) as $pid) {
        foreach (memorial_plugin_product_specs($pid) as [$k]) if ($k !== '') $labels[$k] = true;
    }
    return array_map(fn($l) => ['label' => $l, 'type' => 'text', 'unit' => '', 'options' => [], 'default' => ''], array_keys($labels));
}

function memorial_plugin_clean_field($f) {
    $label = trim(sanitize_text_field($f['label'] ?? ''));
    if ($label === '') return null;
    $type = in_array($f['type'] ?? '', ['text', 'number', 'list'], true) ? $f['type'] : 'text';
    $opts = $f['options'] ?? [];
    if (is_string($opts)) $opts = explode(',', $opts);
    $opts = array_values(array_unique(array_filter(array_map(fn($o) => trim(sanitize_text_field($o)), (array) $opts), 'strlen')));
    $def = trim(sanitize_text_field($f['default'] ?? ''));
    if ($type === 'number') $def = preg_replace('/[^\d.,-]/', '', $def);
    if ($type === 'list' && $def !== '' && !in_array($def, $opts, true)) $def = '';
    return ['label' => $label, 'type' => $type, 'unit' => $type === 'number' ? trim(sanitize_text_field($f['unit'] ?? '')) : '',
            'options' => $type === 'list' ? $opts : [], 'default' => $def];
}

/** Значения характеристик товара по названиям: ['Высота' => '120 см', …] */
function memorial_plugin_product_spec_map($post_id) {
    $m = [];
    foreach (memorial_plugin_product_specs($post_id) as [$k, $v]) $m[$k] = $v;
    return $m;
}

/** Записать одну характеристику; порядок строк — как в настройках типа, остальные строки сохраняются в конце */
function memorial_plugin_set_spec($post_id, $label, $value) {
    $map = memorial_plugin_product_spec_map($post_id);
    $value = trim(sanitize_text_field($value));
    if ($value === '') unset($map[$label]); else $map[$label] = $value;
    $order = array_column(memorial_plugin_type_fields(memorial_plugin_product_type($post_id), false), 'label');
    $lines = [];
    foreach ($order as $l) if (isset($map[$l])) { $lines[] = $l . ': ' . $map[$l]; unset($map[$l]); }
    foreach ($map as $k => $v) $lines[] = ($k !== '' ? $k . ': ' : '') . $v;
    $lines ? update_post_meta($post_id, '_mem_specs', implode("\n", $lines)) : delete_post_meta($post_id, '_mem_specs');
}

/* Удалили тип — удаляем и его настройки (WordPress делает это сам вместе с term meta) */

/** Значения по умолчанию для новых товаров типа: цена, «от», единица */
function memorial_plugin_type_defaults($type_id) {
    $d = get_term_meta($type_id, 'mem_defaults', true);
    $d = is_array($d) ? $d : [];
    return ['price' => (int) ($d['price'] ?? 0) ?: '', 'from' => !empty($d['from']), 'unit' => (string) ($d['unit'] ?? '')];
}

/** Применить значения по умолчанию к новому товару */
function memorial_plugin_apply_type_defaults($post_id, $type_id) {
    $d = memorial_plugin_type_defaults($type_id);
    if ($d['price']) update_post_meta($post_id, '_mem_price', $d['price']);
    if ($d['from']) update_post_meta($post_id, '_mem_price_from', '1');
    if ($d['unit'] !== '') update_post_meta($post_id, '_mem_unit', $d['unit']);
    $lines = [];
    foreach (memorial_plugin_type_fields($type_id, false) as $f) {
        if (($f['default'] ?? '') === '') continue;
        $lines[] = $f['label'] . ': ' . $f['default'] . ($f['type'] === 'number' && $f['unit'] ? ' ' . $f['unit'] : '');
    }
    if ($lines) update_post_meta($post_id, '_mem_specs', implode("\n", $lines));
}
