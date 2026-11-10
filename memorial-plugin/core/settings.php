<?php
/**
 * Настройки сайта в админке: «Мемориал → Контакты» и «Мемориал → Главная страница».
 *
 * Каждая страница описана схемой полей (memorial_plugin_contacts_schema / memorial_plugin_home_schema).
 * По схеме строится форма, проверяются данные при сохранении и подставляются значения по умолчанию.
 * Чтобы добавить поле — допишите его в схему и в defaults, больше ничего не нужно.
 */
defined('ABSPATH') || exit;

define('MEMORIAL_PLUGIN_MAX_URL', 'https://max.ru/u/f9LHodD0cOLf3sDsVZFTsBTdONk3kEAboq9CfefPcekCM_KZnnErTEhejHM');

/* Разовое обновление 1.5: MAX вместо Telegram в уже сохранённых контактах */
add_action('init', function () {
    if (get_option('mem_ver_15')) return;
    $c = get_option('mem_contacts');
    if (is_array($c) && isset($c['messengers'])) {
        if (empty($c['messengers']['max'])) $c['messengers']['max'] = MEMORIAL_PLUGIN_MAX_URL;
        if (trim($c['messengers']['telegram'] ?? '') === '@memorialbel') $c['messengers']['telegram'] = '';
        update_option('mem_contacts', $c);
    }
    update_option('mem_ver_15', 1, false);
});


/* =========================================================
 * Схемы
 * ======================================================= */

function memorial_plugin_contacts_schema() {
    return [
        'phones' => [
            'type' => 'repeater', 'label' => 'Телефоны', 'add' => 'Добавить телефон',
            'help' => 'Первый номер — основной: он в шапке, на кнопках «Позвонить» и «Заказать».',
            'fields' => [
                'num'  => ['type' => 'text', 'label' => 'Номер', 'placeholder' => '+7 (900) 000-00-00'],
                'note' => ['type' => 'text', 'label' => 'Подпись (необязательно)', 'placeholder' => 'Например: круглосуточно'],
            ],
        ],
        'emails' => [
            'type' => 'repeater', 'label' => 'Электронная почта', 'add' => 'Добавить почту',
            'fields' => [
                'email' => ['type' => 'email', 'label' => 'E-mail', 'placeholder' => 'name@mail.ru'],
            ],
        ],
        'addrs' => [
            'type' => 'repeater', 'label' => 'Адреса и время работы', 'add' => 'Добавить адрес', 'collapse' => 'addr', 'open' => true,
            'help' => 'Первый адрес показывается в верхней строке сайта. Для каждого адреса на главной появится вкладка с картой.',
            'fields' => [
                'addr'  => ['type' => 'text', 'label' => 'Адрес', 'placeholder' => '308000, г. Белгород, ул. Студенческая, 40'],
                'hours' => ['type' => 'text', 'label' => 'Время работы', 'placeholder' => 'Ежедневно с 9:00 до 18:00'],
                'title' => ['type' => 'text', 'label' => 'Надпись на вкладке карты (необязательно)', 'placeholder' => 'По умолчанию — улица и дом'],
                'map'   => ['type' => 'map', 'label' => 'Карта (необязательно)',
                            'help' => 'Оставьте пустым — карта сама найдёт адрес. Чтобы поставить точную метку, вставьте ссылку из Яндекс.Карт или код из «Конструктора карт».'],
            ],
        ],
        'night' => ['type' => 'text', 'label' => 'Звонки в нерабочее время', 'placeholder' => 'Звонки принимаем круглосуточно',
            'help' => 'Показывается в верхней строке, на первом экране, в «Контактах» и в подвале. Пусто — не показывать.'],
        'messengers' => ['type' => 'group', 'label' => 'Мессенджеры',
            'help' => 'Показываются в окне обратного звонка и в блоке «Контакты». Пустое поле — кнопки нет.', 'fields' => [
            'max'      => ['type' => 'text', 'label' => 'MAX — ссылка на профиль', 'placeholder' => 'https://max.ru/u/…',
                           'help' => 'В приложении MAX: Профиль → Поделиться профилем → Скопировать ссылку.'],
            'telegram' => ['type' => 'text', 'label' => 'Telegram', 'placeholder' => '@memorialbel или https://t.me/…'],
            'whatsapp' => ['type' => 'text', 'label' => 'WhatsApp — номер', 'placeholder' => '+7 900 000-00-00'],
        ]],
        'call' => ['type' => 'group', 'label' => 'Кнопка «Позвонить» на компьютере',
            'help' => 'На телефоне кнопка просто звонит. На компьютере сначала открывается MAX по ссылке выше; окно «Перезвоните мне» появляется, если MAX не открылся (браузер заблокировал вкладку или посетитель быстро вернулся на сайт). Заявки приходят на почту и сохраняются в «Мемориал → Заявки».', 'fields' => [
            'title'   => ['type' => 'text', 'label' => 'Заголовок окна'],
            'note'    => ['type' => 'text', 'label' => 'Текст под заголовком'],
            'email'   => ['type' => 'email', 'label' => 'Куда присылать заявки', 'placeholder' => 'Пусто — на первую почту из списка выше'],
            'max_app' => ['type' => 'text', 'label' => 'Ссылка для запуска приложения MAX (для опытных)', 'placeholder' => 'например max://…',
                          'help' => 'Обычно оставляйте пустым — используется ссылка на профиль MAX. Если знаете ссылку, которая сразу запускает установленное приложение, впишите её.'],
        ]],
        'legal' => ['type' => 'group', 'label' => 'Реквизиты', 'help' => 'Показываются на странице «Контакты» и в подвале. Пустые поля не выводятся.', 'fields' => [
            'name' => ['type' => 'text', 'label' => 'Название', 'placeholder' => 'Например: ИП Иванов Иван Иванович'],
            'inn'  => ['type' => 'text', 'label' => 'ИНН'],
            'ogrn' => ['type' => 'text', 'label' => 'ОГРН / ОГРНИП'],
        ]],
        'founded' => ['type' => 'number', 'label' => 'Год основания', 'help' => 'Из него считается «более N лет» на сайте.'],
    ];
}

function memorial_plugin_home_schema() {
    $show = ['type' => 'checkbox', 'label' => 'Показывать этот блок'];
    $eyebrow = ['type' => 'text', 'label' => 'Надзаголовок (мелкие буквы над заголовком)'];
    return [
        'layout' => ['type' => 'order', 'label' => 'Порядок блоков на главной',
            'help' => 'Перетащите строку (или карточку блока ниже за ⋮⋮) мышью или пальцем либо нажмите ↑ ↓, затем «Сохранить порядок». Первый экран всегда наверху. Блоки с пометкой «скрыт» на сайте не видны — включите «Показывать этот блок» в их настройках ниже.',
            'options' => memorial_plugin_home_blocks()],
        'hero' => ['type' => 'group', 'label' => 'Первый экран', 'fields' => [
            'eyebrow'  => $eyebrow,
            'title'    => ['type' => 'text', 'label' => 'Заголовок — первая строка'],
            'title_em' => ['type' => 'text', 'label' => 'Заголовок — вторая строка (курсивом, цветом акцента)'],
            'lead'     => ['type' => 'textarea', 'label' => 'Текст под заголовком', 'rows' => 3],
            'trust'    => ['type' => 'repeater', 'label' => 'Преимущества (с лавровыми листками)', 'add' => 'Добавить пункт', 'fields' => [
                'text' => ['type' => 'text', 'label' => 'Текст'],
            ]],
            'help_page' => ['type' => 'page', 'label' => 'Ссылка «что делать» под кнопками',
                            'help' => 'Ссылка под кнопками «Позвонить» и «Перезвоните мне»: человек сразу видит, куда нажать, если только что умер близкий. Пусто — страница «Что делать, если умер человек» (находится сама).'],
            'help_text' => ['type' => 'text', 'label' => 'Текст ссылки «что делать»', 'placeholder' => 'Что делать, если умер человек'],
            'help_off'  => ['type' => 'checkbox', 'label' => 'Не показывать ссылку «что делать»'],
            'img'      => ['type' => 'image', 'label' => 'Фото справа (траурный зал — светлое, дневной свет)', 'help' => 'Показывается в рамке со скруглением рядом с текстом, без затемнения.'],
            'tint'     => ['type' => 'select', 'label' => 'Тонировка фото (в светлой теме 4.0 не используется)', 'options' => [
                              'grad' => 'Плавная: сверху светлее, внизу темнее', 'flat' => 'Ровная по всему фото', 'off' => 'Выключить — фото без тонировки']],
            'tint_color' => ['type' => 'color', 'label' => 'Цвет тонировки (в светлой теме 4.0 не используется)', 'scheme' => 'dark', 'effective' => true],
            'mobile'   => ['type' => 'select', 'label' => 'На телефоне', 'options' => [
                              'bg'  => 'Сначала текст и кнопка звонка, под ними фото (рекомендуется)',
                              'top' => 'Сначала фото невысокой полосой, под ним текст']],
            'tint_op'  => ['type' => 'number', 'label' => 'Сила тонировки, % (в светлой теме 4.0 не используется)', 'placeholder' => '75'],
        ]],
        'services' => ['type' => 'group', 'label' => 'Блок «Наши услуги»', 'help' => 'Карточки со ссылками на страницы услуг. Фото необязательно: если не выбрано, берётся изображение записи у страницы.', 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => 'Заголовок'],
            'items'   => ['type' => 'repeater', 'label' => 'Услуги', 'add' => 'Добавить услугу', 'collapse' => 'title', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Название'],
                'text'  => ['type' => 'textarea', 'label' => 'Короткое описание', 'rows' => 2],
                'page'  => ['type' => 'page', 'label' => 'Страница'],
                'price' => ['type' => 'text', 'label' => 'Цена (необязательно)', 'placeholder' => 'например: от 58 000 ₽'],
                'img'   => ['type' => 'image', 'label' => 'Фото (необязательно)'],
            ]],
        ]],
        'feats' => ['type' => 'group', 'label' => 'Блок «Почему нам доверяют»', 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => 'Заголовок'],
            'items'   => ['type' => 'repeater', 'label' => 'Пункты', 'add' => 'Добавить пункт', 'collapse' => 'title', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Заголовок'],
                'text'  => ['type' => 'textarea', 'label' => 'Текст', 'rows' => 3],
                'icon'  => ['type' => 'select', 'label' => 'Значок', 'options' => ['auto' => 'Подобрать по заголовку'] + memorial_plugin_feat_icons()],
                'img'   => ['type' => 'image', 'label' => 'Фото вместо значка (необязательно)'],
            ]],
        ]],
        'catalog' => ['type' => 'group', 'label' => 'Блок «Каталог»', 'help' => 'Плитки — это подпункты пункта «Ритуальные товары» в меню (Внешний вид → Меню): названия и порядок берутся оттуда. Фото плитки — «Изображение записи» страницы, на которую ведёт пункт; если его нет — фото первого товара этой категории.', 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => 'Заголовок'],
        ]],
        'prices' => ['type' => 'group', 'label' => 'Блок «Пакеты услуг»', 'help' => 'Сами пакеты, их состав и цены — в «Мемориал → Цены».', 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => 'Заголовок'],
        ]],
        'stats' => ['type' => 'group', 'label' => 'Блок с цифрами', 'fields' => [
            'show'  => $show,
            'items' => ['type' => 'repeater', 'label' => 'Цифры', 'add' => 'Добавить цифру', 'inline' => true, 'fields' => [
                'num'   => ['type' => 'number', 'label' => 'Число'],
                'label' => ['type' => 'text', 'label' => 'Подпись'],
            ]],
        ]],
        'about' => ['type' => 'group', 'label' => 'Блок «О компании»', 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => 'Заголовок'],
            'text'    => ['type' => 'textarea', 'label' => 'Текст', 'rows' => 6, 'html' => true,
                          'help' => 'Пустая строка — новый абзац. Можно использовать <b>, <a> и другую простую разметку.'],
        ]],
        'reviews' => ['type' => 'group', 'label' => 'Блок «Отзывы» (с Яндекс Карт)', 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => 'Заголовок'],
            'text'    => ['type' => 'textarea', 'label' => 'Текст рядом с отзывами', 'rows' => 3],
            'org'     => ['type' => 'text', 'label' => 'Организация на Яндекс Картах — номер или ссылка',
                          'help' => 'Например 75717068753 или ссылка вида https://yandex.ru/maps/org/…/75717068753/'],
        ]],
        'contacts' => ['type' => 'group', 'label' => 'Блок «Контакты»', 'help' => 'Телефоны, почта и адреса берутся со страницы «Мемориал → Контакты».', 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => 'Заголовок'],
        ]],
    ];
}

/* =========================================================
 * Значения по умолчанию (то, что было на сайте до появления настроек)
 * ======================================================= */

function memorial_plugin_contacts_defaults() {
    // Если раньше контакты меняли в «Настроить», берём их оттуда
    $m = fn($k, $d) => get_theme_mod('mem_' . $k, $d);
    $addrs = [];
    foreach ([1 => '308000, г. Белгород, ул. Студенческая, 40', 2 => '308024, г. Белгород, ул. Щорса, 21'] as $n => $addr) {
        $a = $m('addr' . $n, $addr);
        if (!$a) continue;
        $map = $m('map' . $n, '');
        $addrs[] = [
            'addr'  => $a,
            'hours' => $m('hours', 'Ежедневно с 9:00 до 18:00'),
            'title' => '',
            'map'   => strpos($map, 'text=') !== false ? '' : $map, // карту по тексту адреса строим сами
        ];
    }
    return [
        'phones'  => [
            ['num' => $m('phone', '+7 (905) 675-22-24'), 'note' => ''],
            ['num' => '+7 (4722) 37-16-58', 'note' => ''],
            ['num' => '+7 (910) 737-16-58', 'note' => ''],
        ],
        'legal'   => ['name' => '', 'inn' => '312311885837', 'ogrn' => '306312324200048'],
        'emails'  => [['email' => $m('email', 'memorialbel@mail.ru')]],
        'addrs'   => $addrs,
        'night'   => 'Звонки принимаем круглосуточно',
        'messengers' => ['max' => MEMORIAL_PLUGIN_MAX_URL, 'telegram' => '', 'whatsapp' => ''],
        'call'       => ['title' => 'Перезвоним вам', 'note' => 'Оставьте номер — перезвоним в ближайшее время.', 'email' => '', 'max_app' => ''],
        'founded' => (int) $m('founded', 2003),
    ];
}

/** Блоки главной, которые можно переставлять */
function memorial_plugin_home_blocks() {
    return ['services' => 'Наши услуги', 'feats' => 'Почему нам доверяют', 'catalog' => 'Каталог', 'prices' => 'Пакеты услуг',
            'stats' => 'Блок с цифрами', 'about' => 'О компании', 'reviews' => 'Отзывы', 'contacts' => 'Контакты'];
}

/* Тексты главной по умолчанию — спокойные и конкретные: что делать и чем поможем, без «качественных услуг» и «не фирма-однодневка» */
const MEMORIAL_PLUGIN_HERO_LEAD = 'Если умер близкий человек — позвоните, подскажем, что делать. Прямо сейчас ничего решать не нужно.';
const MEMORIAL_PLUGIN_HERO_TRUST = [['text' => 'Звонки круглосуточно'], ['text' => 'Свой траурный зал — бесплатно при заказе похорон'], ['text' => 'Цены и состав пакетов — на сайте']];
const MEMORIAL_PLUGIN_ABOUT_TEXT = "Мы работаем в Белгороде с {founded} года. Приедем в любое время, поможем с документами, проведём прощание в своём траурном зале.\n\nПодскажем по шагам, что нужно сделать, — выяснять самим ничего не придётся. Венки делаем сами, договор и документы для выплат оформляем официально.";

/** Вторая кнопка первого экрана: [адрес, надпись] или null */
function memorial_plugin_home_help_link() {
    $h = memorial_plugin_home('hero');
    if (!empty($h['help_off'])) return null;
    $id = (int) ($h['help_page'] ?? 0);
    if (!$id || get_post_status($id) !== 'publish') {
        $id = 0;
        foreach (get_pages(['post_status' => 'publish']) as $p) {
            if (preg_match('~что делать.*умер~ui', $p->post_title) || strpos($p->post_name, 'chto-delat') === 0) { $id = $p->ID; break; }
        }
    }
    if (!$id) return null;
    return [get_permalink($id), trim($h['help_text'] ?? '') ?: 'Что делать, если умер человек'];
}

/* Разово: старые «продающие» тексты главной, если их не меняли, заменяем на новые (прежние — в истории не нужны, они из шаблона) */
add_action('admin_init', function () {
    if (get_option('mem_home_texts_v2') || !current_user_can('manage_options')) return;
    update_option('mem_home_texts_v2', 1, false);
    $o = get_option('mem_home');
    if (!is_array($o)) return;
    $ch = [];
    $old_em = ['Свой траурный зал, венки собственного производства', 'для прощания и панихиды'];
    if (in_array(trim($o['hero']['title_em'] ?? ''), $old_em, true)) { $o['hero']['title_em'] = 'Поможем на каждом шаге'; $ch[] = 'вторая строка заголовка'; }
    if (trim($o['hero']['title'] ?? '') === 'Собственный траурный зал') { $o['hero']['title'] = 'Ритуальные услуги в Белгороде'; $ch[] = 'заголовок'; }
    if (trim($o['hero']['lead'] ?? '') === 'Возьмём на себя все хлопоты: организация похорон, документы, транспорт, венки собственного производства.') { $o['hero']['lead'] = MEMORIAL_PLUGIN_HERO_LEAD; $ch[] = 'текст под заголовком'; }
    $tr = array_map(fn($t) => trim($t['text'] ?? ''), (array) ($o['hero']['trust'] ?? []));
    if (in_array('Собственное здание — не фирма-однодневка', $tr, true)) { $o['hero']['trust'] = MEMORIAL_PLUGIN_HERO_TRUST; $ch[] = 'пункты с галочками'; }
    if (strpos((string) ($o['about']['text'] ?? ''), 'предлагает качественные ритуальные услуги') !== false) { $o['about']['text'] = MEMORIAL_PLUGIN_ABOUT_TEXT; $ch[] = 'текст «О компании»'; }
    if (!$ch) return;
    update_option('mem_home', $o);
    set_transient('mem_places_report', array_merge(array_filter((array) get_transient('mem_places_report')), [
        'Главная: обновлены ' . implode(', ', $ch) . ' — спокойнее и конкретнее. Поменять можно в «Мемориал → Главная страница».']), DAY_IN_SECONDS);
});

function memorial_plugin_home_defaults() {
    $p = fn(...$rows) => array_map(fn($r) => ['name' => $r[0], 'price' => $r[1], 'key' => preg_match('/^(Гроб|Крест|Катафалк|Венок|Услуги по захоронению)/u', $r[0]) ? 1 : 0], $rows);
    return [
        'layout' => array_keys(memorial_plugin_home_blocks()),
        'hero' => [
            'eyebrow'  => 'Ритуальная компания в Белгороде · с {founded} года',
            'title'    => 'Ритуальные услуги в Белгороде',
            'title_em' => 'Поможем на каждом шаге',
            'lead'     => MEMORIAL_PLUGIN_HERO_LEAD,
            'trust'    => MEMORIAL_PLUGIN_HERO_TRUST,
            'help_page' => '', 'help_text' => 'Что делать, если умер человек', 'help_off' => 0,
            'img'      => get_theme_mod('mem_hero_img', ''),
            'tint'     => 'grad', 'tint_color' => '', 'tint_op' => 75, 'mobile' => 'bg',
        ],
        'services' => [
            'show' => 1, 'eyebrow' => 'Чем мы поможем', 'title' => 'Наши услуги',
            'items' => [
                ['title' => 'Организация похорон', 'text' => 'Возьмём на себя документы, морг, транспорт и проведение похорон.', 'page' => memorial_plugin_guess_page(['organizaciya-pohoron', 'organizacziya-pohoron', 'pomoshh-v-organizacii-pohoron']), 'price' => '', 'img' => ''],
                ['title' => 'Кремация', 'text' => 'Доставим в крематорий Тулы или Воронежа, выдадим прах в урне в день кремирования.', 'page' => memorial_plugin_guess_page(['kremacziya', 'kremaciya']), 'price' => 'от 58 000 ₽', 'img' => ''],
                ['title' => 'Траурный зал', 'text' => 'Собственный зал для прощания и отпевания на ул. Студенческой, 40.', 'page' => memorial_plugin_guess_page(['traurnyj-zal']), 'price' => '', 'img' => ''],
                ['title' => 'Груз 200', 'text' => 'Перевозка умерших в другие города и регионы.', 'page' => memorial_plugin_guess_page(['gruz-200', 'perevozka-umershih', 'perevozka-umershego']), 'price' => '', 'img' => ''],
                ['title' => 'Благоустройство захоронений', 'text' => 'Памятники, оградки, укладка плитки — под ключ.', 'page' => memorial_plugin_guess_page(['blagoustrojstvo-zahoroneniya', 'blagoustrojstvo']), 'price' => '', 'img' => ''],
                ['title' => 'Ритуальные товары', 'text' => 'Венки собственного производства, гробы, кресты.', 'page' => memorial_plugin_guess_page(['ritualnye-tovary']), 'price' => '', 'img' => ''],
            ],
        ],
        'feats' => [
            'show' => 1, 'eyebrow' => 'Почему нам доверяют', 'title' => 'Своё, а не через посредников',
            'items' => [
                ['title' => 'Собственный траурный зал', 'text' => 'Предоставляем зал для прощания и панихиды бесплатно — в спокойной и достойной обстановке.', 'icon' => 'candle', 'img' => ''],
                ['title' => 'Собственное производство венков', 'text' => 'Изготавливаем венки и композиции сами: любой размер и цвет, качество под контролем, без наценок посредников.', 'icon' => 'flower', 'img' => ''],
                ['title' => 'Собственное здание', 'text' => 'Более {years} лет работаем в своём здании, а не во временном офисе. Мы не фирма-однодневка.', 'icon' => 'building', 'img' => ''],
                ['title' => 'Документы для компенсации', 'text' => 'Заключаем официальный договор и выдаём полный пакет документов для получения компенсации через военкомат.', 'icon' => 'doc', 'img' => ''],
            ],
        ],
        'catalog' => ['show' => 1, 'eyebrow' => 'Каталог', 'title' => 'Ритуальные товары'],
        'prices' => [
            'show' => 1, 'eyebrow' => 'Стоимость', 'title' => 'Пакеты ритуальных услуг',
            'badge' => 'Чаще выбирают', 'btn' => 'Заказать',
            'items' => [
                ['name' => 'Эконом', 'featured' => 0, 'rows' => $p(
                    ['Гроб атласный', 5000], ['Крест из сосны', 2500], ['Табличка на крест', 700],
                    ['Шёлковый рушник', 350], ['Подушка в гроб', 400], ['Тюль', 450],
                    ['Саван х/б', 450], ['Церковный набор', 250], ['Стропы для опускания гроба', 600],
                    ['Катафалк', 6000], ['Услуги по захоронению', 20000])],
                ['name' => 'Стандарт', 'featured' => 1, 'rows' => $p(
                    ['Гроб бархат', 7500], ['Крест полированный', 4800], ['Табличка на крест', 700],
                    ['Рушник габардин', 450], ['Подушка в гроб', 400], ['Комплект в гроб', 2150],
                    ['Саван атласный', 550], ['Церковный набор', 250], ['Стропы для опускания гроба', 600],
                    ['Катафалк', 6000], ['Венок с лентой', 2500], ['Услуги по захоронению', 22000])],
                ['name' => 'Премиум', 'featured' => 0, 'rows' => $p(
                    ['Гроб лаковый', 30000], ['Крест дубовый', 7000], ['Табличка на крест', 700],
                    ['Рушник габардин', 550], ['Подушка в гроб', 400], ['Комплект в гроб', 2950],
                    ['Саван атласный', 550], ['Церковный набор', 250], ['Стропы для опускания гроба', 600],
                    ['Венок с лентой', 3700], ['Катафалк', 6000], ['Услуги по захоронению', 29000])],
            ],
        ],
        'stats' => ['show' => 1, 'items' => [
            ['num' => 5200, 'label' => 'организованных похорон'],
            ['num' => 2850, 'label' => 'установленных оградок'],
            ['num' => 1250, 'label' => 'благоустроенных захоронений'],
        ]],
        'about' => [
            'show' => 1, 'eyebrow' => 'О компании', 'title' => 'Более {years} лет рядом в трудную минуту',
            'text' => MEMORIAL_PLUGIN_ABOUT_TEXT,
        ],
        'reviews' => [
            'show' => 1, 'eyebrow' => 'Отзывы', 'title' => 'Что о нас пишут',
            'text' => 'Это настоящие отзывы наших клиентов на Яндекс Картах — мы не можем их редактировать или удалять. Будем благодарны, если вы тоже поделитесь впечатлением.',
            'org'  => '75717068753',
        ],
        'contacts' => ['show' => 1, 'eyebrow' => 'Контакты', 'title' => 'Мы на связи'],
    ];
}

/* =========================================================
 * Чтение настроек
 * ======================================================= */

/** Сохранённые значения поверх значений по умолчанию. Списки берутся целиком — чтобы удалённый пункт не возвращался. */
function memorial_plugin_merge($def, $saved) {
    if (!is_array($saved)) return $def;
    foreach ($def as $k => $v) {
        if (!array_key_exists($k, $saved)) continue;
        $def[$k] = (is_array($v) && $v && !array_is_list($v) && is_array($saved[$k]))
            ? memorial_plugin_merge($v, $saved[$k])
            : $saved[$k];
    }
    return $def;
}

function memorial_plugin_settings($name) {
    static $cache = [];
    if (!isset($cache[$name])) {
        // Значения по умолчанию: memorial_plugin_<имя без mem_>_defaults; тема подставляет свою функцию фильтром
        // memorial_plugin_settings_defaults. Имена настроек в базе (mem_…) прежние — переименованы только функции.
        // Список страниц (memorial_plugin_admin_pages) здесь спрашивать нельзя: его обработчики сами читают настройки.
        $fn  = apply_filters('memorial_plugin_settings_defaults', 'memorial_plugin_' . preg_replace('/^mem_/', '', $name) . '_defaults', $name);
        $def = is_callable($fn) ? call_user_func($fn) : [];
        $cache[$name] = memorial_plugin_merge($def, get_option($name, null));
    }
    return $cache[$name];
}

function memorial_plugin_contacts() { return memorial_plugin_settings('mem_contacts'); }
function memorial_plugin_night() { return trim((string) (memorial_plugin_contacts()['night'] ?? '')); }

/** id первой найденной страницы по адресам-кандидатам (для настроек по умолчанию) */
function memorial_plugin_guess_page(array $paths) {
    foreach ($paths as $path) {
        $p = get_page_by_path($path);
        if (!$p) {
            $ids = get_posts(['post_type' => 'page', 'name' => basename($path), 'posts_per_page' => 1, 'fields' => 'ids', 'post_status' => ['publish', 'draft']]);
            $p = $ids ? get_post($ids[0]) : null;
        }
        if ($p) return $p->ID;
    }
    return '';
}
function memorial_plugin_home($section) { return memorial_plugin_settings('mem_home')[$section] ?? []; }

function memorial_plugin_phones() {
    return array_values(array_filter(memorial_plugin_contacts()['phones'], fn($p) => trim($p['num'] ?? '') !== ''));
}
function memorial_plugin_emails() {
    return array_values(array_filter(wp_list_pluck(memorial_plugin_contacts()['emails'], 'email')));
}
/** Номер организации на Яндекс Картах */
function memorial_plugin_yandex_org() {
    $raw = (string) (memorial_plugin_home('reviews')['org'] ?? '');
    return preg_match('~(\d{6,})/?\s*$~', trim($raw), $m) || preg_match('~/(\d{6,})(?:/|$|\?)~', $raw, $m) ? $m[1] : '';
}

/** Реквизиты строкой: «ИП … · ИНН … · ОГРНИП …» */
function memorial_plugin_legal_parts() {
    $l = memorial_plugin_contacts()['legal'] ?? [];
    $out = [];
    if (trim($l['name'] ?? '')) $out[] = trim($l['name']);
    if ($inn = preg_replace('/\D/', '', $l['inn'] ?? '')) $out[] = 'ИНН ' . $inn;
    if ($ogrn = preg_replace('/\D/', '', $l['ogrn'] ?? '')) $out[] = (strlen($ogrn) === 15 ? 'ОГРНИП ' : 'ОГРН ') . $ogrn;
    return $out;
}

/** Ссылки мессенджеров: ['max' => url, 'telegram' => url, 'whatsapp' => url] (только заполненные) */
function memorial_plugin_messengers() {
    $m = memorial_plugin_contacts()['messengers'] ?? [];
    $out = [];
    $max = trim($m['max'] ?? '');
    if ($max) $out['max'] = (strpos($max, 'http') === 0 ? $max : 'https://' . ltrim($max, '/'));
    $tg = trim($m['telegram'] ?? '');
    if ($tg) $out['telegram'] = preg_match('~^https?://~', $tg) ? $tg : 'https://t.me/' . ltrim(preg_replace('~^(t\.me/|telegram\.me/)~', '', $tg), '@');
    $wa = preg_replace('/\D/', '', $m['whatsapp'] ?? '');
    if (strlen($wa) === 11 && $wa[0] === '8') $wa = '7' . substr($wa, 1);
    if (strlen($wa) >= 10) $out['whatsapp'] = 'https://wa.me/' . $wa;
    return array_map('esc_url_raw', $out);
}

function memorial_plugin_addrs() {
    return array_values(array_filter(memorial_plugin_contacts()['addrs'], fn($a) => trim($a['addr'] ?? '') !== ''));
}

/** Номер для ссылки tel: */
function memorial_plugin_tel_of($num) {
    return preg_replace('/[^\d+]/', '', $num);
}

/** Подстановки в тексты: {years}, {founded}, {addr}, {phone} */
function memorial_plugin_t($s) {
    $a = memorial_plugin_addrs();
    return strtr((string) $s, [
        '{years}'   => memorial_plugin_years(),
        '{founded}' => (int) memorial_plugin_contacts()['founded'],
        '{addr}'    => $a ? $a[0]['addr'] : '',
        '{phone}'   => memorial_plugin_opt('phone'),
    ]);
}

/* =========================================================
 * Карты
 * ======================================================= */

/** Из того, что вставили в поле «Карта», достаём ссылку. */
function memorial_plugin_clean_map($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') return '';
    if (preg_match('~src=["\']([^"\']+)~i', $raw, $m)) $raw = html_entity_decode($m[1]);
    if (strpos($raw, '//') === 0) $raw = 'https:' . $raw;
    return esc_url_raw($raw, ['https', 'http']);
}

/** Ссылка для iframe. Любая ссылка Яндекс.Карт приводится к виджету; если не получилось — ищем по адресу. */
function memorial_plugin_map_src($map, $addr) {
    $by_addr = 'https://yandex.ru/map-widget/v1/?text=' . rawurlencode(preg_replace('/^\d{6},\s*/', '', $addr)) . '&z=16';
    if (!$map) return $by_addr;
    $u = wp_parse_url($map);
    $host = $u['host'] ?? '';
    $path = $u['path'] ?? '';
    $query = $u['query'] ?? '';
    if (strpos($host, 'yandex.') !== false) {
        if (strpos($path, '/map-widget/') !== false) return $map;
        // Конструктор карт: api-maps.yandex.ru/services/constructor/1.0/js/?um=...
        if (strpos($host, 'api-maps') !== false && preg_match('/(?:^|&)um=([^&]+)/', $query, $m)) {
            return 'https://yandex.ru/map-widget/v1/?um=' . $m[1] . '&source=constructor';
        }
        // Обычная ссылка yandex.ru/maps/...?ll=...&z=... — те же параметры понимает виджет
        if (strpos($path, '/maps') === 0 && preg_match('/(?:^|&)(ll|pt|oid|text|um|whatshere)/', $query)) {
            return 'https://yandex.ru/map-widget/v1/?' . $query;
        }
        return $by_addr; // короткие ссылки yandex.ru/maps/-/... в iframe не открываются
    }
    if (strpos($host, 'google.') !== false && strpos($path, '/maps/embed') !== false) return $map;
    return $by_addr;
}

/** Короткий адрес для вкладки: «ул. Студенческая, 40» */
function memorial_plugin_short_addr($addr) {
    $s = preg_replace('/^\d{6},\s*/u', '', $addr);
    $s = preg_replace('/^(г\.|город)\s*[^,]+,\s*/u', '', $s);
    return trim($s);
}

/* =========================================================
 * Проверка данных при сохранении
 * ======================================================= */

function memorial_plugin_sanitize($schema, $in) {
    $in = is_array($in) ? $in : [];
    $out = [];
    foreach ($schema as $k => $f) {
        $v = $in[$k] ?? null;
        switch ($f['type']) {
            case 'group':
                $out[$k] = memorial_plugin_sanitize($f['fields'], $v);
                break;
            case 'repeater':
                $rows = [];
                foreach (is_array($v) ? $v : [] as $row) {
                    $r = memorial_plugin_sanitize($f['fields'], $row);
                    if (!memorial_plugin_row_empty($r, $f['fields'])) $rows[] = $r;
                }
                $out[$k] = $rows;
                break;
            case 'checkbox':
                $out[$k] = empty($v) ? 0 : 1;
                break;
            case 'number':
                $v = is_scalar($v) ? preg_replace('/[^\d]/', '', (string) $v) : '';
                $out[$k] = $v === '' ? '' : (int) $v;
                break;
            case 'page':
                $out[$k] = absint($v) ?: '';
                break;
            case 'order':
                $v = array_values(array_intersect(is_array($v) ? array_map('strval', $v) : [], array_keys($f['options'])));
                $out[$k] = array_values(array_unique(array_merge($v, array_keys($f['options']))));
                break;
            case 'color':
                $out[$k] = sanitize_hex_color((string) $v) ?: '';
                break;
            case 'file':
                $out[$k] = esc_url_raw((string) $v);
                break;
            case 'fontsel': // список шрифтов меняется при сохранении (можно добавить свой) — проверяем только формат
                $out[$k] = sanitize_key((string) $v) ?: (string) array_key_first($f['options']);
                break;
            case 'select':
                $out[$k] = is_scalar($v) && array_key_exists((string) $v, $f['options']) ? (string) $v : (string) array_key_first($f['options']);
                break;
            case 'email':
                $out[$k] = sanitize_email((string) $v);
                break;
            case 'image':
                $out[$k] = esc_url_raw((string) $v);
                break;
            case 'map':
                $out[$k] = memorial_plugin_clean_map($v);
                break;
            case 'code': // коды счётчиков: целиком сохраняет только администратор с правом на HTML
                $out[$k] = current_user_can('unfiltered_html') ? trim((string) $v) : '';
                break;
            case 'textarea':
                $out[$k] = !empty($f['html']) ? wp_kses_post((string) $v) : sanitize_textarea_field((string) $v);
                break;
            default:
                $out[$k] = sanitize_text_field((string) $v);
        }
    }
    return $out;
}

/** Пустые строки списков не сохраняем (галочки не в счёт). */
function memorial_plugin_row_empty($r, $fields) {
    foreach ($fields as $k => $f) {
        if ($f['type'] === 'checkbox') continue;
        $v = $r[$k] ?? '';
        if (is_array($v) ? $v : trim((string) $v) !== '') return false;
    }
    return true;
}

/* =========================================================
 * Страницы в админке
 * ======================================================= */

function memorial_plugin_admin_pages() {
    return apply_filters('memorial_plugin_admin_pages', [
        'mem_contacts' => ['slug' => 'mem-contacts', 'title' => 'Контакты', 'schema' => 'memorial_plugin_contacts_schema',
                           'intro' => 'Телефоны, почта, адреса и время работы. Изменения сразу появятся в шапке, подвале, на главной и в карточках товаров.'],
        'mem_home'     => ['slug' => 'mem-home', 'title' => 'Главная страница', 'schema' => 'memorial_plugin_home_schema',
                           'intro' => 'Тексты и пункты блоков главной. Пункты можно перетаскивать за ⋮⋮, удалять крестиком и добавлять кнопкой под списком. Пустые пункты не сохраняются.<br>'
                                    . 'В тексте можно писать <code>{years}</code> — сколько лет компании, <code>{founded}</code> — год основания, <code>{addr}</code> — первый адрес, <code>{phone}</code> — основной телефон.'],
    ]);
}

add_action('admin_menu', function () {
    global $memorial_plugin_admin_hooks;
    $memorial_plugin_admin_hooks = [];
    $pages = memorial_plugin_admin_pages();
    add_menu_page('Настройки сайта', 'Мемориал', 'manage_options', 'mem-contacts', '__return_null', 'dashicons-admin-home', 3);
    foreach ($pages as $opt => $p) {
        // 'hidden' — страница без своего пункта меню (открывается вкладкой другой страницы)
        $hook = add_submenu_page(empty($p['hidden']) ? 'mem-contacts' : '', $p['title'], $p['title'], 'manage_options', $p['slug'],
            fn() => memorial_plugin_admin_render($opt));
        $memorial_plugin_admin_hooks[] = $hook;
        // у страницы без пункта меню WordPress не знает заголовок окна — задаём сами
        if (!empty($p['hidden']) && $hook) add_action('load-' . $hook, function () use ($p) { $GLOBALS['title'] = $p['h1'] ?? $p['title']; });
    }
});

/* Порядок пунктов «Мемориал»: сначала то, чем пользуются каждый день, внизу — разовые настройки */
add_action('admin_menu', function () {
    global $submenu;
    if (empty($submenu['mem-contacts'])) return;
    $order = ['edit.php?post_type=mem_lead', 'mem-contacts', 'mem-prices', 'mem-reviews', 'mem-home', 'mem-rest', 'mem-cem', 'mem-design', 'mem-metrika', 'mem-legal', 'mem-sq', 'mem-modules', 'mem-help-m'];
    $items = $submenu['mem-contacts'];
    usort($items, function ($a, $b) use ($order) {
        $ia = array_search($a[2], $order, true); $ib = array_search($b[2], $order, true);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });
    $submenu['mem-contacts'] = array_values($items);
}, 9999);

/** Вкладки над страницами одного раздела (например, «Отзывы»: сервисы · Rewio · из книги) */
function memorial_plugin_admin_tabs($tabs, $current) {
    echo '<nav class="nav-tab-wrapper mem-tabs" style="margin:6px 0 14px">';
    foreach ($tabs as $key => [$label, $url]) {
        echo '<a class="nav-tab' . ($key === $current ? ' nav-tab-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';
}

add_action('admin_init', function () {
    foreach (memorial_plugin_admin_pages() as $opt => $p) {
        register_setting($opt, $opt, [
            'type'              => 'array',
            'sanitize_callback' => fn($v) => memorial_plugin_sanitize(call_user_func($p['schema']), $v),
            'show_in_rest'      => false,
        ]);
    }
});

add_action('admin_enqueue_scripts', function ($hook) {
    global $memorial_plugin_admin_hooks;
    if (!in_array($hook, (array) $memorial_plugin_admin_hooks, true)) return;
    $dir = MEMORIAL_PLUGIN_DIR;
    $uri = MEMORIAL_PLUGIN_URL;
    wp_enqueue_media();
    wp_enqueue_style('mem-admin', $uri . '/assets/css/admin.css', [], filemtime($dir . '/assets/css/admin.css'));
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('mem-admin', $uri . '/assets/js/admin.js', ['jquery', 'jquery-ui-sortable', 'wp-color-picker'], filemtime($dir . '/assets/js/admin.js'), true);
});

function memorial_plugin_admin_render($opt) {
    $p = memorial_plugin_admin_pages()[$opt];
    $val = memorial_plugin_settings($opt);
    ?>
    <div class="wrap mem-admin">
      <h1><?php echo esc_html($p['h1'] ?? $p['title']); ?></h1>
      <?php do_action('memorial_plugin_admin_tabs', $opt); ?>
      <p class="mem-admin__intro"><?php echo wp_kses_post($p['intro']); ?></p>
      <?php settings_errors(); do_action('memorial_plugin_admin_after_intro', $opt); ?>
      <form method="post" action="options.php">
        <?php settings_fields($opt);
        $schema = call_user_func($p['schema']);
        // «Главная страница»: карточки блоков идут в порядке блоков на сайте, их можно перетаскивать (порядок сохраняется в «layout»)
        $movable = [];
        if ($opt === 'mem_home' && function_exists('memorial_plugin_home_blocks')) {
            $all = array_values(array_intersect(array_keys(memorial_plugin_home_blocks()), array_keys($schema)));
            $movable = array_values(array_unique(array_merge(array_intersect((array) ($val['layout'] ?? []), $all), $all)));
        }
        $card = function ($k, $f, $move) use ($opt, $val) {
            $title = $f['label'];
            if ($f['type'] !== 'group') $f['label'] = '';
            echo '<section class="mem-card' . (!empty($f['class']) ? ' ' . esc_attr($f['class']) : '') . '" data-card="' . esc_attr($opt . '.' . $k) . '"' . ($move ? ' data-block="' . esc_attr($k) . '"' : '') . '>';
            echo '<h2 class="mem-card__head">';
            if ($move) echo '<span class="mem-card__drag" title="Перетащите, чтобы поменять блок местами на главной" aria-hidden="true">⋮⋮</span>';
            echo '<button type="button" class="mem-card__toggle" aria-expanded="true">' . esc_html($title) . '</button>';
            if ($move) echo '<span class="mem-card__mv"><button type="button" class="button-link" data-cmv="-1" aria-label="Блок выше">↑</button><button type="button" class="button-link" data-cmv="1" aria-label="Блок ниже">↓</button></span>';
            echo '</h2><div class="mem-card__body">';
            if (!empty($f['help']) && $f['type'] === 'group') echo '<p class="description">' . esc_html($f['help']) . '</p>';
            // группа со своим видом (например, выбор филиалов кнопками) — рисует себя сама, сохраняется по полям схемы
            if (!empty($f['render'])) call_user_func($f['render'], $opt . '[' . $k . ']', $val[$k] ?? null);
            else memorial_plugin_field($opt . '[' . $k . ']', $f, $val[$k] ?? null, 1, true);
            echo '</div></section>';
        };
        echo '<p class="mem-cards__tools"><button type="button" class="button" data-cards="collapse">Свернуть все</button> <button type="button" class="button" data-cards="expand">Развернуть все</button></p>';
        $printed = false;
        foreach ($schema as $k => $f) {
            if (in_array($k, $movable, true)) {
                if (!$printed) {
                    echo '<div class="mem-blocks">';
                    foreach ($movable as $mk) $card($mk, $schema[$mk], true);
                    echo '</div>';
                    $printed = true;
                }
                continue;
            }
            $card($k, $f, false);
        }
        ?>
        <div class="mem-admin__bar">
          <?php submit_button('Сохранить', 'primary large', 'submit', false); ?>
          <a class="button button-large" href="<?php echo esc_url(home_url('/')); ?>" target="_blank">Открыть сайт ↗</a>
        </div>
      </form>
    </div>
    <?php
}

/** Одно поле формы по схеме. */
function memorial_plugin_field($name, $f, $v, $depth = 1, $top = false) {
    $id    = 'f' . substr(md5($name), 0, 10);
    $label = $f['label'] ?? '';
    $help  = !empty($f['help']) && $f['type'] !== 'group' ? '<span class="description">' . esc_html($f['help']) . '</span>' : '';
    $ph    = !empty($f['placeholder']) ? ' placeholder="' . esc_attr($f['placeholder']) . '"' : '';

    switch ($f['type']) {
        case 'group':
            foreach ($f['fields'] as $k => $sub) memorial_plugin_field($name . '[' . $k . ']', $sub, $v[$k] ?? null, $depth);
            return;

        case 'repeater':
            $key = '__i' . $depth . '__';
            $cls = 'rep' . (!empty($f['inline']) ? ' rep--inline' : '');
            echo '<div class="fld fld--rep">';
            if ($label) echo '<span class="fld__label">' . esc_html($label) . '</span>';
            if ($help) echo $help;
            echo '<div class="' . esc_attr($cls) . '" data-key="' . esc_attr($key) . '">';
            echo '<div class="rep__rows">';
            foreach (is_array($v) ? array_values($v) : [] as $i => $row) memorial_plugin_rep_row($name, $f, $i, $row, $depth);
            echo '</div>';
            echo '<template>';
            memorial_plugin_rep_row($name, $f, $key, [], $depth, true);
            echo '</template>';
            echo '<button type="button" class="button rep__add">+ ' . esc_html($f['add'] ?? 'Добавить') . '</button>';
            echo '</div></div>';
            return;

        case 'page':
            echo '<label class="fld" for="' . $id . '"><span class="fld__label">' . esc_html($label) . '</span>'
               . wp_dropdown_pages(['name' => $name, 'id' => $id, 'selected' => (int) $v, 'echo' => 0,
                                    'show_option_none' => '— не выбрана —', 'option_none_value' => '', 'post_status' => ['publish', 'draft']])
               . $help . '</label>';
            return;

        case 'order':
            $v = is_array($v) ? $v : [];
            $keys = array_values(array_unique(array_merge(array_intersect($v, array_keys($f['options'])), array_keys($f['options']))));
            if ($help) echo '<p class="description">' . wp_strip_all_tags($help) . '</p>';
            // какие блоки сейчас скрыты (галочка «Показывать этот блок» снята) — чтобы было понятно, почему перестановка их не видна
            $home = strpos($name, 'mem_home') === 0 && function_exists('memorial_plugin_settings') ? memorial_plugin_settings('mem_home') : [];
            echo '<ol class="mem-order" aria-label="' . esc_attr($label ?: 'Порядок') . '">';
            foreach ($keys as $key) {
                $off = $home && isset($home[$key]) && is_array($home[$key]) && array_key_exists('show', $home[$key]) && empty($home[$key]['show']);
                echo '<li' . ($off ? ' class="is-off"' : '') . '><span class="rep__handle" aria-hidden="true">⋮⋮</span><input type="hidden" name="' . esc_attr($name) . '[]" value="' . esc_attr($key) . '">'
                   . '<span class="mem-order__name">' . esc_html($f['options'][$key]) . ($off ? ' <small class="mem-order__off">скрыт</small>' : '') . '</span>'
                   . '<span class="mem-order__btns"><button type="button" class="button-link" data-mv="-1" aria-label="Выше">↑</button><button type="button" class="button-link" data-mv="1" aria-label="Ниже">↓</button></span></li>';
            }
            echo '</ol>';
            echo '<p class="mem-order__save"><button type="submit" class="button button-primary">Сохранить порядок</button> '
               . '<span class="mem-order__note" hidden>Порядок изменён — сохраните, чтобы он появился на сайте.</span></p>';
            return;

        case 'color':
            $sc = !empty($f['scheme']) ? (string) apply_filters('memorial_plugin_scheme_color', '', $f['scheme'], !empty($f['effective'])) : '';
            echo '<div class="fld fld--color"><span class="fld__label">' . esc_html($label) . '</span>'
               . '<span class="mem-color__row"><input type="text" class="mem-color" name="' . esc_attr($name) . '" value="' . esc_attr((string) $v) . '"'
               . ($sc ? ' data-scheme-color="' . esc_attr($sc) . '"' . (empty($f['effective']) ? ' data-scheme="' . esc_attr($f['scheme']) . '"' : '') : '') . '>'
               . ($sc ? '<span class="mem-color__from">из схемы</span>' : '') . '</span>' . $help . '</div>';
            return;

        case 'file':
            echo '<div class="fld fld--file"><span class="fld__label">' . esc_html($label) . '</span><span class="file-pick">'
               . '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $v) . '" placeholder="Ссылка на файл .woff2 / .woff / .ttf">'
               . ' <button type="button" class="button file-pick__choose">Загрузить / выбрать</button></span>' . $help . '</div>';
            return;

        case 'fontsel':
        case 'select':
            echo '<label class="fld" for="' . $id . '"><span class="fld__label">' . esc_html($label) . '</span><select id="' . $id . '" name="' . esc_attr($name) . '"'
               . (!empty($f['data']) ? ' data-presets="' . esc_attr(wp_json_encode($f['data'])) . '"' : '') . '>';
            foreach ($f['options'] as $ov => $ol) echo '<option value="' . esc_attr($ov) . '"' . selected((string) $v, (string) $ov, false) . '>' . esc_html($ol) . '</option>';
            echo '</select>' . $help . '</label>';
            return;

        case 'checkbox':
            echo '<label class="fld fld--check"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked(!empty($v), true, false) . '> ' . esc_html($label) . '</label>';
            return;

        case 'textarea':
        case 'code':
        case 'map':
            $rows = $f['rows'] ?? ($f['type'] === 'map' ? 2 : 3);
            echo '<label class="fld" for="' . $id . '"><span class="fld__label">' . esc_html($label) . '</span>'
               . '<textarea id="' . $id . '" name="' . esc_attr($name) . '" rows="' . (int) $rows . '"' . $ph . ($f['type'] === 'code' ? ' class="code" spellcheck="false"' : '') . '>' . esc_textarea((string) $v) . '</textarea>' . $help . '</label>';
            return;

        case 'image':
            echo '<div class="fld fld--img"><span class="fld__label">' . esc_html($label) . '</span>'
               . '<div class="img-pick"><span class="img-pick__prev"' . ($v ? ' style="background-image:url(' . esc_url($v) . ')"' : '') . '></span>'
               . '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) $v) . '">'
               . '<button type="button" class="button img-pick__choose">Выбрать фото</button> '
               . '<button type="button" class="button-link img-pick__clear"' . ($v ? '' : ' hidden') . '>Убрать</button></div>' . $help . '</div>';
            return;

        default:
            $type = ['number' => 'number', 'email' => 'email'][$f['type']] ?? 'text';
            $extra = $type === 'number' ? ' min="0" step="1" inputmode="numeric"' : '';
            echo '<label class="fld fld--' . $type . '" for="' . $id . '"><span class="fld__label">' . esc_html($label) . '</span>'
               . '<input id="' . $id . '" type="' . $type . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $v) . '"' . $ph . $extra . '>' . $help . '</label>';
    }
}

function memorial_plugin_rep_row($name, $f, $i, $row, $depth, $tpl = false) {
    $base = $name . '[' . $i . ']';
    $collapse = $f['collapse'] ?? '';
    $tag = $collapse ? 'details' : 'div';
    echo '<' . $tag . ' class="rep__row"' . ($collapse && ($tpl || !empty($f['open'])) ? ' open' : '') . '>';
    if ($collapse) {
        $t = trim((string) ($row[$collapse] ?? ''));
        echo '<summary><span class="rep__handle" title="Перетащите, чтобы поменять порядок">⋮⋮</span>'
           . '<span class="rep__title" data-from="' . esc_attr($collapse) . '">' . esc_html($t !== '' ? $t : 'Новый пункт') . '</span>'
           . '<button type="button" class="rep__del" aria-label="Удалить" title="Удалить">×</button></summary>';
    } else {
        echo '<span class="rep__handle" title="Перетащите, чтобы поменять порядок">⋮⋮</span>';
    }
    echo '<div class="rep__fields">';
    foreach ($f['fields'] as $k => $sub) {
        $sub_name = $base . '[' . $k . ']';
        $sub_val = $row[$k] ?? ($sub['type'] === 'repeater' ? [] : '');
        echo $collapse === $k ? '<div data-title-src>' : '';
        memorial_plugin_field($sub_name, $sub, $sub_val, $depth + 1);
        echo $collapse === $k ? '</div>' : '';
    }
    echo '</div>';
    if (!$collapse) echo '<button type="button" class="rep__del" aria-label="Удалить" title="Удалить">×</button>';
    echo '</' . $tag . '>';
}

/* Ссылки в верхней панели: быстрое редактирование с сайта */
add_action('admin_bar_menu', function ($bar) {
    if (is_admin() || !current_user_can('manage_options')) return;
    $bar->add_node(['id' => 'mem-settings', 'title' => '✎ Тексты и контакты', 'href' => admin_url('admin.php?page=' . (is_front_page() ? 'mem-home' : 'mem-contacts'))]);
}, 80);
