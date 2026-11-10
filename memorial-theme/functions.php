<?php
/**
 * Тема «Memorial Modern» 4 «Светлая память» — ДИЗАЙН сайта: светлые шапка и подвал, лавровая графика (inc/laurel.php),
 * шаблоны страниц, стили, шрифты; цвета и шрифты — в стилях сайта (theme.json).
 * Весь функционал (каталог, цены, отзывы, заявки, настройки, шорткоды, SEO, кэш) — в плагине «Memorial Core».
 * Без плагина тема выглядит так же (шапка, меню, подвал, страницы, оформление), просто без данных и блоков плагина (inc/no-plugin.php).
 */
defined('ABSPATH') || exit;

define('MEMORIAL_THEME_VER', '5.0.0-alpha.1');

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height' => 60, 'width' => 200, 'flex-width' => true]);
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    register_nav_menus(['primary' => 'Главное меню', 'footer' => 'Меню в подвале']);
    // те же стили текста в редакторе, что и на сайте
    add_theme_support('editor-styles');
    add_editor_style(['assets/css/content.css']);
});

/** Сжатая копия файла (.min.css / .min.js), если она есть и не старше исходника; иначе — сам файл.
    С плагином Memorial Core устаревшая копия пересоздаётся сама. */
function memorial_theme_asset($path) {
    $dir = get_template_directory();
    if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) return $path;
    $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
    // устаревшую копию плагин пересоздаёт сам (автосжатие); без плагина — подключаем исходник
    if (function_exists('memorial_plugin_min_refresh')) return memorial_plugin_min_refresh($dir . $path, $dir . $min) ? $min : $path;
    return is_file($dir . $min) && filemtime($dir . $min) + 300 >= filemtime($dir . $path) ? $min : $path;
}

/* Стили и скрипты: версия = время изменения файла, кэш сбрасывается сам */
add_action('wp_enqueue_scripts', function () {
    $dir  = get_template_directory();
    $uri  = get_template_directory_uri();
    $css  = memorial_theme_asset('/assets/css/main.css');
    $js   = memorial_theme_asset('/assets/js/main.js');

    wp_enqueue_style('memorial-theme', $uri . $css, [], filemtime($dir . $css));
    // предзагрузка основного начертания заголовков — без «прыжка» шрифта на первом экране
    add_action('wp_head', function () use ($uri) {
        // шрифт заголовков из стилей сайта: Cormorant Garamond (по умолчанию) или Lora
        $head = (string) wp_get_global_styles(['elements', 'heading', 'typography', 'fontFamily']);
        $file = str_contains($head, 'font-family--lora') ? 'lora-500' : (($head === '' || str_contains($head, 'cormorant')) ? 'cormorant-garamond-500' : '');
        if ($file) echo '<link rel="preload" href="' . esc_url($uri . '/assets/fonts/' . $file . '.woff2') . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }, 2);
    // фото первого экрана главной: браузер начинает грузить его сразу, а не после стилей (ускоряет LCP)
    if (is_front_page() && function_exists('memorial_plugin_home') && ($hero_img = (memorial_plugin_home('hero')['img'] ?? ''))) {
        add_action('wp_head', function () use ($hero_img) {
            [$d, $m] = memorial_theme_hero_srcs($hero_img);
            echo '<link rel="preload" as="image" href="' . esc_url($m) . '" media="(max-width:700px)" fetchpriority="high">' . "\n"
               . '<link rel="preload" as="image" href="' . esc_url($d) . '" media="(min-width:701px)" fetchpriority="high">' . "\n";
        }, 2);
    }
    wp_enqueue_script('memorial-theme', $uri . $js, [], filemtime($dir . $js), ['strategy' => 'defer', 'in_footer' => true]);

    if (is_front_page()) {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('classic-theme-styles');
    }
}, 20);

/* Чистка head */
add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'rest_output_link_wp_head');
    add_filter('emoji_svg_url', '__return_false');
});

/**
 * Меню в подвале: пункты с подпунктами — колонки с заголовком, одиночные пункты — общая колонка «Информация».
 * Если меню для подвала не выбрано, берётся главное.
 */
function memorial_theme_footer_nav() {
    $locs = get_nav_menu_locations();
    $menu = $locs['footer'] ?? 0 ?: ($locs['primary'] ?? 0);
    $items = $menu ? wp_get_nav_menu_items($menu) : [];
    if (!$items) return '';
    $top = []; $kids = [];
    foreach ($items as $it) {
        if ((int) $it->menu_item_parent) $kids[(int) $it->menu_item_parent][] = $it;
        else $top[] = $it;
    }
    $link = fn($it) => '<a href="' . esc_url($it->url) . '"' . ($it->target ? ' target="' . esc_attr($it->target) . '" rel="noopener"' : '') . '>' . esc_html($it->title) . '</a>';
    $cols = ''; $single = '';
    foreach ($top as $it) {
        if (!empty($kids[$it->ID])) {
            $head = in_array(trim($it->url), ['', '#'], true) ? esc_html($it->title) : $link($it); // «О нас» со ссылкой # — просто заголовок
            $cols .= '<div class="fnav__col"><p class="fnav__title">' . $head . '</p><ul>';
            foreach ($kids[$it->ID] as $k) $cols .= '<li>' . $link($k) . '</li>';
            $cols .= '</ul></div>';
        } else {
            $single .= '<li>' . $link($it) . '</li>';
        }
    }
    if ($single) $cols .= '<div class="fnav__col"><p class="fnav__title">Информация</p><ul>' . $single . '</ul></div>';
    return '<nav class="wrap fnav" aria-label="Меню в подвале">' . $cols . '</nav>';
}


/* Стили «глобальных настроек» блочных тем WordPress оставляем: на них держится раскладка блоков (is-layout-flex и т. п.).
   Убираем только лишние SVG-фильтры дуотона, которые тема не использует. */
add_action('init', function () {
    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
});

/** Адрес для посетителей — без почтового индекса в начале («308000, г. Белгород…» → «г. Белгород…»).
    Индекс остаётся в разметке для поисковиков. */
function memorial_theme_addr($a) {
    return trim(preg_replace('/^\s*\d{6}\s*,?\s*/u', '', (string) $a));
}

/* Оформление текста страниц: таблицы-прайсы, шаги, галочки, карточки, галереи */
add_action('wp_enqueue_scripts', function () {
    $f = memorial_theme_asset('/assets/css/content.css');
    wp_enqueue_style('memorial-theme-content', get_template_directory_uri() . $f, ['memorial-theme'], filemtime(get_template_directory() . $f));
}, 21);

require get_template_directory() . '/inc/icons.php';
require get_template_directory() . '/inc/design.php';
require get_template_directory() . '/inc/design-migrate.php';
require get_template_directory() . '/inc/laurel.php';

/* Функционал — в плагине Memorial Core. Без него шаблоны работают на запасных функциях (inc/no-plugin.php):
   шапка, меню, подвал, страницы и оформление на месте, нет только того, что даёт плагин (контакты, каталог, цены…). */
if (!defined('MEMORIAL_PLUGIN_VER')) require get_template_directory() . '/inc/no-plugin.php';

/** Фото первого экрана: [для ПК — как загружено, для телефона — 1024 px из медиатеки (если есть)] */
function memorial_theme_hero_srcs($url) {
    $m = $url;
    $id = function_exists('memorial_plugin_attachment_id_by_url') ? memorial_plugin_attachment_id_by_url($url)   // находит и фото с адресом рабочего сайта
        : attachment_url_to_postid(preg_replace('/-\d+x\d+(?=\.\w+$)/', '', $url));
    if ($id && ($l = wp_get_attachment_image_src($id, 'large')) && $l[1] < (wp_get_attachment_image_src($id, 'full')[1] ?? 0)) $m = $l[0];
    return [$url, $m];
}

/**
 * Неразрывные пробелы, чтобы строка не рвалась некрасиво: «с 2003 года», «в Белгороде», «36 700 ₽» —
 * короткие предлоги и союзы держатся со следующим словом, число — со словом после него.
 */
function memorial_theme_nbsp($s) {
    $s = (string) $s;
    if ($s === '') return $s;
    $nb = "\u{00A0}";
    // предлог/союз (1–3 буквы и «для», «без», «под», «при») + пробел → неразрывный
    $s = preg_replace('/(?<![\p{L}\d\-])(с|со|в|во|к|ко|и|а|о|об|у|по|от|до|на|за|из|не|но|ни|для|без|под|при|над)\s+(?=[\p{L}\d«"(])/iu', '$1' . $nb, $s);
    // число + слово/знак после него: «2003 года», «23 лет», «700 ₽», «12 км»
    $s = preg_replace('/(\d)\s+(?=(?:год|года|лет|г\.|₽|руб|км|м\b|мин|час|чел|шт|%))/u', '$1' . $nb, $s);
    // разряды числа: «36 700»
    $s = preg_replace('/(\d)\s(?=\d{3}(?!\d))/u', '$1' . $nb, $s);
    // «·» и «—» не переносятся на новую строку отдельно
    $s = preg_replace('/\s+([·—])\s/u', $nb . '$1 ', $s);
    return $s;
}
add_filter('the_title', function ($t, $id = 0) { return is_admin() ? $t : memorial_theme_nbsp($t); }, 20, 2);

/** Ссылка на MAX из «Мемориал → Контакты» (для нижней панели на телефоне) */
function memorial_theme_max_url() {
    $m = function_exists('memorial_plugin_messengers') ? (array) memorial_plugin_messengers() : [];
    return $m['max'] ?? '';
}

/** Заголовок блока главной: надзаголовок, лавровый разделитель, H2 */
function memorial_theme_sec_head($sec, $e = null) {
    $e = $e ?: fn($s) => memorial_theme_nbsp(esc_html(function_exists('memorial_plugin_t') ? memorial_plugin_t($s) : $s));
    if (empty($sec['eyebrow']) && empty($sec['title'])) return;
    echo '<header class="sec-head">';
    if (!empty($sec['eyebrow'])) echo '<p class="eyebrow">' . $e($sec['eyebrow']) . '</p>';
    echo '<span class="sec-head__div">' . memorial_theme_laurel('divider') . '</span>';
    if (!empty($sec['title'])) echo '<h2>' . $e($sec['title']) . '</h2>';
    echo '</header>';
}

/** Год основания из «Мемориал → Контакты» (для «© 2003–2026»), 0 — если не задан */
function memorial_theme_founded_year() {
    if (function_exists('memorial_plugin_opt') && ($y = (int) memorial_plugin_opt('founded')) > 1900) return $y;
    return function_exists('memorial_plugin_years') && memorial_plugin_years() ? (int) date('Y') - (int) memorial_plugin_years() : 0;
}
