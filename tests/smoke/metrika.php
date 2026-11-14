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
// Режим загрузки счётчика → режим плашки о cookie.
$cases = [
  'после согласия'               => [['counter' => ['id' => '123', 'load' => 'consent'], 'consent' => ['mode' => 'notice']], 'ask'],
  'всегда'                       => [['counter' => ['id' => '123', 'load' => 'always'], 'consent' => ['mode' => 'ask']], 'notice'],
  'выключено, плашка из настроек' => [['counter' => ['id' => '', 'load' => 'off'], 'consent' => ['mode' => 'off']], 'off'],
];
foreach ($cases as $name => [$opt, $banner]) {
    $O = ['mem_metrika' => $opt];
    $gb = memorial_plugin_consent_mode();
    $pass = $gb === $banner; $pass || ($GLOBALS['FAILED'] = 1);
    echo ($pass ? 'OK   ' : 'FAIL ') . "$name → плашка $gb\n";
}
