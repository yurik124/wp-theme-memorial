<?php
/**
 * Plugin Name: Memorial Core
 * Description: Функционал сайта «Мемориал»: каталог, цены, отзывы (Rewio, книга отзывов), «Наши работы», заявки, настройки «Мемориал», шорткоды, SEO, Метрика, кэш. Дизайн — в теме Memorial Modern.
 * Version: 1.13.2
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Мемориал
 * Text Domain: memorial-core
 *
 * Где что лежит — см. README.md рядом с этим файлом.
 */
defined('ABSPATH') || exit;

/*
 * Старая тема (до 3.0) сама содержит весь этот функционал. Если её не обновили — плагин ничего не подключает,
 * иначе функции объявились бы дважды. Сайт работает на старой теме, в админке — подсказка обновить тему.
 */
$mem_theme_ver = (string) (get_file_data(get_template_directory() . '/style.css', ['v' => 'Version'])['v'] ?? '');
// старая тема = её версия ниже 3.0 (по файлам не судим: при выгрузке на хостинг старые файлы могут остаться в папке темы)
if (get_template() === 'memorial_speshu' && $mem_theme_ver !== '' && version_compare($mem_theme_ver, '3.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-warning"><p><b>Memorial Core:</b> обновите тему «Memorial Modern» до версии 3.0 — '
           . 'пока стоит старая тема, плагин не работает (весь функционал пока в самой теме, сайт работает как раньше).</p></div>';
    });
    return;
}
unset($mem_theme_ver);

define('MEM_CORE_VER', '1.13.2');
define('MEM_CORE_FILE', __FILE__);
define('MEM_CORE_DIR', __DIR__);                          // без «/» в конце: MEM_CORE_DIR . '/assets/…'
define('MEM_CORE_URL', untrailingslashit(plugins_url('', __FILE__)));

/** Файл плагина по относительному пути */
function mem_core_path($rel) {
    return MEM_CORE_DIR . '/' . ltrim($rel, '/');
}

/** Сжатая копия (.min.css / .min.js), если она есть и не старше исходника (устаревшая пересоздаётся) */
function mem_core_asset($path) {
    if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) return $path;
    $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
    // копия устарела — пересоздаётся сама (speed/auto-minify.php)
    return mem_min_refresh(MEM_CORE_DIR . $path, MEM_CORE_DIR . $min) ? $min : $path;
}

/** Шаблон блока: сначала из темы (template-parts/{name}.php — дизайн можно поменять в теме), иначе — из плагина (templates/) */
function mem_core_part($name, $args = []) {
    $f = locate_template('template-parts/' . $name . '.php');
    if (!$f) $f = mem_core_path('templates/' . $name . '.php');
    if (is_file($f)) load_template($f, false, $args);
}

/**
 * Отключаемые функции: ключ => [название, описание, файлы, шорткоды (на страницах вместо них — пусто), зависит от].
 * Остальное (каталог, цены, контакты, документы, SEO-основа, скорость) — основа сайта, выключить нельзя.
 */
function mem_core_optional() {
    return [
        'callback'  => ['Заявка «Перезвоните мне»', 'Окно с формой обратного звонка и уведомления о заявках. Выключено — кнопки «Перезвоните мне» просто звонят по телефону.',
                        ['leads/callback.php'], []],
        'metrika'   => ['Яндекс Метрика и уведомление о cookie', 'Счётчик, цели, очистка адреса от UTM-меток, плашка о cookie.',
                        ['seo/metrika.php'], []],
        'seoplug'   => ['Совместимость с SEO-плагинами', 'Шорткоды [mem_…] в заголовках и описаниях AIOSEO, Yoast, Rank Math, SEOPress.',
                        ['seo/seo-plugins.php'], []],
        'rewio'     => ['Отзывы с Яндекс Карт (Rewio)', 'Загрузка отзывов по расписанию, блок отзывов [mem_rewio].',
                        ['reviews/rewio.php'], ['mem_rewio']],
        'book'      => ['Книга отзывов', 'Отзывы, оставленные на сайте, [mem_bookreviews].',
                        ['reviews/bookreviews.php'], ['mem_bookreviews']],
        'works'     => ['«Наши работы»', 'Раздел работ с фото и ценами, менеджер работ, блоки [mem_works].',
                        ['works/works.php', 'works/works-manager.php'], ['mem_works']],
        'photos'    => ['Фотогалерея: фото товаров и работ', 'Блоки [mem_cat_photos] и [mem_works vid="фото"] — фото подтягиваются сами.',
                        ['pages/photos.php'], ['mem_cat_photos']],
        'faq'       => ['Вопрос–ответ', 'Все вопросы сайта на одной странице с поиском [mem_faq].',
                        ['content/faq.php'], ['mem_faq']],
        'guide'     => ['Полезно знать', 'Статьи-карточки с поиском [mem_guide], поле «Полезно знать» у страниц.',
                        ['content/guide.php'], ['mem_guide']],
        'sq'        => ['Статистика поиска', '«Что ищут на сайте» и автоматические «Часто ищут».',
                        ['content/search-stats.php'], []],
        'places'    => ['Залы для поминок и кладбища', 'Блоки [mem_restaurants] и [mem_cemeteries], их настройки.',
                        ['places/places.php'], ['mem_restaurants', 'mem_cemeteries']],
        'patterns'  => ['Готовые блоки в редакторе', 'Шаблоны блоков «Мемориал» во вставке блоков редактора.',
                        ['content/patterns.php'], []],
    ];
}

/** Выключенные файлы: ['файл' => ключ функции] */
function mem_core_disabled_files() {
    $on = get_option('mem_modules');
    if (!is_array($on)) return [];
    $off = [];
    foreach (mem_core_optional() as $k => $m) if (isset($on[$k]) && !$on[$k]) foreach ($m[2] as $f) $off[$f] = $k;
    return $off;
}

/*
 * Модули. Порядок важен только там, где один модуль пользуется функциями другого при подключении —
 * поэтому сначала основа (core), затем разделы.
 */
$mem_core_modules = [
    // основа: помощники, рамка настроек «Мемориал → …», контакты и главная
    'core/helpers.php',
    'seo/schema.php',
    'core/settings.php',
    'core/data.php',
    // каталог и страницы
    'catalog/catalog.php',
    'pages/pages.php',
    'catalog/catalog-front.php',
    'catalog/product-pages.php',
    // цены
    'prices/packages.php',
    'prices/prices.php',
    // каталог: админка
    'catalog/catalog-admin.php',
    'catalog/catalog-types.php',
    'catalog/catalog-manager.php',
    'catalog/catalog-summary.php',
    'catalog/catalog-types-admin.php',
    // «Наши работы»
    'works/works.php',
    'works/works-manager.php',
    // разовые переносы данных
    'catalog/catalog-import.php',
    'catalog/catalog-migrate.php',
    // SEO, аналитика, заявки
    'seo/seo.php',
    'seo/seo-plugins.php',
    'seo/metrika.php',
    'legal/legal.php',
    'leads/callback.php',
    // блоки для текста страниц
    'content/content.php',
    'content/search.php',
    'content/search-stats.php',
    // отзывы
    'reviews/logos.php',
    'reviews/reviews.php',
    'reviews/rewio.php',
    'reviews/bookreviews.php',
    // ещё блоки и фото
    'content/faq.php',
    'content/guide.php',
    'content/services.php',
    'pages/media.php',
    'content/patterns.php',
    'pages/cards.php',
    'pages/photos.php',
    'places/places.php',
    // справка в админке
    'admin/help.php',
    // скорость
    'speed/speed.php',
    'speed/page-cache.php',
    'speed/shortcode-cache.php',
    'speed/minify.php',
    'speed/relative-urls.php',
    'speed/auto-minify.php',
    'speed/images.php',
    // стили и скрипты блоков на сайте
    'core/assets.php',
];
// функции, которые можно выключить в «Мемориал → Функции» (core/modules.php): выключенные файлы не подключаются
$mem_core_off = mem_core_disabled_files();
foreach ($mem_core_modules as $m) if (!isset($mem_core_off[$m])) require MEM_CORE_DIR . '/' . $m;
require MEM_CORE_DIR . '/core/modules.php';
unset($mem_core_modules, $mem_core_off, $m);

/* Иконки рисует тема (mem_icon). Тема другая и своих иконок нет — запасной набор плагина */
add_action('after_setup_theme', function () {
    if (!function_exists('mem_icon')) require MEM_CORE_DIR . '/core/icons-fallback.php';
}, 0);

/* Включение: адреса товаров/работ заработают после пересборки ссылок (на следующем запросе, когда типы записей уже есть) */
register_activation_hook(__FILE__, function () {
    update_option('mem_core_flush', 1, false);
});
add_action('init', function () {
    if (get_option('mem_core_flush')) { delete_option('mem_core_flush'); flush_rewrite_rules(); }
}, 99);

/* Выключение: убираем расписание, свои правила .htaccess и кэш */
register_deactivation_hook(__FILE__, function () {
    foreach (['mem_rewio_cron', 'mem_rewio_cron_once', 'mem_logos_cron'] as $h) wp_clear_scheduled_hook($h);
    if (function_exists('mem_htaccess_sync')) mem_htaccess_sync(false);
    if (function_exists('mem_cache_purge')) mem_cache_purge();
});

/* После обновления плагина: один раз обновить отзывы Rewio (фото к отзывам скачаются на свой сайт) и очистить кэш */
add_action('admin_init', function () {
    if (get_option('mem_core_ver') === MEM_CORE_VER) return;
    update_option('mem_core_ver', MEM_CORE_VER, false);
    if (has_action('mem_rewio_cron_once') && !wp_next_scheduled('mem_rewio_cron_once')) wp_schedule_single_event(time() + 30, 'mem_rewio_cron_once');
    if (function_exists('mem_cache_purge')) mem_cache_purge(true);
});
