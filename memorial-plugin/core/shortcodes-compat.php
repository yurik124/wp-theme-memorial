<?php
/**
 * Backward compatibility layer for the old [mem_…] shortcodes.
 *
 * The plugin is block-first: its content is the blocks of blocks/content and the inline values
 * (blocks/content/inline-values.php). The modules still define the output of each former shortcode with
 * add_shortcode(); this file takes those callbacks out of the shortcode registry right after they are added
 * and keeps them as the implementations of the blocks and inline values
 * (memorial_plugin_shortcode_impl()). In the content a shortcode keeps working, but:
 *  - every use reports a deprecation (_deprecated_function(), visible with WP_DEBUG) that names the block
 *    which replaces it;
 *  - the posts that still contain shortcodes are converted to blocks automatically after an update
 *    (memorial_plugin_shortcodes_migrate(), WP-Cron, batches of MEMORIAL_PLUGIN_CONVERT_BATCH posts),
 *    with the same rollback as the manual converter (Memorial → Convert to blocks).
 *
 * New content written by the plugin itself (page drafts, legal documents, price replacement) is
 * converted to blocks before it is saved, so no new shortcodes appear.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Version since which the shortcodes are deprecated. */
const MEMORIAL_PLUGIN_SHORTCODES_DEPRECATED = '2.1.0';

/** Option: state of the automatic conversion of content to blocks. */
const MEMORIAL_PLUGIN_SHORTCODES_MIGRATION = 'memorial_plugin_shortcodes_migration';

/** WP-Cron hook of the automatic conversion. */
const MEMORIAL_PLUGIN_SHORTCODES_CRON = 'memorial_plugin_shortcodes_migrate';

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

/* ---------- Automatic conversion of the content after an update ---------- */

/**
 * Start the automatic conversion once per plugin version (and again when the version changes).
 */
function memorial_plugin_shortcodes_migration_schedule() {
	$state = get_option( MEMORIAL_PLUGIN_SHORTCODES_MIGRATION, array() );
	if ( ( $state['version'] ?? '' ) === MEMORIAL_PLUGIN_VER || ! function_exists( 'memorial_plugin_convert_items' ) ) {
		return;
	}
	update_option(
		MEMORIAL_PLUGIN_SHORTCODES_MIGRATION,
		array(
			'version'   => MEMORIAL_PLUGIN_VER,
			'started'   => time(),
			'converted' => array(),
			'failed'    => array(),
			'done'      => false,
		),
		false
	);
	if ( ! wp_next_scheduled( MEMORIAL_PLUGIN_SHORTCODES_CRON ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, MEMORIAL_PLUGIN_SHORTCODES_CRON );
	}
}
add_action( 'admin_init', 'memorial_plugin_shortcodes_migration_schedule' );

/**
 * One batch of the automatic conversion (WP-Cron). Every converted post keeps a revision and
 * the _memorial_plugin_converted meta, so it can be rolled back in Memorial → Convert to blocks.
 */
function memorial_plugin_shortcodes_migrate() {
	$state = get_option( MEMORIAL_PLUGIN_SHORTCODES_MIGRATION, array() );
	if ( ! empty( $state['done'] ) || ! function_exists( 'memorial_plugin_convert_items' ) ) {
		return;
	}
	$skip  = array_merge( (array) ( $state['converted'] ?? array() ), (array) ( $state['failed'] ?? array() ) );
	$todo  = array_filter(
		memorial_plugin_convert_items( false ),
		static fn( $item ) => empty( $item['converted'] ) && ! in_array( (int) $item['id'], $skip, true )
	);
	$batch = array_slice( $todo, 0, MEMORIAL_PLUGIN_CONVERT_BATCH );
	foreach ( $batch as $item ) {
		$result = memorial_plugin_convert_post( (int) $item['id'] );
		if ( 'converted' === $result ) {
			$state['converted'][] = (int) $item['id'];
		} elseif ( 'unchanged' !== $result ) {
			$state['failed'][] = (int) $item['id'];
		} else {
			$state['converted'][] = (int) $item['id']; // nothing left to convert: do not pick it again.
		}
	}
	$state['done'] = count( $todo ) <= count( $batch );
	update_option( MEMORIAL_PLUGIN_SHORTCODES_MIGRATION, $state, false );
	if ( ! $state['done'] ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, MEMORIAL_PLUGIN_SHORTCODES_CRON );
	}
}
add_action( MEMORIAL_PLUGIN_SHORTCODES_CRON, 'memorial_plugin_shortcodes_migrate' );

/**
 * Report of the automatic conversion for administrators.
 */
function memorial_plugin_shortcodes_migration_notice() {
	$state = get_option( MEMORIAL_PLUGIN_SHORTCODES_MIGRATION, array() );
	if ( empty( $state['done'] ) || ! empty( $state['seen'] ) || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$converted = count( (array) ( $state['converted'] ?? array() ) );
	$failed    = count( (array) ( $state['failed'] ?? array() ) );
	if ( ! $converted && ! $failed ) {
		return;
	}
	$state['seen'] = true;
	update_option( MEMORIAL_PLUGIN_SHORTCODES_MIGRATION, $state, false );
	$url = admin_url( 'admin.php?page=memorial-plugin-convert' );
	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
		$failed ? 'warning' : 'success',
		esc_html(
			sprintf(
				/* translators: 1: number of converted posts, 2: number of posts that could not be converted */
				__( 'The old [mem_…] shortcodes were converted to blocks: %1$d posts. Not converted: %2$d.', 'memorial-plugin' ),
				$converted,
				$failed
			)
		),
		esc_url( $url ),
		esc_html__( 'Review or roll back', 'memorial-plugin' )
	);
}
add_action( 'admin_notices', 'memorial_plugin_shortcodes_migration_notice' );
