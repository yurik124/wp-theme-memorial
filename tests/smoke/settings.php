<?php
/**
 * Дымовой тест без WordPress: чтение настроек не зацикливается со списком страниц админки.
 * (Обработчики фильтра memorial_plugin_admin_pages сами читают настройки — так устроена страница «Цены».)
 * Здесь фильтры выполняются по-настоящему. Запуск: php tests/smoke/settings.php — код выхода 1 при ошибке.
 */
define('ABSPATH', '/tmp/');
ini_set('memory_limit', '64M');
$F = [];
function add_filter($h, $cb, $p = 10, $a = 1) { global $F; $F[$h][] = [$cb, $a]; return true; }
function add_action($h, $cb, $p = 10, $a = 1) { return add_filter($h, $cb, $p, $a); }
function apply_filters($h, $v, ...$args) { global $F; foreach ($F[$h] ?? [] as [$cb, $a]) $v = $cb(...array_slice(array_merge([$v], $args), 0, $a)); return $v; }
function get_option($n, $d = false) { return $n === 'mem_demo' ? ['title' => 'из базы'] : $d; }
function is_admin() { return false; }
require dirname(__DIR__, 2) . '/memorial-plugin/core/settings.php';

function memorial_plugin_demo_defaults() { return ['title' => 'по умолчанию', 'text' => 'текст']; }
function memorial_theme_design_defaults() { return ['preset' => 'laurel']; }
// Как в prices.php: описание страницы строится из настроек.
add_filter('memorial_plugin_admin_pages', function ($pages) {
    $pages['mem_demo'] = ['slug' => 'mem-demo', 'title' => memorial_plugin_settings('mem_demo')['title'], 'schema' => 'x'];
    return $pages;
});
// Как в теме: значения по умолчанию для mem_design даёт функция темы.
add_filter('memorial_plugin_settings_defaults', fn($fn, $name) => $name === 'mem_design' ? 'memorial_theme_design_defaults' : $fn, 10, 2);

$failed = 0;
$ok = function ($c, $m) use (&$failed) { if (!$c) $failed = 1; echo ($c ? 'OK   ' : 'FAIL ') . $m . "\n"; };
$s = memorial_plugin_settings('mem_demo');
$ok($s === ['title' => 'из базы', 'text' => 'текст'], 'настройки: значение из базы поверх значений по умолчанию');
$ok(memorial_plugin_admin_pages()['mem_demo']['title'] === 'из базы', 'список страниц читает настройки без зацикливания');
$ok(memorial_plugin_settings('mem_design') === ['preset' => 'laurel'], 'значения по умолчанию темы — через фильтр');
$ok(memorial_plugin_settings('mem_none') === [], 'неизвестная настройка — пустой массив');
exit($failed);
