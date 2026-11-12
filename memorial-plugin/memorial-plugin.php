<?php
/**
 * Plugin Name: Memorial Plugin
 * Description: Site features for Memorial funeral services: catalog, prices, reviews, works, leads, settings, SEO, analytics. Design lives in the Memorial Modern theme.
 * Version: 2.0.0
 * Requires at least: 7.1
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * Author: Memorial
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: memorial-plugin
 * Domain Path: /languages
 *
 * Где что лежит — см. README.md рядом с этим файлом.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/* Переход со старого Memorial Core: пока он активен, новый плагин ничего не загружает (core/switchover.php) */
require __DIR__ . '/core/switchover.php';
register_activation_hook( __FILE__, 'memorial_plugin_switchover' );
if ( memorial_plugin_legacy_active() ) {
	add_action( 'admin_notices', 'memorial_plugin_legacy_notice' );
	return;
}
add_action( 'wp_loaded', 'memorial_plugin_after_switch' );

define( 'MEMORIAL_PLUGIN_VER', '2.0.0' );
define( 'MEMORIAL_PLUGIN_FILE', __FILE__ );
define( 'MEMORIAL_PLUGIN_DIR', __DIR__ );                          // без «/» в конце: MEMORIAL_PLUGIN_DIR . '/assets/…'.
define( 'MEMORIAL_PLUGIN_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );

/* Translations bundled with the plugin (languages/memorial-plugin-<locale>.mo / .l10n.php, JSON for the scripts) */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'memorial-plugin', false, dirname( plugin_basename( MEMORIAL_PLUGIN_FILE ) ) . '/languages' );
	},
	0
);

/** Файл плагина по относительному пути
 *
 * @param mixed $rel Rel.
 */
function memorial_plugin_path( $rel ) {
	return MEMORIAL_PLUGIN_DIR . '/' . ltrim( $rel, '/' );
}

/** Сжатая копия (.min.css / .min.js), если она есть и не старше исходника (копии собирает tools/minify.php)
 *
 * @param string $path Path.
 */
function memorial_plugin_asset( $path ) {
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		return $path;
	}
	$min = preg_replace( '/\.(css|js)$/', '.min.$1', $path );
	$src = MEMORIAL_PLUGIN_DIR . $path;
	$cpy = MEMORIAL_PLUGIN_DIR . $min;
	return is_file( $cpy ) && filemtime( $cpy ) + 300 >= filemtime( $src ) ? $min : $path;
}

/** Шаблон блока: сначала из темы (template-parts/{name}.php — дизайн можно поменять в теме), иначе — из плагина (templates/)
 *
 * @param string $name Name.
 * @param array  $args Args.
 */
function memorial_plugin_part( $name, $args = array() ) {
	$f = locate_template( 'template-parts/' . $name . '.php' );
	if ( ! $f ) {
		$f = memorial_plugin_path( 'templates/' . $name . '.php' );
	}
	if ( is_file( $f ) ) {
		load_template( $f, false, $args );
	}
}

/**
 * Отключаемые функции: ключ => [название, описание, файлы, шорткоды (на страницах вместо них — пусто), зависит от].
 * Остальное (каталог, цены, контакты, документы, SEO-основа) — основа сайта, выключить нельзя.
 */
function memorial_plugin_optional() {
	return array(
		'callback' => array( 'callback', '', array( 'leads/callback.php' ), array() ),
		'metrika'  => array( 'metrika', '', array( 'seo/metrika.php' ), array() ),
		'seoplug'  => array( 'seoplug', '', array( 'seo/seo-plugins.php' ), array() ),
		'rewio'    => array( 'rewio', '', array( 'reviews/rewio.php' ), array( 'mem_rewio' ) ),
		'book'     => array( 'book', '', array( 'reviews/bookreviews.php' ), array( 'mem_bookreviews' ) ),
		'works'    => array( 'works', '', array( 'works/works.php', 'works/works-manager.php' ), array( 'mem_works' ) ),
		'photos'   => array( 'photos', '', array( 'pages/photos.php' ), array( 'mem_cat_photos' ) ),
		'faq'      => array( 'faq', '', array( 'content/faq.php' ), array( 'mem_faq' ) ),
		'guide'    => array( 'guide', '', array( 'content/guide.php' ), array( 'mem_guide' ) ),
		'sq'       => array( 'sq', '', array( 'content/search-stats.php' ), array() ),
		'places'   => array( 'places', '', array( 'places/places.php' ), array( 'mem_restaurants', 'mem_cemeteries' ) ),
		'patterns' => array( 'patterns', '', array( 'content/patterns.php' ), array() ),
	);
}

/**
 * Names and descriptions of the optional features (for «Memorial → Features»; translated, so only after init).
 *
 * @return array<string,array{0:string,1:string}>
 */
function memorial_plugin_optional_labels() {
	return array(
		'callback' => array( __( '“Call me back” request', 'memorial-plugin' ), __( 'The window with the call-back form and notifications about requests. Off — the “Call me back” buttons simply call the phone.', 'memorial-plugin' ) ),
		'metrika'  => array( __( 'Yandex Metrica and the cookie notice', 'memorial-plugin' ), __( 'The counter, goals, cleaning the address of UTM tags, the cookie banner.', 'memorial-plugin' ) ),
		'seoplug'  => array( __( 'Compatibility with SEO plugins', 'memorial-plugin' ), __( '[mem_…] shortcodes in the titles and descriptions of AIOSEO, Yoast, Rank Math, SEOPress.', 'memorial-plugin' ) ),
		'rewio'    => array( __( 'Reviews from Yandex Maps (Rewio)', 'memorial-plugin' ), __( 'Loading reviews on schedule, the Rewio reviews block.', 'memorial-plugin' ) ),
		'book'     => array( __( 'Guest book', 'memorial-plugin' ), __( 'Reviews left on the site, the guest book block.', 'memorial-plugin' ) ),
		'works'    => array( __( '“Our works”', 'memorial-plugin' ), __( 'The section of works with photos and prices, the works manager, the works blocks.', 'memorial-plugin' ) ),
		'photos'   => array( __( 'Photo gallery: photos of products and works', 'memorial-plugin' ), __( 'Photo blocks of products and works — the photos are picked up automatically.', 'memorial-plugin' ) ),
		'faq'      => array( __( 'Questions and answers', 'memorial-plugin' ), __( 'All questions of the site on one page with a search.', 'memorial-plugin' ) ),
		'guide'    => array( __( 'Good to know', 'memorial-plugin' ), __( 'Article cards with a search, the “Good to know” field of pages.', 'memorial-plugin' ) ),
		'sq'       => array( __( 'Search statistics', 'memorial-plugin' ), __( '“What people search for on the site” and the automatic “Frequent searches”.', 'memorial-plugin' ) ),
		'places'   => array( __( 'Memorial meal halls and cemeteries', 'memorial-plugin' ), __( 'The halls and cemeteries blocks and their settings.', 'memorial-plugin' ) ),
		'patterns' => array( __( 'Ready-made blocks in the editor', 'memorial-plugin' ), __( '“Memorial” patterns in the block inserter of the editor.', 'memorial-plugin' ) ),
	);
}

/** Выключенные файлы: ['файл' => ключ функции] */
function memorial_plugin_disabled_files() {
	$on = get_option( 'mem_modules' );
	if ( ! is_array( $on ) ) {
		return array();
	}
	$off = array();
	foreach ( memorial_plugin_optional() as $k => $m ) {
		if ( isset( $on[ $k ] ) && ! $on[ $k ] ) {
			foreach ( $m[2] as $f ) {
						$off[ $f ] = $k;
			}
		}
	}
	return $off;
}

/*
 * Модули. Порядок важен только там, где один модуль пользуется функциями другого при подключении —
 * поэтому сначала основа (core), затем разделы.
 */
$memorial_plugin_modules = array(
	// основа: помощники, рамка настроек «Мемориал → …», контакты и главная.
	'core/helpers.php',
	'core/presentation.php',
	'seo/schema.php',
	'core/settings.php',
	'core/data.php',
	// каталог и страницы.
	'catalog/catalog.php',
	'pages/pages.php',
	'catalog/catalog-front.php',
	'catalog/product-pages.php',
	// цены.
	'prices/packages.php',
	'prices/prices.php',
	// каталог: админка.
	'catalog/catalog-admin.php',
	'catalog/catalog-types.php',
	'catalog/catalog-manager.php',
	'catalog/catalog-summary.php',
	'catalog/catalog-type-page.php',
	'catalog/catalog-types-admin.php',
	// «Наши работы»
	'works/works.php',
	'works/works-manager.php',
	// разовые переносы данных.
	'catalog/catalog-migrate.php',
	// SEO, аналитика, заявки.
	'seo/seo.php',
	'seo/seo-plugins.php',
	'seo/metrika.php',
	'legal/legal.php',
	'leads/callback.php',
	// блоки для текста страниц.
	'content/content.php',
	'content/search.php',
	'content/search-stats.php',
	// отзывы.
	'reviews/logos.php',
	'reviews/reviews.php',
	'reviews/rewio.php',
	'reviews/bookreviews.php',
	// ещё блоки и фото.
	'content/faq.php',
	'content/guide.php',
	'content/services.php',
	'pages/media.php',
	'content/patterns.php',
	'pages/cards.php',
	'pages/photos.php',
	'places/places.php',
	// справка в админке
	// уборка после удалённых модулей скорости (этап 3).
	'core/speed-removed.php',
	// стили и скрипты блоков на сайте.
	'core/assets.php',
);
// функции, которые можно выключить в «Мемориал → Функции» (core/modules.php): выключенные файлы не подключаются.
$memorial_plugin_off = memorial_plugin_disabled_files();
foreach ( $memorial_plugin_modules as $memorial_plugin_module ) {
	if ( ! isset( $memorial_plugin_off[ $memorial_plugin_module ] ) ) {
		require MEMORIAL_PLUGIN_DIR . '/' . $memorial_plugin_module;
	}
}
require MEMORIAL_PLUGIN_DIR . '/core/modules.php';
unset( $memorial_plugin_modules, $memorial_plugin_off, $memorial_plugin_module );

/* Классы плагина (includes/) — автозагрузка Composer classmap; vendor/ собирается при выгрузке (composer dump-autoload) */
if ( is_readable( MEMORIAL_PLUGIN_DIR . '/vendor/autoload.php' ) ) {
	require MEMORIAL_PLUGIN_DIR . '/vendor/autoload.php';
}

/* Иконки: memorial_plugin_icon() спрашивает тему (фильтр memorial_plugin_icon_html), без неё — запасной набор плагина */
require MEMORIAL_PLUGIN_DIR . '/core/icons-api.php';
require MEMORIAL_PLUGIN_DIR . '/core/icons-fallback.php';

/* Блоки-обёртки для блоковой темы (этап 2): шапка, подвал, главная, товар, каталог… */
require MEMORIAL_PLUGIN_DIR . '/core/shortcodes-compat.php';
require MEMORIAL_PLUGIN_DIR . '/blocks/block-meta.php';
require MEMORIAL_PLUGIN_DIR . '/blocks/render-batch.php';
require MEMORIAL_PLUGIN_DIR . '/blocks/legacy/legacy.php';
require MEMORIAL_PLUGIN_DIR . '/blocks/content/content-blocks.php';
require MEMORIAL_PLUGIN_DIR . '/blocks/content/inline-values.php';
require MEMORIAL_PLUGIN_DIR . '/seo/seo-registry.php';
require MEMORIAL_PLUGIN_DIR . '/prices/values.php';
require MEMORIAL_PLUGIN_DIR . '/core/page-cache.php';
require MEMORIAL_PLUGIN_DIR . '/admin/convert.php';
require MEMORIAL_PLUGIN_DIR . '/admin/settings-form.php';
require MEMORIAL_PLUGIN_DIR . '/admin/collections.php';
require MEMORIAL_PLUGIN_DIR . '/admin/editor-meta.php';
if ( function_exists( 'wp_register_ability' ) ) {
	require MEMORIAL_PLUGIN_DIR . '/admin/abilities.php'; // Abilities API (WordPress 6.9+).
}
require MEMORIAL_PLUGIN_DIR . '/admin/commands.php';
require MEMORIAL_PLUGIN_DIR . '/blocks/floating/floating.php';
require MEMORIAL_PLUGIN_DIR . '/blocks/home/home.php';
require MEMORIAL_PLUGIN_DIR . '/blocks/home/home-template.php';
require MEMORIAL_PLUGIN_DIR . '/blocks/home/pages-to-content.php';
require MEMORIAL_PLUGIN_DIR . '/core/cleanup.php';

/* Включение: адреса товаров/работ заработают после пересборки ссылок (на следующем запросе, когда типы записей уже есть) */
register_activation_hook(
	__FILE__,
	function () {
		update_option( 'mem_core_flush', 1, false );
	}
);
add_action(
	'init',
	function () {
		if ( get_option( 'mem_core_flush' ) ) {
			delete_option( 'mem_core_flush' );
			flush_rewrite_rules(); }
	},
	99
);

/* Выключение: убираем расписание */
register_deactivation_hook(
	__FILE__,
	function () {
		foreach ( array( 'mem_rewio_cron', 'mem_rewio_cron_once', 'mem_logos_cron', 'memorial_plugin_rewio_cron', 'memorial_plugin_rewio_cron_once', 'memorial_plugin_logos_cron' ) as $h ) {
			wp_clear_scheduled_hook( $h );
		}
	}
);

/* После обновления плагина: один раз обновить отзывы Rewio (фото к отзывам скачаются на свой сайт) */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'mem_core_ver' ) === MEMORIAL_PLUGIN_VER ) {
			return;
		}
		update_option( 'mem_core_ver', MEMORIAL_PLUGIN_VER, false );
		if ( has_action( 'memorial_plugin_rewio_cron_once' ) && ! wp_next_scheduled( 'memorial_plugin_rewio_cron_once' ) ) {
			wp_schedule_single_event( time() + 30, 'memorial_plugin_rewio_cron_once' );
		}
	}
);
