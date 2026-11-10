<?php
/**
 * Для workflow «Скриншоты» (wp eval-file): адрес сайта в базе и список страниц для сравнения
 * → wp-content/ci-data/urls.json. На сайт не выгружается.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

$memorial_visual_urls = array();
$memorial_visual_add  = function ( $name, $url ) use ( &$memorial_visual_urls ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return;
	}
	$parts                  = wp_parse_url( $url );
	$memorial_visual_urls[] = array(
		'name' => sanitize_title( $name ),
		'path' => ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ),
	);
};

$memorial_visual_add( 'home', home_url( '/' ) );
$memorial_visual_pages = get_posts(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'menu_order title',
		'order'       => 'ASC',
	)
);
foreach ( $memorial_visual_pages as $memorial_visual_page ) {
	if ( (int) get_option( 'page_on_front' ) === $memorial_visual_page->ID ) {
		continue;
	}
	$memorial_visual_add( 'page-' . $memorial_visual_page->post_name, get_permalink( $memorial_visual_page ) );
}
$memorial_visual_add( 'catalog', get_post_type_archive_link( 'mem_product' ) );
$memorial_visual_terms = get_terms(
	array(
		'taxonomy'   => 'mem_cat',
		'number'     => 2,
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
foreach ( is_wp_error( $memorial_visual_terms ) ? array() : $memorial_visual_terms as $memorial_visual_term ) {
	$memorial_visual_add( 'cat-' . $memorial_visual_term->slug, get_term_link( $memorial_visual_term ) );
}
foreach ( get_posts( array( 'post_type' => 'mem_product', 'numberposts' => 2 ) ) as $memorial_visual_product ) {
	$memorial_visual_add( 'product-' . $memorial_visual_product->post_name, get_permalink( $memorial_visual_product ) );
}
$memorial_visual_add( '404', home_url( '/no-such-page-404/' ) );

file_put_contents( WP_CONTENT_DIR . '/ci-data/urls.json', wp_json_encode( array_values( $memorial_visual_urls ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- служебный файл CI.
echo count( $memorial_visual_urls ) . " pages\n";
