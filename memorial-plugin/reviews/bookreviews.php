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

add_filter('use_block_editor_for_post_type', fn($use, $type) => $type === 'mem_bookrev' ? false : $use, 10, 2);
add_filter('enter_title_here', fn($t, $post) => $post->post_type === 'mem_bookrev' ? 'Имя автора, например: Елена В.' : $t, 10, 2);

/** Филиалы для выбора: адреса из «Мемориал → Контакты» */
function memorial_plugin_branch_options() {
    $out = [];
    foreach (memorial_plugin_addrs() as $a) {
        $short = memorial_plugin_short_addr($a['addr']);
        if ($short !== '') $out[$short] = $short;
    }
    return $out;
}

add_action('add_meta_boxes_mem_bookrev', function () {
    add_meta_box('mem_bookrev', 'Дата и филиал', 'memorial_plugin_bookrev_box', 'mem_bookrev', 'side', 'high');
});

function memorial_plugin_bookrev_box($post) {
    wp_nonce_field('mem_bookrev', 'mem_bookrev_nonce');
    $date = get_post_meta($post->ID, '_mem_bdate', true);
    $branch = get_post_meta($post->ID, '_mem_branch', true);
    $opts = memorial_plugin_branch_options();
    if ($branch && !isset($opts[$branch])) $opts[$branch] = $branch; ?>
    <p><label><strong>Дата отзыва в книге</strong><br>
      <input type="date" name="mem_bdate" value="<?php echo esc_attr($date); ?>" style="width:100%"></label>
      <span class="description">Если число не разобрать — поставьте 1-е число месяца: на сайте показываются только месяц и год.</span></p>
    <p><label><strong>Где хранится книга</strong><br>
      <select name="mem_branch" style="width:100%">
        <option value="">— не указывать —</option>
        <?php foreach ($opts as $v => $l) echo '<option value="' . esc_attr($v) . '"' . selected($branch, $v, false) . '>' . esc_html($l) . '</option>'; ?>
      </select></label></p>
    <p class="description">Текст отзыва — в поле редактора, как написано в книге. Фото страницы — справа, «Фото страницы книги».<br>
      Перед загрузкой фото закрасьте на нём фамилию, телефон и подпись автора, если у вас нет его согласия на публикацию.</p>
    <?php
}

add_action('save_post_mem_bookrev', function ($id) {
    if (!isset($_POST['mem_bookrev_nonce']) || !wp_verify_nonce($_POST['mem_bookrev_nonce'], 'mem_bookrev')) return;
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $id)) return;
    $d = sanitize_text_field(wp_unslash($_POST['mem_bdate'] ?? ''));
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? update_post_meta($id, '_mem_bdate', $d) : delete_post_meta($id, '_mem_bdate');
    $b = sanitize_text_field(wp_unslash($_POST['mem_branch'] ?? ''));
    $b !== '' ? update_post_meta($id, '_mem_branch', $b) : delete_post_meta($id, '_mem_branch');
});

/* Колонки списка */
add_filter('manage_mem_bookrev_posts_columns', function ($c) {
    return ['cb' => $c['cb'], 'mem_photo' => 'Фото', 'title' => 'Автор', 'mem_text' => 'Отзыв', 'mem_bdate' => 'Дата', 'mem_branch' => 'Филиал'];
});
add_action('manage_mem_bookrev_posts_custom_column', function ($col, $id) {
    if ($col === 'mem_photo' && has_post_thumbnail($id)) echo get_the_post_thumbnail($id, [50, 50]);
    if ($col === 'mem_text') echo esc_html(wp_trim_words(get_post_field('post_content', $id), 18));
    if ($col === 'mem_bdate') echo esc_html(memorial_plugin_bookrev_month($id));
    if ($col === 'mem_branch') echo esc_html(get_post_meta($id, '_mem_branch', true));
}, 10, 2);
add_action('admin_head', function () {
    $s = get_current_screen();
    if ($s && $s->post_type === 'mem_bookrev') echo '<style>.column-mem_photo{width:60px}.column-mem_photo img{width:50px;height:50px;object-fit:cover;border-radius:4px}.column-mem_bdate{width:110px}</style>';
});

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
        if ($long) $h .= '<button type="button" class="brev__more" aria-expanded="false">Читать полностью</button>';
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
