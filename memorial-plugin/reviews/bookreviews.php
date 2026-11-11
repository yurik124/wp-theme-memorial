<?php
/**
 * «Мемориал → Отзывы из книги»: отзывы, перепечатанные из бумажной книги отзывов, с фото страницы.
 * На странице «Отзывы» показываются блоком «Из книги отзывов» под виджетами ([mem_reviews]),
 * отдельно — шорткодом [mem_bookreviews] (например, [mem_bookreviews limit="3"]).
 */
defined('ABSPATH') || exit;

add_action('init', function () {
    register_post_type('mem_bookrev', [
        'labels' => [
            'name' => 'Отзывы из книги', 'singular_name' => 'Отзыв из книги', 'menu_name' => 'Отзывы из книги',
            'all_items' => 'Отзывы из книги', 'add_new' => 'Добавить отзыв', 'add_new_item' => 'Новый отзыв из книги',
            'edit_item' => 'Редактировать отзыв', 'featured_image' => 'Фото страницы книги', 'set_featured_image' => 'Выбрать фото страницы',
            'remove_featured_image' => 'Убрать фото', 'use_featured_image' => 'Использовать как фото страницы',
        ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => false, 'show_in_rest' => false,
        'supports' => ['title', 'editor', 'thumbnail', 'page-attributes'],
    ]);
});


/** Филиалы для выбора: адреса из «Мемориал → Контакты» */
function memorial_plugin_branch_options() {
    $out = [];
    foreach (memorial_plugin_addrs() as $a) {
        $short = memorial_plugin_short_addr($a['addr']);
        if ($short !== '') $out[$short] = $short;
    }
    return $out;
}

/* Список и форма отзывов — DataViews + DataForm (admin/collections.php). */

/** «март 2024» */
function memorial_plugin_bookrev_month($id) {
    $d = get_post_meta($id, '_mem_bdate', true);
    if (!$d || !($ts = strtotime($d))) return '';
    $m = [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
    return $m[(int) gmdate('n', $ts)] . ' ' . gmdate('Y', $ts);
}

/** Отзывы из книги: сначала по порядку из админки, затем новые сверху */
function memorial_plugin_bookrevs($limit = -1) {
    $list = get_posts(['post_type' => 'mem_bookrev', 'post_status' => 'publish', 'posts_per_page' => -1]);
    usort($list, function ($a, $b) {
        return $a->menu_order <=> $b->menu_order
            ?: strcmp((string) get_post_meta($b->ID, '_mem_bdate', true), (string) get_post_meta($a->ID, '_mem_bdate', true))
            ?: $b->ID <=> $a->ID;
    });
    return $limit > 0 ? array_slice($list, 0, $limit) : $list;
}

function memorial_plugin_bookreviews_html($limit = -1, $heading = 'Из книги отзывов') {
    $list = memorial_plugin_bookrevs($limit);
    if (!$list) return '';
    $h = '<section class="brev">';
    if ($heading !== '') $h .= '<h2 class="brev__title">' . esc_html($heading) . '</h2>'
        . '<p class="brev__lead">Отзывы, которые клиенты оставили в бумажной книге отзывов в нашем офисе. Перепечатаны без изменений, рядом — фото страницы.</p>';
    $h .= '<div class="brev__list">';
    foreach ($list as $r) {
        $text = trim($r->post_content);
        if ($text === '') continue;
        $meta = array_filter(['Книга отзывов', get_post_meta($r->ID, '_mem_branch', true), memorial_plugin_bookrev_month($r->ID)]);
        $long = mb_strlen(wp_strip_all_tags($text)) > 420;
        $h .= '<article class="brev__item">';
        $h .= '<div class="brev__body">';
        $h .= '<blockquote class="brev__text' . ($long ? ' is-long' : '') . '">' . wpautop(esc_html(wp_strip_all_tags($text))) . '</blockquote>';
        if ($long) $h .= '<button type="button" class="brev__more" aria-expanded="false" data-wp-interactive="memorial-plugin/book" data-wp-on--click="actions.more">Читать полностью</button>';
        $h .= '<p class="brev__author">' . esc_html(get_the_title($r)) . '</p>';
        $h .= '<p class="brev__meta">' . esc_html(implode(' · ', $meta)) . '</p>';
        $h .= '</div>';
        if (has_post_thumbnail($r)) {
            $tid = get_post_thumbnail_id($r);
            $h .= '<a class="brev__photo" href="' . esc_url(wp_get_attachment_image_url($tid, 'large')) . '" data-lb data-cap="' . esc_attr(get_the_title($r) . ' · ' . implode(' · ', $meta)) . '">'
                . wp_get_attachment_image($tid, 'medium', false, ['loading' => 'lazy', 'alt' => 'Фото страницы книги отзывов: ' . get_the_title($r)])
                . '<span>Фото страницы</span></a>';
        }
        $h .= '</article>';
    }
    return $h . '</div></section>';
}

add_shortcode('mem_bookreviews', function ($atts) {
    $a = shortcode_atts(['limit' => -1, 'title' => 'Из книги отзывов'], $atts, 'mem_bookreviews');
    return memorial_plugin_bookreviews_html((int) $a['limit'], $a['title']);
});
