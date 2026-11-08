<?php
/**
 * Общие помощники: телефон, годы работы, иконки (SVG-набор внизу страницы).
 */
defined('ABSPATH') || exit;

/* Контакты и тексты главной редактируются в «Мемориал → Контакты / Главная страница» (core/settings.php).
 * mem_opt() оставлена для совместимости: отдаёт основной телефон, первую почту и т.д. */
function mem_opt($key) {
    $c = mem_contacts();
    $a = mem_addrs();
    $p = mem_phones();
    switch ($key) {
        case 'phone':    return $p ? $p[0]['num'] : '';
        case 'email':    return mem_emails()[0] ?? '';
        case 'addr1':    return $a[0]['addr'] ?? '';
        case 'addr2':    return $a[1]['addr'] ?? '';
        case 'hours':    return $a[0]['hours'] ?? '';
        case 'founded':  return (int) $c['founded'];
        case 'hero_img': return mem_home('hero')['img'] ?? '';
    }
    return '';
}

function mem_tel() {
    return mem_tel_of(mem_opt('phone'));
}

function mem_years() {
    $f = (int) mem_contacts()['founded'];
    return $f ? max(1, (int) date('Y') - $f) : 0;
}

/* Старый раздел «Контакты Мемориал» в «Настроить» больше не нужен — ведём туда, где теперь всё редактируется */
add_action('customize_register', function ($c) {
    $c->add_section('mem_moved', [
        'title'       => 'Контакты и тексты',
        'priority'    => 30,
        'description' => 'Контакты, адреса и тексты главной теперь редактируются в админке: <a href="' . esc_url(admin_url('admin.php?page=mem-contacts')) . '" target="_top">Мемориал → Контакты</a> и <a href="' . esc_url(admin_url('admin.php?page=mem-home')) . '" target="_top">Мемориал → Главная страница</a>.',
    ]);
    $c->add_setting('mem_moved_note', ['sanitize_callback' => '__return_empty_string']);
    $c->add_control('mem_moved_note', ['section' => 'mem_moved', 'type' => 'hidden']);
});

/**
 * Картинка из медиатеки нужного размера по адресу оригинала (для фото, заданных в настройках адресом).
 * Не из медиатеки или размера нет — тот же адрес. Так блоки не грузят оригиналы по 200+ КБ.
 */
function mem_img_sized($url, $size = 'medium_large') {
    if (!$url) return $url;
    static $memo = [];
    $k = $url . '|' . $size;
    if (isset($memo[$k])) return $memo[$k];
    $id = mem_attachment_id_by_url($url);
    $s = $id ? wp_get_attachment_image_url($id, $size) : '';
    return $memo[$k] = $s ?: $url;
}

/** Номер фото в медиатеке по адресу — по пути после /uploads/, поэтому находит и адреса другого домена (рабочего на тестовом) */
function mem_attachment_id_by_url($url) {
    if (!preg_match('~/uploads/(.+?\.(?:jpe?g|png|gif|webp|avif))(?:\?.*)?$~i', (string) $url, $m)) return 0;
    $rel = preg_replace('/-\d+x\d+(?=\.\w+$)/', '', rawurldecode($m[1]));
    global $wpdb;
    $id = (int) $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN (%s, %s) LIMIT 1",
        $rel, preg_replace('/(\.\w+)$/', '-scaled$1', $rel)));
    return $id;
}
