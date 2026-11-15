<?php
/**
 * Small copy of product photos for catalog cards (360 px wide): a card is about 260 px wide, the
 * smallest copy before was 600 px. New uploads get it at once (a registered image size); for the
 * photos already on the site it is made in the background (WP-Cron, a few photos a minute).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

const MEMORIAL_PLUGIN_CARD_SIZE = 'memorial-card';

add_action(
	'after_setup_theme',
	function () {
		add_image_size( MEMORIAL_PLUGIN_CARD_SIZE, 360, 0, false );
	}
);

// Not offered in the editor's size list: the copy is only for the srcset of catalog cards.
add_filter(
	'image_size_names_choose',
	function ( $sizes ) {
		unset( $sizes[ MEMORIAL_PLUGIN_CARD_SIZE ] );
		return $sizes;
	}
);

/**
 * Photos of products (main photo and gallery) that still have no card copy.
 *
 * @param int $limit How many to return.
 * @return int[]
 */
function memorial_plugin_card_size_missing( $limit ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one query over the product photos, a background task.
	$rows = $wpdb->get_col(
		"SELECT m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
		WHERE p.post_type = 'mem_product' AND m.meta_key IN ( '_thumbnail_id', '_mem_gallery' ) AND m.meta_value <> ''"
	);
	$ids = array();
	foreach ( $rows as $v ) {
		foreach ( explode( ',', (string) $v ) as $id ) {
			$ids[ absint( $id ) ] = true;
		}
	}
	unset( $ids[0] );
	$out = array();
	foreach ( array_keys( $ids ) as $id ) {
		$meta = wp_get_attachment_metadata( $id );
		// a photo narrower than the copy does not need it; a broken one is skipped
		if ( ! is_array( $meta ) || empty( $meta['width'] ) || (int) $meta['width'] <= 360 || isset( $meta['sizes'][ MEMORIAL_PLUGIN_CARD_SIZE ] ) || ! empty( $meta['memorial_card_failed'] ) ) {
			continue;
		}
		$out[] = $id;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * Makes the card copy of a few photos and schedules the next batch while there are any left.
 */
function memorial_plugin_card_size_batch() {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$ids = memorial_plugin_card_size_missing( 8 );
	foreach ( $ids as $id ) {
		$meta = wp_get_attachment_metadata( $id );
		$file = wp_get_original_image_path( $id );
		$file = $file && is_file( $file ) ? $file : get_attached_file( $id );
		$ed   = $file && is_file( $file ) ? wp_get_image_editor( $file ) : null;
		$made = $ed && ! is_wp_error( $ed ) ? $ed->make_subsize(
			array(
				'width'  => 360,
				'height' => 0,
				'crop'   => false,
			)
		) : null;
		if ( is_array( $made ) && ! empty( $made['file'] ) ) {
			// the copy lies next to the attached file (the folder of the year and month)
			$meta['sizes'][ MEMORIAL_PLUGIN_CARD_SIZE ] = $made;
		} else {
			$meta['memorial_card_failed'] = 1;
		}
		wp_update_attachment_metadata( $id, $meta );
	}
	if ( count( $ids ) >= 8 && ! wp_next_scheduled( 'memorial_plugin_card_sizes' ) ) {
		wp_schedule_single_event( time() + 60, 'memorial_plugin_card_sizes' );
	}
	if ( count( $ids ) < 8 ) {
		update_option( 'memorial_plugin_card_sizes_done', 1 );
	}
}
add_action( 'memorial_plugin_card_sizes', 'memorial_plugin_card_size_batch' );

// The first batch: once, after the update (then each batch schedules the next one).
add_action(
	'init',
	function () {
		if ( ! get_option( 'memorial_plugin_card_sizes_done' ) && ! wp_next_scheduled( 'memorial_plugin_card_sizes' ) ) {
			wp_schedule_single_event( time() + 30, 'memorial_plugin_card_sizes' );
		}
	}
);
