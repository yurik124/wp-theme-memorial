<?php
/**
 * Тема «Memorial Modern» 5 «Светлая память» — блоковая тема: дизайн сайта.
 *
 * Шаблоны — templates/*.html, части — parts/*.html (редактируются во «Внешний вид → Редактор»),
 * цвета, шрифты и размеры — theme.json и варианты стилей styles/*.json.
 * Данные сайта (телефоны, адреса, каталог, цены) выводят блоки плагина Memorial Plugin;
 * тема даёт им лавровую графику через фильтр (inc/laurel.php); иконки — из реестра иконок WordPress (плагин).
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;

define( 'MEMORIAL_THEME_VER', '5.0.2' );

/*
 * Translations of the theme (languages/memorial-theme-<locale>.mo / .l10n.php) — on init, before anything
 * translatable: by then the current user is known, so the editor (REST requests with _locale=user) gets the
 * language of the user, not of the site. Nothing of the theme is translated earlier (the menu locations
 * are registered on init too).
 */
add_action(
	'init',
	function () {
		load_theme_textdomain( 'memorial-theme', get_template_directory() . '/languages' );
		register_nav_menus(
			array(
				'primary' => __( 'Main menu', 'memorial-theme' ),
				'footer'  => __( 'Footer menu', 'memorial-theme' ),
			)
		);
	},
	0
);

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'post-thumbnails' );
		add_theme_support(
			'custom-logo',
			array(
				'height'     => 60,
				'width'      => 200,
				'flex-width' => true,
			)
		);
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		// В редакторе страница выглядит как на сайте: общие стили темы (шапка, подвал, разделы) и оформление текста.
		add_editor_style( array( 'assets/css/main.css', 'assets/css/content.css', 'assets/css/editor.css' ) );
	}
);

/** Сжатая копия файла (.min.css / .min.js), если она есть и не старше исходника; иначе — сам файл.
	Копии собирает tools/minify.php.
 *
 * @param string $path Path.
 */
function memorial_theme_asset( $path ) {
	$dir = get_template_directory();
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		return $path;
	}
	$min = preg_replace( '/\.(css|js)$/', '.min.$1', $path );
	return is_file( $dir . $min ) && filemtime( $dir . $min ) + 300 >= filemtime( $dir . $path ) ? $min : $path;
}

/* Стили и скрипты темы: версия = время изменения файла, кэш сбрасывается сам */
add_action(
	'wp_enqueue_scripts',
	function () {
		$dir = get_template_directory();
		$uri = get_template_directory_uri();
		$css = memorial_theme_asset( '/assets/css/main.css' );
		$js  = memorial_theme_asset( '/assets/js/main.js' );
		wp_enqueue_style( 'memorial-theme', $uri . $css, array( 'global-styles' ), filemtime( $dir . $css ) );
		$f = memorial_theme_asset( '/assets/css/content.css' );
		wp_enqueue_style( 'memorial-theme-content', $uri . $f, array( 'memorial-theme' ), filemtime( $dir . $f ) );
		wp_enqueue_script(
			'memorial-theme',
			$uri . $js,
			array(),
			filemtime( $dir . $js ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_add_inline_script( 'memorial-theme', 'window.memorialThemeI18n = ' . wp_json_encode( memorial_theme_js_i18n() ) . ';', 'before' );
	},
	20
);

/*
 * Styles of core blocks (card group, media and text, card gallery): each file loads only on pages that
 * have that block — wp_enqueue_block_style(). They come after the theme's stylesheets, as they did inside
 * content.css. The price table, the steps and checks lists and the details stay in content.css: the plugin
 * prints their classes without the core blocks (cremation prices, FAQ, sub-sections list).
 */
add_action(
	'init',
	function () {
		$dir = get_template_directory();
		$uri = get_template_directory_uri();
		foreach ( array( 'group', 'media-text', 'gallery' ) as $block ) {
			$css = memorial_theme_asset( '/assets/css/blocks/core-' . $block . '.css' );
			if ( ! is_file( $dir . $css ) ) {
				continue;
			}
			wp_enqueue_block_style(
				'core/' . $block,
				array(
					'handle' => 'memorial-theme-' . $block,
					'src'    => $uri . $css,
					'path'   => $dir . $css,
					'deps'   => is_admin() ? array() : array( 'memorial-theme-content' ),
					'ver'    => (string) filemtime( $dir . $css ),
				)
			);
		}
	}
);

/**
 * Texts of assets/js/main.js, translated here (the script has no dependency on wp-i18n).
 *
 * @return array<string,string>
 */
function memorial_theme_js_i18n() {
	return array(
		/* translators: %s: menu item */
		'expand'  => __( 'Expand: %s', 'memorial-theme' ),
		'submenu' => __( 'submenu', 'memorial-theme' ),
	);
}

/* Предзагрузка: шрифт заголовков (без «прыжка» на первом экране) и фото первого экрана главной (LCP) */
add_action(
	'wp_head',
	function () {
		$uri  = get_template_directory_uri();
		$head = wp_get_global_styles( array( 'elements', 'heading', 'typography', 'fontFamily' ) );
		$head = is_string( $head ) ? $head : '';
		$file = str_contains( $head, 'font-family--lora' ) ? 'lora-500' : ( ( '' === $head || str_contains( $head, 'cormorant' ) ) ? 'cormorant-garamond-500' : '' );
		if ( $file ) {
			echo '<link rel="preload" href="' . esc_url( $uri . '/assets/fonts/' . $file . '.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
		$img = is_front_page() && function_exists( 'memorial_plugin_home' ) && function_exists( 'memorial_plugin_hero_srcs' ) ? ( memorial_plugin_home( 'hero' )['img'] ?? '' ) : '';
		if ( $img ) {
			[$d, $m] = memorial_plugin_hero_srcs( $img );
			echo '<link rel="preload" as="image" href="' . esc_url( $m ) . '" media="(max-width:700px)" fetchpriority="high">' . "\n"
				. '<link rel="preload" as="image" href="' . esc_url( $d ) . '" media="(min-width:701px)" fetchpriority="high">' . "\n";
		}
	},
	2
);

/* Цвет панели браузера на телефоне и запрет автоссылок на номера (номера — ссылки tel: сами) */
add_action(
	'wp_head',
	function () {
		echo '<meta name="format-detection" content="telephone=no">' . "\n";
		echo '<meta name="theme-color" content="' . esc_attr( memorial_theme_theme_color() ) . '">' . "\n";
	},
	1
);

/* Чистка head */
add_action(
	'init',
	function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		add_filter( 'emoji_svg_url', '__return_false' );
		remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
	}
);

/* «Логотип сайта» без своего логотипа: значок сайта, иначе лавровый знак с буквой (как в теме 4) */
add_filter(
	'render_block_core/site-logo',
	function ( $html, $block ) {
		if ( trim( $html ) !== '' && ! memorial_theme_logo_is_fallback() ) {
			return $html;
		}
		$size  = (int) ( $block['attrs']['width'] ?? 44 );
		$class = trim( 'wp-block-site-logo ' . ( $block['attrs']['className'] ?? '' ) );
		return '<div class="' . esc_attr( $class ) . '"><a href="' . esc_url( home_url( '/' ) ) . '" class="custom-logo-link" rel="home" aria-label="'
		. esc_attr( get_bloginfo( 'name' ) . __( ' — to the home page', 'memorial-theme' ) ) . '">' . memorial_theme_logo_mark( $size ) . '</a></div>';
	},
	10,
	2
);

/* Паттерны темы — категория «Мемориал» */
add_action(
	'init',
	function () {
		register_block_pattern_category( 'memorial-theme', array( 'label' => __( 'Memorial: text layouts', 'memorial-theme' ) ) );
	}
);

require get_template_directory() . '/inc/design.php';
require get_template_directory() . '/inc/design-migrate.php';
require get_template_directory() . '/inc/laurel.php';
require get_template_directory() . '/inc/logo-sync.php';
require get_template_directory() . '/inc/navigation-migrate.php';
require get_template_directory() . '/inc/tagline-migrate.php';

/**
 * Script of the header (assets/js/header-fit.js) right after the Header template part: it measures the menu
 * before painting. Printed with the WordPress inline script API instead of a <script> in the template.
 *
 * @param string $html  Rendered template part.
 * @param array  $block Parsed block.
 * @return string
 */
function memorial_theme_header_fit_script( $html, $block ) {
	static $done = false;
	if ( $done || 'header' !== ( $block['attrs']['slug'] ?? '' ) ) {
		return $html;
	}
	$done = true;
	$file = get_theme_file_path( 'assets/js/header-fit.js' );
	$js   = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file of the theme.
	return '' === $js ? $html : $html . wp_get_inline_script_tag( $js );
}
add_filter( 'render_block_core/template-part', 'memorial_theme_header_fit_script', 10, 2 );
