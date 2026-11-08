<?php
/**
 * «Мемориал → Функции»: включение и выключение частей плагина (список — mem_core_optional() в memorial-core.php).
 * Выключенная функция не загружается вовсе: нет её настроек в меню, расписаний и нагрузки на сайт.
 * Её шорткоды на страницах ничего не выводят (а не показываются текстом). Данные (работы, отзывы, настройки) не удаляются —
 * включите обратно, и всё вернётся.
 */
defined('ABSPATH') || exit;

function mem_modules_schema() {
    $f = [];
    foreach (mem_core_optional() as $k => [$name, $desc]) $f[$k] = ['type' => 'checkbox', 'label' => $name . ' — ' . $desc];
    return ['on' => ['type' => 'group', 'label' => 'Функции плагина', 'help' => 'Снимите галочку — функция выключится: её пункты пропадут из меню, блоки на страницах ничего не покажут. Данные не удаляются. Каталог, цены, контакты, документы, SEO и скорость — основа сайта, они работают всегда (скорость настраивается в «Мемориал → Скорость»).',
                     'fields' => $f]];
}
function mem_modules_page_defaults() {
    return ['on' => array_fill_keys(array_keys(mem_core_optional()), 1)];
}

add_filter('mem_admin_pages', function ($pages) {
    $pages['mem_modules_page'] = ['slug' => 'mem-modules', 'title' => 'Функции', 'schema' => 'mem_modules_schema',
        'intro' => 'Что из плагина «Мемориал» работает на сайте.'];
    return $pages;
});

/* Настройки страницы хранятся в mem_modules_page['on'] — копируем в mem_modules, который читается до загрузки файлов */
add_action('update_option_mem_modules_page', 'mem_modules_sync', 10, 2);
add_action('add_option_mem_modules_page', fn($n, $v) => mem_modules_sync(null, $v), 10, 2);
function mem_modules_sync($old, $new) {
    $on = [];
    foreach (array_keys(mem_core_optional()) as $k) $on[$k] = (int) !empty($new['on'][$k]);
    $was = get_option('mem_modules');
    update_option('mem_modules', $on, true);
    if ($was !== $on) {
        update_option('mem_core_flush', 1, false);                          // адреса работ и т.п.
        if (empty($on['rewio'])) { wp_clear_scheduled_hook('mem_rewio_cron'); wp_clear_scheduled_hook('mem_rewio_cron_once'); }
        if (function_exists('mem_cache_purge')) mem_cache_purge(true);
    }
}

/* Шорткоды выключенных функций — пусто вместо текста «[mem_works …]» на странице */
add_action('init', function () {
    $off = array_flip(mem_core_disabled_files());
    foreach (mem_core_optional() as $k => $m) {
        if (!isset($off[$k])) continue;
        foreach ($m[3] as $tag) if (!shortcode_exists($tag)) add_shortcode($tag, fn() => '');
    }
}, 20);
