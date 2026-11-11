<?php
/**
 * Страница «Отзывы»: шорткод [mem_reviews].
 * Источники отзывов (Яндекс Карты, 2ГИС, Google и любые другие, по филиалам) задаются в «Мемориал → Отзывы».
 * Виджеты сторонних сервисов загружаются после согласия на cookie или по кнопке.
 */
defined('ABSPATH') || exit;

function memorial_plugin_review_services() {
    return [
        'yandex' => 'Яндекс Карты',
        '2gis'   => '2ГИС',
        'google' => 'Google Карты',
        'avito'  => 'Авито',
        'other'  => 'Другой сервис',
    ];
}

function memorial_plugin_reviews_schema() {
    return [
        'sources' => ['type' => 'repeater', 'label' => 'Где о нас пишут отзывы', 'add' => 'Добавить сервис или филиал', 'collapse' => 'branch', 'open' => true,
            'help' => 'По строке на каждый сервис и филиал. Порядок меняется стрелками ↑ ↓ справа от строки. Оценку и число отзывов пишите как в самом сервисе — их видно посетителям.',
            'fields' => [
                'service' => ['type' => 'select', 'label' => 'Сервис', 'options' => memorial_plugin_review_services()],
                'name'    => ['type' => 'text', 'label' => 'Название сервиса (только для «Другой сервис»)'],
                'branch'  => ['type' => 'text', 'label' => 'Филиал', 'placeholder' => 'например: ул. Студенческая, 40'],
                'link'    => ['type' => 'text', 'label' => 'Ссылка на вашу карточку в сервисе', 'placeholder' => 'https://yandex.ru/maps/org/…/1234567890/',
                              'help' => 'Для Яндекс Карт по ссылке тема сама покажет ленту отзывов. Для 2ГИС и других — кнопки «Читать отзывы» и «Оставить отзыв».'],
                'rating'  => ['type' => 'text', 'label' => 'Оценка (необязательно)', 'placeholder' => '4,9'],
                'count'   => ['type' => 'text', 'label' => 'Число отзывов (необязательно)', 'placeholder' => '120'],
                'embed'   => ['type' => 'code', 'label' => 'Код виджета (необязательно)', 'rows' => 4,
                              'help' => 'Если у сервиса или стороннего агрегатора отзывов есть код для сайта — вставьте его сюда целиком. Он покажется на вкладке этого филиала.'],
            ]],
        'texts' => ['type' => 'group', 'label' => 'Тексты на странице', 'fields' => [
            'lead' => ['type' => 'textarea', 'label' => 'Текст над отзывами', 'rows' => 2],
            'ask'  => ['type' => 'textarea', 'label' => 'Просьба оставить отзыв (под отзывами)', 'rows' => 2],
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
        'slug' => 'mem-reviews', 'title' => 'Отзывы', 'schema' => 'memorial_plugin_reviews_schema',
        'intro_tab' => true,
        'intro' => 'Сервисы отзывов для страницы «Отзывы». На странице поставьте шорткод <code>[mem_reviews]</code> (блок «Шорткод»). '
                 . 'Блок отзывов на главной настраивается отдельно: «Главная страница → Блок «Отзывы»».',
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
        . ($n ? '<small>' . $n . ' ' . esc_html(memorial_plugin_word($n, 'отзыв', 'отзыва', 'отзывов')) . '</small>' : '') . '</span>';
}

add_shortcode('mem_reviews', function () {
    $o = memorial_plugin_settings('mem_reviews');
    $src = array_values(array_filter($o['sources'] ?? [], fn($s) => trim($s['link'] ?? '') !== '' || trim($s['embed'] ?? '') !== ''));
    $book = function_exists('memorial_plugin_bookreviews_html') ? memorial_plugin_bookreviews_html() : '';
    if (!$src) {
        return $book ?: (current_user_can('manage_options') ? '<p class="is-style-note">[mem_reviews]: добавьте сервисы отзывов в «Мемориал → Отзывы».</p>' : '');
    }
    $names = memorial_plugin_review_services();
    $label = fn($s) => $s['service'] === 'other' && trim($s['name'] ?? '') !== '' ? trim($s['name']) : ($names[$s['service']] ?? 'Отзывы');
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
        if (trim($s['link'] ?? '') !== '') echo '<a href="' . esc_url($s['link']) . '" target="_blank" rel="noopener">Читать отзывы ↗</a>';
        if ($add && $add !== $s['link']) echo '<a href="' . esc_url($add) . '" target="_blank" rel="noopener">Оставить отзыв ↗</a>';
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
            echo '<div class="mrev__tabs" role="tablist" aria-label="Отзывы по филиалам">';
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
                   . '<div class="reviews__ph">' . $ph($s) . '<button class="btn btn--line" type="button" data-reviews-load>Показать отзывы</button></div>'
                   . '<a class="reviews__credit" href="' . esc_url('https://yandex.ru/maps/org/' . $s['yid'] . '/') . '" target="_blank" rel="noopener">' . esc_html(get_bloginfo('name')) . ' — Яндекс Карты</a></div>';
            } else {
                // код виджета: вставляется только после согласия на cookie или по кнопке
                echo '<div class="mrev__embed" data-embed-code data-wp-interactive="memorial-plugin/reviews" data-wp-init="callbacks.embed"><template>' . $s['embed'] . '</template>'
                   . '<div class="reviews__ph">' . $ph($s) . '<button class="btn btn--line" type="button" data-embed-code-load>Показать отзывы</button></div></div>';
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
        'services' => ['Сервисы и виджеты', admin_url('admin.php?page=mem-reviews')],
        'rewio'    => ['Отзывы Rewio', admin_url('admin.php?page=mem-rewio')],
        'book'     => ['Из книги отзывов', admin_url('admin.php?page=memorial-plugin-bookrev')],
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
