<?php
/**
 * «Мемориал → Цены»: пакеты похорон, пакет кремации, отдельные цены.
 * Цены выводятся на страницах шорткодами и меняются сразу везде:
 *   [mem_packages]                 — карточки пакетов похорон
 *   [mem_cremation]                — таблица «Пакет кремации» с итогом
 *   [mem_price id="kremaciya"]     — итог кремации, «58 000 ₽»
 *   [mem_price paket="Эконом"]     — итог пакета похорон
 *   [mem_price id="lenta"]         — цена из «Отдельные цены» по коду
 */
defined('ABSPATH') || exit;

function mem_prices_schema() {
    return [
        'packages' => ['type' => 'group', 'label' => 'Пакеты похорон', 'help' => 'Выводятся на главной (блок «Пакеты услуг» — его заголовок и показ настраиваются в «Главная страница») и шорткодом [mem_packages] на любой странице.', 'fields' => [
            'badge' => ['type' => 'text', 'label' => 'Надпись на выделенном пакете'],
            'btn'   => ['type' => 'text', 'label' => 'Текст кнопки'],
            'items' => ['type' => 'repeater', 'label' => 'Пакеты', 'add' => 'Добавить пакет', 'collapse' => 'name', 'fields' => [
                'name'     => ['type' => 'text', 'label' => 'Название пакета'],
                'featured' => ['type' => 'checkbox', 'label' => 'Выделить (тёмная карточка с надписью)'],
                'rows'     => ['type' => 'repeater', 'label' => 'Состав пакета — итог считается сам. «На карточке» — 3–5 главных позиций, видны без раскрытия списка', 'add' => 'Добавить позицию', 'inline' => true, 'fields' => [
                    'name'  => ['type' => 'text', 'label' => 'Позиция'],
                    'price' => ['type' => 'number', 'label' => 'Цена, ₽'],
                    'key'   => ['type' => 'checkbox', 'label' => 'На карточке'],
                ]],
            ]],
        ]],
        'cremation' => ['type' => 'group', 'label' => 'Пакет кремации', 'help' => 'Таблица на странице кремации — шорткод [mem_cremation]. Итог считается сам; на других страницах его выводит [mem_price id="kremaciya"].', 'fields' => [
            'head_l' => ['type' => 'text', 'label' => 'Заголовок левой колонки'],
            'head_r' => ['type' => 'text', 'label' => 'Заголовок правой колонки'],
            'rows'   => ['type' => 'repeater', 'label' => 'Что входит', 'add' => 'Добавить позицию', 'inline' => true, 'fields' => [
                'name'  => ['type' => 'text', 'label' => 'Позиция'],
                'price' => ['type' => 'number', 'label' => 'Цена, ₽'],
            ]],
            'total' => ['type' => 'text', 'label' => 'Подпись итоговой строки'],
        ]],
        'other' => ['type' => 'group', 'label' => 'Отдельные цены', 'help' => 'Цены, которые упоминаются в текстах страниц. В текст вставляется шорткод с кодом цены, например [mem_price id="lenta"]. Цену можно писать числом (500), диапазоном (200–300) или словами (бесплатно) — знак ₽ к числам тема добавит сама.', 'fields' => [
            'items' => ['type' => 'repeater', 'label' => 'Цены', 'add' => 'Добавить цену', 'inline' => true, 'fields' => [
                'name'  => ['type' => 'text', 'label' => 'Название'],
                'price' => ['type' => 'text', 'label' => 'Цена'],
                'code'  => ['type' => 'text', 'label' => 'Код (латиницей)'],
            ]],
        ]],
    ];
}

function mem_prices_defaults() {
    // пакеты раньше хранились в настройках главной — переносим оттуда
    $old  = get_option('mem_home');
    $home = mem_home_defaults()['prices'];
    $src  = is_array($old) && !empty($old['prices']['items']) ? $old['prices'] : $home;
    return [
        'packages' => [
            'badge' => $src['badge'] ?? $home['badge'],
            'btn'   => $src['btn'] ?? $home['btn'],
            'items' => $src['items'] ?? $home['items'],
        ],
        'cremation' => [
            'head_l' => 'Что входит', 'head_r' => 'Стоимость',
            'rows' => [
                ['name' => 'Транспортировка тела до крематория', 'price' => 20000],
                ['name' => 'Гроб в комплекте', 'price' => 7000],
                ['name' => 'Кремирование тела', 'price' => 21500],
                ['name' => 'Урна для праха с сумкой и капсулой', 'price' => 4500],
                ['name' => 'Выдача праха в день кремирования', 'price' => 5000],
            ],
            'total' => 'Итого с доставкой до крематория',
        ],
        'other' => ['items' => [
            ['name' => 'Надпись на ленту', 'price' => '200–300', 'code' => 'lenta'],
            ['name' => 'Доставка венков по Белгороду', 'price' => '500', 'code' => 'dostavka'],
            ['name' => 'Социальное пособие на погребение (государственное, обновляется 1 февраля)', 'price' => '9 678,63', 'code' => 'posobie'],
        ]],
    ];
}

function mem_prices($section) { return mem_settings('mem_prices')[$section] ?? []; }

add_filter('mem_admin_pages', function ($pages) {
    $codes = '<code>[mem_price id="kremaciya"]</code> — ' . esc_html(mem_price_value('kremaciya'));
    foreach (mem_prices('other')['items'] ?? [] as $it) {
        if (trim($it['code'] ?? '') === '') continue;
        $codes .= ' · <code>[mem_price id="' . esc_html($it['code']) . '"]</code> — ' . esc_html(mem_price_value($it['code']));
    }
    foreach (mem_packages() as $p) {
        $codes .= ' · <code>[mem_price paket="' . esc_html($p['name']) . '"]</code> — ' . esc_html(mem_rub(array_sum(array_column($p['items'], 1))));
    }
    $pages['mem_prices'] = [
        'slug' => 'mem-prices', 'title' => 'Цены', 'schema' => 'mem_prices_schema',
        'intro' => 'Цены отсюда подставляются на страницы автоматически — меняйте их только здесь.<br><b>Шорткоды для текста:</b> ' . $codes
            . '<br>Цены каталога считаются из товаров: <code>[mem_cat_price]</code>, <code>[mem_cat_count]</code> (см. «Шорткоды и блоки»).'
            . mem_prices_audit_html(),
    ];
    return $pages;
});

/* ---------- Вывод ---------- */

function mem_cremation_total() {
    return array_sum(array_map(fn($r) => (int) ($r['price'] ?? 0), mem_prices('cremation')['rows'] ?? []));
}

/** Число или диапазон → «500 ₽», «200–300 ₽»; слова — как есть */
function mem_price_fmt($v) {
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('/^(\d[\d\s\x{00A0}\x{202F}]*(?:,\d{1,2})?)(?:\s*[–—-]\s*(\d[\d\s\x{00A0}\x{202F}]*(?:,\d{1,2})?))?\s*(?:₽|руб\.?)?$/u', $v, $m)) {
        $n = function ($x) {
            [$int, $kop] = array_pad(explode(',', $x, 2), 2, '');
            return number_format((int) preg_replace('/\D/', '', $int), 0, '', "\u{202F}") . ($kop !== '' ? ',' . $kop : '');
        };
        return $n($m[1]) . (isset($m[2]) && $m[2] !== '' ? '–' . $n($m[2]) : '') . "\u{00A0}₽";
    }
    return $v;
}

function mem_price_value($id, $paket = '') {
    if ($paket !== '') {
        foreach (mem_packages() as $p) {
            if (mb_strtolower(trim($p['name'])) === mb_strtolower(trim($paket))) return mem_rub(array_sum(array_column($p['items'], 1)));
        }
        return '';
    }
    if ($id === 'kremaciya') return mem_rub(mem_cremation_total());
    foreach (mem_prices('other')['items'] ?? [] as $it) {
        if (trim($it['code'] ?? '') !== '' && strcasecmp(trim($it['code']), $id) === 0) return mem_price_fmt($it['price'] ?? '');
    }
    return '';
}

add_shortcode('mem_price', function ($atts) {
    $a = shortcode_atts(['id' => '', 'paket' => ''], $atts, 'mem_price');
    return esc_html(mem_price_value(trim($a['id']), $a['paket']));
});

add_shortcode('mem_cremation', function () {
    $c = mem_prices('cremation');
    $rows = array_filter($c['rows'] ?? [], fn($r) => trim($r['name'] ?? '') !== '');
    if (!$rows) return '';
    $h = '<figure class="wp-block-table is-style-price mem-cremation"><table><thead><tr><th>' . esc_html($c['head_l'] ?: 'Что входит') . '</th><th>' . esc_html($c['head_r'] ?: 'Стоимость') . '</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr><td>' . esc_html($r['name']) . '</td><td class="mem-nw">' . ((int) $r['price'] ? esc_html(mem_rub((int) $r['price'])) : '') . '</td></tr>';
    }
    $h .= '</tbody><tfoot><tr><td>' . esc_html($c['total'] ?: 'Итого') . '</td><td class="mem-nw">' . esc_html(mem_rub(mem_cremation_total())) . '</td></tr></tfoot></table></figure>';
    return $h;
});

/* ---------- Разовый перевод страниц на шорткоды ----------
   Заменяем только то, что точно совпадает с текущими ценами; правка сохраняется как обычная редакция страницы,
   её можно откатить в «Редакциях». */
function mem_prices_migrate() {
    $N = '(?:\s|&nbsp;|\x{00A0}|\x{202F})';
    $num = fn($v) => preg_replace('/(\d)(?=(\d{3})+$)/', '$1' . $N . '?', (string) $v); // 58000 → 58(?:…)?000
    $done = [];
    $pages = get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft', 'private', 'pending', 'future'], 'posts_per_page' => -1]);
    $saved = get_option('mem_prices');
    // в уже сохранённые «Отдельные цены» добавляем пособие, если его там нет
    if (is_array($saved) && !in_array('posobie', array_column($saved['other']['items'] ?? [], 'code'), true)) {
        $def = mem_prices_defaults()['other']['items'];
        $saved['other']['items'][] = $def[count($def) - 1];
        update_option('mem_prices', $saved);
    }

    foreach ($pages as $p) {
        $c = $p->post_content;
        $orig = $c;

        // 1) таблица кремации → [mem_cremation]; если цен ещё не сохраняли — берём их из таблицы
        $c = preg_replace_callback('~<!-- wp:table\b[^>]*-->.*?<!-- /wp:table -->~s', function ($m) use (&$saved) {
            if (mb_stripos($m[0], 'Кремирование') === false) return $m[0];
            if (!is_array($saved) && preg_match_all('~<tr><td>(.*?)</td><td>(.*?)</td></tr>~su', $m[0], $rr, PREG_SET_ORDER)) {
                $rows = []; $total = '';
                foreach ($rr as $r) {
                    $name = trim(wp_strip_all_tags($r[1]));
                    if (preg_match('/^итого/ui', $name)) { $total = $name; continue; }
                    $rows[] = ['name' => $name, 'price' => (int) preg_replace('/\D/', '', html_entity_decode($r[2]))];
                }
                if ($rows) {
                    $d = mem_prices_defaults();
                    $d['cremation']['rows'] = $rows;
                    if ($total) $d['cremation']['total'] = $total;
                    $saved = $d;
                    update_option('mem_prices', $d);
                }
            }
            return "<!-- wp:shortcode -->\n[mem_cremation]\n<!-- /wp:shortcode -->";
        }, $c);

        $krem = (int) array_sum(array_map(fn($r) => (int) ($r['price'] ?? 0), (is_array($saved) ? $saved : mem_prices_defaults())['cremation']['rows']));
        // 2) итог кремации в тексте
        if ($krem) $c = preg_replace('/' . $num($krem) . $N . '?(?:₽|рублей|руб\.?)/u', '[mem_price id="kremaciya"]', $c);
        // 3) пособие на погребение, лента и доставка венков
        $c = preg_replace('/9' . $N . '?678,63' . $N . '?(?:₽|рублей|руб\.?)/u', '[mem_price id="posobie"]', $c);
        $c = preg_replace('/200' . $N . '?[–—-]' . $N . '?300' . $N . '?(?:₽|рублей|руб\.?)/u', '[mem_price id="lenta"]', $c);
        $c = preg_replace('/(Доставк[аи][^<]{0,60}?(?:<\/(?:strong|b)>)?' . $N . '*(?:[—–-]' . $N . '*|<\/td><td>))500' . $N . '?(?:₽|рублей|руб\.?)/u', '$1[mem_price id="dostavka"]', $c);
        // 4) цифры каталога (число моделей, цены «от / до») — везде, где они совпадают с каталогом
        $c = mem_catalog_numbers($c, mem_page_term($p->ID));
        // 5) все шорткоды каталога — в понятный вид: cat="Венки" ot="нет" вместо cat="86" prefix="0"
        $c = mem_sc_readable($c);

        if ($c !== $orig) {
            wp_update_post(['ID' => $p->ID, 'post_content' => wp_slash($c)]);
            $done[] = $p->ID;
        }
    }
    // описания разделов каталога
    foreach (get_terms(['taxonomy' => 'mem_cat', 'hide_empty' => false]) as $t) {
        if ($t->description === '') continue;
        $d = mem_sc_readable(mem_catalog_numbers($t->description, $t->term_id));
        if ($d !== $t->description) wp_update_term($t->term_id, 'mem_cat', ['description' => $d]);
    }
    return $done;
}

/** Раздел каталога страницы: «Товары на странице» или блок [mem_catalog] в тексте */
function mem_page_term($pid) {
    $v = get_post_meta($pid, '_mem_products', true);
    if (is_numeric($v) && (int) $v > 0) return (int) $v;
    return (int) get_post_meta($pid, '_mem_cat', true);
}

/** Основа слова для поиска названия раздела в тексте: «Оградки» → «оград», «Венки» → «вен» */
function mem_stems($text) {
    $w = preg_split('/[^\p{L}]+/u', str_replace('ё', 'е', mb_strtolower($text)), -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    foreach ($w as $x) if (mb_strlen($x) >= 4) $out[] = mb_substr($x, 0, max(3, mb_strlen($x) - 2));
    return array_values(array_unique($out));
}

/**
 * Заменяет в HTML цифры каталога на шорткоды: «72 модели», «от 800 ₽», «от 800 до 9 500 ₽», «до 6 800 ₽», «Венки — 47, от 800 ₽».
 * Раздел для каждой фразы (абзаца, пункта списка, подписи, строки таблицы, вопроса) ищется так:
 * ссылка на страницу раздела → название раздела в тексте → раздел самой страницы.
 * Заменяется только число, которое СОВПАДАЕТ с текущими данными найденного раздела — остальное не трогаем.
 */
function mem_catalog_numbers($html, $page_term = 0) {
    static $terms = null;
    if ($terms === null) {
        $terms = [];
        foreach (get_terms(['taxonomy' => 'mem_cat', 'hide_empty' => false]) as $t) {
            $st = mem_cat_stats($t->term_id);
            if ($st[0]) $terms[$t->term_id] = ['stems' => mem_stems($t->name), 'st' => $st];
        }
    }
    if (!$terms) return $html;
    $N   = '(?:\s|&nbsp;|\x{00A0}|\x{202F})';
    $NUM = '(\d{1,3}(?:' . $N . '?\d{3})*)';
    $R   = $N . '*(?:₽|руб\.?|рублей)';
    $int = fn($x) => (int) preg_replace('/\D/', '', html_entity_decode($x));

    // разделам, вставленным без cat="…" на странице раздела, проставляем раздел — чтобы цифра была верной и в «Вопрос–ответ»
    if ($page_term) $html = preg_replace('/\[(mem_cat_(?:price|count|list))(?![^\]]*\bcat=)/', '[$1 cat="' . $page_term . '"', $html);

    return preg_replace_callback('~<(p|li|figcaption|tr|h[1-6]|summary|dt|dd)\b[^>]*>.*?</\1>~su', function ($seg) use ($terms, $page_term, $N, $NUM, $R, $int) {
        $s = $seg[0];
        if (!preg_match('/\d/', $s)) return $s;
        // кандидаты по порядку
        $cand = [];
        if (preg_match_all('~href="([^"]+)"~', $s, $hm)) {
            foreach ($hm[1] as $u) {
                $pid = url_to_postid(html_entity_decode($u));
                $tid = $pid ? mem_page_term($pid) : 0;
                if ($tid && isset($terms[$tid])) $cand[] = $tid;
            }
        }
        $txt = str_replace('ё', 'е', mb_strtolower(html_entity_decode(wp_strip_all_tags($s), ENT_QUOTES, 'UTF-8')));
        $scored = [];
        foreach ($terms as $tid => $t) {
            if (!$t['stems']) continue;
            $hit = 0;
            foreach ($t['stems'] as $st) if (preg_match('/(?<!\p{L})' . preg_quote($st, '/') . '/u', $txt)) $hit++;
            if ($hit >= min(2, count($t['stems']))) $scored[$tid] = $hit * 10 + count($t['stems']);
        }
        arsort($scored);
        $cand = array_merge($cand, array_keys($scored));
        if ($page_term && isset($terms[$page_term])) $cand[] = $page_term;
        $cand = array_values(array_unique($cand));
        if (!$cand) return $s;
        $find = function ($test) use ($cand, $terms) {
            foreach ($cand as $tid) if ($test($terms[$tid]['st'])) return $tid;
            return 0;
        };
        // «от X до Y ₽»
        $s = preg_replace_callback('/(?<![\d\p{L}])([Оо]т)' . $N . '+' . $NUM . '(?:' . $R . ')?' . $N . '+(до)' . $N . '+' . $NUM . $R . '/u', function ($m) use ($find, $int) {
            $a = $int($m[2]); $b = $int($m[4]);
            $tid = $find(fn($st) => $st[1] === $a && $st[2] === $b);
            return $tid ? $m[1] . "\u{00A0}" . '[mem_cat_price cat="' . $tid . '" prefix="0" rub="0"] ' . $m[3] . "\u{00A0}" . '[mem_cat_price cat="' . $tid . '" max="1" prefix="0"]' : $m[0];
        }, $s);
        // «от X ₽»
        $s = preg_replace_callback('/(?<![\d\p{L}])([Оо]т)' . $N . '+' . $NUM . $R . '/u', function ($m) use ($find, $int) {
            $a = $int($m[2]);
            $tid = $find(fn($st) => $st[1] === $a);
            return $tid ? $m[1] . "\u{00A0}" . '[mem_cat_price cat="' . $tid . '" prefix="0"]' : $m[0];
        }, $s);
        // «до Y ₽»
        $s = preg_replace_callback('/(?<![\d\p{L}])([Дд]о)' . $N . '+' . $NUM . $R . '/u', function ($m) use ($find, $int) {
            $a = $int($m[2]);
            $tid = $find(fn($st) => $st[2] === $a);
            return $tid ? $m[1] . "\u{00A0}" . '[mem_cat_price cat="' . $tid . '" max="1" prefix="0"]' : $m[0];
        }, $s);
        // «72 модели»
        $s = preg_replace_callback('/(?<![\d,.\x{202F}])(\d+)' . $N . '+(?:моделей|модели|модель)(?!\p{L})/u', function ($m) use ($find) {
            $n = (int) $m[1];
            $tid = $n > 1 ? $find(fn($st) => $st[0] === $n) : 0;
            return $tid ? '[mem_cat_count cat="' . $tid . '"]' : $m[0];
        }, $s);
        // «Венки — 47, от …»
        $s = preg_replace_callback('/([—–]' . $N . '*)(\d+)(?=,' . $N . '*[Оо]т)/u', function ($m) use ($find) {
            $n = (int) $m[2];
            $tid = $n > 1 ? $find(fn($st) => $st[0] === $n) : 0;
            return $tid ? $m[1] . '[mem_cat_count cat="' . $tid . '" word="0"]' : $m[0];
        }, $s);
        return $s;
    }, $html);
}

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    // кнопка «Перевести ещё раз» на странице «Цены»
    if (isset($_GET['mem_reprice']) && wp_verify_nonce($_GET['mem_reprice'], 'mem_reprice')) {
        update_option('mem_prices_migrated_pages', mem_prices_migrate(), false);
        wp_safe_redirect(admin_url('admin.php?page=mem-prices&repriced=1'));
        exit;
    }
    if ((int) get_option('mem_prices_migrated') >= 4) return; // 2 — цифры каталога, 3 — пособие, 4 — понятные шорткоды
    update_option('mem_prices_migrated', 4, false);
    update_option('mem_prices_migrated_pages', mem_prices_migrate(), false);
    // пакеты из настроек главной сохраняем в «Цены», чтобы они не потерялись при следующем сохранении главной
    if (get_option('mem_prices') === false) add_option('mem_prices', mem_prices_defaults());
});

/* ---------- Проверка: цены в текстах, которые не меняются сами ---------- */
/** Фразы с ценой из текста: целые предложения / строки таблицы, где есть «₽» или «руб» */
function mem_price_phrases($html) {
    $t = preg_replace('/\[[a-z_]+[^\]]*\]/', '', (string) $html);                      // то, что уже считается само
    $t = preg_replace('~</(td)>~', ' — ', $t);
    $t = preg_replace('~</(tr|p|li|h\d|summary|div|figcaption)>|<br\s*/?>~', "\n", $t);
    $t = html_entity_decode(wp_strip_all_tags($t), ENT_QUOTES, 'UTF-8');
    $out = [];
    foreach (preg_split('/\n+|(?<=[.!?])\s+(?=[А-ЯЁA-Z«])/u', $t) as $ph) {
        $ph = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $ph), " —\t");
        if ($ph === '' || !preg_match('/\d\s?(?:₽|руб)/u', $ph)) continue;
        if (preg_match('/участ\w*\s*\d+(?:,\d+)?\s*[×x]\s*\d/u', $ph)) continue; // примеры выполненных работ «участок 2,5 × 3 м — 197 400 ₽» — это не прайс
        $out[] = mb_strimwidth($ph, 0, 140, '…');
    }
    return array_values(array_unique($out));
}

function mem_prices_audit() {
    $out = [];
    $pages = get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => -1]);
    foreach ($pages as $p) {
        if ($ph = mem_price_phrases($p->post_content)) $out[] = [$p, $ph];
    }
    foreach (get_terms(['taxonomy' => 'mem_cat', 'hide_empty' => false]) as $term) {
        if ($ph = mem_price_phrases($term->description)) $out[] = [$term, $ph];
    }
    return $out;
}

function mem_prices_audit_html() {
    if (!is_admin() || ($_GET['page'] ?? '') !== 'mem-prices') return '';
    $done = (array) get_option('mem_prices_migrated_pages', []);
    $h = '';
    if ($done) {
        $h .= '<br><br><b>Переведены на автоматические цены:</b> ' . implode(', ', array_map(fn($id) => '<a href="' . esc_url(get_edit_post_link($id)) . '">' . esc_html(get_the_title($id)) . '</a>', $done))
            . '. Прежний текст сохранён в «Редакциях» страницы.';
    }
    $btn = ' <a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=mem-prices'), 'mem_reprice', 'mem_reprice')) . '">Перевести цены в текстах на автоматические</a>'
         . ' <span style="color:#666">— тема заменит в текстах страниц итог кремации, ленту, доставку и цены каталога, которые совпадают с текущими, на шорткоды.</span>';
    if (!empty($_GET['repriced'])) $h .= '<br><br>✔ Готово.';
    $list = mem_prices_audit();
    if (!$list) return $h . '<br><br>✔ Цен, вписанных в тексты вручную, не найдено.';
    $h .= '<br><br>' . $btn;
    $h .= '<br><br><b>Цены, вписанные в текст вручную — сами не меняются</b> (проверьте, актуальны ли; при желании замените шорткодом):<ul style="list-style:disc;margin-left:1.5em">';
    foreach ($list as [$obj, $lines]) {
        $link = $obj instanceof WP_Term ? get_edit_term_link($obj) : get_edit_post_link($obj->ID);
        $name = $obj instanceof WP_Term ? 'Раздел каталога «' . $obj->name . '»' : $obj->post_title;
        $h .= '<li style="margin-bottom:.6em"><a href="' . esc_url($link) . '"><b>' . esc_html($name) . '</b></a> (' . ($obj instanceof WP_Term ? 'описание раздела' : 'страница') . ')<br>'
            . implode('<br>', array_map(fn($l) => '— <i>' . esc_html($l) . '</i>', $lines)) . '</li>';
    }
    return $h . '</ul>';
}
