<?php
/**
 * Страница «Вопрос–ответ»: шорткод [mem_faq] сам собирает вопросы со всех опубликованных страниц —
 * из блоков «Подробности» (раскрывающиеся вопросы). Новый вопрос на любой странице появится здесь автоматически.
 *
 *   [mem_faq]                     — все вопросы, по разделам (страницам)
 *   [mem_faq exclude="12,34"]     — без вопросов с этих страниц (номера страниц)
 *   [mem_faq search="0"]          — без поля поиска
 *   [mem_faq chasto="документы, морг"] — свои слова «Часто ищут» (по умолчанию — из статистики «Что ищут на сайте»)
 */
defined('ABSPATH') || exit;

/** Вопросы одного набора блоков: [вопрос, ответ HTML] */
function memorial_plugin_faq_from_blocks(array $blocks, array &$out) {
    foreach ($blocks as $b) {
        if ($b['blockName'] === 'core/details' && preg_match('~<summary[^>]*>(.*?)</summary>~s', $b['innerHTML'], $m)) {
            $q = trim(wp_strip_all_tags($m[1]));
            $a = '';
            foreach ($b['innerBlocks'] as $ib) $a .= render_block($ib);
            $a = do_shortcode($a);
            if ($q !== '' && trim(wp_strip_all_tags($a)) !== '') $out[] = [$q, $a];
            continue;
        }
        if (!empty($b['innerBlocks'])) memorial_plugin_faq_from_blocks($b['innerBlocks'], $out);
    }
}

/** Все вопросы сайта, по страницам. Кэш сбрасывается при сохранении любой страницы. */
function memorial_plugin_faq_all() {
    $cached = get_transient('mem_faq_all');
    if (is_array($cached)) return $cached;

    // порядок разделов — как в главном меню, остальные страницы — по названию
    $order = [];
    $locs = get_nav_menu_locations();
    if (!empty($locs['primary'])) {
        foreach ((array) wp_get_nav_menu_items($locs['primary']) as $i => $it) {
            if ($it->object === 'page') $order[(int) $it->object_id] = $i;
        }
    }
    $pages = get_posts(['post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 's' => 'wp:details', 'search_columns' => ['post_content']]);
    if (!$pages) { // старые версии WordPress не знают search_columns
        $pages = array_filter(get_posts(['post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1]), fn($p) => strpos($p->post_content, 'wp:details') !== false);
    }
    $out = [];
    foreach ($pages as $p) {
        if (has_shortcode($p->post_content, 'mem_faq') || post_password_required($p)) continue;
        $qs = [];
        memorial_plugin_faq_from_blocks(parse_blocks($p->post_content), $qs);
        if ($qs) $out[] = ['id' => $p->ID, 'title' => get_the_title($p), 'url' => get_permalink($p), 'qs' => $qs, 'o' => $order[$p->ID] ?? 999];
    }
    usort($out, fn($a, $b) => $a['o'] <=> $b['o'] ?: strcmp($a['title'], $b['title']));
    set_transient('mem_faq_all', $out, DAY_IN_SECONDS);
    return $out;
}

add_action('save_post_page', fn() => delete_transient('mem_faq_all'));
add_action('deleted_post', fn() => delete_transient('mem_faq_all'));
add_action('wp_update_nav_menu', fn() => delete_transient('mem_faq_all'));

add_shortcode('mem_faq', function ($atts) {
    $a = shortcode_atts(['exclude' => '', 'search' => '1', 'chasto' => ''], $atts, 'mem_faq');
    $skip = array_map('intval', array_filter(explode(',', $a['exclude'])));
    $groups = array_values(array_filter(memorial_plugin_faq_all(), fn($g) => !in_array($g['id'], $skip, true) && $g['id'] !== get_the_ID()));
    if (!$groups) return current_user_can('edit_pages') ? '<p class="is-style-note">[mem_faq]: на страницах пока нет вопросов (блоков «Подробности»).</p>' : '';

    $total = array_sum(array_map(fn($g) => count($g['qs']), $groups));
    ob_start();
    echo '<div class="mfaq" data-faq>';
    if ($a['search'] !== '0') {
        echo memorial_plugin_search_box(['id' => 'mfaq-q', 'label' => 'Поиск по вопросам', 'placeholder' => 'Например: документы',
            // «Часто ищут»: заданные в шорткоде слова, иначе — из статистики поиска (content/search-stats.php)
            'popular' => trim($a['chasto']) !== '' ? explode(',', $a['chasto']) : (function_exists('memorial_plugin_sq_popular') ? memorial_plugin_sq_popular(['документы', 'стоимость', 'пособие', 'кремация', 'морг', 'памятник']) : ['документы', 'стоимость', 'пособие', 'кремация', 'морг', 'памятник']),
            'none' => 'Такого вопроса пока нет на сайте. Позвоните или оставьте номер — ответим.']);
    }
    if (count($groups) > 1) {
        // Темы — фильтр: показывают вопросы одной темы на этой же странице
        echo '<div class="srow"><div class="mfaq__toc srow__in" role="group" aria-label="Темы вопросов"><button type="button" class="chip is-on" data-faq-group="" aria-pressed="true">Все <small>' . $total . '</small></button>';
        foreach ($groups as $g) echo '<button type="button" class="chip" data-faq-group="faq-' . (int) $g['id'] . '" aria-pressed="false">' . esc_html($g['title']) . ' <small>' . count($g['qs']) . '</small></button>';
        echo '</div></div>';
    }
    $used = [];
    foreach ($groups as $g) {
        echo '<section class="mfaq__group" id="faq-' . (int) $g['id'] . '"><h2 class="mfaq__title">' . esc_html($g['title']) . '</h2>';
        foreach ($g['qs'] as [$q, $ans]) {
            $id = memorial_plugin_q_anchor($q);
            $used[$id] = ($used[$id] ?? 0) + 1;
            if ($used[$id] > 1) $id .= '-' . $used[$id];
            echo '<details class="wp-block-details"' . ($id ? ' id="' . esc_attr($id) . '"' : '') . ' data-g="' . esc_attr($g['title']) . '"><summary>' . esc_html($q) . '</summary>' . $ans . '</details>';
        }
        echo '<p class="mfaq__more"><a href="' . esc_url($g['url']) . '">Подробнее — на странице «' . esc_html($g['title']) . '» →</a></p></section>';
    }
    echo '</div>';
    return ob_get_clean();
});

/* Разметка FAQ для поисковиков на самой странице «Вопрос–ответ» */
add_action('wp_head', function () {
    if (!is_page() || !has_shortcode((string) get_queried_object()->post_content, 'mem_faq') || !function_exists('memorial_plugin_ld')) return;
    $items = [];
    foreach (memorial_plugin_faq_all() as $g) {
        if ($g['id'] === get_queried_object_id()) continue;
        foreach ($g['qs'] as [$q, $ans]) {
            $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($ans)));
            $items[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $t]];
        }
    }
    if (count($items) >= 2) memorial_plugin_ld(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items]);
}, 7);
