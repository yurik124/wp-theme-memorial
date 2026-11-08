<?php
/**
 * «Мемориал → Оформление» (часть темы): цветовая схема «Светлая память» (готовые светлые схемы + свои цвета), шрифты, WebP.
 * Страница настроек появляется через рамку плагина Memorial Core (фильтр mem_admin_pages);
 * сами цвета и шрифты применяются и без плагина.
 */
defined('ABSPATH') || exit;

/** Цвета, которыми окрашен сайт. Ключ = CSS-переменная. */
function mem_color_keys() {
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
function mem_color_presets() {
    $k = array_keys(mem_color_keys());
    $p = fn($name, ...$c) => ['name' => $name, 'c' => array_combine($k, $c)];
    //                                                  dark       dark2      gold       gold2      gold3      bg         bg2        ink        mute       line       sage       bronze
    return [
        'laurel'        => $p('Светлая память — лавр (основная)', '#f2efe6', '#e6ebdf', '#5e7052', '#a9b796', '#3f4d36', '#fbfaf6', '#f2efe6', '#2a2c28', '#66675e', '#e2ded2', '#e6ebdf', '#a88a5a'),
        'laurel_bronze' => $p('Светлая память — бронза',          '#f2efe6', '#ece6da', '#8c6f45', '#cdbb98', '#5c4628', '#fbfaf6', '#f2efe6', '#2a2c28', '#66675e', '#e2ded2', '#ede7db', '#a88a5a'),
        'stone'         => $p('Светлый камень (серо-голубой)',    '#f0f0ec', '#e5e9ec', '#56687a', '#aebac6', '#3a4a5a', '#fafaf8', '#f0f0ec', '#2a2c2e', '#62666b', '#e0e0da', '#e5e9ec', '#a88a5a'),
    ];
}

/** Ключ схемы по умолчанию (её цвета прописаны в main.css) */
function mem_default_preset() { return 'laurel'; }

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

/** Варианты размера текста: ['100' => '100 % — как задумано', …] */
function mem_text_scales($min = 85, $max = 130) {
    $o = [];
    for ($v = $min; $v <= $max; $v += 5) {
        $o[(string) $v] = $v . ' %' . ($v === 100 ? ' — как задумано' : ($v < 100 ? ' — мельче' : ' — крупнее'));
    }
    return $o;
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
            'head'   => ['type' => 'fontsel', 'label' => 'Шрифт крупных заголовков (H1, H2). Заголовки карточек, цены и телефоны — Lora', 'options' => $fonts],
            'body'   => ['type' => 'fontsel', 'label' => 'Шрифт основного текста', 'options' => ['system' => $fonts['system']] + $fonts],
            'custom' => ['type' => 'repeater', 'label' => 'Свои шрифты', 'add' => 'Добавить шрифт', 'collapse' => 'name', 'fields' => [
                'name'  => ['type' => 'text', 'label' => 'Название (любое, например «PT Serif»)'],
                'r'     => ['type' => 'file', 'label' => 'Файл: обычное начертание (обязательно)'],
                'b'     => ['type' => 'file', 'label' => 'Файл: жирное (необязательно)'],
                'i'     => ['type' => 'file', 'label' => 'Файл: курсив (необязательно)'],
                'serif' => ['type' => 'checkbox', 'label' => 'Шрифт с засечками (для запасного варианта, пока файл загружается)'],
            ]],
        ]],
        'text' => ['type' => 'group', 'label' => 'Размер текста',
            'help' => 'Увеличивает или уменьшает весь текст сайта: меню, заголовки, кнопки, цены, подписи. Отступы растут вместе с текстом, вёрстка подстраивается сама: меню при нехватке места уходит во вторую строку или в «бургер», длинные слова переносятся. На телефоне текст не становится мельче задуманного (меньше 100 % — только на компьютере), чтобы оставаться читаемым. Посетитель видит результат сразу после сохранения.', 'fields' => [
            'scale' => ['type' => 'select', 'label' => 'Весь текст сайта', 'options' => mem_text_scales()],
            'head'  => ['type' => 'select', 'label' => 'Заголовки — дополнительно (к размеру всего текста)', 'options' => mem_text_scales(80, 130)],
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
        'colors' => ['preset' => mem_default_preset()] + array_fill_keys(array_keys(mem_color_keys()), ''),
        'fonts'  => ['head' => 'cormorant', 'body' => 'system', 'custom' => []],
        'text'   => ['scale' => '100', 'head' => '100'],
        'img'    => ['webp' => (int) get_option('mem_webp', 0)],
    ];
}

add_filter('mem_admin_pages', function ($pages) {
    $webp = wp_image_editor_supports(['mime_type' => 'image/webp']);
    $sw = '';
    foreach (mem_color_presets() as $pr) {
        $sw .= '<span style="display:inline-flex;align-items:center;gap:6px;margin:4px 14px 4px 0">';
        foreach (['gold', 'sage', 'bg2', 'bronze'] as $c) $sw .= '<i style="width:18px;height:18px;border-radius:50%;display:inline-block;border:1px solid #0002;background:' . esc_attr($pr['c'][$c]) . '"></i>';
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
    $head = $d['fonts']['head'] ?? 'cormorant';
    $body = $d['fonts']['body'] ?? 'system';
    if ($head !== 'cormorant' && isset($all[$head])) $vars[] = '--serif:' . $all[$head][1];
    if ($body !== 'system' && isset($all[$body])) $vars[] = '--sans:' . $all[$body][1];

    // Размер текста: общий масштаб (root font-size в main.css берёт --fs) и заголовки
    $sc = max(70, min(150, (int) ($d['text']['scale'] ?? 100) ?: 100));
    $hd = max(70, min(150, (int) ($d['text']['head'] ?? 100) ?: 100));
    if ($sc !== 100) $vars[] = '--fs:' . rtrim(rtrim(number_format($sc / 100, 2, '.', ''), '0'), '.');
    if ($hd !== 100) $vars[] = '--fs-h:' . rtrim(rtrim(number_format($hd / 100, 2, '.', ''), '0'), '.');

    // Цвета: схема, поверх — свои. В main.css прописана схема по умолчанию — выводим только отличия.
    $presets = mem_color_presets();
    $def = $presets[mem_default_preset()]['c'];
    $base = $presets[$d['colors']['preset'] ?? '']['c'] ?? $def;
    $fin = [];
    foreach ($base as $k => $v) {
        $v = !empty($d['colors'][$k]) ? $d['colors'][$k] : $v;
        $fin[$k] = $v;
        if (strtolower($v) !== $def[$k]) $vars[] = '--' . $k . ':' . $v;
    }
    // текст на кнопках с заливкой акцентом: белый, если читается (иначе — основной цвет текста)
    if (strtolower($fin['gold']) !== $def['gold'] || strtolower($fin['ink']) !== $def['ink']) {
        $vars[] = '--on-gold:' . (mem_contrast($fin['gold'], '#ffffff') >= 4.5 || mem_contrast($fin['gold'], '#ffffff') >= mem_contrast($fin['gold'], $fin['ink']) ? '#fff' : $fin['ink']);
    }
    // текст на плашках «бывшего тёмного» цвета: если такой цвет выбрали тёмным — белый текст
    if (strtolower($fin['dark']) !== $def['dark']) {
        $light = mem_contrast($fin['dark'], $fin['ink']) >= mem_contrast($fin['dark'], '#ffffff');
        $vars[] = '--on-dark:' . ($light ? $fin['ink'] : '#fff');
        $vars[] = '--on-dark-mute:' . ($light ? $fin['mute'] : 'rgba(255,255,255,.72)');
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
    $base = $p[$d['colors']['preset'] ?? '']['c'] ?? $p[mem_default_preset()]['c'];
    if ($effective && !empty($d['colors'][$key])) return $d['colors'][$key];
    return $base[$key] ?? '';
}

/* Цвет панели браузера на телефоне — под фон страницы (дизайн светлый целиком) */
function mem_theme_color() {
    return mem_scheme_color('bg', true) ?: '#fbfaf6';
}

/*
 * Переход на «Светлую память» (тема 4.0): прежние тёмные схемы убраны. Если на сайте была выбрана тёмная схема
 * (или свои цвета под неё), один раз переключаем на светлую «лавр» и шрифт заголовков Cormorant Garamond.
 * Свои шрифты и настройку WebP не трогаем.
 */
add_action('init', function () {
    if (get_option('mem_design_v4')) return;
    $o = get_option('mem_design');
    if (is_array($o)) {
        $o['colors'] = ['preset' => mem_default_preset()] + array_fill_keys(array_keys(mem_color_keys()), '');
        if (in_array($o['fonts']['head'] ?? 'lora', ['lora', ''], true)) $o['fonts']['head'] = 'cormorant';
        update_option('mem_design', $o);
    }
    update_option('mem_design_v4', 1, false);
}, 1);

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
