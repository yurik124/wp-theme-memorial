<?php
/**
 * Пересобирает *.min.css / *.min.js после правки исходника (на сайте сжатые копии больше не пересоздаются).
 * Запуск: php tools/minify.php путь/к/файлу.css [ещё файлы…]
 */
require __DIR__ . '/minify-lib.php';
$fail = 0;
foreach (array_slice($argv, 1) as $src) {
    if (!preg_match('/\.(css|js)$/', $src) || preg_match('/\.min\.(css|js)$/', $src) || !is_file($src)) { fwrite(STDERR, "пропуск: $src\n"); $fail = 1; continue; }
    $min = preg_replace('/\.(css|js)$/', '.min.$1', $src);
    $code = file_get_contents($src);
    $out = substr($src, -4) === '.css' ? memorial_tools_minify_css($code) : memorial_tools_minify_js($code);
    file_put_contents($min, $out);
    echo "$min: " . strlen($out) . " байт\n";
}
exit($fail);
