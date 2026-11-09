<?php
/**
 * Значки сервисов отзывов (Яндекс Карты, 2ГИС, Zoon, Google…) — настоящие значки сайтов этих сервисов.
 * Скачиваются один раз на сервер (папка uploads/mem-logos) — посетитель не обращается к чужим сайтам.
 * Не скачалось — показывается цветная метка с буквой.
 */
defined('ABSPATH') || exit;

/** Ключ сервиса по slug Rewio или ключу из «Мемориал → Отзывы» */
function memorial_plugin_logo_key($slug, $name = '') {
    $k = strtolower((string) $slug);
    if ($k === '' && $name !== '') { // slug неизвестен — узнаём сервис по названию
        $n = mb_strtolower($name);
        foreach (['яндекс' => 'yandex', 'yandex' => 'yandex', '2гис' => '2gis', '2gis' => '2gis', 'google' => 'google', 'гугл' => 'google', 'zoon' => 'zoon', 'зун' => 'zoon',
                  'отзовик' => 'otzovik', 'flamp' => 'flamp', 'фламп' => 'flamp', 'авито' => 'avito', 'avito' => 'avito', 'продокторов' => 'prodoctorov', 'tripadvisor' => 'tripadvisor',
                  'irecommend' => 'irecommend', 'yell' => 'yell', 'вконтакте' => 'vk', 'vk' => 'vk'] as $w => $key) {
            if (mb_strpos($n, $w) !== false) return $key;
        }
    }
    foreach (['yandex', '2gis', 'google', 'zoon', 'otzovik', 'flamp', 'avito', 'prodoctorov', 'tripadvisor', 'irecommend', 'yell', 'vk'] as $s) {
        if (strpos($k, $s) !== false) return $s;
    }
    return preg_replace('/[^a-z0-9_-]/', '', $k);
}

/** Сайт, чей значок берём */
function memorial_plugin_logo_domain($key, $base = '') {
    $known = ['yandex' => 'yandex.ru/maps', '2gis' => '2gis.ru', 'google' => 'maps.google.com', 'zoon' => 'zoon.ru', 'otzovik' => 'otzovik.com',
              'flamp' => 'flamp.ru', 'avito' => 'avito.ru', 'prodoctorov' => 'prodoctorov.ru', 'tripadvisor' => 'tripadvisor.ru',
              'irecommend' => 'irecommend.ru', 'yell' => 'yell.ru', 'vk' => 'vk.com'];
    if (isset($known[$key])) return $known[$key];
    $h = $base ? parse_url($base, PHP_URL_HOST) : '';
    return $h ?: '';
}

function memorial_plugin_logo_dir() {
    $u = wp_upload_dir();
    return ['dir' => trailingslashit($u['basedir']) . 'mem-logos/', 'url' => trailingslashit($u['baseurl']) . 'mem-logos/'];
}

/** Адрес сохранённого значка или '' */
function memorial_plugin_logo_url($key) {
    $l = get_option('mem_logos', []);
    return !empty($l[$key]['file']) ? memorial_plugin_logo_dir()['url'] . $l[$key]['file'] : '';
}

/** Скачать значок (если ещё нет; неудачные попытки — не чаще раза в сутки) */
function memorial_plugin_logo_fetch($key, $base = '') {
    if ($key === '' || $key === 'other') return '';
    $l = get_option('mem_logos', []);
    if (!empty($l[$key]['file'])) return memorial_plugin_logo_dir()['url'] . $l[$key]['file'];
    if (!empty($l[$key]['tried']) && time() - $l[$key]['tried'] < DAY_IN_SECONDS) return '';
    $dom = memorial_plugin_logo_domain($key, $base);
    $l[$key] = ['tried' => time()];
    if ($dom === '') { update_option('mem_logos', $l, false); return ''; }
    $host = explode('/', $dom)[0];
    $tries = [
        'https://www.google.com/s2/favicons?sz=64&domain_url=' . rawurlencode('https://' . $dom),
        'https://' . $host . '/favicon.ico',
    ];
    foreach ($tries as $url) {
        $r = wp_remote_get($url, ['timeout' => 8, 'redirection' => 3]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) continue;
        $type = (string) wp_remote_retrieve_header($r, 'content-type');
        $body = wp_remote_retrieve_body($r);
        if (strlen($body) < 100 || !preg_match('~image/(png|x-icon|vnd\.microsoft\.icon|svg\+xml|jpeg|webp|gif)~', $type, $tm)) continue;
        $ext = ['png' => 'png', 'x-icon' => 'ico', 'vnd.microsoft.icon' => 'ico', 'svg+xml' => 'svg', 'jpeg' => 'jpg', 'webp' => 'webp', 'gif' => 'gif'][$tm[1]];
        if ($ext === 'svg' && preg_match('~<script|on\w+=~i', $body)) continue; // svg без скриптов
        $d = memorial_plugin_logo_dir();
        if (!wp_mkdir_p($d['dir'])) break;
        $file = $key . '.' . $ext;
        if (file_put_contents($d['dir'] . $file, $body) === false) break;
        $l[$key] = ['file' => $file, 'tried' => time()];
        break;
    }
    update_option('mem_logos', $l, false);
    return !empty($l[$key]['file']) ? memorial_plugin_logo_dir()['url'] . $l[$key]['file'] : '';
}

/** Значок для вывода. Картинка встраивается в страницу один раз (data:-адрес в <style>) — без отдельных запросов,
    дальше каждый значок — пустой <span> с классом. Нет картинки — цветная метка, буква рисуется через CSS (не копируется в текст). */
function memorial_plugin_logo_html($slug, $name = '', $base = '') {
    static $printed = [];
    $key = memorial_plugin_logo_key($slug, $name);
    $file = $key ? (get_option('mem_logos', [])[$key]['file'] ?? '') : '';
    $style = '';
    if ($file && !isset($printed[$key])) {
        $path = memorial_plugin_logo_dir()['dir'] . $file;
        $mime = ['png' => 'image/png', 'ico' => 'image/x-icon', 'svg' => 'image/svg+xml', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'][pathinfo($file, PATHINFO_EXTENSION)] ?? 'image/png';
        $data = is_readable($path) && filesize($path) < 30000 ? base64_encode((string) file_get_contents($path)) : '';
        $printed[$key] = $data !== '';
        if ($data) $style = '<style>.rw-ico--' . esc_attr($key) . '{background:url(data:' . $mime . ';base64,' . $data . ') center/contain no-repeat!important}.rw-ico--' . esc_attr($key) . '::before{content:none!important}</style>';
    }
    if (!$file && $key && $key !== 'other' && empty(get_option('mem_logos', [])[$key]['tried'])) {
        // ещё не скачан — скачаем в фоне
        $q = get_option('mem_logos_queue', []);
        if (!isset($q[$key])) { $q[$key] = $base; update_option('mem_logos_queue', $q, false); if (!wp_next_scheduled('memorial_plugin_logos_cron')) wp_schedule_single_event(time(), 'memorial_plugin_logos_cron'); }
    }
    $colors = ['yandex' => '#fc3f1d', '2gis' => '#29b24a', 'google' => '#4285f4', 'zoon' => '#6c3cc8', 'otzovik' => '#e8600c', 'flamp' => '#00a0e3',
               'avito' => '#00aaff', 'prodoctorov' => '#1aa5dc', 'tripadvisor' => '#34e0a1', 'irecommend' => '#e61c5d', 'yell' => '#f5a300', 'vk' => '#0077ff'];
    $g = ['yandex' => 'Я', '2gis' => '2', 'google' => 'G', 'zoon' => 'Z', 'vk' => 'VK'][$key] ?? mb_strtoupper(mb_substr(trim($name) ?: $key ?: '?', 0, 1));
    return $style . '<span class="rw-ico' . ($key ? ' rw-ico--' . esc_attr($key) : '') . '" style="--c:' . ($colors[$key] ?? '#8a8f98') . '" data-g="' . esc_attr($g) . '" aria-hidden="true"></span>';
}

add_action('memorial_plugin_logos_cron', function () {
    $q = get_option('mem_logos_queue', []);
    delete_option('mem_logos_queue');
    foreach ((array) $q as $key => $base) memorial_plugin_logo_fetch($key, $base);
});

/* В админке страниц отзывов — сразу скачать значки известных сервисов */
add_action('admin_init', function () {
    $page = $_GET['page'] ?? '';
    if (!in_array($page, ['mem-reviews', 'mem-rewio'], true) || !current_user_can('manage_options')) return;
    foreach (['yandex', '2gis', 'google', 'zoon', 'avito'] as $k) memorial_plugin_logo_fetch($k);
    $cat = get_option('mem_rewio_catalog');
    if (is_array($cat)) foreach ($cat['sources'] ?? [] as $sid => $nm) memorial_plugin_logo_fetch(memorial_plugin_logo_key($cat['slugs'][$sid] ?? '', $nm), $cat['urls'][$sid] ?? '');
});
