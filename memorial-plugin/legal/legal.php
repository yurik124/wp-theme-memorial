<?php
/**
 * «Мемориал → Документы»: какие страницы сайта — Политика обработки ПД, Согласие на обработку ПД, Политика cookie.
 * Где какая используется (memorial_plugin_legal_link):
 *   — окно «Перезвоните мне»: Согласие (галочка) + Политика обработки ПД;
 *   — плашка cookie: Политика cookie (и ссылки, указанные в тексте плашки);
 *   — подвал (тема): Политика обработки ПД и Политика cookie.
 * Политика обработки ПД заодно становится «Страницей политики конфиденциальности» WordPress (её берут другие плагины).
 * Кнопка «Создать страницы» делает черновики с шаблонами текстов (legal/templates.php) — реквизиты в них подставляются
 * шорткодами из «Мемориал → Контакты» и сами обновляются.
 */
defined('ABSPATH') || exit;

require __DIR__ . '/templates.php';

/** Документы: ключ → [название, для чего] */
function memorial_plugin_legal_docs() {
    return [
        'policy'  => ['Политика обработки персональных данных', 'ссылка в подвале и в окне «Перезвоните мне»; она же — страница политики конфиденциальности WordPress'],
        'consent' => ['Согласие на обработку персональных данных', 'ссылка у галочки согласия в окне «Перезвоните мне»'],
        'cookies' => ['Политика использования cookie', 'ссылка в плашке cookie и в подвале'],
    ];
}

function memorial_plugin_legal_page_options() {
    $o = ['' => '— не выбрана —'];
    foreach (get_pages(['post_status' => 'publish,draft,private', 'sort_column' => 'post_title']) as $p) {
        $o[(string) $p->ID] = $p->post_title . ($p->post_status !== 'publish' ? ' (черновик)' : '');
    }
    return $o;
}

function memorial_plugin_legal_schema() {
    $f = [];
    foreach (memorial_plugin_legal_docs() as $k => [$title, $use]) {
        $f[$k] = ['type' => 'select', 'label' => $title, 'options' => memorial_plugin_legal_page_options(), 'help' => 'Где используется: ' . $use . '.'];
    }
    return ['pages' => ['type' => 'group', 'label' => 'Страницы документов', 'fields' => $f]];
}

function memorial_plugin_legal_defaults() {
    $pp = (int) get_option('wp_page_for_privacy_policy');
    return ['pages' => ['policy' => $pp && get_post($pp) ? (string) $pp : '', 'consent' => '', 'cookies' => '']];
}

/** ID страницы документа (0 — не выбрана) */
function memorial_plugin_legal_page($key) {
    return (int) (memorial_plugin_settings('mem_legal')['pages'][$key] ?? 0);
}

/** Адрес опубликованной страницы документа или '' */
function memorial_plugin_legal_url($key) {
    $id = memorial_plugin_legal_page($key);
    if ($id && get_post_status($id) === 'publish') return get_permalink($id);
    if ($key === 'policy') return get_privacy_policy_url(); // запасной вариант — политика из настроек WordPress
    return '';
}

/** Ссылка на документ: <a>текст</a>; нет страницы — просто текст */
function memorial_plugin_legal_link($key, $text = '', $attrs = '') {
    $text = $text !== '' ? $text : memorial_plugin_legal_docs()[$key][0];
    $u = memorial_plugin_legal_url($key);
    return $u ? '<a href="' . esc_url($u) . '"' . $attrs . '>' . esc_html($text) . '</a>' : esc_html($text);
}

/** Текст с метками {policy:…}, {consent:…}, {cookies:…} → со ссылками на документы */
function memorial_plugin_legal_text($text) {
    return preg_replace_callback('~\{(policy|consent|cookies):([^}]+)\}~u', fn($m) => memorial_plugin_legal_link($m[1], trim($m[2])), esc_html($text));
}

/* Политика обработки ПД — она же страница политики конфиденциальности WordPress */
add_action('update_option_mem_legal', function ($old, $new) {
    $id = (int) ($new['pages']['policy'] ?? 0);
    if ($id && get_post_status($id) === 'publish') update_option('wp_page_for_privacy_policy', $id);
}, 10, 2);
add_action('transition_post_status', function ($new, $old, $post) { // опубликовали черновик политики
    if ($new === 'publish' && $post->post_type === 'page' && (int) $post->ID === memorial_plugin_legal_page('policy')) update_option('wp_page_for_privacy_policy', (int) $post->ID);
}, 10, 3);

/* ---------- Страница «Мемориал → Документы» ---------- */
add_filter('memorial_plugin_admin_pages', function ($pages) {
    $rows = '';
    foreach (memorial_plugin_legal_docs() as $k => [$title]) {
        $id = memorial_plugin_legal_page($k);
        $st = !$id ? '<span style="color:#b32d2e">не выбрана</span>'
            : (get_post_status($id) === 'publish' ? '<span style="color:#1a7f37">✔ опубликована</span>'
            : '<span style="color:#9a6700">черновик — проверьте текст и опубликуйте</span>');
        $act = $id ? ' · <a href="' . esc_url(get_edit_post_link($id)) . '">редактировать</a> · <a href="' . esc_url(get_permalink($id)) . '" target="_blank">открыть</a>' : '';
        $act .= ' · <a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=mem_legal_create&doc=' . $k), 'mem_legal_create')) . '"'
            . ($id ? ' onclick="return confirm(\'Создать новую страницу-черновик по шаблону и выбрать её вместо текущей? Текущая страница останется, её можно удалить вручную.\')"' : '')
            . '>создать по шаблону</a>';
        $rows .= '<li><b>' . esc_html($title) . '</b>: ' . $st . $act . '</li>';
    }
    $missing = array_filter(array_keys(memorial_plugin_legal_docs()), fn($k) => !memorial_plugin_legal_page($k));
    $btn = $missing ? '<p><a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=mem_legal_create'), 'mem_legal_create')) . '">Создать недостающие страницы</a> '
        . '<span class="description">— появятся черновики с шаблонами текстов; реквизиты (ИП, ИНН, ОГРНИП, адрес, почта) подставятся из «Контактов» сами.</span></p>' : '';
    $pages['mem_legal'] = [
        'slug'   => 'mem-legal',
        'title'  => 'Документы',
        'schema' => 'memorial_plugin_legal_schema',
        'intro'  => '<ul style="margin:.4em 0 .8em">' . $rows . '</ul>' . $btn
            . '<p class="description">Шаблоны — образец по 152-ФЗ; перед публикацией проверьте их под вашу работу (лучше с юристом). '
            . 'Режим плашки cookie (спрашивать согласие или только уведомлять) — в «Мемориал → Аналитика».</p>',
    ];
    return $pages;
});

add_action('admin_post_mem_legal_create', function () {
    if (!current_user_can('edit_pages') || !check_admin_referer('mem_legal_create')) wp_die('Нет доступа');
    $opt = get_option('mem_legal');
    $opt = is_array($opt) ? $opt : memorial_plugin_legal_defaults();
    $only = sanitize_key($_GET['doc'] ?? '');
    foreach (memorial_plugin_legal_docs() as $k => [$title]) {
        if ($only && $k !== $only) continue;
        if (!$only && !empty($opt['pages'][$k]) && get_post((int) $opt['pages'][$k])) continue;
        $slug = ['policy' => 'politika-obrabotki-personalnyh-dannyh', 'consent' => 'soglasie-na-obrabotku-personalnyh-dannyh', 'cookies' => 'politika-cookie'][$k];
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => $title, 'post_name' => $slug, 'post_content' => memorial_plugin_legal_template($k)]);
        if ($id && !is_wp_error($id)) $opt['pages'][$k] = (string) $id;
    }
    update_option('mem_legal', $opt);
    wp_safe_redirect(admin_url('admin.php?page=mem-legal'));
    exit;
});

/* ---------- Шорткоды для текстов документов ---------- */
/* [mem_operator] — «ИП Красильникова Л. В. (ИНН …, ОГРНИП …)»; [mem_operator chto="адрес|почта|телефон|сайт"] */
add_shortcode('mem_operator', function ($atts) {
    $a = shortcode_atts(['chto' => ''], $atts, 'mem_operator');
    $l = memorial_plugin_contacts()['legal'] ?? [];
    switch (mb_strtolower(trim($a['chto']))) {
        case 'адрес':   $ad = memorial_plugin_addrs(); return esc_html($ad[0]['addr'] ?? '');
        case 'почта':   $e = memorial_plugin_emails(); return $e ? '<a href="mailto:' . esc_attr($e[0]) . '">' . esc_html($e[0]) . '</a>' : '';
        case 'телефон': return esc_html(memorial_plugin_opt('phone'));
        case 'сайт':    return '<a href="' . esc_url(home_url('/')) . '">' . esc_html(preg_replace('~^https?://~', '', untrailingslashit(home_url()))) . '</a>';
    }
    $name = trim($l['name'] ?? '') ?: get_bloginfo('name');
    $req = array_slice(memorial_plugin_legal_parts(), trim($l['name'] ?? '') ? 1 : 0);
    return esc_html($name . ($req ? ' (' . implode(', ', $req) . ')' : ''));
});
