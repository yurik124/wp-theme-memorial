<?php
/**
 * Что ищут на сайте: учёт запросов в поиске «Вопрос–ответ» и «Полезно знать».
 *
 *  — браузер отправляет итоговый запрос (выбрали подсказку, нажали «Найти», «Часто ищут» или перестали печатать)
 *    и сколько нашлось — admin-ajax.php?action=mem_sq;
 *  — хранится только сам запрос и счётчики: без IP, cookie и данных посетителя. Номера телефонов, почта, ссылки,
 *    длинные строки не сохраняются; с одного адреса — не больше 40 запросов в час (защита от накрутки);
 *  — «Мемориал → Что ищут на сайте»: частые запросы и запросы, по которым ничего не нашлось;
 *  — «Часто ищут» под полем поиска собирается из частых запросов, по которым есть ответы
 *    (пока статистики мало — показываются слова по умолчанию).
 */
defined('ABSPATH') || exit;

const MEMORIAL_PLUGIN_SQ_OPT = 'mem_sq_stats';
const MEMORIAL_PLUGIN_SQ_MAX = 600;     // столько разных запросов храним (редкие и старые вытесняются)

/** Запрос для учёта: нижний регистр, без лишних пробелов; null — не сохраняем */
function memorial_plugin_sq_clean($q) {
    $q = trim(preg_replace('/\s+/u', ' ', mb_strtolower(wp_strip_all_tags((string) $q))));
    $q = str_replace('ё', 'е', $q);
    $q = trim($q, " \t.,!?;:-—«»\"'()");
    if (mb_strlen($q) < 3 || mb_strlen($q) > 60) return null;
    if (preg_match('/\d[\d\s\-()]{4,}|@|https?:|www\.|\.ru\b|\.com\b/u', $q)) return null;   // телефоны, почта, ссылки
    if (count(explode(' ', $q)) > 7) return null;
    if (!preg_match('/[а-яa-z]{2}/u', $q)) return null;
    return $q;
}

function memorial_plugin_sq_stats() {
    $s = get_option(MEMORIAL_PLUGIN_SQ_OPT, []);
    return is_array($s) ? $s : [];
}

function memorial_plugin_sq_ajax() {
    $q = memorial_plugin_sq_clean(wp_unslash($_POST['q'] ?? ''));
    if ($q === null) wp_die('', '', 204);
    // не больше 40 запросов в час с одного адреса (адрес не хранится — только его хэш на час)
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $key = 'mem_sq_' . substr(md5($ip . wp_salt('nonce')), 0, 16);
    $n = (int) get_transient($key);
    if ($n >= 40) wp_die('', '', 204);
    set_transient($key, $n + 1, HOUR_IN_SECONDS);

    $found = max(0, min(999, (int) ($_POST['n'] ?? 0)));
    $where = ($_POST['w'] ?? '') === 'guide' ? 'g' : 'f';
    $s = memorial_plugin_sq_stats();
    $r = $s[$q] ?? ['c' => 0, 'n' => 0, 't' => 0, 'w' => ''];
    $r['c']++;
    $r['n'] = $found;
    $r['t'] = time();
    $r['w'] = strpos($r['w'], $where) === false ? $r['w'] . $where : $r['w'];
    $s[$q] = $r;
    if (count($s) > MEMORIAL_PLUGIN_SQ_MAX) {   // вытесняем редкие и давние
        uasort($s, fn($a, $b) => ($b['c'] * 10 + $b['t'] / 86400 / 30) <=> ($a['c'] * 10 + $a['t'] / 86400 / 30));
        $s = array_slice($s, 0, MEMORIAL_PLUGIN_SQ_MAX, true);
    }
    update_option(MEMORIAL_PLUGIN_SQ_OPT, $s, false);
    wp_die('', '', 204);
}
add_action('wp_ajax_mem_sq', 'memorial_plugin_sq_ajax');
add_action('wp_ajax_nopriv_mem_sq', 'memorial_plugin_sq_ajax');

/**
 * «Часто ищут»: частые запросы за последние полгода, по которым есть ответы (короткие — до 3 слов).
 * Нужно хотя бы $min таких запросов, иначе — $fallback.
 */
function memorial_plugin_sq_popular(array $fallback, $limit = 6, $min = 4) {
    $out = [];
    $from = time() - 180 * DAY_IN_SECONDS;
    $s = array_filter(memorial_plugin_sq_stats(), fn($r, $q) => $r['n'] > 0 && $r['c'] >= 2 && $r['t'] >= $from && count(explode(' ', $q)) <= 3, ARRAY_FILTER_USE_BOTH);
    uasort($s, fn($a, $b) => $b['c'] <=> $a['c']);
    foreach (array_keys($s) as $q) {
        // «пособие» и «пособие на погребение» — не дублируем
        foreach ($out as $o) if (mb_strpos($q, $o) !== false || mb_strpos($o, $q) !== false) continue 2;
        $out[] = $q;
        if (count($out) >= $limit) break;
    }
    return count($out) >= $min ? $out : $fallback;
}

/* ---------- Админка: «Мемориал → Что ищут на сайте» ---------- */
add_action('admin_menu', function () {
    add_submenu_page('mem-contacts', 'Что ищут на сайте', 'Что ищут на сайте', 'edit_pages', 'mem-sq', 'memorial_plugin_sq_page');
}, 20);

add_action('admin_post_mem_sq_clear', function () {
    if (!current_user_can('manage_options') || !check_admin_referer('mem_sq_clear')) wp_die('Нет доступа');
    $q = isset($_POST['q']) ? (string) wp_unslash($_POST['q']) : '';
    if ($q !== '') { $s = memorial_plugin_sq_stats(); unset($s[$q]); update_option(MEMORIAL_PLUGIN_SQ_OPT, $s, false); }
    else delete_option(MEMORIAL_PLUGIN_SQ_OPT);
    wp_safe_redirect(admin_url('admin.php?page=mem-sq&cleared=1'));
    exit;
});

function memorial_plugin_sq_page() {
    $s = memorial_plugin_sq_stats();
    $total = array_sum(array_column($s, 'c'));
    $ok = array_filter($s, fn($r) => $r['n'] > 0);
    $none = array_filter($s, fn($r) => $r['n'] === 0);
    $sort = fn($a, $b) => $b['c'] <=> $a['c'] ?: $b['t'] <=> $a['t'];
    uasort($ok, $sort);
    uasort($none, $sort);
    $faq = '';
    foreach (get_pages() as $p) if (has_shortcode($p->post_content, 'mem_faq')) { $faq = get_permalink($p); break; }
    $where = ['f' => 'Вопрос–ответ', 'g' => 'Полезно знать'];

    $table = function ($rows, $title, $note) use ($faq, $where) {
        echo '<h2 style="margin-top:2em">' . esc_html($title) . ' <span style="font-weight:400;color:#777">(' . count($rows) . ')</span></h2><p class="description">' . esc_html($note) . '</p>';
        if (!$rows) { echo '<p><i>Пока пусто.</i></p>'; return; }
        echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Запрос</th><th style="width:90px">Сколько раз</th><th style="width:110px">Нашлось</th><th style="width:130px">Где искали</th><th style="width:120px">Последний раз</th><th style="width:40px"></th></tr></thead><tbody>';
        foreach (array_slice($rows, 0, 100, true) as $q => $r) {
            $w = implode(', ', array_map(fn($c) => $where[$c] ?? '', str_split($r['w'] ?: 'f')));
            echo '<tr><td>' . ($faq ? '<a href="' . esc_url(add_query_arg('q', $q, $faq)) . '" target="_blank">' . esc_html($q) . '</a>' : esc_html($q)) . '</td>'
               . '<td>' . (int) $r['c'] . '</td><td>' . ($r['n'] ? (int) $r['n'] . ' ' . memorial_plugin_word($r['n'], 'ответ', 'ответа', 'ответов') : '<b style="color:#b32d2e">ничего</b>') . '</td>'
               . '<td>' . esc_html($w) . '</td><td>' . esc_html(date_i18n('j M Y', $r['t'])) . '</td><td>';
            if (current_user_can('manage_options')) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0">';
                wp_nonce_field('mem_sq_clear');
                echo '<input type="hidden" name="action" value="mem_sq_clear"><input type="hidden" name="q" value="' . esc_attr($q) . '">'
                   . '<button class="button-link" title="Удалить запрос из списка" aria-label="Удалить">✕</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    };

    echo '<div class="wrap"><h1>Что ищут на сайте</h1>';
    if (!empty($_GET['cleared'])) echo '<div class="notice notice-success is-dismissible"><p>Готово.</p></div>';
    echo '<p style="max-width:900px">Запросы в поиске на страницах «Вопрос–ответ» и «Полезно знать». Сохраняется только текст запроса и счётчики — без данных о посетителях; '
       . 'номера телефонов, почта и ссылки не сохраняются. Всего запросов: <b>' . (int) $total . '</b>, разных: <b>' . count($s) . '</b>.</p>'
       . '<p style="max-width:900px">Под полем поиска «Часто ищут» собирается сам из частых запросов, по которым есть ответы (как наберётся хотя бы 4 таких запроса за полгода).</p>';
    $table($none, 'Ничего не нашли', 'Подсказка, каких ответов не хватает: добавьте вопрос в текст подходящей страницы (блок «Подробности» в разделе «Частые вопросы») — он сам появится в «Вопрос–ответ».');
    $table($ok, 'Частые запросы', 'Нажмите на запрос — откроется поиск с ним на сайте.');
    if ($s && current_user_can('manage_options')) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:2em" onsubmit="return confirm(\'Удалить всю статистику поиска?\')">';
        wp_nonce_field('mem_sq_clear');
        echo '<input type="hidden" name="action" value="mem_sq_clear"><button class="button">Очистить статистику</button></form>';
    }
    echo '</div>';
}
