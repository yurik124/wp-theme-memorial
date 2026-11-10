<?php
/**
 * Проверка HTML-шаблонов блоков (тема и плагин), без WordPress: php tools/check-templates.php
 *  — комментарии блоков парные, атрибуты — правильный JSON;
 *  — во встроенных <script> нет знака «меньше»: WordPress пропускает HTML шаблона через wptexturize,
 *    после «<» он принимает текст скрипта за разметку и портит его (&& → &#038;&#038;).
 */
$root  = dirname(__DIR__);
$files = array_merge(
    glob("$root/memorial-theme/templates/*.html"),
    glob("$root/memorial-theme/parts/*.html"),
    glob("$root/memorial-theme/patterns/*.php") ?: [],
    glob("$root/memorial-plugin/blocks/*/templates/*.html")
);
$issues = [];
foreach ($files as $f) {
    $rel = substr($f, strlen($root) + 1);
    $s   = file_get_contents($f);
    $stack = [];
    preg_match_all('~<!--\s+(/)?wp:([a-z0-9/-]+)(\s+(\{.*?\}))?\s*(/)?-->~s', $s, $m, PREG_SET_ORDER);
    foreach ($m as $x) {
        [$all, $close, $name] = [$x[0], $x[1], $x[2]];
        $json = $x[4] ?? '';
        $self = !empty($x[5]);
        if ($json !== '' && json_decode($json) === null) $issues[] = "$rel: неверный JSON у wp:$name";
        if ($self) continue;
        if ($close) {
            $top = array_pop($stack);
            if ($top !== $name) { $issues[] = "$rel: закрывается wp:$name, а открыт wp:" . ($top ?? '—'); break; }
        } else {
            $stack[] = $name;
        }
    }
    if ($stack) $issues[] = "$rel: не закрыты " . implode(', ', $stack);
    preg_match_all('~<script\b[^>]*>(.*?)</script>~s', $s, $sc);
    foreach ($sc[1] as $code) if (str_contains($code, '<')) $issues[] = "$rel: во встроенном скрипте есть «<» — WordPress испортит скрипт";
}
echo $issues ? implode("\n", $issues) . "\n" : 'Шаблоны в порядке: ' . count($files) . " файлов.\n";
exit($issues ? 1 : 0);
