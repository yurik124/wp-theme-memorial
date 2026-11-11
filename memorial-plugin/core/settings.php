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
            'type' => 'repeater', 'label' => __( 'Phones', 'memorial-plugin' ), 'add' => __( 'Add a phone', 'memorial-plugin' ),
            'help' => __( 'The first number is the main one: it is in the header and on the “Call” and “Order” buttons.', 'memorial-plugin' ),
            'fields' => [
                'num'  => ['type' => 'text', 'label' => __( 'Phone number', 'memorial-plugin' ), 'placeholder' => '+7 (900) 000-00-00'],
                'note' => ['type' => 'text', 'label' => __( 'Note (optional)', 'memorial-plugin' ), 'placeholder' => __( 'For example: round the clock', 'memorial-plugin' )],
            ],
        ],
        'emails' => [
            'type' => 'repeater', 'label' => __( 'Email', 'memorial-plugin' ), 'add' => __( 'Add an email', 'memorial-plugin' ),
            'fields' => [
                'email' => ['type' => 'email', 'label' => 'E-mail', 'placeholder' => 'name@mail.ru'],
            ],
        ],
        'addrs' => [
            'type' => 'repeater', 'label' => __( 'Addresses and opening hours', 'memorial-plugin' ), 'add' => __( 'Add an address', 'memorial-plugin' ), 'collapse' => 'addr', 'open' => true,
            'help' => __( 'The first address is shown in the top bar of the site. Every address gets a tab with a map on the home page.', 'memorial-plugin' ),
            'fields' => [
                'addr'  => ['type' => 'text', 'label' => __( 'Address', 'memorial-plugin' ), 'placeholder' => __( '308000, Belgorod, 40 Studencheskaya St.', 'memorial-plugin' )],
                'hours' => ['type' => 'text', 'label' => __( 'Opening hours', 'memorial-plugin' ), 'placeholder' => __( 'Daily from 9:00 to 18:00', 'memorial-plugin' )],
                'title' => ['type' => 'text', 'label' => __( 'Map tab label (optional)', 'memorial-plugin' ), 'placeholder' => __( 'By default — the street and the house', 'memorial-plugin' )],
                'map'   => ['type' => 'map', 'label' => __( 'Map (optional)', 'memorial-plugin' ),
                            'help' => __( 'Leave empty — the map finds the address itself. To set an exact marker, paste a link from Yandex Maps or the code from the “Map Constructor”.', 'memorial-plugin' )],
            ],
        ],
        'night' => ['type' => 'text', 'label' => __( 'Calls outside working hours', 'memorial-plugin' ), 'placeholder' => __( 'We take calls round the clock', 'memorial-plugin' ),
            'help' => __( 'Shown in the top bar, on the first screen, in “Contacts” and in the footer. Empty — not shown.', 'memorial-plugin' )],
        'messengers' => ['type' => 'group', 'label' => __( 'Messengers', 'memorial-plugin' ),
            'help' => __( 'Shown in the call-back window and in the “Contacts” block. Empty field — no button.', 'memorial-plugin' ), 'fields' => [
            'max'      => ['type' => 'text', 'label' => __( 'MAX — profile link', 'memorial-plugin' ), 'placeholder' => 'https://max.ru/u/…',
                           'help' => __( 'In the MAX app: Profile → Share profile → Copy link.', 'memorial-plugin' )],
            'telegram' => ['type' => 'text', 'label' => 'Telegram', 'placeholder' => __( 'memorialbel or https://t.me/…', 'memorial-plugin' )],
            'whatsapp' => ['type' => 'text', 'label' => __( 'WhatsApp — number', 'memorial-plugin' ), 'placeholder' => '+7 900 000-00-00'],
        ]],
        'call' => ['type' => 'group', 'label' => __( '“Call” button on a computer', 'memorial-plugin' ),
            'help' => __( 'On a phone the button simply calls. On a computer MAX opens first by the link above; the “Call me back” window appears if MAX did not open (the browser blocked the tab or the visitor came back to the site quickly). Requests come by email and are kept in “Memorial → Requests”.', 'memorial-plugin' ), 'fields' => [
            'title'   => ['type' => 'text', 'label' => __( 'Window title', 'memorial-plugin' )],
            'note'    => ['type' => 'text', 'label' => __( 'Text under the title', 'memorial-plugin' )],
            'email'   => ['type' => 'email', 'label' => __( 'Where to send requests', 'memorial-plugin' ), 'placeholder' => __( 'Empty — to the first email of the list above', 'memorial-plugin' )],
            'max_app' => ['type' => 'text', 'label' => __( 'Link that starts the MAX app (advanced)', 'memorial-plugin' ), 'placeholder' => __( 'for example max://…', 'memorial-plugin' ),
                          'help' => __( 'Usually leave it empty — the MAX profile link is used. If you know a link that starts the installed app right away, enter it.', 'memorial-plugin' )],
        ]],
        'legal' => ['type' => 'group', 'label' => __( 'Company details', 'memorial-plugin' ), 'help' => __( 'Shown on the “Contacts” page and in the footer. Empty fields are not shown.', 'memorial-plugin' ), 'fields' => [
            'name' => ['type' => 'text', 'label' => __( 'Name', 'memorial-plugin' ), 'placeholder' => __( 'For example: Sole proprietor Ivan Ivanov', 'memorial-plugin' )],
            'inn'  => ['type' => 'text', 'label' => __( 'TIN (INN)', 'memorial-plugin' )],
            'ogrn' => ['type' => 'text', 'label' => __( 'OGRN / OGRNIP', 'memorial-plugin' )],
        ]],
        'founded' => ['type' => 'number', 'label' => __( 'Year founded', 'memorial-plugin' ), 'help' => __( 'The “more than N years” on the site is counted from it.', 'memorial-plugin' )],
    ];
}

function memorial_plugin_home_schema() {
    $show = ['type' => 'checkbox', 'label' => __( 'Show this block', 'memorial-plugin' )];
    $eyebrow = ['type' => 'text', 'label' => __( 'Eyebrow (small letters above the title)', 'memorial-plugin' )];
    return [
        'layout' => ['type' => 'order', 'label' => __( 'Order of the blocks on the home page', 'memorial-plugin' ),
            'help' => __( 'Drag a row (or a block card below by ⋮⋮) with the mouse or a finger, or press ↑ ↓, then “Save order”. The first screen is always at the top. Blocks marked “hidden” are not visible on the site — turn on “Show this block” in their settings below.', 'memorial-plugin' ),
            'options' => memorial_plugin_home_blocks()],
        'hero' => ['type' => 'group', 'label' => __( 'First screen', 'memorial-plugin' ), 'fields' => [
            'eyebrow'  => $eyebrow,
            'title'    => ['type' => 'text', 'label' => __( 'Title — first line', 'memorial-plugin' )],
            'title_em' => ['type' => 'text', 'label' => __( 'Title — second line (italic, accent colour)', 'memorial-plugin' )],
            'lead'     => ['type' => 'textarea', 'label' => __( 'Text under the title', 'memorial-plugin' ), 'rows' => 3],
            'trust'    => ['type' => 'repeater', 'label' => __( 'Advantages (with laurel leaves)', 'memorial-plugin' ), 'add' => __( 'Add an item', 'memorial-plugin' ), 'fields' => [
                'text' => ['type' => 'text', 'label' => __( 'Text', 'memorial-plugin' )],
            ]],
            'help_page' => ['type' => 'page', 'label' => __( '“What to do” link under the buttons', 'memorial-plugin' ),
                            'help' => __( 'A link under the “Call” and “Call me back” buttons: a person immediately sees where to click if someone close has just died. Empty — the page “What to do when someone dies” (found automatically).', 'memorial-plugin' )],
            'help_text' => ['type' => 'text', 'label' => __( 'Text of the “what to do” link', 'memorial-plugin' ), 'placeholder' => __( 'What to do when someone dies', 'memorial-plugin' )],
            'help_off'  => ['type' => 'checkbox', 'label' => __( 'Do not show the “what to do” link', 'memorial-plugin' )],
            'img'      => ['type' => 'image', 'label' => __( 'Photo on the right (mourning hall — light, daylight)', 'memorial-plugin' ), 'help' => __( 'Shown in a rounded frame next to the text, without darkening.', 'memorial-plugin' )],
            'tint'     => ['type' => 'select', 'label' => __( 'Photo tint (not used in the light theme 4.0)', 'memorial-plugin' ), 'options' => [
                              'grad' => __( 'Gradient: lighter at the top, darker at the bottom', 'memorial-plugin' ), 'flat' => __( 'Even over the whole photo', 'memorial-plugin' ), 'off' => __( 'Off — the photo without a tint', 'memorial-plugin' )]],
            'tint_color' => ['type' => 'color', 'label' => __( 'Tint colour (not used in the light theme 4.0)', 'memorial-plugin' ), 'scheme' => 'dark', 'effective' => true],
            'mobile'   => ['type' => 'select', 'label' => __( 'On a phone', 'memorial-plugin' ), 'options' => [
                              'bg'  => __( 'Text and the call button first, the photo below (recommended)', 'memorial-plugin' ),
                              'top' => __( 'A low strip of the photo first, the text below', 'memorial-plugin' )]],
            'tint_op'  => ['type' => 'number', 'label' => __( 'Tint strength, % (not used in the light theme 4.0)', 'memorial-plugin' ), 'placeholder' => '75'],
        ]],
        'services' => ['type' => 'group', 'label' => __( '“Our services” block', 'memorial-plugin' ), 'help' => __( 'Cards with links to the service pages. The photo is optional: when it is not chosen, the featured image of the page is used.', 'memorial-plugin' ), 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => __( 'Title', 'memorial-plugin' )],
            'items'   => ['type' => 'repeater', 'label' => __( 'Services', 'memorial-plugin' ), 'add' => __( 'Add a service', 'memorial-plugin' ), 'collapse' => 'title', 'fields' => [
                'title' => ['type' => 'text', 'label' => __( 'Name', 'memorial-plugin' )],
                'text'  => ['type' => 'textarea', 'label' => __( 'Short description', 'memorial-plugin' ), 'rows' => 2],
                'page'  => ['type' => 'page', 'label' => __( 'Page', 'memorial-plugin' )],
                'price' => ['type' => 'text', 'label' => __( 'Price (optional)', 'memorial-plugin' ), 'placeholder' => __( 'for example: from 58,000 ₽', 'memorial-plugin' )],
                'img'   => ['type' => 'image', 'label' => __( 'Photo (optional)', 'memorial-plugin' )],
            ]],
        ]],
        'feats' => ['type' => 'group', 'label' => __( '“Why people trust us” block', 'memorial-plugin' ), 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => __( 'Title', 'memorial-plugin' )],
            'items'   => ['type' => 'repeater', 'label' => __( 'Items', 'memorial-plugin' ), 'add' => __( 'Add an item', 'memorial-plugin' ), 'collapse' => 'title', 'fields' => [
                'title' => ['type' => 'text', 'label' => __( 'Title', 'memorial-plugin' )],
                'text'  => ['type' => 'textarea', 'label' => __( 'Text', 'memorial-plugin' ), 'rows' => 3],
                'icon'  => ['type' => 'select', 'label' => __( 'Icon', 'memorial-plugin' ), 'options' => ['auto' => __( 'Choose by the title', 'memorial-plugin' )] + memorial_plugin_feat_icons()],
                'img'   => ['type' => 'image', 'label' => __( 'Photo instead of the icon (optional)', 'memorial-plugin' )],
            ]],
        ]],
        'catalog' => ['type' => 'group', 'label' => __( '“Catalog” block', 'memorial-plugin' ), 'help' => __( 'The tiles are the sub-items of the “Funeral goods” menu item (Appearance → Menus): names and order are taken from there. The photo of a tile is the “Featured image” of the page the item leads to; if there is none — the photo of the first product of that category.', 'memorial-plugin' ), 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => __( 'Title', 'memorial-plugin' )],
        ]],
        'prices' => ['type' => 'group', 'label' => __( '“Service packages” block', 'memorial-plugin' ), 'help' => __( 'The packages themselves, their contents and prices — in “Memorial → Prices”.', 'memorial-plugin' ), 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => __( 'Title', 'memorial-plugin' )],
        ]],
        'stats' => ['type' => 'group', 'label' => __( 'Block with numbers', 'memorial-plugin' ), 'fields' => [
            'show'  => $show,
            'items' => ['type' => 'repeater', 'label' => __( 'Numbers', 'memorial-plugin' ), 'add' => __( 'Add a number', 'memorial-plugin' ), 'inline' => true, 'fields' => [
                'num'   => ['type' => 'number', 'label' => __( 'Number', 'memorial-plugin' )],
                'label' => ['type' => 'text', 'label' => __( 'Caption', 'memorial-plugin' )],
            ]],
        ]],
        'about' => ['type' => 'group', 'label' => __( '“About the company” block', 'memorial-plugin' ), 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => __( 'Title', 'memorial-plugin' )],
            'text'    => ['type' => 'textarea', 'label' => __( 'Text', 'memorial-plugin' ), 'rows' => 6, 'html' => true,
                          'help' => __( 'An empty line — a new paragraph. You can use <b>, <a> and other simple markup.', 'memorial-plugin' )],
        ]],
        'reviews' => ['type' => 'group', 'label' => __( '“Reviews” block (from Yandex Maps)', 'memorial-plugin' ), 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => __( 'Title', 'memorial-plugin' )],
            'text'    => ['type' => 'textarea', 'label' => __( 'Text next to the reviews', 'memorial-plugin' ), 'rows' => 3],
            'org'     => ['type' => 'text', 'label' => __( 'Organisation on Yandex Maps — number or link', 'memorial-plugin' ),
                          'help' => __( 'For example 75717068753 or a link like https://yandex.ru/maps/org/…/75717068753/', 'memorial-plugin' )],
        ]],
        'contacts' => ['type' => 'group', 'label' => __( '“Contacts” block', 'memorial-plugin' ), 'help' => __( 'Phones, email and addresses are taken from the “Memorial → Contacts” page.', 'memorial-plugin' ), 'fields' => [
            'show'    => $show,
            'eyebrow' => $eyebrow,
            'title'   => ['type' => 'text', 'label' => __( 'Title', 'memorial-plugin' )],
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
    foreach ([1 => __( '308000, Belgorod, 40 Studencheskaya St.', 'memorial-plugin' ), 2 => '308024, г. Белгород, ул. Щорса, 21'] as $n => $addr) {
        $a = $m('addr' . $n, $addr);
        if (!$a) continue;
        $map = $m('map' . $n, '');
        $addrs[] = [
            'addr'  => $a,
            'hours' => $m('hours', __( 'Daily from 9:00 to 18:00', 'memorial-plugin' )),
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
        'night'   => __( 'We take calls round the clock', 'memorial-plugin' ),
        'messengers' => ['max' => MEMORIAL_PLUGIN_MAX_URL, 'telegram' => '', 'whatsapp' => ''],
        'call'       => ['title' => 'Перезвоним вам', 'note' => 'Оставьте номер — перезвоним в ближайшее время.', 'email' => '', 'max_app' => ''],
        'founded' => (int) $m('founded', 2003),
    ];
}

/** Блоки главной, которые можно переставлять */
function memorial_plugin_home_blocks() {
    return ['services' => __( 'Our services', 'memorial-plugin' ), 'feats' => __( 'Why people trust us', 'memorial-plugin' ), 'catalog' => __( 'Catalog', 'memorial-plugin' ), 'prices' => __( 'Service packages', 'memorial-plugin' ),
            'stats' => __( 'Block with numbers', 'memorial-plugin' ), 'about' => __( 'About the company', 'memorial-plugin' ), 'reviews' => __( 'Reviews', 'memorial-plugin' ), 'contacts' => __( 'Contacts', 'memorial-plugin' )];
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
    return [get_permalink($id), trim($h['help_text'] ?? '') ?: __( 'What to do when someone dies', 'memorial-plugin' )];
}

/* Разово: старые «продающие» тексты главной, если их не меняли, заменяем на новые (прежние — в истории не нужны, они из шаблона) */
add_action('admin_init', function () {
    if (get_option('mem_home_texts_v2') || !current_user_can('manage_options')) return;
    update_option('mem_home_texts_v2', 1, false);
    $o = get_option('mem_home');
    if (!is_array($o)) return;
    $ch = [];
    $old_em = ['Свой траурный зал, венки собственного производства', 'для прощания и панихиды'];
    if (in_array(trim($o['hero']['title_em'] ?? ''), $old_em, true)) { $o['hero']['title_em'] = 'Поможем на каждом шаге'; $ch[] = __( 'second line of the title', 'memorial-plugin' ); }
    if (trim($o['hero']['title'] ?? '') === 'Собственный траурный зал') { $o['hero']['title'] = 'Ритуальные услуги в Белгороде'; $ch[] = __( 'title', 'memorial-plugin' ); }
    if (trim($o['hero']['lead'] ?? '') === 'Возьмём на себя все хлопоты: организация похорон, документы, транспорт, венки собственного производства.') { $o['hero']['lead'] = MEMORIAL_PLUGIN_HERO_LEAD; $ch[] = __( 'text under the title', 'memorial-plugin' ); }
    $tr = array_map(fn($t) => trim($t['text'] ?? ''), (array) ($o['hero']['trust'] ?? []));
    if (in_array('Собственное здание — не фирма-однодневка', $tr, true)) { $o['hero']['trust'] = MEMORIAL_PLUGIN_HERO_TRUST; $ch[] = __( 'items with ticks', 'memorial-plugin' ); }
    if (strpos((string) ($o['about']['text'] ?? ''), 'предлагает качественные ритуальные услуги') !== false) { $o['about']['text'] = MEMORIAL_PLUGIN_ABOUT_TEXT; $ch[] = __( '“About the company” text', 'memorial-plugin' ); }
    if (!$ch) return;
    update_option('mem_home', $o);
    set_transient('mem_places_report', array_merge(array_filter((array) get_transient('mem_places_report')), [
        __( 'Home page: updated ', 'memorial-plugin' ) . implode(', ', $ch) . __( ' — calmer and more specific. You can change it in “Memorial → Home page”.', 'memorial-plugin' )]), DAY_IN_SECONDS);
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
            'help_page' => '', 'help_text' => __( 'What to do when someone dies', 'memorial-plugin' ), 'help_off' => 0,
            'img'      => get_theme_mod('mem_hero_img', ''),
            'tint'     => 'grad', 'tint_color' => '', 'tint_op' => 75, 'mobile' => 'bg',
        ],
        'services' => [
            'show' => 1, 'eyebrow' => 'Чем мы поможем', 'title' => __( 'Our services', 'memorial-plugin' ),
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
            'show' => 1, 'eyebrow' => __( 'Why people trust us', 'memorial-plugin' ), 'title' => 'Своё, а не через посредников',
            'items' => [
                ['title' => 'Собственный траурный зал', 'text' => 'Предоставляем зал для прощания и панихиды бесплатно — в спокойной и достойной обстановке.', 'icon' => 'candle', 'img' => ''],
                ['title' => 'Собственное производство венков', 'text' => 'Изготавливаем венки и композиции сами: любой размер и цвет, качество под контролем, без наценок посредников.', 'icon' => 'flower', 'img' => ''],
                ['title' => 'Собственное здание', 'text' => 'Более {years} лет работаем в своём здании, а не во временном офисе. Мы не фирма-однодневка.', 'icon' => 'building', 'img' => ''],
                ['title' => 'Документы для компенсации', 'text' => 'Заключаем официальный договор и выдаём полный пакет документов для получения компенсации через военкомат.', 'icon' => 'doc', 'img' => ''],
            ],
        ],
        'catalog' => ['show' => 1, 'eyebrow' => __( 'Catalog', 'memorial-plugin' ), 'title' => 'Ритуальные товары'],
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
            'show' => 1, 'eyebrow' => __( 'About the company', 'memorial-plugin' ), 'title' => 'Более {years} лет рядом в трудную минуту',
            'text' => MEMORIAL_PLUGIN_ABOUT_TEXT,
        ],
        'reviews' => [
            'show' => 1, 'eyebrow' => __( 'Reviews', 'memorial-plugin' ), 'title' => 'Что о нас пишут',
            'text' => 'Это настоящие отзывы наших клиентов на Яндекс Картах — мы не можем их редактировать или удалять. Будем благодарны, если вы тоже поделитесь впечатлением.',
            'org'  => '75717068753',
        ],
        'contacts' => ['show' => 1, 'eyebrow' => __( 'Contacts', 'memorial-plugin' ), 'title' => 'Мы на связи'],
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
    if ($inn = preg_replace('/\D/', '', $l['inn'] ?? '')) $out[] = __( 'TIN ', 'memorial-plugin' ) . $inn;
    if ($ogrn = preg_replace('/\D/', '', $l['ogrn'] ?? '')) $out[] = (strlen($ogrn) === 15 ? __( 'OGRNIP ', 'memorial-plugin' ) : __( 'OGRN ', 'memorial-plugin' )) . $ogrn;
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

/** Главная перенесена в блоки (blocks/home): на экране остаётся только список услуг */
function memorial_plugin_home_migrated() {
    $log = get_option('memorial_plugin_home_migrated');
    return is_array($log) && ($log['result'] ?? '') === 'migrated';
}

function memorial_plugin_home_services_schema() {
    $s = memorial_plugin_home_schema()['services'];
    return ['services' => ['type' => 'group', 'label' => __( 'Services', 'memorial-plugin' ), 'help' => $s['help'] ?? '', 'fields' => ['items' => $s['fields']['items']]]];
}

function memorial_plugin_admin_pages() {
    $home = memorial_plugin_home_migrated()
        ? ['slug' => 'mem-home', 'title' => __( 'Services', 'memorial-plugin' ), 'schema' => 'memorial_plugin_home_services_schema',
           'intro' => __( 'Service cards with photos and prices — on the home page and in the “Our services” block on other pages. The texts of the home page are edited right on it: “Appearance → Editor → Templates → Home”.', 'memorial-plugin' )]
        : null;
    return apply_filters('memorial_plugin_admin_pages', [
        'mem_contacts' => ['slug' => 'mem-contacts', 'title' => __( 'Contacts', 'memorial-plugin' ), 'schema' => 'memorial_plugin_contacts_schema',
                           'intro' => __( 'Phones, email, addresses and opening hours. Changes appear at once in the header, the footer, on the home page and in the product cards.', 'memorial-plugin' )],
        'mem_home'     => $home ?? ['slug' => 'mem-home', 'title' => __( 'Home page', 'memorial-plugin' ), 'schema' => 'memorial_plugin_home_schema',
                           'intro' => __( 'Texts and items of the home page blocks. Items can be dragged by ⋮⋮, removed with the cross and added with the button under the list. Empty items are not saved.<br>', 'memorial-plugin' )
                                    . __( 'In the text you can write <code>{years}</code> — how many years the company has worked, <code>{founded}</code> — the year it was founded, <code>{addr}</code> — the first address, <code>{phone}</code> — the main phone.', 'memorial-plugin' )],
    ]);
}

add_action('admin_menu', function () {
    global $memorial_plugin_admin_hooks;
    $memorial_plugin_admin_hooks = [];
    $pages = memorial_plugin_admin_pages();
    add_menu_page(__( 'Site settings', 'memorial-plugin' ), __( 'Memorial', 'memorial-plugin' ), 'manage_options', 'mem-contacts', '__return_null', 'dashicons-admin-home', 3);
    foreach ($pages as $opt => $p) {
        // 'hidden' — страница без своего пункта меню (открывается вкладкой другой страницы)
        // страница настроек — приложение на DataForm (admin/settings-form.php, src/settings)
        $hook = add_submenu_page(empty($p['hidden']) ? 'mem-contacts' : '', $p['title'], $p['title'], 'manage_options', $p['slug'],
            fn() => memorial_plugin_settings_container($opt));
        $memorial_plugin_admin_hooks[] = $hook;
        if ($hook) add_action('load-' . $hook, fn() => add_action('admin_enqueue_scripts', 'memorial_plugin_settings_assets'));
        // у страницы без пункта меню WordPress не знает заголовок окна — задаём сами
        if (!empty($p['hidden']) && $hook) add_action('load-' . $hook, function () use ($p) { $GLOBALS['title'] = $p['h1'] ?? $p['title']; });
    }
});

/* Порядок пунктов «Мемориал»: сначала то, чем пользуются каждый день, внизу — разовые настройки */
add_action('admin_menu', function () {
    global $submenu;
    if (empty($submenu['mem-contacts'])) return;
    $order = ['memorial-plugin-leads', 'mem-contacts', 'mem-prices', 'mem-reviews', 'mem-home', 'mem-rest', 'mem-cem', 'mem-design', 'mem-metrika', 'mem-legal', 'mem-sq', 'mem-modules'];
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

/* Форма страниц настроек строится приложением на DataForm (admin/settings-form.php); классические формы удалены на этапе 4. */

/* Ссылки в верхней панели: быстрое редактирование с сайта */
add_action('admin_bar_menu', function ($bar) {
    if (is_admin() || !current_user_can('manage_options')) return;
    $bar->add_node(['id' => 'mem-settings', 'title' => __( '✎ Texts and contacts', 'memorial-plugin' ), 'href' => admin_url('admin.php?page=' . (is_front_page() ? 'mem-home' : 'mem-contacts'))]);
}, 80);
