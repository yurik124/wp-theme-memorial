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
function mem_ratios() {
    return [
        'photo'    => 'Фото 4:3 (гробы, примеры работ)',
        'portrait' => 'Вертикальные 3:4 (венки, памятники)',
        'tall'     => 'Узкие высокие 3:5 (кресты), целиком',
        'land'     => 'Широкие 5:3 (оградки, чертежи), целиком',
        'square'   => 'Квадрат',
    ];
}

/** Настройка категории с наследованием от родителя */
function mem_term_opt($term_id, $key, $def = '') {
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
function mem_sorted_children($term_id) {
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
function mem_resolve_cat($val, $page_id = 0) {
    $val = trim((string) $val);
    if ($val === '' && $page_id) $val = (string) get_post_meta($page_id, '_mem_cat', true);
    if ($val === '') return null;
    $t = ctype_digit($val) ? get_term((int) $val, 'mem_cat') : get_term_by('slug', sanitize_title($val), 'mem_cat');
    return ($t && !is_wp_error($t)) ? $t : null;
}

/** Товары одной категории (без подкатегорий), в порядке из админки */
function mem_cat_products($term_id) {
    return get_posts([
        'post_type' => 'mem_product', 'post_status' => 'publish', 'posts_per_page' => -1,
        'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'],
        'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $term_id, 'include_children' => false]],
    ]);
}

function mem_word($n, $one, $few, $many) {
    $n = abs($n) % 100; $d = $n % 10;
    if ($n > 10 && $n < 20) return $many;
    if ($d === 1) return $one;
    if ($d >= 2 && $d <= 4) return $few;
    return $many;
}

/** Форма фото заметно отличается от формы карточки (> 12 %) */
function mem_card_ratio_differs($thumb, $card = 0) {
    $card = $card ?: ($GLOBALS['mem_card_ratio'] ?? 4 / 3);
    $r = function_exists('mem_img_ratio') ? mem_img_ratio($thumb) : 0;
    return $r && abs($r / $card - 1) > .12;
}

/** Карточка товара каталога */
function mem_catalog_card($p, $i) {
    $id    = $p->ID;
    $price = (int) get_post_meta($id, '_mem_price', true);
    $sub   = get_post_meta($id, '_mem_sub', true);
    $thumb = get_post_thumbnail_id($id);
    $big   = $thumb ? wp_get_attachment_image_url($thumb, 'large') : '';
    $page  = mem_product_has_page($id) ? get_permalink($id) : '';
    $title = get_the_title($id);
    $cap   = $title . ($sub ? ' · ' . $sub : '') . implode('', array_map(fn($x) => ' · ' . ($x[0] !== '' ? $x[0] . ' ' : '') . $x[1], mem_product_specs($id)))
           . ($price ? ' · ' . wp_strip_all_tags(mem_price_html($id)) : '');

    echo '<li class="mcard" id="' . mem_product_anchor($id) . '" data-price="' . $price . '" data-i="' . (int) $i . '">';
    if ($page) echo '<a class="mcard__link" href="' . esc_url($page) . '">';
    elseif ($big) echo '<a class="mcard__link" href="' . esc_url($big) . '" data-lb data-cap="' . esc_attr($cap) . '">';
    else echo '<div class="mcard__link">';
    // фото другой формы, чем карточка: поля по бокам — размытая копия этого же фото (а не пустой белый фон)
    $mbg = $thumb && mem_card_ratio_differs($thumb) ? wp_get_attachment_image_url($thumb, 'thumbnail') : '';
    echo '<span class="mcard__img' . ($mbg ? ' has-bg" style="--mbg:url(&quot;' . esc_url($mbg) . '&quot;)' : '') . '">';
    echo $thumb ? wp_get_attachment_image($thumb, 'medium_large', false, ['loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width:600px) 50vw, 260px', 'alt' => get_post_meta($thumb, '_wp_attachment_image_alt', true) ?: $title])
                : '<span class="mcard__noimg" aria-hidden="true">✝</span>';
    if (get_post_meta($id, '_mem_on_order', true)) echo '<span class="mcard__tag">Под заказ</span>';
    echo '</span>';
    echo '<span class="mcard__body"><span class="mcard__name">' . esc_html($title) . '</span>';
    $specs = mem_product_specs($id);
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
    echo '<span class="mcard__price">' . wp_kses_post(mem_price_html($id)) . '</span></span>';
    echo ($page || $big) ? '</a>' : '</div>';
    echo '</li>';
}

add_shortcode('mem_catalog', function ($atts) {
    $a = shortcode_atts(['cat' => '', 'nav' => '1', 'sort' => '1', 'heading' => 'h2'], $atts, 'mem_catalog');
    $root = mem_resolve_cat($a['cat'], get_the_ID());
    if (!$root) {
        return current_user_can('edit_posts')
            ? '<p class="is-style-note">[mem_catalog]: категория не найдена. Укажите её так: [mem_catalog cat="адрес-категории"].</p>' : '';
    }

    // Разделы: товары самой категории (без заголовка) + подкатегории
    $sections = [];
    $own = mem_cat_products($root->term_id);
    if ($own) $sections[] = [$root, $own, false];
    foreach (mem_sorted_children($root->term_id) as $k) {
        $items = mem_cat_products($k->term_id);
        foreach (mem_sorted_children($k->term_id) as $kk) $items = array_merge($items, mem_cat_products($kk->term_id));
        if ($items) $sections[] = [$k, $items, true];
    }
    if (!$sections) {
        return '<div class="mcat__empty"><p>Каталог скоро появится. Позвоните — расскажем о моделях и ценах.</p></div>';
    }

    $h = in_array($a['heading'], ['h2', 'h3'], true) ? $a['heading'] : 'h2';
    $titled = array_filter($sections, fn($s) => $s[2]);
    $total = array_sum(array_map(fn($s) => count($s[1]), $sections));

    ob_start();
    echo '<div class="mcat" data-mcat>';
    if ($a['nav'] !== '0' || ($a['sort'] !== '0' && $total > 1)) {
        echo '<span class="mcat__pin" aria-hidden="true"></span><div class="mcat__bar">';
        if ($a['nav'] !== '0' && count($titled) > 1) {
            echo '<div class="mcat__navwrap"><nav class="mcat__nav" aria-label="Разделы каталога">';
            foreach ($titled as [$t, $items]) {
                echo '<a class="chip" href="#' . esc_attr(mem_term_anchor($t)) . '">' . esc_html($t->name) . ' <small>' . count($items) . '</small></a>';
            }
            echo '</nav></div>';
        }
        if ($a['sort'] !== '0' && $total > 1) {
            echo '<label class="mcat__sort"><span class="mcat__sortlbl">Сортировка:</span><select data-mcat-sort>'
               . '<option value="">По умолчанию</option><option value="asc">Сначала дешевле</option><option value="desc">Сначала дороже</option>'
               . '</select></label>';
        }
        echo '</div>';
    }

    foreach ($sections as [$t, $items, $with_head]) {
        $ratio = mem_term_opt($t->term_id, 'mem_ratio', 'photo');
        $cols  = (int) mem_term_opt($t->term_id, 'mem_cols', 4) ?: 4;
        echo '<section class="mcat__sec"' . ($with_head ? ' id="' . esc_attr(mem_term_anchor($t)) . '"' : '') . '>';
        if ($with_head) {
            echo '<' . $h . ' class="mcat__title">' . esc_html($t->name) . '</' . $h . '>';
            $prices = array_filter(array_map(fn($p) => (int) get_post_meta($p->ID, '_mem_price', true), $items));
            $n = count($items);
            echo '<p class="mcat__meta">' . $n . ' ' . mem_word($n, 'модель', 'модели', 'моделей')
               . ($prices ? ', от ' . esc_html(mem_rub(min($prices))) : '') . '</p>';
        }
        if (trim($t->description) !== '' && $with_head) {
            // в описании раздела можно писать [mem_cat_count], [mem_cat_price] — они считают этот раздел
            $GLOBALS['mem_cat_ctx'] = $t->term_id;
            echo '<div class="mcat__desc">' . do_shortcode(wp_kses_post(wpautop($t->description))) . '</div>';
            unset($GLOBALS['mem_cat_ctx']);
        }
        echo '<ul class="mcat__grid mcat--' . esc_attr($ratio) . '" style="--cols:' . $cols . '">';
        $GLOBALS['mem_card_ratio'] = ['photo' => 4 / 3, 'portrait' => 3 / 4, 'tall' => 3 / 5, 'land' => 5 / 3, 'square' => 1][$ratio] ?? 4 / 3;
        foreach ($items as $i => $p) mem_catalog_card($p, $i);
        unset($GLOBALS['mem_card_ratio']);
        echo '</ul></section>';
    }
    echo '</div>';
    return ob_get_clean();
});

/** Якорь раздела: сохранённый при переносе (чтобы старые ссылки работали) или адрес категории */
function mem_term_anchor($t) {
    return get_term_meta($t->term_id, 'mem_anchor', true) ?: 'r-' . $t->slug;
}

/* Окно для крупного фото — одно на страницу */
add_action('wp_footer', function () {
    echo '<dialog class="lb" id="lb" aria-label="Фото"><button class="lb__x" type="button" aria-label="Закрыть">×</button>'
       . '<button class="lb__nav lb__prev" type="button" aria-label="Предыдущее">‹</button>'
       . '<figure><img alt=""><figcaption></figcaption></figure>'
       . '<button class="lb__nav lb__next" type="button" aria-label="Следующее">›</button></dialog>';
}, 20);

/* Страница с шорткодом = страница своей категории: ссылки на категорию ведут на неё */
add_action('save_post_page', function ($id, $post) {
    if (wp_is_post_revision($id) || !has_shortcode($post->post_content, 'mem_catalog')) return;
    if (preg_match('/\[mem_catalog[^\]]*\bcat="([^"]+)"/', $post->post_content, $m) && ($t = mem_resolve_cat($m[1]))) {
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
