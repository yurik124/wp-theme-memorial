<?php
/**
 * «Каталог → Типы и категории» — в том же стиле, что «Товары»: типы на вкладках, у типа —
 * название, характеристики со значениями по умолчанию, цена по умолчанию, категории (переименовать,
 * перетащить, удалить, добавить), вид каталога на сайте. Всё сохраняется само.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=mem_product', 'Типы и категории', 'Типы и категории', 'manage_categories', 'mem-types', 'memorial_plugin_types_page', 1);
    remove_submenu_page('edit.php?post_type=mem_product', 'edit-tags.php?taxonomy=mem_cat&amp;post_type=mem_product');
    remove_submenu_page('edit.php?post_type=mem_product', 'edit-tags.php?taxonomy=mem_cat&post_type=mem_product');
}, 31);

/* Стандартный список категорий → новый экран (кроме прямых ссылок на редактирование) */
add_action('load-edit-tags.php', function () {
    if (($_GET['taxonomy'] ?? '') !== 'mem_cat' || isset($_GET['classic']) || isset($_GET['action']) || !empty($_POST)) return;
    wp_safe_redirect(admin_url('edit.php?post_type=mem_product&page=mem-types'));
    exit;
});

/** Подкатегории типа с вложенностью и числом товаров */
function memorial_plugin_types_cats($parent, $depth = 0) {
    $out = [];
    foreach (memorial_plugin_sorted_children($parent) as $c) {
        $out[] = ['id' => $c->term_id, 'name' => $c->name, 'depth' => $depth, 'parent' => (int) $c->parent, 'count' => memorial_plugin_cat_count($c->term_id), 'desc' => $c->description];
        $out = array_merge($out, memorial_plugin_types_cats($c->term_id, $depth + 1));
    }
    return $out;
}

function memorial_plugin_types_data($type_id) {
    $t = get_term($type_id, 'mem_cat');
    if (!$t || is_wp_error($t)) return null;
    $page = memorial_plugin_type_page_any($type_id) ?: (function_exists('memorial_plugin_term_page') ? memorial_plugin_term_page($type_id) : 0);
    return [
        'id'       => $type_id,
        'name'     => $t->name,
        'desc'     => $t->description,
        'count'    => memorial_plugin_cat_count($type_id),
        'fields'   => memorial_plugin_type_fields($type_id),
        'defaults' => memorial_plugin_type_defaults($type_id),
        'cats'     => memorial_plugin_types_cats($type_id),
        'ratio'    => (string) get_term_meta($type_id, 'mem_ratio', true),
        'cols'     => (string) get_term_meta($type_id, 'mem_cols', true),
        'ratios'   => memorial_plugin_ratios(),
        'page'     => $page ? ['title' => get_the_title($page), 'url' => get_permalink($page), 'edit' => get_edit_post_link($page, 'raw'),
                               'draft' => get_post_status($page) !== 'publish'] : null,
        'products' => admin_url('edit.php?post_type=mem_product&page=mem-products&type=' . $type_id),
        'advanced' => get_edit_term_link($type_id, 'mem_cat', 'mem_product'),
    ];
}

function memorial_plugin_types_page() {
    $types = memorial_plugin_manager_types();
    $cur = absint($_GET['type'] ?? 0);
    if (!$cur || !in_array($cur, wp_list_pluck($types, 'term_id'), true)) $cur = $types ? $types[0]->term_id : 0; ?>
    <div class="wrap mcm mct" id="mct">
      <div class="mcm__head">
        <h1>Типы и категории</h1>
        <a class="mcm__classic" href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=mem_cat&post_type=mem_product&classic=1')); ?>">Классический список</a>
      </div>
      <nav class="mcm__tabs mct__tabs" aria-label="Типы товаров">
        <?php foreach ($types as $t) : ?>
          <a class="mcm__tab<?php echo $t->term_id === $cur ? ' is-on' : ''; ?>" data-id="<?php echo (int) $t->term_id; ?>" href="<?php echo esc_url(add_query_arg('type', $t->term_id)); ?>"><span class="mct__grip" title="Потяните, чтобы поменять порядок" aria-hidden="true">⇆</span><span class="mct__tname"><?php echo esc_html($t->name); ?></span> <span><?php echo memorial_plugin_cat_count($t->term_id); ?></span></a>
        <?php endforeach; ?>
        <button type="button" class="mcm__tab mcm__tab--add" data-act="new-type">+ Новый тип</button>
      </nav>
      <?php if (!$cur) : ?><p class="mcm__empty">Пока нет ни одного типа товаров. Нажмите «+ Новый тип», например «Венки».</p><?php else : ?>
      <div class="mct__app"><p class="mcm__loading">Загрузка…</p></div>
      <?php endif; ?>
      <p class="mct__foot">Перетаскивайте вкладки типов, чтобы поменять их порядок. Перенести каталог со старых страниц сайта — <a href="<?php echo esc_url(admin_url('admin.php?page=mem-import')); ?>">«Перенос со страниц»</a>.</p>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'mem_product_page_mem-types') return;
    $dir = MEMORIAL_PLUGIN_DIR; $uri = MEMORIAL_PLUGIN_URL;
    wp_enqueue_style('mem-cm', $uri . '/assets/css/catalog-manager.css', [], filemtime($dir . '/assets/css/catalog-manager.css'));
    wp_enqueue_script('mem-hscroll', $uri . '/assets/js/admin-scroll.js', [], filemtime($dir . '/assets/js/admin-scroll.js'), true);
    wp_enqueue_script('mem-ct', $uri . '/assets/js/catalog-types.js', ['jquery', 'jquery-ui-sortable'], filemtime($dir . '/assets/js/catalog-types.js'), true);
    $type = absint($_GET['type'] ?? 0) ?: (memorial_plugin_manager_types()[0]->term_id ?? 0);
    wp_localize_script('mem-ct', 'memCT', ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('mem_cm'), 'data' => $type ? memorial_plugin_types_data($type) : null]);
});

function memorial_plugin_ct_type() {
    memorial_plugin_cm_check('manage_categories');
    $type = absint($_POST['type'] ?? 0);
    if (!$type || !term_exists($type, 'mem_cat') || memorial_plugin_type_root($type) !== $type) wp_send_json_error('Нет типа');
    return $type;
}

/* Название, вид на сайте, значения по умолчанию */
add_action('wp_ajax_mem_ct_type', function () {
    $type = memorial_plugin_ct_type();
    $f = (string) ($_POST['f'] ?? ''); $v = wp_unslash($_POST['v'] ?? '');
    if ($f === 'name') {
        $n = trim(sanitize_text_field($v));
        if ($n === '') wp_send_json_error('Пустое название');
        $r = wp_update_term($type, 'mem_cat', ['name' => $n]);
        if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
    } elseif ($f === 'ratio') {
        array_key_exists($v, memorial_plugin_ratios()) ? update_term_meta($type, 'mem_ratio', $v) : delete_term_meta($type, 'mem_ratio');
    } elseif ($f === 'cols') {
        ($c = absint($v)) ? update_term_meta($type, 'mem_cols', min(6, max(2, $c))) : delete_term_meta($type, 'mem_cols');
    } elseif ($f === 'defaults') {
        $d = (array) json_decode((string) $v, true);
        update_term_meta($type, 'mem_defaults', ['price' => absint(preg_replace('/\D/', '', (string) ($d['price'] ?? ''))), 'from' => !empty($d['from']) ? 1 : 0,
                                                 'unit' => trim(sanitize_text_field($d['unit'] ?? ''))]);
    } else {
        wp_send_json_error('Поле?');
    }
    wp_send_json_success();
});

/* Удалить тип — только пустой */
add_action('wp_ajax_mem_ct_type_delete', function () {
    $type = memorial_plugin_ct_type();
    if (memorial_plugin_cat_count($type)) wp_send_json_error('В типе есть товары — сначала перенесите или удалите их.');
    foreach (array_reverse(memorial_plugin_types_cats($type)) as $c) wp_delete_term($c['id'], 'mem_cat');
    wp_delete_term($type, 'mem_cat');
    wp_send_json_success(['url' => admin_url('edit.php?post_type=mem_product&page=mem-types')]);
});

/* Порядок типов (вкладок) и категорий */
add_action('wp_ajax_mem_ct_order', function () {
    memorial_plugin_cm_check('manage_categories');
    foreach (array_map('absint', (array) ($_POST['ids'] ?? [])) as $i => $id) {
        if ($id && term_exists($id, 'mem_cat')) update_term_meta($id, 'mem_order', ($i + 1) * 10);
    }
    wp_send_json_success();
});

/* Переименовать категорию или поменять её описание (описание есть и у типа) */
add_action('wp_ajax_mem_ct_cat', function () {
    memorial_plugin_cm_check('manage_categories');
    $id = absint($_POST['id'] ?? 0);
    if (!$id || !term_exists($id, 'mem_cat')) wp_send_json_error('Нет категории');
    if (isset($_POST['desc'])) {
        $r = wp_update_term($id, 'mem_cat', ['description' => wp_kses_post(trim(wp_unslash($_POST['desc'])))]);
    } else {
        $n = trim(sanitize_text_field(wp_unslash($_POST['name'] ?? '')));
        if ($n === '' || memorial_plugin_type_root($id) === $id) wp_send_json_error('Нет категории');
        $r = wp_update_term($id, 'mem_cat', ['name' => $n]);
    }
    is_wp_error($r) ? wp_send_json_error($r->get_error_message()) : wp_send_json_success();
});

/* Удалить категорию: её товары остаются в типе (или в других своих категориях) */
add_action('wp_ajax_mem_ct_cat_delete', function () {
    memorial_plugin_cm_check('manage_categories');
    $id = absint($_POST['id'] ?? 0);
    $type = $id ? memorial_plugin_type_root($id) : 0;
    if (!$id || $type === $id) wp_send_json_error('Нет категории');
    $ids = get_posts(['post_type' => 'mem_product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
                      'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $id, 'include_children' => false]]]);
    wp_delete_term($id, 'mem_cat');
    foreach ($ids as $pid) if (!wp_get_object_terms($pid, 'mem_cat', ['fields' => 'ids'])) wp_set_object_terms($pid, [$type], 'mem_cat');
    wp_send_json_success(memorial_plugin_types_data($type));
});

/* =========================================================
 * Страница для нового типа товара: черновик по тому же образцу, что у остальных каталогов
 * ======================================================= */

/** Страница, привязанная к типу (в том числе черновик) */
function memorial_plugin_type_page_any($type_id) {
    $p = get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft', 'pending', 'private'], 'posts_per_page' => 1,
                    'meta_key' => '_mem_cat', 'meta_value' => (int) $type_id, 'fields' => 'ids']);
    return $p ? (int) $p[0] : 0;
}

/** Содержимое страницы: вступление, призыв позвонить, каталог, как заказать, вопросы, призыв */
function memorial_plugin_type_page_content($type_id) {
    $t = get_term($type_id, 'mem_cat');
    $name = $t ? $t->name : 'Товары';
    $low = mb_strtolower($name);
    $p = fn($text, $cls = '') => '<!-- wp:paragraph' . ($cls ? ' {"className":"' . $cls . '"}' : '') . ' -->' . "\n"
        . '<p' . ($cls ? ' class="' . $cls . '"' : '') . '>' . $text . '</p>' . "\n<!-- /wp:paragraph -->";
    $h = fn($text) => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . $text . "</h2>\n<!-- /wp:heading -->";
    $sc = fn($code) => "<!-- wp:shortcode -->\n" . $code . "\n<!-- /wp:shortcode -->";
    $list = fn($items, $cls, $ol = false) => '<!-- wp:list {' . ($ol ? '"ordered":true,' : '') . '"className":"' . $cls . '"} -->' . "\n"
        . '<' . ($ol ? 'ol' : 'ul') . ' class="wp-block-list ' . $cls . '">'
        . implode('', array_map(fn($i) => "<!-- wp:list-item -->\n<li>" . $i . "</li>\n<!-- /wp:list-item -->", $items))
        . '</' . ($ol ? 'ol' : 'ul') . ">\n<!-- /wp:list -->";
    $q = fn($question, $answer) => "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . $question . '</summary>' . $p($answer) . "</details>\n<!-- /wp:details -->";
    return implode("\n\n", [
        $p('[Вступление: 1–2 предложения — что предлагаем, от какой цены, чем отличаемся. Например: «' . esc_html($name) . ' в Белгороде — в наличии и под заказ, от … ₽. Поможем выбрать и доставим».]', 'is-style-lead'),
        $sc('[mem_cta title="Поможем выбрать" text="Позвоните — подскажем по моделям и ценам, примем заказ."]'),
        $h('Каталог: ' . esc_html($low)),
        $sc('[mem_catalog cat="' . (int) $type_id . '"]'),
        $h('Как заказать'),
        $list(['<strong>Выберите</strong> модель в каталоге — или расскажите по телефону, что нужно.', '<strong>Позвоните.</strong> Уточним наличие, сроки и стоимость.', '<strong>Получите заказ</strong> — [в офисе / с доставкой, впишите как у вас].'], 'is-style-steps', true),
        $h('Частые вопросы'),
        $q('Сколько стоят ' . esc_html($low) . '?', '[Ответ: от … до … ₽, от чего зависит цена.]'),
        $q('Есть ли доставка?', '[Ответ: как и куда доставляете, сколько стоит.]'),
        $q('Можно ли заказать нужный размер или цвет?', '[Ответ.]'),
        $sc('[mem_cta]'),
    ]) . "\n";
}

/** Создать черновик страницы типа (если её ещё нет) */
function memorial_plugin_type_page_create($type_id) {
    if ($id = memorial_plugin_type_page_any($type_id)) return $id;
    $t = get_term($type_id, 'mem_cat');
    if (!$t || is_wp_error($t)) return 0;
    $parent = get_page_by_path('ritualnye-tovary');
    $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => $t->name,
                          'post_parent' => $parent && $parent->post_status === 'publish' ? $parent->ID : 0,
                          'post_content' => wp_slash(memorial_plugin_type_page_content($type_id))]);
    if (is_wp_error($id) || !$id) return 0;
    update_post_meta($id, '_mem_cat', (int) $type_id);
    return $id;
}

/* Новый тип из «Товаров» или «Типов и категорий» — сразу черновик страницы */
add_action('wp_ajax_mem_cm_term', function () {
    if (absint($_POST['parent'] ?? 0)) return; // категория — страницу не создаём
    add_action('created_mem_cat', function ($term_id, $tt_id) {
        $t = get_term($term_id, 'mem_cat');
        if ($t && !$t->parent) memorial_plugin_type_page_create($term_id);
    }, 10, 2);
}, 1);

/* Кнопка «Создать страницу» для типов, у которых её ещё нет */
add_action('wp_ajax_mem_ct_page', function () {
    $type = memorial_plugin_ct_type();
    memorial_plugin_type_page_create($type) ? wp_send_json_success(memorial_plugin_types_data($type)) : wp_send_json_error('Не удалось создать страницу');
});
