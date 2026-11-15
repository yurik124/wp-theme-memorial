<?php
/**
 * One-time repair of photo addresses: after renaming files to Latin letters (Cyr To Lat) or converting
 * them to WebP, a stored address may point to a file that no longer exists (the browser gets 404).
 * Looked at: the home page settings, the old settings of ritual services and the text of published
 * pages. An address is replaced only when its file is missing and the renamed file is found; every
 * replacement is written to the log `memorial_plugin_fix_uploads_log` (where, old address, new address).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * The current address of an uploaded file whose stored address no longer works.
 *
 * @param string $url Stored address (absolute, also of another domain, or starting with /wp-content/uploads/).
 * @return string The new address without the domain, or '' when the file exists or nothing was found.
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
	// The same file in WebP.
	$webp = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $rel );
	if ( $webp !== $rel && is_file( $dir['basedir'] . '/' . $webp ) ) {
		$new = $dir['baseurl'] . '/' . $webp;
	} else {
		// The file renamed: the attachment still keeps its first address in the guid. First the exact
		// name; then, for a copy of a size («-1024x768», from 100 px), the attachment without the size.
		$name  = pathinfo( $rel, PATHINFO_FILENAME );
		$tries = array( array( $name, 0 ) );
		if ( preg_match( '/^(.+)-(\d{3,4})x(\d{3,4})$/', $name, $s ) ) {
			$tries[] = array( $s[1], (int) $s[2] );
		}
		global $wpdb;
		foreach ( $tries as [$base, $w] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time repair.
			$id   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ( guid LIKE %s OR guid LIKE %s ) ORDER BY ID LIMIT 1", '%/' . $wpdb->esc_like( $base ) . '.%', '%/' . $wpdb->esc_like( rawurlencode( $base ) ) . '.%' ) );
			$file = $id ? get_attached_file( $id ) : '';
			if ( ! $file || ! is_file( $file ) ) {
				continue;
			}
			$new = (string) wp_get_attachment_url( $id );
			if ( $w ) {
				// the smallest copy not narrower than the old one; none — the file itself
				$best = 0;
				$meta = wp_get_attachment_metadata( $id );
				foreach ( is_array( $meta ) ? (array) ( $meta['sizes'] ?? array() ) : array() as $size ) {
					$sw = (int) $size['width'];
					if ( $sw >= $w && ( ! $best || $sw < $best ) && is_file( dirname( $file ) . '/' . $size['file'] ) ) {
						$best = $sw;
						$new  = dirname( $new ) . '/' . $size['file'];
					}
				}
			}
			break;
		}
	}
	if ( '' === $new ) {
		return '';
	}
	// Without the domain: works on any address of the site (also after moving to another domain).
	return wp_make_link_relative( $new );
}

/**
 * Replaces broken photo addresses in a value: a settings array or a text with addresses inside.
 *
 * @param mixed  $value Value.
 * @param array  $log   Replacements: [ where, old, new ].
 * @param string $where Where the value is (for the log).
 * @return mixed
 */
function memorial_plugin_fix_upload_urls( $value, &$log, $where = '' ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = memorial_plugin_fix_upload_urls( $v, $log, $where . '/' . $k );
		}
		return $value;
	}
	if ( ! is_string( $value ) || ! str_contains( $value, '/wp-content/uploads/' ) ) {
		return $value;
	}
	return preg_replace_callback(
		'~(?:https?://[^\s"\'()<>,]+)?/wp-content/uploads/[^\s"\'()<>,]+\.(?:jpe?g|png|webp|gif)~iu',
		function ( $m ) use ( &$log, $where ) {
			$new = memorial_plugin_fixed_upload_url( $m[0] );
			if ( '' === $new ) {
				return $m[0];
			}
			$log[] = array( $where, $m[0], $new );
			return $new;
		},
		$value
	);
}

/**
 * Runs the repair once: options, then the text of published pages and posts.
 *
 * @return array Replacements: [ where, old, new ].
 */
function memorial_plugin_fix_uploads_run() {
	global $wpdb;
	$log = array();
	foreach ( array( 'mem_home', 'memorial_ritual_services_options' ) as $option ) {
		$value = get_option( $option );
		if ( ! is_array( $value ) && ! is_string( $value ) ) {
			continue;
		}
		$count = count( $log );
		$new   = memorial_plugin_fix_upload_urls( $value, $log, $option );
		if ( count( $log ) > $count ) {
			// Saved as is: the sanitizer of a settings screen is not registered here (it is for the form).
			update_option( $option, $new );
		}
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time repair over the published texts.
	$rows = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status IN ( 'publish', 'private', 'future', 'draft' ) AND post_type NOT IN ( 'revision', 'attachment' ) AND post_content LIKE '%/wp-content/uploads/%'" );
	foreach ( $rows as $row ) {
		$count = count( $log );
		$new   = memorial_plugin_fix_upload_urls( $row->post_content, $log, 'post ' . $row->ID );
		if ( count( $log ) > $count ) {
			// Directly, without a revision and without the filters of saving (they may change the blocks).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time repair.
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $row->ID ) );
			clean_post_cache( (int) $row->ID );
		}
	}
	return $log;
}

add_action(
	'init',
	function () {
		if ( get_option( 'memorial_plugin_fix_uploads_2' ) ) {
			return;
		}
		update_option( 'memorial_plugin_fix_uploads_2', 1 );
		$log = memorial_plugin_fix_uploads_run();
		update_option( 'memorial_plugin_fix_uploads_log', $log, false );
		if ( $log && function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
	},
	20
);
