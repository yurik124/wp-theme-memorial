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
