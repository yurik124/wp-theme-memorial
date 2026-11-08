<?php
/**
 * «Полезно знать»: [mem_guide] — карточки вложенных страниц с поиском.
 * Каждая карточка — вопрос, на который отвечает страница, короткий ответ и разделы внутри («В статье»).
 * Поиск находит и сами страницы, и разделы, и вопросы-ответы внутри них (и со всего сайта, vse="да"),
 * подсказка ведёт сразу к нужному месту страницы.
 *
 *   [mem_guide]                        — страницы, вложенные в эту («Атрибуты страницы → Родительская»)
 *   [mem_guide roditel="12"]           — вложенные в другую страницу
 *   [mem_guide stranicy="12, 34, 56"]  — эти страницы в этом порядке
 *   [mem_guide vse="нет"]              — искать только по этим страницам, без вопросов с остального сайта
 *
 * Вопрос и короткий ответ задаются у каждой страницы в редакторе — поле «Полезно знать» справа/внизу.
 * Пусто — берутся название страницы и её первый абзац.
 */
defined('ABSPATH') || exit;

/* ---------- Поле в редакторе страницы ---------- */
add_action('init', function () {
    foreach (['_mem_q', '_mem_qa'] as $k) {
        register_post_meta('page', $k, ['type' => 'string', 'single' => true, 'show_in_rest' => false,
                                        'auth_callback' => fn() => current_user_can('edit_pages')]);
    }
});
add_action('add_meta_boxes_page', function () {
    add_meta_box('mem-guide-q', 'Полезно знать: какой вопрос решает страница', function ($post) {
        wp_nonce_field('mem_guide_q', 'mem_guide_q_nonce');
        echo '<p><label for="mem-q"><b>Вопрос</b> — как его задал бы человек</label>'
           . '<input type="text" id="mem-q" name="mem_q" class="widefat" value="' . esc_attr(get_post_meta($post->ID, '_mem_q', true)) . '" placeholder="Например: Как получить пособие на погребение?"></p>'
           . '<p><label for="mem-qa"><b>Короткий ответ</b> — 1–2 предложения</label>'
           . '<textarea id="mem-qa" name="mem_qa" class="widefat" rows="3" placeholder="Например: Пособие получает тот, кто организовал похороны. Нужны свидетельство о смерти и паспорт.">'
           . esc_textarea(get_post_meta($post->ID, '_mem_qa', true)) . '</textarea></p>'
           . '<p class="description">Показывается на карточке страницы в «Полезно знать» и в поиске. Пусто — название страницы и первый абзац.</p>';
    }, 'page', 'normal', 'default');
});
add_action('save_post_page', function ($id) {
    if (!isset($_POST['mem_guide_q_nonce']) || !wp_verify_nonce($_POST['mem_guide_q_nonce'], 'mem_guide_q') || !current_user_can('edit_page', $id)) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    update_post_meta($id, '_mem_q', sanitize_text_field(wp_unslash($_POST['mem_q'] ?? '')));
    update_post_meta($id, '_mem_qa', sanitize_textarea_field(wp_unslash($_POST['mem_qa'] ?? '')));
});

/* ---------- Данные страницы для карточки ---------- */

/** Первый абзац (лучше — «вводный» is-style-lead) обычным текстом */
function mem_guide_lead($content) {
    $p = '';
    if (preg_match('~<p class="[^"]*is-style-lead[^"]*">(.*?)</p>~su', $content, $m)) $p = $m[1];
    elseif (preg_match('~<p\b[^>]*>(.*?)</p>~su', $content, $m)) $p = $m[1];
    $p = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes($p))));
    return mb_strlen($p) > 200 ? rtrim(mb_substr($p, 0, mb_strrpos(mb_substr($p, 0, 200), ' ') ?: 200), ' ,.;:—') . '…' : $p;
}

/** Разделы (заголовки h2) и вопросы (блоки «Подробности») страницы: [[текст, якорь, тип, ответ]] */
function mem_guide_parts($blocks, array &$out) {
    foreach ($blocks as $b) {
        if ($b['blockName'] === 'core/heading' && (int) ($b['attrs']['level'] ?? 2) === 2) {
            $t = trim(html_entity_decode(wp_strip_all_tags($b['innerHTML']), ENT_QUOTES, 'UTF-8'));
            $anchor = $b['attrs']['anchor'] ?? '';
            if (!$anchor && preg_match('/\sid="([^"]+)"/', $b['innerHTML'], $m)) $anchor = $m[1];
            if (!$anchor) $anchor = substr(mem_slug($t), 0, 70);
            if ($t !== '' && !preg_match('~^(частые вопросы|вопросы и ответы|остались вопросы)~ui', $t)) $out[] = [$t, $anchor, 'h', ''];
        } elseif ($b['blockName'] === 'core/details' && preg_match('~<summary[^>]*>(.*?)</summary>~s', $b['innerHTML'], $m)) {
            $q = trim(html_entity_decode(wp_strip_all_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            $a = '';
            foreach ($b['innerBlocks'] as $ib) $a .= ' ' . wp_strip_all_tags($ib['innerHTML']);
            if ($q !== '') $out[] = [$q, mem_q_anchor($q), 'q', trim(preg_replace('/\s+/u', ' ', $a))];
        } elseif (!empty($b['innerBlocks'])) {
            mem_guide_parts($b['innerBlocks'], $out);
        }
    }
}

function mem_guide_page_data($p) {
    $parts = [];
    mem_guide_parts(parse_blocks($p->post_content), $parts);
    $q = trim((string) get_post_meta($p->ID, '_mem_q', true)) ?: get_the_title($p);
    $qa = trim((string) get_post_meta($p->ID, '_mem_qa', true)) ?: mem_guide_lead($p->post_content);
    return ['id' => $p->ID, 'title' => get_the_title($p), 'q' => $q, 'qa' => $qa, 'url' => get_permalink($p), 'parts' => $parts,
            'img' => (int) get_post_thumbnail_id($p)];
}

/* ---------- [mem_guide] ---------- */
add_shortcode('mem_guide', function ($atts) {
    $a = shortcode_atts(['roditel' => '', 'stranicy' => '', 'vse' => 'да', 'chasto' => ''], $atts, 'mem_guide');
    if (trim($a['stranicy']) !== '') {
        $ids = array_filter(array_map('absint', explode(',', $a['stranicy'])));
        $pages = $ids ? get_posts(['post_type' => 'page', 'post_status' => 'publish', 'post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => -1]) : [];
    } else {
        $parent = $a['roditel'] !== '' ? (ctype_digit(trim($a['roditel'])) ? (int) $a['roditel'] : (int) (get_page_by_path(trim($a['roditel']))->ID ?? 0)) : (int) get_the_ID();
        $pages = $parent ? get_posts(['post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent, 'posts_per_page' => -1,
                                      'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']]) : [];
    }
    $pages = array_filter($pages, fn($p) => !post_password_required($p));
    if (!$pages) return current_user_can('edit_pages')
        ? '<p class="is-style-note">[mem_guide]: внутри этой страницы пока нет опубликованных страниц. Откройте нужную страницу → «Атрибуты страницы» → «Родительская» → выберите эту страницу.</p>' : '';

    $cards = '';
    $idx = [];          // поиск: q — что показать, t — где искать, u — ссылка, c — номер карточки, g — подпись
    $seen = [];
    foreach (array_values($pages) as $i => $p) {
        $d = mem_guide_page_data($p);
        $seen[$p->ID] = true;
        $idx[] = ['q' => $d['q'], 't' => $d['q'] . ' ' . $d['title'] . ' ' . $d['qa'], 'u' => $d['url'], 'c' => $i, 'g' => 'Статья', 'k' => 'p'];
        $heads = array_values(array_filter($d['parts'], fn($x) => $x[2] === 'h'));
        $qs = array_values(array_filter($d['parts'], fn($x) => $x[2] === 'q'));
        foreach ($d['parts'] as [$t, $anc, $k, $ans]) {
            $idx[] = ['q' => $t, 't' => $t . ' ' . mb_substr($ans, 0, 300), 'u' => $d['url'] . ($anc ? '#' . $anc : ''), 'c' => $i, 'g' => $d['title'], 'k' => $k];
        }
        $in = array_slice($heads, 0, 4);
        $cards .= '<article class="mguide__card" data-card="' . $i . '">'
            . '<h2 class="mguide__q"><a href="' . esc_url($d['url']) . '">' . esc_html($d['q']) . '</a></h2>'
            . ($d['qa'] ? '<p class="mguide__a">' . esc_html($d['qa']) . '</p>' : '')
            . ($in ? '<p class="mguide__in-t">В статье:</p><ul class="mguide__in">' . implode('', array_map(fn($h) => '<li><a href="' . esc_url($d['url'] . '#' . $h[1]) . '">' . esc_html($h[0]) . '</a></li>', $in)) . '</ul>' : '')
            . '<p class="mguide__foot"><a class="mguide__go" href="' . esc_url($d['url']) . '">Читать ответ <span aria-hidden="true">→</span></a>'
            . ($qs ? '<span class="mguide__cnt">' . count($qs) . ' ' . mem_word(count($qs), 'вопрос', 'вопроса', 'вопросов') . ' с ответами</span>' : '') . '</p>'
            . '</article>';
    }
    // вопросы с остальных страниц сайта — тоже находятся поиском
    if (!mem_sc_no($a['vse']) && function_exists('mem_faq_all')) {
        foreach (mem_faq_all() as $g) {
            if (isset($seen[$g['id']]) || $g['id'] === get_the_ID()) continue;
            foreach ($g['qs'] as [$q, $ans]) {
                $idx[] = ['q' => $q, 't' => $q . ' ' . mb_substr(trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($ans))), 0, 300),
                          'u' => $g['url'] . '#' . mem_q_anchor($q), 'c' => -1, 'g' => $g['title'], 'k' => 'q'];
            }
        }
    }
    $pop = trim($a['chasto']) !== '' ? explode(',', $a['chasto']) : ['документы', 'пособие', 'кладбище', 'стоимость', 'морг'];
    return '<div class="mguide" data-guide>'
        . mem_search_box(['id' => 'mguide-q', 'label' => 'Поиск по статьям', 'placeholder' => 'Например: справка о смерти', 'popular' => $pop,
                          'none' => 'Ответа на такой вопрос пока нет на сайте. Позвоните или оставьте номер — подскажем.'])
        . '<script type="application/json" data-guide-idx>' . wp_json_encode($idx, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'
        . '<div class="mguide__grid">' . $cards . '</div></div>';
});

/* Разметка для поисковиков: список статей */
add_action('wp_head', function () {
    if (!is_page() || !function_exists('mem_ld')) return;
    $p = get_queried_object();
    if (!$p || !has_shortcode((string) $p->post_content, 'mem_guide')) return;
    $kids = get_posts(['post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $p->ID, 'posts_per_page' => -1, 'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']]);
    if (!$kids) return;
    $list = [];
    foreach ($kids as $i => $k) $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => get_permalink($k), 'name' => get_the_title($k)];
    mem_ld(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => get_the_title($p), 'itemListElement' => $list]);
}, 7);

/* ---------- Разово: страница «Полезно знать» (черновик) и вопросы у известных страниц ---------- */
add_action('admin_init', function () {
    if (get_option('mem_guide_setup') || !current_user_can('edit_pages')) return;
    update_option('mem_guide_setup', 1, false);
    $known = [
        '~что делать~ui'           => ['Что делать, если умер близкий человек?', 'Куда звонить в первые часы, какие документы получить и в каком порядке — по шагам.'],
        '~пособ~ui'                => ['Как получить пособие на погребение?', 'Кто может получить пособие, какие документы нужны и куда обращаться.'],
        '~кладбищ~ui'              => ['На каком кладбище можно похоронить?', 'Кладбища Белгорода и района: где есть места, где только подзахоронение, как оформить.'],
        '~вопрос~ui'               => ['Ответы на частые вопросы о похоронах', 'Документы, стоимость, сроки, кремация — собрали ответы со всего сайта.'],
        '~прижизн~ui'              => ['Как заранее договориться о своих похоронах?', 'Что такое прижизненный договор, что в него входит и как его заключить.'],
    ];
    foreach (get_pages(['post_status' => 'publish,draft,private']) as $p) {
        if (trim((string) get_post_meta($p->ID, '_mem_q', true)) !== '') continue;
        foreach ($known as $re => [$q, $qa]) {
            if (!preg_match($re, $p->post_title)) continue;
            if ($re === '~кладбищ~ui' && !has_shortcode($p->post_content, 'mem_cemeteries')) continue;   // не «Укладка плитки… на кладбище»
            update_post_meta($p->ID, '_mem_q', $q);
            update_post_meta($p->ID, '_mem_qa', $qa);
            break;
        }
    }
    foreach (get_pages(['post_status' => 'publish,draft,private']) as $p) if (has_shortcode($p->post_content, 'mem_guide')) return;
    $c = "<!-- wp:paragraph {\"className\":\"is-style-lead\"} -->\n<p class=\"is-style-lead\">Ответы на вопросы, которые возникают, когда умирает близкий человек: что делать в первые часы, какие нужны документы, какие выплаты положены, где можно похоронить. Начните вводить вопрос — поиск подскажет нужную статью.</p>\n<!-- /wp:paragraph -->\n\n"
       . "<!-- wp:shortcode -->\n[mem_guide]\n<!-- /wp:shortcode -->\n\n"
       . "<!-- wp:shortcode -->\n[mem_cta title=\"Не нашли ответ?\" text=\"Позвоните — подскажем, что делать, в любое время.\"]\n<!-- /wp:shortcode -->";
    $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Полезно знать', 'post_name' => 'poleznoe', 'post_content' => $c]);
    if ($id && !is_wp_error($id)) {
        $r = (array) get_transient('mem_places_report');
        $r[] = '<a href="' . esc_url(get_edit_post_link($id)) . '">«Полезно знать»</a> — черновик с поиском. Чтобы статья появилась на ней: откройте статью → «Атрибуты страницы» → «Родительская» → «Полезно знать». '
             . 'Вопрос и короткий ответ на карточке — поле «Полезно знать» под текстом статьи.';
        set_transient('mem_places_report', array_filter($r), DAY_IN_SECONDS);
    }
});
