<?php
/**
 * One-time cleanup of test content left on the site: pages whose title is only digits
 * (“0000000000…”, “2342342342…” — created while testing the converter) and empty products called
 * “New product”. They go to the trash, not deleted: “Pages → Trash” brings them back for 30 days.
 * The log is the memorial_plugin_cleanup option.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Move the test posts to the trash.
 */
function memorial_plugin_cleanup_test_posts() {
	if ( get_option( 'memorial_plugin_cleanup' ) || ! current_user_can( 'delete_pages' ) ) {
		return;
	}
	$trashed = array();
	$posts   = get_posts(
		array(
			'post_type'      => array( 'page', 'mem_product' ),
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'all',
		)
	);
	foreach ( $posts as $post ) {
		$title = trim( $post->post_title );
		$test  = 'page' === $post->post_type
			? (bool) preg_match( '/^\d{10,}$/', $title )
			: 'New product' === $title && '' === trim( $post->post_content ) && ! has_post_thumbnail( $post );
		if ( $test && wp_trash_post( $post->ID ) ) {
			$trashed[] = array(
				'id'    => (int) $post->ID,
				'type'  => $post->post_type,
				'title' => $title,
			);
		}
	}
	update_option(
		'memorial_plugin_cleanup',
		array(
			'time'    => time(),
			'trashed' => $trashed,
		),
		false
	);
}
add_action( 'admin_init', 'memorial_plugin_cleanup_test_posts', 30 );
