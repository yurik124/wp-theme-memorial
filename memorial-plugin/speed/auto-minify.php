<?php
/**
 * Автосжатие стилей и скриптов на сервере.
 * Рядом с main.css / main.js темы и blocks.css / front.js плагина лежат сжатые копии .min.css / .min.js.
 * Если исходник поправили прямо на хостинге и он стал новее копии — копия пересоздаётся сама при следующем
 * открытии страницы (memorial_plugin_min_refresh вызывают mem_asset() темы и memorial_plugin_asset() плагина).
 *
 * Сжатие осторожное: убираются комментарии, отступы и лишние пробелы. Строки, шаблоны `…` и регулярные
 * выражения не трогаются; переносы строк в JS сохраняются (от них может зависеть смысл кода). Не получилось
 * (нет прав на запись и т. п.) — сайт просто отдаёт исходник.
 */
defined('ABSPATH') || exit;

function memorial_plugin_automin_on() {
    return !empty(memorial_plugin_settings('mem_speed')['static']['automin']);
}

/**
 * Обновить сжатую копию, если она старше исходника. true — копия свежая (можно подключать).
 * $src, $min — полные пути к файлам.
 */
function memorial_plugin_min_refresh($src, $min) {
    if (!is_file($src)) return false;
    if (is_file($min) && filemtime($min) + 300 >= filemtime($src)) return true;
    if (!memorial_plugin_automin_on() || !is_writable(dirname($min)) || (is_file($min) && !is_writable($min))) return false;
    $lock = $min . '.lock';
    if (is_file($lock) && time() - filemtime($lock) < 30) return false; // уже сжимает другой запрос
    @touch($lock);
    $code = (string) file_get_contents($src);
    $out = substr($src, -4) === '.css' ? memorial_plugin_minify_css($code) : memorial_plugin_minify_js($code);
    $ok = false;
    if ($out !== '' || trim($code) === '') {
        $tmp = $min . '.' . getmypid() . '.tmp';
        $ok = @file_put_contents($tmp, $out) !== false && @rename($tmp, $min);
        if (!$ok) @unlink($tmp);
    }
    @unlink($lock);
    return $ok;
}

/** Строка/шаблон/регулярка: длина литерала, начиная с позиции $i (кавычка или «/»). */
function memorial_plugin_min_skip_quoted($s, $i, $q) {
    $n = strlen($s);
    for ($j = $i + 1; $j < $n; $j++) {
        if ($s[$j] === '\\') { $j++; continue; }
        if ($s[$j] === $q) return $j + 1;
        if ($q !== '`' && $s[$j] === "\n") return $j; // незакрытая строка — не ломаем дальше
    }
    return $n;
}

/* ---------- CSS ---------- */
function memorial_plugin_minify_css($css) {
    $out = ''; $n = strlen($css); $ws = false;
    for ($i = 0; $i < $n; ) {
        $c = $css[$i];
        if ($c === '/' && ($css[$i + 1] ?? '') === '*') {
            $e = strpos($css, '*/', $i + 2);
            $i = $e === false ? $n : $e + 2; $ws = true; continue;
        }
        if ($c === '"' || $c === "'") {
            $j = memorial_plugin_min_skip_quoted($css, $i, $c);
            if ($ws && $out !== '') $out .= ' ';
            $out .= substr($css, $i, $j - $i); $i = $j; $ws = false; continue;
        }
        if (ctype_space($c)) { $ws = true; $i++; continue; }
        if (strpos('{};,', $c) !== false) {
            $out = rtrim($out, ' ');
            if ($c === '}') $out = rtrim($out, ';');
            $out .= $c; $i++; $ws = false;
            continue;
        }
        if ($ws && $out !== '' && strpos('{};,', substr($out, -1)) === false) $out .= ' ';
        $out .= $c; $i++; $ws = false;
    }
    return trim($out);
}

/* ---------- JS ---------- */
function memorial_plugin_minify_js($js) {
    $out = ''; $n = strlen($js); $ws = ''; // $ws: '' / ' ' / "\n" — какой пробел нужен перед следующим символом
    $last = '';     // последний значимый символ
    $word = '';     // последнее слово (для «return /re/» и т. п.)
    $tpl = [];      // вложенность шаблонов `…${ … }…`: глубина фигурных скобок внутри ${}
    $re_kw = ['return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await'];
    $flush = function () use (&$out, &$ws) { if ($ws !== '' && $out !== '') $out .= $ws; $ws = ''; };
    // перед строкой/шаблоном/регуляркой пробел нужен только после слова (return /x/), перенос строки — всегда
    $soft = function () use (&$out, &$ws, $flush) {
        $p = substr($out, -1);
        if ($ws === "\n" || ($p !== '' && (ctype_alnum($p) || $p === '_' || $p === '$'))) $flush(); else $ws = '';
    };
    for ($i = 0; $i < $n; ) {
        $c = $js[$i]; $d = $js[$i + 1] ?? '';
        // комментарии
        if ($c === '/' && $d === '/') {
            $e = strpos($js, "\n", $i); $i = $e === false ? $n : $e; continue;
        }
        if ($c === '/' && $d === '*') {
            $e = strpos($js, '*/', $i + 2); $body = substr($js, $i, ($e === false ? $n : $e + 2) - $i);
            $i = $e === false ? $n : $e + 2;
            $ws = strpos($body, "\n") !== false ? "\n" : ($ws ?: ' ');
            continue;
        }
        // пробелы и переносы
        if ($c === ' ' || $c === "\t" || $c === "\r" || $c === "\n" || $c === "\f" || $c === "\v") {
            if ($c === "\n") $ws = "\n"; elseif ($ws === '') $ws = ' ';
            $i++; continue;
        }
        // продолжение шаблона после ${ … }
        if ($c === '}' && $tpl && end($tpl) === 0) {
            array_pop($tpl);
            $ws = ''; $j = memorial_plugin_min_tpl_part($js, $i + 1, $tpl);
            $out .= substr($js, $i, $j - $i); $i = $j; $last = '`'; $word = ''; continue;
        }
        if ($c === '`') {
            $soft(); $j = memorial_plugin_min_tpl_part($js, $i + 1, $tpl);
            $out .= substr($js, $i, $j - $i); $i = $j; $last = '`'; $word = ''; continue;
        }
        if ($c === '"' || $c === "'") {
            $soft(); $j = memorial_plugin_min_skip_quoted($js, $i, $c);
            $out .= substr($js, $i, $j - $i); $i = $j; $last = $c; $word = ''; continue;
        }
        // регулярное выражение
        if ($c === '/' && ($last === '' || strpos('(,=:[!&|?{};+-*%<>~^', $last) !== false || in_array($word, $re_kw, true))) {
            $soft(); $j = $i + 1; $cls = false;
            for (; $j < $n; $j++) {
                $x = $js[$j];
                if ($x === '\\') { $j++; continue; }
                if ($x === "\n") break;
                if ($x === '[') $cls = true; elseif ($x === ']') $cls = false;
                elseif ($x === '/' && !$cls) { $j++; break; }
            }
            while ($j < $n && ctype_alpha($js[$j])) $j++; // флаги
            $out .= substr($js, $i, $j - $i); $i = $j; $last = '/'; $word = ''; continue;
        }
        // слово
        if (ctype_alnum($c) || $c === '_' || $c === '$' || ord($c) > 127) {
            $j = $i;
            while ($j < $n && (ctype_alnum($js[$j]) || $js[$j] === '_' || $js[$j] === '$' || ord($js[$j]) > 127)) $j++;
            $w = substr($js, $i, $j - $i);
            if ($ws === "\n") $flush();
            elseif ($ws !== '') { $p = substr($out, -1); if ($p !== '' && (ctype_alnum($p) || $p === '_' || $p === '$' || ord($p) > 127)) $flush(); else $ws = ''; }
            $out .= $w; $i = $j; $last = substr($w, -1); $word = $w; continue;
        }
        // знаки
        if ($tpl) {
            if ($c === '{') $tpl[count($tpl) - 1]++;
            elseif ($c === '}') $tpl[count($tpl) - 1]--;
        }
        if ($ws === "\n") $flush();
        elseif ($ws !== '') { // пробел нужен только между «+ +», «- -» и т. п.
            $p = substr($out, -1);
            if (($c === '+' || $c === '-') && $p === $c) $flush(); else $ws = '';
        }
        $out .= $c; $i++; $last = $c; $word = '';
    }
    return trim($out) . "\n";
}

/** Кусок шаблона `…` до закрывающей ` или до ${ (тогда в $tpl добавляется уровень). Возвращает позицию после куска. */
function memorial_plugin_min_tpl_part($s, $j, &$tpl) {
    $n = strlen($s);
    for (; $j < $n; $j++) {
        if ($s[$j] === '\\') { $j++; continue; }
        if ($s[$j] === '`') return $j + 1;
        if ($s[$j] === '$' && ($s[$j + 1] ?? '') === '{') { $tpl[] = 0; return $j + 2; }
    }
    return $n;
}
