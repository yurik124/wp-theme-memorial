<?php
/**
 * Permanent redirects (301) of addresses that search engines still know.
 *
 *  - Old addresses of memorialbel.ru that changed or are gone (memorial_plugin_redirects()): only when the
 *    address does not open anything itself (404), so a real page always wins. The target is a page path:
 *    it is looked up on every request, so a renamed page keeps working through its permalink.
 *  - /<page>/page/N/: pages have no pages of their own (the “Catalog” block shows all products at once),
 *    WordPress would show the same page again under another address — a duplicate.
 *
 * Runs before WordPress guesses an address by a similar name (redirect_canonical): without it /kresty/ led to a
 * product and from there to its section — two redirects instead of one.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Old address (path from the site root) => page path, or '' for the home page.
 *
 * @return array<string,string>
 */
function memorial_plugin_redirects() {
	$map = array(
		'/index.html'                     => '',
		'/indexdata/index.swf'            => '',
		'/politika-ispolzovaniya-cookie/' => 'politika-cookie',
		'/kresty/'                        => 'metallicheskie-kresty',
		'/ogradki-2/'                     => 'ogradki',
		'/posobie-na-pogrebenie/'         => 'poleznoe/posobie-na-pogrebenie',
	);
	/**
	 * Filters the redirects of old addresses.
	 *
	 * @param array $map Old path => page path ('' — the home page).
	 */
	return (array) apply_filters( 'memorial_plugin_redirects', $map );
}

/**
 * Path of the request from the site root, decoded, with a trailing slash for addresses without an extension.
 *
 * @param string $uri Request URI.
 * @return string
 */
function memorial_plugin_request_path( $uri ) {
	$path = rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
	$base = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	if ( '' !== $base && 0 === strpos( $path, $base . '/' ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	if ( '' === $path ) {
		$path = '/';
	}
	if ( ! preg_match( '~\.[a-z0-9]{2,5}$~i', $path ) ) {
		$path = trailingslashit( $path );
	}
	return $path;
}

/**
 * Where an old address leads: the full URL, or '' when the address is not in the list (or its page is gone).
 *
 * @param string $path Path from the site root (memorial_plugin_request_path()).
 * @return string
 */
function memorial_plugin_redirect_target( $path ) {
	$map = memorial_plugin_redirects();
	if ( ! array_key_exists( $path, $map ) ) {
		return '';
	}
	$to = trim( (string) $map[ $path ], '/' );
	if ( '' === $to ) {
		return home_url( '/' );
	}
	$page = get_page_by_path( $to );
	return $page && 'publish' === $page->post_status ? (string) get_permalink( $page ) : '';
}

add_action(
	'template_redirect',
	static function () {
		$to = '';
		if ( is_404() ) {
			$to = memorial_plugin_redirect_target( memorial_plugin_request_path( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only compared with the list.
		} elseif ( is_page() && (int) get_query_var( 'paged' ) > 1 ) {
			$to = (string) get_permalink( get_queried_object_id() );
		}
		if ( '' === $to ) {
			return;
		}
		$query = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passed on as it is (ad tags).
		if ( '' !== $query ) {
			$to .= ( false === strpos( $to, '?' ) ? '?' : '&' ) . $query;
		}
		wp_safe_redirect( $to, 301, 'Memorial' );
		exit;
	},
	0
);
