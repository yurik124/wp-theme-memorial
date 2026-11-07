<?php
/**
 * Стили и скрипты блоков плагина на сайте:
 *   assets/css/blocks.css — базовый вид блоков шорткодов (тема может переопределить или отключить);
 *   assets/js/front.js    — поведение блоков (отзывы, вопрос–ответ, каталог, «Наши работы»).
 * Версия = время изменения файла: после правки браузер сразу берёт новый файл.
 */
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    if (apply_filters('mem_core_blocks_css', true)) {
        $css = mem_core_asset('/assets/css/blocks.css');
        // после стилей темы (memorial, memorial-content), чтобы порядок правил остался прежним
        $deps = array_values(array_filter(['memorial', 'memorial-content'], 'wp_style_is'));
        wp_enqueue_style('memorial-core', MEM_CORE_URL . $css, $deps, filemtime(MEM_CORE_DIR . $css));
    }
    $js = mem_core_asset('/assets/js/front.js');
    wp_enqueue_script('memorial-core', MEM_CORE_URL . $js, [], filemtime(MEM_CORE_DIR . $js), ['strategy' => 'defer', 'in_footer' => true]);
}, 22);
