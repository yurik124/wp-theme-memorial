<?php
/**
 * Общие помощники: телефон, годы работы, иконки (SVG-набор внизу страницы).
 */
defined('ABSPATH') || exit;

/* Контакты и тексты главной редактируются в «Мемориал → Контакты / Главная страница» (core/settings.php).
 * memorial_plugin_opt() оставлена для совместимости: отдаёт основной телефон, первую почту и т.д. */
function memorial_plugin_opt($key) {
    $c = memorial_plugin_contacts();
    $a = memorial_plugin_addrs();
    $p = memorial_plugin_phones();
    switch ($key) {
        case 'phone':    return $p ? $p[0]['num'] : '';
        case 'email':    return memorial_plugin_emails()[0] ?? '';
        case 'addr1':    return $a[0]['addr'] ?? '';
        case 'addr2':    return $a[1]['addr'] ?? '';
        case 'hours':    return $a[0]['hours'] ?? '';
        case 'founded':  return (int) $c['founded'];
        case 'hero_img': return memorial_plugin_home('hero')['img'] ?? '';
    }
    return '';
}

/** Лавровая графика от темы (фильтр memorial_plugin_laurel); пусто, если тема её не рисует */
function memorial_plugin_laurel($kind, $arg = '') {
    return (string) apply_filters('memorial_plugin_laurel', '', $kind, $arg);
}

function memorial_plugin_tel() {
    return memorial_plugin_tel_of(memorial_plugin_opt('phone'));
}

function memorial_plugin_years() {
    $f = (int) memorial_plugin_contacts()['founded'];
    return $f ? max(1, (int) date('Y') - $f) : 0;
}

/* Старый раздел «Контакты Мемориал» в «Настроить» больше не нужен — ведём туда, где теперь всё редактируется */
add_action('customize_register', function ($c) {
    $c->add_section('mem_moved', [
        'title'       => __( 'Contacts and texts', 'memorial-plugin' ),
        'priority'    => 30,
        'description' => ( esc_html__( 'Contacts, addresses and the texts of the home page are now edited in the admin:', 'memorial-plugin' ) . ' <a href="' ) . esc_url(admin_url('admin.php?page=mem-contacts')) . ( '" target="_top">' . esc_html__( 'Memorial → Contacts', 'memorial-plugin' ) . '</a> ' . esc_html__( 'and', 'memorial-plugin' ) . ' <a href="' ) . esc_url(admin_url('admin.php?page=mem-home')) . ( '" target="_top">' . esc_html__( 'Memorial → Home page', 'memorial-plugin' ) . '</a>.' ),
    ]);
    $c->add_setting('mem_moved_note', ['sanitize_callback' => '__return_empty_string']);
    $c->add_control('mem_moved_note', ['section' => 'mem_moved', 'type' => 'hidden']);
});

/**
 * Картинка из медиатеки нужного размера по адресу оригинала (для фото, заданных в настройках адресом).
 * Не из медиатеки или размера нет — тот же адрес. Так блоки не грузят оригиналы по 200+ КБ.
 */
function memorial_plugin_img_sized($url, $size = 'medium_large') {
    if (!$url) return $url;
    static $memo = [];
    $k = $url . '|' . $size;
    if (isset($memo[$k])) return $memo[$k];
    $id = memorial_plugin_attachment_id_by_url($url);
    $s = $id ? wp_get_attachment_image_url($id, $size) : '';
    return $memo[$k] = $s ?: $url;
}

/** Номер фото в медиатеке по адресу — по пути после /uploads/, поэтому находит и адреса другого домена (рабочего на тестовом) */
function memorial_plugin_attachment_id_by_url($url) {
    if (!preg_match('~/uploads/(.+?\.(?:jpe?g|png|gif|webp|avif))(?:\?.*)?$~i', (string) $url, $m)) return 0;
    $rel = preg_replace('/-\d+x\d+(?=\.\w+$)/', '', rawurldecode($m[1]));
    global $wpdb;
    $id = (int) $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN (%s, %s) LIMIT 1",
        $rel, preg_replace('/(\.\w+)$/', '-scaled$1', $rel)));
    return $id;
}
