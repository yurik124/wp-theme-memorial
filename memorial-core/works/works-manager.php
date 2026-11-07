<?php
/**
 * «Наши работы» в том же стиле, что «Каталог»:
 *  — «Работы»: все работы таблицей с правкой на месте (фото, название, год, размер, состав и цены, виды работ),
 *    отметки и действия разом, перетаскивание порядка, «Новая работа из фото» перетаскиванием;
 *  — «Виды работ»: переименовать, перетащить, удалить, добавить.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    $p = 'edit.php?post_type=mem_work';
    add_submenu_page($p, 'Работы', 'Работы', 'edit_posts', 'mem-works', 'mem_wm_page', 0);
    add_submenu_page($p, 'Виды работ', 'Виды работ', 'manage_categories', 'mem-work-types', 'mem_wt_page', 1);
    remove_submenu_page($p, $p);
    remove_submenu_page($p, 'post-new.php?post_type=mem_work');
    remove_submenu_page($p, 'edit-tags.php?taxonomy=mem_work_type&amp;post_type=mem_work');
    remove_submenu_page($p, 'edit-tags.php?taxonomy=mem_work_type&post_type=mem_work');
}, 30);

add_action('load-edit.php', function () {
    if (($_GET['post_type'] ?? '') !== 'mem_work') return;
    foreach (['classic', 'post_status', 's', 'mem_work_type', 'orderby', 'paged', 'trashed', 'untrashed', 'deleted', 'action'] as $k) if (isset($_GET[$k])) return;
    wp_safe_redirect(admin_url('edit.php?post_type=mem_work&page=mem-works'));
    exit;
});
add_action('load-edit-tags.php', function () {
    if (($_GET['taxonomy'] ?? '') !== 'mem_work_type' || isset($_GET['classic']) || isset($_GET['action']) || !empty($_POST)) return;
    wp_safe_redirect(admin_url('edit.php?post_type=mem_work&page=mem-work-types'));
    exit;
});

/** Виды работ по порядку */
function mem_wm_kinds() {
    $t = get_terms(['taxonomy' => 'mem_work_type', 'hide_empty' => false]);
    if (is_wp_error($t)) return [];
    usort($t, fn($a, $b) => ((int) get_term_meta($a->term_id, 'mem_order', true) <=> (int) get_term_meta($b->term_id, 'mem_order', true)) ?: strcmp($a->name, $b->name));
    return array_map(fn($k) => ['id' => $k->term_id, 'name' => $k->name, 'count' => (int) $k->count], $t);
}

function mem_wm_item($w) {
    $d = mem_work_data($w->ID);
    $kinds = wp_get_object_terms($w->ID, 'mem_work_type', ['fields' => 'ids']);
    return [
        'id'     => $w->ID,
        'title'  => html_entity_decode(get_the_title($w), ENT_QUOTES, 'UTF-8'),
        'status' => $w->post_status,
        'text'   => $w->post_content,
        'year'   => $d['year'] ?: '',
        'size'   => $d['size'],
        'rows'   => array_map(fn($r) => [(string) ($r[0] ?? ''), (int) ($r[1] ?? 0) ?: ''], $d['rows']),
        'photos' => array_map(fn($a) => ['id' => $a, 'thumb' => wp_get_attachment_image_url($a, 'thumbnail')], $d['photos']),
        'kinds'  => is_wp_error($kinds) ? [] : array_map('intval', $kinds),
        'ex'     => (bool) get_post_meta($w->ID, '_mem_example', true),
        'edit'   => get_edit_post_link($w->ID, 'raw'),
    ];
}

function mem_wm_data() {
    $works = get_posts(['post_type' => 'mem_work', 'post_status' => ['publish', 'draft', 'pending', 'private'], 'posts_per_page' => -1,
                        'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC']]);
    return ['kinds' => mem_wm_kinds(), 'works' => array_map('mem_wm_item', $works)];
}

function mem_wm_page() { ?>
    <div class="wrap mcm mwm" id="mwm">
      <div class="mcm__head">
        <h1>Наши работы</h1>
        <a class="mcm__classic" href="<?php echo esc_url(admin_url('edit.php?post_type=mem_work&classic=1')); ?>">Классический список</a>
      </div>
      <div class="mwm__app"><p class="mcm__loading">Загрузка…</p></div>
    </div>
    <?php
}

function mem_wt_page() { ?>
    <div class="wrap mcm mct" id="mwt">
      <div class="mcm__head">
        <h1>Виды работ</h1>
        <a class="mcm__classic" href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=mem_work_type&post_type=mem_work&classic=1')); ?>">Классический список</a>
      </div>
      <div class="mwt__app"><p class="mcm__loading">Загрузка…</p></div>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['mem_work_page_mem-works', 'mem_work_page_mem-work-types'], true)) return;
    $dir = MEM_CORE_DIR; $uri = MEM_CORE_URL;
    wp_enqueue_media();
    wp_enqueue_style('mem-cm', $uri . '/assets/css/catalog-manager.css', [], filemtime($dir . '/assets/css/catalog-manager.css'));
    wp_enqueue_script('mem-hscroll', $uri . '/assets/js/admin-scroll.js', [], filemtime($dir . '/assets/js/admin-scroll.js'), true);
    wp_enqueue_script('mem-wm', $uri . '/assets/js/works-manager.js', ['jquery', 'jquery-ui-sortable'], filemtime($dir . '/assets/js/works-manager.js'), true);
    wp_localize_script('mem-wm', 'memWM', [
        'ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('mem_cm'), 'data' => mem_wm_data(),
        'screen' => $hook === 'mem_work_page_mem-works' ? 'works' : 'kinds',
        'kindsUrl' => admin_url('edit.php?post_type=mem_work&page=mem-work-types'), 'worksUrl' => admin_url('edit.php?post_type=mem_work&page=mem-works'),
        'maxUpload' => wp_max_upload_size(), 'canManage' => current_user_can('manage_categories'),
    ]);
});

/* ---------- запросы ---------- */
function mem_wm_work($id) {
    $id = absint($id);
    if (!$id || get_post_type($id) !== 'mem_work' || !current_user_can('edit_post', $id)) wp_send_json_error('Работа не найдена', 404);
    return $id;
}

add_action('wp_ajax_mem_wm_save', function () {
    mem_cm_check();
    $id = mem_wm_work($_POST['id'] ?? 0);
    $f = (string) ($_POST['f'] ?? ''); $v = wp_unslash($_POST['v'] ?? '');
    switch ($f) {
        case 'title':
            $t = trim(sanitize_text_field($v));
            wp_update_post(['ID' => $id, 'post_title' => $t !== '' ? $t : 'Работа без названия']);
            break;
        case 'text':
            wp_update_post(['ID' => $id, 'post_content' => wp_kses_post($v)]);
            break;
        case 'year':
            $y = absint($v);
            $y >= 1990 && $y <= (int) gmdate('Y') + 1 ? update_post_meta($id, '_mem_year', $y) : delete_post_meta($id, '_mem_year');
            break;
        case 'size':
            $s = trim(sanitize_text_field($v));
            $s !== '' ? update_post_meta($id, '_mem_size', $s) : delete_post_meta($id, '_mem_size');
            break;
        case 'rows':
            $rows = [];
            foreach ((array) json_decode((string) $v, true) as $r) {
                $n = trim(sanitize_text_field($r[0] ?? '')); $p = absint(preg_replace('/\D/', '', (string) ($r[1] ?? '')));
                if ($n !== '' || $p) $rows[] = [$n, $p];
            }
            update_post_meta($id, '_mem_rows', $rows);
            break;
        case 'photos':
            $ph = array_values(array_filter(array_map('absint', (array) json_decode((string) $v, true)), 'wp_attachment_is_image'));
            update_post_meta($id, '_mem_photos', implode(',', $ph));
            $ph ? set_post_thumbnail($id, $ph[0]) : delete_post_thumbnail($id);
            break;
        case 'kinds':
            $k = array_values(array_filter(array_map('absint', (array) json_decode((string) $v, true)), fn($t) => term_exists($t, 'mem_work_type')));
            wp_set_object_terms($id, $k, 'mem_work_type');
            break;
        case 'ex': // «В примерах» — карточки [mem_works vid="карточки"]
            $v === '1' ? update_post_meta($id, '_mem_example', 1) : delete_post_meta($id, '_mem_example');
            break;
        case 'status':
            wp_update_post(['ID' => $id, 'post_status' => $v === 'publish' ? 'publish' : 'draft']);
            break;
        default:
            wp_send_json_error('Поле?');
    }
    wp_send_json_success(mem_wm_item(get_post($id)));
});

/* Фото с компьютера → в медиатеку (работа создаётся следующим запросом из всех загруженных фото) */
add_action('wp_ajax_mem_wm_upload', function () {
    mem_cm_check('upload_files');
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $att = media_handle_upload('file', 0);
    if (is_wp_error($att)) wp_send_json_error($att->get_error_message());
    if (!wp_attachment_is_image($att)) { wp_delete_attachment($att, true); wp_send_json_error('Это не фото'); }
    wp_send_json_success(['id' => $att, 'thumb' => wp_get_attachment_image_url($att, 'thumbnail')]);
});

/* Новая работа (с фото или без), ставится в конец списка */
add_action('wp_ajax_mem_wm_new', function () {
    mem_cm_check();
    $ph = array_values(array_filter(array_map('absint', (array) ($_POST['atts'] ?? [])), 'wp_attachment_is_image'));
    $last = get_posts(['post_type' => 'mem_work', 'post_status' => 'any', 'posts_per_page' => 1, 'orderby' => 'menu_order', 'order' => 'DESC']);
    $id = wp_insert_post(['post_type' => 'mem_work', 'post_status' => 'publish', 'post_title' => 'Новая работа', 'menu_order' => $last ? (int) $last[0]->menu_order + 1 : 1]);
    if (is_wp_error($id) || !$id) wp_send_json_error('Не удалось создать работу');
    update_post_meta($id, '_mem_photos', implode(',', $ph));
    update_post_meta($id, '_mem_year', (int) gmdate('Y'));
    if ($ph) set_post_thumbnail($id, $ph[0]);
    $kind = absint($_POST['kind'] ?? 0);
    if ($kind && term_exists($kind, 'mem_work_type')) wp_set_object_terms($id, [$kind], 'mem_work_type');
    wp_send_json_success(mem_wm_item(get_post($id)));
});

add_action('wp_ajax_mem_wm_order', function () {
    mem_cm_check();
    foreach (array_map('absint', (array) ($_POST['ids'] ?? [])) as $i => $id) {
        if ($id && get_post_type($id) === 'mem_work' && current_user_can('edit_post', $id)) wp_update_post(['ID' => $id, 'menu_order' => $i + 1]);
    }
    wp_send_json_success();
});

add_action('wp_ajax_mem_wm_copy', function () {
    mem_cm_check();
    $id = mem_wm_work($_POST['id'] ?? 0);
    $w = get_post($id);
    $new = wp_insert_post(['post_type' => 'mem_work', 'post_status' => $w->post_status, 'post_title' => $w->post_title . ' (копия)', 'post_content' => $w->post_content, 'menu_order' => $w->menu_order]);
    if (is_wp_error($new) || !$new) wp_send_json_error('Не удалось');
    foreach (['_mem_photos', '_mem_size', '_mem_rows', '_mem_year', '_thumbnail_id'] as $k) { $v = get_post_meta($id, $k, true); if ($v !== '') update_post_meta($new, $k, $v); }
    wp_set_object_terms($new, wp_get_object_terms($id, 'mem_work_type', ['fields' => 'ids']), 'mem_work_type');
    wp_send_json_success(mem_wm_item(get_post($new)));
});

/* Действия с отмеченными */
add_action('wp_ajax_mem_wm_bulk', function () {
    mem_cm_check();
    $ids = array_values(array_filter(array_map('absint', (array) ($_POST['ids'] ?? [])), fn($id) => get_post_type($id) === 'mem_work' && current_user_can('edit_post', $id)));
    $op = sanitize_key($_POST['op'] ?? '');
    if (!$ids) wp_send_json_error('Не отмечено ни одной работы');
    $kinds = array_values(array_filter(array_map('absint', (array) json_decode(wp_unslash((string) ($_POST['kinds'] ?? '[]')), true))));
    $out = [];
    foreach ($ids as $id) {
        if ($op === 'trash') { wp_trash_post($id); continue; }
        if ($op === 'publish' || $op === 'draft') wp_update_post(['ID' => $id, 'post_status' => $op]);
        elseif ($op === 'kinds_add') wp_set_object_terms($id, $kinds, 'mem_work_type', true);
        elseif ($op === 'kinds_remove') wp_remove_object_terms($id, $kinds, 'mem_work_type');
        else wp_send_json_error('Действие?');
        $out[] = mem_wm_item(get_post($id));
    }
    wp_send_json_success(['works' => $out, 'trashed' => $op === 'trash' ? $ids : [], 'kinds' => mem_wm_kinds()]);
});

/* Виды работ: создать, переименовать, порядок, удалить */
add_action('wp_ajax_mem_wt', function () {
    mem_cm_check('manage_categories');
    $op = sanitize_key($_POST['op'] ?? '');
    $id = absint($_POST['id'] ?? 0);
    $name = trim(sanitize_text_field(wp_unslash($_POST['name'] ?? '')));
    if ($op === 'add') {
        if ($name === '') wp_send_json_error('Пустое название');
        $r = wp_insert_term($name, 'mem_work_type');
        if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
        update_term_meta($r['term_id'], 'mem_order', 1000);
    } elseif ($op === 'rename' && $id && $name !== '') {
        $r = wp_update_term($id, 'mem_work_type', ['name' => $name]);
        if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
    } elseif ($op === 'delete' && $id) {
        wp_delete_term($id, 'mem_work_type');
    } elseif ($op === 'order') {
        foreach (array_map('absint', (array) ($_POST['ids'] ?? [])) as $i => $t) if ($t) update_term_meta($t, 'mem_order', ($i + 1) * 10);
    } else {
        wp_send_json_error('Действие?');
    }
    wp_send_json_success(['kinds' => mem_wm_kinds(), 'id' => isset($r['term_id']) ? (int) $r['term_id'] : $id]);
});
