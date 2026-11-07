<?php
/**
 * «Мемориал → Скорость»: всё, что ускоряет сайт.
 *   — кэширование фото/стилей/скриптов в браузере и сжатие gzip (правила в .htaccess, Apache);
 *   — кэш страниц (speed/page-cache.php): готовые страницы для посетителей без входа;
 *   — кэш шорткодов (speed/shortcode-cache.php): цены и карточки из каталога не пересчитываются каждый раз;
 *   — сжатие HTML (speed/minify.php);
 *   — автосжатие CSS/JS на сервере (speed/auto-minify.php).
 * Кэш очищается сам при любой правке на сайте; вручную — кнопкой здесь или в верхней панели «Очистить кэш».
 */
defined('ABSPATH') || exit;

function mem_speed_schema() {
    return [
        'static' => ['type' => 'group', 'label' => 'Браузер посетителя', 'fields' => [
            'htaccess' => ['type' => 'checkbox', 'label' => 'Кэширование и сжатие на сервере: браузер хранит фото, шрифты, стили и скрипты до года и не скачивает их заново (заголовки Expires / Cache-Control), страницы передаются сжатыми (gzip). Только для сервера Apache — правила записываются в файл .htaccess'],
            'automin'  => ['type' => 'checkbox', 'label' => 'Сжимать стили и скрипты сайта сами: если поправить CSS или JS темы или плагина прямо на хостинге, сжатая копия (.min) пересоздастся при следующем открытии страницы'],
        ]],
        'pages' => ['type' => 'group', 'label' => 'Кэш страниц',
            'help' => 'Посетитель получает уже готовую страницу — сайт не собирает её заново из базы. Работает только для тех, кто не вошёл в админку: вы всегда видите свежую версию. Кэш очищается сам, когда вы что-то меняете (страницы, товары, цены, меню, настройки, новые отзывы).', 'fields' => [
            'on'    => ['type' => 'checkbox', 'label' => 'Включить кэш страниц'],
            'hours' => ['type' => 'select', 'label' => 'Обновлять страницу не реже чем раз в', 'options' => ['1' => '1 час', '3' => '3 часа', '6' => '6 часов', '12' => '12 часов', '24' => 'сутки', '72' => '3 суток']],
        ]],
        'sc' => ['type' => 'group', 'label' => 'Кэш блоков', 'fields' => [
            'on' => ['type' => 'checkbox', 'label' => 'Запоминать готовые блоки из каталога (цены «от … ₽», число моделей, карточки разделов, каталог на странице) — помогает и тогда, когда кэш страниц не работает (вы вошли в админку, ссылка с параметрами)'],
        ]],
    ];
}

function mem_speed_defaults() {
    $old = get_option('mem_design'); // до 3.0 галочка «Кэширование» была в «Оформлении»
    return [
        'static' => ['htaccess' => (int) !empty($old['speed']['cache']), 'automin' => 1],
        'pages'  => ['on' => 1, 'hours' => '12'],
        'sc'     => ['on' => 1],
    ];
}

/** Папка кэша: wp-content/cache/memorial/ */
function mem_cache_dir() {
    return WP_CONTENT_DIR . '/cache/memorial/';
}

/** Другой плагин кэширования страниц — свой кэш страниц не включаем, чтобы не мешать */
function mem_cache_conflict() {
    $known = ['wp-super-cache' => 'WP Super Cache', 'w3-total-cache' => 'W3 Total Cache', 'litespeed-cache' => 'LiteSpeed Cache',
              'wp-fastest-cache' => 'WP Fastest Cache', 'wp-rocket' => 'WP Rocket', 'cache-enabler' => 'Cache Enabler',
              'hummingbird-performance' => 'Hummingbird', 'breeze' => 'Breeze', 'sg-cachepress' => 'SiteGround Optimizer', 'comet-cache' => 'Comet Cache'];
    foreach ((array) get_option('active_plugins', []) as $p) {
        $slug = explode('/', $p)[0];
        if (isset($known[$slug])) return $known[$slug];
    }
    return '';
}

/** Удалить весь кэш (страницы и блоки). $later — в конце запроса, один раз, сколько бы правок ни было */
function mem_cache_purge($later = false) {
    if ($later) {
        if (!has_action('shutdown', 'mem_cache_purge_now')) add_action('shutdown', 'mem_cache_purge_now', 1);
        return;
    }
    mem_cache_purge_now();
}
function mem_cache_purge_now() {
    $dir = mem_cache_dir();
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
}

/** Сколько страниц в кэше и сколько места */
function mem_cache_stats() {
    $n = 0; $size = 0;
    foreach (glob(mem_cache_dir() . 'pages/*.html') ?: [] as $f) { $n++; $size += filesize($f); }
    return [$n, $size];
}

/* ---------- Когда очищать кэш ---------- */
/* Любое сохранение в админке (страницы, товары, цены, настройки, каталог, работы) */
add_action('init', function () {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' || !is_user_logged_in() || !current_user_can('edit_posts')) return;
    if (wp_doing_ajax() && in_array($_REQUEST['action'] ?? '', ['heartbeat', 'mem_cm_load', 'mem_sc_explain', 'mem_rewio_catalog'], true)) return;
    mem_cache_purge(true);
});
/* Изменения без админки (расписание, импорт): записи, рубрики, меню, настройки */
foreach (['save_post', 'deleted_post', 'trashed_post', 'untrashed_post', 'edit_attachment'] as $h) {
    add_action($h, function ($id) {
        if (in_array(get_post_type($id), ['mem_lead', 'revision', 'customize_changeset', 'oembed_cache'], true)) return;
        mem_cache_purge(true);
    });
}
foreach (['created_term', 'edited_term', 'delete_term', 'wp_update_nav_menu', 'switch_theme', 'activated_plugin', 'deactivated_plugin', 'upgrader_process_complete'] as $h) {
    add_action($h, fn() => mem_cache_purge(true));
}
foreach (['updated_option', 'added_option', 'deleted_option'] as $h) {
    add_action($h, function ($name) {
        if (in_array($name, ['mem_logos_queue', 'mem_rewio_status', 'mem_core_flush', 'mem_core_ver', 'cron', 'rewrite_rules'], true)) return;
        if (strpos($name, 'mem_') === 0 || strpos($name, 'theme_mods_') === 0 || strpos($name, 'widget_') === 0
            || in_array($name, ['blogname', 'blogdescription', 'site_icon', 'page_on_front', 'show_on_front', 'permalink_structure', 'sidebars_widgets', 'date_format', 'time_format', 'aioseo_options'], true)) {
            mem_cache_purge(true);
        }
    });
}

/* ---------- Правила .htaccess ---------- */
function mem_htaccess_rules() {
    return [
        '<IfModule mod_expires.c>',
        'ExpiresActive On',
        'ExpiresByType image/jpeg "access plus 1 year"',
        'ExpiresByType image/png "access plus 1 year"',
        'ExpiresByType image/webp "access plus 1 year"',
        'ExpiresByType image/avif "access plus 1 year"',
        'ExpiresByType image/gif "access plus 1 year"',
        'ExpiresByType image/svg+xml "access plus 1 year"',
        'ExpiresByType image/x-icon "access plus 1 year"',
        'ExpiresByType font/woff2 "access plus 1 year"',
        'ExpiresByType font/woff "access plus 1 year"',
        'ExpiresByType font/ttf "access plus 1 year"',
        'ExpiresByType application/font-woff2 "access plus 1 year"',
        'ExpiresByType text/css "access plus 1 year"',
        'ExpiresByType application/javascript "access plus 1 year"',
        'ExpiresByType text/javascript "access plus 1 year"',
        'ExpiresByType video/mp4 "access plus 1 year"',
        '</IfModule>',
        '<IfModule mod_headers.c>',
        '<FilesMatch "\\.(jpe?g|png|webp|avif|gif|svg|ico|woff2?|ttf|otf|css|js|mp4)$">',
        'Header set Cache-Control "public, max-age=31536000"',
        '</FilesMatch>',
        '</IfModule>',
        '<IfModule mod_deflate.c>',
        'AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml application/javascript text/javascript application/json application/xml image/svg+xml font/ttf font/otf',
        '</IfModule>',
    ];
}

function mem_htaccess_path() {
    if (!function_exists('get_home_path')) require_once ABSPATH . 'wp-admin/includes/file.php';
    return get_home_path() . '.htaccess';
}

function mem_htaccess_sync($on) {
    if (!function_exists('insert_with_markers')) require_once ABSPATH . 'wp-admin/includes/misc.php';
    $f = mem_htaccess_path();
    if (!$on && !is_file($f)) return true;
    if (is_file($f) ? !is_writable($f) : !is_writable(dirname($f))) return false;
    return insert_with_markers($f, 'Memorial cache', $on ? mem_htaccess_rules() : []);
}

function mem_htaccess_status() {
    $soft = $_SERVER['SERVER_SOFTWARE'] ?? '';
    $f = mem_htaccess_path();
    if (!function_exists('extract_from_markers')) require_once ABSPATH . 'wp-admin/includes/misc.php';
    $has = is_file($f) && in_array('ExpiresActive On', extract_from_markers($f, 'Memorial cache'), true);
    $wr = is_file($f) ? is_writable($f) : is_writable(dirname($f));
    $out = '<b>Сервер:</b> ' . esc_html($soft ?: 'не определён') . '. ';
    if (stripos($soft, 'nginx') !== false && stripos($soft, 'apache') === false) {
        $out .= 'Похоже на чистый nginx — файл .htaccess он не читает, кэширование настраивается в панели хостинга или в конфиге nginx. ';
    }
    $out .= '<b>Правила кэширования:</b> ' . ($has ? 'записаны в .htaccess.' : 'не записаны.');
    if (!$wr) $out .= ' Файл .htaccess недоступен для записи — попросите хостинг разрешить запись или добавьте правила вручную.';
    return $out;
}

add_action('update_option_mem_design', function ($old, $new) {
    mem_htaccess_sync(!empty($new['speed']['cache']));
}, 10, 2);
add_action('add_option_mem_design', function ($name, $val) {
    mem_htaccess_sync(!empty($val['speed']['cache']));
}, 10, 2);
/* Тема отключена — убираем свои правила */
add_action('switch_theme', function () { mem_htaccess_sync(false); });


add_action('update_option_mem_speed', function ($old, $new) {
    mem_htaccess_sync(!empty($new['static']['htaccess']));
}, 10, 2);
add_action('add_option_mem_speed', function ($name, $val) {
    mem_htaccess_sync(!empty($val['static']['htaccess']));
}, 10, 2);

/* ---------- Страница «Мемориал → Скорость» ---------- */
add_filter('mem_admin_pages', function ($pages) {
    $intro = mem_htaccess_status() . '<br>';
    if ($c = mem_cache_conflict()) {
        $intro .= '<b>Кэш страниц:</b> на сайте уже работает плагин «' . esc_html($c) . '» — он сам кэширует страницы, поэтому кэш страниц Мемориала не используется.';
    } else {
        [$n, $size] = mem_cache_stats();
        $dir = mem_cache_dir();
        $ok = is_dir($dir) ? is_writable($dir) : is_writable(WP_CONTENT_DIR) || (is_dir(WP_CONTENT_DIR . '/cache') && is_writable(WP_CONTENT_DIR . '/cache'));
        $intro .= '<b>Кэш страниц:</b> ' . (mem_page_cache_on() ? 'работает, сейчас готовых страниц: ' . $n . ($n ? ' (' . size_format($size) . ')' : '') . '.' : 'выключен.')
            . ($ok ? '' : ' Папка wp-content/cache недоступна для записи — попросите хостинг разрешить запись.');
    }
    $intro .= ' <a class="button" style="vertical-align:middle;margin-left:6px" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=mem_cache_clear'), 'mem_cache_clear')) . '">Очистить кэш</a>';
    if (!empty($_GET['cleared'])) $intro .= ' ✔ Кэш очищен.';
    $pages['mem_speed'] = ['slug' => 'mem-speed', 'title' => 'Скорость', 'schema' => 'mem_speed_schema', 'intro' => $intro];
    return $pages;
});

add_action('admin_post_mem_cache_clear', function () {
    if (!current_user_can('edit_posts') || !check_admin_referer('mem_cache_clear')) wp_die('Нет доступа');
    mem_cache_purge();
    $back = wp_get_referer() ?: admin_url('admin.php?page=mem-speed');
    wp_safe_redirect(add_query_arg('cleared', 1, $back));
    exit;
});

/* «Очистить кэш» в верхней панели (для администратора, на сайте и в админке) */
add_action('admin_bar_menu', function ($bar) {
    if (!current_user_can('edit_posts') || (!mem_page_cache_on() && empty(mem_settings('mem_speed')['sc']['on']))) return;
    $bar->add_node(['id' => 'mem-cache', 'title' => 'Очистить кэш', 'href' => wp_nonce_url(admin_url('admin-post.php?action=mem_cache_clear'), 'mem_cache_clear'),
        'meta' => ['title' => 'Обычно не нужно: кэш очищается сам при любой правке']]);
}, 95);

/* Страницы сайта браузер и прокси хостинга не хранят у себя без проверки: после обновления сайта посетитель сразу
   получает новую страницу, а не старую копию со ссылками на старые стили и скрипты.
   (Фото, стили, скрипты, шрифты по-прежнему хранятся долго — у них в адресе версия.) */
add_action('template_redirect', function () {
    if (is_admin() || headers_sent()) return;
    foreach (headers_list() as $h) if (stripos($h, 'cache-control:') === 0) return; // уже задано (WordPress для вошедших и т. п.)
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
}, 0);
