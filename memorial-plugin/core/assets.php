<?php
/**
 * Стили и скрипты блоков плагина на сайте:
 *   assets/css/blocks.css — базовый вид блоков шорткодов (тема может переопределить или отключить);
 *   assets/js/front.js    — поведение блоков (отзывы, вопрос–ответ, каталог, «Наши работы»).
 * Версия = время изменения файла: после правки браузер сразу берёт новый файл.
 */
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    if (apply_filters('memorial_plugin_blocks_css', true)) {
        $css = memorial_plugin_asset('/assets/css/blocks.css');
        // после стилей темы (memorial-theme, memorial-theme-content), чтобы порядок правил остался прежним
        $deps = array_values(array_filter(['memorial-theme', 'memorial-theme-content'], 'wp_style_is'));
        wp_enqueue_style('memorial-plugin', MEMORIAL_PLUGIN_URL . $css, $deps, filemtime(MEMORIAL_PLUGIN_DIR . $css));
    }
    $js = memorial_plugin_asset('/assets/js/front.js');
    wp_enqueue_script('memorial-plugin', MEMORIAL_PLUGIN_URL . $js, [], filemtime(MEMORIAL_PLUGIN_DIR . $js), ['strategy' => 'defer', 'in_footer' => true]);
}, 22);

/* Те же стили блоков в редакторе (в его окне страницы), чтобы блоки плагина выглядели как на сайте */
add_action('enqueue_block_assets', function () {
    if (!is_admin() || !apply_filters('memorial_plugin_blocks_css', true)) return;
    $css = memorial_plugin_asset('/assets/css/blocks.css');
    wp_enqueue_style('memorial-plugin-editor-blocks', MEMORIAL_PLUGIN_URL . $css, [], filemtime(MEMORIAL_PLUGIN_DIR . $css));
});
