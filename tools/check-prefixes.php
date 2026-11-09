<?php
/**
 * Проверка префиксов после переименования (этап 1). Работает без Composer и WPCS,
 * поэтому её можно запускать где угодно: php tools/check-prefixes.php
 *
 * Ищет по токенам PHP (комментарии не считаются):
 *  — старые имена: идентификаторы mem_* / MEM_* и переменные $mem_* в коде;
 *  — объявления функций, классов, констант и define() без префикса своей части
 *    (memorial_plugin_ / memorial_theme_);
 *  — вызовы функций memorial_plugin_* / memorial_theme_*, которые нигде не объявлены.
 * Код выхода 1, если есть замечания.
 */
$root  = dirname(__DIR__);
$parts = ['memorial-plugin' => 'memorial_plugin_', 'memorial-theme' => 'memorial_theme_'];
// Временные исключения до этапа 2: заглушки функций плагина в теме (тема без плагина).
$allowForeign = ['memorial-theme/inc/no-plugin-stubs.php'];

// Полифилы функций PHP (WPCS их тоже не требует префиксовать) и глобальные переменные ядра WordPress.
$phpPolyfills = ['array_is_list'];
$wpGlobals    = ['title', 'post', 'wp_query', 'wpdb', 'pagenow', 'typenow', 'hook_suffix', 'current_screen'];
$skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
if (defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG')) { $skip[] = T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG; $skip[] = T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG; }
$defined = []; $calls = []; $issues = [];
foreach ($parts as $dir => $prefix) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = (string) $file;
        if (substr($path, -4) !== '.php' || preg_match('~/(vendor|node_modules|build)/~', $path)) continue;
        $rel = substr($path, strlen($root) + 1);
        $t = token_get_all(file_get_contents($path)); $n = count($t);
        $depth = 0;
        for ($i = 0; $i < $n; $i++) {
            $tok = $t[$i];
            if ($tok === '{') $depth++;
            if ($tok === '}') $depth--;
            if (!is_array($tok)) continue;
            [$id, $text, $line] = $tok;
            $prev = $i - 1; while ($prev >= 0 && ($t[$prev] === '&' || (is_array($t[$prev]) && in_array($t[$prev][0], $skip, true)))) $prev--;
            $next = $i + 1; while ($next < $n && is_array($t[$next]) && in_array($t[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $next++;
            $prevId = is_array($t[$prev] ?? null) ? $t[$prev][0] : ($t[$prev] ?? null);
            if ($id === T_STRING) {
                $member = in_array($prevId, [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR], true);
                if ($member) continue;
                if (preg_match('/^(mem|MEM)_/', $text)) $issues[] = "$rel:$line старое имя $text";
                if ($prevId === T_FUNCTION) {
                    $defined[$text] = $rel;
                    if (strpos($text, $prefix) !== 0 && !in_array($rel, $allowForeign, true) && !in_array($text, $phpPolyfills, true)) $issues[] = "$rel:$line функция без префикса $prefix: $text";
                } elseif (in_array($prevId, [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                    if (stripos($text, rtrim($prefix, '_')) !== 0 && stripos($text, str_replace('_', '', ucwords($prefix, '_'))) !== 0) $issues[] = "$rel:$line класс без префикса: $text";
                } elseif ($prevId === T_CONST && $depth === 0) {
                    if (strpos($text, strtoupper($prefix)) !== 0) $issues[] = "$rel:$line константа без префикса: $text";
                } elseif (($t[$next] ?? null) === '(' && preg_match('/^memorial_(plugin|theme)_/', $text)) {
                    $calls[$text][] = "$rel:$line";
                } elseif ($text === 'define' && ($t[$next] ?? null) === '(') {
                    $a = $next + 1; while (is_array($t[$a]) && $t[$a][0] === T_WHITESPACE) $a++;
                    if (is_array($t[$a]) && $t[$a][0] === T_CONSTANT_ENCAPSED_STRING) {
                        $c = substr($t[$a][1], 1, -1);
                        if (strpos($c, strtoupper($prefix)) !== 0) $issues[] = "$rel:$line define без префикса: $c";
                    }
                }
            } elseif ($id === T_VARIABLE && preg_match('/^\$mem_/', $text)) {
                $issues[] = "$rel:$line старая переменная $text";
            } elseif ($id === T_CONSTANT_ENCAPSED_STRING && $prevId === '[' ) {
                $p2 = $prev - 1; while (is_array($t[$p2]) && $t[$p2][0] === T_WHITESPACE) $p2--;
                if (is_array($t[$p2]) && $t[$p2][1] === '$GLOBALS' && !preg_match('/^.memorial_(plugin|theme)_/', $text) && !in_array(substr($text, 1, -1), $wpGlobals, true)) $issues[] = "$rel:$line \$GLOBALS без префикса: $text";
            }
        }
    }
}
foreach ($calls as $fn => $where) if (!isset($defined[$fn])) $issues[] = "{$where[0]} вызов необъявленной функции $fn" . (count($where) > 1 ? ' (+' . (count($where) - 1) . ')' : '');
sort($issues);
echo $issues ? implode("\n", $issues) . "\n\nЗамечаний: " . count($issues) . "\n" : "Префиксы в порядке: объявлено функций " . count($defined) . ", проверено вызовов " . count($calls) . ".\n";
exit($issues ? 1 : 0);
