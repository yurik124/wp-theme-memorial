<?php
/**
 * Кэш страниц: готовый HTML страницы сохраняется файлом в wp-content/cache/memorial/pages/
 * и отдаётся следующим посетителям без сборки из базы.
 *
 * Кому отдаём из кэша: только GET-запросы посетителей без входа в админку, без cookie комментариев/паролей,
 * без параметров в адресе (кроме меток рекламы: utm_*, yclid, gclid…). 404, поиск, предпросмотр и т. п. не кэшируются.
 * Расписание (WP-Cron) продолжает работать: страница отдаётся после того, как WordPress его проверил.
 * Очистка — speed/speed.php (mem_cache_purge).
 */
defined('ABSPATH') || exit;

function mem_page_cache_on() {
    static $on = null;
    if ($on === null) $on = !empty(mem_settings('mem_speed')['pages']['on']) && !mem_cache_conflict() && !(defined('MEM_NO_PAGE_CACHE') && MEM_NO_PAGE_CACHE);
    return $on;
}

/** Параметры в адресе, которые не меняют страницу (рекламные метки) */
function mem_page_cache_ignored_arg($k) {
    return strpos($k, 'utm_') === 0 || in_array($k, ['yclid', 'gclid', 'fbclid', 'ysclid', '_openstat', 'from', 'etext', 'roistat', 'msclkid'], true);
}

/** Можно ли этот запрос отдать из кэша / сохранить в кэш (без учёта самой страницы) */
function mem_page_cache_request_ok() {
    if (!mem_page_cache_on()) return false;
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) return false;
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) return false;
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (preg_match('~^/(wp-admin|wp-json|wp-login|wp-cron|xmlrpc|wp-content|wp-includes|feed)|\.(php|xml|txt)$|/feed/?$~', $path)) return false;
    foreach (array_keys($_GET) as $k) if (!mem_page_cache_ignored_arg((string) $k)) return false;
    foreach (array_keys($_COOKIE) as $c) {
        if (preg_match('/^(wordpress_logged_in_|wp-postpass_|comment_author_|wordpress_no_cache|wp_woocommerce_session)/', $c)) return false;
    }
    if (is_user_logged_in()) return false;
    return true;
}

function mem_page_cache_file() {
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $https = is_ssl() ? 's' : '';
    // версии плагина и темы — в ключе: после обновления старые страницы из кэша не отдаются (в них ссылки на старые стили)
    $ver = MEM_CORE_VER . '|' . wp_get_theme(get_template())->get('Version');
    return mem_cache_dir() . 'pages/' . md5($https . '|' . $host . '|' . $path . '|' . $ver) . '.html';
}

/* Отдаём готовую страницу. wp_loaded, приоритет 99 — после проверки расписания (WP-Cron, приоритет 20) */
add_action('wp_loaded', function () {
    if (!mem_page_cache_request_ok()) return;
    $f = mem_page_cache_file();
    $hours = max(1, (int) (mem_settings('mem_speed')['pages']['hours'] ?? 12));
    if (!is_file($f) || filemtime($f) + $hours * HOUR_IN_SECONDS < time()) return;
    header('Content-Type: text/html; charset=' . get_option('blog_charset', 'UTF-8'));
    header('X-Mem-Cache: HIT');
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($f)) . ' GMT');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') readfile($f);
    exit;
}, 99);

/* Сохраняем страницу, которую WordPress только что собрал */
add_action('template_redirect', function () {
    if (!mem_page_cache_request_ok()) return;
    if (is_404() || is_search() || is_feed() || is_preview() || is_robots() || is_trackback() || is_customize_preview()) return;
    if (is_singular() && post_password_required()) return;
    header('X-Mem-Cache: MISS');
    $file = mem_page_cache_file();
    ob_start(function ($html, $phase) use ($file) {
        static $partial = false; // страница ушла по частям (кто-то вызвал flush) — такую не сохраняем
        if (!($phase & PHP_OUTPUT_HANDLER_FINAL)) { $partial = true; return $html; }
        if ($partial || (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE)) return $html;
        if (http_response_code() !== 200 || stripos($html, '</html>') === false) return $html;
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) return $html;
            if (stripos($h, 'location:') === 0) return $html;
        }
        $dir = dirname($file);
        if (!is_dir($dir) && !wp_mkdir_p($dir)) return $html;
        if (!is_file(mem_cache_dir() . 'index.html')) @file_put_contents(mem_cache_dir() . 'index.html', '');
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $html) !== false) @rename($tmp, $file);
        return $html;
    });
}, 0); // раньше сжатия HTML (приоритет 1): в кэш попадает уже сжатая страница
