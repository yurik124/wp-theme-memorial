<?php
/**
 * Сжатие фото: уменьшенные копии (которые и показываются на сайте) сохраняются с качеством из настроек
 * «Мемориал → Скорость → Фото» (по умолчанию 75 — на глаз не отличить, файл в 1,5–2 раза меньше).
 *  — новые загрузки: WordPress сам делает копии с этим качеством;
 *  — уже загруженные: кнопка «Пересжать» — копии пересоздаются из оригинала в фоне, по 10 фото раз в минуту.
 *    Оригиналы не трогаются; копия заменяется, только если стала меньше.
 */
defined('ABSPATH') || exit;

function memorial_plugin_img_quality() {
    $q = (int) (memorial_plugin_settings('mem_speed')['img']['q'] ?? 75);
    return $q >= 50 && $q <= 95 ? $q : 75;
}

/* Новые загрузки: качество копий (оригинал WordPress не пересжимает) */
add_filter('wp_editor_set_quality', function ($q, $mime = '') {
    return in_array($mime, ['image/jpeg', 'image/webp', ''], true) ? memorial_plugin_img_quality() : $q;
}, 10, 2);

function memorial_plugin_img_q_field() {
    return ['type' => 'select', 'label' => 'Качество уменьшенных копий фото (на сайте показываются они, оригиналы не меняются)',
            'options' => ['90' => '90 — почти без сжатия', '82' => '82', '75' => '75 — рекомендуется: на глаз не отличить, файлы в 1,5–2 раза меньше', '68' => '68 — ещё меньше']];
}

/** Группа «Фото» в настройках «Скорость»: выбор качества + пересжатие уже загруженных */
function memorial_plugin_img_group($name, $val) {
    memorial_plugin_field($name . '[q]', memorial_plugin_img_q_field(), $val['q'] ?? '75', 2);
    echo '<h3 style="margin:1.2em 0 .4em;font-size:1em">Уже загруженные фото</h3>';
    memorial_plugin_img_job_field();
}

/* Поле в настройках «Скорость»: состояние и кнопка */
function memorial_plugin_img_job_field() {
    $st = get_option('mem_img_job', []);
    $total = (int) (wp_count_attachments('image/jpeg')->{'image/jpeg'} ?? 0) + (int) (wp_count_attachments('image/webp')->{'image/webp'} ?? 0);
    $done = (int) ($st['done'] ?? 0);
    echo '<div class="mem-img-job">';
    if (!empty($st['running'])) {
        echo '<p>Идёт пересжатие: обработано <b>' . $done . '</b> из ' . (int) ($st['total'] ?? $total) . ' фото, сэкономлено <b>' . size_format((int) ($st['saved'] ?? 0)) . '</b>. '
           . 'Работает в фоне — страницу можно закрыть.</p>';
    } elseif ($done) {
        echo '<p>Последнее пересжатие: ' . $done . ' фото, сэкономлено <b>' . size_format((int) ($st['saved'] ?? 0)) . '</b> (качество ' . (int) ($st['q'] ?? 0) . ').</p>';
    }
    if (empty($st['running'])) {
        echo '<p><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=mem_img_job'), 'mem_img_job')) . '">Пересжать уже загруженные фото (' . $total . ')</a></p>'
           . '<p class="description">Копии фото пересоздаются из оригиналов с выбранным качеством. Оригиналы не меняются. Занимает несколько минут — идёт в фоне.</p>';
    }
    echo '</div>';
}

add_action('admin_post_mem_img_job', function () {
    if (!current_user_can('manage_options') || !check_admin_referer('mem_img_job')) wp_die('Нет доступа');
    $ids = get_posts(['post_type' => 'attachment', 'post_mime_type' => ['image/jpeg', 'image/webp'], 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
    update_option('mem_img_job', ['running' => 1, 'q' => memorial_plugin_img_quality(), 'ids' => $ids, 'total' => count($ids), 'done' => 0, 'saved' => 0], false);
    if (!wp_next_scheduled('memorial_plugin_img_job_tick')) wp_schedule_event(time() + 5, 'mem_minute', 'memorial_plugin_img_job_tick');
    wp_safe_redirect(admin_url('admin.php?page=mem-speed'));
    exit;
});

add_filter('cron_schedules', function ($s) {
    $s['mem_minute'] = ['interval' => 60, 'display' => 'Раз в минуту'];
    return $s;
});

add_action('memorial_plugin_img_job_tick', function () {
    $st = get_option('mem_img_job', []);
    if (empty($st['running'])) { wp_clear_scheduled_hook('memorial_plugin_img_job_tick'); return; }
    $t0 = microtime(true);
    while ($st['ids'] && microtime(true) - $t0 < 20) {
        $id = array_shift($st['ids']);
        $st['saved'] += memorial_plugin_img_recompress($id, (int) $st['q'], !empty($st['fix']));
        $st['done']++;
        if ($st['done'] % 10 === 0) break;
    }
    if (!$st['ids']) { $st['running'] = 0; wp_clear_scheduled_hook('memorial_plugin_img_job_tick'); if (function_exists('memorial_plugin_cache_purge')) memorial_plugin_cache_purge(true); }
    update_option('mem_img_job', $st, false);
});

/**
 * Пересоздать уменьшенные копии одного фото с качеством $q; вернуть, сколько байт сэкономлено.
 * $fix — ремонт: копия заменяется в любом случае (даже если не стала меньше).
 */
function memorial_plugin_img_recompress($id, $q, $fix = false) {
    if (!$fix && (int) get_post_meta($id, '_mem_img_q', true) === $q && get_post_meta($id, '_mem_img_rot', true)) return 0;
    $meta = wp_get_attachment_metadata($id);
    $file = get_attached_file($id);
    if (!$file || !is_file($file) || empty($meta['sizes'])) return 0;
    $src = !empty($meta['original_image']) && is_file(dirname($file) . '/' . $meta['original_image']) ? dirname($file) . '/' . $meta['original_image'] : $file;
    $reg = wp_get_registered_image_subsizes();
    $saved = 0;
    foreach ($meta['sizes'] as $name => $s) {
        $dst = dirname($file) . '/' . $s['file'];
        if (!is_file($dst)) continue;
        $ed = wp_get_image_editor($src);
        if (is_wp_error($ed)) break;
        // фото с телефона: поворот записан меткой EXIF — поворачиваем, как WordPress при загрузке
        // (без этого копии из исходника получались лёжа на боку — ошибка 1.9.0, исправлена в 1.11.2)
        if (method_exists($ed, 'maybe_exif_rotate')) $ed->maybe_exif_rotate();
        $crop = !empty($reg[$name]['crop']) ? $reg[$name]['crop'] : false;
        if (is_wp_error($ed->resize($s['width'], $s['height'], $crop))) continue;
        $ed->set_quality($q);   // после resize: он сам заново выставляет качество по умолчанию
        $tmp = $dst . '.mem-tmp.' . pathinfo($dst, PATHINFO_EXTENSION);
        $r = $ed->save($tmp, $s['mime-type'] ?? null);
        if (is_wp_error($r) || !is_file($r['path'])) continue;
        $old = filesize($dst); $new = filesize($r['path']);
        if ($new > 0 && ($fix || $new < $old * 0.97)) { @rename($r['path'], $dst); $saved += $old - $new; }
        else @unlink($r['path']);
    }
    update_post_meta($id, '_mem_img_q', $q);
    update_post_meta($id, '_mem_img_rot', 1);
    update_post_meta($id, '_mem_img_v', time());   // новая версия копий — браузеры не возьмут старые из своей памяти
    return $saved;
}

/* Ремонт после 1.9.0–1.11.1: копии, пересжатые без учёта поворота, пересоздаются заново (один раз, в фоне) */
add_action('admin_init', function () {
    if (get_option('mem_img_rotfix') || !current_user_can('manage_options')) return;
    update_option('mem_img_rotfix', 1, false);
    global $wpdb;
    $ids = array_map('intval', $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mem_img_q'"));
    if (!$ids) return;
    $st = get_option('mem_img_job', []);
    if (!empty($st['running'])) $ids = array_values(array_unique(array_merge($ids, (array) $st['ids'])));
    update_option('mem_img_job', ['running' => 1, 'fix' => 1, 'q' => memorial_plugin_img_quality(), 'ids' => $ids, 'total' => count($ids), 'done' => 0, 'saved' => 0], false);
    if (!wp_next_scheduled('memorial_plugin_img_job_tick')) wp_schedule_event(time() + 5, 'mem_minute', 'memorial_plugin_img_job_tick');
});

/*
 * Копии пересоздаются под теми же именами файлов, а браузер хранит картинки долго (до года).
 * Поэтому к адресу пересозданных копий добавляется ?v=… — браузер загрузит новую версию, а не покажет старую.
 */
function memorial_plugin_img_ver_url($url, $id) {
    $v = (int) get_post_meta($id, '_mem_img_v', true);
    return $v && $url && strpos($url, '?') === false ? $url . '?v=' . $v : $url;
}
add_filter('wp_get_attachment_image_src', function ($img, $id) {
    if ($img && !empty($img[0])) $img[0] = memorial_plugin_img_ver_url($img[0], $id);
    return $img;
}, 10, 2);
add_filter('wp_calculate_image_srcset', function ($sources, $size, $src, $meta, $id) {
    foreach ((array) $sources as $w => $s) $sources[$w]['url'] = memorial_plugin_img_ver_url($s['url'], $id);
    return $sources;
}, 10, 5);

/* Разово: фото, уже пересозданные в 1.11.2, получают версию — чтобы браузеры, где осталась старая (повёрнутая) копия, загрузили новую */
add_action('admin_init', function () {
    if (get_option('mem_img_vfix') || !current_user_can('manage_options')) return;
    update_option('mem_img_vfix', 1, false);
    global $wpdb;
    $t = time();
    foreach ($wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mem_img_q'") as $id) {
        if (!get_post_meta($id, '_mem_img_v', true)) update_post_meta((int) $id, '_mem_img_v', $t);
    }
    if (function_exists('memorial_plugin_cache_purge')) memorial_plugin_cache_purge(true);
});
