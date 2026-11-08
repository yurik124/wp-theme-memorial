<?php
/**
 * «Мемориал → Оформление» (часть темы): цветовая схема (готовые шаблоны + свои цвета), шрифты (встроенные и свои), WebP.
 * Страница настроек появляется через рамку плагина Memorial Core (фильтр mem_admin_pages);
 * сами цвета и шрифты применяются и без плагина.
 */
defined('ABSPATH') || exit;

/** Цвета, которыми окрашен сайт. Ключ = CSS-переменная. */
function mem_color_keys() {
    return [
        'dark'  => ['Тёмный фон (шапка, подвал, тёмные карточки)', '#16181b'],
        'dark2' => ['Тёмный фон — второй оттенок', '#202328'],
        'gold'  => ['Акцент: кнопки, иконки, линии', '#b89b5e'],
        'gold2' => ['Акцент на тёмном фоне (светлее)', '#d4bd8a'],
        'gold3' => ['Акцент для текста на светлом (темнее)', '#75602e'],
        'bg'    => ['Фон страницы', '#f6f4f0'],
        'bg2'   => ['Фон чередующихся блоков', '#ece8e1'],
        'ink'   => ['Основной текст', '#1d1f22'],
        'mute'  => ['Второстепенный текст', '#6b6862'],
        'line'  => ['Рамки и разделители', '#e3ddd2'],
    ];
}

/** Готовые цветовые схемы */
function mem_color_presets() {
    $k = array_keys(mem_color_keys());
    $p = fn($name, ...$c) => ['name' => $name, 'c' => array_combine($k, $c)];
    return [
        'classic'  => $p('Графит и золото (исходная)', '#16181b', '#202328', '#b89b5e', '#d4bd8a', '#75602e', '#f6f4f0', '#ece8e1', '#1d1f22', '#6b6862', '#e3ddd2'),
        'navy'     => $p('Тёмно-синий и серебро',      '#142033', '#1c2a40', '#7d8fa6', '#c3cedb', '#4f6177', '#f4f6f8', '#e7ebf0', '#1b2230', '#5f6876', '#dbe1e8'),
        'burgundy' => $p('Бордо',                      '#2a1418', '#3a1d22', '#8e2f3c', '#d9a5ab', '#6e2430', '#f7f3f2', '#eee5e3', '#241a1b', '#6d6162', '#e6d9d7'),
        'olive'    => $p('Оливковый',                  '#1e2420', '#28302a', '#7c8b5a', '#c5cfa6', '#56633c', '#f5f5f0', '#e9eae1', '#1e2220', '#646a62', '#dfe1d5'),
        'bronze'   => $p('Светлый: чёрный и бронза',   '#111111', '#1d1d1d', '#9c7a4b', '#d8bf98', '#6f5531', '#ffffff', '#f3f1ee', '#111111', '#666666', '#e7e3dd'),
        'brown'    => $p('Тёплый коричневый',          '#2b2420', '#3a312b', '#b07a45', '#e0b98c', '#7d5228', '#f8f4ef', '#efe7dd', '#2a2420', '#6f655c', '#e7dccf'),
    ];
}

/** Встроенные шрифты: ключ => [название, CSS-стек, для заголовков, для текста] */
function mem_builtin_fonts() {
    return [
        'lora'      => ['Lora', '"Lora",Georgia,"Times New Roman",serif'],
        'cormorant' => ['Cormorant Garamond', '"Cormorant Garamond",Georgia,"Times New Roman",serif'],
        'georgia'   => ['Georgia (системный с засечками)', 'Georgia,"Times New Roman",serif'],
        'system'    => ['Системный без засечек (как в Windows/телефоне)', 'system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif'],
    ];
}

/** Все шрифты: встроенные + загруженные в админке */
function mem_all_fonts() {
    $out = mem_builtin_fonts();
    foreach ((array) (get_option('mem_design')['fonts']['custom'] ?? []) as $f) {
        $name = trim($f['name'] ?? '');
        if ($name === '' || empty($f['r'])) continue;
        $out['c_' . sanitize_key(sanitize_title($name))] = [$name . ' (свой)', '"' . str_replace('"', '', $name) . '",' . (!empty($f['serif']) ? 'Georgia,serif' : 'system-ui,Arial,sans-serif'), $f];
    }
    return $out;
}

function mem_font_options() {
    return array_map(fn($f) => $f[0], mem_all_fonts());
}

function mem_design_schema() {
    $colors = [];
    foreach (mem_color_keys() as $k => [$label, $def]) $colors[$k] = ['type' => 'color', 'label' => $label, 'scheme' => $k];
    $fonts = mem_font_options();
    return [
        'colors' => ['type' => 'group', 'label' => 'Цветовая схема',
            'help' => 'Выберите готовую схему — рядом с каждым цветом видно, какой цвет даёт схема. Любой цвет можно заменить своим; кнопка «Очистить» в палитре возвращает цвет из схемы.', 'fields' => [
            'preset' => ['type' => 'select', 'label' => 'Готовая схема', 'options' => array_map(fn($p) => $p['name'], mem_color_presets()),
                         'data' => array_map(fn($p) => $p['c'], mem_color_presets())],
        ] + $colors],
        'fonts' => ['type' => 'group', 'label' => 'Шрифты',
            'help' => 'Свой шрифт: скачайте файлы .woff2 (лучше) или .ttf, например с fonts.google.com — нужна кириллица; добавьте шрифт ниже, сохраните, затем выберите его в списке. Файлы хранятся на вашем сайте, со сторонних серверов ничего не загружается.', 'fields' => [
            'head'   => ['type' => 'fontsel', 'label' => 'Шрифт заголовков, цен и телефонов', 'options' => $fonts],
            'body'   => ['type' => 'fontsel', 'label' => 'Шрифт основного текста', 'options' => ['system' => $fonts['system']] + $fonts],
            'custom' => ['type' => 'repeater', 'label' => 'Свои шрифты', 'add' => 'Добавить шрифт', 'collapse' => 'name', 'fields' => [
                'name'  => ['type' => 'text', 'label' => 'Название (любое, например «PT Serif»)'],
                'r'     => ['type' => 'file', 'label' => 'Файл: обычное начертание (обязательно)'],
                'b'     => ['type' => 'file', 'label' => 'Файл: жирное (необязательно)'],
                'i'     => ['type' => 'file', 'label' => 'Файл: курсив (необязательно)'],
                'serif' => ['type' => 'checkbox', 'label' => 'Шрифт с засечками (для запасного варианта, пока файл загружается)'],
            ]],
        ]],
        'img' => ['type' => 'group', 'label' => 'Изображения', 'fields' => [
            'webp' => ['type' => 'checkbox', 'label' => 'Сохранять уменьшенные копии новых фото в WebP (весят в 1,5–3 раза меньше)'],
        ]],
    ];
}

/** Сохранённое «Оформление» поверх значений по умолчанию. Работает и без плагина (тогда — прямо из базы). */
function mem_design_opts() {
    if (function_exists('mem_settings')) return mem_settings('mem_design');
    static $d = null;
    if ($d === null) {
        $merge = function ($def, $saved) use (&$merge) {
            if (!is_array($saved)) return $def;
            foreach ($def as $k => $v) if (array_key_exists($k, $saved)) $def[$k] = is_array($v) && $v && array_keys($v) !== range(0, count($v) - 1) ? $merge($v, $saved[$k]) : $saved[$k];
            return $def;
        };
        $d = $merge(mem_design_defaults(), get_option('mem_design'));
    }
    return $d;
}

function mem_design_defaults() {
    return [
        'colors' => ['preset' => 'classic'] + array_fill_keys(array_keys(mem_color_keys()), ''),
        'fonts'  => ['head' => get_option('mem_font', 'lora') === 'cormorant' ? 'cormorant' : 'lora', 'body' => 'system', 'custom' => []],
        'img'    => ['webp' => (int) get_option('mem_webp', 0)],
    ];
}

add_filter('mem_admin_pages', function ($pages) {
    $webp = wp_image_editor_supports(['mime_type' => 'image/webp']);
    $sw = '';
    foreach (mem_color_presets() as $pr) {
        $sw .= '<span style="display:inline-flex;align-items:center;gap:6px;margin:4px 14px 4px 0">';
        foreach (['dark', 'gold', 'bg2', 'bg'] as $c) $sw .= '<i style="width:18px;height:18px;border-radius:50%;display:inline-block;border:1px solid #0002;background:' . esc_attr($pr['c'][$c]) . '"></i>';
        $sw .= ' ' . esc_html($pr['name']) . '</span>';
    }
    $pages['mem_design'] = [
        'slug' => 'mem-design', 'title' => 'Оформление', 'schema' => 'mem_design_schema',
        'intro' => 'Цвета и шрифты меняются на всём сайте сразу после сохранения. Откройте сайт в соседней вкладке и обновляйте её, чтобы сравнить.<br>'
            . '<b>Готовые схемы:</b><br>' . $sw . '<br>'
            . '<b>WebP на вашем хостинге:</b> ' . ($webp ? 'поддерживается.' : 'не поддерживается — попросите хостинг включить WebP в PHP (GD или Imagick).')
            . ' Чтобы перевести уже загруженные фото, после включения галочки установите плагин <b>Regenerate Thumbnails</b> и запустите его в «Инструменты → Regenerate Thumbnails»; сайт сам подставит новые адреса фото.<br>'
            . 'Кэширование и сжатие — в «Мемориал → Скорость».',
    ];
    return $pages;
});

/* Свои шрифты: разрешаем загружать файлы шрифтов администратору */
add_filter('upload_mimes', function ($m) {
    if (current_user_can('manage_options')) {
        $m['woff']  = 'font/woff';
        $m['woff2'] = 'font/woff2';
        $m['ttf']   = 'font/ttf';
        $m['otf']   = 'font/otf';
    }
    return $m;
});
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = ['woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf'];
    if (isset($map[$ext]) && current_user_can('manage_options')) {
        $data = ['ext' => $ext, 'type' => $map[$ext], 'proper_filename' => false];
    }
    return $data;
}, 10, 3);

/** CSS со шрифтами и цветами — в шапку сайта и в редактор */
function mem_design_css() {
    $d = mem_design_opts();
    $css = '';

    // Свои шрифты
    foreach (mem_all_fonts() as $key => $f) {
        if (empty($f[2])) continue;
        $c = $f[2]; $fam = str_replace('"', '', trim($c['name']));
        $fmt = fn($u) => ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'][strtolower(pathinfo(parse_url($u, PHP_URL_PATH), PATHINFO_EXTENSION))] ?? 'woff2';
        foreach ([['r', 'normal', '400 500'], ['b', 'normal', '600 800'], ['i', 'italic', '400 500']] as [$k, $style, $w]) {
            if (empty($c[$k])) continue;
            $css .= '@font-face{font-family:"' . $fam . '";font-style:' . $style . ';font-weight:' . $w . ';font-display:swap;src:url(' . esc_url($c[$k]) . ') format("' . $fmt($c[$k]) . '")}';
        }
    }

    $vars = [];
    $all = mem_all_fonts();
    $head = $d['fonts']['head'] ?? 'lora';
    $body = $d['fonts']['body'] ?? 'system';
    if ($head !== 'lora' && isset($all[$head])) $vars[] = '--serif:' . $all[$head][1];
    if ($body !== 'system' && isset($all[$body])) $vars[] = '--sans:' . $all[$body][1];

    // Цвета: схема, поверх — свои
    $presets = mem_color_presets();
    $base = $presets[$d['colors']['preset'] ?? 'classic']['c'] ?? $presets['classic']['c'];
    $def = $presets['classic']['c'];
    $fin = [];
    foreach ($base as $k => $v) {
        $v = !empty($d['colors'][$k]) ? $d['colors'][$k] : $v;
        if ($k === 'gold3' && strtolower($v) === '#8a6f35') $v = $def['gold3'];   // прежний цвет по умолчанию был слишком светлым для текста
        $fin[$k] = $v;
        if (strtolower($v) !== $def[$k]) $vars[] = '--' . $k . ':' . $v;
    }
    // текст на золотых кнопках: тёмный или белый — что контрастнее с цветом акцента
    if (!empty($fin['gold']) && strtolower($fin['gold']) !== $def['gold']) {
        $dark = $fin['dark'] ?? '#16181b';
        $vars[] = '--on-gold:' . (mem_contrast($fin['gold'], $dark) >= mem_contrast($fin['gold'], '#ffffff') ? $dark : '#fff');
    }
    if ($vars) $css .= ':root{' . implode(';', $vars) . '}';
    return $css;
}

add_action('wp_head', function () {
    $css = mem_design_css();
    if ($css !== '') echo '<style id="mem-design">' . $css . "</style>\n";
}, 30);

/* Те же цвета и шрифты в редакторе страниц */
add_action('enqueue_block_editor_assets', function () {
    $css = mem_design_css();
    if ($css === '') return;
    wp_register_style('mem-design-editor', false);
    wp_enqueue_style('mem-design-editor');
    wp_add_inline_style('mem-design-editor', str_replace(':root{', '.editor-styles-wrapper{', $css));
});

/**
 * Цвет из схемы. $effective = true — с учётом своих цветов (что сейчас на сайте),
 * false — только схема (что будет, если поле оставить пустым).
 */
function mem_scheme_color($key, $effective = false) {
    $d = mem_design_opts();
    $p = mem_color_presets();
    $base = $p[$d['colors']['preset'] ?? 'classic']['c'] ?? $p['classic']['c'];
    if ($effective && !empty($d['colors'][$key])) return $d['colors'][$key];
    return $base[$key] ?? '';
}

/* Цвет панели браузера на телефоне — под тёмный цвет схемы */
function mem_theme_color() {
    $d = mem_design_opts();
    $p = mem_color_presets();
    return !empty($d['colors']['dark']) ? $d['colors']['dark'] : ($p[$d['colors']['preset'] ?? 'classic']['c']['dark'] ?? '#16181b');
}

/** Контраст двух цветов #rrggbb по WCAG (1…21) */
function mem_contrast($a, $b) {
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
