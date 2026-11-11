<?php
/**
 * Дымовой тест без WordPress: переменные SEO из реестра (seo/seo-registry.php).
 * Имена и значения для SEOPress должны совпадать по числу и порядку — иначе SEOPress молча подставит
 * не те значения. Запуск: php tests/smoke/seo.php — код выхода 1 при ошибке.
 */
define('ABSPATH', '/tmp/');
$F = [];
function add_filter($h, $cb, $p = 10, $a = 1) { global $F; $F[$h][] = [$cb, $a]; return true; }
function add_action($h, $cb, $p = 10, $a = 1) { return add_filter($h, $cb, $p, $a); }
function apply_filters($h, $v, ...$args) { global $F; foreach ($F[$h] ?? [] as [$cb, $a]) $v = $cb(...array_slice(array_merge([$v], $args), 0, $a)); return $v; }
function __($s, $d = null) { return $s; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function get_queried_object_id() { return 166; }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
function memorial_plugin_opt($k) { return $k === 'phone' ? '+7 (4722) 00-00-00' : ''; }
function memorial_plugin_years() { return 23; }
function memorial_plugin_inline_value_html($key, $atts) { return $key === 'cat-price' && ($atts['cat'] ?? '') === 'modeli-ogradok-i-czeny' ? 'от&nbsp;12&nbsp;300 ₽' : null; }
require dirname(__DIR__, 2) . '/memorial-plugin/seo/seo-registry.php';

$failed = 0;
$ok = function ($c, $m) use (&$failed) { if (!$c) $failed = 1; echo ($c ? 'OK   ' : 'FAIL ') . $m . "\n"; };
// SEOPress передаёт свои массивы одинаковой длины — после нас они должны остаться одинаковыми.
$vars = apply_filters('seopress_titles_template_variables_array', ['%%sitetitle%%', '%%sep%%']);
$vals = apply_filters('seopress_titles_template_replace_array', ['Мемориал', '|']);
$ok(count($vars) === count($vals), 'SEOPress: имён и значений поровну (' . count($vars) . ')');
$map = array_combine($vars, $vals);
$ok(($map['%%mem_cat_price_ograda%%'] ?? null) === esc_attr("от\u{00A0}12\u{00A0}300 ₽"), 'цена оградок: ' . ($map['%%mem_cat_price_ograda%%'] ?? '—'));
$ok(($map['%%mem_years%%'] ?? null) === '23', 'годы работы');
$ok(($map['%%mem_phone%%'] ?? null) === '+7 (4722) 00-00-00', 'телефон');
$dyn = apply_filters('seopress_get_dynamic_variables', []);
$ok(array_keys($dyn) === array_slice($vars, 2), 'список в метабоксе — те же переменные');
exit($failed);
