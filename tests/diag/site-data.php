<?php
/**
 * Read-only: data of the live site for the text check (tools/content-lint.php) — price codes, addresses of
 * pages and catalog sections. Put on the site under a random name and deleted at once by
 * .github/workflows/site-data.yml. Nothing is written.
 */
// phpcs:ignoreFile -- a temporary diagnostic file, not a part of the plugin.
if ( ! isset( $_GET['k'] ) || '__KEY__' !== $_GET['k'] ) {
	http_response_code( 404 );
	exit;
}
define( 'SHORTINIT', false );
require dirname( __DIR__ ) . '/wp-load.php';
header( 'Content-Type: application/json; charset=utf-8' );

$codes = array( 'kremaciya' );
foreach ( (array) ( memorial_plugin_prices( 'other' )['items'] ?? array() ) as $it ) {
	if ( '' !== trim( (string) ( $it['code'] ?? '' ) ) ) {
		$codes[] = trim( (string) $it['code'] );
	}
}
foreach ( memorial_plugin_packages() as $p ) {
	$codes[] = $p['name'];
}
$pages = array();
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1 ) ) as $p ) {
	$pages[] = '/' . urldecode( (string) get_page_uri( $p ) ) . '/';
}
$terms = array();
foreach ( get_terms( array( 'taxonomy' => 'mem_cat', 'hide_empty' => false ) ) as $t ) {
	$link = get_term_link( $t );
	if ( ! is_wp_error( $link ) ) {
		$terms[] = strtok( wp_make_link_relative( urldecode( $link ) ), '#' );
	}
}
sort( $pages );
$terms = array_values( array_unique( $terms ) );
sort( $terms );
echo wp_json_encode(
	array(
		'codes'        => array_values( array_unique( $codes ) ),
		'pages'        => $pages,
		'terms'        => $terms,
		'product_base' => wp_make_link_relative( urldecode( (string) get_post_type_archive_link( 'mem_product' ) ) ),
	),
	JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
