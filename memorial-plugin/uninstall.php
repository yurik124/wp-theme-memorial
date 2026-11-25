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

	foreach ( array( 'memorial_plugin_rewio_cron', 'memorial_plugin_rewio_cron_once', 'memorial_plugin_logos_cron', 'memorial_plugin_card_sizes', 'memorial_plugin_llms', 'memorial_plugin_photos' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	// Temporary data: reports of one-time moves, rate limits, the theme bridge, Rewio caches.
	$like = array( '_transient_mem\\_%', '_transient_timeout_mem\\_%', '_transient_memorial\\_plugin\\_%', '_transient_timeout_memorial\\_plugin\\_%' );
	foreach ( $like as $pattern ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time cleanup of the plugin’s own rows.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
	}
	// llms.txt in the root of the site — only the one the plugin made.
	if ( get_option( 'memorial_plugin_llms_file' ) && file_exists( ABSPATH . 'llms.txt' ) ) {
		wp_delete_file( ABSPATH . 'llms.txt' );
	}
	// Its rules in .htaccess: the charset of llms.txt (seo/llms.php) and closed xmlrpc.php (core/no-comments.php).
	if ( file_exists( ABSPATH . '.htaccess' ) && wp_is_writable( ABSPATH . '.htaccess' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		insert_with_markers( ABSPATH . '.htaccess', 'Memorial llms.txt', array() );
		insert_with_markers( ABSPATH . '.htaccess', 'Memorial xmlrpc', array() );
	}
	foreach ( array( 'mem_rewio_cache', 'mem_rewio_catalog', 'mem_rewio_status', 'mem_logos_queue', 'mem_core_flush', 'memorial_plugin_pages_to_content', 'memorial_plugin_upgrade_210', 'memorial_plugin_reviews_mail_last', 'memorial_plugin_card_sizes_done', 'memorial_plugin_fix_uploads_2', 'memorial_plugin_fix_uploads_log', 'memorial_plugin_sku_1', 'memorial_plugin_sku_log', 'memorial_plugin_import_log', 'memorial_plugin_llms_file', 'memorial_plugin_seo_1', 'memorial_plugin_seo_1_log', 'memorial_plugin_llms_charset', 'memorial_plugin_terms_1', 'memorial_plugin_terms_1_log', 'memorial_plugin_xmlrpc_block', 'memorial_plugin_services_1', 'memorial_plugin_services_1_log', 'memorial_plugin_seo_2', 'memorial_plugin_seo_2_log', 'memorial_plugin_schema_1', 'memorial_plugin_schema_1_log', 'memorial_plugin_photos_report', 'memorial_plugin_photos_state', 'memorial_plugin_photos_logo_key', 'memorial_plugin_photos_errors', 'memorial_plugin_repo_log', 'memorial_plugin_repo_seen', 'memorial_plugin_repo_ver', 'memorial_plugin_prices_2', 'memorial_plugin_prices_2_log', 'memorial_plugin_contacts_3', 'memorial_plugin_contacts_3_log', 'memorial_plugin_contacts_4', 'memorial_plugin_contacts_4_log', 'memorial_plugin_gallery_1', 'memorial_plugin_gallery_1_log', 'mem_prices_changed' ) as $option ) {
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
