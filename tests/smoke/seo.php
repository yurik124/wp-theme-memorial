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
function _n($s, $p, $n, $d = null) { return $n === 1 ? $s : $p; }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
// «Мемориал → Цены → Отдельные цены» (prices/values.php): цена оградок с галочкой SEO и своя цена без неё.
function memorial_plugin_prices($section) { return ['items' => [
	['name' => 'Цена «от»: оградки', 'code' => 'cat_price_ograda', 'what' => 'cat-min', 'section' => 'modeli-ogradok-i-czeny', 'price' => '', 'bare' => 0, 'seo' => 1],
	['name' => 'Доставка', 'code' => 'dostavka', 'what' => 'custom', 'price' => '500', 'seo' => 0],
]]; }
function memorial_plugin_price_fmt($v) { return $v . "\u{00A0}₽"; }
function get_term_by($f, $v, $t) { return $v === 'modeli-ogradok-i-czeny' ? (object) ['term_id' => 7, 'name' => 'Модели оградок и цены'] : false; }
function memorial_plugin_cat_stats($id) { return $id === 7 ? [96, 12300, 69000] : [0, 0, 0]; }
function memorial_plugin_rub($n) { return number_format($n, 0, '', "\u{00A0}") . "\u{00A0}₽"; }
require dirname(__DIR__, 2) . '/memorial-plugin/seo/seo-registry.php';
require dirname(__DIR__, 2) . '/memorial-plugin/prices/values.php';

$failed = 0;
$ok = function ($c, $m) use (&$failed) { if (!$c) $failed = 1; echo ($c ? 'OK   ' : 'FAIL ') . $m . "\n"; };
// SEOPress передаёт свои массивы одинаковой длины — после нас они должны остаться одинаковыми.
$vars = apply_filters('seopress_titles_template_variables_array', ['%%sitetitle%%', '%%sep%%']);
$vals = apply_filters('seopress_titles_template_replace_array', ['Мемориал', '|']);
$ok(count($vars) === count($vals), 'SEOPress: имён и значений поровну (' . count($vars) . ')');
$map = array_combine($vars, $vals);
$ok(($map['%%mem_cat_price_ograda%%'] ?? null) === esc_attr("from 12\u{00A0}300\u{00A0}₽"), 'цена оградок: ' . ($map['%%mem_cat_price_ograda%%'] ?? '—'));
$ok(! isset($map['%%mem_dostavka%%']), 'цена без галочки «В SEO-плагин» не попадает в SEO-плагин');
$ok(memorial_plugin_value_by_key('dostavka') === "500\u{00A0}₽", 'своя цена по коду');
$ok(memorial_plugin_value_code_from('Доставка венков по Белгороду!') === 'dostavka_venkov_po_belgorodu', 'код из названия: ' . memorial_plugin_value_code_from('Доставка венков по Белгороду!'));
$filled = memorial_plugin_values_fill_codes(['other' => ['items' => [['name' => 'Лента', 'code' => ''], ['name' => 'Лента', 'code' => '']]]]);
$ok(array_column($filled['other']['items'], 'code') === ['lenta', 'lenta_2'], 'коды из одинаковых названий не совпадают');
$ok(($map['%%mem_years%%'] ?? null) === '23', 'годы работы');
$ok(($map['%%mem_phone%%'] ?? null) === '+7 (4722) 00-00-00', 'телефон');
$dyn = apply_filters('seopress_get_dynamic_variables', []);
$ok(array_keys($dyn) === array_slice($vars, 2), 'список в метабоксе — те же переменные');
exit($failed);
