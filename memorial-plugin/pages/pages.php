<?php
/**
 * Товары на обычных страницах сайта.
 *
 * В редакторе страницы (справа, блок «Товары на странице») выбирается категория каталога —
 * под текстом страницы появляется сетка карточек с ценами и сортировкой. Адрес и меню остаются прежними.
 * Вариант «Все категории» делает страницу главной страницей каталога: на ней плитки категорий.
 *
 * Ссылки на категории и на каталог по всему сайту ведут на эти страницы,
 * а старые адреса /catalog/... перенаправляются на них (301).
 */
defined('ABSPATH') || exit;

/* Выбор категории — в боковой панели редактора страницы (admin/editor-meta.php, поле _mem_products) */

/* Колонка в списке страниц: видно, где какие товары */
add_filter('manage_page_posts_columns', function ($c) {
    $c['mem_products'] = 'Товары';
    return $c;
});
add_action('manage_page_posts_custom_column', function ($col, $id) {
    if ($col !== 'mem_products') return;
    $v = get_post_meta($id, '_mem_products', true);
    if ($v === 'all') echo 'Все категории';
    elseif ($v && ($t = get_term((int) $v, 'mem_cat')) && !is_wp_error($t)) echo esc_html($t->name);
    else echo '—';
}, 10, 2);

/* ---------- Связи «категория ↔ страница» ---------- */

/** ['all' => id страницы-каталога, term_id => id страницы, …] */
function memorial_plugin_page_map() {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    $pages = get_posts([
        'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_key' => '_mem_products', 'orderby' => 'menu_order ID', 'order' => 'ASC',
    ]);
    foreach ($pages as $id) {
        $v = get_post_meta($id, '_mem_products', true);
        $map[$v] ??= $id; // если одну категорию поставили на две страницы — главной считается первая
    }
    // Страницы с каталогом в тексте ([mem_catalog])
    $pages = get_posts([
        'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_key' => '_mem_cat', 'orderby' => 'menu_order ID', 'order' => 'ASC',
    ]);
    foreach ($pages as $id) $map[(string) get_post_meta($id, '_mem_cat', true)] ??= $id;
    return $map;
}

function memorial_plugin_catalog_page() { return (int) (memorial_plugin_page_map()['all'] ?? 0); }
function memorial_plugin_term_page($term_id) { return (int) (memorial_plugin_page_map()[(string) $term_id] ?? 0); }

/** Ссылка на каталог: страница «Все категории», а если её нет — первая страница с товарами */
function memorial_plugin_catalog_url() {
    if ($id = memorial_plugin_catalog_page()) return get_permalink($id);
    $map = memorial_plugin_page_map();
    return $map ? get_permalink(reset($map)) : home_url('/');
}

add_filter('post_type_archive_link', function ($link, $type) {
    if ($type === 'mem_product' && memorial_plugin_page_map()) return memorial_plugin_catalog_url();
    return $link;
}, 10, 2);

add_filter('term_link', function ($link, $term, $tax) {
    if ($tax !== 'mem_cat') return $link;
    if ($id = memorial_plugin_term_page($term->term_id)) return get_permalink($id);
    // Подкатегория без своей страницы — раздел на странице родителя
    $t = $term; $guard = 0;
    while ($t->parent && $guard++ < 5) {
        $t = get_term($t->parent, 'mem_cat');
        if (!$t || is_wp_error($t)) break;
        if ($id = memorial_plugin_term_page($t->term_id)) return get_permalink($id) . '#' . memorial_plugin_term_anchor($term);
    }
    return $link;
}, 10, 3);

/* Старые адреса /catalog/... → на страницы */
add_action('template_redirect', function () {
    $to = '';
    if (is_post_type_archive('mem_product') && memorial_plugin_page_map()) {
        $to = memorial_plugin_catalog_url();
    } elseif (is_tax('mem_cat') && ($id = memorial_plugin_term_page(get_queried_object_id()))) {
        $to = get_permalink($id);
    } elseif (is_tax('mem_cat') && !is_wp_error($l = get_term_link(get_queried_object())) && strpos($l, '#') !== false) {
        $to = $l; // подкатегория — раздел на странице родителя
    }
    // /groby/page/9/ при 2 страницах товаров — на первую страницу
    $paged = (int) get_query_var('paged');
    if (!$to && $paged > 1 && is_page() && ($mode = get_post_meta(get_queried_object_id(), '_mem_products', true))) {
        $args = ['post_type' => 'mem_product', 'post_status' => 'publish', 'posts_per_page' => 24, 'paged' => $paged, 'fields' => 'ids'];
        if ($mode !== 'all') $args['tax_query'] = [['taxonomy' => 'mem_cat', 'terms' => (int) $mode]];
        $q = new WP_Query($args);
        if ($mode === 'all' || $paged > $q->max_num_pages) $to = get_permalink(get_queried_object_id());
    }
    if ($to) {
        if (!empty($_GET['sort'])) $to = add_query_arg('sort', sanitize_key($_GET['sort']), $to);
        wp_safe_redirect($to, 301);
        exit;
    }
});

/* /venki/page/2/ — WordPress по умолчанию считает это несуществующей страницей у обычных страниц */
add_filter('redirect_canonical', function ($url) {
    if (is_page() && get_query_var('paged') > 1 && get_post_meta(get_queried_object_id(), '_mem_products', true)) return false;
    return $url;
});

/* ---------- Вывод ---------- */

/** Плитки категорий (главная и страница «Все категории») */
/* ---------- Как фото страницы показывается в плитке каталога ---------- */
add_action('init', function () {
    $auth = fn() => current_user_can('edit_pages');
    register_post_meta('page', '_mem_tile_pos', ['type' => 'string', 'single' => true, 'default' => '', 'show_in_rest' => true, 'auth_callback' => $auth]);
    register_post_meta('page', '_mem_tile_fit', ['type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => true, 'auth_callback' => $auth]);
    register_post_meta('page', '_mem_hide_thumb', ['type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => true, 'auth_callback' => $auth]);
    register_post_meta('page', '_mem_tile_bg', ['type' => 'string', 'single' => true, 'default' => '', 'show_in_rest' => true, 'auth_callback' => $auth]);
});

/** Доп. стиль плитки: точка фокуса (--pos) и «целиком» (--fit) */
function memorial_plugin_tile_style($page_id) {
    $st  = '';
    $pos = (string) get_post_meta($page_id, '_mem_tile_pos', true);
    // точка фокуса: скрипт сайта ставит её в центр плитки (data-focus), --pos — запасной вариант без скрипта
    if (preg_match('/^(\d{1,3}(?:\.\d+)?)% (\d{1,3}(?:\.\d+)?)%$/', $pos, $m)) $st .= ';--pos:' . round((float) $m[1]) . '% ' . round((float) $m[2]) . '%';
    if (get_post_meta($page_id, '_mem_tile_fit', true)) $st .= ';--fit:contain';
    if ($bg = sanitize_hex_color((string) get_post_meta($page_id, '_mem_tile_bg', true))) $st .= ';--tbg:' . $bg;
    if ($st !== '' && strpos($st, '--pos') !== false && strpos($st, '--fit') === false) $st .= '" data-wp-interactive="memorial-plugin/focus" data-wp-init="callbacks.init" data-focus="' . round((float) $m[1]) . ' ' . round((float) $m[2]);
    return $st;
}

add_action('enqueue_block_editor_assets', function () {
    $s = get_current_screen();
    if (!$s || $s->post_type !== 'page') return;
    $f = '/assets/js/tile-panel.js';
    wp_enqueue_script('mem-tile-panel', MEMORIAL_PLUGIN_URL . $f,
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-hooks'], filemtime(MEMORIAL_PLUGIN_DIR . $f), true);
});

/** Плитка раздела: фото сверху (точка фокуса, «целиком», цвет фона — из панели «Плитка и карточка раздела»), под ним название и «Смотреть →» */
function memorial_plugin_cat_tile_html($url, $name, $img, $st) {
    return '<a class="cat reveal" href="' . esc_url($url) . '"' . ($img ? ' style="--img:url(' . esc_url($img) . ')' . $st . '"' : '') . '>'
         . '<span class="cat__ph" aria-hidden="true"></span>'
         . '<span class="cat__cap"><h3>' . esc_html($name) . '</h3><span class="cat__more">Смотреть →</span></span></a>';
}

function memorial_plugin_cat_tiles($cats) {
    echo '<div class="cats">';
    foreach ($cats as $c) {
        $page = memorial_plugin_term_page($c->term_id);
        // Фото плитки: «Изображение записи» страницы категории, иначе — фото первого товара
        $img  = $page ? get_the_post_thumbnail_url($page, 'medium_large') : '';
        $st   = $img ? memorial_plugin_tile_style($page) : '';
        if (!$img) {
            $p = get_posts([
                'post_type' => 'mem_product', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_thumbnail_id',
                'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $c->term_id]],
            ]);
            $img = $p ? get_the_post_thumbnail_url($p[0], 'medium_large') : '';
        }
        $name = $page ? get_the_title($page) : $c->name;
        echo memorial_plugin_cat_tile_html(get_term_link($c), $name, $img, $st ?? '');
    }
    echo '</div>';
}

/** Сетка товаров с сортировкой и страницами. $term_id = 0 — все товары. */
function memorial_plugin_products_grid($term_id, $base) {
    $paged = max(1, (int) get_query_var('paged'));
    $args = [
        'post_type' => 'mem_product', 'post_status' => 'publish', 'posts_per_page' => 24, 'paged' => $paged,
    ];
    if ($term_id) $args['tax_query'] = [['taxonomy' => 'mem_cat', 'terms' => $term_id, 'include_children' => true]];
    memorial_plugin_apply_sort($args);
    $q = new WP_Query($args);
    $sort = sanitize_key($_GET['sort'] ?? '');

    if ($term_id) {
        $subs = get_terms(['taxonomy' => 'mem_cat', 'parent' => $term_id, 'hide_empty' => true]);
        if (!is_wp_error($subs) && $subs) {
            echo '<div class="chips">';
            foreach ($subs as $s) {
                $pid = memorial_plugin_term_page($s->term_id);
                echo '<a class="chip" href="' . esc_url(get_term_link($s)) . '">' . esc_html($pid ? get_the_title($pid) : $s->name) . '</a>';
            }
            echo '</div>';
        }
    }

    if (!$q->have_posts()) {
        echo '<div class="empty"><p>Товары скоро появятся. Позвоните — подберём нужное и расскажем о ценах.</p>';
        if (memorial_plugin_tel()) echo '<a class="btn btn--gold" href="tel:' . esc_attr(memorial_plugin_tel()) . '">' . memorial_plugin_icon('phone') . '<span class="nowrap">' . esc_html(memorial_plugin_opt('phone')) . '</span></a>';
        echo '</div>';
        return;
    }
    ?>
    <div class="toolbar" id="tovary">
      <span class="toolbar__count">Товаров: <?php echo (int) $q->found_posts; ?></span>
      <?php if ($q->found_posts > 1) : ?>
      <form class="sort" method="get" action="<?php echo esc_url($base); ?>" data-sort>
        <label>
          <span class="screen-reader-text">Сортировка</span>
          <select name="sort">
            <?php foreach (memorial_plugin_sorts() as $v => $l) : ?>
              <option value="<?php echo esc_attr($v); ?>" <?php selected($sort, $v); ?>><?php echo esc_html($l); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <noscript><button class="btn btn--line btn--sm" type="submit">OK</button></noscript>
      </form>
      <?php endif; ?>
    </div>
    <div class="products">
      <?php while ($q->have_posts()) : $q->the_post(); memorial_plugin_part('product-card'); endwhile; ?>
    </div>
    <?php
    if ($q->max_num_pages > 1) {
        echo '<nav class="pagination" aria-label="Страницы"><div class="nav-links">'
           . paginate_links(['total' => $q->max_num_pages, 'current' => $paged, 'mid_size' => 1, 'prev_text' => '←', 'next_text' => '→', 'add_fragment' => '#tovary'])
           . '</div></nav>';
    }
    wp_reset_postdata();
}

/* ---------- Структура главного меню ---------- */

/** Пункты главного меню: [id пункта => пункт] */
function memorial_plugin_menu_items() {
    static $items = null;
    if ($items !== null) return $items;
    $items = [];
    $loc = get_nav_menu_locations();
    if (empty($loc['primary'])) return $items;
    foreach ((array) wp_get_nav_menu_items($loc['primary']) as $it) $items[$it->ID] = $it;
    return $items;
}

/** Пункт меню, ведущий на страницу */
function memorial_plugin_menu_item_for($page_id) {
    foreach (memorial_plugin_menu_items() as $it) {
        if ($it->type === 'post_type' && (int) $it->object_id === (int) $page_id) return $it;
    }
    return null;
}

/** Цепочка родительских пунктов меню над страницей: [[название, ссылка], …] (без самой страницы) */
function memorial_plugin_menu_chain($page_id) {
    $it = memorial_plugin_menu_item_for($page_id);
    if (!$it) return null;
    $items = memorial_plugin_menu_items();
    $out = [];
    $guard = 0;
    while ($it->menu_item_parent && isset($items[$it->menu_item_parent]) && $guard++ < 10) {
        $it = $items[$it->menu_item_parent];
        $url = $it->url && $it->url !== '#' ? $it->url : '';
        $out[] = [$it->title, $url];
    }
    return array_reverse($out);
}

/** Подпункты меню под страницей, которые ведут на страницы */
function memorial_plugin_menu_children($page_id) {
    $it = memorial_plugin_menu_item_for($page_id);
    if (!$it) return [];
    return array_values(array_filter(memorial_plugin_menu_items(), fn($c) => (int) $c->menu_item_parent === (int) $it->ID && $c->type === 'post_type'));
}

/** Плитки из подпунктов меню (страница «Все категории») */
function memorial_plugin_page_tiles($children) {
    echo '<div class="cats">';
    foreach ($children as $c) {
        $pid = (int) $c->object_id;
        $img = get_the_post_thumbnail_url($pid, 'medium_large');
        $st  = $img ? memorial_plugin_tile_style($pid) : '';
        $mode = get_post_meta($pid, '_mem_products', true);
        if (!$img && $mode && $mode !== 'all') {
            $p = get_posts(['post_type' => 'mem_product', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_thumbnail_id',
                            'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => (int) $mode]]]);
            $img = $p ? get_the_post_thumbnail_url($p[0], 'medium_large') : '';
        }
        echo memorial_plugin_cat_tile_html($c->url, $c->title, $img, $st);
    }
    echo '</div>';
}
