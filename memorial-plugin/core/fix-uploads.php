<?php
/**
 * One-time repair of photo addresses in the home page settings: after renaming files to Latin letters
 * (Cyr To Lat) or converting them to WebP, a stored address may point to a file that no longer exists
 * (the browser gets 404). An address is replaced only when its file is missing and the renamed file is found.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * The current address of an uploaded file whose stored address no longer works.
 *
 * @param string $url Stored address (absolute or starting with /wp-content/uploads/).
 * @return string The new address, or '' when the file exists or nothing was found.
 */
function memorial_plugin_fixed_upload_url( $url ) {
	if ( ! is_string( $url ) || ! preg_match( '~/wp-content/uploads/(.+)$~u', $url, $m ) ) {
		return '';
	}
	$dir = wp_get_upload_dir();
	$rel = rawurldecode( $m[1] );
	if ( is_file( $dir['basedir'] . '/' . $rel ) ) {
		return '';
	}
	$new = '';
	// the same file in WebP.
	$webp = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $rel );
	if ( $webp !== $rel && is_file( $dir['basedir'] . '/' . $webp ) ) {
		$new = $dir['baseurl'] . '/' . $webp;
	} else {
		// the file renamed: the attachment still keeps the first address in its guid.
		global $wpdb;
		$name = pathinfo( $rel, PATHINFO_FILENAME );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time repair.
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ( guid LIKE %s OR guid LIKE %s ) LIMIT 1", '%/' . $wpdb->esc_like( $name ) . '.%', '%/' . $wpdb->esc_like( rawurlencode( $name ) ) . '.%' ) );
		$file = $id ? get_attached_file( $id ) : '';
		if ( $file && is_file( $file ) ) {
			$new = (string) wp_get_attachment_url( $id );
		}
	}
	if ( '' === $new ) {
		return '';
	}
	// keep the form of the stored address (the home settings keep it relative to the site).
	return str_starts_with( $url, '/' ) ? wp_make_link_relative( $new ) : $new;
}

/**
 * Replaces broken photo addresses in a settings value.
 *
 * @param mixed $value Value.
 * @param int   $fixed Count of replaced addresses.
 * @return mixed
 */
function memorial_plugin_fix_upload_urls( $value, &$fixed ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = memorial_plugin_fix_upload_urls( $v, $fixed );
		}
		return $value;
	}
	if ( is_string( $value ) && str_contains( $value, '/wp-content/uploads/' ) && ! preg_match( '/\s/', trim( $value ) ) ) {
		$new = memorial_plugin_fixed_upload_url( trim( $value ) );
		if ( '' !== $new ) {
			++$fixed;
			return $new;
		}
	}
	return $value;
}

add_action(
	'init',
	function () {
		if ( get_option( 'memorial_plugin_fix_uploads_1' ) ) {
			return;
		}
		$home = get_option( 'mem_home' );
		if ( is_array( $home ) ) {
			$fixed = 0;
			$new   = memorial_plugin_fix_upload_urls( $home, $fixed );
			if ( $fixed ) {
				update_option( 'mem_home', $new );
			}
		}
		update_option( 'memorial_plugin_fix_uploads_1', 1 );
	},
	20
);
