<?php
/**
 * Совместимость с SEO-плагинами (All in One SEO, Yoast SEO, Rank Math, SEOPress) и заголовком WordPress.
 * В SEO-заголовок и описание можно вставлять шорткоды плагина — они превращаются в текст:
 *   «Венки в Белгороде от [mem_cat_price cat="Венки"]» → «Венки в Белгороде от 800 ₽».
 * Работает для всех шорткодов [mem_…], которые выводят текст (цены, число моделей, годы работы, телефон…).
 */
defined('ABSPATH') || exit;

/** Шорткоды [mem_…] в строке → обычный текст (без тегов и лишних пробелов) */
function mem_seo_sc($s) {
    if (!is_string($s) || strpos($s, '[mem_') === false) return $s;
    $out = do_shortcode($s);
    $out = html_entity_decode(wp_strip_all_tags($out), ENT_QUOTES, 'UTF-8');
    $out = preg_replace('~\[/?mem_[^\]]*\]~u', '', $out); // неизвестный шорткод — убираем, чтобы скобки не попали в поиск
    return trim(preg_replace('~\s+~u', ' ', $out));
}

/* Заголовок WordPress (когда SEO-плагина нет) */
add_filter('document_title_parts', function ($parts) {
    return array_map('mem_seo_sc', (array) $parts);
}, 99);

/* All in One SEO, Yoast, Rank Math, SEOPress: заголовок, описание, соцсети */
foreach ([
    'aioseo_title', 'aioseo_description',
    'wpseo_title', 'wpseo_metadesc', 'wpseo_opengraph_title', 'wpseo_opengraph_desc', 'wpseo_twitter_title', 'wpseo_twitter_description',
    'rank_math/frontend/title', 'rank_math/frontend/description', 'rank_math/opengraph/facebook/og_title', 'rank_math/opengraph/facebook/og_description',
    'rank_math/opengraph/twitter/twitter_title', 'rank_math/opengraph/twitter/twitter_description',
    'seopress_titles_title', 'seopress_titles_desc', 'seopress_social_og_title', 'seopress_social_og_desc',
] as $hook) {
    add_filter($hook, 'mem_seo_sc', 99);
}
/* AIOSEO: теги соцсетей приходят массивом */
foreach (['aioseo_facebook_tags', 'aioseo_twitter_tags'] as $hook) {
    add_filter($hook, function ($tags) {
        if (!is_array($tags)) return $tags;
        foreach ($tags as $k => $v) if (is_string($v)) $tags[$k] = mem_seo_sc($v);
        return $tags;
    }, 99);
}
/* Разметка AIOSEO (названия и описания страниц в графе) */
add_filter('aioseo_schema_output', function ($graphs) {
    if (!is_array($graphs)) return $graphs;
    array_walk_recursive($graphs, function (&$v, $k) { if (in_array($k, ['name', 'description', 'headline'], true)) $v = mem_seo_sc($v); });
    return $graphs;
}, 99);
