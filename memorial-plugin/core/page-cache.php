<?php
/**
 * Page cache plugins (WP Super Cache, W3 Total Cache): the cached pages are cleared when the data of the
 * site changes in «Memorial → …» (contacts, prices, services, reviews…), because these values are shown
 * on many pages at once — in the header, the footer, price blocks and the texts of pages.
 * Posts and products are cleared by the cache plugins themselves when they are saved.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clear the whole page cache of the cache plugin in use (once per request).
 */
function memorial_plugin_page_cache_clear() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache(); // WP Super Cache.
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all(); // W3 Total Cache.
	}
	/**
	 * Fires after the plugin asked the page cache to clear (for other cache plugins).
	 */
	do_action( 'memorial_plugin_page_cache_cleared' );
}

/**
 * A setting of the plugin changed → clear the page cache.
 *
 * @param string $option Option name.
 */
function memorial_plugin_page_cache_on_option( $option ) {
	// Only the settings screens of «Memorial → …» (not logs, queues and one-time flags, which change on visits).
	if ( 0 !== strpos( (string) $option, 'mem_' ) || ! function_exists( 'memorial_plugin_admin_pages' ) ) {
		return;
	}
	if ( isset( memorial_plugin_admin_pages()[ $option ] ) || 'mem_rewio_hidden' === $option ) {
		memorial_plugin_page_cache_clear();
	}
}
add_action( 'updated_option', 'memorial_plugin_page_cache_on_option' );
add_action( 'added_option', 'memorial_plugin_page_cache_on_option' );

/* Catalog sections: names, order and prices of sections are shown on many pages. */
add_action( 'edited_mem_cat', 'memorial_plugin_page_cache_clear' );
add_action( 'created_mem_cat', 'memorial_plugin_page_cache_clear' );
add_action( 'delete_mem_cat', 'memorial_plugin_page_cache_clear' );
