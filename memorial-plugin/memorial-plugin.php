<?php
/**
 * Plugin Name: Memorial Plugin
 * Description: Site features for Memorial funeral services: catalog, prices, reviews, works, leads, settings, SEO, analytics. Design lives in the Memorial Modern theme.
 * Version: 2.0.0-alpha.1
 * Requires at least: 7.1
 * Requires PHP: 8.1
 * Author: Memorial
 * Text Domain: memorial-plugin
 *
 * Где что лежит — см. README.md рядом с этим файлом.
 */
defined('ABSPATH') || exit;

/* Переход со старого Memorial Core: пока он активен, новый плагин ничего не загружает (core/switchover.php) */
require __DIR__ . '/core/switchover.php';
register_activation_hook(__FILE__, 'memorial_plugin_switchover');
if (memorial_plugin_legacy_active()) {
    add_action('admin_notices', 'memorial_plugin_legacy_notice');
    return;
}
add_action('wp_loaded', 'memorial_plugin_after_switch');

define('MEMORIAL_PLUGIN_VER', '2.0.0-alpha.1');
define('MEMORIAL_PLUGIN_FILE', __FILE__);
define('MEMORIAL_PLUGIN_DIR', __DIR__);                          // без «/» в конце: MEMORIAL_PLUGIN_DIR . '/assets/…'
define('MEMORIAL_PLUGIN_URL', untrailingslashit(plugins_url('', __FILE__)));

/** Файл плагина по относительному пути */
function memorial_plugin_path($rel) {
    return MEMORIAL_PLUGIN_DIR . '/' . ltrim($rel, '/');
}

/** Сжатая копия (.min.css / .min.js), если она есть и не старше исходника (копии собирает tools/minify.php) */
function memorial_plugin_asset($path) {
    if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) return $path;
    $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
    $src = MEMORIAL_PLUGIN_DIR . $path;
    $cpy = MEMORIAL_PLUGIN_DIR . $min;
    return is_file($cpy) && filemtime($cpy) + 300 >= filemtime($src) ? $min : $path;
}

/** Шаблон блока: сначала из темы (template-parts/{name}.php — дизайн можно поменять в теме), иначе — из плагина (templates/) */
function memorial_plugin_part($name, $args = []) {
    $f = locate_template('template-parts/' . $name . '.php');
    if (!$f) $f = memorial_plugin_path('templates/' . $name . '.php');
    if (is_file($f)) load_template($f, false, $args);
}

/**
 * Отключаемые функции: ключ => [название, описание, файлы, шорткоды (на страницах вместо них — пусто), зависит от].
 * Остальное (каталог, цены, контакты, документы, SEO-основа) — основа сайта, выключить нельзя.
 */
function memorial_plugin_optional() {
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
function memorial_plugin_disabled_files() {
    $on = get_option('mem_modules');
    if (!is_array($on)) return [];
    $off = [];
    foreach (memorial_plugin_optional() as $k => $m) if (isset($on[$k]) && !$on[$k]) foreach ($m[2] as $f) $off[$f] = $k;
    return $off;
}

/*
 * Модули. Порядок важен только там, где один модуль пользуется функциями другого при подключении —
 * поэтому сначала основа (core), затем разделы.
 */
$memorial_plugin_modules = [
    // основа: помощники, рамка настроек «Мемориал → …», контакты и главная
    'core/helpers.php',
    'core/presentation.php',
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
    // уборка после удалённых модулей скорости (этап 3)
    'core/speed-removed.php',
    // стили и скрипты блоков на сайте
    'core/assets.php',
];
// функции, которые можно выключить в «Мемориал → Функции» (core/modules.php): выключенные файлы не подключаются
$memorial_plugin_off = memorial_plugin_disabled_files();
foreach ($memorial_plugin_modules as $memorial_plugin_module) if (!isset($memorial_plugin_off[$memorial_plugin_module])) require MEMORIAL_PLUGIN_DIR . '/' . $memorial_plugin_module;
require MEMORIAL_PLUGIN_DIR . '/core/modules.php';
unset($memorial_plugin_modules, $memorial_plugin_off, $memorial_plugin_module);

/* Классы плагина (includes/) — автозагрузка Composer classmap; vendor/ собирается при выгрузке (composer dump-autoload) */
if (is_readable(MEMORIAL_PLUGIN_DIR . '/vendor/autoload.php')) require MEMORIAL_PLUGIN_DIR . '/vendor/autoload.php';

/* Проверка сборки DataViews / admin-ui — только при WP_DEBUG (этап 1, удаляется на этапе 4) */
if (is_admin() && defined('WP_DEBUG') && WP_DEBUG) require MEMORIAL_PLUGIN_DIR . '/admin/dataviews-check.php';

/* Иконки: memorial_plugin_icon() спрашивает тему (фильтр memorial_plugin_icon_html), без неё — запасной набор плагина */
require MEMORIAL_PLUGIN_DIR . '/core/icons-fallback.php';

/* Блоки-обёртки для блоковой темы (этап 2): шапка, подвал, главная, товар, каталог… */
require MEMORIAL_PLUGIN_DIR . '/blocks/legacy/legacy.php';

/* Включение: адреса товаров/работ заработают после пересборки ссылок (на следующем запросе, когда типы записей уже есть) */
register_activation_hook(__FILE__, function () {
    update_option('mem_core_flush', 1, false);
});
add_action('init', function () {
    if (get_option('mem_core_flush')) { delete_option('mem_core_flush'); flush_rewrite_rules(); }
}, 99);

/* Выключение: убираем расписание */
register_deactivation_hook(__FILE__, function () {
    foreach (['mem_rewio_cron', 'mem_rewio_cron_once', 'mem_logos_cron', 'memorial_plugin_rewio_cron', 'memorial_plugin_rewio_cron_once', 'memorial_plugin_logos_cron'] as $h) wp_clear_scheduled_hook($h);
});

/* После обновления плагина: один раз обновить отзывы Rewio (фото к отзывам скачаются на свой сайт) */
add_action('admin_init', function () {
    if (get_option('mem_core_ver') === MEMORIAL_PLUGIN_VER) return;
    update_option('mem_core_ver', MEMORIAL_PLUGIN_VER, false);
    if (has_action('memorial_plugin_rewio_cron_once') && !wp_next_scheduled('memorial_plugin_rewio_cron_once')) wp_schedule_single_event(time() + 30, 'memorial_plugin_rewio_cron_once');
});
