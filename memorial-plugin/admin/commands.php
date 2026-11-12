<?php
/**
 * Command Palette (Ctrl/Cmd + K): the plugin’s screens and actions (src/commands). The script is loaded on
 * every admin page and in the editors; only the screens and actions the user may use are passed to it.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Screens for the palette: id, label, search words, icon, address — only those the user can open.
 *
 * @return array<int,array>
 */
function memorial_plugin_command_screens() {
	$screens = array(
		array( 'leads', __( 'Call back requests', 'memorial-plugin' ), __( 'leads calls phone', 'memorial-plugin' ), 'edit_posts', 'admin.php?page=memorial-plugin-leads' ),
		array( 'products', __( 'Products', 'memorial-plugin' ), __( 'catalog prices', 'memorial-plugin' ), 'edit_posts', 'edit.php?post_type=mem_product&page=mem-products' ),
		array( 'types', __( 'Types and categories', 'memorial-plugin' ), __( 'catalog specifications', 'memorial-plugin' ), 'manage_categories', 'edit.php?post_type=mem_product&page=mem-types' ),
		array( 'works', __( 'Our works', 'memorial-plugin' ), __( 'portfolio photos examples', 'memorial-plugin' ), 'edit_posts', 'edit.php?post_type=mem_work&page=mem-works' ),
		array( 'work-types', __( 'Kinds of works', 'memorial-plugin' ), '', 'manage_categories', 'edit.php?post_type=mem_work&page=mem-work-types' ),
		array( 'search', __( 'What people search for on the site', 'memorial-plugin' ), __( 'search statistics queries', 'memorial-plugin' ), 'edit_pages', 'admin.php?page=mem-sq' ),
		array( 'convert', __( 'Convert to blocks', 'memorial-plugin' ), __( 'shortcodes migration', 'memorial-plugin' ), 'edit_pages', 'admin.php?page=memorial-plugin-convert' ),
		array( 'bookrev', __( 'Guest book reviews', 'memorial-plugin' ), __( 'reviews book', 'memorial-plugin' ), 'edit_posts', 'admin.php?page=memorial-plugin-bookrev' ),
	);
	$icons   = array(
		'mem-contacts' => 'contacts',
		'mem-prices'   => 'prices',
		'mem-reviews'  => 'reviews',
		'mem-metrika'  => 'analytics',
		'mem-modules'  => 'features',
		'mem-home'     => 'services',
	);
	foreach ( memorial_plugin_admin_pages() as $opt => $p ) {
		$slug      = (string) ( $p['slug'] ?? $opt );
		$screens[] = array(
			$opt,
			/* translators: %s: settings page title. */
			sprintf( __( 'Memorial: %s', 'memorial-plugin' ), wp_strip_all_tags( (string) $p['title'] ) ),
			__( 'settings', 'memorial-plugin' ),
			'manage_options',
			'admin.php?page=' . $slug,
			$icons[ $slug ] ?? 'features',
		);
	}
	$out = array();
	foreach ( $screens as $s ) {
		if ( current_user_can( $s[3] ) ) {
			$out[] = array(
				'id'       => sanitize_key( $s[0] ),
				'label'    => $s[1],
				'keywords' => $s[2],
				'icon'     => $s[5] ?? $s[0],
				'url'      => admin_url( $s[4] ),
			);
		}
	}
	return $out;
}

/**
 * Load the commands.
 */
function memorial_plugin_commands_assets() {
	$asset_file = MEMORIAL_PLUGIN_DIR . '/build/commands/index.asset.php';
	if ( ! is_readable( $asset_file ) || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$asset = require $asset_file;
	wp_enqueue_script( 'memorial-plugin-commands', MEMORIAL_PLUGIN_URL . '/build/commands/index.js', $asset['dependencies'], $asset['version'], array( 'in_footer' => true ) );
	wp_set_script_translations( 'memorial-plugin-commands', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
	if ( is_readable( MEMORIAL_PLUGIN_DIR . '/build/commands/style-index.css' ) ) {
		wp_enqueue_style( 'memorial-plugin-commands', MEMORIAL_PLUGIN_URL . '/build/commands/style-index.css', array(), $asset['version'] );
	}
	wp_add_inline_script(
		'memorial-plugin-commands',
		'window.memorialPluginCommands = ' . wp_json_encode(
			array(
				'screens'  => memorial_plugin_command_screens(),
				'leadsUrl' => admin_url( 'admin.php?page=memorial-plugin-leads' ),
				'can'      => array(
					'export'  => current_user_can( 'edit_posts' ),
					'leads'   => current_user_can( 'edit_posts' ),
					'convert' => current_user_can( 'edit_posts' ),
				),
			)
		) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'memorial_plugin_commands_assets' );
