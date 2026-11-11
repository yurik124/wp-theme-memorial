<?php
/**
 * Styles and scripts of the plugin’s blocks on the site.
 *
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
add_action( 'wp_enqueue_scripts', 'memorial_plugin_register_front_assets', 21 );
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
