<?php
/**
 * «Каталог → Товары»: все товары по типам на вкладках, правка прямо в таблице (фото, название, цена,
 * характеристики, категории), загрузка фото перетаскиванием — каждое фото сразу становится товаром.
 * Всё сохраняется само. Классический список WordPress остаётся по ссылке «Классический список».
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=mem_product', 'Товары', 'Товары', 'edit_posts', 'mem-products', 'mem_manager_page', 0);
    remove_submenu_page('edit.php?post_type=mem_product', 'edit.php?post_type=mem_product');
    remove_submenu_page('edit.php?post_type=mem_product', 'post-new.php?post_type=mem_product');
}, 30);

/* Пункт «Каталог» ведёт на новый экран; классический список — с параметром classic=1 или с фильтрами */
add_action('load-edit.php', function () {
    if (($_GET['post_type'] ?? '') !== 'mem_product') return;
    $keep = ['classic', 'post_status', 's', 'mem_cat', 'mem_new', 'orderby', 'paged', 'trashed', 'untrashed', 'deleted', 'action'];
    foreach ($keep as $k) if (isset($_GET[$k])) return;
    wp_safe_redirect(admin_url('edit.php?post_type=mem_product&page=mem-products'));
    exit;
});

/* Подписи в меню: категории = «Типы и категории» */
add_filter('register_taxonomy_args', function ($args, $tax) {
    if ($tax === 'mem_cat') $args['labels'] = array_merge((array) ($args['labels'] ?? []), ['name' => 'Типы и категории', 'menu_name' => 'Типы и категории']);
    return $args;
}, 10, 2);

function mem_manager_types() {
    $t = get_terms(['taxonomy' => 'mem_cat', 'parent' => 0, 'hide_empty' => false]);
    if (is_wp_error($t)) return [];
    usort($t, fn($a, $b) => ((int) get_term_meta($a->term_id, 'mem_order', true) <=> (int) get_term_meta($b->term_id, 'mem_order', true)) ?: strcmp($a->name, $b->name));
    return $t;
}

/** Все подкатегории типа (вложенные — с отступом в названии) */
function mem_manager_cats($type_id, $depth = 0) {
    $out = [];
    foreach (mem_sorted_children($type_id) as $c) {
        $out[] = ['id' => $c->term_id, 'name' => str_repeat('— ', $depth) . $c->name];
        $out = array_merge($out, mem_manager_cats($c->term_id, $depth + 1));
    }
    return $out;
}

function mem_manager_product($p, $type_id) {
    $cats = wp_get_object_terms($p->ID, 'mem_cat', ['fields' => 'ids']);
    $thumb = get_post_thumbnail_id($p->ID);
    return [
        'id'     => $p->ID,
        'title'  => html_entity_decode(get_the_title($p), ENT_QUOTES, 'UTF-8'),
        'status' => $p->post_status,
        'thumb'  => $thumb ? wp_get_attachment_image_url($thumb, 'thumbnail') : '',
        'tid'    => (int) $thumb,   // номер фото — чтобы в окне выбора было отмечено текущее
        'pids'   => mem_product_photos($p->ID),   // все фото товара: первое — главное
        'big'    => $thumb ? wp_get_attachment_image_url($thumb, 'large') : '',
        'price'  => (int) get_post_meta($p->ID, '_mem_price', true) ?: '',
        'from'   => get_post_meta($p->ID, '_mem_price_from', true) === '1',
        'unit'   => (string) get_post_meta($p->ID, '_mem_unit', true),
        'sub'    => (string) get_post_meta($p->ID, '_mem_sub', true),
        'specs'  => (object) mem_product_spec_map($p->ID),
        'cats'   => array_values(array_diff(is_wp_error($cats) ? [] : array_map('intval', $cats), [$type_id])),
        'edit'   => get_edit_post_link($p->ID, 'raw'),
        'view'   => trim(wp_strip_all_tags($p->post_content)) !== '' ? get_permalink($p) : '',
    ];
}

function mem_manager_type_data($type_id) {
    $posts = get_posts(['post_type' => 'mem_product', 'post_status' => ['publish', 'draft', 'pending', 'private'], 'posts_per_page' => -1,
        'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'],
        'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $type_id, 'include_children' => true]]]);
    $t = get_term($type_id, 'mem_cat');
    return [
        'id'       => $type_id,
        'name'     => $t ? $t->name : '',
        'fields'   => mem_type_fields($type_id),
        'cats'     => mem_manager_cats($type_id),
        'products' => array_map(fn($p) => mem_manager_product($p, $type_id), $posts),
        'termEdit' => get_edit_term_link($type_id, 'mem_cat', 'mem_product'),
    ];
}

function mem_manager_page() {
    $types = mem_manager_types();
    $cur = absint($_GET['type'] ?? 0);
    if (!$cur || !in_array($cur, wp_list_pluck($types, 'term_id'), true)) $cur = $types ? $types[0]->term_id : 0;
    $counts = [];
    foreach ($types as $t) $counts[$t->term_id] = mem_cat_count($t->term_id);
    ?>
    <div class="wrap mcm" id="mcm" data-type="<?php echo (int) $cur; ?>">
      <div class="mcm__head">
        <h1>Товары</h1>
        <a class="mcm__classic" href="<?php echo esc_url(admin_url('edit.php?post_type=mem_product&classic=1')); ?>">Классический список</a>
      </div>
      <nav class="mcm__tabs" aria-label="Типы товаров">
        <?php foreach ($types as $t) : ?>
          <a class="mcm__tab<?php echo $t->term_id === $cur ? ' is-on' : ''; ?>" href="<?php echo esc_url(add_query_arg('type', $t->term_id)); ?>"><?php echo esc_html($t->name); ?> <span><?php echo (int) $counts[$t->term_id]; ?></span></a>
        <?php endforeach; ?>
        <button type="button" class="mcm__tab mcm__tab--add" data-act="new-type">+ Новый тип</button>
      </nav>
      <?php if (!$cur) : ?>
        <p class="mcm__empty">Пока нет ни одного типа товаров. Нажмите «+ Новый тип», например «Венки».</p>
      <?php else : ?>
        <div class="mcm__app" aria-live="polite"><p class="mcm__loading">Загрузка…</p></div>
      <?php endif; ?>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'mem_product_page_mem-products') return;
    $dir = MEM_CORE_DIR; $uri = MEM_CORE_URL;
    wp_enqueue_media();
    wp_enqueue_style('mem-cm', $uri . '/assets/css/catalog-manager.css', [], filemtime($dir . '/assets/css/catalog-manager.css'));
    wp_enqueue_script('mem-hscroll', $uri . '/assets/js/admin-scroll.js', [], filemtime($dir . '/assets/js/admin-scroll.js'), true);
    wp_enqueue_script('mem-cm', $uri . '/assets/js/catalog-manager.js', ['jquery', 'jquery-ui-sortable'], filemtime($dir . '/assets/js/catalog-manager.js'), true);
    $type = absint($_GET['type'] ?? 0) ?: (mem_manager_types()[0]->term_id ?? 0);
    wp_localize_script('mem-cm', 'memCM', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mem_cm'),
        'data'  => $type ? mem_manager_type_data($type) : null,
        'canManage' => current_user_can('manage_categories'),
        'typesUrl'  => admin_url('edit.php?post_type=mem_product&page=mem-types'),
        'maxUpload' => wp_max_upload_size(),
    ]);
});

/* ---------- Запросы со страницы ---------- */
function mem_cm_check($cap = 'edit_posts') {
    check_ajax_referer('mem_cm');
    if (!current_user_can($cap)) wp_send_json_error('Недостаточно прав', 403);
}
function mem_cm_product($id) {
    $id = absint($id);
    if (!$id || get_post_type($id) !== 'mem_product' || !current_user_can('edit_post', $id)) wp_send_json_error('Товар не найден', 404);
    return $id;
}

/* Данные вкладки */
add_action('wp_ajax_mem_cm_load', function () {
    mem_cm_check();
    wp_send_json_success(mem_manager_type_data(absint($_POST['type'] ?? 0)));
});

/* Сохранить поле товара */
add_action('wp_ajax_mem_cm_save', function () {
    mem_cm_check();
    $id = mem_cm_product($_POST['id'] ?? 0);
    $f = (string) ($_POST['f'] ?? '');
    $v = wp_unslash($_POST['v'] ?? '');
    if ($f === 'title') {
        $t = trim(sanitize_text_field($v));
        wp_update_post(['ID' => $id, 'post_title' => $t !== '' ? $t : 'Без названия']);
    } elseif ($f === 'price') {
        update_post_meta($id, '_mem_price', absint(preg_replace('/\D/', '', (string) $v)));
    } elseif ($f === 'from') {
        update_post_meta($id, '_mem_price_from', $v ? '1' : '');
    } elseif ($f === 'unit' || $f === 'sub') {
        $v = trim(sanitize_text_field($v));
        $v !== '' ? update_post_meta($id, '_mem_' . $f, $v) : delete_post_meta($id, '_mem_' . $f);
    } elseif (strpos($f, 'spec:') === 0) {
        mem_set_spec($id, sanitize_text_field(substr($f, 5)), (string) $v);
    } elseif ($f === 'cats') {
        $type = absint($_POST['type'] ?? 0);
        $ids = array_values(array_filter(array_map('absint', (array) json_decode((string) $v, true)), fn($c) => $c && mem_type_root($c) === $type));
        // без категорий товар лежит прямо в типе; с категориями — только в них (иначе на сайте он показался бы дважды)
        wp_set_object_terms($id, $ids ?: [$type], 'mem_cat');
    } elseif ($f === 'status') {
        wp_update_post(['ID' => $id, 'post_status' => $v === 'publish' ? 'publish' : 'draft']);
    } elseif ($f === 'photo' || $f === 'photos') {
        // photos: «12,34,56» — первое фото главное, остальные листаются в карточке
        $ids = array_values(array_unique(array_filter(array_map('absint', explode(',', (string) $v)), 'wp_attachment_is_image')));
        if ($ids) set_post_thumbnail($id, $ids[0]);
        if ($f === 'photos') $ids && count($ids) > 1 ? update_post_meta($id, '_mem_gallery', implode(',', array_slice($ids, 1))) : delete_post_meta($id, '_mem_gallery');
        $tid = get_post_thumbnail_id($id);
        wp_send_json_success(['pids' => mem_product_photos($id), 'tid' => (int) $tid, 'thumb' => $tid ? wp_get_attachment_image_url($tid, 'thumbnail') : '', 'big' => $tid ? wp_get_attachment_image_url($tid, 'large') : '']);
    } else {
        wp_send_json_error('Неизвестное поле');
    }
    wp_send_json_success();
});

/* Новые товары из фото (уже загруженных или выбранных в библиотеке) */
function mem_cm_create($type, $att, $title = '') {
    $last = get_posts(['post_type' => 'mem_product', 'post_status' => 'any', 'posts_per_page' => 1, 'orderby' => 'menu_order', 'order' => 'DESC',
                       'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $type, 'include_children' => true]]]);
    $order = $last ? (int) $last[0]->menu_order + 1 : 1;
    $id = wp_insert_post(['post_type' => 'mem_product', 'post_status' => 'publish', 'post_title' => $title !== '' ? $title : 'Новый товар', 'menu_order' => $order]);
    if (is_wp_error($id) || !$id) return 0;
    wp_set_object_terms($id, [$type], 'mem_cat');
    update_post_meta($id, '_mem_price', 0);
    mem_apply_type_defaults($id, $type);
    if ($att) set_post_thumbnail($id, $att);
    return $id;
}

add_action('wp_ajax_mem_cm_new', function () {
    mem_cm_check();
    $type = absint($_POST['type'] ?? 0);
    if (!$type || mem_type_root($type) !== $type) wp_send_json_error('Нет типа');
    $out = [];
    $atts = array_filter(array_map('absint', (array) ($_POST['atts'] ?? [])));
    foreach ($atts ?: [0] as $att) {
        if ($att && !wp_attachment_is_image($att)) continue;
        if ($id = mem_cm_create($type, $att)) $out[] = mem_manager_product(get_post($id), $type);
    }
    wp_send_json_success($out);
});

/* Загрузка файла с компьютера (перетаскивание) → фото в библиотеку + новый товар */
add_action('wp_ajax_mem_cm_upload', function () {
    mem_cm_check('upload_files');
    $type = absint($_POST['type'] ?? 0);
    if (!$type || mem_type_root($type) !== $type) wp_send_json_error('Нет типа');
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $att = media_handle_upload('file', 0);
    if (is_wp_error($att)) wp_send_json_error($att->get_error_message());
    if (!wp_attachment_is_image($att)) { wp_delete_attachment($att, true); wp_send_json_error('Это не фото'); }
    $id = mem_cm_create($type, $att);
    $id ? wp_send_json_success(mem_manager_product(get_post($id), $type)) : wp_send_json_error('Не удалось создать товар');
});

/* Порядок */
add_action('wp_ajax_mem_cm_order', function () {
    mem_cm_check();
    foreach (array_map('absint', (array) ($_POST['ids'] ?? [])) as $i => $id) {
        if ($id && get_post_type($id) === 'mem_product' && current_user_can('edit_post', $id)) wp_update_post(['ID' => $id, 'menu_order' => $i + 1]);
    }
    wp_send_json_success();
});

/* В корзину (восстановить можно в классическом списке → «Корзина») */
add_action('wp_ajax_mem_cm_trash', function () {
    mem_cm_check();
    $id = mem_cm_product($_POST['id'] ?? 0);
    wp_trash_post($id) ? wp_send_json_success() : wp_send_json_error('Не удалось');
});

/* Копия товара */
add_action('wp_ajax_mem_cm_copy', function () {
    mem_cm_check();
    $id = mem_cm_product($_POST['id'] ?? 0);
    $p = get_post($id);
    $new = wp_insert_post(['post_type' => 'mem_product', 'post_status' => $p->post_status, 'post_title' => $p->post_title . ' (копия)',
                           'post_content' => $p->post_content, 'menu_order' => $p->menu_order]);
    if (is_wp_error($new) || !$new) wp_send_json_error('Не удалось');
    foreach (['_mem_price', '_mem_price_from', '_mem_unit', '_mem_sub', '_mem_specs', '_mem_price_old', '_mem_on_order', '_thumbnail_id'] as $k) {
        $v = get_post_meta($id, $k, true);
        if ($v !== '') update_post_meta($new, $k, $v);
    }
    wp_set_object_terms($new, wp_get_object_terms($id, 'mem_cat', ['fields' => 'ids']), 'mem_cat');
    wp_send_json_success(mem_manager_product(get_post($new), absint($_POST['type'] ?? 0)));
});

/* Настройки типа: характеристики */
add_action('wp_ajax_mem_cm_fields', function () {
    mem_cm_check('manage_categories');
    $type = absint($_POST['type'] ?? 0);
    if (!$type || mem_type_root($type) !== $type) wp_send_json_error('Нет типа');
    $fields = array_values(array_filter(array_map('mem_clean_field', (array) json_decode(wp_unslash((string) ($_POST['fields'] ?? '[]')), true))));
    update_term_meta($type, 'mem_fields', wp_json_encode($fields, JSON_UNESCAPED_UNICODE));
    // переименование: старое название → новое у всех товаров типа
    foreach ((array) json_decode(wp_unslash((string) ($_POST['renames'] ?? '{}')), true) as $old => $new) {
        $old = sanitize_text_field($old); $new = sanitize_text_field($new);
        if ($old === '' || $new === '' || $old === $new) continue;
        foreach (get_posts(['post_type' => 'mem_product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
                            'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $type, 'include_children' => true]]]) as $pid) {
            $m = mem_product_spec_map($pid);
            if (isset($m[$old])) { mem_set_spec($pid, $old, ''); mem_set_spec($pid, $new, $m[$old]); }
        }
    }
    wp_send_json_success(($_POST['for'] ?? '') === 'types' ? mem_types_data($type) : mem_manager_type_data($type));
});

/* Новая категория типа / новый тип */
add_action('wp_ajax_mem_cm_term', function () {
    mem_cm_check('manage_categories');
    $name = trim(sanitize_text_field(wp_unslash($_POST['name'] ?? '')));
    $parent = absint($_POST['parent'] ?? 0);
    if ($name === '') wp_send_json_error('Пустое название');
    if ($parent && mem_type_root($parent) !== $parent) wp_send_json_error('Нет типа');
    $r = wp_insert_term($name, 'mem_cat', ['parent' => $parent]);
    if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
    wp_send_json_success(['id' => (int) $r['term_id'], 'name' => $name, 'url' => admin_url('edit.php?post_type=mem_product&page=mem-products&type=' . (int) $r['term_id'])]);
});

/* Действия с отмеченными товарами: цены, категории, скрыть/показать, удалить */
add_action('wp_ajax_mem_cm_bulk', function () {
    mem_cm_check();
    $type = absint($_POST['type'] ?? 0);
    $ids = array_values(array_filter(array_map('absint', (array) ($_POST['ids'] ?? [])), fn($id) => get_post_type($id) === 'mem_product' && current_user_can('edit_post', $id)));
    $op = sanitize_key($_POST['op'] ?? '');
    if (!$ids) wp_send_json_error('Не отмечено ни одного товара');
    $out = [];
    foreach ($ids as $id) {
        if ($op === 'price') {
            $cur = (int) get_post_meta($id, '_mem_price', true);
            if (!$cur && ($_POST['mode'] ?? '') !== 'set') continue; // «по запросу» не трогаем при наценке
            $val = (float) str_replace(',', '.', (string) ($_POST['value'] ?? 0));
            $new = match ($_POST['mode'] ?? '') {
                'pct' => $cur * (1 + $val / 100),
                'add' => $cur + $val,
                'set' => $val,
                default => $cur,
            };
            $round = max(1, absint($_POST['round'] ?? 1));
            $new = (int) (round($new / $round) * $round);
            update_post_meta($id, '_mem_price', max(0, $new));
        } elseif ($op === 'cats_add' || $op === 'cats_remove') {
            $cats = array_values(array_filter(array_map('absint', (array) json_decode(wp_unslash((string) ($_POST['cats'] ?? '[]')), true)), fn($c) => $c && mem_type_root($c) === $type && $c !== $type));
            $have = array_diff(array_map('intval', wp_get_object_terms($id, 'mem_cat', ['fields' => 'ids'])), [$type]);
            $have = $op === 'cats_add' ? array_unique(array_merge($have, $cats)) : array_diff($have, $cats);
            wp_set_object_terms($id, $have ? array_values($have) : [$type], 'mem_cat');
        } elseif ($op === 'publish' || $op === 'draft') {
            wp_update_post(['ID' => $id, 'post_status' => $op]);
        } elseif ($op === 'trash') {
            wp_trash_post($id);
            continue;
        } else {
            wp_send_json_error('Действие?');
        }
        $out[] = mem_manager_product(get_post($id), $type);
    }
    wp_send_json_success(['products' => $out, 'trashed' => $op === 'trash' ? $ids : []]);
});
