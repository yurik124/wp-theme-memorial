<?php
/**
 * Read-only report of the live site for the «Диагностика сайта» workflow (.github/workflows/diag.yml).
 * The workflow puts this file into wp-content under a random name, calls it once with the key and deletes it.
 *
 * Run of 17.11.2026 (old addresses, Cyr To Lat, SEOPress, pages with the old “Products on the page” field):
 * nothing is written, only read.
 */
// phpcs:ignoreFile -- a temporary diagnostic file, not a part of the plugin.
if ( ! isset( $_GET['k'] ) || '__KEY__' !== $_GET['k'] ) {
	http_response_code( 404 );
	exit;
}
define( 'SHORTINIT', false );
require dirname( __DIR__ ) . '/wp-load.php';
header( 'Content-Type: application/json; charset=utf-8' );
@set_time_limit( 120 );
global $wpdb;

// Values with secrets are not shown.
$mask = function ( $v ) use ( &$mask ) {
	if ( ! is_array( $v ) ) {
		return $v;
	}
	foreach ( $v as $k => $x ) {
		if ( is_string( $k ) && preg_match( '/key|api|token|secret|pass|license/i', $k ) && '' !== $x && null !== $x ) {
			$v[ $k ] = is_scalar( $x ) ? '***' : '*** (array)';
		} else {
			$v[ $k ] = $mask( $x );
		}
	}
	return $v;
};

$out = array(
	'plugin'    => defined( 'MEMORIAL_PLUGIN_VER' ) ? MEMORIAL_PLUGIN_VER : null,
	'theme'     => wp_get_theme()->get( 'Version' ),
	'home'      => home_url( '/' ),
	'siteurl'   => site_url( '/' ),
	'public'    => get_option( 'blog_public' ),
	'permalink' => get_option( 'permalink_structure' ),
	'robots_file' => is_file( ABSPATH . 'robots.txt' ) ? substr( (string) file_get_contents( ABSPATH . 'robots.txt' ), 0, 2000 ) : null,
	'llms_file'   => is_file( ABSPATH . 'llms.txt' ),
);

// Active plugins with versions.
if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$all = get_plugins();
foreach ( (array) get_option( 'active_plugins' ) as $f ) {
	$out['plugins'][] = $f . ' ' . ( $all[ $f ]['Version'] ?? '' );
}

// Cyr To Lat: the whole settings (the tables of letters).
$out['cyr_to_lat'] = array(
	'settings' => get_option( 'cyr_to_lat_settings' ),
	'other'    => $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%cyr%' OR option_name LIKE '%ctl\_%'" ),
);

// SEOPress: all its options, secrets hidden.
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'seopress%' AND option_name NOT LIKE '%transient%'" ) as $o ) {
	$v = get_option( $o );
	if ( is_string( $v ) && strlen( $v ) > 3000 ) {
		$v = substr( $v, 0, 3000 ) . '…';
	}
	$out['seopress'][ $o ] = $mask( $v );
}

// Blog content and authors (for /author/, /feed/).
$out['blog'] = array(
	'posts'      => (int) wp_count_posts( 'post' )->publish,
	'categories' => (int) wp_count_terms( array( 'taxonomy' => 'category' ) ),
	'tags'       => (int) wp_count_terms( array( 'taxonomy' => 'post_tag' ) ),
	'author_ids' => array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','mem_product') LIMIT 10" ) ),
);

// Pages.
$cat_count = function ( $term_id ) {
	return (int) ( new WP_Query(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'tax_query'      => array( array( 'taxonomy' => 'mem_cat', 'terms' => (int) $term_id ) ),
		)
	) )->found_posts;
};
$short = fn( $html ) => mb_substr( trim( preg_replace( '~\s+~u', ' ', wp_strip_all_tags( strip_shortcodes( (string) $html ) ) ) ), 0, 200 );
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) {
	$row = array(
		'id'       => $p->ID,
		'status'   => $p->post_status,
		'title'    => $p->post_title,
		'slug'     => urldecode( $p->post_name ),
		'path'     => urldecode( get_page_uri( $p ) ),
		'parent'   => $p->post_parent,
		'old'      => array_map( 'urldecode', get_post_meta( $p->ID, '_wp_old_slug' ) ),
		'template' => get_post_meta( $p->ID, '_wp_page_template', true ),
		'in_menu'  => function_exists( 'memorial_plugin_menu_item_for' ) && memorial_plugin_menu_item_for( $p->ID ) ? 1 : 0,
		'modified' => $p->post_modified,
	);
	$mode = get_post_meta( $p->ID, '_mem_products', true );
	if ( '' !== (string) $mode ) {
		$parts              = get_extended( (string) $p->post_content );
		$row['products']    = $mode;
		$row['products_n']  = 'all' === $mode ? 'all' : $cat_count( (int) $mode );
		$t                  = 'all' === $mode ? null : get_term( (int) $mode, 'mem_cat' );
		$row['products_cat'] = $t && ! is_wp_error( $t ) ? $t->name . ' (' . $t->slug . ')' : null;
		$row['menu_kids']   = function_exists( 'memorial_plugin_menu_children' ) ? count( memorial_plugin_menu_children( $p->ID ) ) : null;
		$row['has_more']    = '' !== $parts['extended'];
		$row['text_top']    = '' !== $parts['extended'] ? $short( $parts['main'] ) : '';
		$row['text_bottom'] = $short( '' !== $parts['extended'] ? $parts['extended'] : $parts['main'] );
	}
	$mc = get_post_meta( $p->ID, '_mem_cat', true );
	if ( '' !== (string) $mc ) {
		$row['mem_cat'] = $mc;
	}
	if ( has_block( 'memorial-plugin/catalog', $p ) ) {
		preg_match_all( '~<!-- wp:memorial-plugin/catalog(\s+\{.*?\})?\s*/?-->~s', $p->post_content, $m );
		$row['catalog_blocks'] = $m[1];
	}
	$blocks = array();
	foreach ( parse_blocks( $p->post_content ) as $b ) {
		if ( ! empty( $b['blockName'] ) ) {
			$blocks[] = $b['blockName'];
		}
	}
	$row['blocks'] = implode( ', ', array_unique( $blocks ) );
	$out['pages'][] = $row;
}

// Catalog sections.
foreach ( get_terms( array( 'taxonomy' => 'mem_cat', 'hide_empty' => false ) ) as $t ) {
	$out['terms'][] = $t->term_id . ' ←' . $t->parent . ' ' . urldecode( $t->slug ) . ' «' . $t->name . '» n=' . $t->count . ' page=' . ( function_exists( 'memorial_plugin_term_page' ) ? memorial_plugin_term_page( $t->term_id ) : '' );
}

// Products: how many, slugs (for the letter table), which ones have no own page.
$prods                    = get_posts( array( 'post_type' => 'mem_product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
$out['products']['count'] = count( $prods );
$out['products']['mode']  = get_option( 'mem_product_pages' );
$out['products']['slugs'] = array_map( fn( $id ) => urldecode( get_post_field( 'post_name', $id ) ) . ' «' . get_the_title( $id ) . '»', array_slice( $prods, 0, 40 ) );
$out['products']['without_page'] = function_exists( 'memorial_plugin_products_without_page' ) ? count( memorial_plugin_products_without_page() ) : null;
$out['products']['without_page_urls'] = function_exists( 'memorial_plugin_products_without_page' ) ? array_map( 'get_permalink', memorial_plugin_products_without_page() ) : array();

// Slugs with letters of interest (to see the current table at work): every post name and term slug.
$letters = array();
foreach ( $wpdb->get_results( "SELECT ID, post_type, post_title, post_name FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type IN ('page','mem_product','post') " ) as $r ) {
	if ( preg_match( '/[хцчшщйыъьэюяёж]/iu', $r->post_title ) ) {
		$letters[] = $r->post_type . ' ' . $r->ID . ': «' . $r->post_title . '» → ' . urldecode( $r->post_name );
	}
}
$out['slug_samples'] = array_slice( $letters, 0, 80 );

// Main navigation (links of the menu items).
$nav_id = function_exists( 'memorial_plugin_main_nav_id' ) ? memorial_plugin_main_nav_id() : 0;
$flat   = array();
$walk   = function ( $blocks, $depth ) use ( &$walk, &$flat ) {
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
$out['nav_id']     = $nav_id;
$out['nav_blocks'] = $flat;

// Internal links in texts and settings: where each address is used and whether it opens a page.
$host  = wp_parse_url( home_url(), PHP_URL_HOST );
$links = array();
$add   = function ( $url, $where ) use ( &$links, $host ) {
	$url = html_entity_decode( trim( $url ) );
	$u   = wp_parse_url( $url );
	if ( ! $u || ( isset( $u['host'] ) && $u['host'] !== $host && 'memorialbel.ru' !== $u['host'] && 'www.memorialbel.ru' !== $u['host'] ) ) {
		return;
	}
	$path = rawurldecode( $u['path'] ?? '' );
	if ( '' === $path || '/' !== $path[0] || preg_match( '~^/(wp-content|wp-admin|wp-json|wp-includes)/~', $path ) || preg_match( '~\.(jpe?g|png|webp|gif|svg|pdf|css|js)$~i', $path ) ) {
		return;
	}
	$key = ( isset( $u['host'] ) ? $u['host'] : '' ) . $path;
	if ( ! isset( $links[ $key ] ) ) {
		$id             = url_to_postid( home_url( $path ) );
		$links[ $key ]  = array(
			'post'  => $id,
			'where' => array(),
		);
	}
	if ( count( $links[ $key ]['where'] ) < 6 && ! in_array( $where, $links[ $key ]['where'], true ) ) {
		$links[ $key ]['where'][] = $where;
	}
};
foreach ( $wpdb->get_results( "SELECT ID, post_type, post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('page','mem_product','post','wp_navigation','wp_template','wp_template_part','wp_block')" ) as $r ) {
	if ( preg_match_all( '~(?:href|"url")\s*[=:]\s*\\\\?"([^"\\\\]+)~', $r->post_content, $m ) ) {
		foreach ( $m[1] as $u ) {
			$add( $u, $r->post_type . ' ' . $r->ID );
		}
	}
}
$scan = function ( $v, $path ) use ( &$scan, $add ) {
	if ( is_array( $v ) ) {
		foreach ( $v as $k => $x ) {
			$scan( $x, $path . '/' . $k );
		}
		return;
	}
	if ( is_string( $v ) && preg_match_all( '~(?:https?://[^\s"\'<>]+|(?<=["\'])/[a-z0-9][^\s"\'<>]*)~iu', $v, $m ) ) {
		foreach ( $m[0] as $u ) {
			$add( $u, 'option ' . $path );
		}
	}
};
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'mem\_%'" ) as $o ) {
	$scan( get_option( $o ), $o );
}
foreach ( $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_mem\_%' AND meta_value LIKE '%/%' AND meta_value NOT LIKE '%wp-content%' LIMIT 500" ) as $r ) {
	$scan( maybe_unserialize( $r->meta_value ), 'meta ' . $r->post_id . ' ' . $r->meta_key );
}
ksort( $links );
$out['links'] = $links;

// SEOPress fields of the pages (title, description, noindex).
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $p ) {
	$out['seo_meta'][ $p->ID . ' ' . urldecode( get_page_uri( $p ) ) ] = array(
		'title'   => get_post_meta( $p->ID, '_seopress_titles_title', true ),
		'desc'    => get_post_meta( $p->ID, '_seopress_titles_desc', true ),
		'noindex' => get_post_meta( $p->ID, '_seopress_robots_index', true ),
		'h1'      => $p->post_title,
	);
}
// Separate prices: code, kind, section, “In the SEO plugin”, what the site prints.
foreach ( function_exists( 'memorial_plugin_values' ) ? memorial_plugin_values() : array() as $k => $row ) {
	$out['values'][ $k ] = array(
		'name'    => $row['name'] ?? '',
		'what'    => $row['what'] ?? '',
		'section' => $row['section'] ?? '',
		'seo'     => $row['seo'] ?? '',
		'bare'    => $row['bare'] ?? '',
		'text'    => function_exists( 'memorial_plugin_value_text' ) ? memorial_plugin_value_text( $row ) : '',
	);
}
$out['seo_registry'] = function_exists( 'memorial_plugin_seo_registry' ) ? array_keys( memorial_plugin_seo_registry() ) : null;
// Who holds the name “ogradki” (any type and status, also attachments and trash).
$out['ogradki_taken'] = $wpdb->get_results( "SELECT ID, post_type, post_status, post_parent, post_name FROM {$wpdb->posts} WHERE post_name IN ('ogradki','ogradki__trashed','politika-ispolzovaniya-cookie') OR post_name LIKE 'ogradki-%'" );
$out['legal_pages']   = array( get_option( 'mem_legal' ), (int) get_option( 'wp_page_for_privacy_policy' ) );

// Products with a price below 100 ₽ (a placeholder price spoils “from …” in titles).
$out['cheap'] = $wpdb->get_results( "SELECT p.ID, p.post_title, p.post_status, m.meta_value price, (SELECT GROUP_CONCAT(t.slug) FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=r.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id=tt.term_id WHERE r.object_id=p.ID AND tt.taxonomy='mem_cat') cats FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_mem_price' WHERE p.post_type='mem_product' AND p.post_status='publish' AND CAST(m.meta_value AS SIGNED) BETWEEN 1 AND 99" );

echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE );
