<?php
/**
 * Разметка Schema.org «FuneralHome» на главной и в «Контактах»: название, телефоны, адреса, часы, ИНН.
 */
defined('ABSPATH') || exit;

/* Schema.org */
function mem_schema_hours($s) {
    if (!preg_match('/(\d{1,2})[:.](\d{2})\D+(\d{1,2})[:.](\d{2})/u', $s, $m)) return '';
    $t = sprintf('%02d:%s-%02d:%s', $m[1], $m[2], $m[3], $m[4]);
    if (preg_match('/ежедневно|без выходных/iu', $s)) return 'Mo-Su ' . $t;
    if (preg_match('/пн\S*\s*[-–—]\s*пт|будни/iu', $s)) return 'Mo-Fr ' . $t;
    if (preg_match('/пн\S*\s*[-–—]\s*сб/iu', $s)) return 'Mo-Sa ' . $t;
    return '';
}

add_action('wp_head', function () {
    if (!is_front_page() && !is_page_template('page-kontakty.php') && !is_page('kontakty')) {
        return;
    }
    $addrs = mem_addrs();
    $data = [
        '@context'  => 'https://schema.org',
        '@type'     => 'FuneralHome',
        'name'      => get_bloginfo('name'),
        'url'       => home_url('/'),
        'telephone' => array_map(fn($p) => mem_tel_of($p['num']), mem_phones()),
        'email'     => mem_emails(),
        'address'   => array_map(function ($a) {
            $zip = preg_match('/\b(\d{6})\b/', $a['addr'], $m) ? $m[1] : '';
            $city = preg_match('/(?:г\.|город)\s*([^,]+)/u', $a['addr'], $m) ? trim($m[1]) : 'Белгород';
            return array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => mem_short_addr($a['addr']),
                'addressLocality' => $city,
                'postalCode'      => $zip,
                'addressCountry'  => 'RU',
            ]);
        }, $addrs),
        'taxID'        => preg_replace('/\D/', '', mem_contacts()['legal']['inn'] ?? ''),
        'areaServed'   => 'Белгород',
        'openingHours' => array_values(array_unique(array_filter(array_map(fn($a) => mem_schema_hours($a['hours']), $addrs)))),
    ];
    $data = ['@context' => $data['@context'], '@type' => $data['@type'], '@id' => home_url('/#organization')] + $data;
    // логотип / фото
    $logo = get_theme_mod('custom_logo') ? wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full') : get_site_icon_url(512);
    if ($logo) { $data['logo'] = $logo; $data['image'] = $logo; }
    // юридическое название
    if ($ln = trim(mem_contacts()['legal']['name'] ?? '')) $data['legalName'] = $ln;
    // профили на картах и площадках отзывов (Яндекс Карты, 2ГИС, Zoon…) — поисковик связывает сайт с карточкой компании
    $same = [];
    foreach ((array) (get_option('mem_reviews')['sources'] ?? []) as $src) {
        $u = trim((string) ($src['link'] ?? ''));
        if (preg_match('~^https?://~', $u)) $same[] = $u;
    }
    if (($org = mem_yandex_org()) && !array_filter($same, fn($u) => strpos($u, $org) !== false)) $same[] = 'https://yandex.ru/maps/org/' . $org . '/';
    foreach (mem_messengers() as $k => $u) if ($k === 'telegram') $same[] = $u;
    $same = array_values(array_unique(array_map('esc_url_raw', $same)));
    if ($same) $data['sameAs'] = $same;
    // одно значение — без массива
    if (empty($data['taxID'])) unset($data['taxID']);
    foreach (['telephone', 'email', 'address', 'openingHours'] as $k) {
        if (count($data[$k]) === 1) $data[$k] = $data[$k][0];
        if (!$data[$k]) unset($data[$k]);
    }
    mem_ld($data);
}, 5);
