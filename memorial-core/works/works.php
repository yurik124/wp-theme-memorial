<?php
/**
 * «Наши работы» — примеры благоустройства с фото, размером участка и ценами.
 * Добавляются в «Наши работы → Добавить работу», выводятся на странице шорткодом [mem_works].
 *
 *   [mem_works]               — все работы
 *   [mem_works type="plitka"] — только один вид работ
 *   [mem_works limit="3"]     — первые три (например, на странице «Благоустройство»)
 *
 * Карточками (фото + название + размер и цена, клик — к работе на странице со всеми работами):
 *   [mem_works vid="карточки"]                              — 6 работ: отмеченные «В примерах» (если отмеченных нет — любые)
 *   [mem_works vid="карточки" chislo="3" poryadok="случайно"] — 3 случайные, при каждом открытии страницы другие
 *   [mem_works vid="карточки" type="Укладка плитки"]          — только один вид работ (название или адрес вида)
 *   [mem_works vid="карточки" raboty="Плитка «Гжель», …"]    — выбранные работы по названию
 *   razmer="нет" / cena="нет" — без размера участка / без цены
 */
defined('ABSPATH') || exit;

/** Якорь работы по названию латиницей: «Плитка «Гжель»…» → #rabota-plitka-gzhel-… — на него можно ссылаться с других страниц */
function mem_work_anchor($title) {
    return 'rabota-' . substr(mem_slug($title), 0, 60);
}

add_action('init', function () {
    register_post_type('mem_work', [
        'labels' => [
            'name' => 'Наши работы', 'singular_name' => 'Работа', 'menu_name' => 'Наши работы',
            'all_items' => 'Все работы', 'add_new' => 'Добавить работу', 'add_new_item' => 'Новая работа', 'edit_item' => 'Редактировать работу',
        ],
        'public' => false, 'show_ui' => true, 'show_in_rest' => true,
        'menu_icon' => 'dashicons-format-gallery', 'menu_position' => 6,
        'supports' => ['title', 'editor', 'page-attributes'],
    ]);
    register_taxonomy('mem_work_type', 'mem_work', [
        'labels' => ['name' => 'Виды работ', 'singular_name' => 'Вид работ', 'add_new_item' => 'Добавить вид'],
        'hierarchical' => true, 'public' => false, 'show_ui' => true, 'show_in_rest' => true, 'show_admin_column' => true, 'rewrite' => false,
    ]);
});

/* Классический редактор для работ: текст короткий, а поля ниже */
add_filter('use_block_editor_for_post_type', fn($use, $type) => $type === 'mem_work' ? false : $use, 10, 2);

function mem_work_data($id) {
    $rows = get_post_meta($id, '_mem_rows', true);
    $rows = is_array($rows) ? $rows : [];
    return [
        'photos' => array_values(array_filter(array_map('absint', explode(',', (string) get_post_meta($id, '_mem_photos', true))))),
        'size'   => (string) get_post_meta($id, '_mem_size', true),
        'year'   => (int) get_post_meta($id, '_mem_year', true) ?: '',
        'rows'   => $rows,
        'total'  => array_sum(array_map(fn($r) => (int) ($r[1] ?? 0), $rows)),
    ];
}

add_action('add_meta_boxes_mem_work', function () {
    add_meta_box('mem_work', 'Фото, размер и цены', 'mem_work_box', 'mem_work', 'normal', 'high');
});

function mem_work_box($post) {
    $d = mem_work_data($post->ID);
    wp_nonce_field('mem_work', 'mem_work_nonce'); ?>
    <p><strong>Фото</strong> — первое главное, остальные показываются маленькими под ним. Порядок меняется перетаскиванием.</p>
    <input type="hidden" name="mem_photos" id="mem-photos" value="<?php echo esc_attr(implode(',', $d['photos'])); ?>">
    <ul id="mem-photos-list" class="mem-photos">
      <?php foreach ($d['photos'] as $a) : ?>
        <li data-id="<?php echo (int) $a; ?>"><?php echo wp_get_attachment_image($a, 'thumbnail'); ?><button type="button" aria-label="Убрать">×</button></li>
      <?php endforeach; ?>
    </ul>
    <p><button type="button" class="button" id="mem-photos-add">Добавить фото</button></p>

    <p style="display:flex;gap:1.5em;flex-wrap:wrap">
      <label><strong>Размер участка</strong><br>
      <input type="text" name="mem_size" value="<?php echo esc_attr($d['size']); ?>" class="regular-text" placeholder="например: 2,5 × 2 м"></label>
      <label><strong>Год выполнения</strong><br>
      <input type="number" name="mem_year" value="<?php echo esc_attr($d['year']); ?>" min="1990" max="<?php echo (int) gmdate('Y') + 1; ?>" style="width:110px" placeholder="<?php echo esc_attr(gmdate('Y')); ?>"></label></p>
    <p class="description" style="margin-top:-.5em">По году посетитель понимает, что цены — на момент выполнения. Пусто — год не показывается.</p>

    <p><strong>Что сделано и стоимость</strong> — итог считается сам.</p>
    <table class="widefat striped mem-rows" id="mem-rows">
      <thead><tr><th>Что сделано</th><th style="width:140px">Цена, ₽</th><th style="width:30px"></th></tr></thead>
      <tbody>
        <?php foreach (array_merge($d['rows'], [['', '']]) as $r) : ?>
          <tr><td><input type="text" name="mem_rows_name[]" value="<?php echo esc_attr($r[0] ?? ''); ?>" style="width:100%"></td>
              <td><input type="number" min="0" name="mem_rows_price[]" value="<?php echo esc_attr($r[1] ?? ''); ?>" style="width:100%"></td>
              <td><button type="button" class="button-link mem-row-del" aria-label="Удалить">×</button></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p><button type="button" class="button" id="mem-row-add">+ Строка</button>
       <span style="margin-left:1em">Итого: <b id="mem-rows-total"><?php echo esc_html(mem_rub($d['total'])); ?></b></span></p>
    <p class="description">Текст в редакторе выше — короткое описание работы: что хотели заказчики, что сделали.</p>
    <p><label><input type="checkbox" name="mem_example" value="1"<?php checked((bool) get_post_meta($post->ID, '_mem_example', true)); ?>>
       <strong>Показывать в примерах</strong> — в карточках «Примеры наших работ» ([mem_works vid="карточки"]) на других страницах</label></p>
    <?php
}

add_action('save_post_mem_work', function ($id) {
    if (!isset($_POST['mem_work_nonce']) || !wp_verify_nonce($_POST['mem_work_nonce'], 'mem_work')) return;
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $id)) return;
    $photos = array_filter(array_map('absint', explode(',', (string) ($_POST['mem_photos'] ?? ''))));
    update_post_meta($id, '_mem_photos', implode(',', $photos));
    $photos ? set_post_thumbnail($id, reset($photos)) : delete_post_thumbnail($id);
    update_post_meta($id, '_mem_size', sanitize_text_field(wp_unslash($_POST['mem_size'] ?? '')));
    $y = absint($_POST['mem_year'] ?? 0);
    $y >= 1990 && $y <= (int) gmdate('Y') + 1 ? update_post_meta($id, '_mem_year', $y) : delete_post_meta($id, '_mem_year');
    $rows = [];
    foreach ((array) ($_POST['mem_rows_name'] ?? []) as $i => $n) {
        $n = sanitize_text_field(wp_unslash($n));
        $p = absint($_POST['mem_rows_price'][$i] ?? 0);
        if ($n !== '' || $p) $rows[] = [$n, $p];
    }
    update_post_meta($id, '_mem_rows', $rows);
    !empty($_POST['mem_example']) ? update_post_meta($id, '_mem_example', 1) : delete_post_meta($id, '_mem_example');
});

/* Колонки списка */
add_filter('manage_mem_work_posts_columns', function ($c) {
    $n = [];
    foreach ($c as $k => $v) {
        if ($k === 'title') $n['mem_thumb'] = 'Фото';
        $n[$k] = $v;
        if ($k === 'title') { $n['mem_year'] = 'Год'; $n['mem_size'] = 'Размер'; $n['mem_total'] = 'Итого'; }
    }
    unset($n['date']);
    return $n;
});
add_action('manage_mem_work_posts_custom_column', function ($col, $id) {
    $d = mem_work_data($id);
    if ($col === 'mem_thumb' && $d['photos']) echo wp_get_attachment_image($d['photos'][0], [50, 50]);
    if ($col === 'mem_size') echo esc_html($d['size']);
    if ($col === 'mem_year') echo $d['year'] ? (int) $d['year'] : '<span style="color:#b32d2e">не указан</span>';
    if ($col === 'mem_total' && $d['total']) echo esc_html(mem_rub($d['total']));
}, 10, 2);

add_action('pre_get_posts', function ($q) {
    if (is_admin() && $q->is_main_query() && $q->get('post_type') === 'mem_work' && empty($_GET['orderby'])) {
        $q->set('orderby', ['menu_order' => 'ASC', 'date' => 'ASC']);
    }
});

add_action('admin_enqueue_scripts', function () {
    $s = get_current_screen();
    if (!$s || $s->post_type !== 'mem_work') return;
    $dir = MEM_CORE_DIR; $uri = MEM_CORE_URL;
    if ($s->base === 'post') wp_enqueue_media();
    wp_enqueue_script('mem-catalog-admin', $uri . '/assets/js/catalog-admin.js', ['jquery', 'jquery-ui-sortable'], filemtime($dir . '/assets/js/catalog-admin.js'), true);
    wp_localize_script('mem-catalog-admin', 'memCat', ['nonce' => wp_create_nonce('mem_sort'), 'offset' => 0, 'sortable' => $s->base === 'edit' && empty($_GET['orderby'])]);
    wp_add_inline_style('wp-admin', '.mem-photos{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0}.mem-photos li{position:relative;margin:0;cursor:move}.mem-photos img{width:90px;height:90px;object-fit:cover;border-radius:4px;display:block}'
        . '.mem-photos li:first-child img{outline:3px solid #b89b5e}.mem-photos button{position:absolute;top:2px;right:2px;border:0;border-radius:50%;width:22px;height:22px;background:#000a;color:#fff;cursor:pointer;line-height:1}'
        . '.column-mem_thumb{width:60px}.column-mem_year{width:80px}.column-mem_thumb img{width:50px;height:50px;object-fit:cover;border-radius:4px}.mem-sortable #the-list tr{cursor:move}');
});

/* ---------- Вывод: [mem_works] ---------- */
add_shortcode('mem_works', function ($atts) {
    $a = shortcode_atts(['type' => '', 'limit' => -1, 'sort' => '1', 'nav' => '1', 'vid' => '', 'chislo' => '', 'poryadok' => '',
                         'raboty' => '', 'razmer' => 'да', 'cena' => 'да'], $atts, 'mem_works');
    if (in_array(mb_strtolower(trim($a['vid'])), ['карточки', 'cards'], true)) return mem_works_cards($a);
    if (in_array(mb_strtolower(trim($a['vid'])), ['фото', 'photos'], true)) return function_exists('mem_works_photos') ? mem_works_photos($a) : '';
    $a['nav'] = mem_sc_no($a['nav']) ? '0' : '1';
    $a['sort'] = mem_sc_no($a['sort']) ? '0' : '1';
    $args = ['post_type' => 'mem_work', 'post_status' => 'publish', 'posts_per_page' => (int) $a['limit'] ?: -1,
             'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC']];
    if ($a['type'] !== '') $args['tax_query'] = [['taxonomy' => 'mem_work_type', 'field' => 'slug', 'terms' => mem_work_type_slugs($a['type'])]];
    $works = get_posts($args);
    if (!$works) return '';

    $types = [];
    $terms_of = fn($w) => ($t = get_the_terms($w, 'mem_work_type')) && !is_wp_error($t) ? $t : [];
    foreach ($works as $w) foreach ($terms_of($w) as $t) $types[$t->slug] = $t->name;
    // порядок кнопок — как в «Наши работы → Виды работ»
    uksort($types, function ($a, $b) {
        $ta = get_term_by('slug', $a, 'mem_work_type'); $tb = get_term_by('slug', $b, 'mem_work_type');
        return (int) get_term_meta($ta->term_id ?? 0, 'mem_order', true) <=> (int) get_term_meta($tb->term_id ?? 0, 'mem_order', true);
    });

    $has_year = (bool) array_filter($works, fn($w) => get_post_meta($w->ID, '_mem_year', true));

    ob_start();
    echo '<div class="works" data-works>';
    $show_nav = $a['nav'] !== '0' && count($types) > 1 && $a['type'] === '';
    if ($show_nav || ($a['sort'] !== '0' && count($works) > 1)) {
        echo '<span class="mcat__pin" aria-hidden="true"></span><div class="mcat__bar">';
        if ($show_nav) {
            echo '<div class="mcat__navwrap"><nav class="mcat__nav" aria-label="Виды работ"><button type="button" class="chip is-on" data-type="">Все</button>';
            foreach ($types as $slug => $name) echo '<button type="button" class="chip" data-type="' . esc_attr($slug) . '">' . esc_html($name) . '</button>';
            echo '</nav></div>';
        }
        if ($a['sort'] !== '0' && count($works) > 1) {
            echo '<label class="mcat__sort"><span class="mcat__sortlbl">Сортировка:</span><select data-works-sort>'
               . '<option value="">По умолчанию</option>'
               . ($has_year ? '<option value="new">Сначала новые</option>' : '')
               . '<option value="asc">Сначала дешевле</option><option value="desc">Сначала дороже</option></select></label>';
        }
        echo '</div>';
    }
    echo '<div class="works__list">';
    foreach ($works as $i => $w) {
        $d = mem_work_data($w->ID);
        $slugs = implode(' ', wp_list_pluck($terms_of($w), 'slug'));
        $title = get_the_title($w);
        $cap = $title . ($d['year'] ? ' · ' . $d['year'] . ' г.' : '');
        echo '<article class="work" id="' . esc_attr(mem_work_anchor($title)) . '" data-price="' . (int) $d['total'] . '" data-year="' . (int) $d['year'] . '" data-i="' . $i . '" data-types="' . esc_attr($slugs) . '">';
        if ($d['photos']) {
            $main = $d['photos'][0];
            $wbg = wp_get_attachment_image_url($main, 'thumbnail'); // размытый фон, если следующее фото другой формы
            // рамка — по форме главного фото: вертикальное фото и показывается вертикально, крупно (не уже 3:4, не шире 16:10)
            $war = mem_img_ratio($main) ?: 4 / 3;
            $war = max(.75, min(1.6, $war));
            echo '<div class="work__media"><div class="work__stage"><a class="work__img" href="' . esc_url(wp_get_attachment_image_url($main, 'large')) . '" data-lb data-cap="' . esc_attr($cap) . '"'
               . ' style="--war:' . mem_ratio_css($war) . ($wbg ? ';--wbg:url(&quot;' . esc_url($wbg) . '&quot;)' : '') . '">'
               . wp_get_attachment_image($main, 'large', false, ['loading' => 'lazy', 'sizes' => '(max-width:700px) 100vw, 460px', 'alt' => $title]);
            if ($d['year']) echo '<span class="work__year">' . (int) $d['year'] . '</span>';
            echo '</a>';
            if (count($d['photos']) > 1) {
                echo '<button type="button" class="work__arr work__arr--l" data-dir="-1" aria-label="Предыдущее фото">' . mem_chevron('l') . '</button>'
                   . '<button type="button" class="work__arr work__arr--r" data-dir="1" aria-label="Следующее фото">' . mem_chevron('r') . '</button>';
            }
            echo '</div>';
            if (count($d['photos']) > 1) {
                echo '<div class="work__thumbs">';
                foreach ($d['photos'] as $k => $p) {
                    echo '<a class="work__thumb' . ($k ? '' : ' is-on') . '" href="' . esc_url(wp_get_attachment_image_url($p, 'large')) . '" data-lb-item data-cap="' . esc_attr($cap) . '" aria-label="Фото ' . ($k + 1) . '">'
                       . wp_get_attachment_image($p, 'thumbnail', false, ['loading' => 'lazy', 'alt' => '']) . '</a>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '<div class="work__body"><h3>' . esc_html($title) . '</h3>';
        $facts = [];
        if ($d['year']) $facts[] = '<span><strong>Выполнено:</strong> ' . (int) $d['year'] . ' г.</span>';
        if ($d['size']) $facts[] = '<span><strong>Размер участка:</strong> ' . esc_html($d['size']) . '</span>';
        if ($facts) echo '<p class="work__size">' . implode('', $facts) . '</p>';
        if (trim($w->post_content) !== '') echo '<div class="work__text">' . wp_kses_post(wpautop($w->post_content)) . '</div>';
        if ($d['rows']) {
            echo '<figure class="wp-block-table is-style-price"><table><thead><tr><th>Что сделано</th><th>Стоимость</th></tr></thead><tbody>';
            foreach ($d['rows'] as $r) echo '<tr><td>' . esc_html($r[0]) . '</td><td>' . ($r[1] ? esc_html(mem_rub($r[1])) : '—') . '</td></tr>';
            echo '</tbody>';
            if (count($d['rows']) > 1 && $d['total']) echo '<tfoot><tr><td>Итого</td><td>' . esc_html(mem_rub($d['total'])) . '</td></tr></tfoot>';
            echo '</table></figure>';
            echo '<p class="work__note">' . ($d['year'] ? 'Цены ' . (int) $d['year'] . ' года. ' : '') . 'Сейчас стоимость может отличаться — посчитаем по телефону бесплатно.</p>';
        }
        echo '</div></article>';
    }
    echo '</div></div>';
    return ob_get_clean();
});

/** Виды работ из параметра type: адреса или названия через запятую → адреса */
function mem_work_type_slugs($v) {
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', (string) $v))) as $x) {
        $t = get_term_by('slug', sanitize_title($x), 'mem_work_type') ?: get_term_by('name', $x, 'mem_work_type');
        $out[] = $t ? $t->slug : $x;
    }
    return $out;
}

/** Адрес страницы со всеми работами ([mem_works] без vid="карточки") — туда ведут карточки */
function mem_works_page_url() {
    static $url = null;
    if ($url !== null) return $url;
    global $wpdb;
    $url = '';
    $rows = $wpdb->get_results("SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[mem\\_works%' ORDER BY menu_order, ID");
    foreach ($rows as $r) {
        if (preg_match_all('~\[mem_works(?:\s[^\]]*)?\]~u', $r->post_content, $m)) {
            foreach ($m[0] as $sc) if (!preg_match('~vid\s*=\s*["\'](карточки|cards)~u', $sc)) { $url = get_permalink($r->ID); break 2; }
        }
    }
    return $url;
}

/** «В примерах»: отмечается в «Наши работы» (меню «⋯» у работы) или в редакторе работы */
add_action('init', function () {
    register_post_meta('mem_work', '_mem_example', ['type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => false,
        'auth_callback' => fn() => current_user_can('edit_posts')]);
});

/** Карточки работ: [mem_works vid="карточки" …] */
function mem_works_cards($a) {
    $n = max(1, min(30, (int) ($a['chislo'] ?: 6)));
    $args = ['post_type' => 'mem_work', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC']];
    if (trim($a['type']) !== '') $args['tax_query'] = [['taxonomy' => 'mem_work_type', 'field' => 'slug', 'terms' => mem_work_type_slugs($a['type'])]];
    $works = get_posts($args);
    $works = array_values(array_filter($works, fn($w) => mem_work_data($w->ID)['photos'])); // карточка без фото не нужна
    if (trim($a['raboty']) !== '') { // выбранные по названию — в том порядке, как перечислены; кавычки любые («», "", „“)
        $norm = fn($t) => trim(preg_replace(['~[«»"„“”\'`]~u', '~\s*,\s*~u', '~\s+~u'], ['', ',', ' '], mb_strtolower(html_entity_decode((string) $t, ENT_QUOTES, 'UTF-8'))));
        $list = ',' . $norm($a['raboty']) . ',';
        $pick = [];
        foreach ($works as $w) {
            $pos = mb_strpos($list, ',' . $norm(get_the_title($w)) . ',');
            if ($pos !== false) $pick[$pos] = $w;
        }
        ksort($pick);
        $works = array_values($pick);
    } else {
        $marked = array_values(array_filter($works, fn($w) => (bool) get_post_meta($w->ID, '_mem_example', true)));
        if ($marked) $works = $marked;
    }
    if (!$works) {
        return current_user_can('edit_posts') ? '<p class="is-style-note">[mem_works vid="карточки"]: нет подходящих работ с фото — добавьте их в «Наши работы».</p>' : '';
    }
    $ord = mb_strtolower(trim($a['poryadok']));
    $rand = in_array($ord, ['случайно', 'случайные', 'random'], true);
    if ($rand) shuffle($works);
    elseif (in_array($ord, ['дешевле', 'дороже'], true)) usort($works, fn($x, $y) => (mem_work_data($x->ID)['total'] <=> mem_work_data($y->ID)['total']) * ($ord === 'дешевле' ? 1 : -1));
    elseif ($ord === 'новые') usort($works, fn($x, $y) => (int) mem_work_data($y->ID)['year'] <=> (int) mem_work_data($x->ID)['year']);
    // случайные: на страницу попадают все (до 30), браузер показывает N случайных — разные при каждом открытии, даже из кэша
    if (!$rand) $works = array_slice($works, 0, $n);
    $base = mem_works_page_url();
    // общие пропорции карточек — как у большинства фото (фото другой формы показывается целиком, с полями)
    $ar = mem_common_ratio(array_map(fn($w) => mem_work_data($w->ID)['photos'][0], array_slice($works, 0, 30)));
    $h = '<div class="mem-wcards"' . ($ar ? ' style="--mem-ar:' . mem_ratio_css($ar) . '"' : '') . ($rand ? ' data-wcards-rand="' . $n . '"' : '') . '>';
    foreach (array_slice($works, 0, 30) as $i => $w) {
        $d = mem_work_data($w->ID);
        $title = get_the_title($w);
        $sub = [];
        if (!mem_sc_no($a['razmer']) && $d['size'] !== '') $sub[] = '<span class="nowrap">участок ' . esc_html($d['size']) . '</span>';
        if (!mem_sc_no($a['cena']) && $d['total']) $sub[] = '<span class="nowrap">' . esc_html(mem_rub($d['total'])) . '</span>';
        $href = $base ? $base . '#' . mem_work_anchor($title) : ''; // тот же якорь, что у работы в общем списке
        $img = wp_get_attachment_image($d['photos'][0], 'medium_large', false, ['class' => 'mem-wcard__img', 'loading' => 'lazy', 'decoding' => 'async',
                                       'alt' => wp_strip_all_tags($title), 'sizes' => '(max-width:600px) 50vw, 300px']);
        $inner = '<span class="mem-wcard__ph">' . $img . '</span><span class="mem-wcard__cap"><strong>' . esc_html($title) . '</strong>' . implode(' — ', $sub) . '</span>';
        $h .= ($href ? '<a class="mem-wcard" href="' . esc_url($href) . '"' : '<div class="mem-wcard"') . ($i >= $n ? ' hidden' : '') . '>' . $inner . ($href ? '</a>' : '</div>');
    }
    return $h . '</div>';
}

/* Разово: галереи-карточки, сделанные вручную и ведущие на работы (ссылки «#rabota-…»), заменяются на
   [mem_works vid="карточки"] — теперь карточки собираются из «Наших работ» и не устаревают.
   Прежний текст страницы остаётся в её «Редакциях». */
add_action('admin_init', function () {
    if (get_option('mem_wcards_migrated') || !current_user_can('edit_pages')) return;
    update_option('mem_wcards_migrated', 1, false);
    $pages = get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => -1]);
    $done = [];
    foreach ($pages as $p) {
        if (strpos($p->post_content, '#rabota-') === false) continue;
        $c = preg_replace_callback('~<!-- wp:gallery\b[^>]*-->.*?<!-- /wp:gallery -->~s', function ($m) {
            if (strpos($m[0], '#rabota-') === false) return $m[0];
            $n = max(1, substr_count($m[0], '<!-- wp:image'));
            return "<!-- wp:shortcode -->\n[mem_works vid=\"карточки\" chislo=\"$n\" poryadok=\"случайно\"]\n<!-- /wp:shortcode -->";
        }, $p->post_content);
        if ($c !== null && $c !== $p->post_content) {
            wp_update_post(['ID' => $p->ID, 'post_content' => wp_slash($c)]);
            $done[] = get_the_title($p);
        }
    }
    if ($done) set_transient('mem_wcards_report', $done, DAY_IN_SECONDS);
});
add_action('admin_notices', function () {
    if (!($done = get_transient('mem_wcards_report')) || !current_user_can('edit_pages')) return;
    delete_transient('mem_wcards_report');
    echo '<div class="notice notice-success is-dismissible"><p><b>Примеры работ:</b> карточки на страницах «' . esc_html(implode('», «', $done))
       . '» теперь собираются сами из «Наших работ» (шорткод [mem_works vid="карточки"]). Какие работы показывать — отметьте «Показывать в примерах» в «Наши работы» (меню «⋯» у работы); без отметок показываются случайные из всех. Прежний вариант — в «Редакциях» страницы.</p></div>';
});
