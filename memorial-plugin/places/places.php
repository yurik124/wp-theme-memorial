<?php
/**
 * Места, которые заполняются в админке:
 *   «Мемориал → Залы для поминок» → [mem_restaurants] — блок на странице «Поминальный обед»;
 *   «Мемориал → Кладбища»         → [mem_cemeteries]  — список кладбищ; ставится на любую страницу
 *     (страница «Кладбища» создаётся черновиком, ссылки на неё — обычные ссылки в тексте).
 *
 * Залы — без телефонов и сайтов ресторанов: заказ идёт через нас (кнопка «Перезвоните мне»),
 * поэтому блок помогает выбрать, а не уводит посетителя к ресторану.
 * Пустой список — блок не выводится (редактору видна подсказка).
 */
defined('ABSPATH') || exit;

/* =========================================================
 * Настройки
 * ======================================================= */

function memorial_plugin_rest_schema() {
    return [
        'block' => ['type' => 'group', 'label' => 'Блок на странице', 'fields' => [
            'title' => ['type' => 'text', 'label' => 'Заголовок'],
            'text'  => ['type' => 'textarea', 'label' => 'Текст под заголовком', 'rows' => 2],
            'btn'   => ['type' => 'text', 'label' => 'Надпись на кнопке у зала', 'placeholder' => 'Заказать обед здесь'],
        ]],
        'items' => ['type' => 'repeater', 'label' => 'Залы и рестораны', 'add' => 'Добавить зал', 'collapse' => 'name', 'fields' => [
            'name'  => ['type' => 'text', 'label' => 'Название', 'placeholder' => 'Кафе «…»'],
            'img'   => ['type' => 'image', 'label' => 'Фото зала'],
            'addr'  => ['type' => 'text', 'label' => 'Адрес или район', 'placeholder' => 'ул. …, рядом с кладбищем …'],
            'seats' => ['type' => 'number', 'label' => 'Сколько гостей вмещает (до)', 'placeholder' => '60'],
            'price' => ['type' => 'text', 'label' => 'Цена', 'placeholder' => 'от 900 ₽ на человека'],
            'feats' => ['type' => 'text', 'label' => 'Особенности — через запятую', 'placeholder' => 'парковка, отдельный зал, постное меню'],
            'text'  => ['type' => 'textarea', 'label' => 'Коротко о зале (необязательно)', 'rows' => 2],
        ]],
    ];
}
function memorial_plugin_rest_defaults() {
    return [
        'block' => ['title' => 'Где провести поминальный обед', 'text' => 'Залы, с которыми мы работаем. Договоримся о дате, меню и числе гостей за вас — стоимость та же, что в самом зале.',
                    'btn' => 'Заказать обед здесь'],
        'items' => [],
    ];
}

function memorial_plugin_cem_schema() {
    return [
        'items' => ['type' => 'repeater', 'label' => 'Кладбища', 'add' => 'Добавить кладбище', 'collapse' => 'name', 'fields' => [
            'name'   => ['type' => 'text', 'label' => 'Название', 'placeholder' => 'Северное кладбище'],
            'status' => ['type' => 'select', 'label' => 'Захоронения', 'options' => [
                'open'   => 'Действующее — новые захоронения',
                'family' => 'Только подзахоронение в родственную могилу',
                'closed' => 'Закрыто для захоронений',
            ]],
            'img'    => ['type' => 'image', 'label' => 'Фото (необязательно)'],
            'addr'   => ['type' => 'text', 'label' => 'Адрес', 'placeholder' => 'Белгородский район, с. …'],
            'dist'   => ['type' => 'text', 'label' => 'Как далеко (необязательно)', 'placeholder' => '12 км от центра Белгорода'],
            'hours'  => ['type' => 'text', 'label' => 'Время работы (необязательно)', 'placeholder' => 'Ежедневно с 8:00 до 17:00'],
            'text'   => ['type' => 'textarea', 'label' => 'Подробнее: как оформить захоронение, что учесть', 'rows' => 4, 'html' => true],
            'map'    => ['type' => 'map', 'label' => 'Карта (необязательно)', 'help' => 'Ссылка с Яндекс Карт. Пусто — карта по адресу.'],
        ]],
    ];
}
function memorial_plugin_cem_defaults() {
    return [
        'items' => [],
    ];
}

add_filter('memorial_plugin_admin_pages', function ($pages) {
    $pages['mem_rest'] = ['slug' => 'mem-rest', 'title' => 'Залы для поминок', 'schema' => 'memorial_plugin_rest_schema',
        'intro' => 'Залы и рестораны для поминального обеда. Блок выводится шорткодом <code>[mem_restaurants]</code> (он уже стоит на странице «Поминальный обед»). '
                 . 'Телефоны и сайты ресторанов на сайт не выводятся — посетитель заказывает через вас кнопкой «Перезвоните мне».'];
    $pages['mem_cem'] = ['slug' => 'mem-cem', 'title' => 'Кладбища', 'schema' => 'memorial_plugin_cem_schema',
        'intro' => 'Список кладбищ выводится шорткодом <code>[mem_cemeteries]</code> на любой странице (например, «Кладбища Белгорода и района»). '
                 . 'Только действующие: <code>[mem_cemeteries status="действующие"]</code>, без кнопок-фильтров: <code>filtr="нет"</code>. '
                 . 'Порядок — перетаскиванием за ⋮⋮.'];
    return $pages;
});

/** Фото по адресу из настроек: <img> с размерами и srcset (если картинка из медиатеки) */
function memorial_plugin_place_img($url, $alt, $sizes) {
    if (!$url) return '';
    $id = memorial_plugin_attachment_id_by_url($url);
    if ($id) return wp_get_attachment_image($id, 'medium_large', false, ['alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => $sizes]);
    return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy" decoding="async">';
}

/* =========================================================
 * [mem_restaurants]
 * ======================================================= */
add_shortcode('mem_restaurants', function ($atts) {
    $o = memorial_plugin_settings('mem_rest');
    $a = shortcode_atts(['title' => $o['block']['title'], 'text' => $o['block']['text']], $atts, 'mem_restaurants');
    $items = array_values(array_filter((array) $o['items'], fn($r) => trim($r['name'] ?? '') !== ''));
    if (!$items) return current_user_can('edit_pages') ? '<p class="is-style-note">[mem_restaurants]: залы не добавлены — «Мемориал → Залы для поминок». Посетители этот блок не видят.</p>' : '';
    $btn = trim($o['block']['btn'] ?? '') ?: 'Заказать обед здесь';
    $h = '<section class="mem-places mem-rest" id="zaly">';
    if ($a['title']) $h .= '<h2>' . esc_html($a['title']) . '</h2>';
    if ($a['text'])  $h .= '<p class="mem-places__lead">' . esc_html($a['text']) . '</p>';
    $h .= '<div class="mem-places__grid">';
    foreach ($items as $r) {
        $name = trim($r['name']);
        $img = memorial_plugin_place_img($r['img'] ?? '', $name, '(max-width:600px) 100vw, 33vw');
        $meta = [];
        if (!empty($r['seats'])) $meta[] = '<span>до ' . (int) $r['seats'] . ' ' . memorial_plugin_word((int) $r['seats'], 'гостя', 'гостей', 'гостей') . '</span>';
        if (trim($r['price'] ?? '')) $meta[] = '<span class="mem-place__price">' . esc_html(trim($r['price'])) . '</span>';
        $feats = array_filter(array_map('trim', explode(',', (string) ($r['feats'] ?? ''))));
        $h .= '<article class="mem-place">'
            . ($img ? '<div class="mem-place__img">' . $img . '</div>' : '')
            . '<div class="mem-place__body"><h3 class="mem-place__name">' . esc_html($name) . '</h3>'
            . (trim($r['addr'] ?? '') ? '<p class="mem-place__addr">' . memorial_plugin_icon('pin') . esc_html(trim($r['addr'])) . '</p>' : '')
            . ($meta ? '<p class="mem-place__meta">' . implode('', $meta) . '</p>' : '')
            . (trim($r['text'] ?? '') ? '<p class="mem-place__text">' . esc_html(trim($r['text'])) . '</p>' : '')
            . ($feats ? '<ul class="mem-place__tags">' . implode('', array_map(fn($f) => '<li>' . esc_html($f) . '</li>', $feats)) . '</ul>' : '')
            . '<button class="btn btn--ghost mem-place__btn" type="button" data-callback>' . esc_html($btn) . '</button>'
            . '</div></article>';
    }
    return $h . '</div></section>';
});

/* =========================================================
 * [mem_cemeteries]
 * ======================================================= */
function memorial_plugin_cem_status_names() {
    return ['open' => 'Действующее', 'family' => 'Подзахоронение', 'closed' => 'Закрыто'];
}

function memorial_plugin_cem_items() {
    return array_values(array_filter((array) (memorial_plugin_settings('mem_cem')['items'] ?? []), fn($r) => trim($r['name'] ?? '') !== ''));
}

add_shortcode('mem_cemeteries', function ($atts) {
    $a = shortcode_atts(['status' => '', 'filtr' => 'да'], $atts, 'mem_cemeteries');
    $items = memorial_plugin_cem_items();
    $want = [];   // status="действующие, подзахоронение"
    foreach (array_filter(array_map('trim', explode(',', mb_strtolower($a['status'])))) as $w) {
        if (preg_match('~^(действ|откр|open)~u', $w)) $want[] = 'open';
        elseif (preg_match('~^(подзах|родств|family)~u', $w)) $want[] = 'family';
        elseif (preg_match('~^(закр|closed)~u', $w)) $want[] = 'closed';
    }
    if ($want) $items = array_values(array_filter($items, fn($r) => in_array($r['status'] ?? 'open', $want, true)));
    if (!$items) return current_user_can('edit_pages') ? '<p class="is-style-note">[mem_cemeteries]: кладбища не добавлены — «Мемориал → Кладбища». Посетители этот блок не видят.</p>' : '';
    $names = memorial_plugin_cem_status_names();
    $cnt = array_count_values(array_map(fn($r) => $r['status'] ?? 'open', $items));
    $h = '<div class="mem-cem" data-cem>';
    if (count($cnt) > 1 && !memorial_plugin_sc_no($a['filtr'])) {
        $h .= '<div class="srow"><div class="srow__in" role="group" aria-label="Какие кладбища показать">'
            . '<button type="button" class="chip is-on" data-cem-f="" aria-pressed="true">Все <small>' . count($items) . '</small></button>';
        foreach ($names as $k => $n) if (!empty($cnt[$k])) $h .= '<button type="button" class="chip" data-cem-f="' . esc_attr($k) . '" aria-pressed="false">' . esc_html($n) . ' <small>' . (int) $cnt[$k] . '</small></button>';
        $h .= '</div></div>';
    }
    $h .= '<div class="mem-places__grid">';
    $used = [];
    foreach ($items as $r) {
        $name = trim($r['name']);
        $st = $r['status'] ?? 'open';
        $slug = sanitize_title($name) ?: 'cem';
        $slug = isset($used[$slug]) ? $slug . '-' . (++$used[$slug]) : $slug;
        $used[$slug] = $used[$slug] ?? 1;
        $img = memorial_plugin_place_img($r['img'] ?? '', $name, '(max-width:600px) 100vw, 33vw');
        $addr = trim($r['addr'] ?? '');
        $map = ($r['map'] ?? '') || $addr ? memorial_plugin_map_src($r['map'] ?? '', $addr) : '';
        $h .= '<article class="mem-place mem-cem__item" id="' . esc_attr($slug) . '" data-cem-s="' . esc_attr($st) . '">'
            . ($img ? '<div class="mem-place__img">' . $img . '</div>' : '')
            . '<div class="mem-place__body">'
            . '<p class="mem-cem__st mem-cem__st--' . esc_attr($st) . '">' . esc_html($names[$st] ?? '') . '</p>'
            . '<h2 class="mem-place__name">' . esc_html($name) . '</h2>'
            . ($addr ? '<p class="mem-place__addr">' . memorial_plugin_icon('pin') . esc_html($addr) . '</p>' : '')
            . (trim($r['dist'] ?? '') ? '<p class="mem-place__meta"><span>' . esc_html(trim($r['dist'])) . '</span></p>' : '')
            . (trim($r['hours'] ?? '') ? '<p class="mem-place__meta"><span>' . memorial_plugin_icon('clock') . esc_html(trim($r['hours'])) . '</span></p>' : '');
        if (trim(wp_strip_all_tags($r['text'] ?? '')) !== '' || $map) {
            $h .= '<details class="mem-cem__more"><summary>Подробнее' . ($map ? ' и карта' : '') . '</summary>'
                . (trim($r['text'] ?? '') ? '<div class="mem-cem__text">' . wpautop(wp_kses_post($r['text'])) . '</div>' : '')
                . ($map ? '<div class="mem-cem__map" data-map-src="' . esc_url($map) . '" data-map-title="' . esc_attr('Карта: ' . $name) . '"></div>' : '')
                . '</details>';
        }
        $h .= '</div></article>';
    }
    return $h . '</div></div>';
});

/* Разметка для поисковиков: список кладбищ (schema.org Cemetery) */
add_action('wp_head', function () {
    if (!is_page() || !function_exists('memorial_plugin_ld')) return;
    $p = get_queried_object();
    if (!$p || !memorial_plugin_has_content_block((string) $p->post_content, 'mem_cemeteries')) return;
    $list = [];
    foreach (memorial_plugin_cem_items() as $i => $r) {
        $c = ['@type' => 'Cemetery', 'name' => trim($r['name'])];
        if (trim($r['addr'] ?? '')) $c['address'] = trim($r['addr']);
        if (!empty($r['img'])) $c['image'] = $r['img'];
        if (trim($r['hours'] ?? '')) $c['openingHours'] = trim($r['hours']);
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $c];
    }
    if ($list) memorial_plugin_ld(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => get_the_title($p), 'itemListElement' => $list]);
}, 7);

/* =========================================================
 * Новые страницы (черновики) и блок залов — один раз, при заходе в админку
 * ======================================================= */
add_action('admin_init', function () {
    if (get_option('mem_places_setup') || !current_user_can('edit_pages')) return;
    update_option('mem_places_setup', 1, false);
    $made = [];
    $find = function ($words) {
        foreach (get_pages(['post_status' => 'publish,draft,private']) as $p) {
            foreach ((array) $words as $w) if (mb_stripos($p->post_title . ' ' . urldecode($p->post_name), $w) !== false) return $p;
        }
        return null;
    };

    // 1) Блок залов на странице «Поминальный обед»
    $pom = $find(['поминальн', 'pominal']);
    if ($pom && !memorial_plugin_has_content_block($pom->post_content, 'mem_restaurants')) {
        $c = $pom->post_content;
        $sc = "<!-- wp:memorial-plugin/restaurants /-->\n\n";
        $pos = false;
        foreach (['~<!-- wp:heading[^>]*-->\s*<h2[^>]*>\s*Поминальные традиции~u', '~<!-- wp:heading[^>]*-->\s*<h2[^>]*>\s*Частые вопросы~u', '~<!-- wp:shortcode -->\s*\[mem_cta~u', '~<!-- wp:memorial-plugin/cta[ /]~u'] as $re) {
            if (preg_match($re, $c, $m, PREG_OFFSET_CAPTURE)) { $pos = $m[0][1]; break; }
        }
        $c = $pos === false ? $c . "\n\n" . $sc : substr($c, 0, $pos) . $sc . substr($c, $pos);
        wp_update_post(['ID' => $pom->ID, 'post_content' => $c]);
        $made[] = '<a href="' . esc_url(get_edit_post_link($pom->ID)) . '">«' . esc_html($pom->post_title) . '»</a> — добавлен блок залов (сами залы — «Мемориал → Залы для поминок»)';
    }

    // 3) Страница «Прижизненный договор»
    if (!$find(['прижизненн', 'prizhiznenn'])) {
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Прижизненный договор на ритуальные услуги', 'post_name' => 'prizhiznennyj-dogovor',
                              'post_content' => memorial_plugin_places_tpl('dogovor')]);
        if ($id && !is_wp_error($id)) $made[] = '<a href="' . esc_url(get_edit_post_link($id)) . '">«Прижизненный договор на ритуальные услуги»</a> — черновик с текстом; проверьте условия и опубликуйте';
    }
    if ($made) set_transient('mem_places_report', $made, DAY_IN_SECONDS);
});

/* Страница «Кладбища Белгорода и района» — черновик, если ни на одной странице ещё нет [mem_cemeteries].
   (1.5.0 по ошибке принимал за неё любую страницу со словом «кладбище» в названии и добавлял ссылки-карточки — это убрано.) */
add_action('admin_init', function () {
    if (get_option('mem_places_fix1') || !current_user_can('edit_pages')) return;
    update_option('mem_places_fix1', 1, false);
    $o = get_option('mem_cem');
    if (is_array($o) && (isset($o['page']) || isset($o['links']))) { unset($o['page'], $o['links']); update_option('mem_cem', $o); }
    foreach (get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft', 'private', 'pending'], 'posts_per_page' => -1]) as $p) {
        if (memorial_plugin_has_content_block($p->post_content, 'mem_cemeteries')) return;
    }
    $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Кладбища Белгорода и района', 'post_name' => 'kladbishha',
                          'post_content' => memorial_plugin_places_tpl('cem')]);
    if ($id && !is_wp_error($id)) {
        $r = (array) get_transient('mem_places_report');
        $r[] = '<a href="' . esc_url(get_edit_post_link($id)) . '">«Кладбища Белгорода и района»</a> — черновик; добавьте кладбища в «Мемориал → Кладбища» и опубликуйте';
        set_transient('mem_places_report', array_filter($r), DAY_IN_SECONDS);
    }
});

add_action('admin_notices', function () {
    $r = get_transient('mem_places_report');
    if (!$r || !current_user_can('edit_pages')) return;
    delete_transient('mem_places_report');
    echo '<div class="notice notice-success is-dismissible"><p><b>Memorial Core:</b></p><ul style="list-style:disc;margin-left:2em">';
    foreach ($r as $line) echo '<li>' . wp_kses_post($line) . '</li>';
    echo '</ul></div>';
});

/** Шаблоны текстов новых страниц */
function memorial_plugin_places_tpl_raw($k) {
    $p   = fn($t, $cls = '') => '<!-- wp:paragraph' . ($cls ? ' {"className":"' . $cls . '"}' : '') . " -->\n<p" . ($cls ? ' class="' . $cls . '"' : '') . ">$t</p>\n<!-- /wp:paragraph -->\n\n";
    $h2  = fn($t) => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$t</h2>\n<!-- /wp:heading -->\n\n";
    $ul  = fn(array $li) => "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode('', array_map(fn($x) => "<!-- wp:list-item -->\n<li>$x</li>\n<!-- /wp:list-item -->", $li)) . "</ul>\n<!-- /wp:list -->\n\n";
    $ol  = fn(array $li) => "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">" . implode('', array_map(fn($x) => "<!-- wp:list-item -->\n<li>$x</li>\n<!-- /wp:list-item -->", $li)) . "</ol>\n<!-- /wp:list -->\n\n";
    $faq = fn($q, $a) => "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>$q</summary><!-- wp:paragraph -->\n<p>$a</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->\n\n";
    $sc  = fn($s) => "<!-- wp:shortcode -->\n$s\n<!-- /wp:shortcode -->\n\n";

    if ($k === 'cem') {
        return $p('Кладбища Белгорода и Белгородского района: адреса, время работы и где сейчас возможны новые захоронения. Поможем выбрать место и оформить документы на захоронение.', 'is-style-lead')
            . $sc('[mem_cemeteries]')
            . $h2('Как оформить захоронение')
            . $ol(['Получите медицинское свидетельство о смерти и гербовое свидетельство в ЗАГСе (или через МФЦ).',
                   'Выберите кладбище: новое место или подзахоронение в родственную могилу.',
                   'Для подзахоронения подготовьте документы на захоронение родственника (удостоверение о захоронении) и подтверждение родства.',
                   'Мы согласуем место и время с администрацией кладбища и подготовим могилу.'])
            . $h2('Частые вопросы')
            . $faq('Можно ли похоронить рядом с родственником?', 'Если кладбище открыто для подзахоронений и на участке есть место — да. Понадобятся документы на родственную могилу. Позвоните — проверим условия конкретного кладбища.')
            . $faq('Сколько стоит место на кладбище?', 'На муниципальных кладбищах место для захоронения предоставляется бесплатно. Платные — услуги: копка могилы, перевозка, установка креста или памятника.')
            . $faq('Вы работаете на всех кладбищах?', 'Да, организуем похороны на любом кладбище Белгорода и района, а также перевозку в другие города.')
            . $sc('[mem_cta title="Нужна помощь с выбором кладбища?" text="Подскажем, где есть места, и всё согласуем с администрацией."]');
    }
    // прижизненный договор
    return $p('Прижизненный договор — это заранее оформленная договорённость о ваших будущих похоронах: вы сами выбираете, как всё пройдёт, и фиксируете стоимость. Близким не придётся принимать решения и искать деньги в тяжёлый момент.', 'is-style-lead')
        . $h2('Зачем заключают прижизненный договор')
        . $ul(['<strong>Ваша воля будет исполнена</strong> — обряд, место захоронения, гроб, одежда, музыка, поминальный обед.',
               '<strong>Цена не вырастет</strong> — стоимость услуг по договору фиксируется на день заключения.',
               '<strong>Близким не нужно ничего решать</strong> — им достаточно позвонить нам, остальное мы сделаем по договору.',
               '<strong>Можно платить частями</strong> — порядок оплаты обсуждается при заключении договора.'])
        . $h2('Что можно включить в договор')
        . $ul(['Вид погребения: захоронение или кремация, кладбище, подзахоронение к родственникам.',
               'Ритуальные товары: гроб, венки, крест, одежда, принадлежности.',
               'Прощание: траурный зал, отпевание, транспорт для гостей.',
               'Поминальный обед, благоустройство могилы, памятник.'])
        . $h2('Как заключить договор')
        . $ol(['Позвоните или оставьте заявку — договоримся о встрече в удобное время, можем приехать на дом.',
               'Обсудим ваши пожелания и составим перечень услуг с ценами.',
               'Подпишем договор и согласуем порядок оплаты.',
               'Вы получите экземпляр договора. Расскажите близким, что договор есть и где он хранится.'])
        . $h2('Частые вопросы')
        . $faq('Можно ли изменить или расторгнуть договор?', 'Да. Пожелания можно изменить, договор — расторгнуть. Условия возврата оплаченных средств прописываются в самом договоре.')
        . $faq('Что делать родственникам, когда придёт время?', 'Позвонить нам по телефону, указанному в договоре. Дальше мы действуем по договору: оформим документы и организуем похороны так, как было решено.')
        . $faq('Можно заключить договор за родственника?', 'Договор заключается с согласия того, чьи похороны в нём описаны. Обсудим ваш случай по телефону.')
        . $faq('Учитывается ли пособие на погребение?', 'Да, социальное пособие на погребение может получить родственник, и его можно направить на оплату услуг. Подробнее — на странице о пособии.')
        . $sc('[mem_cta title="Хотите обсудить договор?" text="Расскажем об условиях и ответим на вопросы — бесплатно и без обязательств."]');
}

/** Заготовка страницы «Кладбища» / «Прижизненный договор». Шорткоды [mem_…] в заготовке сразу становятся блоками и значениями сайта (admin/convert.php). */
function memorial_plugin_places_tpl($k) {
    $content = memorial_plugin_places_tpl_raw($k);
    return function_exists('memorial_plugin_convert_content') ? memorial_plugin_convert_content($content) : $content;
}
