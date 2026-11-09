<?php
/**
 * Иконки сайта (часть дизайна). memorial_theme_icon('phone') — SVG-иконка; на странице каждая иконка описана один раз
 * (набор <symbol> внизу страницы, список — $GLOBALS['memorial_plugin_icons_used']). Ими пользуются и шаблоны темы, и блоки плагина.
 * Нарисовать иконки по-другому — меняйте здесь. Без этой темы плагин подставляет свой запасной набор (core/icons-fallback.php).
 */
defined('ABSPATH') || exit;

/* SVG-иконки */
/** Иконки для пунктов «Почему нам доверяют» (выбираются в админке) */
function memorial_theme_feat_icons() {
    return ['' => 'Номер (01, 02…)', 'candle' => 'Свеча — зал, прощание', 'flower' => 'Цветок — венки', 'building' => 'Здание',
            'doc' => 'Документ', 'shield' => 'Щит с галочкой — надёжность', 'car' => 'Автомобиль — перевозка', 'clock' => 'Часы — круглосуточно',
            'phone' => 'Телефон', 'heart' => 'Сердце — забота', 'ruble' => 'Рубль — цены', 'cross' => 'Крест'];
}

/** Иконка пункта: выбранная, иначе подбирается по заголовку */
function memorial_theme_feat_icon($item) {
    $ic = $item['icon'] ?? 'auto';
    if ($ic !== 'auto' && $ic !== null) return $ic;
    $t = mb_strtolower(($item['title'] ?? '') . ' ' . ($item['text'] ?? ''));
    foreach (['candle' => 'зал|прощан|панихид', 'flower' => 'вен|цвет|флорист', 'building' => 'здани|офис', 'doc' => 'документ|компенсац|договор',
              'car' => 'транспорт|катафалк|перевоз|груз', 'clock' => 'круглосуточ|24', 'ruble' => 'цен|стоимост|рассроч', 'shield' => 'гарант|надёжн|опыт'] as $k => $re) {
        if (preg_match('/' . $re . '/u', $t)) return $k;
    }
    return '';
}

function memorial_theme_icon($name) {
    $i = [
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'pin'   => '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'mail'  => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'chat'  => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-4-.9L3 20l1.1-4.3A8.4 8.4 0 0 1 3 11.5 8.6 8.6 0 0 1 12 3a8.6 8.6 0 0 1 9 8.5z"/>',
        // для блока «Почему нам доверяют»
        'candle'   => '<path d="M12 2.5c1.6 1.9 2.2 3.1 2.2 4.2a2.2 2.2 0 0 1-4.4 0c0-1.1.6-2.3 2.2-4.2z"/><rect x="9" y="10.5" width="6" height="10.5" rx="1"/><path d="M5.5 21h13"/>',
        'flower'   => '<circle cx="12" cy="6.5" r="2.6"/><circle cx="15.6" cy="10" r="2.6"/><circle cx="8.4" cy="10" r="2.6"/><circle cx="12" cy="13.4" r="2.6"/><path d="M12 16v6M12 20.5c-1.3-1.8-3.2-2.4-4.8-2.4M12 20c1.3-1.8 3.2-2.4 4.8-2.4"/>',
        'building' => '<path d="M4 21V9l8-5.5L20 9v12"/><path d="M2 21h20M9.5 21v-5.5h5V21M8 11.5h.01M12 11.5h.01M16 11.5h.01"/>',
        'doc'      => '<path d="M14 2.5H6.5a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V8z"/><path d="M14 2.5V8h5.5M8.5 13h7M8.5 17h4.5"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5.2L12 2 4 5.2V12c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'car'      => '<path d="M2.5 16.5V10l3-4.5h9.5l4.5 4.5h2v6.5"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6M2.5 11h19"/>',
        'heart'    => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21.2l8.8-8.8a5.5 5.5 0 0 0 0-7.8z"/>',
        'ruble'    => '<path d="M8 21V3.5h5.5a4.5 4.5 0 0 1 0 9H5.5M5.5 16.5H15"/>',
        'cross'    => '<path d="M12 2.5v19M8.5 6.5h7M6.5 10.5h11M9.5 17.5l5-1.8"/>',
    ];
    if (!isset($i[$name])) return '';
    // на сайте — ссылка на общий набор иконок внизу страницы (одна иконка описана один раз, а не на каждом месте)
    $open = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    if (!is_admin() && !wp_doing_ajax() && !(defined('REST_REQUEST') && REST_REQUEST) && !did_action('wp_footer')) {
        $GLOBALS['memorial_plugin_icons_used'][$name] = $i[$name];
        return '<svg class="ico" aria-hidden="true"><use href="#i-' . $name . '"/></svg>';
    }
    return $open . $i[$name] . '</svg>';
}


/* Набор иконок, использованных на странице */
add_action('wp_footer', function () {
    if (empty($GLOBALS['memorial_plugin_icons_used'])) return;
    echo '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>';
    foreach ($GLOBALS['memorial_plugin_icons_used'] as $n => $paths) {
        echo '<symbol id="i-' . esc_attr($n) . '" viewBox="0 0 24 24">' . $paths . '</symbol>';
    }
    echo '</defs></svg>';
}, 1);

/* Иконки для плагина: Memorial Plugin спрашивает их через фильтр, а не вызывает функцию темы */
add_filter('memorial_plugin_icon_html', fn($html, $name) => memorial_theme_icon($name), 10, 2);
