<?php
/**
 * Дымовой тест без WordPress (заглушки функций). Запуск: php tests/smoke/metrika.php — код выхода 1 при ошибке.
 */
register_shutdown_function(function () { if (!empty($GLOBALS['FAILED'])) exit(1); });
define('ABSPATH', '/tmp/'); $O = [];
function get_option($n, $d = false) { global $O; return array_key_exists($n, $O) ? $O[$n] : $d; }
function update_option($n, $v, $a = null) { global $O; $O[$n] = apply_filters('pre_update_option_' . $n, $v); return true; }
function delete_option($n) { global $O; unset($O[$n]); }
$F = []; function add_filter($h, $cb, $p = 10, $a = 1) { global $F; $F[$h][] = $cb; } function add_action($h, $cb, $p = 10, $a = 1) { add_filter($h, $cb); }
function apply_filters($h, $v) { global $F; foreach ($F[$h] ?? [] as $cb) $v = $cb($v); return $v; }
function __($s) { return $s; } function esc_url_raw($s) { return $s; } function rest_url($s) { return $s; }
$SET = []; function memorial_plugin_settings($n) { global $O; $d = memorial_plugin_metrika_defaults(); $s = $O[$n] ?? []; return ['counter' => ($s['counter'] ?? []) + $d['counter'], 'consent' => ($s['consent'] ?? []) + $d['consent']]; }
require dirname(__DIR__, 2) . '/memorial-plugin/seo/metrika.php';
$cases = [
  'нет опции (тестовый сайт)'      => [null, 'off', 'ask'],
  'счётчик + ask'                   => [['counter' => ['id' => '123'], 'consent' => ['mode' => 'ask']], 'consent', 'ask'],
  'счётчик + notice'                => [['counter' => ['id' => '123'], 'consent' => ['mode' => 'notice']], 'always', 'notice'],
  'счётчик + off'                   => [['counter' => ['id' => '123'], 'consent' => ['mode' => 'off']], 'always', 'notice'],
  'счётчик, режим не задан'         => [['counter' => ['id' => '123']], 'consent', 'ask'],
  'счётчика нет + notice'           => [['counter' => ['id' => ''], 'consent' => ['mode' => 'notice']], 'off', 'notice'],
];
foreach ($cases as $name => [$opt, $load, $banner]) {
    $O = $opt === null ? [] : ['mem_metrika' => $opt];
    memorial_plugin_metrika_migrate_load();
    $got = $O['mem_metrika']['counter']['load'] ?? '?'; $gb = memorial_plugin_consent_mode();
    $pass = $got === $load && $gb === $banner && !empty($O['memorial_plugin_metrika_confirm']); $pass || ($GLOBALS['FAILED'] = 1);
    echo ($pass ? 'OK   ' : 'FAIL ') . "$name → $got / плашка $gb\n";
    update_option('mem_metrika', $O['mem_metrika']); // «Сохранить» на странице
    $pass = empty($O['memorial_plugin_metrika_confirm']); $pass || ($GLOBALS['FAILED'] = 1);
    echo ($pass ? 'OK   ' : 'FAIL ') . "  после сохранения просьба подтвердить снята\n";
    memorial_plugin_metrika_migrate_load();
}
