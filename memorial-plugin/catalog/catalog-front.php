<?php
/**
 * Каталог внутри текста страницы: шорткод [mem_catalog].
 *
 *   [mem_catalog]                — категория, привязанная к этой странице (ставится при переносе со страницы)
 *   [mem_catalog cat="venki"]    — категория по адресу (slug) или номеру
 *   [mem_catalog nav="0"]        — без кнопок-разделов вверху; sort="0" — без сортировки
 *
 * Подкатегории становятся разделами с заголовками и якорями, вверху — кнопки для перехода к разделу.
 * Фото открываются крупно (с листанием), сортировка работает без перезагрузки.
 * Пропорции фото и число колонок задаются у категории: «Каталог → Категории».
 */
defined('ABSPATH') || exit;

/** Пропорции фото в карточках */
function memorial_plugin_ratios() {
    return [
        'photo'    => __( 'Photo 4:3 (coffins, examples of works)', 'memorial-plugin' ),
        'portrait' => __( 'Portrait 3:4 (wreaths, monuments)', 'memorial-plugin' ),
        'tall'     => __( 'Narrow tall 3:5 (crosses), whole', 'memorial-plugin' ),
        'land'     => __( 'Wide 5:3 (fences, drawings), whole', 'memorial-plugin' ),
        'square'   => __( 'Square', 'memorial-plugin' ),
    ];
}

/** Настройка категории с наследованием от родителя */
function memorial_plugin_term_opt($term_id, $key, $def = '') {
    $guard = 0;
    while ($term_id && $guard++ < 5) {
        $v = get_term_meta($term_id, $key, true);
        if ($v !== '' && $v !== false) return $v;
        $t = get_term($term_id, 'mem_cat');
        $term_id = ($t && !is_wp_error($t)) ? (int) $t->parent : 0;
    }
    return $def;
}

/** Подкатегории по порядку (поле «Порядок» у категории, затем по дате создания) */
function memorial_plugin_sorted_children($term_id) {
    $kids = get_terms(['taxonomy' => 'mem_cat', 'parent' => $term_id, 'hide_empty' => false]);
    if (is_wp_error($kids)) return [];
    usort($kids, function ($a, $b) {
        $oa = (int) get_term_meta($a->term_id, 'mem_order', true);
        $ob = (int) get_term_meta($b->term_id, 'mem_order', true);
        return $oa <=> $ob ?: $a->term_id <=> $b->term_id;
    });
    return $kids;
}

/** Категория страницы: из шорткода или привязки при переносе */
function memorial_plugin_resolve_cat($val, $page_id = 0) {
    $val = trim((string) $val);
    if ($val === '' && $page_id) $val = (string) get_post_meta($page_id, '_mem_cat', true);
    if ($val === '') return null;
    $t = ctype_digit($val) ? get_term((int) $val, 'mem_cat') : get_term_by('slug', sanitize_title($val), 'mem_cat');
    return ($t && !is_wp_error($t)) ? $t : null;
}

/** Товары одной категории (без подкатегорий), в порядке из админки */
function memorial_plugin_cat_products($term_id) {
    return get_posts([
        'post_type' => 'mem_product', 'post_status' => 'publish', 'posts_per_page' => -1,
        'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'],
        'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $term_id, 'include_children' => false]],
    ]);
}

function memorial_plugin_word($n, $one, $few, $many) {
    $n = abs($n) % 100; $d = $n % 10;
    if ($n > 10 && $n < 20) return $many;
    if ($d === 1) return $one;
    if ($d >= 2 && $d <= 4) return $few;
    return $many;
}

/** Форма фото заметно отличается от формы карточки (> 12 %) */
function memorial_plugin_card_ratio_differs($thumb, $card = 0) {
    $card = $card ?: ($GLOBALS['memorial_plugin_card_ratio'] ?? 4 / 3);
    $r = function_exists('memorial_plugin_img_ratio') ? memorial_plugin_img_ratio($thumb) : 0;
    return $r && abs($r / $card - 1) > .12;
}

/** Стрелка «‹ ›» картинкой — строго по центру кнопки (символы шрифта смещены) */
function memorial_plugin_chevron($dir) {
    return '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="' . ($dir === 'l' ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7')
         . '" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/** Все фото товара: [главное, дополнительные…] (номера в медиатеке) */
function memorial_plugin_product_photos($id) {
    $out = [];
    if ($t = (int) get_post_thumbnail_id($id)) $out[] = $t;
    foreach (array_map('absint', explode(',', (string) get_post_meta($id, '_mem_gallery', true))) as $g) {
        if ($g && !in_array($g, $out, true) && wp_attachment_is_image($g)) $out[] = $g;
    }
    return $out;
}

/** Карточка товара каталога */
function memorial_plugin_catalog_card($p, $i) {
    $id    = $p->ID;
    $price = (int) get_post_meta($id, '_mem_price', true);
    $sub   = get_post_meta($id, '_mem_sub', true);
    $thumb = get_post_thumbnail_id($id);
    $photos = memorial_plugin_product_photos($id);
    $big   = $thumb ? wp_get_attachment_image_url($thumb, 'large') : '';
    $page  = memorial_plugin_product_has_page($id) ? get_permalink($id) : '';
    $title = get_the_title($id);
    $cap   = $title . ($sub ? ' · ' . $sub : '') . implode('', array_map(fn($x) => ' · ' . ($x[0] !== '' ? $x[0] . ' ' : '') . $x[1], memorial_plugin_product_specs($id)))
           . ($price ? ' · ' . wp_strip_all_tags(memorial_plugin_price_html($id)) : '');

    echo '<li class="mcard" id="' . memorial_plugin_product_anchor($id) . '" data-price="' . $price . '" data-i="' . (int) $i . '">';
    if ($page) echo '<a class="mcard__link" href="' . esc_url($page) . '">';
    elseif ($big) {
        // несколько фото: в крупном просмотре листаются все фото товара, затем — следующий товар
        $set = count($photos) > 1 ? ' data-lb-set="' . esc_attr(wp_json_encode(array_map(fn($p) => wp_get_attachment_image_url($p, 'large'), $photos))) . '"' : '';
        echo '<a class="mcard__link" href="' . esc_url($big) . '" data-lb data-cap="' . esc_attr($cap) . '"' . $set . '>';
    }
    else echo '<div class="mcard__link">';
    // фото другой формы, чем карточка: поля по бокам — размытая копия этого же фото (а не пустой белый фон)
    $mbg = $thumb && memorial_plugin_card_ratio_differs($thumb) ? memorial_plugin_blur_bg_url($thumb) : '';
    echo '<span class="mcard__media" data-wp-interactive="memorial-plugin/card" data-wp-init="callbacks.init"><span class="mcard__img' . ($mbg ? ' has-bg" style="--mbg:url(&quot;' . esc_url($mbg) . '&quot;)' : '') . '">';
    $imgattr = fn($p, $k) => ['loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width:600px) 50vw, 260px',
                              'alt' => get_post_meta($p, '_wp_attachment_image_alt', true) ?: $title . ($k ? __( ', photo ', 'memorial-plugin' ) . ($k + 1) : ''),
                              'class' => 'mcard__ph' . ($k ? '' : ' is-on'), 'data-big' => wp_get_attachment_image_url($p, 'large'),
                              'data-bg' => $k && memorial_plugin_card_ratio_differs($p) ? memorial_plugin_blur_bg_url($p) : ''];
    if (!$thumb) echo '<span class="mcard__noimg" aria-hidden="true">' . (memorial_plugin_laurel('wreath') ?: '✝') . '</span>';
    // несколько фото: остальные лежат рядом скрытыми (грузятся, только когда до них долистают), стрелки и точки — assets/js/view/blocks.js (store memorial-plugin/card)
    foreach ($photos as $k => $p) echo wp_get_attachment_image($p, 'medium_large', false, $imgattr($p, $k));
    if (get_post_meta($id, '_mem_on_order', true)) echo ( '<span class="mcard__tag">' . esc_html__( 'Made to order', 'memorial-plugin' ) . '</span>' );
    echo '</span>';
    // стрелки — снаружи фото (центр круга на краю фото), поэтому почти не закрывают снимок
    if (count($photos) > 1) {
        echo ( '<span class="mcard__arr mcard__arr--l" role="button" tabindex="0" aria-label="' . esc_html__( 'Previous photo', 'memorial-plugin' ) . '" data-dir="-1" data-wp-on--click="actions.step" data-wp-on--keydown="actions.key">' ) . memorial_plugin_chevron('l') . '</span>'
           . ( '<span class="mcard__arr mcard__arr--r" role="button" tabindex="0" aria-label="' . esc_html__( 'Next photo', 'memorial-plugin' ) . '" data-dir="1" data-wp-on--click="actions.step" data-wp-on--keydown="actions.key">' ) . memorial_plugin_chevron('r') . '</span>';
    }
    echo '</span>';
    if (count($photos) > 1) {
        echo '<span class="mcard__dots" aria-hidden="true">';
        foreach ($photos as $k => $p) echo '<i' . ($k ? '' : ' class="is-on"') . '></i>';
        echo '</span>';
    }
    echo '<span class="mcard__body"><span class="mcard__name">' . esc_html($title) . '</span>';
    $specs = memorial_plugin_product_specs($id);
    // подпись повторяет характеристику («700 × 2050» и «Ширина × высота: 700 × 2050 мм») — не дублируем
    if ($sub && $specs) {
        $num = fn($t) => preg_replace('/\D+/', ' ', $t);
        $all = ' ' . $num(implode(' ', array_column($specs, 1))) . ' ';
        $sn = trim($num($sub));
        if ($sn !== '' && strpos($all, ' ' . $sn . ' ') !== false) $sub = '';
    }
    if ($sub) echo '<span class="mcard__sub">' . esc_html($sub) . '</span>';
    if ($specs) {
        echo '<span class="mcard__specs">';
        foreach ($specs as [$k, $v]) echo '<span>' . ($k !== '' ? '<i>' . esc_html($k) . '</i> ' : '') . '<b>' . esc_html($v) . '</b></span>';
        echo '</span>';
    }
    echo '<span class="mcard__price">' . wp_kses_post(memorial_plugin_price_html($id)) . '</span></span>';
    echo ($page || $big) ? '</a>' : '</div>';
    echo '</li>';
}

add_shortcode('mem_catalog', function ($atts) {
    $a = shortcode_atts(['cat' => '', 'nav' => '1', 'sort' => '1', 'heading' => 'h2', 'pokazat' => ''], $atts, 'mem_catalog');
    $root = memorial_plugin_resolve_cat($a['cat'], get_the_ID());
    if (!$root) {
        return current_user_can('edit_posts')
            ? ( '<p class="is-style-note">' . esc_html__( '[mem_catalog]: the category was not found. Set it like this: [mem_catalog cat="category-slug"].', 'memorial-plugin' ) . '</p>' ) : '';
    }

    // Разделы: товары самой категории (без заголовка) + подкатегории
    $sections = [];
    $own = memorial_plugin_cat_products($root->term_id);
    if ($own) $sections[] = [$root, $own, false];
    foreach (memorial_plugin_sorted_children($root->term_id) as $k) {
        $items = memorial_plugin_cat_products($k->term_id);
        foreach (memorial_plugin_sorted_children($k->term_id) as $kk) $items = array_merge($items, memorial_plugin_cat_products($kk->term_id));
        if ($items) $sections[] = [$k, $items, true];
    }
    if (!$sections) {
        return ( '<div class="mcat__empty"><p>' . esc_html__( 'The catalog will appear soon. Call — we will tell you about the models and prices.', 'memorial-plugin' ) . '</p></div>' );
    }

    $h = in_array($a['heading'], ['h2', 'h3'], true) ? $a['heading'] : 'h2';
    $titled = array_filter($sections, fn($s) => $s[2]);
    $total = array_sum(array_map(fn($s) => count($s[1]), $sections));

    ob_start();
    echo '<div class="mcat" data-mcat data-wp-interactive="memorial-plugin/catalog" data-wp-init="callbacks.init">';
    if ($a['nav'] !== '0' || ($a['sort'] !== '0' && $total > 1)) {
        echo '<span class="mcat__pin" aria-hidden="true"></span><div class="mcat__bar">';
        if ($a['nav'] !== '0' && count($titled) > 1) {
            echo ( '<div class="mcat__navwrap"><nav class="mcat__nav" aria-label="' . esc_attr__( 'Catalog sections', 'memorial-plugin' ) . '">' );
            foreach ($titled as [$t, $items]) {
                echo '<a class="chip" href="#' . esc_attr(memorial_plugin_term_anchor($t)) . '">' . esc_html($t->name) . ' <small>' . count($items) . '</small></a>';
            }
            echo '</nav></div>';
        }
        if ($a['sort'] !== '0' && $total > 1) {
            echo ( '<label class="mcat__sort"><span class="mcat__sortlbl">' . esc_html__( 'Sorting:', 'memorial-plugin' ) . '</span><select data-mcat-sort data-wp-on--change="actions.sort">' )
               . ( '<option value="">' . esc_html__( 'Default', 'memorial-plugin' ) . '</option><option value="asc">' . esc_html__( 'Cheapest first', 'memorial-plugin' ) . '</option><option value="desc">' . esc_html__( 'Most expensive first', 'memorial-plugin' ) . '</option>' )
               . '</select></label>';
        }
        echo '</div>';
    }

    foreach ($sections as [$t, $items, $with_head]) {
        $ratio = memorial_plugin_term_opt($t->term_id, 'mem_ratio', 'photo');
        $cols  = (int) memorial_plugin_term_opt($t->term_id, 'mem_cols', 4) ?: 4;
        echo '<section class="mcat__sec"' . ($with_head ? ' id="' . esc_attr(memorial_plugin_term_anchor($t)) . '"' : '') . '>';
        if ($with_head) {
            echo '<' . $h . ' class="mcat__title">' . esc_html($t->name) . '</' . $h . '>';
            $prices = array_filter(array_map(fn($p) => (int) get_post_meta($p->ID, '_mem_price', true), $items));
            $n = count($items);
            echo '<p class="mcat__meta">' . $n . ' ' . _n( 'model', 'models', $n, 'memorial-plugin' )
               . ($prices ? __( ', from ', 'memorial-plugin' ) . esc_html(memorial_plugin_rub(min($prices))) : '') . '</p>';
        }
        if (trim($t->description) !== '' && $with_head) {
            // в описании раздела можно писать [mem_cat_count], [mem_cat_price] — они считают этот раздел
            $GLOBALS['memorial_plugin_cat_ctx'] = $t->term_id;
            echo '<div class="mcat__desc">' . do_shortcode(wp_kses_post(wpautop($t->description))) . '</div>';
            unset($GLOBALS['memorial_plugin_cat_ctx']);
        }
        // много товаров — сначала 3 ряда, остальные по кнопке «Показать ещё» (pokazat="20" — своё число, pokazat="все" — все сразу)
        $more = memorial_plugin_sc_no($a['pokazat']) || in_array(mb_strtolower($a['pokazat']), ['все', 'all'], true) ? 0 : ((int) $a['pokazat'] ?: max(8, $cols * 3));
        $more = $more && count($items) > $more + 2 ? $more : 0;
        echo '<ul class="mcat__grid mcat--' . esc_attr($ratio) . '" style="--cols:' . $cols . '"' . ($more ? ' data-more="' . $more . '"' : '') . '>';
        $GLOBALS['memorial_plugin_card_ratio'] = ['photo' => 4 / 3, 'portrait' => 3 / 4, 'tall' => 3 / 5, 'land' => 5 / 3, 'square' => 1][$ratio] ?? 4 / 3;
        foreach ($items as $i => $p) memorial_plugin_catalog_card($p, $i);
        unset($GLOBALS['memorial_plugin_card_ratio']);
        echo '</ul></section>';
    }
    echo '</div>';
    return ob_get_clean();
});

/** Якорь раздела: сохранённый при переносе (чтобы старые ссылки работали) или адрес категории */
function memorial_plugin_term_anchor($t) {
    return get_term_meta($t->term_id, 'mem_anchor', true) ?: 'r-' . $t->slug;
}

/* Окно для крупного фото — одно на страницу */
add_action('wp_footer', function () {
    echo ( '<dialog class="lb" id="lb" aria-label="' . esc_html__( 'Photo', 'memorial-plugin' ) . '"><button class="lb__x" type="button" aria-label="' . esc_attr__( 'Close', 'memorial-plugin' ) . '">' )
       . '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button>'
       . ( '<button class="lb__nav lb__prev" type="button" aria-label="' . esc_html__( 'Previous', 'memorial-plugin' ) . '">' ) . memorial_plugin_chevron('l') . '</button>'
       . '<figure><div class="lb__stage"><img alt=""></div><figcaption></figcaption><p class="lb__count" aria-live="polite"></p></figure>'
       . ( '<button class="lb__nav lb__next" type="button" aria-label="' . esc_html__( 'Next', 'memorial-plugin' ) . '">' ) . memorial_plugin_chevron('r') . '</button></dialog>';
}, 20);

/* Страница с шорткодом = страница своей категории: ссылки на категорию ведут на неё */
add_action('save_post_page', function ($id, $post) {
    if (wp_is_post_revision($id) || !memorial_plugin_has_content_block($post->post_content, 'mem_catalog')) return;
    $cat = '';
    if (preg_match('/\[mem_catalog[^\]]*\bcat="([^"]+)"/', $post->post_content, $m)) $cat = $m[1];
    foreach (parse_blocks($post->post_content) as $b) {   // блок «Каталог» с выбранным разделом
        if ($cat === '' && ($b['blockName'] ?? '') === 'memorial-plugin/catalog' && !empty($b['attrs']['cat'])) $cat = (string) $b['attrs']['cat'];
    }
    if ($cat !== '' && ($t = memorial_plugin_resolve_cat($cat))) {
        update_post_meta($id, '_mem_cat', $t->term_id);
    }
}, 10, 2);

/* Товар без описания — отдельной страницы у него по сути нет: просим поисковики её не индексировать */
add_filter('wp_robots', function ($r) {
    if (is_singular('mem_product') && trim(wp_strip_all_tags(get_queried_object()->post_content)) === '') {
        $r['noindex'] = true;
        $r['follow'] = true;
    }
    return $r;
});
