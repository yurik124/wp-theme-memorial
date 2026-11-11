<?php
/**
 * Тема «Memorial Modern» 5 «Светлая память» — блоковая тема: дизайн сайта.
 *
 * Шаблоны — templates/*.html, части — parts/*.html (редактируются во «Внешний вид → Редактор»),
 * цвета, шрифты и размеры — theme.json и варианты стилей styles/*.json.
 * Данные сайта (телефоны, адреса, каталог, цены) выводят блоки плагина Memorial Plugin;
 * тема даёт им лавровую графику через фильтр (inc/laurel.php); иконки — из реестра иконок WordPress (плагин).
 *
 * @package memorial-theme
 */

defined('ABSPATH') || exit;

define('MEMORIAL_THEME_VER', '5.0.0');

/* Translations of the theme (languages/memorial-theme-<locale>.mo / .l10n.php) — before anything translatable */
add_action('after_setup_theme', function () {
    load_theme_textdomain('memorial-theme', get_template_directory() . '/languages');
}, 0);

add_action('after_setup_theme', function () {
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height' => 60, 'width' => 200, 'flex-width' => true]);
    add_theme_support('responsive-embeds');
    // классические меню — источник для колонок меню в подвале (блок плагина) до этапа 3
    register_nav_menus(['primary' => __( 'Main menu', 'memorial-theme' ), 'footer' => __( 'Footer menu', 'memorial-theme' )]);
    add_theme_support('editor-styles');
    // В редакторе страница выглядит как на сайте: общие стили темы (шапка, подвал, разделы) и оформление текста
    add_editor_style(['assets/css/main.css', 'assets/css/content.css']);
});

/** Сжатая копия файла (.min.css / .min.js), если она есть и не старше исходника; иначе — сам файл.
    Копии собирает tools/minify.php. */
function memorial_theme_asset($path) {
    $dir = get_template_directory();
    if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) return $path;
    $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
    return is_file($dir . $min) && filemtime($dir . $min) + 300 >= filemtime($dir . $path) ? $min : $path;
}

/* Стили и скрипты темы: версия = время изменения файла, кэш сбрасывается сам */
add_action('wp_enqueue_scripts', function () {
    $dir = get_template_directory();
    $uri = get_template_directory_uri();
    $css = memorial_theme_asset('/assets/css/main.css');
    $js  = memorial_theme_asset('/assets/js/main.js');
    wp_enqueue_style('memorial-theme', $uri . $css, ['global-styles'], filemtime($dir . $css));
    $f = memorial_theme_asset('/assets/css/content.css');
    wp_enqueue_style('memorial-theme-content', $uri . $f, ['memorial-theme'], filemtime($dir . $f));
    wp_enqueue_script('memorial-theme', $uri . $js, [], filemtime($dir . $js), ['strategy' => 'defer', 'in_footer' => true]);
    wp_add_inline_script('memorial-theme', 'window.memorialThemeI18n = ' . wp_json_encode(memorial_theme_js_i18n()) . ';', 'before');
}, 20);

/**
 * Texts of assets/js/main.js, translated here (the script has no dependency on wp-i18n).
 *
 * @return array<string,string>
 */
function memorial_theme_js_i18n() {
    return [
        /* translators: %s: menu item */
        'expand'       => __( 'Expand: %s', 'memorial-theme' ),
        'submenu'      => __( 'submenu', 'memorial-theme' ),
        'fullNumber'   => __( 'Enter the full number: +7 and 10 digits.', 'memorial-theme' ),
        'consent'      => __( 'Tick the consent to the data processing.', 'memorial-theme' ),
        'notSent'      => __( 'Could not send.', 'memorial-theme' ),
        'callUs'       => __( 'Please call us.', 'memorial-theme' ),
        'maxFailed'    => __( 'Could not open MAX. Leave your number — we will call you back.', 'memorial-theme' ),
        'maxBack'      => __( 'Could not write in MAX? Leave your number — we will call you back.', 'memorial-theme' ),
        'reviewsFrame' => __( 'Reviews on Yandex Maps', 'memorial-theme' ),
        'mapFrame'     => __( 'Directions map', 'memorial-theme' ),
        'scrollLeft'   => __( 'Scroll left', 'memorial-theme' ),
        'scrollRight'  => __( 'Scroll right', 'memorial-theme' ),
        /* translators: 1: number of the photo, 2: number of photos */
        'photoOf'      => __( 'photo %1$d of %2$d', 'memorial-theme' ),
    ];
}

/* Предзагрузка: шрифт заголовков (без «прыжка» на первом экране) и фото первого экрана главной (LCP) */
add_action('wp_head', function () {
    $uri  = get_template_directory_uri();
    $head = wp_get_global_styles(['elements', 'heading', 'typography', 'fontFamily']);
    $head = is_string($head) ? $head : '';
    $file = str_contains($head, 'font-family--lora') ? 'lora-500' : (($head === '' || str_contains($head, 'cormorant')) ? 'cormorant-garamond-500' : '');
    if ($file) echo '<link rel="preload" href="' . esc_url($uri . '/assets/fonts/' . $file . '.woff2') . '" as="font" type="font/woff2" crossorigin>' . "\n";
    if (is_front_page() && function_exists('memorial_plugin_home') && function_exists('memorial_plugin_hero_srcs') && ($img = (memorial_plugin_home('hero')['img'] ?? ''))) {
        [$d, $m] = memorial_plugin_hero_srcs($img);
        echo '<link rel="preload" as="image" href="' . esc_url($m) . '" media="(max-width:700px)" fetchpriority="high">' . "\n"
           . '<link rel="preload" as="image" href="' . esc_url($d) . '" media="(min-width:701px)" fetchpriority="high">' . "\n";
    }
}, 2);

/* Цвет панели браузера на телефоне и запрет автоссылок на номера (номера — ссылки tel: сами) */
add_action('wp_head', function () {
    echo '<meta name="format-detection" content="telephone=no">' . "\n";
    echo '<meta name="theme-color" content="' . esc_attr(memorial_theme_theme_color()) . '">' . "\n";
}, 1);

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
    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
});

/* «Логотип сайта» без своего логотипа: значок сайта, иначе лавровый знак с буквой (как в теме 4) */
add_filter('render_block_core/site-logo', function ($html, $block) {
    if (trim($html) !== '') return $html;
    $size  = (int) ($block['attrs']['width'] ?? 44);
    $class = trim('wp-block-site-logo ' . ($block['attrs']['className'] ?? ''));
    return '<div class="' . esc_attr($class) . '"><a href="' . esc_url(home_url('/')) . '" class="custom-logo-link" rel="home" aria-label="'
         . esc_attr(get_bloginfo('name') . __( ' — to the home page', 'memorial-theme' )) . '">' . memorial_theme_logo_mark($size) . '</a></div>';
}, 10, 2);

/* Паттерны темы — категория «Мемориал» */
add_action('init', function () {
    register_block_pattern_category('memorial-theme', ['label' => __( 'Memorial: design', 'memorial-theme' )]);
});

require get_template_directory() . '/inc/design.php';
require get_template_directory() . '/inc/design-migrate.php';
require get_template_directory() . '/inc/laurel.php';
require get_template_directory() . '/inc/navigation-migrate.php';
