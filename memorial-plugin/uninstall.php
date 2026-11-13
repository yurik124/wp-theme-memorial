<?php
/**
 * Removing the plugin (Plugins → Delete).
 *
 * By default only what the plugin can rebuild by itself is removed: scheduled events, temporary data
 * (transients: reports, rate limits, caches of the theme bridge) and the cached reviews of Rewio.
 * The content of the site — products, works, guest book, call requests, catalog sections, settings,
 * the text of pages — stays, so that a reinstalled plugin (or a backup) finds everything in place.
 *
 * To delete all data of the plugin as well, add to wp-config.php before deleting the plugin:
 *
 *     define( 'MEMORIAL_PLUGIN_DELETE_DATA', true );
 *
 * Then also removed: posts of the plugin’s types with their meta, terms of its taxonomies, its options
 * and the meta it added to pages. Make a backup of the database first — this cannot be undone.
 *
 * @package memorial-plugin
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Clean one site.
 *
 * @param bool $everything Delete the content of the plugin too.
 */
function memorial_plugin_uninstall_site( $everything ) {
	global $wpdb;

	foreach ( array( 'memorial_plugin_rewio_cron', 'memorial_plugin_rewio_cron_once', 'memorial_plugin_logos_cron' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	// Temporary data: reports of one-time moves, rate limits, the theme bridge, Rewio caches.
	$like = array( '_transient_mem\\_%', '_transient_timeout_mem\\_%', '_transient_memorial\\_plugin\\_%', '_transient_timeout_memorial\\_plugin\\_%' );
	foreach ( $like as $pattern ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time cleanup of the plugin’s own rows.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
	}
	foreach ( array( 'mem_rewio_cache', 'mem_rewio_catalog', 'mem_rewio_status', 'mem_logos_queue', 'mem_core_flush', 'memorial_plugin_pages_to_content', 'memorial_plugin_cleanup' ) as $option ) {
		delete_option( $option );
	}

	if ( ! $everything ) {
		return;
	}

	// Posts of the plugin’s types, with their meta, revisions and attached terms.
	foreach ( array( 'mem_product', 'mem_work', 'mem_lead', 'mem_bookrev', 'mem_rewio' ) as $type ) {
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	// Terms of its taxonomies: catalog sections, kinds of works and product specifications (mem_*).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the taxonomies are not registered while uninstalling.
	$taxonomies = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE %s", 'mem\\_%' ) );
	foreach ( $taxonomies as $taxonomy ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see above.
		$terms = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy ) );
		foreach ( $terms as $term ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery -- see above.
			$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
			$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
			$wpdb->delete( $wpdb->termmeta, array( 'term_id' => $term->term_id ) );
			$wpdb->delete( $wpdb->terms, array( 'term_id' => $term->term_id ) );
			// phpcs:enable
		}
	}

	// Meta the plugin added to pages (catalog section of a page, “Good to know” fields, focus points…).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time cleanup of the plugin’s own rows.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", '\\_mem\\_%' ) );

	// Options: settings (mem_*) and the plugin’s own flags (memorial_plugin_*).
	foreach ( array( 'mem\\_%', 'memorial\\_plugin\\_%' ) as $pattern ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time cleanup of the plugin’s own rows.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
	}
	wp_cache_flush();
}

$memorial_plugin_everything = defined( 'MEMORIAL_PLUGIN_DELETE_DATA' ) && MEMORIAL_PLUGIN_DELETE_DATA;
if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $memorial_plugin_site ) {
		switch_to_blog( $memorial_plugin_site );
		memorial_plugin_uninstall_site( $memorial_plugin_everything );
		restore_current_blog();
	}
} else {
	memorial_plugin_uninstall_site( $memorial_plugin_everything );
}
