<?php
/**
 * «Шорткоды и блоки» — понятный справочник:
 *   • «Что значит этот шорткод?» — вставьте любой шорткод, страница объяснит каждую часть и покажет, что выйдет на сайте;
 *   • «Собрать шорткод» — выбираете, что нужно, кнопками и списками, код пишется сам;
 *   • карточки всех шорткодов: что выводит, что выводит прямо сейчас, параметры, примеры, где уже используется;
 *   • готовые заготовки (паттерны).
 */
defined('ABSPATH') || exit;

/* ---------- Описание всех шорткодов ---------- */
function mem_sc_spec() {
    // разделы каталога — с отступами по вложенности
    $cats = ['' => '— раздел этой страницы —'];
    $walk = function ($parent, $depth) use (&$walk, &$cats) {
        foreach (get_terms(['taxonomy' => 'mem_cat', 'parent' => $parent, 'hide_empty' => false, 'orderby' => 'name']) as $t) {
            $cats[mem_cat_ref($t->term_id)] = str_repeat('— ', $depth) . $t->name;
            $walk($t->term_id, $depth + 1);
        }
    };
    $walk(0, 0);
    $kinds = ['' => '— все виды —'];
    foreach (get_terms(['taxonomy' => 'mem_work_type', 'hide_empty' => false]) as $t) if (!is_wp_error($t)) $kinds[$t->slug] = $t->name;
    $codes = ['kremaciya' => 'Итог пакета кремации'];
    foreach (mem_prices('other')['items'] ?? [] as $it) if (trim($it['code'] ?? '') !== '') $codes[trim($it['code'])] = $it['name'];
    $pk = ['' => '—'];
    foreach (mem_packages() as $p) $pk[$p['name']] = 'Пакет «' . $p['name'] . '»';
    $yes = ['type' => 'bool', 'default' => 'да'];

    return [
        /* ===== Цифры и цены — вставляются прямо в текст ===== */
        'mem_cat_count' => [
            'group' => 'Цифры и цены в тексте', 'title' => 'Сколько моделей в разделе', 'inline' => true,
            'desc'  => 'Число моделей раздела каталога со словом «модель/модели/моделей». Меняется само, когда добавляете или убираете товары.',
            'params' => [
                'cat'   => ['type' => 'select', 'label' => 'Раздел каталога', 'options' => $cats],
                'slovo' => ['label' => 'Писать слово «моделей»'] + $yes,
            ],
            'old' => ['word' => 'slovo'],
            'examples' => [
                ['[mem_cat_count]', 'число моделей каталога этой страницы'],
                ['[mem_cat_count slovo="нет"]', 'только число, без слова — для «Венки — 47, от 800 ₽»'],
            ],
        ],
        'mem_cat_price' => [
            'group' => 'Цифры и цены в тексте', 'title' => 'Цена из каталога', 'inline' => true,
            'desc'  => 'Самая низкая (или самая высокая) цена моделей раздела. Меняется сама, когда меняете цены товаров.',
            'params' => [
                'cat'  => ['type' => 'select', 'label' => 'Раздел каталога', 'options' => $cats],
                'cena' => ['type' => 'select', 'label' => 'Какая цена', 'options' => ['' => 'самая низкая', 'макс' => 'самая высокая']],
                'ot'   => ['label' => 'Писать «от» / «до» перед ценой'] + $yes,
                'rub'  => ['label' => 'Писать знак ₽'] + $yes,
            ],
            'old' => ['max' => 'cena', 'prefix' => 'ot'],
            'examples' => [
                ['[mem_cat_price]', '«от 35 000 ₽» — самая низкая цена каталога этой страницы'],
                ['[mem_cat_price cena="макс"]', '«до 69 000 ₽» — самая высокая'],
                ['от [mem_cat_price ot="нет" rub="нет"] до [mem_cat_price cena="макс" ot="нет"]', '«от 800 до 9 500 ₽» — диапазон цен'],
            ],
        ],
        'mem_price' => [
            'group' => 'Цифры и цены в тексте', 'title' => 'Цена из «Мемориал → Цены»', 'inline' => true,
            'desc'  => 'Одна цена из раздела «Цены»: итог кремации, итог пакета похорон или цена из «Отдельные цены» (лента, доставка, пособие…). Меняете в «Ценах» — меняется на всех страницах.',
            'params' => [
                'id'    => ['type' => 'select', 'label' => 'Какая цена', 'options' => ['' => '—'] + $codes],
                'paket' => ['type' => 'select', 'label' => '…или итог пакета похорон', 'options' => $pk],
            ],
            'examples' => [
                ['[mem_price id="kremaciya"]', 'итог пакета кремации'],
                ['[mem_price id="lenta"]', 'цена надписи на ленте'],
                ['[mem_price paket="Эконом"]', 'итог пакета «Эконом»'],
            ],
        ],
        'mem_years' => [
            'group' => 'Цифры и цены в тексте', 'title' => 'Сколько лет компании', 'inline' => true,
            'desc'  => 'Число лет с года основания (из «Мемориал → Контакты»). Каждый январь увеличивается само.',
            'examples' => [['Работаем более [mem_years] лет', '«Работаем более 23 лет»']],
        ],
        'mem_phone' => [
            'group' => 'Цифры и цены в тексте', 'title' => 'Телефон ссылкой', 'inline' => true,
            'desc'  => 'Основной телефон из «Контактов» — на телефоне по нему можно сразу позвонить.',
            'examples' => [['Звоните: [mem_phone]', 'телефон внутри предложения']],
        ],
        'mem_cat_photos' => [
            'group' => 'Блоки', 'title' => 'Фото товаров из каталога',
            'desc'  => 'Сетка фото товаров из выбранных разделов каталога, крупно по клику, с названием и ценой. Новые товары появляются сами.',
            'params' => [
                'cat'      => ['type' => 'text', 'label' => 'Разделы каталога (через запятую)', 'placeholder' => 'Венки, Гробы'],
                'chislo'   => ['type' => 'number', 'label' => 'Сколько фото', 'placeholder' => '6'],
                'poryadok' => ['type' => 'select', 'label' => 'Какие', 'options' => ['' => 'по порядку каталога', 'случайно' => 'случайные — при каждом открытии другие']],
                'cena'     => ['label' => 'Цена в подписи'] + $yes,
            ],
            'examples' => [['[mem_cat_photos cat="Венки, Гробы" chislo="6" poryadok="случайно"]', '6 случайных фото венков и гробов']],
        ],
        'mem_restaurants' => [
            'group' => 'Блоки', 'title' => 'Залы для поминального обеда',
            'desc'  => 'Карточки залов из «Мемориал → Залы для поминок»: фото, адрес, вместимость, цена и кнопка «Перезвоните мне». Телефоны ресторанов не выводятся — заказ идёт через вас.',
            'params' => [
                'title' => ['type' => 'text', 'label' => 'Заголовок (пусто — из настроек)'],
                'text'  => ['type' => 'text', 'label' => 'Текст под заголовком (пусто — из настроек)'],
            ],
            'examples' => [['[mem_restaurants]', 'залы с заголовком из настроек']],
        ],
        'mem_cemeteries' => [
            'group' => 'Блоки', 'title' => 'Список кладбищ',
            'desc'  => 'Кладбища из «Мемориал → Кладбища»: адрес, время работы, можно ли хоронить, подробности и карта. Ставится на любую страницу.',
            'params' => [
                'status' => ['type' => 'select', 'label' => 'Какие', 'options' => ['' => 'все', 'действующие' => 'только действующие', 'действующие, подзахоронение' => 'действующие и подзахоронение', 'закрытые' => 'только закрытые']],
                'filtr'  => ['type' => 'select', 'label' => 'Кнопки-фильтры', 'options' => ['' => 'показывать', 'нет' => 'не показывать']],
            ],
            'examples' => [['[mem_cemeteries]', 'все, с фильтром'], ['[mem_cemeteries status="действующие" filtr="нет"]', 'только где можно хоронить — для страниц услуг']],
        ],
        'mem_operator' => [
            'group' => 'Цифры и цены в тексте', 'title' => 'Реквизиты компании (для документов)', 'inline' => true,
            'desc'  => 'Название и реквизиты из «Мемориал → Контакты → Реквизиты». С chto= — адрес, почта, телефон или адрес сайта. Используется в политиках и согласии.',
            'params' => ['chto' => ['type' => 'select', 'label' => 'Что вывести', 'options' => ['' => 'название и ИНН/ОГРНИП', 'адрес' => 'адрес', 'почта' => 'эл. почту', 'телефон' => 'телефон', 'сайт' => 'адрес сайта']]],
            'examples' => [['Оператор — [mem_operator]', '«ИП Красильникова Л. В. (ИНН …, ОГРНИП …)»'], ['Пишите на [mem_operator chto="почта"]', 'почта ссылкой']],
        ],
        'mem_doc' => [
            'group' => 'Цифры и цены в тексте', 'title' => 'Ссылка на документ', 'inline' => true,
            'desc'  => 'Ссылка на страницу из «Мемориал → Документы». Если страницу поменяют — ссылка обновится сама.',
            'params' => ['kto' => ['type' => 'select', 'label' => 'Документ', 'options' => ['policy' => 'Политика обработки ПД', 'consent' => 'Согласие на обработку ПД', 'cookies' => 'Политика cookie']]],
            'examples' => [['См. [mem_doc kto="cookies"]Политику cookie[/mem_doc]', 'ссылка с вашим текстом']],
        ],

        /* ===== Блоки ===== */
        'mem_catalog' => [
            'group' => 'Каталог и цены — блоки', 'title' => 'Каталог товаров',
            'desc'  => 'Карточки товаров раздела с кнопками подразделов и сортировкой по цене.',
            'params' => [
                'cat'     => ['type' => 'select', 'label' => 'Раздел каталога', 'options' => $cats],
                'nav'     => ['label' => 'Кнопки подразделов сверху'] + $yes,
                'sort'    => ['label' => 'Сортировка по цене'] + $yes,
            ],
            'examples' => [['[mem_catalog]', 'каталог раздела, к которому привязана страница'], ['[mem_catalog nav="нет"]', 'без кнопок подразделов']],
        ],
        'mem_cat_list' => [
            'group' => 'Каталог и цены — блоки', 'title' => 'Список подразделов',
            'desc'  => 'Список подразделов со ссылками: «С крестом — 21, от 35 000 ₽». Нажатие ведёт к разделу в каталоге ниже.',
            'params' => [
                'cat'  => ['type' => 'select', 'label' => 'Раздел каталога', 'options' => $cats],
                'ceny' => ['label' => 'Писать цену «от»'] + $yes,
            ],
            'old' => ['price' => 'ceny'],
            'examples' => [['[mem_cat_list]', 'подразделы каталога этой страницы'], ['[mem_cat_list ceny="нет"]', 'без цен']],
        ],
        'mem_cards' => [
            'group' => 'Каталог и цены — блоки', 'title' => 'Карточки разделов',
            'desc'  => 'Карточки со ссылками на разделы — собираются сами из подпунктов этой страницы в главном меню (в том же порядке). Фото — как в плитке каталога, под названием — «96 моделей, от 35 000 ₽» (для страниц каталога считается само) и подпись страницы. Название и подпись меняются в редакторе той страницы: панель «Плитка и карточка раздела».',
            'params' => [
                'pages'   => ['type' => 'text', 'label' => 'Свой список страниц (адреса через запятую)', 'placeholder' => 'пусто — подпункты меню'],
                'foto'    => ['label' => 'Фото'] + $yes,
                'chislo'  => ['label' => 'Число моделей'] + $yes,
                'cena'    => ['label' => 'Цена «от»'] + $yes,
                'podpis'  => ['label' => 'Подпись страницы'] + $yes,
                'strelka' => ['label' => '«Подробнее →»'] + $yes,
            ],
            'examples' => [
                ['[mem_cards]', 'карточки подразделов этой страницы — для «Ритуальных товаров», «Благоустройства»'],
                ['[mem_cards cena="нет"]', 'без цен'],
                ['[mem_cards pages="pamyatniki, ogradki-2"]', 'только выбранные страницы'],
            ],
        ],
        'mem_packages' => [
            'group' => 'Каталог и цены — блоки', 'title' => 'Пакеты похорон',
            'desc'  => 'Карточки «Эконом / Стандарт / Премиум» с итогом и составом — из «Мемориал → Цены».',
            'examples' => [['[mem_packages]', 'все пакеты']],
        ],
        'mem_cremation' => [
            'group' => 'Каталог и цены — блоки', 'title' => 'Пакет кремации',
            'desc'  => 'Таблица «Что входит — Стоимость» с итогом — из «Мемориал → Цены».',
            'examples' => [['[mem_cremation]', 'таблица кремации']],
        ],
        'mem_services' => [
            'group' => 'Каталог и цены — блоки', 'title' => 'Карточки услуг',
            'desc'  => 'Карточки услуг, как на главной. Можно добавить другие страницы по их адресу.',
            'params' => ['more' => ['type' => 'text', 'label' => 'Добавить страницы (адреса через запятую)', 'placeholder' => 'pominalnyj-obed, kremaciya']],
            'examples' => [['[mem_services]', 'услуги с главной']],
        ],
        'mem_works' => [
            'group' => 'Работы, отзывы, вопросы', 'title' => 'Наши работы',
            'desc'  => 'Работы с фото, годом и ценами — из «Наши работы». Карточками — короткие примеры со ссылкой на работу; какие работы попадают в примеры, отмечается в «Наши работы» (меню «⋯» у работы → «Показывать в примерах»).',
            'params' => [
                'vid'      => ['type' => 'select', 'label' => 'Как показать', 'options' => ['' => 'полностью (фото, состав и цены)', 'карточки' => 'карточками (фото, название, размер, цена)', 'фото' => 'фотогалереей (все фото работ, крупно по клику)']],
                'type'     => ['type' => 'select', 'label' => 'Вид работ', 'options' => $kinds],
                'limit'    => ['type' => 'number', 'label' => 'Полностью: сколько показать (пусто — все)'],
                'nav'      => ['label' => 'Полностью: кнопки видов работ сверху'] + $yes,
                'sort'     => ['label' => 'Полностью: сортировка'] + $yes,
                'chislo'   => ['type' => 'number', 'label' => 'Карточки / фото: сколько показать', 'placeholder' => '6 карточек / 12 фото'],
                'poryadok' => ['type' => 'select', 'label' => 'Карточки: какие', 'options' => ['' => 'по порядку из «Наших работ»', 'случайно' => 'случайные — при каждом открытии другие', 'новые' => 'сначала новые', 'дешевле' => 'сначала дешевле', 'дороже' => 'сначала дороже']],
                'raboty'   => ['type' => 'text', 'label' => 'Карточки: только эти работы (названия через запятую)', 'placeholder' => 'пусто — отмеченные «В примерах», иначе все'],
                'razmer'   => ['label' => 'Карточки: размер участка'] + $yes,
                'cena'     => ['label' => 'Карточки: цена'] + $yes,
            ],
            'examples' => [['[mem_works]', 'все работы'], ['[mem_works limit="3"]', 'три работы — для превью на странице услуги'],
                           ['[mem_works vid="карточки" chislo="6" poryadok="случайно"]', '6 случайных работ карточками — «Примеры наших работ»'],
                           ['[mem_works vid="карточки" chislo="3" type="Укладка плитки"]', '3 работы по плитке карточками'],
                           ['[mem_works vid="фото" chislo="12" poryadok="случайно"]', '12 случайных фото работ — для «Фотогалереи»']],
        ],
        'mem_reviews' => [
            'group' => 'Работы, отзывы, вопросы', 'title' => 'Отзывы',
            'desc'  => 'Отзывы с Яндекс Карт, 2ГИС и других сайтов по филиалам + «Из книги отзывов». Настраиваются в «Мемориал → Отзывы».',
            'examples' => [['[mem_reviews]', 'для страницы «Отзывы»']],
        ],
        'mem_rewio' => [
            'group' => 'Работы, отзывы, вопросы', 'title' => 'Отзывы Rewio',
            'desc'  => 'Отзывы с Яндекс Карт, 2ГИС, Google и других площадок через Rewio — карточками в оформлении сайта, со средней оценкой, фото и ответами. Что показывать и как часто обновлять — «Мемориал → Отзывы Rewio».',
            'params' => [
                'title' => ['type' => 'text', 'label' => 'Заголовок', 'placeholder' => 'Отзывы клиентов'],
                'count' => ['type' => 'number', 'label' => 'Сколько показать сразу'],
                'cols'  => ['type' => 'select', 'label' => 'Колонок', 'options' => ['' => 'как в настройках', '3' => '3', '2' => '2', '1' => '1']],
            ],
            'examples' => [['[mem_rewio]', 'все отзывы по настройкам'], ['[mem_rewio count="3" title="Что о нас пишут"]', 'три отзыва — для главной или страницы услуги']],
        ],
        'mem_bookreviews' => [
            'group' => 'Работы, отзывы, вопросы', 'title' => 'Отзывы из книги',
            'desc'  => 'Только перепечатанные отзывы из бумажной книги («Мемориал → Отзывы из книги»).',
            'params' => [
                'limit' => ['type' => 'number', 'label' => 'Сколько показать (пусто — все)'],
                'title' => ['type' => 'text', 'label' => 'Заголовок', 'placeholder' => 'Из книги отзывов'],
            ],
            'examples' => [['[mem_bookreviews limit="3"]', 'три последних отзыва']],
        ],
        'mem_faq' => [
            'group' => 'Работы, отзывы, вопросы', 'title' => 'Все вопросы сайта',
            'desc'  => 'Собирает частые вопросы (блоки «Подробности») со всех страниц в одну с поиском и фильтром. Для страницы «Вопрос–ответ».',
            'params' => [
                'search'  => ['label' => 'Поиск по вопросам'] + $yes,
                'exclude' => ['type' => 'text', 'label' => 'Не брать вопросы со страниц (номера через запятую)'],
            ],
            'examples' => [['[mem_faq]', 'все вопросы']],
        ],
        'mem_cta' => [
            'group' => 'Работы, отзывы, вопросы', 'title' => 'Призыв позвонить',
            'desc'  => 'Тёмная плашка с заголовком, телефоном и кнопкой «Перезвоните мне».',
            'params' => [
                'title' => ['type' => 'text', 'label' => 'Заголовок', 'placeholder' => 'Позвоните — расскажем, с чего начать'],
                'text'  => ['type' => 'text', 'label' => 'Текст под заголовком', 'placeholder' => 'Ответим на вопросы, подскажем по документам и стоимости.'],
            ],
            'examples' => [['[mem_cta title="Поможем выбрать" text="Позвоните — подскажем по ценам."]', 'свои заголовок и текст']],
        ],
    ];
}

/** Объяснение шорткода словами: [что это, [[параметр, значение словами, предупреждение?], …]] */
function mem_sc_explain($code) {
    $spec = mem_sc_spec();
    if (!preg_match('/\[([a-z_]+)(\s[^\]]*)?\]/u', $code, $m)) return ['error' => 'Шорткод не найден. Он выглядит так: [mem_cat_price cat="Венки"] — в квадратных скобках.'];
    $tag = $m[1];
    if (!isset($spec[$tag])) return ['error' => '[' . $tag . '] — не шорткод этой темы.' . (shortcode_exists($tag) ? ' Его добавил плагин или WordPress.' : ' Такого шорткода нет — проверьте написание.')];
    $s = $spec[$tag];
    $at = shortcode_parse_atts(trim($m[2] ?? '')) ?: [];
    $rows = [];
    $seen = [];
    foreach ((array) $at as $k => $v) {
        if (is_int($k)) { $rows[] = [$v, 'лишний текст без «=»', true]; continue; }
        $name = $s['old'][$k] ?? $k;
        $p = $s['params'][$name] ?? null;
        if (!$p) { $rows[] = [$k . '="' . $v . '"', 'неизвестный параметр — не действует', true]; continue; }
        $seen[$name] = true;
        $old = isset($s['old'][$k]) ? ' (старая запись ' . $k . '="' . $v . '")' : '';
        $warn = false;
        if (($p['type'] ?? 'bool') === 'bool') {
            $val = mem_sc_no($v) ? 'нет' : 'да';
            if (isset($s['old'][$k]) && $k === 'max') $val = mem_sc_no($v) ? 'самая низкая' : 'самая высокая';
        } elseif ($p['type'] === 'select') {
            $opts = $p['options'];
            if ($name === 'cat') {
                $t = mem_summary_cat($v);
                $val = $t ? '«' . $t->name . '»' . (ctype_digit((string) $v) ? ' (номер ' . $v . ')' : '') : '«' . $v . '» — такого раздела нет';
                $warn = !$t;
            } elseif ($name === 'cena') {
                $hi = $k === 'max' ? !mem_sc_no($v) : (bool) preg_match('/^(макс|max)/u', mb_strtolower($v));
                $val = $hi ? 'самая высокая' : 'самая низкая';
            } else {
                $val = isset($opts[$v]) ? $opts[$v] : '«' . $v . '» — нет такого значения';
                $warn = !isset($opts[$v]);
            }
        } else {
            $val = '«' . $v . '»';
        }
        $rows[] = [$p['label'], $val . $old, $warn];
    }
    // что взято по умолчанию
    foreach ($s['params'] ?? [] as $name => $p) {
        if (isset($seen[$name])) continue;
        if ($name === 'cat') $rows[] = [$p['label'], 'не указан — берётся раздел страницы, на которой стоит шорткод', false, true];
        elseif (($p['type'] ?? 'bool') === 'bool') $rows[] = [$p['label'], 'да (по умолчанию)', false, true];
        elseif ($p['type'] === 'select' && isset($p['options'][''])) $rows[] = [$p['label'], 'не указано — ' . $p['options'][''], false, true];
    }
    return ['tag' => $tag, 'title' => $s['title'], 'desc' => $s['desc'], 'rows' => $rows, 'inline' => !empty($s['inline'])];
}

/** Страницы, где шорткод уже стоит */
function mem_sc_where($tag) {
    static $pages = null;
    if ($pages === null) $pages = get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft'], 'posts_per_page' => -1]);
    $out = [];
    foreach ($pages as $p) if (strpos($p->post_content, '[' . $tag) !== false) $out[] = $p;
    return $out;
}

/** Результат шорткода текстом (для коротких) */
function mem_sc_result($code, $inline, $page_id = 0) {
    global $post;
    $keep = $post;
    if ($page_id && ($pp = get_post($page_id))) { $post = $pp; setup_postdata($pp); }
    $html = do_shortcode($code);
    if ($page_id) { $post = $keep; if ($keep) setup_postdata($keep); else wp_reset_postdata(); }
    if (!$inline) {
        $len = mb_strlen(trim(wp_strip_all_tags($html)));
        return $len ? 'крупный блок на странице (' . $len . ' знаков текста)' : 'пусто — нечего показать';
    }
    $t = trim(html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8'));
    return $t !== '' ? $t : 'пусто';
}

add_action('wp_ajax_mem_sc_explain', function () {
    check_ajax_referer('mem_sc', 'nonce');
    if (!current_user_can('edit_pages')) wp_send_json_error();
    $code = trim(wp_unslash((string) ($_POST['code'] ?? '')));
    $page = absint($_POST['page'] ?? 0);
    $e = mem_sc_explain($code);
    if (!empty($e['error'])) wp_send_json_success($e);
    preg_match('/\[[a-z_]+[^\]]*\]/u', $code, $one);
    $e['result'] = mem_sc_result($e['inline'] ? $code : $one[0], $e['inline'], $page);
    $e['where'] = array_map(fn($p) => ['t' => $p->post_title, 'u' => get_edit_post_link($p->ID, 'raw')], mem_sc_where($e['tag']));
    wp_send_json_success($e);
});

/* ---------- Страница ---------- */
add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=page', 'Шорткоды и блоки', 'Шорткоды и блоки', 'edit_pages', 'mem-help', 'mem_help_page');
    add_submenu_page('mem-contacts', 'Шорткоды и блоки', 'Шорткоды и блоки', 'manage_options', 'mem-help-m', 'mem_help_page');
}, 40);

function mem_help_page() {
    $spec = mem_sc_spec();
    $groups = [];
    foreach ($spec as $tag => $s) $groups[$s['group']][$tag] = $s;
    $cat_pages = array_filter(get_posts(['post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1]), fn($p) => mem_page_term($p->ID));
    ?>
    <div class="wrap mem-help">
      <h1>Шорткоды и блоки</h1>

      <div class="mh-intro">
        <div><b>Шорткод</b> — короткая команда в квадратных скобках. В редакторе вы видите команду, а на сайте на её месте — цифра, цена или целый блок. Всё, что внутри, считается само: поменяли цену товара — поменялось на всех страницах.</div>
        <div><b>Куда вставлять.</b> Цифры и цены — прямо в текст абзаца: <code>Венки от [mem_cat_price]</code>. Блоки (каталог, пакеты, отзывы) — в отдельный блок <b>«Шорткод»</b>: «+» → найдите «Шорткод».</div>
        <div><b>Как читать.</b> Сначала имя — что выводит, дальше уточнения <code>имя="значение"</code>. Например, <code>[mem_cat_price cat="Венки" cena="макс"]</code> — «цена из каталога, раздел Венки, самая высокая».</div>
      </div>

      <div class="mh-tools">
        <section class="mh-box">
          <h2>Что значит этот шорткод?</h2>
          <p class="mh-mute">Вставьте шорткод со страницы — объясню каждую часть и покажу, что выйдет на сайте.</p>
          <textarea id="mh-decode" rows="2" placeholder='например: [mem_cat_price cat="86" prefix="0"]'></textarea>
          <label class="mh-ctx">Как на странице:
            <select id="mh-page"><option value="0">— без страницы —</option>
              <?php foreach ($cat_pages as $p) echo '<option value="' . (int) $p->ID . '">' . esc_html($p->post_title) . '</option>'; ?>
            </select>
            <span class="mh-mute">важно для шорткодов без cat="…" — они берут раздел страницы</span>
          </label>
          <div id="mh-decoded" class="mh-out" hidden></div>
        </section>

        <section class="mh-box" id="mh-build-box">
          <h2>Собрать шорткод</h2>
          <p class="mh-mute">Выберите, что нужно вывести, — код напишется сам. Скопируйте и вставьте на страницу.</p>
          <select id="mh-tag">
            <?php foreach ($groups as $g => $list) {
                echo '<optgroup label="' . esc_attr($g) . '">';
                foreach ($list as $tag => $s) echo '<option value="' . esc_attr($tag) . '">' . esc_html($s['title']) . '</option>';
                echo '</optgroup>';
            } ?>
          </select>
          <div id="mh-fields"></div>
          <div class="mh-code"><code id="mh-built"></code><button type="button" class="button button-primary mem-copy" id="mh-copy">Скопировать</button></div>
          <div id="mh-built-out" class="mh-out"></div>
        </section>
      </div>

      <p><input type="search" id="mh-filter" class="mh-filter" placeholder="Найти шорткод: цена, отзывы, каталог…"></p>

      <?php foreach ($groups as $g => $list) : ?>
        <h2 class="mh-group"><?php echo esc_html($g); ?></h2>
        <div class="mh-cards">
        <?php foreach ($list as $tag => $s) :
            $where = mem_sc_where($tag);
            // пример считаем как на странице с разделом каталога — там, где шорткод уже стоит, или на первой такой
            $ctx = 0;
            foreach ($where as $wp) if (mem_page_term($wp->ID)) { $ctx = $wp->ID; break; }
            if (!$ctx && $cat_pages) $ctx = reset($cat_pages)->ID;
            $now = !empty($s['inline']) ? mem_sc_result($s['examples'][0][0], true, $ctx) : '';
            $ctx_name = $ctx && isset($s['params']['cat']) ? get_the_title($ctx) : ''; ?>
          <article class="mh-card" data-search="<?php echo esc_attr(mb_strtolower($tag . ' ' . $s['title'] . ' ' . $s['desc'] . ' ' . $g)); ?>">
            <header><h3><?php echo esc_html($s['title']); ?></h3><code class="mh-tag">[<?php echo esc_html($tag); ?>]</code></header>
            <p><?php echo esc_html($s['desc']); ?></p>
            <?php if ($now !== '' && $now !== 'пусто' && strpos($now, '[') === false) : ?><p class="mh-now">Сейчас выводит: <b><?php echo esc_html($now); ?></b><?php if ($ctx_name) echo ' <span class="mh-mute">(на странице «' . esc_html($ctx_name) . '»)</span>'; ?></p><?php endif; ?>
            <?php if (!empty($s['params'])) : ?>
              <details class="mh-params"><summary>Уточнения (<?php echo count($s['params']); ?>)</summary><ul>
              <?php foreach ($s['params'] as $k => $p) {
                  $t = $p['type'] ?? 'bool';
                  if ($t === 'bool') {
                      $how = '<code>' . $k . '="нет"</code> — выключить (по умолчанию «да»)';
                  } elseif ($k === 'cat') {
                      $how = '<code>cat="Название раздела"</code>; не указан — раздел страницы';
                  } elseif ($t === 'select') {
                      $parts = [];
                      foreach (array_slice($p['options'], 0, 6, true) as $v => $l) {
                          $parts[] = $v === '' ? 'не указано — ' . esc_html($l) : '<code>' . $k . '="' . esc_html($v) . '"</code> — ' . esc_html($l);
                      }
                      $how = implode('; ', $parts) . (count($p['options']) > 6 ? '; …' : '');
                  } else {
                      $how = '<code>' . $k . '="…"</code>';
                  }
                  echo '<li><b>' . esc_html($p['label']) . '</b>: ' . $how . '</li>';
              } ?>
              </ul></details>
            <?php endif; ?>
            <div class="mh-ex">
              <?php foreach ($s['examples'] as [$code, $what]) : ?>
                <div class="mh-exrow"><button type="button" class="mem-copy mh-chip" data-copy="<?php echo esc_attr($code); ?>" title="Нажмите, чтобы скопировать"><?php echo esc_html($code); ?></button><span>— <?php echo esc_html($what); ?></span></div>
              <?php endforeach; ?>
            </div>
            <footer>
              <?php if ($where) : ?><span class="mh-mute">Стоит на: <?php echo implode(', ', array_map(fn($p) => '<a href="' . esc_url(get_edit_post_link($p->ID)) . '">' . esc_html($p->post_title) . '</a>', array_slice($where, 0, 5))) . (count($where) > 5 ? ' и ещё ' . (count($where) - 5) : ''); ?></span>
              <?php else : ?><span class="mh-mute">Пока нигде не используется</span><?php endif; ?>
              <?php if (!empty($s['params'])) : ?><button type="button" class="button button-small mh-build" data-tag="<?php echo esc_attr($tag); ?>">Собрать →</button><?php endif; ?>
            </footer>
          </article>
        <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <h2 class="mh-group">Готовые заготовки (паттерны)</h2>
      <div class="mh-intro">
        <div><b>Паттерн</b> — готовый кусок страницы: вставили и поменяли текст под себя. В редакторе страницы: <b>«+» → «Паттерны» → «Мемориал»</b>.</div>
        <div>Заготовки темы: «Каталог страницы со сводкой», «Сводка каталога по разделам», «Призыв позвонить», «Делаем сами (фото + текст)», «Частые вопросы», «Таблица цен», «Отзывы из книги», «Наши работы», «Шаги с иконками».</div>
        <div><b>Один блок на многих страницах.</b> Выделите блок → «⋮» → «Создать паттерн» → включите <b>«Синхронизированный»</b>. Дальше меняете в одном месте — меняется везде.</div>
      </div>
    </div>

    <style>
      .mem-help{max-width:1180px}
      .mh-intro{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin:14px 0 18px}
      .mh-intro>div{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;line-height:1.55}
      .mh-tools{display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:16px}
      .mh-box{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px 18px}
      .mh-box h2{margin:0 0 4px}
      .mh-box textarea,.mh-box select#mh-tag{width:100%;font:13px/1.5 Consolas,Monaco,monospace}
      .mh-box select#mh-tag{font-family:inherit;font-size:14px;max-width:none}
      .mh-mute{color:#646970;font-size:12.5px}
      .mh-ctx{display:block;margin:8px 0 0}
      .mh-out{margin-top:12px;background:#f6f7f7;border-radius:8px;padding:12px 14px}
      .mh-out table{width:100%;border-collapse:collapse}
      .mh-out td{padding:4px 6px;vertical-align:top;border-bottom:1px solid #e6e6e6}
      .mh-out td:first-child{width:42%;color:#50575e}
      .mh-out .warn td{color:#b32d2e;font-weight:600}
      .mh-out .def td{color:#8c8f94}
      .mh-res{font-size:15px;margin:10px 0 0}
      .mh-res b{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:2px 8px}
      #mh-fields{display:grid;gap:8px;margin:12px 0}
      #mh-fields label{display:grid;grid-template-columns:44% 1fr;align-items:center;gap:8px}
      #mh-fields input[type=text],#mh-fields input[type=number],#mh-fields select{width:100%;max-width:none}
      .mh-code{display:flex;gap:8px;align-items:center;background:#1d2327;border-radius:8px;padding:10px 12px}
      .mh-code code{background:none;color:#f0f0f1;flex:1;font-size:13.5px;word-break:break-all}
      .mh-filter{width:100%;max-width:520px;font-size:15px;padding:6px 12px;margin-top:18px}
      .mh-group{margin-top:26px}
      .mh-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px}
      .mh-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px 16px;display:flex;flex-direction:column;gap:6px}
      .mh-card header{display:flex;justify-content:space-between;align-items:baseline;gap:8px;flex-wrap:wrap}
      .mh-card h3{margin:0;font-size:15px}
      .mh-card p{margin:0}
      .mh-tag{font-size:12px;background:#f0f6fc;color:#2271b1}
      .mh-now{background:#edfaef;border-radius:6px;padding:6px 10px}
      .mh-params summary{cursor:pointer;color:#2271b1}
      .mh-params ul{margin:6px 0 0 1.2em;list-style:disc}
      .mh-params li{margin:3px 0}
      .mh-ex{display:grid;gap:5px;margin-top:4px}
      .mh-exrow{display:flex;gap:6px;align-items:baseline;flex-wrap:wrap}
      .mh-chip{font:12.5px/1.4 Consolas,Monaco,monospace;background:#f6f7f7;border:1px solid #dcdcde;border-radius:5px;padding:3px 7px;cursor:pointer;text-align:left;color:#1d2327}
      .mh-chip:hover{border-color:#2271b1;background:#f0f6fc}
      .mh-exrow span{color:#50575e;font-size:12.5px}
      .mh-card footer{margin-top:auto;padding-top:8px;display:flex;justify-content:space-between;align-items:center;gap:8px;border-top:1px solid #f0f0f1}
      .mh-hide{display:none!important}
      @media (max-width:782px){.mh-tools{grid-template-columns:1fr}#mh-fields label{grid-template-columns:1fr}}
    </style>
    <script>
    (function () {
      const SPEC = <?php echo wp_json_encode(array_map(fn($s) => ['params' => $s['params'] ?? new stdClass(), 'title' => $s['title']], $spec), JSON_UNESCAPED_UNICODE); ?>;
      const AJ = <?php echo wp_json_encode(['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('mem_sc')]); ?>;
      const $ = s => document.querySelector(s);
      const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
      let tmr = {};
      function ask(code, out, page, key) {
        clearTimeout(tmr[key]);
        tmr[key] = setTimeout(() => {
          const fd = new FormData(); fd.append('action', 'mem_sc_explain'); fd.append('nonce', AJ.nonce); fd.append('code', code); fd.append('page', page || 0);
          fetch(AJ.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json()).then(r => {
            const d = r.data || {}; out.hidden = false;
            if (d.error) { out.innerHTML = '<b style="color:#b32d2e">' + esc(d.error) + '</b>'; return; }
            let h = '<b>' + esc(d.title) + '</b> — ' + esc(d.desc);
            if (d.rows.length) h += '<table>' + d.rows.map(r => '<tr class="' + (r[2] ? 'warn' : (r[3] ? 'def' : '')) + '"><td>' + esc(r[0]) + '</td><td>' + esc(r[1]) + '</td></tr>').join('') + '</table>';
            h += '<p class="mh-res">На сайте будет: <b>' + esc(d.result) + '</b></p>';
            if (d.where && d.where.length) h += '<p class="mh-mute">Уже стоит на: ' + d.where.slice(0, 6).map(w => '<a href="' + esc(w.u) + '">' + esc(w.t) + '</a>').join(', ') + '</p>';
            out.innerHTML = h;
          });
        }, 250);
      }
      // расшифровка
      const dec = $('#mh-decode'), decOut = $('#mh-decoded'), pg = $('#mh-page');
      const runDec = () => dec.value.trim() ? ask(dec.value, decOut, pg.value, 'd') : (decOut.hidden = true);
      dec.addEventListener('input', runDec); pg.addEventListener('change', runDec);

      // конструктор
      const tagSel = $('#mh-tag'), fields = $('#mh-fields'), built = $('#mh-built'), builtOut = $('#mh-built-out');
      function renderFields() {
        const p = SPEC[tagSel.value].params; fields.innerHTML = '';
        Object.keys(p).forEach(k => {
          const f = p[k], t = f.type || 'bool'; let inp;
          if (t === 'bool') inp = '<select data-k="' + k + '" data-def="да"><option value="да">да</option><option value="нет">нет</option></select>';
          else if (t === 'select') inp = '<select data-k="' + k + '">' + Object.keys(f.options).map(v => '<option value="' + esc(v) + '">' + esc(f.options[v]) + '</option>').join('') + '</select>';
          else inp = '<input type="' + (t === 'number' ? 'number' : 'text') + '" data-k="' + k + '" placeholder="' + esc(f.placeholder || '') + '">';
          fields.insertAdjacentHTML('beforeend', '<label><span>' + esc(f.label) + '</span>' + inp + '</label>');
        });
        build();
      }
      function build() {
        let c = '[' + tagSel.value;
        fields.querySelectorAll('[data-k]').forEach(el => {
          const v = el.value.trim();
          if (v === '' || (el.dataset.def && v === el.dataset.def)) return;
          c += ' ' + el.dataset.k + '="' + v.replace(/"/g, '«') + '"';
        });
        c += ']'; built.textContent = c; $('#mh-copy').dataset.copy = c;
        ask(c, builtOut, pg.value, 'b');
      }
      tagSel.addEventListener('change', renderFields);
      fields.addEventListener('input', build); fields.addEventListener('change', build);
      document.addEventListener('click', e => {
        const b = e.target.closest('.mh-build'); if (!b) return;
        tagSel.value = b.dataset.tag; renderFields(); $('#mh-build-box').scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      renderFields();

      // копирование
      document.addEventListener('click', e => {
        const b = e.target.closest('.mem-copy'); if (!b || !b.dataset.copy) return;
        const done = () => { const t = b.textContent; b.textContent = 'Скопировано ✓'; setTimeout(() => b.textContent = t, 1200); };
        if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done);
        // пример сразу показываем в «Что значит»
        if (b.classList.contains('mh-chip')) { dec.value = b.dataset.copy; runDec(); }
      });

      // поиск
      $('#mh-filter').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.mh-card').forEach(c => c.classList.toggle('mh-hide', q && !c.dataset.search.includes(q)));
        document.querySelectorAll('.mh-cards').forEach(g => { const any = g.querySelector('.mh-card:not(.mh-hide)'); g.classList.toggle('mh-hide', !any); g.previousElementSibling.classList.toggle('mh-hide', !any); });
      });
    })();
    </script>
    <?php
}
