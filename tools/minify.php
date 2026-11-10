<?php
/**
 * Пересобирает *.min.css / *.min.js тем же сжатием, что и плагин на сайте (speed/auto-minify.php).
 * Нужно, когда меняется исходный файл: на сайте копия пересоздаётся сама, только если исходник новее,
 * а при выгрузке у всех файлов одна дата. Запуск: php tools/minify.php путь/к/файлу.css [ещё файлы…]
 */
define('ABSPATH', __DIR__ . '/');
foreach (['add_action', 'add_filter', 'register_activation_hook', 'register_deactivation_hook'] as $fn) {
    if (!function_exists($fn)) eval("function $fn(...\$a) { return true; }");
}
require dirname(__DIR__) . '/memorial-plugin/speed/auto-minify.php';
$fail = 0;
foreach (array_slice($argv, 1) as $src) {
    if (!preg_match('/\.(css|js)$/', $src) || preg_match('/\.min\.(css|js)$/', $src) || !is_file($src)) { fwrite(STDERR, "пропуск: $src\n"); $fail = 1; continue; }
    $min = preg_replace('/\.(css|js)$/', '.min.$1', $src);
    $code = file_get_contents($src);
    $out = substr($src, -4) === '.css' ? memorial_plugin_minify_css($code) : memorial_plugin_minify_js($code);
    file_put_contents($min, $out);
    echo "$min: " . strlen($out) . " байт\n";
}
exit($fail);
