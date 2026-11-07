<?php
/**
 * Кэш блоков: готовый вывод «тяжёлых» шорткодов каталога и цен запоминается — при следующем открытии страницы
 * не нужно заново перебирать товары. Все блоки одной страницы — в одном файле wp-content/cache/memorial/blocks/.
 * Очищается вместе с кэшем страниц при любой правке (speed/speed.php).
 *
 * Список шорткодов — mem_sc_cache_tags() (фильтр 'mem_sc_cache_tags'). Иконки, которые блок добавил
 * в набор страницы ($GLOBALS['mem_icons_used']), запоминаются вместе с ним — набор внизу страницы остаётся полным.
 */
defined('ABSPATH') || exit;

function mem_sc_cache_tags() {
    return apply_filters('mem_sc_cache_tags', ['mem_cat_count', 'mem_cat_price', 'mem_cat_list', 'mem_price', 'mem_cremation', 'mem_packages',
                                               'mem_cards', 'mem_catalog', 'mem_works', 'mem_services', 'mem_cat_photos', 'mem_restaurants', 'mem_cemeteries', 'mem_guide']);
}

function mem_sc_cache_on() {
    static $on = null;
    if ($on !== null) return $on;
    $on = !empty(mem_settings('mem_speed')['sc']['on']) && !is_admin() && !wp_doing_ajax() && !(defined('REST_REQUEST') && REST_REQUEST)
        && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && !is_preview() && !is_customize_preview();
    if ($on) foreach (array_keys($_GET) as $k) if (!mem_page_cache_ignored_arg((string) $k)) { $on = false; break; }
    return $on;
}

/** Блоки этой страницы: ['ключ' => ['h' => html, 'i' => [иконки]]] */
function &mem_sc_bundle() {
    static $b = null;
    if ($b === null) {
        $b = ['file' => mem_cache_dir() . 'blocks/' . md5((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) . '|' . MEM_CORE_VER) . '.json', 'items' => [], 'dirty' => false];
        $hours = max(1, (int) (mem_settings('mem_speed')['pages']['hours'] ?? 12));
        if (is_file($b['file']) && filemtime($b['file']) + $hours * HOUR_IN_SECONDS > time()) {
            $d = json_decode((string) file_get_contents($b['file']), true);
            if (is_array($d)) $b['items'] = $d;
        }
    }
    return $b;
}

function mem_sc_key($tag, $attr, $m) {
    return md5($tag . '|' . serialize($attr) . '|' . ($m[5] ?? '') . '|' . (int) get_the_ID() . '|' . (int) ($GLOBALS['mem_cat_ctx'] ?? 0));
}

add_filter('pre_do_shortcode_tag', function ($ret, $tag, $attr, $m) {
    if ($ret !== false || !in_array($tag, mem_sc_cache_tags(), true) || !mem_sc_cache_on()) return $ret;
    $b = &mem_sc_bundle();
    $k = mem_sc_key($tag, $attr, $m);
    if (isset($b['items'][$k])) {
        foreach ((array) $b['items'][$k]['i'] as $n => $p) $GLOBALS['mem_icons_used'][$n] = $p;
        return $b['items'][$k]['h'];
    }
    // какие иконки уже есть на странице — после блока увидим, какие он добавил
    $GLOBALS['mem_sc_stack'][] = [$tag, $k, array_keys((array) ($GLOBALS['mem_icons_used'] ?? []))];
    return $ret;
}, 10, 4);

add_filter('do_shortcode_tag', function ($out, $tag, $attr, $m) {
    $top = end($GLOBALS['mem_sc_stack']) ?: null;
    if (!$top || $top[0] !== $tag || !is_string($out)) return $out;
    array_pop($GLOBALS['mem_sc_stack']);
    $k = $top[1];
    $b = &mem_sc_bundle();
    $added = array_diff_key((array) ($GLOBALS['mem_icons_used'] ?? []), array_flip($top[2]));
    $b['items'][$k] = ['h' => $out, 'i' => $added];
    $b['dirty'] = true;
    return $out;
}, 10, 4);

$GLOBALS['mem_sc_stack'] = [];

add_action('shutdown', function () {
    if (!mem_sc_cache_on()) return;
    $b = &mem_sc_bundle();
    if (!$b['dirty'] || !$b['items']) return;
    if (!is_dir(dirname($b['file'])) && !wp_mkdir_p(dirname($b['file']))) return;
    $tmp = $b['file'] . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, wp_json_encode($b['items'])) !== false) @rename($tmp, $b['file']);
}, 0);
