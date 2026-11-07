<?php
/**
 * Относительные ссылки: адреса этого сайта в HTML страниц (картинки, ссылки, фоны) выводятся как «/wp-content/…»,
 * без домена. Картинки всегда грузятся с того сайта, который открыт, — тестовый не тянет фото с рабочего и наоборот.
 *
 * Не трогаются: адреса для поисковиков и соцсетей (<link rel="canonical"> и т. п., <meta>), разметка JSON-LD
 * и содержимое скриптов — там нужны полные адреса.
 * Свои домены (рабочий и тестовый) — «Мемориал → Скорость → Ссылки».
 */
defined('ABSPATH') || exit;

/** Домены этого сайта: текущий + указанные в настройках */
function mem_own_hosts() {
    static $h = null;
    if ($h !== null) return $h;
    $h = [strtolower((string) parse_url(home_url(), PHP_URL_HOST))];
    foreach (preg_split('~[\s,]+~', (string) (mem_settings('mem_speed')['links']['hosts'] ?? '')) as $x) {
        $x = strtolower(trim(preg_replace('~^https?://~', '', $x), '/ '));
        if ($x !== '') { $h[] = $x; $h[] = strpos($x, 'www.') === 0 ? substr($x, 4) : 'www.' . $x; }
    }
    return $h = array_values(array_unique(array_filter($h)));
}

/** Полные адреса своих доменов внутри строки → относительные */
function mem_relative_urls($s) {
    $hosts = implode('|', array_map(fn($x) => preg_quote($x, '~'), mem_own_hosts()));
    $out = preg_replace_callback('~(?:https?:)?//(?:' . $hosts . ')(?::\d+)?(/[^"\'\s<>),]*)?~i', fn($m) => ($m[1] ?? '') !== '' ? $m[1] : '/', $s);
    return $out === null ? $s : $out;
}

function mem_relative_html($html) {
    // части, которые не трогаем: скрипты, <meta>, канонические и альтернативные ссылки
    $keep = [];
    $html = preg_replace_callback('~<script\b.*?</script>|<meta\b[^>]*>|<link\b[^>]*rel=["\']?(?:canonical|alternate|shortlink|prev|next|EditURI|pingback|https://api\.w\.org/)[^>]*>~is', function ($m) use (&$keep) {
        // у скрипта меняем только адрес файла (src), содержимое не трогаем
        if (stripos($m[0], '<script') === 0) {
            $m[0] = preg_replace_callback('~^(<script\b[^>]*?\bsrc=)("[^"]*"|\'[^\']*\')~i', fn($x) => $x[1] . mem_relative_urls($x[2]), $m[0]);
        }
        $keep[] = $m[0];
        return "\x01" . (count($keep) - 1) . "\x02";
    }, $html);
    // атрибуты с адресами и url(...) в стилях
    $html = preg_replace_callback('~\b(src|srcset|href|action|poster|data-src|data-srcset|data-lb|data-href|data-bg|style)=("[^"]*"|\'[^\']*\')~i', function ($m) {
        return $m[1] . '=' . mem_relative_urls($m[2]);
    }, $html);
    $html = preg_replace_callback('~<style\b[^>]*>.*?</style>~is', fn($m) => mem_relative_urls($m[0]), $html);
    return preg_replace_callback("~\x01(\d+)\x02~", fn($m) => $keep[(int) $m[1]], $html);
}

add_action('template_redirect', function () {
    if (empty(mem_settings('mem_speed')['links']['relative']) || is_feed() || is_robots() || is_customize_preview() || wp_doing_ajax()) return;
    ob_start(function ($html, $phase) {
        static $partial = false;
        if (!($phase & PHP_OUTPUT_HANDLER_FINAL)) { $partial = true; return $html; }
        if ($partial || stripos($html, '<html') === false) return $html;
        $out = mem_relative_html($html);
        return is_string($out) && $out !== '' ? $out : $html;
    });
}, 2); // внутри кэша страниц (0) и сжатия HTML (1): в кэш попадает уже исправленная страница
