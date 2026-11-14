<?php
/**
 * Backward compatibility layer for the old [mem_…] shortcodes.
 *
 * The plugin is block-first: its content is the blocks of blocks/content and the inline values
 * (blocks/content/inline-values.php). The modules still define the output of each former shortcode with
 * add_shortcode(); this file takes those callbacks out of the shortcode registry right after they are added
 * and keeps them as the implementations of the blocks and inline values
 * (memorial_plugin_shortcode_impl()). In the content a shortcode keeps working until 3.0.0, but:
 *  - every use reports a deprecation (_deprecated_function(), visible with WP_DEBUG) that names the block
 *    which replaces it;
 *  - a post that still has shortcodes is converted from its editor (command “Convert old codes to blocks”),
 *    with a rollback (admin/convert.php). The conversion is never started automatically.
 *
 * New content written by the plugin itself (page drafts, legal documents, price replacement) is
 * converted to blocks before it is saved, so no new shortcodes appear.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Version since which the shortcodes are deprecated. */
const MEMORIAL_PLUGIN_SHORTCODES_DEPRECATED = '2.0.0';

/**
 * Implementations of the former shortcodes: tag => callback.
 *
 * @param string|null   $tag      Tag to register (with $callback) or null to read the list.
 * @param callable|null $callback Implementation.
 * @return array<string,callable>
 */
function memorial_plugin_shortcode_impls( $tag = null, $callback = null ) {
	static $impls = array();
	if ( null !== $tag && is_callable( $callback ) ) {
		$impls[ $tag ] = $callback;
	}
	return $impls;
}

/**
 * Implementation of a former shortcode (what the block or inline value renders).
 *
 * @param string $tag Shortcode tag, e.g. mem_catalog.
 * @return callable|null
 */
function memorial_plugin_shortcode_impl( $tag ) {
	return memorial_plugin_shortcode_impls()[ $tag ] ?? null;
}

/**
 * What replaces a shortcode, for the deprecation message.
 *
 * @param string $tag Shortcode tag.
 * @return string
 */
function memorial_plugin_shortcode_replacement( $tag ) {
	$blocks = function_exists( 'memorial_plugin_convert_block_tags' ) ? memorial_plugin_convert_block_tags() : array();
	if ( isset( $blocks[ $tag ] ) ) {
		return 'the block memorial-plugin/' . $blocks[ $tag ];
	}
	$inline = function_exists( 'memorial_plugin_convert_inline_tags' ) ? memorial_plugin_convert_inline_tags() : array();
	if ( isset( $inline[ $tag ] ) ) {
		return 'the inline value “' . $inline[ $tag ] . '” (toolbar of a paragraph)';
	}
	return 'the blocks of the plugin';
}

/**
 * Take the [mem_…] callbacks out of the shortcode registry and register deprecated wrappers instead.
 * Runs after every module has called add_shortcode().
 */
function memorial_plugin_shortcodes_compat() {
	global $shortcode_tags;
	foreach ( (array) $shortcode_tags as $tag => $callback ) {
		if ( 0 !== strpos( $tag, 'mem_' ) || isset( memorial_plugin_shortcode_impls()[ $tag ] ) ) {
			continue;
		}
		memorial_plugin_shortcode_impls( $tag, $callback );
		remove_shortcode( $tag );
		add_shortcode( $tag, 'memorial_plugin_shortcode_deprecated' );
	}
}
add_action( 'init', 'memorial_plugin_shortcodes_compat', 999 );

/**
 * A former shortcode met in the content: report the deprecation and render it as before.
 *
 * @param array|string $atts    Shortcode attributes.
 * @param string|null  $content Enclosed content.
 * @param string       $tag     Shortcode tag.
 * @return string
 */
function memorial_plugin_shortcode_deprecated( $atts, $content = null, $tag = '' ) {
	static $reported = array();
	if ( ! isset( $reported[ $tag ] ) ) {
		$reported[ $tag ] = true;
		_deprecated_function( esc_html( '[' . $tag . ']' ), esc_html( MEMORIAL_PLUGIN_SHORTCODES_DEPRECATED ), esc_html( memorial_plugin_shortcode_replacement( $tag ) ) );
	}
	$impl = memorial_plugin_shortcode_impl( $tag );
	return $impl ? (string) call_user_func( $impl, $atts, $content, $tag ) : '';
}

/**
 * Convert content written by the plugin itself (drafts and templates) before it is saved:
 * block-level shortcodes become plugin blocks, inline ones — inline values.
 *
 * @param string $content Block markup that may contain [mem_…] shortcodes.
 * @return string
 */
function memorial_plugin_blocks_from_shortcodes( $content ) {
	return function_exists( 'memorial_plugin_convert_content' ) ? memorial_plugin_convert_content( (string) $content, false ) : (string) $content;
}
