<?php
/**
 * Карточки услуг: блок «Наши услуги» на главной и шорткод [mem_services] для страницы «Услуги».
 *
 *   [mem_services]  — те же карточки, что на главной («Главная страница → Наши услуги»)
 *   [mem_services more="ritualnye-uslugi/pominalnyj-obed,pohorony-uchastnikov-svo"]
 *                   — плюс карточки других страниц по адресу (название, отрывок и фото записи берутся со страницы)
 */
defined('ABSPATH') || exit;

/** Пункты услуг из настроек главной, со ссылками и фото */
function mem_services_items() {
    $out = [];
    foreach (mem_home('services')['items'] ?? [] as $it) {
        if (trim($it['title'] ?? '') === '') continue;
        $pid = (int) ($it['page'] ?? 0);
        if ($pid && get_post_status($pid) !== 'publish') $pid = 0; // черновик — без ссылки
        $it['pid'] = $pid;
        $it['url'] = $pid ? get_permalink($pid) : '';
        $it['img'] = ($it['img'] ?? '') ?: ($pid ? get_the_post_thumbnail_url($pid, 'medium_large') : '');
        $out[] = $it;
    }
    return $out;
}

function mem_services_grid(array $items) {
    $e = fn($s) => esc_html(mem_t($s));
    $h = '<div class="svc">';
    foreach ($items as $it) {
        $tag = $it['url'] ? 'a' : 'div';
        $h .= '<' . $tag . ' class="svc__item reveal' . ($it['img'] ? ' svc__item--img' : '') . '"' . ($it['url'] ? ' href="' . esc_url($it['url']) . '"' : '') . '>';
        if ($it['img']) $h .= '<span class="svc__img" style="background-image:url(' . esc_url(mem_img_sized($it['img'])) . ')"></span>';   // не оригинал, а 768 px
        $h .= '<span class="svc__body"><h3>' . $e($it['title']) . '</h3>';
        if (!empty($it['text'])) $h .= '<p>' . $e($it['text']) . '</p>';
        $h .= '<span class="svc__foot">' . (!empty($it['price']) ? '<b>' . $e($it['price']) . '</b>' : '') . ($it['url'] ? '<span class="svc__more">Подробнее →</span>' : '') . '</span>';
        $h .= '</span></' . $tag . '>';
    }
    return $h . '</div>';
}

add_shortcode('mem_services', function ($atts) {
    $a = shortcode_atts(['more' => ''], $atts, 'mem_services');
    $items = mem_services_items();
    $have = array_filter(array_column($items, 'pid'));
    foreach (array_filter(array_map('trim', explode(',', $a['more']))) as $path) {
        $p = get_page_by_path(trim($path, '/'));
        if (!$p || $p->post_status !== 'publish' || in_array($p->ID, $have, true) || $p->ID === get_the_ID()) continue;
        $items[] = ['title' => get_the_title($p), 'text' => has_excerpt($p) ? get_the_excerpt($p) : '', 'price' => '',
                    'url' => get_permalink($p), 'img' => get_the_post_thumbnail_url($p, 'medium_large'), 'pid' => $p->ID];
        $have[] = $p->ID;
    }
    return $items ? '<div class="mem-svc">' . mem_services_grid($items) . '</div>' : '';
});
