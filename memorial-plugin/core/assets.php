<?php
/**
 * Styles and scripts of the plugin’s blocks on the site.
 *
 * Files:
 *   assets/css/blocks.css — look of the blocks; colours and fonts are the presets of the theme (theme.json)
 *                           with fallbacks: var(--wp--preset--color--primary, #5e7052) and so on;
 *   assets/js/view/blocks.js — behaviour of the blocks on the Interactivity API (script module
 *                           “memorial-plugin-view”; the cemeteries block has its own, view/cemeteries.js).
 *
 * Loaded only where they are needed: they are the style and view script of every block of the plugin
 * (WordPress adds them when such a block is on the page) and of the old [mem_…] shortcodes while they work.
 * The same styles are in the editor canvas. A theme without some of the presets the plugin uses gets them
 * mapped to its own colours (memorial_plugin_theme_bridge_css), so the blocks follow any block theme.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the handles (the files are enqueued by the blocks themselves).
 */
function memorial_plugin_register_front_assets() {
	if ( wp_style_is( 'memorial-plugin', 'registered' ) ) {
		return;
	}
	$css  = memorial_plugin_asset( '/assets/css/blocks.css' );
	$deps = array();
	// After the global styles and the theme’s styles, so that the order of the rules stays as it was.
	foreach ( array( 'global-styles', 'memorial-theme', 'memorial-theme-content' ) as $theme_handle ) {
		if ( wp_style_is( $theme_handle, 'registered' ) ) {
			$deps[] = $theme_handle;
		}
	}
	wp_register_style( 'memorial-plugin', MEMORIAL_PLUGIN_URL . $css, $deps, (string) filemtime( MEMORIAL_PLUGIN_DIR . $css ) );
	$bridge = memorial_plugin_theme_bridge_css();
	if ( '' !== $bridge ) {
		wp_add_inline_style( 'memorial-plugin', $bridge );
	}
}

/**
 * Script module of the blocks (Interactivity API).
 */
function memorial_plugin_register_view_module() {
	$js = '/assets/js/view/blocks.js';
	wp_register_script_module( 'memorial-plugin-view', MEMORIAL_PLUGIN_URL . $js, array( '@wordpress/interactivity' ), (string) filemtime( MEMORIAL_PLUGIN_DIR . $js ) );
}
add_action( 'init', 'memorial_plugin_register_view_module', 5 );

/**
 * Plural forms for the view module: one text per category of Intl.PluralRules, chosen with _n().
 *
 * @param callable $form Function of a number that returns its form (an _n() call).
 * @return array<string,string>
 */
function memorial_plugin_view_plural( $form ) {
	$out = array();
	foreach ( array(
		'zero'  => 0,
		'one'   => 1,
		'two'   => 2,
		'few'   => 3,
		'many'  => 5,
		'other' => 11,
	) as $category => $n ) {
		$out[ $category ] = $form( $n );
	}
	return $out;
}

/**
 * Texts of the view module (assets/js/view/blocks.js), translated here: script modules get them
 * through the Interactivity API config, getConfig( 'memorial-plugin' ).
 *
 * @return array<string,mixed>
 */
function memorial_plugin_view_i18n() {
	return array(
		/* translators: %d: number of hidden topics */
		'moreTopics'           => __( 'More topics: %d', 'memorial-plugin' ),
		/* translators: %d: number of questions */
		'questionsWithAnswers' => memorial_plugin_view_plural( fn( $n ) => _n( '%d question with an answer', '%d questions with answers', $n, 'memorial-plugin' ) ),
		'startTyping'          => __( 'Start typing — we will suggest.', 'memorial-plugin' ),
		/* translators: %d: number of questions */
		'questions'            => memorial_plugin_view_plural( fn( $n ) => _n( '%d question', '%d questions', $n, 'memorial-plugin' ) ),
		/* translators: 1: search query, 2: “5 questions” */
		'foundBelow'           => __( 'For “%1$s” — %2$s below.', 'memorial-plugin' ),
		'showAll'              => __( 'Show all', 'memorial-plugin' ),
		/* translators: %d: number of articles */
		'articles'             => memorial_plugin_view_plural( fn( $n ) => _n( '%d article', '%d articles', $n, 'memorial-plugin' ) ),
		/* translators: %d: number of answers */
		'answersElsewhere'     => memorial_plugin_view_plural( fn( $n ) => _n( '%d answer on other pages', '%d answers on other pages', $n, 'memorial-plugin' ) ),
		'and'                  => __( 'and', 'memorial-plugin' ),
		/* translators: 1: search query, 2: what was found */
		'guideFound'           => __( 'For “%1$s”: %2$s. Click a suggestion to open the right place.', 'memorial-plugin' ),
		'collapse'             => __( 'Collapse', 'memorial-plugin' ),
		'readMore'             => __( 'Read more', 'memorial-plugin' ),
		'newestFirst'          => __( 'Newest first — click for the oldest first', 'memorial-plugin' ),
		'sortByDate'           => __( 'Sort by date', 'memorial-plugin' ),
		'highestFirst'         => __( 'Highest ratings first — click for the reverse', 'memorial-plugin' ),
		'sortByRating'         => __( 'Sort by rating', 'memorial-plugin' ),
		/* translators: %s: review site */
		'reviewsOf'            => __( 'Reviews: %s', 'memorial-plugin' ),
		'showAllLower'         => __( 'show all', 'memorial-plugin' ),
		'scrollLeft'           => __( 'Scroll left', 'memorial-plugin' ),
		'scrollRight'          => __( 'Scroll right', 'memorial-plugin' ),
		/* translators: %d: how many more */
		'showMore'             => __( 'Show %d more', 'memorial-plugin' ),
		/* translators: 1: how many more, 2: how many are left */
		'showMoreOf'           => __( 'Show %1$d more of %2$d', 'memorial-plugin' ),
	);
}
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( function_exists( 'wp_interactivity_config' ) ) {
			wp_interactivity_config(
				'memorial-plugin',
				array(
					'locale' => str_replace( '_', '-', determine_locale() ),
					'i18n'   => memorial_plugin_view_i18n(),
				)
			);
		}
	}
);
add_action( 'wp_enqueue_scripts', 'memorial_plugin_register_front_assets', 21 );

/**
 * Behaviour of the plugin markup on the site (assets/js/front.js): the “Call me back” window and the
 * phone links are on every page (header, footer, floating blocks), so the script is loaded everywhere.
 */
function memorial_plugin_front_script() {
	$js = memorial_plugin_asset( '/assets/js/front.js' );
	wp_enqueue_script(
		'memorial-plugin-front',
		MEMORIAL_PLUGIN_URL . $js,
		array(),
		(string) filemtime( MEMORIAL_PLUGIN_DIR . $js ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
	wp_add_inline_script( 'memorial-plugin-front', 'window.memorialPluginFrontI18n = ' . wp_json_encode( memorial_plugin_front_i18n() ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'memorial_plugin_front_script', 21 );

/**
 * Texts of assets/js/front.js (the script has no dependency on wp-i18n).
 *
 * @return array<string,string>
 */
function memorial_plugin_front_i18n() {
	return array(
		'fullNumber'   => __( 'Enter the full number: +7 and 10 digits.', 'memorial-plugin' ),
		'consent'      => __( 'Tick the consent to the data processing.', 'memorial-plugin' ),
		'notSent'      => __( 'Could not send.', 'memorial-plugin' ),
		'callUs'       => __( 'Please call us.', 'memorial-plugin' ),
		'maxFailed'    => __( 'Could not open MAX. Leave your number — we will call you back.', 'memorial-plugin' ),
		'maxBack'      => __( 'Could not write in MAX? Leave your number — we will call you back.', 'memorial-plugin' ),
		'reviewsFrame' => __( 'Reviews on Yandex Maps', 'memorial-plugin' ),
		'mapFrame'     => __( 'Directions map', 'memorial-plugin' ),
		'scrollLeft'   => __( 'Scroll left', 'memorial-plugin' ),
		'scrollRight'  => __( 'Scroll right', 'memorial-plugin' ),
		/* translators: 1: number of the photo, 2: number of photos */
		'photoOf'      => __( 'photo %1$d of %2$d', 'memorial-plugin' ),
	);
}
add_action(
	'enqueue_block_assets',
	static function () {
		// In the editor; on the site the files are registered after the theme’s (wp_enqueue_scripts, 21).
		if ( is_admin() ) {
			memorial_plugin_register_front_assets();
		}
	},
	5
);

/**
 * Every block of the plugin carries the style and the view script.
 *
 * @param array  $args Block type arguments.
 * @param string $name Block name.
 * @return array
 */
function memorial_plugin_block_assets_args( $args, $name ) {
	if ( ! str_starts_with( $name, 'memorial-plugin/' ) ) {
		return $args;
	}
	if ( apply_filters( 'memorial_plugin_blocks_css', true ) ) {
		$args['style_handles'] = array_values( array_unique( array_merge( (array) ( $args['style_handles'] ?? array() ), array( 'memorial-plugin' ) ) ) );
	}
	$args['view_script_module_ids'] = array_values( array_unique( array_merge( (array) ( $args['view_script_module_ids'] ?? array() ), array( 'memorial-plugin-view' ) ) ) );
	return $args;
}
add_filter( 'register_block_type_args', 'memorial_plugin_block_assets_args', 10, 2 );

/**
 * Old shortcodes (until 3.0.0): the files are added when a shortcode of the plugin is printed.
 *
 * @param string $output Output of the shortcode.
 * @param string $tag    Shortcode.
 * @return string
 */
function memorial_plugin_shortcode_assets( $output, $tag ) {
	if ( str_starts_with( (string) $tag, 'mem_' ) && ! is_admin() ) {
		if ( apply_filters( 'memorial_plugin_blocks_css', true ) ) {
			wp_enqueue_style( 'memorial-plugin' );
		}
		wp_enqueue_script_module( 'memorial-plugin-view' );
	}
	return $output;
}
add_filter( 'do_shortcode_tag', 'memorial_plugin_shortcode_assets', 10, 2 );

/**
 * Presets the plugin’s styles use, and what to take instead when the theme has no such preset.
 *
 * @return array<string,string[]> Preset slug => slugs to try instead, in order.
 */
function memorial_plugin_preset_alternatives() {
	return array(
		'base'       => array( 'background', 'white', 'base-2' ),
		'base-2'     => array( 'tertiary', 'background-secondary', 'base', 'white' ),
		'surface'    => array( 'base-2', 'tertiary', 'base' ),
		'contrast'   => array( 'foreground', 'text', 'black' ),
		'contrast-2' => array( 'contrast', 'foreground', 'text', 'black' ),
		'primary'    => array( 'accent', 'vivid-cyan-blue', 'contrast', 'foreground' ),
		'secondary'  => array( 'primary', 'accent', 'contrast', 'foreground' ),
		'tertiary'   => array( 'secondary', 'primary', 'accent' ),
		'accent'     => array( 'secondary', 'primary' ),
		'on-primary' => array( 'base', 'background', 'white' ),
		'line'       => array( 'base-2', 'tertiary', 'cyan-bluish-gray' ),
	);
}

/**
 * CSS that maps the presets missing in the theme to the theme’s own colours. Empty for the Memorial theme
 * (it has all of them). Cached until the theme or its global styles change.
 *
 * @return string
 */
function memorial_plugin_theme_bridge_css() {
	$palette = wp_get_global_settings( array( 'color', 'palette' ) );
	$slugs   = array();
	foreach ( (array) $palette as $origin ) {
		foreach ( (array) $origin as $color ) {
			if ( isset( $color['slug'] ) ) {
				$slugs[] = (string) $color['slug'];
			}
		}
	}
	$key    = 'memorial_plugin_bridge_' . md5( get_stylesheet() . '|' . implode( ',', $slugs ) );
	$cached = get_transient( $key );
	if ( is_string( $cached ) ) {
		return $cached;
	}
	$vars = array();
	foreach ( memorial_plugin_preset_alternatives() as $slug => $alternatives ) {
		if ( in_array( $slug, $slugs, true ) ) {
			continue;
		}
		foreach ( $alternatives as $alt ) {
			if ( in_array( $alt, $slugs, true ) ) {
				$vars[] = '--wp--preset--color--' . sanitize_key( $slug ) . ':var(--wp--preset--color--' . sanitize_key( $alt ) . ')';
				break;
			}
		}
	}
	$css = $vars ? ':root{' . implode( ';', $vars ) . '}' : '';
	set_transient( $key, $css, DAY_IN_SECONDS );
	return $css;
}

/**
 * The same styles in the editor canvas, so that the blocks look as on the site.
 */
function memorial_plugin_editor_block_styles() {
	if ( is_admin() && apply_filters( 'memorial_plugin_blocks_css', true ) ) {
		wp_enqueue_style( 'memorial-plugin' );
	}
}
add_action( 'enqueue_block_assets', 'memorial_plugin_editor_block_styles', 20 );

/**
 * Translations of the WordPress packages bundled into the admin screens (@wordpress/dataviews,
 *
 * @wordpress/admin-ui): their strings are in the “default” text domain, so the screens take them from
 * WordPress’s own translation of the editor scripts, which bundle the same packages
 * (“Search”, “No results”, “Actions”, “Add filter”…).
 */
function memorial_plugin_vendor_i18n() {
	if ( 'en_US' === determine_locale() ) {
		return;
	}
	// Every app of the plugin screens; the screens enqueue them while the page is rendered, so this runs
	// right before the footer scripts are printed.
	$handles = array_values(
		array_filter(
			wp_scripts()->queue,
			static fn( $handle ) => 0 === strpos( $handle, 'memorial-plugin-' ) && in_array( 'wp-i18n', (array) ( wp_scripts()->registered[ $handle ]->deps ?? array() ), true )
		)
	);
	if ( ! $handles ) {
		return;
	}
	$messages = array();
	foreach ( array( 'wp-editor', 'wp-edit-site', 'wp-dataviews', 'wp-media-utils', 'wp-components' ) as $core ) {
		$json = wp_script_is( $core, 'registered' ) ? load_script_textdomain( $core, 'default' ) : false;
		$data = $json ? json_decode( $json, true ) : null;
		if ( ! empty( $data['locale_data']['messages'] ) ) {
			$messages += $data['locale_data']['messages'];
		}
	}
	if ( ! $messages ) {
		return;
	}
	$js = 'wp.i18n.setLocaleData(' . wp_json_encode( $messages ) . ',"default");';
	// One screen has one app; the locale data of wp.i18n is shared, so once is enough.
	wp_add_inline_script( reset( $handles ), $js, 'before' );
}
add_action( 'admin_print_footer_scripts', 'memorial_plugin_vendor_i18n', 1 );
