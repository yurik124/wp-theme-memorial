<?php
/**
 * Тема «Memorial Modern» — ДИЗАЙН сайта: шаблоны страниц, шапка, подвал, стили, шрифты, «Мемориал → Оформление».
 * Весь функционал (каталог, цены, отзывы, заявки, настройки, шорткоды, SEO, кэш) — в плагине «Memorial Core».
 * Без плагина тема выглядит так же (шапка, меню, подвал, страницы, оформление), просто без данных и блоков плагина (inc/no-plugin.php).
 */
defined('ABSPATH') || exit;

define('MEM_VER', '3.3.3');

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
function mem_asset($path) {
    $dir = get_template_directory();
    if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) return $path;
    $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
    // устаревшую копию плагин пересоздаёт сам (автосжатие); без плагина — подключаем исходник
    if (function_exists('mem_min_refresh')) return mem_min_refresh($dir . $path, $dir . $min) ? $min : $path;
    return is_file($dir . $min) && filemtime($dir . $min) + 300 >= filemtime($dir . $path) ? $min : $path;
}

/* Стили и скрипты: версия = время изменения файла, кэш сбрасывается сам */
add_action('wp_enqueue_scripts', function () {
    $dir  = get_template_directory();
    $uri  = get_template_directory_uri();
    $css  = mem_asset('/assets/css/main.css');
    $js   = mem_asset('/assets/js/main.js');

    wp_enqueue_style('memorial', $uri . $css, [], filemtime($dir . $css));
    // предзагрузка основного начертания заголовков — без «прыжка» шрифта на первом экране
    add_action('wp_head', function () use ($uri) {
        $head = mem_design_opts()['fonts']['head'] ?? 'lora';
        $file = ['lora' => 'lora-500', 'cormorant' => 'cormorant-garamond-500'][$head] ?? '';
        if ($file) echo '<link rel="preload" href="' . esc_url($uri . '/assets/fonts/' . $file . '.woff2') . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }, 2);
    // фото первого экрана главной: браузер начинает грузить его сразу, а не после стилей (ускоряет LCP)
    if (is_front_page() && function_exists('mem_home') && ($hero_img = (mem_home('hero')['img'] ?? ''))) {
        add_action('wp_head', function () use ($hero_img) {
            [$d, $m] = memorial_hero_srcs($hero_img);
            echo '<link rel="preload" as="image" href="' . esc_url($m) . '" media="(max-width:700px)" fetchpriority="high">' . "\n"
               . '<link rel="preload" as="image" href="' . esc_url($d) . '" media="(min-width:701px)" fetchpriority="high">' . "\n";
        }, 2);
    }
    wp_enqueue_script('memorial', $uri . $js, [], filemtime($dir . $js), ['strategy' => 'defer', 'in_footer' => true]);

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
function mem_footer_nav() {
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
function memorial_addr($a) {
    return trim(preg_replace('/^\s*\d{6}\s*,?\s*/u', '', (string) $a));
}

/* Оформление текста страниц: таблицы-прайсы, шаги, галочки, карточки, галереи */
add_action('wp_enqueue_scripts', function () {
    $f = mem_asset('/assets/css/content.css');
    wp_enqueue_style('memorial-content', get_template_directory_uri() . $f, ['memorial'], filemtime(get_template_directory() . $f));
}, 21);

require get_template_directory() . '/inc/icons.php';
require get_template_directory() . '/inc/design.php';

/* Функционал — в плагине Memorial Core. Без него шаблоны работают на запасных функциях (inc/no-plugin.php):
   шапка, меню, подвал, страницы и оформление на месте, нет только того, что даёт плагин (контакты, каталог, цены…). */
if (!defined('MEM_CORE_VER')) require get_template_directory() . '/inc/no-plugin.php';

/** Фото первого экрана: [для ПК — как загружено, для телефона — 1024 px из медиатеки (если есть)] */
function memorial_hero_srcs($url) {
    $m = $url;
    $id = attachment_url_to_postid(preg_replace('/-\d+x\d+(?=\.\w+$)/', '', $url));
    if ($id && ($l = wp_get_attachment_image_src($id, 'large')) && $l[1] < (wp_get_attachment_image_src($id, 'full')[1] ?? 0)) $m = $l[0];
    return [$url, $m];
}
