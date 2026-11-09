<?php
defined('ABSPATH') || exit;

/* Пакеты услуг — редактируются в «Мемориал → Главная страница → Пакеты услуг» */
function memorial_plugin_packages() {
    $out = [];
    foreach (memorial_plugin_prices('packages')['items'] ?? [] as $p) {
        $items = [];
        foreach ($p['rows'] ?? [] as $r) {
            if (trim($r['name'] ?? '') === '') continue;
            $items[] = [$r['name'], (int) ($r['price'] ?? 0), !empty($r['key'])];
        }
        $out[] = ['name' => $p['name'] ?? '', 'featured' => !empty($p['featured']), 'items' => $items];
    }
    return apply_filters('memorial_plugin_packages', $out);
}

function memorial_plugin_rub($n) {
    return number_format($n, 0, '', "\u{202F}") . "\u{00A0}₽";
}
