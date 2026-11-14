<?php
/**
 * Update of the site data to 5.1.0 — once, on the first admin visit after the update.
 *
 * 5.0 moved the old theme (memorial_speshu) over: the “Memorial → Design” settings into the site styles,
 * the classic main menu into a Navigation menu, the header subtitle into the site tagline. Those moves are
 * done on the site, and 5.1.0 has no way back to the old theme any more (the way back is a backup), so:
 *  - the Navigation menu made from the classic menu becomes the main menu (option memorial_theme_main_nav);
 *  - other Navigation menus that nothing uses (a second copy made by WordPress) go to the trash;
 *  - the logs of the moves, the settings of the old theme and the menu locations are deleted.
 * The old “Memorial → Design” setting (mem_design) is removed by Memorial Plugin 2.1.0: it still holds
 * the WebP choice it moves.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run the update once.
 */
function memorial_theme_upgrade() {
	if ( version_compare( (string) get_option( 'memorial_theme_db', '0' ), '5.1.0', '>=' ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$log = array( 'time' => time() );

	// The main menu: the one made from the classic menu, otherwise the newest one.
	$old  = get_option( 'memorial_theme_nav_migrated' );
	$main = is_array( $old ) ? (int) ( $old['nav_id'] ?? 0 ) : 0;
	if ( ! $main || 'publish' !== get_post_status( $main ) ) {
		$main = memorial_theme_main_nav_id();
	}
	if ( $main ) {
		update_option( 'memorial_theme_main_nav', $main, false );
		$log['main_nav'] = $main;
		$log['trashed']  = memorial_theme_trash_unused_navigation( $main );
	}

	foreach ( array( 'memorial_theme_nav_migrated', 'memorial_theme_design_migrated', 'memorial_theme_tagline_migrated', 'theme_mods_memorial_speshu' ) as $name ) {
		delete_option( $name );
	}
	remove_theme_mod( 'nav_menu_locations' );

	update_option( 'memorial_theme_upgrade_510', $log, false );
	update_option( 'memorial_theme_db', '5.1.0', false );
}
add_action( 'admin_init', 'memorial_theme_upgrade' );

/**
 * Navigation menus other than the main one that no template, part or post refers to go to the trash
 * (they can be restored from it for 30 days).
 *
 * @param int $main ID of the main menu.
 * @return int[] Trashed IDs.
 */
function memorial_theme_trash_unused_navigation( $main ) {
	global $wpdb;
	$trashed = array();
	$ids     = get_posts(
		array(
			'post_type'      => 'wp_navigation',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	foreach ( $ids as $id ) {
		if ( (int) $id === (int) $main ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time check whether any content refers to the menu.
		$used = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type <> 'revision' AND post_content LIKE %s",
				'%' . $wpdb->esc_like( '"ref":' . (int) $id ) . '%'
			)
		);
		if ( ! $used && wp_trash_post( $id ) ) {
			$trashed[] = (int) $id;
		}
	}
	return $trashed;
}
