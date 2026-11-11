<?php
/**
 * Icon sources for the conversion check: every icon of memorial-plugin/core/icons-fallback.php as a
 * stand-alone SVG drawn the way the site draws it (stroke 1.5, round caps and joins — see .ico in main.css).
 * Usage: php tools/icons/export-sources.php <out-dir>
 */
define('ABSPATH', __DIR__ . '/');
function add_filter(...$a) {}
function add_action(...$a) {}
function apply_filters($hook, $value, ...$args) { return $value; }
function is_admin() { return true; } // full SVG, not a sprite reference
function wp_doing_ajax() { return false; }
function did_action($a) { return 0; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
require dirname(__DIR__, 2) . '/memorial-plugin/core/icons-fallback.php';

$out = rtrim($argv[1] ?? 'icons-src', '/');
is_dir($out) || mkdir($out, 0777, true);
$n = 0;
$names = array_unique(array_merge(['phone', 'pin', 'clock', 'mail', 'check', 'chat'], array_filter(array_keys(memorial_plugin_feat_icons()))));
foreach ($names as $name) {
    $svg = memorial_plugin_icon($name);
    if ($svg === '' || !preg_match('~<svg[^>]*>(.*)</svg>~s', $svg, $m)) continue;
    file_put_contents("$out/$name.svg", '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $m[1] . "</svg>\n");
    $n++;
}
echo "$n icons -> $out\n";
