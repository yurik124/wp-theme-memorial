<?php
/**
 * Товары на обычных страницах сайта: блок «Каталог» в тексте страницы показывает раздел каталога
 * (раздел страницы запоминается в поле _mem_cat при сохранении, catalog/catalog-front.php).
 *
 * Ссылки на разделы и на каталог по всему сайту ведут на эти страницы,
 * а старые адреса /catalog/... перенаправляются на них (301).
 * Прежний способ — поле «Товары на странице» (_mem_products) с листанием по 24 — убран 17.11.2026.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/* Колонка в списке страниц: видно, где какие товары */
add_filter(
	'manage_page_posts_columns',
	function ( $c ) {
		$c['mem_products'] = __( 'Products', 'memorial-plugin' );
		return $c;
	}
);
add_action(
	'manage_page_posts_custom_column',
	function ( $col, $id ) {
		if ( 'mem_products' !== $col ) {
			return;
		}
		$v = (int) get_post_meta( $id, '_mem_cat', true );
		if ( $v && get_term( (int) $v, 'mem_cat' ) instanceof WP_Term ) {
			echo esc_html( get_term( (int) $v, 'mem_cat' )->name );
		} else {
			echo '—';
		}
	},
	10,
	2
);

/* ---------- Связи «категория ↔ страница» ---------- */

/** [term_id => id страницы с блоком «Каталог» этого раздела, …] */
function memorial_plugin_page_map() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map   = array();
	// Страницы с каталогом в тексте ([mem_catalog]).
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_mem_cat',
			'orderby'        => 'menu_order ID',
			'order'          => 'ASC',
		)
	);
	foreach ( $pages as $id ) {
		$map[ (string) get_post_meta( $id, '_mem_cat', true ) ] ??= $id;
	}
	return $map;
}

/**
 * Catalog page.
 */
function memorial_plugin_catalog_page() {
	return (int) ( memorial_plugin_page_map()['all'] ?? 0 ); }
/**
 * Term page.
 *
 * @param int $term_id Term ID.
 */
function memorial_plugin_term_page( $term_id ) {
	return (int) ( memorial_plugin_page_map()[ (string) $term_id ] ?? 0 ); }

/** Ссылка на каталог: страница «Все категории», а если её нет — первая страница с товарами */
function memorial_plugin_catalog_url() {
	$id = memorial_plugin_catalog_page();
	if ( $id ) {
		return get_permalink( $id );
	}
	$map = memorial_plugin_page_map();
	return $map ? get_permalink( reset( $map ) ) : home_url( '/' );
}

add_filter(
	'post_type_archive_link',
	function ( $link, $type ) {
		if ( 'mem_product' === $type && memorial_plugin_page_map() ) {
			return memorial_plugin_catalog_url();
		}
		return $link;
	},
	10,
	2
);

add_filter(
	'term_link',
	function ( $link, $term, $tax ) {
		if ( 'mem_cat' !== $tax ) {
			return $link;
		}
		$id = memorial_plugin_term_page( $term->term_id );
		if ( $id ) {
			return get_permalink( $id );
		}
		// Подкатегория без своей страницы — раздел на странице родителя.
		$t     = $term;
		$guard = 0;
		while ( $t->parent && $guard++ < 5 ) {
			$t = get_term( $t->parent, 'mem_cat' );
			if ( ! $t || is_wp_error( $t ) ) {
				break;
			}
			$id = memorial_plugin_term_page( $t->term_id );
			if ( $id ) {
				return get_permalink( $id ) . '#' . memorial_plugin_term_anchor( $term );
			}
		}
		return $link;
	},
	10,
	3
);

/* Старые адреса /catalog/... → на страницы */
add_action(
	'template_redirect',
	function () {
		$to = '';
		if ( is_post_type_archive( 'mem_product' ) && memorial_plugin_page_map() ) {
			$to = memorial_plugin_catalog_url();
		} elseif ( is_tax( 'mem_cat' ) ) {
			$id = memorial_plugin_term_page( get_queried_object_id() );
			$l  = $id ? '' : get_term_link( get_queried_object() );
			if ( $id ) {
				$to = get_permalink( $id );
			} elseif ( ! is_wp_error( $l ) && false !== strpos( $l, '#' ) ) {
				$to = $l;
			} // подкатегория — раздел на странице родителя.
		}
		if ( $to ) {
			if ( ! empty( $_GET['sort'] ) ) {
				$to = add_query_arg( 'sort', sanitize_key( $_GET['sort'] ), $to );
			}
			wp_safe_redirect( $to, 301 );
			exit;
		}
	}
);

/* ---------- Вывод ---------- */

/** Плитки категорий (главная и страница «Все категории») */
/* ---------- Как фото страницы показывается в плитке каталога ---------- */
add_action(
	'init',
	function () {
		$auth = fn() => current_user_can( 'edit_pages' );
		register_post_meta(
			'page',
			'_mem_tile_pos',
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
		register_post_meta(
			'page',
			'_mem_tile_fit',
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
		register_post_meta(
			'page',
			'_mem_hide_thumb',
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
		register_post_meta(
			'page',
			'_mem_tile_bg',
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
	}
);

add_action(
	'enqueue_block_editor_assets',
	function () {
		$s = get_current_screen();
		if ( ! $s || 'page' !== $s->post_type ) {
			return;
		}
		$f = '/assets/js/tile-panel.js';
		wp_enqueue_script(
			'mem-tile-panel',
			MEMORIAL_PLUGIN_URL . $f,
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-hooks' ),
			filemtime( MEMORIAL_PLUGIN_DIR . $f ),
			true
		);
	}
);

/** Название модели для сравнения: без кавычек, пробелов и регистра
 *
 * @param string $s Name.
 */
function memorial_plugin_norm_name( $s ) {
	$s = mb_strtolower( html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ) );
	return preg_replace( '/[\s«»"\'„“”‹›\-–—.,№#]+/u', '', $s );
}

/** Товары категории страницы (с подкатегориями), по нормализованному названию
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_page_products( $page_id ) {
	$cat = (int) get_post_meta( $page_id, '_mem_cat', true );
	if ( ! $cat ) {
		return array();
	}
	$map = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy'         => 'mem_cat',
					'terms'            => $cat,
					'include_children' => true,
				),
			),
		)
	) as $p ) {
		$map[ memorial_plugin_norm_name( $p->post_title ) ] = $p;
	}
	return $map;
}

/* ---------- Структура главного меню (блок «Навигация») ---------- */

/**
 * ID of the site's main menu — a Navigation menu (wp_navigation). The theme names it (filter
 * memorial_plugin_main_nav_id); in another theme it is the most recent published Navigation menu,
 * the one the Navigation block shows when no menu is chosen.
 *
 * @return int
 */
function memorial_plugin_main_nav_id() {
	$id = (int) apply_filters( 'memorial_plugin_main_nav_id', 0 );
	if ( $id ) {
		return $id;
	}
	$latest = get_posts(
		array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return (int) ( $latest[0] ?? 0 );
}

/**
 * The post an address of the site leads to (a menu link that keeps only the address), 0 if none.
 * Also an address whose page was renamed to Latin (Cyr To Lat): the path is decoded and looked up.
 *
 * @param string $url Address.
 * @return int
 */
function memorial_plugin_menu_url_post( $url ) {
	static $cache = null;
	if ( null === $cache ) {
		$cache = get_transient( 'memorial_plugin_menu_urls' );
		$cache = is_array( $cache ) ? $cache : array();
	}
	if ( isset( $cache[ $url ] ) ) {
		return (int) $cache[ $url ];
	}
	$id = 0;
	if ( '' !== $url && '#' !== $url[0] ) {
		$own  = str_starts_with( $url, '/' ) || wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST );
		$id   = $own ? (int) url_to_postid( $url ) : 0;
		$path = trim( urldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ), '/' );
		if ( ! $id && '' !== $path ) {
			$page = get_page_by_path( $path );
			$id   = $page ? (int) $page->ID : 0;
		}
	}
	$cache[ $url ] = $id;
	// Kept for a day; a page or the menu saved — looked up again.
	set_transient( 'memorial_plugin_menu_urls', $cache, DAY_IN_SECONDS );
	return $id;
}
add_action( 'save_post_page', static fn() => delete_transient( 'memorial_plugin_menu_urls' ) );
add_action( 'save_post_wp_navigation', static fn() => delete_transient( 'memorial_plugin_menu_urls' ) );

/**
 * Items of the main menu, in order: [item id => item]. Each item has the fields of a classic menu item
 * that the plugin uses — ID, menu_item_parent, title, url, target, type (post_type / taxonomy / custom),
 * object (page, post…), object_id — built from the Navigation links and submenus of the menu.
 *
 * @param bool $fresh Build again (not the copy of this request).
 * @return array<int,object>
 */
function memorial_plugin_menu_items( $fresh = false ) {
	static $items = null;
	if ( null !== $items && ! $fresh ) {
		return $items;
	}
	$items = array();
	$nav   = get_post( memorial_plugin_main_nav_id() );
	if ( ! $nav instanceof WP_Post || 'wp_navigation' !== $nav->post_type ) {
		return $items;
	}
	$next = 0;
	$walk = static function ( $blocks, $parent ) use ( &$walk, &$items, &$next ) {
		foreach ( $blocks as $b ) {
			$name = (string) ( $b['blockName'] ?? '' );
			$a    = (array) ( $b['attrs'] ?? array() );
			if ( in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu', 'core/home-link' ), true ) ) {
				$kind = (string) ( $a['kind'] ?? '' );
				$oid  = (int) ( $a['id'] ?? 0 );
				$url  = (string) ( $a['url'] ?? '' );
				// A link without the page ID (only its address) — the page by the address of the site.
				if ( ! $oid && '' !== $url && 'core/home-link' !== $name ) {
					$found = memorial_plugin_menu_url_post( $url );
					if ( $found ) {
						$oid  = $found;
						$kind = 'post-type';
						$a   += array( 'type' => (string) get_post_type( $found ) );
					}
				}
				if ( 'core/home-link' === $name ) {
					$url = home_url( '/' );
				} elseif ( $oid && 'post-type' === $kind && get_post_status( $oid ) ) {
					$url = (string) get_permalink( $oid );
				} elseif ( $oid && 'taxonomy' === $kind ) {
					$link = get_term_link( $oid );
					$url  = is_wp_error( $link ) ? $url : $link;
				}
				$label = (string) ( $a['label'] ?? '' );
				if ( '' === $label && 'core/home-link' === $name ) {
					$label = __( 'Home', 'memorial-plugin' );
				}
				$id           = ++$next;
				$items[ $id ] = (object) array(
					'ID'               => $id,
					'menu_item_parent' => $parent,
					'title'            => trim( wp_strip_all_tags( html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) ) ),
					'url'              => $url,
					'target'           => empty( $a['opensInNewTab'] ) ? '' : '_blank',
					'type'             => 'post-type' === $kind ? 'post_type' : ( 'taxonomy' === $kind ? 'taxonomy' : 'custom' ),
					'object'           => (string) ( $a['type'] ?? '' ),
					'object_id'        => $oid,
				);
				$walk( (array) ( $b['innerBlocks'] ?? array() ), $id );
			} elseif ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'], $parent );
			}
		}
	};
	$walk( parse_blocks( (string) $nav->post_content ), 0 );
	return $items;
}

/** Пункт меню, ведущий на страницу
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_menu_item_for( $page_id ) {
	foreach ( memorial_plugin_menu_items() as $it ) {
		if ( 'post_type' === $it->type && (int) $it->object_id === (int) $page_id ) {
			return $it;
		}
	}
	return null;
}

/** Цепочка родительских пунктов меню над страницей: [[название, ссылка], …] (без самой страницы)
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_menu_chain( $page_id ) {
	$it = memorial_plugin_menu_item_for( $page_id );
	if ( ! $it ) {
		return null;
	}
	$items = memorial_plugin_menu_items();
	$out   = array();
	$guard = 0;
	while ( $it->menu_item_parent && isset( $items[ $it->menu_item_parent ] ) && $guard++ < 10 ) {
		$it    = $items[ $it->menu_item_parent ];
		$url   = $it->url && '#' !== $it->url ? $it->url : '';
		$out[] = array( $it->title, $url );
	}
	return array_reverse( $out );
}

/** Подпункты меню под страницей, которые ведут на страницы
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_menu_children( $page_id ) {
	$it = memorial_plugin_menu_item_for( $page_id );
	if ( ! $it ) {
		return array();
	}
	return array_values( array_filter( memorial_plugin_menu_items(), fn( $c ) => (int) $c->menu_item_parent === (int) $it->ID && 'post_type' === $c->type ) );
}
