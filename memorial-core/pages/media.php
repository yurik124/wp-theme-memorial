<?php
/**
 * Фото на страницах:
 * — в галереях и рядах из нескольких фото все снимки одного размера (общие пропорции по большинству фото);
 * — фото в галереях открываются крупно, даже если в редакторе не указана ссылка;
 * — современный формат WebP: «Мемориал → Оформление».
 */
defined('ABSPATH') || exit;

/** Пропорции фото по номеру вложения */
function mem_img_ratio($id) {
    $m = wp_get_attachment_metadata($id);
    return (!empty($m['width']) && !empty($m['height'])) ? $m['width'] / $m['height'] : 0;
}

/** Общие пропорции для набора фото: медиана, в разумных пределах */
function mem_common_ratio(array $ids) {
    $r = array_filter(array_map('mem_img_ratio', $ids));
    if (!$r) return 0;
    sort($r);
    $mid = $r[intdiv(count($r), 2)];
    if (count($r) % 2 === 0) $mid = ($mid + $r[count($r) / 2 - 1]) / 2;
    return max(.5, min(2, $mid));
}

/** Картинка для размытых полей вокруг фото другой формы. PNG/GIF (часто с прозрачным фоном) — без размытия: поля просто светлые */
function mem_blur_bg_url($id) {
    $mime = (string) get_post_mime_type($id);
    if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/svg+xml') return '';
    return (string) wp_get_attachment_image_url($id, 'thumbnail');
}

function mem_ratio_css($r) {
    return round($r * 1000) . '/1000';
}

/* Галерея: одинаковые пропорции и крупный просмотр */
add_filter('render_block_core/gallery', function ($html, $block) {
    if (is_admin()) return $html;
    if (strpos($html, 'is-style-cards') !== false) {
        // «Карточки»: ссылки на страницы (не на файлы фото) — это плитки-разделы; на телефоне их ставим в столбик
        if (preg_match_all('~<a[^>]+href="([^"]+)"~', $html, $l) && array_filter($l[1], fn($u) => !preg_match('~\.(jpe?g|png|webp|gif|avif)(\?|$)~i', $u))) {
            $html = preg_replace('/<figure class="wp-block-gallery/', '<figure class="mem-hub wp-block-gallery', $html, 1);
            $html = mem_hub_tile_photos($html);
        }
        return $html;
    }
    preg_match_all('/wp-image-(\d+)/', $html, $m);
    $ids = array_map('intval', array_unique($m[1]));
    if (!$ids) return $html;

    if (strpos($html, 'is-cropped') !== false && ($r = mem_common_ratio($ids))) {
        $html = preg_replace('/<figure class="wp-block-gallery/', '<figure style="--mem-ar:' . mem_ratio_css($r) . '" class="mem-eq wp-block-gallery', $html, 1);
    }
    // Фото без ссылки — делаем ссылку на крупное фото, его откроет просмотрщик
    $html = preg_replace_callback('~(<figure[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>)\s*(<img[^>]*wp-image-(\d+)[^>]*>)~', function ($mm) {
        $big = wp_get_attachment_image_url((int) $mm[3], 'large');
        return $big ? $mm[1] . '<a href="' . esc_url($big) . '" data-lb>' . $mm[2] . '</a>' : $mm[0];
    }, $html);
    return isset($r) && $r ? mem_fit_odd_photos($html, $r) : $html;
}, 10, 2);

/**
 * Фото другой формы в ряду одинаковых: не обрезаем, а показываем целиком,
 * поля по бокам — размытая копия этого же фото (класс mem-fit + --mbg).
 */
function mem_fit_odd_photos($html, $r) {
    return preg_replace_callback('~<figure class="([^"]*wp-block-image[^"]*)"([^>]*)>((?:(?!</figure>).)*?wp-image-(\d+)(?:(?!</figure>).)*?)</figure>~s', function ($m) use ($r) {
        $ir = mem_img_ratio((int) $m[4]);
        if (!$ir || abs($ir / $r - 1) <= .12) return $m[0];
        $bg = mem_blur_bg_url((int) $m[4]);
        $style = $bg ? ' style="--mbg:url(&quot;' . esc_url($bg) . '&quot;)"' : '';
        return '<figure class="' . $m[1] . ' mem-fit"' . $m[2] . $style . '>' . $m[3] . '</figure>';
    }, $html);
}

/* Ряд колонок, где в каждой только одно фото: тоже одинаковые пропорции */
add_filter('render_block_core/columns', function ($html, $block) {
    if (is_admin() || count($block['innerBlocks']) < 2) return $html;
    $ids = [];
    foreach ($block['innerBlocks'] as $col) {
        $inner = $col['innerBlocks'] ?? [];
        if (count($inner) !== 1 || $inner[0]['blockName'] !== 'core/image' || empty($inner[0]['attrs']['id'])) return $html;
        $ids[] = (int) $inner[0]['attrs']['id'];
    }
    $r = mem_common_ratio($ids);
    return $r ? mem_fit_odd_photos(preg_replace('/<div class="wp-block-columns/', '<div style="--mem-ar:' . mem_ratio_css($r) . '" class="mem-eq-cols wp-block-columns', $html, 1), $r) : $html;
}, 10, 2);

/* =========================================================
 * WebP
 * ======================================================= */

function mem_webp_on() {
    return !empty(mem_settings('mem_design')['img']['webp']) && wp_image_editor_supports(['mime_type' => 'image/webp']);
}

/* Новые фото: уменьшенные копии (их и показывает сайт) сохраняются в WebP; оригинал остаётся как есть */
add_filter('image_editor_output_format', function ($map) {
    if (mem_webp_on()) {
        $map['image/jpeg'] = 'image/webp';
        $map['image/png']  = 'image/webp';
    }
    return $map;
});

/* В текстах страниц ссылки на фото вписаны при вставке. После пересоздания копий в WebP старые
 * адреса заменяем на актуальные — по номеру фото и размеру из редактора. */
add_filter('wp_content_img_tag', function ($tag, $context, $id) {
    if (!$id || !preg_match('/\bsize-([\w-]+)/', $tag, $m) || !preg_match('/src="([^"]+)"/', $tag, $src)) return $tag;
    $url = wp_get_attachment_image_url($id, $m[1]);
    if (!$url || $url === $src[1] || pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) === pathinfo(parse_url($src[1], PHP_URL_PATH), PATHINFO_EXTENSION)) return $tag;
    // адрес поменялся только расширением (было .jpg, стало .webp) — заменяем и убираем устаревший srcset, WordPress соберёт новый
    $tag = str_replace($src[0], 'src="' . esc_url($url) . '"', $tag);
    $tag = preg_replace('/\s(srcset|sizes)="[^"]*"/', '', $tag);
    return wp_image_add_srcset_and_sizes($tag, wp_get_attachment_metadata($id), $id);
}, 9, 3);

/* То же для ссылки на крупное фото вокруг картинки */
add_filter('render_block_core/image', function ($html, $block) {
    $id = (int) ($block['attrs']['id'] ?? 0);
    if (!$id || !preg_match('~<a href="([^"]+\.(?:jpe?g|png))"~i', $html, $m)) return $html;
    $file = wp_basename(preg_replace('/-\d+x\d+(?=\.\w+$)/', '', $m[1]));
    $orig = wp_get_attachment_url($id);
    if (!$orig || pathinfo($file, PATHINFO_FILENAME) !== pathinfo(wp_basename($orig), PATHINFO_FILENAME)) return $html;
    $big = wp_get_attachment_image_url($id, 'large');
    return ($big && $big !== $m[1] && preg_match('/\.webp$/i', $big)) ? str_replace($m[0], '<a href="' . esc_url($big) . '"', $html) : $html;
}, 10, 2);


/**
 * Карточки-разделы (галерея «Карточки» со ссылками на страницы): фото берём как в плитке каталога —
 * «Изображение записи» страницы, на которую ведёт карточка, с её точкой фокуса, «целиком» и цветом фона
 * (панель «Фото в плитке каталога» в редакторе страницы). Нет изображения у страницы — остаётся фото из галереи.
 */
function mem_hub_tile_photos($html) {
    return preg_replace_callback('~(<figure[^>]*wp-block-image[^>]*>\s*<a[^>]+href="([^"]+)"[^>]*>\s*)(<img[^>]*>)~u', function ($m) {
        $pid = url_to_postid(html_entity_decode($m[2]));
        if (!$pid || get_post_type($pid) !== 'page') return $m[0];
        $thumb = (int) get_post_thumbnail_id($pid);
        if (!$thumb) return $m[0];
        $src = wp_get_attachment_image_src($thumb, 'large');
        if (!$src) return $m[0];
        $img = $m[3];
        $img = preg_replace('/\s(srcset|sizes|width|height)="[^"]*"/', '', $img);
        $img = preg_replace('/\ssrc="[^"]*"/', ' src="' . esc_url($src[0]) . '"', $img);
        $img = preg_replace('/wp-image-\d+/', 'wp-image-' . $thumb, $img);
        $st = [];
        $pos = (string) get_post_meta($pid, '_mem_tile_pos', true);
        $fit = (bool) get_post_meta($pid, '_mem_tile_fit', true);
        $bg  = sanitize_hex_color((string) get_post_meta($pid, '_mem_tile_bg', true));
        $focus = '';
        if (preg_match('/^(\d{1,3}(?:\.\d+)?)% (\d{1,3}(?:\.\d+)?)%$/', $pos, $pm)) {
            $st[] = 'object-position:' . round((float) $pm[1]) . '% ' . round((float) $pm[2]) . '%';
            if (!$fit) $focus = ' data-focus="' . round((float) $pm[1]) . ' ' . round((float) $pm[2]) . '"';
        }
        $st[] = 'object-fit:' . ($fit ? 'contain' : 'cover'); // как в плитке: заполняет карточку, края обрезаются
        if ($bg) $st[] = 'background-color:' . $bg;
        if ($st) {
            $css = esc_attr(implode(';', $st));
            $img = preg_match('/\sstyle="/', $img)
                ? preg_replace('/\sstyle="([^"]*)"/', ' style="$1;' . $css . '"', $img, 1)
                : preg_replace('~\s*/?>$~', ' style="' . $css . '">', $img);
            if ($focus) $img = preg_replace('~\s*/?>$~', $focus . '>', $img);
        }
        return $m[1] . $img;
    }, $html);
}
