<?php
/**
 * Отзывы через Rewio API (https://docs.rewio.ru/) — Яндекс Карты, 2ГИС, Zoon, Google и другие площадки в одном формате.
 * Настройки: «Мемориал → Отзывы Rewio». Вывод на сайте — шорткод [mem_rewio].
 * Отзывы забираются по расписанию (WP-Cron) и хранятся у нас — страница сайта не ждёт ответа API и не ломается, если он недоступен.
 * Пользователь выбирает филиалы и площадки галочками по названиям — внутренние номера Rewio хранятся в скрытых полях.
 */
defined('ABSPATH') || exit;

const MEM_REWIO_API = 'https://api.rewio.ru/v3';

/* ---------- Настройки ---------- */
function mem_rewio_schema() {
    return [
        'api' => ['type' => 'group', 'label' => '1. Подключение', 'help' => 'Ключ — в личном кабинете app.rewio.ru (начинается с hrev_). Хватает ключа «только чтение». Ключ хранится на сервере сайта, посетителям он не виден.', 'fields' => [
            'key' => ['type' => 'text', 'label' => 'API-ключ', 'placeholder' => 'hrev_…'],
            'on'  => ['type' => 'checkbox', 'label' => 'Показывать отзывы на сайте (там, где стоит шорткод [mem_rewio])'],
        ]],
        'scope' => ['type' => 'group', 'label' => '2. Какие филиалы и площадки', 'render' => 'mem_rewio_picker', 'class' => 'mrw-card', 'fields' => [
            'objects' => ['type' => 'text', 'label' => ''],
            'sources' => ['type' => 'text', 'label' => ''],
            'per'     => ['type' => 'text', 'label' => ''],
            'tabs'    => ['type' => 'checkbox', 'label' => ''],
        ]],
        'filter' => ['type' => 'group', 'label' => '3. Какие отзывы показывать', 'fields' => [
            'min'     => ['type' => 'select', 'label' => 'Оценка', 'options' => ['1' => 'Все оценки', '3' => '3★ и выше', '4' => '4★ и выше', '5' => 'Только 5★']],
            'text'    => ['type' => 'checkbox', 'label' => 'Только с текстом (без одних звёзд)'],
            'photos'  => ['type' => 'checkbox', 'label' => 'Только с фото'],
            'months'  => ['type' => 'number', 'label' => 'Не старше, месяцев (0 — за всё время)'],
            'stop'    => ['type' => 'textarea', 'label' => 'Скрывать отзывы со словами (через запятую)', 'rows' => 2, 'placeholder' => 'например: фамилия сотрудника'],
            'fetch'   => ['type' => 'number', 'label' => 'Сколько последних отзывов загружать с каждого филиала (до 1000)'],
        ]],
        'view' => ['type' => 'group', 'label' => '4. Как выглядит на сайте', 'fields' => [
            'title'   => ['type' => 'text', 'label' => 'Заголовок блока', 'placeholder' => 'Отзывы клиентов'],
            'sort'    => ['type' => 'select', 'label' => 'Порядок', 'options' => ['new' => 'Сначала новые', 'best' => 'Сначала лучшие', 'photos' => 'Сначала с фото, затем новые']],
            'count'   => ['type' => 'number', 'label' => 'Сколько показывать сразу (остальные — кнопкой «Показать ещё»)'],
            'cols'    => ['type' => 'select', 'label' => 'Колонок на компьютере', 'options' => ['3' => '3', '2' => '2', '1' => '1 (лентой на всю ширину)']],
            'clip'    => ['type' => 'number', 'label' => 'Сворачивать длинный текст после, символов'],
            'summary' => ['type' => 'checkbox', 'label' => 'Средняя оценка и число отзывов сверху'],
            'controls'=> ['type' => 'checkbox', 'label' => 'Сортировка для посетителей: по дате и по оценке (справа над отзывами)'],
            'pfilter' => ['type' => 'checkbox', 'label' => 'Фильтр по площадкам: нажатие на площадку в сводке оценок показывает только её отзывы'],
            'reply'   => ['type' => 'checkbox', 'label' => 'Ответ компании'],
            'photo'   => ['type' => 'checkbox', 'label' => 'Фото из отзывов'],
            'source'  => ['type' => 'checkbox', 'label' => 'Название площадки со ссылкой на отзыв'],
            'date'    => ['type' => 'checkbox', 'label' => 'Дата отзыва'],
        ]],
        'cache' => ['type' => 'group', 'label' => '5. Обновление', 'help' => 'Отзывы загружаются в фоне и хранятся на сайте. Если Rewio временно недоступен, на сайте остаются последние загруженные.', 'fields' => [
            'every' => ['type' => 'select', 'label' => 'Как часто проверять новые отзывы', 'options' => ['1' => 'Каждый час', '3' => 'Каждые 3 часа', '6' => 'Каждые 6 часов', '12' => 'Дважды в день', '24' => 'Раз в сутки', '168' => 'Раз в неделю']],
        ]],
    ];
}

function mem_rewio_defaults() {
    return [
        'api'    => ['key' => '', 'on' => 0],
        'scope'  => ['objects' => '', 'sources' => '', 'per' => '', 'tabs' => 1],
        'filter' => ['min' => '4', 'text' => 1, 'photos' => 0, 'months' => 0, 'stop' => '', 'fetch' => 100],
        'view'   => ['title' => 'Отзывы клиентов', 'sort' => 'new', 'count' => 6, 'cols' => '3', 'clip' => 320,
                     'summary' => 1, 'controls' => 1, 'pfilter' => 1, 'reply' => 1, 'photo' => 1, 'source' => 1, 'date' => 1],
        'cache'  => ['every' => '6'],
    ];
}

add_filter('mem_admin_pages', function ($pages) {
    $pages['mem_rewio'] = [
        'slug' => 'mem-rewio', 'title' => 'Отзывы Rewio', 'h1' => 'Отзывы', 'hidden' => true, 'schema' => 'mem_rewio_schema',
        'intro' => 'Отзывы с Яндекс Карт, 2ГИС, Zoon и других площадок через сервис <a href="https://rewio.ru/" target="_blank" rel="noopener">Rewio</a> — карточками в оформлении сайта. '
                 . 'Вставьте ключ и сохраните, отметьте филиалы — отзывы загрузятся сами. На страницу их выводит шорткод <code>[mem_rewio]</code> (блок «Шорткод»).',
    ];
    return $pages;
});

function mem_rewio() { return mem_settings('mem_rewio'); }

/** Площадки по филиалам: «1645:1.3;1652:1» → [1645 => [1, 3], 1652 => [1]]. Филиала нет в списке — со всех его площадок */
function mem_rewio_per($s) {
    $out = [];
    foreach (explode(';', (string) $s) as $part) {
        if (!preg_match('/^\s*(\d+)\s*:\s*([\d.]*)\s*$/', $part, $m)) continue;
        $out[(int) $m[1]] = array_values(array_filter(array_map('intval', explode('.', $m[2]))));
    }
    return $out;
}

/** Значок площадки — настоящий значок сервиса (reviews/logos.php) */
function mem_rewio_icon($slug, $name = '') { return mem_logo_html($slug, $name); }
function mem_rewio_ids($s) { return array_values(array_filter(array_map('absint', preg_split('/[\s,;]+/', (string) $s)))); }

/* ---------- Запросы к API ---------- */
function mem_rewio_get($path, $args = [], $key = null) {
    $key = $key ?? trim((string) (mem_rewio()['api']['key'] ?? ''));
    if ($key === '') return new WP_Error('nokey', 'Не указан API-ключ.');
    $q = [];
    foreach ($args as $k => $v) {
        if ($v === null || $v === '' || $v === []) continue;
        foreach ((array) $v as $one) $q[] = rawurlencode($k) . '=' . rawurlencode(is_bool($one) ? ($one ? 'true' : 'false') : (string) $one);
    }
    $url = MEM_REWIO_API . $path . ($q ? '?' . implode('&', $q) : '');
    $r = wp_remote_get($url, ['timeout' => 30, 'headers' => ['X-API-Key' => $key, 'Accept' => 'application/json']]);
    if (is_wp_error($r)) return new WP_Error('net', 'Rewio не отвечает (' . $r->get_error_message() . ').');
    $code = (int) wp_remote_retrieve_response_code($r);
    $raw  = wp_remote_retrieve_body($r);
    $body = json_decode($raw, true);
    if ($code !== 200) {
        $d = is_array($body) ? ($body['detail'] ?? $body['message'] ?? $body['error'] ?? '') : '';
        if (is_array($d)) $d = $d['message'] ?? (isset($d[0]['msg']) ? implode('; ', array_map(fn($e) => ($e['loc'] ? implode('.', (array) $e['loc']) . ': ' : '') . ($e['msg'] ?? ''), $d)) : wp_json_encode($d, JSON_UNESCAPED_UNICODE));
        if ($d === '' && $raw !== '') $d = mb_substr(wp_strip_all_tags($raw), 0, 160);
        $hint = [401 => 'ключ не подошёл', 403 => 'у ключа нет доступа', 404 => 'не найдено', 422 => 'API не принял параметры запроса', 429 => 'слишком много запросов, попробуем позже'][$code] ?? 'ошибка ' . $code;
        return new WP_Error('http', 'Rewio: ' . $hint . ($d ? ' — ' . $d : '') . '.');
    }
    return is_array($body) ? $body : new WP_Error('json', 'Rewio вернул непонятный ответ.');
}

/* ---------- Список филиалов, папок и площадок аккаунта (по названиям) ---------- */
function mem_rewio_catalog_fetch($key = null) {
    $src = mem_rewio_get('/sources', [], $key);
    if (is_wp_error($src)) return $src;
    $names = [];
    $slugs = [];
    $urls = [];
    foreach ($src['data'] ?? [] as $s) { $names[(int) $s['id']] = (string) $s['name']; $slugs[(int) $s['id']] = (string) ($s['slug'] ?? ''); $urls[(int) $s['id']] = (string) ($s['base_url'] ?? ''); }
    $obj = mem_rewio_get('/objects', ['limit' => 1000], $key);
    if (is_wp_error($obj)) return $obj;
    $objects = []; $used = [];
    foreach ($obj['data'] ?? [] as $o) {
        $sids = [];
        foreach ($o['links'] ?? [] as $l) { $sid = (int) $l['source_id']; $sids[] = $sid; $used[$sid] = true; }
        $objects[] = ['id' => (int) $o['id'], 'name' => (string) $o['name'], 'count' => $o['review_count'], 'status' => (string) ($o['scrape_status'] ?? ''),
                      'demo' => !empty($o['is_demo']), 'active' => !isset($o['is_active']) || $o['is_active'], 'sources' => array_values(array_unique($sids))];
    }
    $folders = [];
    $fo = mem_rewio_get('/folders', [], $key);
    if (!is_wp_error($fo)) {
        $walk = function ($f, $prefix) use (&$walk, &$folders) {
            $ids = array_map(fn($x) => (int) $x['id'], $f['objects'] ?? []);
            foreach ($f['subfolders'] ?? [] as $sf) $ids = array_merge($ids, array_map(fn($x) => (int) $x['id'], $sf['objects'] ?? []));
            if ($ids) $folders[] = ['name' => $prefix . $f['name'], 'objects' => array_values(array_unique($ids)), 'demo' => !empty($f['is_demo'])];
            foreach ($f['subfolders'] ?? [] as $sf) $walk($sf, $f['name'] . ' / ');
        };
        foreach ($fo['data'] ?? [] as $f) $walk($f, '');
    }
    $sources = [];
    foreach (array_keys($used) as $sid) $sources[$sid] = $names[$sid] ?? 'Площадка ' . $sid;
    asort($sources);
    $cat = ['time' => time(), 'objects' => $objects, 'folders' => $folders, 'sources' => $sources, 'all_sources' => $names, 'slugs' => $slugs, 'urls' => $urls];
    foreach ($sources as $sid => $nm) mem_logo_fetch(mem_logo_key($slugs[$sid] ?? '', $nm), $urls[$sid] ?? '');
    update_option('mem_rewio_catalog', $cat, false);
    return $cat;
}

/* ---------- Хранение загруженных отзывов ----------
   В отзывах бывают эмодзи (😊🙏), а таблицы базы на многих хостингах их не принимают — запись молча не проходит.
   Поэтому храним JSON, где все не-латинские символы записаны кодами \uXXXX: такая строка сохраняется в любой базе. */
function mem_rewio_cache_set($data) {
    $json = wp_json_encode($data); // без JSON_UNESCAPED_UNICODE — только ASCII
    if (!$json) return false;
    update_option('mem_rewio_cache', $json, false);
    wp_cache_delete('mem_rewio_cache', 'options');
    return get_option('mem_rewio_cache') === $json;
}
function mem_rewio_cache_get() {
    $v = get_option('mem_rewio_cache', '');
    return is_array($v) ? $v : (json_decode((string) $v, true) ?: []);
}

/* ---------- Загрузка отзывов ---------- */
function mem_rewio_status($data) {
    update_option('mem_rewio_status', $data + ['time' => time()], false);
}

/** Забрать отзывы и сохранить у себя. Возвращает true или WP_Error (старые отзывы при ошибке остаются). */
function mem_rewio_refresh() {
    if (function_exists('set_time_limit')) @set_time_limit(180);
    $log = [];
    try {
        $o = mem_rewio(); $f = $o['filter']; $sc = $o['scope'];
        $cat = get_option('mem_rewio_catalog');
        if (!is_array($cat) || empty($cat['objects'])) {
            $cat = mem_rewio_catalog_fetch();
            if (is_wp_error($cat)) throw new Exception($cat->get_error_message());
        }
        $byId = [];
        foreach ($cat['objects'] as $ob) $byId[$ob['id']] = $ob;
        // выбранные филиалы; ничего не выбрано — все, кроме демо-примера Rewio
        $ids = array_values(array_filter(mem_rewio_ids($sc['objects']), fn($id) => isset($byId[$id])));
        if (!$ids) $ids = array_map(fn($ob) => $ob['id'], array_filter($cat['objects'], fn($ob) => !$ob['demo']));
        if (!$ids) throw new Exception('В аккаунте Rewio нет филиалов — добавьте их в кабинете app.rewio.ru.');
        $tabs = !empty($sc['tabs']) && count($ids) > 1;

        $common = [
            'limit'      => max(1, min(1000, (int) ($f['fetch'] ?: 100))),
            'sort_by'    => 'review_date', 'sort_order' => 'desc',
            'min_rating' => max(1, (int) $f['min']),
            'has_text'   => !empty($f['text']) ? true : null,
            'has_images' => !empty($f['photos']) ? true : null,
            'source_ids' => null,
        ];
        if ((int) $f['months'] > 0) $common['published_from'] = gmdate('Y-m-d', strtotime('-' . (int) $f['months'] . ' months'));

        $per = mem_rewio_per($sc['per'] ?? '');
        $global = mem_rewio_ids($sc['sources'] ?? ''); // старая общая настройка площадок
        $groups = [];
        foreach ($ids as $id) {
            $name = $byId[$id]['name'];
            $sids = $per[$id] ?? ($global ? array_values(array_intersect($global, $byId[$id]['sources'])) : []);
            $common['source_ids'] = $sids ?: null;
            $r = mem_rewio_get('/reviews', ['object_id' => $id] + $common);
            if (is_wp_error($r)) throw new Exception('«' . $name . '»: ' . $r->get_error_message());
            $reviews = array_map('mem_rewio_norm', $r['data'] ?? []);
            $note = $r['collecting']['message'] ?? ($r['access']['message'] ?? '');
            $log[] = '«' . $name . '»: загружено ' . count($reviews) . ' из ' . (int) ($r['total'] ?? count($reviews)) . ($note ? ' (' . $note . ')' : '');
            $an = mem_rewio_get('/analytics', ['object_id' => $id, 'source_ids' => $common['source_ids']]);
            $sum = is_wp_error($an) ? null : [
                'avg'   => $an['summary']['average_rating'] ?? null,
                'count' => (int) ($an['summary']['review_count'] ?? 0),
                'by'    => array_map(fn($b) => ['id' => (int) $b['source_id'], 'name' => (string) $b['source_name'], 'avg' => $b['average_rating'], 'count' => (int) $b['review_count']], $an['by_source'] ?? []),
            ];
            $groups[] = ['name' => $name, 'reviews' => $reviews, 'summary' => $sum];
        }
        if (!$tabs && count($groups) > 1) { // одной лентой: отзывы вместе, сводка — средневзвешенная
            $all = []; $cnt = 0; $sumr = 0; $by = [];
            foreach ($groups as $g) {
                $all = array_merge($all, $g['reviews']);
                if (!$g['summary']) continue;
                $cnt += $g['summary']['count']; $sumr += (float) $g['summary']['avg'] * $g['summary']['count'];
                foreach ($g['summary']['by'] as $b) {
                    $by[$b['id']] = $by[$b['id']] ?? ['id' => $b['id'], 'name' => $b['name'], 'count' => 0, 's' => 0];
                    $by[$b['id']]['count'] += $b['count']; $by[$b['id']]['s'] += (float) $b['avg'] * $b['count'];
                }
            }
            foreach ($by as &$b) { $b['avg'] = $b['count'] ? round($b['s'] / $b['count'], 2) : null; unset($b['s']); } unset($b);
            $groups = [['name' => '', 'reviews' => $all, 'summary' => $cnt ? ['avg' => round($sumr / $cnt, 2), 'count' => $cnt, 'by' => array_values($by)] : null]];
        }
        foreach ($groups as &$g) usort($g['reviews'], fn($a, $b) => strcmp($b['date'], $a['date']));
        unset($g);

        $sources = [];
        foreach ($cat['all_sources'] ?? $cat['sources'] as $sid => $nm) $sources[(int) $sid] = ['name' => $nm, 'slug' => $cat['slugs'][$sid] ?? ''];
        $n = 0; foreach ($groups as $g) $n += count($g['reviews']);
        if (!mem_rewio_cache_set(['time' => time(), 'sources' => $sources, 'groups' => $groups])) {
            throw new Exception('Отзывы получены (' . $n . '), но не сохранились в базе сайта. Сообщите разработчику темы.');
        }
        mem_rewio_status(['ok' => true, 'error' => '', 'log' => $log]);
        return true;
    } catch (Throwable $e) {
        mem_rewio_status(['ok' => false, 'error' => $e->getMessage(), 'log' => $log]);
        return new WP_Error('rewio', $e->getMessage());
    }
}

/** Копия фото к отзыву на своём сайте (uploads/mem-rewio/). '' — не скачалось (фото тогда не показываем). */
function mem_rewio_local_img($url) {
    $url = (string) $url;
    if (!preg_match('~^https?://~', $url)) return '';
    $u = wp_upload_dir();
    $dir = trailingslashit($u['basedir']) . 'mem-rewio/';
    $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $ext : 'jpg';
    $file = md5($url) . '.' . $ext;
    $local = trailingslashit($u['baseurl']) . 'mem-rewio/' . $file;
    if (is_file($dir . $file)) return $local;
    static $budget = 30;                         // не больше 30 новых фото за одно обновление (остальные — в следующий раз)
    if ($budget-- <= 0 || !wp_mkdir_p($dir)) return '';
    $r = wp_remote_get($url, ['timeout' => 6, 'redirection' => 3, 'limit_response_size' => 4 * MB_IN_BYTES]);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) return '';
    $type = (string) wp_remote_retrieve_header($r, 'content-type');
    $body = wp_remote_retrieve_body($r);
    if (strpos($type, 'image/') !== 0 || strlen($body) < 200) return '';
    return file_put_contents($dir . $file, $body) !== false ? $local : '';
}

function mem_rewio_norm($r) {
    $imgs = [];
    foreach (array_slice($r['images'] ?? [], 0, 8) as $im) {
        if (!empty($im['image']) || !empty($im['preview'])) {
            // фото к отзывам скачиваются на свой сайт — посетитель не грузит картинки со сторонних серверов
            $sm = mem_rewio_local_img($im['preview'] ?? $im['image']);
            $bg = mem_rewio_local_img($im['image'] ?? $im['preview']);
            if ($sm && $bg) $imgs[] = ['s' => $sm, 'b' => $bg];
        }
    }
    return [
        'id'     => (int) ($r['id'] ?? 0),
        'author' => trim((string) ($r['author'] ?? '')) ?: 'Гость',
        'text'   => trim((string) ($r['text'] ?? '')),
        'date'   => (string) ($r['review_date'] ?? ''),
        'rating' => isset($r['rating']) ? (float) $r['rating'] : null,
        'src'    => (int) ($r['source_id'] ?? 0),
        'slug'   => (string) ($r['source_slug'] ?? ''),
        'url'    => (string) ($r['review_url'] ?? ''),
        'reply'  => trim((string) ($r['reply_text'] ?? '')),
        'rdate'  => (string) ($r['reply_date'] ?? ''),
        'imgs'   => $imgs,
    ];
}

/* ---------- Расписание ---------- */
add_filter('cron_schedules', function ($s) {
    foreach ([1, 3, 6, 12, 24, 168] as $h) $s['mem_rewio_' . $h] = ['interval' => $h * HOUR_IN_SECONDS, 'display' => 'Rewio: каждые ' . $h . ' ч'];
    return $s;
});
add_action('mem_rewio_cron', 'mem_rewio_refresh');
add_action('mem_rewio_cron_once', 'mem_rewio_refresh');

function mem_rewio_schedule($o = null) {
    wp_clear_scheduled_hook('mem_rewio_cron');
    $o = $o ? mem_merge(mem_rewio_defaults(), $o) : mem_rewio();
    if (trim($o['api']['key']) === '') return;
    $h = (int) $o['cache']['every'] ?: 6;
    wp_schedule_event(time() + $h * HOUR_IN_SECONDS, 'mem_rewio_' . $h, 'mem_rewio_cron');
}
// сохранили настройки: новый ключ — сразу список филиалов; расписание; загрузка отзывов в фоне
add_action('update_option_mem_rewio', function ($old, $new) {
    $key = trim($new['api']['key'] ?? '');
    if ($key !== '' && $key !== trim($old['api']['key'] ?? '')) mem_rewio_catalog_fetch($key);
    mem_rewio_schedule($new);
    if ($key !== '') wp_schedule_single_event(time(), 'mem_rewio_cron_once');
}, 20, 2);
add_action('add_option_mem_rewio', function ($name, $val) {
    if (trim($val['api']['key'] ?? '') !== '') mem_rewio_catalog_fetch(trim($val['api']['key']));
    mem_rewio_schedule($val);
}, 20, 2);
/* плагин выключен — расписание снимается в memorial-core.php (register_deactivation_hook) */
add_action('init', function () { // расписание потерялось (перенос сайта и т. п.) — восстанавливаем
    if (is_admin() && !wp_next_scheduled('mem_rewio_cron') && trim(mem_rewio()['api']['key'] ?? '') !== '') mem_rewio_schedule();
});

/* ---------- Админка: выбор филиалов и площадок галочками, состояние, предпросмотр ---------- */
function mem_rewio_when($t) {
    if (!$t) return '—';
    $d = wp_date('Y-m-d', $t);
    $day = $d === wp_date('Y-m-d') ? 'сегодня' : ($d === wp_date('Y-m-d', time() - DAY_IN_SECONDS) ? 'вчера' : ($d === wp_date('Y-m-d', time() + DAY_IN_SECONDS) ? 'завтра' : wp_date('j F', $t)));
    return $day . ' в ' . wp_date('H:i', $t);
}

function mem_rewio_picker($name, $val) {
    $o = mem_rewio();
    $key = trim($o['api']['key']);
    $val = is_array($val) ? $val : [];
    // скрытые поля — сохраняются общей кнопкой «Сохранить»; галочки ниже их заполняют и сохраняют сразу
    echo '<input type="hidden" name="' . esc_attr($name) . '[objects]" value="' . esc_attr($val['objects'] ?? '') . '" data-rw-field="objects">';
    echo '<input type="hidden" name="' . esc_attr($name) . '[sources]" value="' . esc_attr($val['sources'] ?? '') . '" data-rw-field="sources">';
    if ($key === '') {
        echo '<p class="mrw__empty">Сначала вставьте API-ключ в «1. Подключение» и нажмите «Сохранить» — здесь появятся ваши филиалы.</p>';
        echo '<input type="hidden" name="' . esc_attr($name) . '[tabs]" value="' . (!empty($val['tabs']) ? 1 : 0) . '">';
        return;
    }
    $cat = get_option('mem_rewio_catalog');
    if (!is_array($cat) || empty($cat['time'])) { $cat = mem_rewio_catalog_fetch(); }
    if (is_wp_error($cat)) {
        echo '<p class="mrw__bad">Не удалось получить список филиалов: ' . esc_html($cat->get_error_message()) . '</p>';
        echo '<p><button type="button" class="button" data-rw="catalog">Попробовать ещё раз</button></p>';
        echo '<input type="hidden" name="' . esc_attr($name) . '[tabs]" value="' . (!empty($val['tabs']) ? 1 : 0) . '">';
        return;
    }
    $selO = mem_rewio_ids($val['objects'] ?? '');
    $per = mem_rewio_per($val['per'] ?? '');
    $global = mem_rewio_ids($val['sources'] ?? '');
    $sname = fn($sid) => $cat['all_sources'][$sid] ?? $cat['sources'][$sid] ?? 'Площадка';
    $sslug = fn($sid) => $cat['slugs'][$sid] ?? '';
    echo '<input type="hidden" name="' . esc_attr($name) . '[per]" value="' . esc_attr($val['per'] ?? '') . '" data-rw-field="per">';
    ?>
    <div class="mrw" data-rw-box>
      <p class="mrw__h">Филиалы и их площадки <span class="mrw__hint">Отметьте филиалы, чьи отзывы показывать (ничего не отмечено — все). У каждого филиала можно снять ненужные площадки.</span></p>
      <?php if ($cat['folders']) : ?>
        <p class="mrw__quick">Быстро:
          <?php foreach ($cat['folders'] as $fd) if (!$fd['demo']) echo '<button type="button" class="button button-small" data-rw-folder="' . esc_attr(implode(',', $fd['objects'])) . '">Все из папки «' . esc_html($fd['name']) . '»</button> '; ?>
          <button type="button" class="button-link" data-rw-folder="">снять все</button>
        </p>
      <?php endif; ?>
      <div class="mrw__objs">
        <?php foreach ($cat['objects'] as $ob) :
            $on = $per[$ob['id']] ?? ($global ? array_values(array_intersect($global, $ob['sources'])) : $ob['sources']);
            if (!$on) $on = $ob['sources']; ?>
          <div class="mrw__obj<?php echo $ob['demo'] ? ' is-demo' : ''; ?><?php echo in_array($ob['id'], $selO, true) ? ' is-on' : ''; ?>" data-rw-objbox="<?php echo (int) $ob['id']; ?>">
            <label class="mrw__objhead">
              <input type="checkbox" data-rw-obj="<?php echo (int) $ob['id']; ?>"<?php checked(in_array($ob['id'], $selO, true)); ?>>
              <b><?php echo esc_html($ob['name']); ?></b>
              <?php if ($ob['count'] !== null) echo '<span class="mrw__cnt">' . (int) $ob['count'] . ' ' . esc_html(mem_word((int) $ob['count'], 'отзыв', 'отзыва', 'отзывов')) . '</span>'; ?>
              <?php if ($ob['demo']) echo '<em class="mrw__tag">пример от Rewio</em>'; ?>
              <?php if (!$ob['active']) echo '<em class="mrw__tag">сбор выключен</em>'; ?>
              <?php if (in_array($ob['status'], ['pending', 'in_progress'], true)) echo '<em class="mrw__tag is-wait">Rewio ещё собирает отзывы</em>'; ?>
              <?php if ($ob['status'] === 'failed') echo '<em class="mrw__tag is-bad">ошибка сбора в Rewio</em>'; ?>
            </label>
            <?php if ($ob['sources']) : ?>
            <div class="mrw__srcs">
              <?php foreach ($ob['sources'] as $sid) : ?>
                <label class="mrw__src"><input type="checkbox" data-rw-src="<?php echo (int) $sid; ?>"<?php checked(in_array($sid, $on, true)); ?>> <?php echo mem_rewio_icon($sslug($sid), $sname($sid)) . ' ' . esc_html($sname($sid)); ?></label>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$cat['objects']) echo '<p class="mrw__empty">В аккаунте Rewio пока нет филиалов — добавьте их в кабинете app.rewio.ru и нажмите «Обновить список».</p>'; ?>
      </div>
      <label class="mrw__tabs"><input type="checkbox" name="<?php echo esc_attr($name); ?>[tabs]" value="1"<?php checked(!empty($val['tabs'])); ?> data-rw-tabs> Каждый филиал — отдельной вкладкой (иначе все отзывы одной лентой)</label>
      <p class="mrw__foot"><span class="mrw__saved" aria-live="polite"></span>
        <button type="button" class="button-link" data-rw="catalog">Обновить список филиалов</button>
        <span class="mrw__hint">(список из Rewio: <?php echo esc_html(mem_rewio_when($cat['time'])); ?>)</span></p>
    </div>
    <?php
}

/* Состояние и предпросмотр — над настройками */
add_action('mem_admin_after_intro', function ($opt) {
    if ($opt !== 'mem_rewio') return;
    $o = mem_rewio();
    $st = get_option('mem_rewio_status', []);
    $c  = mem_rewio_cache_get();
    $hidden = array_map('intval', (array) get_option('mem_rewio_hidden', []));
    $n = 0; foreach ($c['groups'] ?? [] as $g) $n += count($g['reviews']);
    $once = wp_next_scheduled('mem_rewio_cron_once');
    $next = $once && $once > time() ? $once : wp_next_scheduled('mem_rewio_cron');
    $key = trim($o['api']['key']) !== '';
    $state = !$key ? ['wait', 'Не подключено — вставьте API-ключ ниже и сохраните.']
        : (!empty($st) && empty($st['ok']) ? ['bad', 'Не удалось загрузить отзывы: ' . $st['error']]
        : ($n ? ['ok', 'На сайте ' . $n . ' ' . mem_word($n, 'отзыв', 'отзыва', 'отзывов') . (empty($o['api']['on']) ? ' — но показ выключен (галочка в «1. Подключение»)' : '')]
        : ['wait', 'Отзывы ещё не загружены — нажмите «Загрузить отзывы сейчас».']));
    ?>
    <section class="mem-card mrw-state is-<?php echo esc_attr($state[0]); ?>">
      <div class="mrw-state__row">
        <span class="mrw-state__dot" aria-hidden="true"></span>
        <div class="mrw-state__txt">
          <b><?php echo esc_html($state[1]); ?></b>
          <?php if ($key) : ?>
          <span>Обновлено: <?php echo esc_html(mem_rewio_when($c['time'] ?? 0)); ?><?php if ($next) echo ' · следующая проверка: ' . esc_html(mem_rewio_when($next)); ?></span>
          <?php if (!empty($st['log'])) echo '<span class="mrw-state__log">' . esc_html(implode(' · ', $st['log'])) . '</span>'; ?>
          <?php endif; ?>
        </div>
        <?php if ($key) : ?><button type="button" class="button button-primary" data-rw="refresh">Загрузить отзывы сейчас</button><?php endif; ?>
      </div>
      <p class="mrw-state__msg" aria-live="polite"></p>
      <?php if ($n) : ?>
      <details class="mrw__prev">
        <summary>Посмотреть загруженные отзывы и скрыть ненужные<?php echo $hidden ? ' (скрыто: ' . count($hidden) . ')' : ''; ?></summary>
        <?php foreach ($c['groups'] as $g) :
            if ($g['name']) echo '<h3>' . esc_html($g['name']) . '</h3>'; ?>
          <table class="widefat striped"><tbody>
          <?php foreach ($g['reviews'] as $r) : ?>
            <tr<?php echo in_array($r['id'], $hidden, true) ? ' class="is-hid"' : ''; ?>>
              <td style="width:110px"><label><input type="checkbox" data-rw-hide="<?php echo (int) $r['id']; ?>"<?php checked(in_array($r['id'], $hidden, true)); ?>> скрыть</label></td>
              <td style="width:190px"><b><?php echo esc_html($r['author']); ?></b><br><span style="color:#f5b301"><?php echo esc_html(str_repeat('★', (int) round((float) $r['rating']))); ?></span><br><small><?php echo esc_html(($c['sources'][$r['src']]['name'] ?? $r['slug']) . ' · ' . mem_rewio_date($r['date'])); ?></small></td>
              <td><?php echo esc_html(wp_trim_words($r['text'], 40, '…')); ?><?php if ($r['imgs']) echo ' <small>· фото: ' . count($r['imgs']) . '</small>'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table>
        <?php endforeach; ?>
      </details>
      <?php endif; ?>
    </section>
    <style>
      .mrw-state{border-left:4px solid #dba617}
      .mrw-state.is-ok{border-left-color:#00a32a}.mrw-state.is-bad{border-left-color:#d63638}
      .mrw-state h2{display:none}
      .mrw-state__row{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
      .mrw-state__dot{width:12px;height:12px;border-radius:50%;background:#dba617;flex:none}
      .mrw-state.is-ok .mrw-state__dot{background:#00a32a}.mrw-state.is-bad .mrw-state__dot{background:#d63638}
      .mrw-state__txt{display:flex;flex-direction:column;gap:2px;flex:1;min-width:260px}
      .mrw-state__txt span{color:#646970;font-size:13px}
      .mrw-state__log{font-size:12px!important}
      .mrw-state__msg{margin:8px 0 0;font-weight:600}.mrw-state__msg:empty{display:none}
      .mrw-state__msg.is-err{color:#b32d2e}.mrw-state__msg.is-ok{color:#00a32a}
      .mrw__prev{margin-top:12px}.mrw__prev summary{cursor:pointer;color:#2271b1;font-weight:500}
      .mrw__prev h3{margin:14px 0 6px}.mrw__prev .is-hid td{opacity:.45}
      .mrw__h{font-weight:600;font-size:14px;margin:4px 0 8px}
      .mrw__hint{font-weight:400;color:#646970;font-size:12.5px;margin-left:6px}
      .mrw__quick{margin:0 0 8px;color:#50575e}
      .mrw__objs{display:grid;gap:8px;margin-bottom:10px}
      .mrw__obj{border:1px solid #dcdcde;border-radius:8px;padding:10px 12px;background:#fff}
      .mrw__obj.is-on{border-color:#2271b1;background:#f0f6fc}
      .mrw__obj.is-demo{opacity:.75}
      .mrw__objhead{display:flex;flex-wrap:wrap;gap:4px 10px;align-items:center;cursor:pointer}
      .mrw__cnt{color:#646970;font-size:13px}
      .mrw__obj .mrw__srcs{margin:8px 0 0 26px}
      .rw-ico{--c:#888;display:inline-grid;place-items:center;width:18px;height:18px;border-radius:5px;background:var(--c);color:#fff;font:700 10px/1 system-ui,sans-serif;vertical-align:-4px}
      .rw-ico::before{content:attr(data-g)}
      .mrw__tag{font-style:normal;font-size:11.5px;background:#f0f0f1;border-radius:4px;padding:1px 6px;color:#50575e}
      .mrw__tag.is-wait{background:#fcf9e8;color:#8a6d00}.mrw__tag.is-bad{background:#fcf0f1;color:#b32d2e}
      .mrw__tabs{display:block;margin:4px 0 16px}
      .mrw__srcs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px}
      .mrw__src{border:1px solid #dcdcde;border-radius:999px;padding:4px 12px 4px 8px;cursor:pointer;background:#fff;display:inline-flex;align-items:center;gap:6px;font-size:13px}
      .mrw__src:has(input:checked){border-color:#2271b1;background:#f0f6fc}
      .mrw__src input{margin:0 4px 0 0}
      .mrw__foot{margin:10px 0 0}
      .mrw__saved{color:#00a32a;font-weight:600;margin-right:10px}
      .mrw__bad{color:#b32d2e;font-weight:600}.mrw__empty{color:#646970}
    </style>
    <script>
    jQuery(function ($) {
      const A = <?php echo wp_json_encode(['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('mem_rewio')]); ?>;
      const msg = document.querySelector('.mrw-state__msg');
      const say = (t, cls) => { if (!msg) return; msg.textContent = t; msg.className = 'mrw-state__msg ' + (cls || ''); };
      const failText = x => 'Сайт не ответил' + (x && x.status ? ' (ошибка ' + x.status + (x.status >= 500 ? ' — скорее всего, превышено время ожидания на сервере' : '') + ')' : '') + '. Попробуйте ещё раз через минуту.';
      // загрузить сейчас / обновить список
      document.addEventListener('click', e => {
        const b = e.target.closest('[data-rw]'); if (!b) return;
        const act = b.dataset.rw;
        b.disabled = true;
        if (act === 'refresh') say('Загружаю отзывы… обычно это 10–30 секунд');
        $.post(A.url, { action: 'mem_rewio_' + act, _ajax_nonce: A.nonce }).done(r => {
          b.disabled = false;
          if (!r || !r.success) { say((r && r.data) || 'Ошибка', 'is-err'); if (act === 'catalog') alert((r && r.data) || 'Ошибка'); return; }
          if (act === 'refresh') say(r.data, 'is-ok');
          setTimeout(() => location.reload(), act === 'refresh' ? 800 : 0);
        }).fail(x => { b.disabled = false; say(failText(x), 'is-err'); });
      });
      // выбор филиалов и площадок — сохраняется сразу
      const box = document.querySelector('[data-rw-box]');
      if (box) {
        const saved = box.querySelector('.mrw__saved');
        const setField = (n, v) => { const f = document.querySelector('[data-rw-field="' + n + '"]'); if (f) f.value = v; };
        const collect = () => {
          const objs = [...box.querySelectorAll('[data-rw-obj]:checked')].map(i => i.dataset.rwObj).join(',');
          // площадки по филиалам: записываем только филиалы, где сняты не все галочки
          const per = [...box.querySelectorAll('[data-rw-objbox]')].map(ob => {
            const all = [...ob.querySelectorAll('[data-rw-src]')], on = all.filter(i => i.checked);
            return on.length && on.length < all.length ? ob.dataset.rwObjbox + ':' + on.map(i => i.dataset.rwSrc).join('.') : '';
          }).filter(Boolean).join(';');
          const tabs = box.querySelector('[data-rw-tabs]')?.checked ? 1 : 0;
          box.querySelectorAll('[data-rw-objbox]').forEach(ob => ob.classList.toggle('is-on', ob.querySelector('[data-rw-obj]').checked));
          setField('objects', objs); setField('sources', ''); setField('per', per);
          saved.textContent = 'Сохраняю…';
          $.post(A.url, { action: 'mem_rewio_scope', _ajax_nonce: A.nonce, objects: objs, sources: '', per, tabs })
            .done(r => { saved.textContent = r && r.success ? 'Сохранено ✓ Отзывы обновятся в течение минуты' : 'Не сохранилось'; })
            .fail(() => { saved.textContent = 'Не сохранилось — нажмите «Сохранить» внизу'; });
        };
        box.addEventListener('change', e => {
          const t = e.target;
          if (t.matches('[data-rw-src]')) {
            const ob = t.closest('[data-rw-objbox]');
            if (!ob.querySelector('[data-rw-src]:checked')) { t.checked = true; saved.textContent = 'Хотя бы одна площадка должна остаться — иначе снимите галочку у самого филиала'; return; }
            ob.querySelector('[data-rw-obj]').checked = true; // настроили площадки — значит, филиал нужен
          }
          if (t.matches('[data-rw-obj],[data-rw-src],[data-rw-tabs]')) collect();
        });
        box.addEventListener('click', e => {
          const f = e.target.closest('[data-rw-folder]'); if (!f) return;
          const ids = f.dataset.rwFolder ? f.dataset.rwFolder.split(',') : [];
          box.querySelectorAll('[data-rw-obj]').forEach(i => { i.checked = ids.includes(i.dataset.rwObj); });
          collect();
        });
      }
      // скрыть / вернуть отзыв
      document.addEventListener('change', e => {
        const c = e.target.closest('[data-rw-hide]'); if (!c) return;
        c.closest('tr').classList.toggle('is-hid', c.checked);
        $.post(A.url, { action: 'mem_rewio_hide', _ajax_nonce: A.nonce, id: c.dataset.rwHide, on: c.checked ? 1 : 0 });
      });
    });
    </script>
    <?php
});

add_action('wp_ajax_mem_rewio_catalog', function () {
    check_ajax_referer('mem_rewio');
    if (!current_user_can('manage_options')) wp_send_json_error('Нет прав.');
    $c = mem_rewio_catalog_fetch();
    is_wp_error($c) ? wp_send_json_error($c->get_error_message()) : wp_send_json_success();
});

add_action('wp_ajax_mem_rewio_scope', function () {
    check_ajax_referer('mem_rewio');
    if (!current_user_can('manage_options')) wp_send_json_error();
    $o = get_option('mem_rewio', []);
    $o = is_array($o) ? $o : [];
    $o['scope'] = [
        'objects' => implode(',', mem_rewio_ids(wp_unslash($_POST['objects'] ?? ''))),
        'sources' => implode(',', mem_rewio_ids(wp_unslash($_POST['sources'] ?? ''))),
        'per'     => implode(';', array_map(fn($k, $v) => $k . ':' . implode('.', $v), array_keys($pp = mem_rewio_per(wp_unslash($_POST['per'] ?? ''))), $pp)),
        'tabs'    => empty($_POST['tabs']) ? 0 : 1,
    ];
    update_option('mem_rewio', $o); // запустит фоновую загрузку отзывов
    wp_send_json_success();
});

add_action('wp_ajax_mem_rewio_refresh', function () {
    check_ajax_referer('mem_rewio');
    if (!current_user_can('manage_options')) wp_send_json_error('Нет прав.');
    $r = mem_rewio_refresh();
    if (is_wp_error($r)) wp_send_json_error($r->get_error_message());
    $n = 0; foreach (mem_rewio_cache_get()['groups'] ?? [] as $g) $n += count($g['reviews']);
    wp_send_json_success('Готово: загружено ' . $n . ' ' . mem_word($n, 'отзыв', 'отзыва', 'отзывов') . '.');
});

add_action('wp_ajax_mem_rewio_hide', function () {
    check_ajax_referer('mem_rewio');
    if (!current_user_can('manage_options')) wp_send_json_error();
    $id = absint($_POST['id'] ?? 0);
    $h = array_map('intval', (array) get_option('mem_rewio_hidden', []));
    $h = !empty($_POST['on']) ? array_values(array_unique(array_merge($h, [$id]))) : array_values(array_diff($h, [$id]));
    update_option('mem_rewio_hidden', $h, false);
    wp_send_json_success();
});

/* ---------- Вывод на сайте ---------- */
function mem_rewio_date($iso) {
    $t = strtotime($iso);
    return $t ? wp_date('j F Y', $t) : '';
}

function mem_rewio_stars($r, $cls = 'rwo__stars') {
    $r = max(0, min(5, (float) $r));
    return '<span class="' . $cls . '" style="--r:' . round($r / 5 * 100, 1) . '%" role="img" aria-label="Оценка ' . esc_attr(rtrim(rtrim(number_format($r, 1, ',', ''), '0'), ',')) . ' из 5"></span>';
}

function mem_rewio_html($atts = []) {
    $o = mem_rewio();
    $v = $o['view'];
    $a = shortcode_atts(['count' => $v['count'], 'title' => $v['title'], 'cols' => $v['cols']], $atts, 'mem_rewio');
    if (empty($o['api']['on'])) return current_user_can('manage_options') ? '<p class="is-style-note">[mem_rewio]: показ отзывов выключен — галочка в «Мемориал → Отзывы Rewio → 1. Подключение». Это сообщение видно только вам.</p>' : '';
    $c = mem_rewio_cache_get();
    // давно не обновлялось (например, отключён WP-Cron) — обновим в фоне, страница не ждёт
    $every = ((int) $o['cache']['every'] ?: 6) * HOUR_IN_SECONDS;
    $tried = (int) (get_option('mem_rewio_status', [])['time'] ?? 0);
    if (!$tried || time() - $tried > $every * 2) {
        if (!wp_next_scheduled('mem_rewio_cron_once')) wp_schedule_single_event(time(), 'mem_rewio_cron_once');
    }
    if (empty($c['groups'])) return current_user_can('manage_options') ? '<p class="is-style-note">[mem_rewio]: отзывы ещё не загружены — «Мемориал → Отзывы Rewio» → «Загрузить отзывы сейчас». Это сообщение видно только вам.</p>' : '';

    $hidden = array_map('intval', (array) get_option('mem_rewio_hidden', []));
    $stop = array_filter(array_map(fn($w) => mb_strtolower(trim($w)), explode(',', (string) $o['filter']['stop'])));
    $src = $c['sources'] ?? [];
    $clip = max(80, (int) $v['clip'] ?: 320);
    $count = max(1, (int) $a['count'] ?: 6);

    $groups = [];
    foreach ($c['groups'] as $g) {
        $list = array_values(array_filter($g['reviews'], function ($r) use ($hidden, $stop) {
            if (in_array($r['id'], $hidden, true)) return false;
            $t = mb_strtolower($r['text'] . ' ' . $r['reply']);
            foreach ($stop as $w) if ($w !== '' && mb_strpos($t, $w) !== false) return false;
            return true;
        }));
        if ($v['sort'] === 'best') usort($list, fn($x, $y) => [$y['rating'], $y['date']] <=> [$x['rating'], $x['date']]);
        elseif ($v['sort'] === 'photos') usort($list, fn($x, $y) => [count($y['imgs']) > 0, $y['date']] <=> [count($x['imgs']) > 0, $x['date']]);
        if ($list) $groups[] = $g + ['list' => $list];
    }
    if (!$groups) return '';

    $uid = 'rwo' . wp_rand(100, 999);
    $h = '<section class="rwo rwo--c' . (int) $a['cols'] . '" data-rwo data-step="' . $count . '">';
    if (trim($a['title']) !== '') $h .= '<h2 class="rwo__title">' . esc_html($a['title']) . '</h2>';
    if (count($groups) > 1) {
        $h .= '<div class="rwo__tabs srow"><div class="srow__in" role="tablist" aria-label="Отзывы по филиалам">';
        foreach ($groups as $n => $g) {
            $h .= '<button type="button" role="tab" class="chip rwo__tab" id="' . $uid . 't' . $n . '" aria-controls="' . $uid . 'p' . $n . '" aria-selected="' . ($n ? 'false' : 'true') . '"' . ($n ? ' tabindex="-1"' : '') . '>'
                . esc_html($g['name'] ?: 'Филиал ' . ($n + 1)) . (isset($g['summary']['avg']) && $g['summary']['avg'] ? ' <small>★ ' . esc_html(number_format((float) $g['summary']['avg'], 1, ',', '')) . '</small>' : '') . '</button>';
        }
        $h .= '</div></div>';
    }
    foreach ($groups as $n => $g) {
        $h .= '<div class="rwo__panel" id="' . $uid . 'p' . $n . '"' . (count($groups) > 1 ? ' role="tabpanel" aria-labelledby="' . $uid . 't' . $n . '"' : '') . ($n ? ' hidden' : '') . '>';
        // сводка
        $s = $g['summary'] ?? null;
        if (!empty($v['summary']) && $s && $s['avg']) {
            $h .= '<div class="rwo__sum"><div class="rwo__avg"><b>' . esc_html(number_format((float) $s['avg'], 1, ',', '')) . '</b>' . mem_rewio_stars($s['avg'], 'rwo__stars rwo__stars--lg')
                . '<span>' . (int) $s['count'] . ' ' . esc_html(mem_word((int) $s['count'], 'отзыв', 'отзыва', 'отзывов')) . '</span></div>';
            // площадки: оценка и число отзывов; при включённом фильтре — нажатие показывает отзывы только этой площадки
            $loaded = [];
            foreach ($g['list'] as $r) if ($r['src']) $loaded[$r['src']] = ($loaded[$r['src']] ?? 0) + 1;
            $filter = !empty($v['pfilter']) && count($loaded) > 1;
            $by = array_values(array_filter($s['by'], fn($b) => $b['count']));
            if ($by && (count($by) > 1 || count($groups) === 1)) {
                $h .= '<div class="rwo__by"' . ($filter ? ' role="group" aria-label="Показать отзывы площадки"' : '') . '>';
                foreach ($by as $b) {
                    $nm = $b['name'];
                    $inner = mem_rewio_icon($src[$b['id']]['slug'] ?? '', $nm) . '<span>' . esc_html($nm) . '</span> <b>' . esc_html($b['avg'] ? number_format((float) $b['avg'], 1, ',', '') : '—') . '</b><i aria-hidden="true">★</i> <small>' . (int) $b['count'] . '</small>';
                    $h .= $filter && isset($loaded[$b['id']])
                        ? '<button type="button" class="rwo__pl" data-rwo-src="' . (int) $b['id'] . '" aria-pressed="false" title="Показать только отзывы: ' . esc_attr($nm) . '">' . $inner . '</button>'
                        : '<span class="rwo__pl">' . $inner . '</span>';
                }
                $h .= '</div>';
            }
            $h .= '</div>';
        }
        // сортировка для посетителей — справа над отзывами
        $note = '';
        if (!empty($v['controls'])) {
            $init = $v['sort'] === 'best' ? 'r' : ($v['sort'] === 'new' ? 'd' : '');
            $h .= '<div class="rwo__ctl"><p class="rwo__shown" aria-live="polite"></p><div class="rwo__sort" role="group" aria-label="Сортировка"><span>Сортировка:</span>'
                . '<button type="button" data-rwo-sort="d" aria-pressed="' . ($init === 'd' ? 'true' : 'false') . '" title="Сортировать по дате">по дате <i aria-hidden="true">↓</i></button>'
                . '<button type="button" data-rwo-sort="r" aria-pressed="' . ($init === 'r' ? 'true' : 'false') . '" title="Сортировать по оценке">по оценке <i aria-hidden="true">↓</i></button></div></div>';
        } elseif (!empty($v['pfilter'])) {
            $h .= '<div class="rwo__ctl"><p class="rwo__shown" aria-live="polite"></p></div>';
        }
        $h .= '<div class="rwo__list">';
        foreach ($g['list'] as $i => $r) {
            $name = $src[$r['src']]['name'] ?? '';
            $ini = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}]/u', '', $r['author']) ?: '?', 0, 1));
            $hue = crc32($r['author']) % 360;
            $long = mb_strlen($r['text']) > $clip;
            $h .= '<article class="rwo__card" data-d="' . esc_attr(substr($r['date'], 0, 19)) . '" data-r="' . esc_attr((string) (float) $r['rating']) . '" data-s="' . (int) $r['src'] . '"' . ($i >= $count ? ' hidden data-more' : '') . '>';
            $h .= '<header class="rwo__head"><span class="rwo__ava" style="--h:' . (int) $hue . '" aria-hidden="true">' . esc_html($ini) . '</span>'
                . '<span class="rwo__who"><b>' . esc_html($r['author']) . '</b><span class="rwo__line">'
                . ($r['rating'] ? mem_rewio_stars($r['rating']) : '')
                . (!empty($v['date']) && $r['date'] ? '<time datetime="' . esc_attr(substr($r['date'], 0, 10)) . '">' . esc_html(mem_rewio_date($r['date'])) . '</time>' : '') . '</span></span></header>';
            if ($r['text'] !== '') $h .= '<div class="rwo__text' . ($long ? ' is-clip' : '') . '">' . wpautop(esc_html($r['text'])) . '</div>'
                . ($long ? '<button type="button" class="rwo__more" aria-expanded="false">Читать полностью</button>' : '');
            if (!empty($v['photo']) && $r['imgs']) {
                $h .= '<div class="rwo__imgs">';
                foreach ($r['imgs'] as $im) $h .= '<a href="' . esc_url($im['b']) . '" data-lb><img src="' . esc_url($im['s']) . '" alt="Фото к отзыву" loading="lazy" decoding="async" referrerpolicy="no-referrer"></a>';
                $h .= '</div>';
            }
            if (!empty($v['reply']) && $r['reply'] !== '') {
                $h .= '<details class="rwo__reply"><summary>Ответ компании</summary>' . wpautop(esc_html($r['reply'])) . '</details>';
            }
            if (!empty($v['source']) && $name) {
                $ico = mem_rewio_icon($r['slug'] ?: ($src[$r['src']]['slug'] ?? ''), $name);
                $h .= '<footer class="rwo__src">'
                    . ($r['url'] ? '<a href="' . esc_url($r['url']) . '" target="_blank" rel="noopener nofollow">' . $ico . esc_html($name) . ' ↗</a>' : $ico . esc_html($name)) . '</footer>';
            }
            $h .= '</article>';
        }
        $h .= '</div>';
        if (count($g['list']) > $count) $h .= '<p class="rwo__morewrap"><button type="button" class="btn btn--line rwo__showmore">Показать ещё отзывы</button></p>';
        $h .= '</div>';
    }
    return $h . '</section>';
}
add_shortcode('mem_rewio', 'mem_rewio_html');
