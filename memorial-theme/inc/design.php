<?php
/**
 * Оформление сайта — стили сайта WordPress (Внешний вид → Редактор → Стили).
 *
 * Палитра, шрифты и размеры — в theme.json, готовые схемы — styles/*.json («лавр» — сама тема,
 * «бронза», «камень», «тёмная»). Прежняя страница «Мемориал → Оформление» (настройка mem_design) убрана:
 * её значения один раз переносятся в стили сайта (inc/design-migrate.php), сама настройка остаётся
 * в базе для отката до 2.1.0.
 *
 * Здесь — только то, что нужно переносу: старые схемы и шрифты, значения по умолчанию, контраст.
 */
defined('ABSPATH') || exit;

/** Цвета, которыми окрашен сайт. Ключ = CSS-переменная. */
function memorial_theme_color_keys() {
    return [
        'dark'   => ['Фон шапки-плашек и подвала (в светлой схеме — светлый)', '#f2efe6'],
        'dark2'  => ['Фон плашек — второй оттенок', '#e6ebdf'],
        'gold'   => ['Акцент: кнопки, иконки, лавр', '#5e7052'],
        'gold2'  => ['Светлый акцент: орнамент, разделители', '#a9b796'],
        'gold3'  => ['Тёмный акцент: ссылки, надзаголовки, наведение', '#3f4d36'],
        'bg'     => ['Фон страницы', '#fbfaf6'],
        'bg2'    => ['Фон чередующихся блоков', '#f2efe6'],
        'ink'    => ['Основной текст', '#2a2c28'],
        'mute'   => ['Второстепенный текст', '#66675e'],
        'line'   => ['Рамки и разделители', '#e2ded2'],
        'sage'   => ['Шалфейная подложка: «Что делать», цифры, подвал', '#e6ebdf'],
        'bronze' => ['Бронза: тонкие линии, ягоды лавра (в малых дозах)', '#a88a5a'],
    ];
}

/** Готовые цветовые схемы (все светлые: тёмный цвет — только для текста) */
function memorial_theme_color_presets() {
    $k = array_keys(memorial_theme_color_keys());
    $p = fn($name, ...$c) => ['name' => $name, 'c' => array_combine($k, $c)];
    //                                                  dark       dark2      gold       gold2      gold3      bg         bg2        ink        mute       line       sage       bronze
    return [
        'laurel'        => $p('Светлая память — лавр (основная)', '#f2efe6', '#e6ebdf', '#5e7052', '#a9b796', '#3f4d36', '#fbfaf6', '#f2efe6', '#2a2c28', '#66675e', '#e2ded2', '#e6ebdf', '#a88a5a'),
        'laurel_bronze' => $p('Светлая память — бронза',          '#f2efe6', '#ece6da', '#8c6f45', '#cdbb98', '#5c4628', '#fbfaf6', '#f2efe6', '#2a2c28', '#66675e', '#e2ded2', '#ede7db', '#a88a5a'),
        'stone'         => $p('Светлый камень (серо-голубой)',    '#f0f0ec', '#e5e9ec', '#56687a', '#aebac6', '#3a4a5a', '#fafaf8', '#f0f0ec', '#2a2c2e', '#62666b', '#e0e0da', '#e5e9ec', '#a88a5a'),
    ];
}

/** Ключ схемы по умолчанию (её цвета прописаны в main.css) */
function memorial_theme_default_preset() { return 'laurel'; }

/** Встроенные шрифты: ключ => [название, CSS-стек, для заголовков, для текста] */
function memorial_theme_builtin_fonts() {
    return [
        'lora'      => ['Lora', '"Lora",Georgia,"Times New Roman",serif'],
        'cormorant' => ['Cormorant Garamond', '"Cormorant Garamond",Georgia,"Times New Roman",serif'],
        'georgia'   => ['Georgia (системный с засечками)', 'Georgia,"Times New Roman",serif'],
        'system'    => ['Системный без засечек (как в Windows/телефоне)', 'system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif'],
    ];
}

/** Все шрифты: встроенные + загруженные в админке */
function memorial_theme_all_fonts() {
    $out = memorial_theme_builtin_fonts();
    foreach ((array) (get_option('mem_design')['fonts']['custom'] ?? []) as $f) {
        $name = trim($f['name'] ?? '');
        if ($name === '' || empty($f['r'])) continue;
        $out['c_' . sanitize_key(sanitize_title($name))] = [$name . ' (свой)', '"' . str_replace('"', '', $name) . '",' . (!empty($f['serif']) ? 'Georgia,serif' : 'system-ui,Arial,sans-serif'), $f];
    }
    return $out;
}

/** Сохранённое «Оформление» поверх значений по умолчанию. Работает и без плагина (тогда — прямо из базы). */
function memorial_theme_design_opts() {
    if (function_exists('memorial_plugin_settings')) return memorial_plugin_settings('mem_design');
    static $d = null;
    if ($d === null) {
        $merge = function ($def, $saved) use (&$merge) {
            if (!is_array($saved)) return $def;
            foreach ($def as $k => $v) if (array_key_exists($k, $saved)) $def[$k] = is_array($v) && $v && array_keys($v) !== range(0, count($v) - 1) ? $merge($v, $saved[$k]) : $saved[$k];
            return $def;
        };
        $d = $merge(memorial_theme_design_defaults(), get_option('mem_design'));
    }
    return $d;
}

function memorial_theme_design_defaults() {
    return [
        'colors' => ['preset' => memorial_theme_default_preset()] + array_fill_keys(array_keys(memorial_theme_color_keys()), ''),
        'fonts'  => ['head' => 'cormorant', 'body' => 'system', 'custom' => []],
        'text'   => ['scale' => '100', 'head' => '100'],
        'img'    => ['webp' => (int) get_option('mem_webp', 0)],
    ];
}

/** Контраст двух цветов #rrggbb по WCAG (1…21) */
function memorial_theme_contrast($a, $b) {
    $l = function ($h) {
        $h = ltrim((string) $h, '#');
        if (strlen($h) === 3) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        $c = array_map(fn($i) => hexdec(substr($h, $i, 2)) / 255, [0, 2, 4]);
        $c = array_map(fn($v) => $v <= .03928 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4, $c);
        return .2126 * $c[0] + .7152 * $c[1] + .0722 * $c[2];
    };
    [$x, $y] = [$l($a), $l($b)];
    return (max($x, $y) + .05) / (min($x, $y) + .05);
}

/** Цвет из палитры стилей сайта (с учётом выбранной схемы и правок пользователя), например 'base' */
function memorial_theme_palette_color($slug, $fallback = '') {
    $palette = wp_get_global_settings(['color', 'palette']);
    foreach (['custom', 'theme'] as $origin) {
        foreach ((array) ($palette[$origin] ?? []) as $c) {
            if (($c['slug'] ?? '') === $slug && !empty($c['color'])) return $c['color'];
        }
    }
    return $fallback;
}

/* Цвет панели браузера на телефоне — под фон страницы */
function memorial_theme_theme_color() {
    return memorial_theme_palette_color('base', '#fbfaf6');
}

/* Значения по умолчанию для настройки mem_design — функция темы (нужно переносу и откату) */
add_filter('memorial_plugin_settings_defaults', fn($fn, $name) => $name === 'mem_design' ? 'memorial_theme_design_defaults' : $fn, 10, 2);
