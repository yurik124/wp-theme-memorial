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
// фото главной (LCP): путь блоков до изображения
global $wpdb;
$img_path = function ( $blocks, $trail ) use ( &$img_path ) {
	foreach ( $blocks as $b ) {
		$name = ( $b['blockName'] ?? '' ) . ' ' . wp_json_encode( array_intersect_key( $b['attrs'] ?? array(), array_flip( array( 'className', 'layout', 'width', 'sizeSlug', 'id', 'tagName', 'columns', 'style' ) ) ) );
		if ( 'core/image' === ( $b['blockName'] ?? '' ) && false !== strpos( $b['innerHTML'] ?? '', 'traurnyy' ) ) {
			return array_merge( $trail, array( $name, trim( $b['innerHTML'] ) ) );
		}
		$r = $img_path( $b['innerBlocks'] ?? array(), array_merge( $trail, array( $name ) ) );
		if ( $r ) {
			return $r;
		}
	}
	return null;
};
foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_content LIKE '%traurnyy-zal%'" ) as $pid ) {
	$out['lcp'][ $pid ] = $img_path( parse_blocks( get_post( $pid )->post_content ), array() );
}
// размеры фото товаров
$prod = get_posts( array( 'post_type' => 'mem_product', 'posts_per_page' => 3, 'post_status' => 'publish' ) );
foreach ( $prod as $p ) {
	$m = wp_get_attachment_metadata( get_post_thumbnail_id( $p ) );
	$out['thumb_sizes'][ $p->ID ] = $m ? array( $m['width'] ?? 0, $m['height'] ?? 0, array_map( fn( $x ) => $x['width'] . 'x' . $x['height'], $m['sizes'] ?? array() ) ) : null;
}
$out['image_sizes'] = wp_get_registered_image_subsizes();
// ссылка на отсутствующее фото (404 в консоли)
$out['missing_ref'] = array(
	'posts' => $wpdb->get_results( "SELECT ID, post_type, post_title FROM {$wpdb->posts} WHERE post_content LIKE '%котляров%' OR post_content LIKE '%D0%BA%D0%BE%D1%82%D0%BB%D1%8F%D1%80%D0%BE%D0%B2%' LIMIT 10" ),
	'meta'  => $wpdb->get_results( "SELECT post_id, meta_key, LEFT(meta_value,200) v FROM {$wpdb->postmeta} WHERE meta_value LIKE '%котляров%' LIMIT 10" ),
	'opts'  => $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE '%котляров%' LIMIT 10" ),
	'att'   => $wpdb->get_results( "SELECT ID, guid FROM {$wpdb->posts} WHERE post_type='attachment' AND (guid LIKE '%котляров%' OR post_name LIKE '%kotlyarov%' OR post_title LIKE '%котляров%') LIMIT 10" ),
);
// где в настройках главной адреса загрузок, которых нет на диске
$up = wp_get_upload_dir();
$broken = array();
$scan = function ( $v, $path ) use ( &$scan, &$broken, $up ) {
	if ( is_array( $v ) ) {
		foreach ( $v as $k => $x ) {
			$scan( $x, $path . '/' . $k );
		}
		return;
	}
	if ( is_string( $v ) && preg_match_all( '~(?:https?://[^/"\'\s]+)?/wp-content/uploads/[^"\'\s)]+~u', $v, $m ) ) {
		foreach ( $m[0] as $u ) {
			$rel = rawurldecode( preg_replace( '~^.*?/wp-content/uploads/~', '', $u ) );
			if ( ! is_file( $up['basedir'] . '/' . $rel ) ) {
				$broken[] = array( $path, $u );
			}
		}
	}
};
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'mem\_%' OR option_name LIKE 'memorial\_%'" ) as $o ) {
	$scan( get_option( $o ), $o );
}
foreach ( $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_content LIKE '%/wp-content/uploads/%'" ) as $row ) {
	$scan( $row->post_content, 'post ' . $row->ID );
}
$out['broken_uploads'] = array_slice( $broken, 0, 60 );
$out['broken_count']   = count( $broken );
// холостой прогон исправления адресов фото (core/fix-uploads.php): что нашлось бы, без записи
if ( true ) {
function diag_fixed_upload_url( $url ) {
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
				// the smallest copy not narrower than the old one; none — the file itself.
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
function diag_fix_upload_urls( $value, &$log, $where = '' ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = diag_fix_upload_urls( $v, $log, $where . '/' . $k );
		}
		return $value;
	}
	if ( ! is_string( $value ) || ! str_contains( $value, '/wp-content/uploads/' ) ) {
		return $value;
	}
	return preg_replace_callback(
		'~(?:https?://[^\s"\'()<>,]+)?/wp-content/uploads/[^\s"\'()<>,]+\.(?:jpe?g|png|webp|gif)~iu',
		function ( $m ) use ( &$log, $where ) {
			$new = diag_fixed_upload_url( $m[0] );
			if ( '' === $new ) {
				return $m[0];
			}
			$log[] = array( $where, $m[0], $new );
			return $new;
		},
		$value
	);
}

}
$dry = array();
foreach ( array( 'mem_home', 'memorial_ritual_services_options' ) as $o ) {
	diag_fix_upload_urls( get_option( $o ), $dry, $o );
}
foreach ( $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status IN ( 'publish', 'private', 'future', 'draft' ) AND post_type NOT IN ( 'revision', 'attachment' ) AND post_content LIKE '%/wp-content/uploads/%'" ) as $row ) {
	diag_fix_upload_urls( $row->post_content, $dry, 'post ' . $row->ID . ' ' . get_post_type( $row->ID ) . ' ' . get_post_status( $row->ID ) . ' ' . get_the_title( $row->ID ) );
}
$out['fix_dry']     = $dry;
$out['fix_flag']    = get_option( 'memorial_plugin_fix_uploads_2' );
$out['ritual_used'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = 'memorial_ritual_services_options'" );
echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
