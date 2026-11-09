<?php
/**
 * Тема без плагина Memorial Core.
 * Шаблоны темы (шапка, меню, подвал, страницы) работают как обычно; то, что даёт плагин, просто не выводится:
 * контакты, каталог, цены, блоки главной, хлебные крошки. Для этого на сайте подставляются «пустые» версии
 * функций плагина. Подставляются только при показе страниц сайта (template_redirect) — в админке их нет,
 * поэтому включение плагина не конфликтует с ними.
 */
defined('ABSPATH') || exit;

add_action('admin_notices', function () {
    if (!current_user_can('activate_plugins')) return;
    echo '<div class="notice notice-error"><p><b>Тема «Memorial Modern»:</b> включите плагин <b>Memorial Core</b> '
       . '(<a href="' . esc_url(admin_url('plugins.php')) . '">Плагины</a>) — в нём каталог, цены, отзывы, контакты и настройки «Мемориал». '
       . 'Пока он выключен, на сайте видны только страницы, меню и оформление.</p></div>';
});

/* Шорткоды плагина без плагина — убираем, чтобы в тексте не было «[mem_…]» */
add_filter('the_content', fn($c) => preg_replace('~\[/?mem_[a-z_]+[^\]]*\]~', '', $c), 9);

add_action('template_redirect', function () {
    if (defined('MEMORIAL_PLUGIN_VER') || function_exists('memorial_plugin_settings')) return;
    require get_template_directory() . '/inc/no-plugin-stubs.php';
}, 0);
