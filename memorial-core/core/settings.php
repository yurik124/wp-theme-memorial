<?php
/**
 * Настройки сайта в админке: «Мемориал → Контакты» и «Мемориал → Главная страница».
 *
 * Каждая страница описана схемой полей (mem_contacts_schema / mem_home_schema).
 * По схеме строится форма, проверяются данные при сохранении и подставляются значения по умолчанию.
 * Чтобы добавить поле — допишите его в схему и в defaults, больше ничего не нужно.
 */
defined('ABSPATH') || exit;

define('MEM_MAX_URL', 'https://max.ru/u/f9LHodD0cOLf3sDsVZFTsBTdONk3kEAboq9CfefPcekCM_KZnnErTEhejHM');

/* Разовое обновление 1.5: MAX вместо Telegram в уже сохранённых контактах */
add_action('init', function () {
    if (get_option('mem_ver_15')) return;
    $c = get_option('mem_contacts');
    if (is_array($c) && isset($c['messengers'])) {
        if (empty($c['messengers']['max'])) $c['messengers']['max'] = MEM_MAX_URL;
        if (trim($c['messengers']['telegram'] ?? '') === '@memorialbel') $c['messengers']['telegram'] = '';
        update_option('mem_contacts', $c);
    }
    update_option('mem_ver_15', 1, false);
});

if (!function_exists('array_is_list')) { // PHP < 8.1
    function array_is_list(array $a) { return $a === [] || array_keys($a) === range(0, count($a) - 1); }
}

/* =========================================================
 * Схемы
 * ======================================================= */

function mem_contacts_schema() {
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

function mem_home_schema() {
    $show = ['type' => 'checkbox', 'label' => 'Показывать этот блок'];
    $eyebrow = ['type' => 'text', 'label' => 'Надзаголовок (мелкие буквы над заголовком)'];
    return [
        'layout' => ['type' => 'order', 'label' => 'Порядок блоков на главной',
            'help' => 'Перетащите строки за ⋮⋮, чтобы поменять блоки местами. Первый экран всегда наверху. Скрыть блок — снимите «Показывать этот блок» в его настройках ниже.',
            'options' => mem_home_blocks()],
        'hero' => ['type' => 'group', 'label' => 'Первый экран', 'fields' => [
            'eyebrow'  => $eyebrow,
            'title'    => ['type' => 'text', 'label' => 'Заголовок — первая строка'],
            'title_em' => ['type' => 'text', 'label' => 'Заголовок — вторая строка (золотым курсивом)'],
            'lead'     => ['type' => 'textarea', 'label' => 'Текст под заголовком', 'rows' => 3],
            'trust'    => ['type' => 'repeater', 'label' => 'Преимущества с галочками', 'add' => 'Добавить пункт', 'fields' => [
                'text' => ['type' => 'text', 'label' => 'Текст'],
            ]],
            'img'      => ['type' => 'image', 'label' => 'Фоновое фото'],
            'tint'     => ['type' => 'select', 'label' => 'Тонировка фото (чтобы текст хорошо читался)', 'options' => [
                              'grad' => 'Плавная: сверху светлее, внизу темнее', 'flat' => 'Ровная по всему фото', 'off' => 'Выключить — фото без тонировки']],
            'tint_color' => ['type' => 'color', 'label' => 'Цвет тонировки', 'scheme' => 'dark', 'effective' => true],
            'tint_op'  => ['type' => 'number', 'label' => 'Сила тонировки, % (0 — нет, 100 — сплошной цвет)', 'placeholder' => '75',
                           'help' => 'При слабой тонировке на светлом фото белый текст может читаться плохо — тогда тема добавит ему лёгкую тень.'],
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
                'icon'  => ['type' => 'select', 'label' => 'Значок', 'options' => ['auto' => 'Подобрать по заголовку'] + mem_feat_icons()],
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

function mem_contacts_defaults() {
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
        'messengers' => ['max' => MEM_MAX_URL, 'telegram' => '', 'whatsapp' => ''],
        'call'       => ['title' => 'Перезвоним вам', 'note' => 'Оставьте номер — перезвоним в ближайшее время.', 'email' => '', 'max_app' => ''],
        'founded' => (int) $m('founded', 2003),
    ];
}

/** Блоки главной, которые можно переставлять */
function mem_home_blocks() {
    return ['services' => 'Наши услуги', 'feats' => 'Почему нам доверяют', 'catalog' => 'Каталог', 'prices' => 'Пакеты услуг',
            'stats' => 'Блок с цифрами', 'about' => 'О компании', 'reviews' => 'Отзывы', 'contacts' => 'Контакты'];
}

function mem_home_defaults() {
    $p = fn(...$rows) => array_map(fn($r) => ['name' => $r[0], 'price' => $r[1], 'key' => preg_match('/^(Гроб|Крест|Катафалк|Венок|Услуги по захоронению)/u', $r[0]) ? 1 : 0], $rows);
    return [
        'layout' => array_keys(mem_home_blocks()),
        'hero' => [
            'eyebrow'  => 'Ритуальная компания в Белгороде · с {founded} года',
            'title'    => 'Собственный траурный зал',
            'title_em' => 'для прощания и панихиды',
            'lead'     => 'Возьмём на себя все хлопоты: организация похорон, документы, транспорт, венки собственного производства.',
            'trust'    => [
                ['text' => 'Собственное здание — не фирма-однодневка'],
                ['text' => 'Своё производство венков'],
                ['text' => 'Документы для компенсации через военкомат'],
            ],
            'img'      => get_theme_mod('mem_hero_img', ''),
            'tint'     => 'grad', 'tint_color' => '', 'tint_op' => 75,
        ],
        'services' => [
            'show' => 1, 'eyebrow' => 'Чем мы поможем', 'title' => 'Наши услуги',
            'items' => [
                ['title' => 'Организация похорон', 'text' => 'Возьмём на себя документы, морг, транспорт и проведение похорон.', 'page' => mem_guess_page(['organizaciya-pohoron', 'organizacziya-pohoron', 'pomoshh-v-organizacii-pohoron']), 'price' => '', 'img' => ''],
                ['title' => 'Кремация', 'text' => 'Доставим в крематорий Тулы или Воронежа, выдадим прах в урне в день кремирования.', 'page' => mem_guess_page(['kremacziya', 'kremaciya']), 'price' => 'от 58 000 ₽', 'img' => ''],
                ['title' => 'Траурный зал', 'text' => 'Собственный зал для прощания и отпевания на ул. Студенческой, 40.', 'page' => mem_guess_page(['traurnyj-zal']), 'price' => '', 'img' => ''],
                ['title' => 'Груз 200', 'text' => 'Перевозка умерших в другие города и регионы.', 'page' => mem_guess_page(['gruz-200', 'perevozka-umershih', 'perevozka-umershego']), 'price' => '', 'img' => ''],
                ['title' => 'Благоустройство захоронений', 'text' => 'Памятники, оградки, укладка плитки — под ключ.', 'page' => mem_guess_page(['blagoustrojstvo-zahoroneniya', 'blagoustrojstvo']), 'price' => '', 'img' => ''],
                ['title' => 'Ритуальные товары', 'text' => 'Венки собственного производства, гробы, кресты.', 'page' => mem_guess_page(['ritualnye-tovary']), 'price' => '', 'img' => ''],
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
            'text' => "Ритуальная компания «Мемориал» предлагает качественные ритуальные услуги в Белгороде. Наша главная задача — максимально облегчить заботы, связанные с организацией похорон.\n\nРитуальные агенты помогут организовать достойные похороны близкого человека. Быстрое обслуживание и внимательный персонал освободят вас от траурных хлопот.",
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
function mem_merge($def, $saved) {
    if (!is_array($saved)) return $def;
    foreach ($def as $k => $v) {
        if (!array_key_exists($k, $saved)) continue;
        $def[$k] = (is_array($v) && $v && !array_is_list($v) && is_array($saved[$k]))
            ? mem_merge($v, $saved[$k])
            : $saved[$k];
    }
    return $def;
}

function mem_settings($name) {
    static $cache = [];
    if (!isset($cache[$name])) {
        $def = function_exists($name . '_defaults') ? call_user_func($name . '_defaults') : [];
        $cache[$name] = mem_merge($def, get_option($name, null));
    }
    return $cache[$name];
}

function mem_contacts() { return mem_settings('mem_contacts'); }
function mem_night() { return trim((string) (mem_contacts()['night'] ?? '')); }

/** id первой найденной страницы по адресам-кандидатам (для настроек по умолчанию) */
function mem_guess_page(array $paths) {
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
function mem_home($section) { return mem_settings('mem_home')[$section] ?? []; }

function mem_phones() {
    return array_values(array_filter(mem_contacts()['phones'], fn($p) => trim($p['num'] ?? '') !== ''));
}
function mem_emails() {
    return array_values(array_filter(wp_list_pluck(mem_contacts()['emails'], 'email')));
}
/** Номер организации на Яндекс Картах */
function mem_yandex_org() {
    $raw = (string) (mem_home('reviews')['org'] ?? '');
    return preg_match('~(\d{6,})/?\s*$~', trim($raw), $m) || preg_match('~/(\d{6,})(?:/|$|\?)~', $raw, $m) ? $m[1] : '';
}

/** Реквизиты строкой: «ИП … · ИНН … · ОГРНИП …» */
function mem_legal_parts() {
    $l = mem_contacts()['legal'] ?? [];
    $out = [];
    if (trim($l['name'] ?? '')) $out[] = trim($l['name']);
    if ($inn = preg_replace('/\D/', '', $l['inn'] ?? '')) $out[] = 'ИНН ' . $inn;
    if ($ogrn = preg_replace('/\D/', '', $l['ogrn'] ?? '')) $out[] = (strlen($ogrn) === 15 ? 'ОГРНИП ' : 'ОГРН ') . $ogrn;
    return $out;
}

/** Ссылки мессенджеров: ['max' => url, 'telegram' => url, 'whatsapp' => url] (только заполненные) */
function mem_messengers() {
    $m = mem_contacts()['messengers'] ?? [];
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

function mem_addrs() {
    return array_values(array_filter(mem_contacts()['addrs'], fn($a) => trim($a['addr'] ?? '') !== ''));
}

/** Номер для ссылки tel: */
function mem_tel_of($num) {
    return preg_replace('/[^\d+]/', '', $num);
}

/** Подстановки в тексты: {years}, {founded}, {addr}, {phone} */
function mem_t($s) {
    $a = mem_addrs();
    return strtr((string) $s, [
        '{years}'   => mem_years(),
        '{founded}' => (int) mem_contacts()['founded'],
        '{addr}'    => $a ? $a[0]['addr'] : '',
        '{phone}'   => mem_opt('phone'),
    ]);
}

/* =========================================================
 * Карты
 * ======================================================= */

/** Из того, что вставили в поле «Карта», достаём ссылку. */
function mem_clean_map($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') return '';
    if (preg_match('~src=["\']([^"\']+)~i', $raw, $m)) $raw = html_entity_decode($m[1]);
    if (strpos($raw, '//') === 0) $raw = 'https:' . $raw;
    return esc_url_raw($raw, ['https', 'http']);
}

/** Ссылка для iframe. Любая ссылка Яндекс.Карт приводится к виджету; если не получилось — ищем по адресу. */
function mem_map_src($map, $addr) {
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
function mem_short_addr($addr) {
    $s = preg_replace('/^\d{6},\s*/u', '', $addr);
    $s = preg_replace('/^(г\.|город)\s*[^,]+,\s*/u', '', $s);
    return trim($s);
}

/* =========================================================
 * Проверка данных при сохранении
 * ======================================================= */

function mem_sanitize($schema, $in) {
    $in = is_array($in) ? $in : [];
    $out = [];
    foreach ($schema as $k => $f) {
        $v = $in[$k] ?? null;
        switch ($f['type']) {
            case 'group':
                $out[$k] = mem_sanitize($f['fields'], $v);
                break;
            case 'repeater':
                $rows = [];
                foreach (is_array($v) ? $v : [] as $row) {
                    $r = mem_sanitize($f['fields'], $row);
                    if (!mem_row_empty($r, $f['fields'])) $rows[] = $r;
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
                $out[$k] = mem_clean_map($v);
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
function mem_row_empty($r, $fields) {
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

function mem_admin_pages() {
    return apply_filters('mem_admin_pages', [
        'mem_contacts' => ['slug' => 'mem-contacts', 'title' => 'Контакты', 'schema' => 'mem_contacts_schema',
                           'intro' => 'Телефоны, почта, адреса и время работы. Изменения сразу появятся в шапке, подвале, на главной и в карточках товаров.'],
        'mem_home'     => ['slug' => 'mem-home', 'title' => 'Главная страница', 'schema' => 'mem_home_schema',
                           'intro' => 'Тексты и пункты блоков главной. Пункты можно перетаскивать за ⋮⋮, удалять крестиком и добавлять кнопкой под списком. Пустые пункты не сохраняются.<br>'
                                    . 'В тексте можно писать <code>{years}</code> — сколько лет компании, <code>{founded}</code> — год основания, <code>{addr}</code> — первый адрес, <code>{phone}</code> — основной телефон.'],
    ]);
}

add_action('admin_menu', function () {
    global $mem_admin_hooks;
    $mem_admin_hooks = [];
    $pages = mem_admin_pages();
    add_menu_page('Настройки сайта', 'Мемориал', 'manage_options', 'mem-contacts', '__return_null', 'dashicons-admin-home', 3);
    foreach ($pages as $opt => $p) {
        // 'hidden' — страница без своего пункта меню (открывается вкладкой другой страницы)
        $hook = add_submenu_page(empty($p['hidden']) ? 'mem-contacts' : '', $p['title'], $p['title'], 'manage_options', $p['slug'],
            fn() => mem_admin_render($opt));
        $mem_admin_hooks[] = $hook;
        // у страницы без пункта меню WordPress не знает заголовок окна — задаём сами
        if (!empty($p['hidden']) && $hook) add_action('load-' . $hook, function () use ($p) { $GLOBALS['title'] = $p['h1'] ?? $p['title']; });
    }
});

/* Порядок пунктов «Мемориал»: сначала то, чем пользуются каждый день, внизу — разовые настройки */
add_action('admin_menu', function () {
    global $submenu;
    if (empty($submenu['mem-contacts'])) return;
    $order = ['edit.php?post_type=mem_lead', 'mem-contacts', 'mem-prices', 'mem-reviews', 'mem-home', 'mem-design', 'mem-speed', 'mem-metrika', 'mem-help-m'];
    $items = $submenu['mem-contacts'];
    usort($items, function ($a, $b) use ($order) {
        $ia = array_search($a[2], $order, true); $ib = array_search($b[2], $order, true);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });
    $submenu['mem-contacts'] = array_values($items);
}, 9999);

/** Вкладки над страницами одного раздела (например, «Отзывы»: сервисы · Rewio · из книги) */
function mem_admin_tabs($tabs, $current) {
    echo '<nav class="nav-tab-wrapper mem-tabs" style="margin:6px 0 14px">';
    foreach ($tabs as $key => [$label, $url]) {
        echo '<a class="nav-tab' . ($key === $current ? ' nav-tab-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';
}

add_action('admin_init', function () {
    foreach (mem_admin_pages() as $opt => $p) {
        register_setting($opt, $opt, [
            'type'              => 'array',
            'sanitize_callback' => fn($v) => mem_sanitize(call_user_func($p['schema']), $v),
            'show_in_rest'      => false,
        ]);
    }
});

add_action('admin_enqueue_scripts', function ($hook) {
    global $mem_admin_hooks;
    if (!in_array($hook, (array) $mem_admin_hooks, true)) return;
    $dir = MEM_CORE_DIR;
    $uri = MEM_CORE_URL;
    wp_enqueue_media();
    wp_enqueue_style('mem-admin', $uri . '/assets/css/admin.css', [], filemtime($dir . '/assets/css/admin.css'));
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('mem-admin', $uri . '/assets/js/admin.js', ['jquery', 'jquery-ui-sortable', 'wp-color-picker'], filemtime($dir . '/assets/js/admin.js'), true);
});

function mem_admin_render($opt) {
    $p = mem_admin_pages()[$opt];
    $val = mem_settings($opt);
    ?>
    <div class="wrap mem-admin">
      <h1><?php echo esc_html($p['h1'] ?? $p['title']); ?></h1>
      <?php do_action('mem_admin_tabs', $opt); ?>
      <p class="mem-admin__intro"><?php echo wp_kses_post($p['intro']); ?></p>
      <?php settings_errors(); do_action('mem_admin_after_intro', $opt); ?>
      <form method="post" action="options.php">
        <?php settings_fields($opt);
        foreach (call_user_func($p['schema']) as $k => $f) {
            $title = $f['label'];
            if ($f['type'] !== 'group') $f['label'] = '';
            echo '<section class="mem-card' . (!empty($f['class']) ? ' ' . esc_attr($f['class']) : '') . '"><h2>' . esc_html($title) . '</h2>';
            if (!empty($f['help']) && $f['type'] === 'group') echo '<p class="description">' . esc_html($f['help']) . '</p>';
            // группа со своим видом (например, выбор филиалов кнопками) — рисует себя сама, сохраняется по полям схемы
            if (!empty($f['render'])) call_user_func($f['render'], $opt . '[' . $k . ']', $val[$k] ?? null);
            else mem_field($opt . '[' . $k . ']', $f, $val[$k] ?? null, 1, true);
            echo '</section>';
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
function mem_field($name, $f, $v, $depth = 1, $top = false) {
    $id    = 'f' . substr(md5($name), 0, 10);
    $label = $f['label'] ?? '';
    $help  = !empty($f['help']) && $f['type'] !== 'group' ? '<span class="description">' . esc_html($f['help']) . '</span>' : '';
    $ph    = !empty($f['placeholder']) ? ' placeholder="' . esc_attr($f['placeholder']) . '"' : '';

    switch ($f['type']) {
        case 'group':
            foreach ($f['fields'] as $k => $sub) mem_field($name . '[' . $k . ']', $sub, $v[$k] ?? null, $depth);
            return;

        case 'repeater':
            $key = '__i' . $depth . '__';
            $cls = 'rep' . (!empty($f['inline']) ? ' rep--inline' : '');
            echo '<div class="fld fld--rep">';
            if ($label) echo '<span class="fld__label">' . esc_html($label) . '</span>';
            if ($help) echo $help;
            echo '<div class="' . esc_attr($cls) . '" data-key="' . esc_attr($key) . '">';
            echo '<div class="rep__rows">';
            foreach (is_array($v) ? array_values($v) : [] as $i => $row) mem_rep_row($name, $f, $i, $row, $depth);
            echo '</div>';
            echo '<template>';
            mem_rep_row($name, $f, $key, [], $depth, true);
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
            echo '<ol class="mem-order">';
            foreach ($keys as $key) {
                echo '<li><span class="rep__handle" aria-hidden="true">⋮⋮</span><input type="hidden" name="' . esc_attr($name) . '[]" value="' . esc_attr($key) . '">'
                   . esc_html($f['options'][$key]) . '<span class="mem-order__btns"><button type="button" class="button-link" data-mv="-1" aria-label="Выше">↑</button><button type="button" class="button-link" data-mv="1" aria-label="Ниже">↓</button></span></li>';
            }
            echo '</ol>';
            return;

        case 'color':
            $sc = !empty($f['scheme']) && function_exists('mem_scheme_color') ? mem_scheme_color($f['scheme'], !empty($f['effective'])) : '';
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

function mem_rep_row($name, $f, $i, $row, $depth, $tpl = false) {
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
        mem_field($sub_name, $sub, $sub_val, $depth + 1);
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
