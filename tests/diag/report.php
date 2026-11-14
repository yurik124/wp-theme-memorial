<?php
/**
 * Read-only report of the live site for the «Диагностика сайта» workflow (.github/workflows/diag.yml).
 * The workflow puts this file into wp-content under a random name, calls it once with the key and deletes it.
 */
// phpcs:ignoreFile -- a temporary diagnostic file, not a part of the plugin.
if ( ! isset( $_GET['k'] ) || '__KEY__' !== $_GET['k'] ) {
	http_response_code( 404 );
	exit;
}
define( 'SHORTINIT', false );
require dirname( __DIR__ ) . '/wp-load.php';
header( 'Content-Type: application/json; charset=utf-8' );
$out = array(
	'plugin' => defined( 'MEMORIAL_PLUGIN_VER' ) ? MEMORIAL_PLUGIN_VER : null,
	'theme'  => wp_get_theme()->get( 'Version' ),
	'db'     => array( get_option( 'memorial_plugin_db' ), get_option( 'memorial_theme_db' ) ),
);
$nav_id         = function_exists( 'memorial_plugin_main_nav_id' ) ? memorial_plugin_main_nav_id() : 0;
$out['nav_id']  = $nav_id;
$out['navs']    = array_map( fn( $p ) => $p->ID . ' ' . $p->post_status . ' ' . $p->post_title, get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'any', 'posts_per_page' => 20 ) ) );
$flat           = array();
$walk           = function ( $blocks, $depth ) use ( &$walk, &$flat ) {
	foreach ( $blocks as $b ) {
		if ( empty( $b['blockName'] ) ) {
			continue;
		}
		$a      = $b['attrs'] ?? array();
		$flat[] = str_repeat( '  ', $depth ) . $b['blockName'] . ' | ' . ( $a['label'] ?? '' ) . ' | id=' . ( $a['id'] ?? '' ) . ' kind=' . ( $a['kind'] ?? '' ) . ' type=' . ( $a['type'] ?? '' ) . ' | ' . urldecode( (string) ( $a['url'] ?? '' ) );
		$walk( $b['innerBlocks'] ?? array(), $depth + 1 );
	}
};
$nav = $nav_id ? get_post( $nav_id ) : null;
if ( $nav ) {
	$walk( parse_blocks( $nav->post_content ), 0 );
}
$out['nav_blocks'] = $flat;
if ( function_exists( 'memorial_plugin_menu_items' ) ) {
	$out['menu_items'] = array_values( array_map( fn( $i ) => $i->ID . ' ←' . $i->menu_item_parent . ' ' . $i->type . '/' . $i->object . '#' . $i->object_id . ' ' . $i->title, memorial_plugin_menu_items() ) );
}
$pages = array();
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $p ) {
	$row = array(
		'id'       => $p->ID,
		'title'    => $p->post_title,
		'slug'     => urldecode( $p->post_name ),
		'parent'   => $p->post_parent,
		'products' => get_post_meta( $p->ID, '_mem_products', true ),
		'in_menu'  => function_exists( 'memorial_plugin_menu_item_for' ) && memorial_plugin_menu_item_for( $p->ID ) ? 1 : 0,
		'children' => function_exists( 'memorial_plugin_menu_children' ) ? count( memorial_plugin_menu_children( $p->ID ) ) : null,
	);
	if ( false !== strpos( $p->post_content, 'wp:memorial-plugin/cards' ) || false !== strpos( $p->post_content, 'mem_cards' ) ) {
		preg_match_all( '~<!-- wp:memorial-plugin/cards(\s+\{.*?\})?\s*/?-->~s', $p->post_content, $m );
		$row['cards_blocks'] = $m[1];
		if ( function_exists( 'memorial_plugin_cards_pages' ) ) {
			$row['cards_pages_self'] = count( memorial_plugin_cards_pages( '', $p->ID ) );
		}
	}
	$pages[] = $row;
}
$out['pages'] = $pages;
echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
