<?php
/**
 * Дымовой тест без WordPress (заглушки функций). Запуск: php tests/smoke/switch.php — код выхода 1 при ошибке.
 */
register_shutdown_function(function () { if (!empty($GLOBALS['FAILED'])) exit(1); });
define('ABSPATH', '/tmp/');
$O = []; $CRON_CLEARED = []; $SWITCHED = null; $DEACT = [];
function get_option($n, $d = false) { global $O; return array_key_exists($n, $O) ? $O[$n] : $d; }
function update_option($n, $v, $a = null) { global $O; $O[$n] = apply_filters('pre_update_option_' . $n, $v); return true; }
function add_option($n, $v) { global $O; if (array_key_exists($n, $O)) return false; $O[$n] = $v; return true; }
function delete_option($n) { global $O; unset($O[$n]); }
$F = [];
function add_filter($h, $cb) { global $F; $F[$h][] = $cb; } function add_action($h, $cb) { add_filter($h, $cb); }
function apply_filters($h, $v) { global $F; foreach ($F[$h] ?? [] as $cb) $v = $cb($v); return $v; }
function wp_clear_scheduled_hook($h) { global $CRON_CLEARED; $CRON_CLEARED[] = $h; }
function deactivate_plugins($p) { global $O, $DEACT; $DEACT[] = $p; $O['active_plugins'] = array_values(array_diff($O['active_plugins'], [$p])); }
function get_stylesheet() { global $O; return $O['stylesheet']; }
function switch_theme($t) { global $O, $SWITCHED; $SWITCHED = $t; $O['stylesheet'] = $O['template'] = $t; }
class T { public $e; function __construct($e) { $this->e = $e; } function exists() { return $this->e; } }
function wp_get_theme($t) { return new T($t === 'memorial-theme'); }
function __($s) { return $s; }
require dirname(__DIR__, 2) . '/memorial-plugin/core/switchover.php';
// 1) переключение
$O = ['active_plugins' => ['memorial-core/memorial-core.php', 'wp-seopress/seopress.php'], 'stylesheet' => 'memorial_speshu', 'template' => 'memorial_speshu',
      'theme_mods_memorial_speshu' => ['nav_menu_locations' => ['primary' => 96, 'footer' => 96], 'custom_logo' => '']];
$ok = function ($c, $m) { if (!$c) $GLOBALS['FAILED'] = 1; echo ($c ? 'OK   ' : 'FAIL ') . $m . "\n"; };
$ok(memorial_plugin_legacy_active(), 'старый плагин виден как активный');
memorial_plugin_switchover();
$ok($DEACT === ['memorial-core/memorial-core.php'], 'старый плагин выключен');
$ok(!memorial_plugin_legacy_active() && in_array('wp-seopress/seopress.php', $O['active_plugins']), 'остальные плагины на месте');
$ok($SWITCHED === 'memorial-theme', 'включена тема memorial-theme');
$ok(($O['theme_mods_memorial-theme'] ?? null) === $O['theme_mods_memorial_speshu'], 'настройки темы скопированы, старые сохранены');
$ok(count(array_intersect(['mem_rewio_cron','mem_rewio_cron_once','mem_logos_cron','mem_img_job_tick'], $CRON_CLEARED)) === 4, 'старые расписания сняты');
$ok(!empty($O['memorial_plugin_after_switch']) && !empty($O['mem_core_flush']), 'отложенные задачи и пересборка ссылок назначены');
// 2) повторная активация не затирает настройки новой темы
$O['theme_mods_memorial-theme'] = ['custom_logo' => 5]; $O['stylesheet'] = 'memorial_speshu'; memorial_plugin_switchover();
$ok($O['theme_mods_memorial-theme'] === ['custom_logo' => 5], 'существующие настройки новой темы не перезаписаны');
// 3) свежая установка: ничего не трогаем
$O = ['active_plugins' => [], 'stylesheet' => 'twentytwentyfive']; $SWITCHED = null; $DEACT = [];
memorial_plugin_switchover(); $ok($SWITCHED === null && $DEACT === [], 'на сайте без старых папок тема и плагины не меняются');
