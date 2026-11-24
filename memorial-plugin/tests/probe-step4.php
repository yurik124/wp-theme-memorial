<?php
/**
 * Probe on the site copy (PROBE_CLI → `wp eval-file`): what the one-time moves of step 4 (contacts_4,
 * gallery_1) did on real data — the workflow runs admin_init before. Not deployed (tests/).
 *
 * @package memorial-plugin
 */

// phpcs:ignoreFile -- a probe script.
foreach ( array( 'memorial_plugin_contacts_4', 'memorial_plugin_gallery_1' ) as $o ) {
	echo $o . ': ' . var_export( get_option( $o ), true ) . "\n";
	$log = get_option( $o . '_log' );
	unset( $log['contacts'], $log['posts'] );
	print_r( $log );
}
foreach ( memorial_plugin_addrs() as $a ) {
	echo $a['addr'] . ' | ' . implode( ' · ', memorial_plugin_addr_notes( $a ) ) . "\n";
}
$kid = url_to_postid( home_url( '/ritualnye-uslugi/katafalk/' ) );
echo 'katafalk: ' . $kid . ' ' . ( $kid ? get_post_type( $kid ) . ' ' . get_post_status( $kid ) : '' ) . ' page_by_path=' . var_export( (bool) get_page_by_path( 'ritualnye-uslugi/katafalk' ), true ) . "\n";
$page = get_page_by_path( 'fotogalereia' );
preg_match_all( '~<figcaption[^>]*>(.*?)</figcaption>~s', (string) $page->post_content, $m );
echo "captions:\n " . implode( "\n ", $m[1] ) . "\n";
echo 'desc: ' . get_post_meta( $page->ID, '_seopress_titles_desc', true ) . "\n";
echo 'MAX: ' . ( memorial_plugin_messengers()['max'] ?? '—' ) . "\n";
