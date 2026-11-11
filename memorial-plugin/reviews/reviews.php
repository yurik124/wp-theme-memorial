<?php
/**
 * Страница «Отзывы»: шорткод [mem_reviews].
 * Источники отзывов (Яндекс Карты, 2ГИС, Google и любые другие, по филиалам) задаются в «Мемориал → Отзывы».
 * Виджеты сторонних сервисов загружаются после согласия на cookie или по кнопке.
 */
defined('ABSPATH') || exit;

function memorial_plugin_review_services() {
    return [
        'yandex' => __( 'Yandex Maps', 'memorial-plugin' ),
        '2gis'   => __( '2GIS', 'memorial-plugin' ),
        'google' => __( 'Google Maps', 'memorial-plugin' ),
        'avito'  => __( 'Avito', 'memorial-plugin' ),
        'other'  => __( 'Another service', 'memorial-plugin' ),
    ];
}

function memorial_plugin_reviews_schema() {
    return [
        'sources' => ['type' => 'repeater', 'label' => __( 'Where people write reviews about us', 'memorial-plugin' ), 'add' => __( 'Add a service or a branch', 'memorial-plugin' ), 'collapse' => 'branch', 'open' => true,
            'help' => __( 'A row for every service and branch. The order is changed with the ↑ ↓ arrows to the right of a row. Write the rating and the number of reviews as in the service itself — visitors see them.', 'memorial-plugin' ),
            'fields' => [
                'service' => ['type' => 'select', 'label' => __( 'Service', 'memorial-plugin' ), 'options' => memorial_plugin_review_services()],
                'name'    => ['type' => 'text', 'label' => __( 'Service name (only for “Another service”)', 'memorial-plugin' )],
                'branch'  => ['type' => 'text', 'label' => __( 'Branch', 'memorial-plugin' ), 'placeholder' => __( 'for example: 40 Studencheskaya St.', 'memorial-plugin' )],
                'link'    => ['type' => 'text', 'label' => __( 'Link to your card in the service', 'memorial-plugin' ), 'placeholder' => 'https://yandex.ru/maps/org/…/1234567890/',
                              'help' => __( 'For Yandex Maps the theme shows the review feed by the link itself. For 2GIS and others — the “Read reviews” and “Leave a review” buttons.', 'memorial-plugin' )],
                'rating'  => ['type' => 'text', 'label' => __( 'Rating (optional)', 'memorial-plugin' ), 'placeholder' => '4,9'],
                'count'   => ['type' => 'text', 'label' => __( 'Number of reviews (optional)', 'memorial-plugin' ), 'placeholder' => '120'],
                'embed'   => ['type' => 'code', 'label' => __( 'Widget code (optional)', 'memorial-plugin' ), 'rows' => 4,
                              'help' => __( 'If the service or a third-party review aggregator has a code for websites — paste it here whole. It is shown on the tab of this branch.', 'memorial-plugin' )],
            ]],
        'texts' => ['type' => 'group', 'label' => __( 'Texts on the page', 'memorial-plugin' ), 'fields' => [
            'lead' => ['type' => 'textarea', 'label' => __( 'Text above the reviews', 'memorial-plugin' ), 'rows' => 2],
            'ask'  => ['type' => 'textarea', 'label' => __( 'Request to leave a review (under the reviews)', 'memorial-plugin' ), 'rows' => 2],
        ]],
    ];
}

function memorial_plugin_reviews_defaults() {
    $org = function_exists('memorial_plugin_yandex_org') ? memorial_plugin_yandex_org() : '';
    $addr = function_exists('memorial_plugin_addrs') ? (memorial_plugin_addrs()[0]['addr'] ?? '') : '';
    return [
        'sources' => $org ? [['service' => 'yandex', 'name' => '', 'branch' => $addr ? memorial_plugin_short_addr($addr) : '', 'link' => 'https://yandex.ru/maps/org/' . $org . '/',
                              'rating' => '', 'count' => '', 'embed' => '']] : [],
        'texts' => [
            'lead' => 'Мы не убираем и не правим отзывы — здесь они показываются прямо из сервисов, где их оставили.',
            'ask'  => 'Если мы помогли вашей семье, напишите пару слов о нашей работе — это поможет другим людям в трудную минуту.',
        ],
    ];
}

add_filter('memorial_plugin_admin_pages', function ($pages) {
    $pages['mem_reviews'] = [
        'slug' => 'mem-reviews', 'title' => __( 'Reviews', 'memorial-plugin' ), 'schema' => 'memorial_plugin_reviews_schema',
        'intro_tab' => true,
        'intro' => __( 'Review services for the “Reviews” page. Put the “Reviews” block (or the <code>[mem_reviews]</code> shortcode) on the page. ', 'memorial-plugin' )
                 . __( 'The reviews block on the home page is set up separately: “Home page → “Reviews” block”.', 'memorial-plugin' ),
    ];
    return $pages;
});

/** Номер организации из ссылки Яндекс Карт */
function memorial_plugin_yandex_id($link) {
    return preg_match('~/org/(?:[^/]+/)?(\d{6,})~', (string) $link, $m) || preg_match('~^\s*(\d{6,})\s*$~', (string) $link, $m) ? $m[1] : '';
}

/** Ссылка «оставить отзыв» для сервиса */
function memorial_plugin_review_add_link($s) {
    $link = trim($s['link'] ?? '');
    if ($s['service'] === 'yandex' && ($id = memorial_plugin_yandex_id($link))) return 'https://yandex.ru/maps/org/' . $id . '/reviews/?add-review=true';
    if ($s['service'] === '2gis' && $link) return rtrim(preg_replace('~/tab/.*$~', '', $link), '/') . '/tab/reviews';
    return $link;
}

/** Оценка из настроек («4,9») числом */
function memorial_plugin_review_rating($s) { return (float) str_replace(',', '.', preg_replace('/[^\d,.]/', '', (string) ($s['rating'] ?? ''))); }

/** Строка «4,9 ★★★★★ 120 отзывов» — звёзды закрашены по оценке */
function memorial_plugin_review_rating_html($s) {
    $r = memorial_plugin_review_rating($s);
    if ($r <= 0) return '';
    $n = (int) preg_replace('/\D/', '', (string) ($s['count'] ?? ''));
    return '<span class="mrev__rate"><b>' . esc_html(number_format(min(5, $r), 1, ',', '')) . '</b>' . (function_exists('memorial_plugin_rewio_stars') ? memorial_plugin_rewio_stars(min(5, $r)) : '')
        . ($n ? '<small>' . $n . ' ' . esc_html(_n( 'review', 'reviews', $n, 'memorial-plugin' )) . '</small>' : '') . '</span>';
}

add_shortcode('mem_reviews', function () {
    $o = memorial_plugin_settings('mem_reviews');
    $src = array_values(array_filter($o['sources'] ?? [], fn($s) => trim($s['link'] ?? '') !== '' || trim($s['embed'] ?? '') !== ''));
    $book = function_exists('memorial_plugin_bookreviews_html') ? memorial_plugin_bookreviews_html() : '';
    if (!$src) {
        return $book ?: (current_user_can('manage_options') ? ( '<p class="is-style-note">' . esc_html__( '[mem_reviews]: add the review services in “Memorial → Reviews”.', 'memorial-plugin' ) . '</p>' ) : '');
    }
    $names = memorial_plugin_review_services();
    $label = fn($s) => $s['service'] === 'other' && trim($s['name'] ?? '') !== '' ? trim($s['name']) : ($names[$s['service']] ?? __( 'Reviews', 'memorial-plugin' ));
    // заглушка до загрузки виджета — одинаковая у всех сервисов: значок, название, филиал, оценка звёздами
    $ph = fn($s) => '<p class="mrev__phsvc">' . memorial_plugin_logo_html($s['service'], $label($s)) . '<span>' . esc_html($label($s)) . (trim($s['branch'] ?? '') !== '' ? ' · ' . esc_html($s['branch']) : '') . '</span></p>'
        . memorial_plugin_review_rating_html($s);

    ob_start();
    echo '<div class="mrev">';
    if (trim($o['texts']['lead'] ?? '') !== '') echo '<p class="mrev__lead">' . esc_html($o['texts']['lead']) . '</p>';

    // Карточки сервисов
    echo '<ul class="mrev__cards">';
    foreach ($src as $s) {
        $add = memorial_plugin_review_add_link($s);
        echo '<li class="mrev__card mrev__card--' . esc_attr($s['service']) . '">';
        echo '<span class="mrev__svc">' . memorial_plugin_logo_html($s['service'], $label($s)) . esc_html($label($s)) . '</span>';
        if (trim($s['branch'] ?? '') !== '') echo '<span class="mrev__branch">' . esc_html($s['branch']) . '</span>';
        echo memorial_plugin_review_rating_html($s);
        echo '<span class="mrev__btns">';
        if (trim($s['link'] ?? '') !== '') echo '<a href="' . esc_url($s['link']) . ( '" target="_blank" rel="noopener">' . esc_html__( 'Read reviews ↗', 'memorial-plugin' ) . '</a>' );
        if ($add && $add !== $s['link']) echo '<a href="' . esc_url($add) . ( '" target="_blank" rel="noopener">' . esc_html__( 'Leave a review ↗', 'memorial-plugin' ) . '</a>' );
        echo '</span></li>';
    }
    echo '</ul>';

    // Ленты отзывов: Яндекс — официальный виджет, остальные — код из настроек
    $feeds = [];
    foreach ($src as $s) {
        $id = $s['service'] === 'yandex' ? memorial_plugin_yandex_id($s['link']) : '';
        if ($id || trim($s['embed'] ?? '') !== '') $feeds[] = $s + ['yid' => $id];
    }
    if ($feeds) {
        $uid = 'mrev' . wp_rand(100, 999);
        echo '<div class="mrev__feeds" data-tabs data-wp-interactive="memorial-plugin/reviews" data-wp-init="callbacks.tabs">';
        if (count($feeds) > 1) {
            echo ( '<div class="mrev__tabs" role="tablist" aria-label="' . esc_html__( 'Reviews by branch', 'memorial-plugin' ) . '">' );
            foreach ($feeds as $n => $s) {
                $t = $label($s) . (trim($s['branch'] ?? '') !== '' ? ' · ' . $s['branch'] : '');
                echo '<button type="button" role="tab" class="maps__tab" id="' . $uid . 't' . $n . '" aria-controls="' . $uid . 'p' . $n . '" aria-selected="' . ($n ? 'false' : 'true') . '" tabindex="' . ($n ? '-1' : '0') . '">' . memorial_plugin_logo_html($s['service'], $label($s)) . esc_html($t) . '</button>';
            }
            echo '</div>';
        }
        foreach ($feeds as $n => $s) {
            echo '<div class="mrev__feed" id="' . $uid . 'p' . $n . '"' . (count($feeds) > 1 ? ' role="tabpanel" aria-labelledby="' . $uid . 't' . $n . '"' : '') . ($n ? ' hidden' : '') . '>';
            if ($s['yid']) {
                echo '<div class="reviews__widget" data-reviews="' . esc_url('https://yandex.ru/maps-reviews-widget/' . $s['yid'] . '?comments') . '">'
                   . '<div class="reviews__ph">' . $ph($s) . ( '<button class="btn btn--line" type="button" data-reviews-load>' . esc_html__( 'Show reviews', 'memorial-plugin' ) . '</button></div>' )
                   . '<a class="reviews__credit" href="' . esc_url('https://yandex.ru/maps/org/' . $s['yid'] . '/') . '" target="_blank" rel="noopener">' . esc_html(get_bloginfo('name')) . ( ' — ' . esc_html__( 'Yandex Maps', 'memorial-plugin' ) . '</a></div>' );
            } else {
                // код виджета: вставляется только после согласия на cookie или по кнопке
                echo '<div class="mrev__embed" data-embed-code data-wp-interactive="memorial-plugin/reviews" data-wp-init="callbacks.embed"><template>' . $s['embed'] . '</template>'
                   . '<div class="reviews__ph">' . $ph($s) . ( '<button class="btn btn--line" type="button" data-embed-code-load>' . esc_html__( 'Show reviews', 'memorial-plugin' ) . '</button></div></div>' );
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo $book; // «Из книги отзывов» — под виджетами
    if (trim($o['texts']['ask'] ?? '') !== '') echo '<p class="mrev__ask">' . esc_html($o['texts']['ask']) . '</p>';
    echo '</div>';
    return ob_get_clean();
});

/* ---------- Один пункт меню «Отзывы» с вкладками ---------- */
function memorial_plugin_reviews_tabs() {
    return [
        'services' => [__( 'Services and widgets', 'memorial-plugin' ), admin_url('admin.php?page=mem-reviews')],
        'rewio'    => [__( 'Rewio reviews', 'memorial-plugin' ), admin_url('admin.php?page=mem-rewio')],
        'book'     => [__( 'From the guest book', 'memorial-plugin' ), admin_url('admin.php?page=memorial-plugin-bookrev')],
    ];
}
add_action('memorial_plugin_admin_tabs', function ($opt) {
    if ($opt === 'mem_reviews') memorial_plugin_admin_tabs(memorial_plugin_reviews_tabs(), 'services');
    if ($opt === 'mem_rewio') memorial_plugin_admin_tabs(memorial_plugin_reviews_tabs(), 'rewio');
});
// список и правка отзывов из книги: те же вкладки, в меню подсвечен пункт «Отзывы»
add_action('all_admin_notices', function () {
    $sc = get_current_screen();
    if ($sc && in_array($sc->id, ['edit-mem_bookrev', 'admin_page_memorial-plugin-bookrev'], true)) memorial_plugin_admin_tabs(memorial_plugin_reviews_tabs(), 'book');
});
add_filter('parent_file', function ($f) {
    $sc = get_current_screen();
    if ($sc && ($sc->post_type === 'mem_bookrev' || ($_GET['page'] ?? '') === 'mem-rewio')) {
        global $submenu_file;
        $submenu_file = 'mem-reviews';
        return 'mem-contacts';
    }
    return $f;
});
