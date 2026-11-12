<?php
/**
 * Catalog → Products: all products on DataViews (src/catalog), one tab per product type.
 *
 * Price, “from”, unit, line under the title, specifications of the type, categories, photos, order and
 * visibility are edited in a form right in the list; several products at once — change prices, add or
 * remove categories, show or hide, copy, merge, move to the trash. New products are made from photos:
 * one product per photo. The text of a product is edited in the block editor (admin/editor-meta.php).
 *
 * REST memorial-plugin/v1/catalog/products (edit_posts); the old admin-ajax handlers and the jQuery
 * screen are gone.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menu: “Products” replaces the classic list (it stays reachable for the trash: edit.php?post_status=trash).
 */
function memorial_plugin_products_menu() {
	$parent = 'edit.php?post_type=mem_product';
	$hook   = add_submenu_page( $parent, __( 'Products', 'memorial-plugin' ), __( 'Products', 'memorial-plugin' ), 'edit_posts', 'mem-products', 'memorial_plugin_catalog_views_container', 0 );
	if ( $hook ) {
		add_action( 'load-' . $hook, 'memorial_plugin_catalog_views_assets' );
	}
	remove_submenu_page( $parent, $parent );
	remove_submenu_page( $parent, 'post-new.php?post_type=mem_product' );
}
add_action( 'admin_menu', 'memorial_plugin_products_menu', 30 );

/**
 * The “Catalog” menu item and the classic list lead to the new screen (the trash and search stay classic).
 */
function memorial_plugin_products_redirect() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only reading which screen is open.
	if ( 'mem_product' !== ( $_GET['post_type'] ?? '' ) ) {
		return;
	}
	foreach ( array( 'classic', 'post_status', 's', 'mem_cat', 'trashed', 'untrashed', 'deleted', 'action' ) as $k ) {
		if ( isset( $_GET[ $k ] ) ) {
			return;
		}
	}
	// phpcs:enable
	wp_safe_redirect( admin_url( 'edit.php?post_type=mem_product&page=mem-products' ) );
	exit;
}
add_action( 'load-edit.php', 'memorial_plugin_products_redirect' );

/**
 * Menu label: categories = “Types and categories”.
 *
 * @param array  $args Taxonomy arguments.
 * @param string $tax  Taxonomy.
 * @return array
 */
function memorial_plugin_products_tax_labels( $args, $tax ) {
	if ( 'mem_cat' === $tax ) {
		$args['labels'] = array_merge(
			(array) ( $args['labels'] ?? array() ),
			array(
				'name'      => __( 'Types and categories', 'memorial-plugin' ),
				'menu_name' => __( 'Types and categories', 'memorial-plugin' ),
			)
		);
	}
	return $args;
}
add_filter( 'register_taxonomy_args', 'memorial_plugin_products_tax_labels', 10, 2 );

/**
 * Product types (top categories) in the order set in “Types and categories”.
 *
 * @return WP_Term[]
 */
function memorial_plugin_manager_types() {
	$t = get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'parent'     => 0,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $t ) ) {
		return array();
	}
	usort(
		$t,
		static function ( $a, $b ) {
			$d = (int) get_term_meta( $a->term_id, 'mem_order', true ) <=> (int) get_term_meta( $b->term_id, 'mem_order', true );
			return 0 !== $d ? $d : strcmp( $a->name, $b->name );
		}
	);
	return $t;
}

/**
 * Number of products in a category with its subcategories (all statuses except the trash).
 *
 * @param int $term_id Category ID.
 * @return int
 */
function memorial_plugin_cat_count( $term_id ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- admin screen.
				array(
					'taxonomy'         => 'mem_cat',
					'terms'            => (int) $term_id,
					'include_children' => true,
				),
			),
		)
	);
	return (int) $q->found_posts;
}

/**
 * Subcategories of a type, nested ones with “— ” in front.
 *
 * @param int $type_id Type ID.
 * @param int $depth   Depth.
 * @return array<int,array{id:int,name:string}>
 */
function memorial_plugin_manager_cats( $type_id, $depth = 0 ) {
	$out = array();
	foreach ( memorial_plugin_sorted_children( $type_id ) as $c ) {
		$out[] = array(
			'id'   => (int) $c->term_id,
			'name' => str_repeat( '— ', $depth ) . $c->name,
		);
		$out   = array_merge( $out, memorial_plugin_manager_cats( (int) $c->term_id, $depth + 1 ) );
	}
	return $out;
}

/**
 * Photos as {id, url}.
 *
 * @param int[] $ids Attachment IDs.
 * @return array<int,array{id:int,url:string}>
 */
function memorial_plugin_catalog_photos( $ids ) {
	return array_values(
		array_map(
			static fn( $a ) => array(
				'id'  => (int) $a,
				'url' => (string) wp_get_attachment_image_url( $a, 'thumbnail' ),
			),
			$ids
		)
	);
}

/**
 * Product for the list.
 *
 * @param WP_Post $p Product.
 * @return array
 */
function memorial_plugin_manager_product( $p ) {
	$type = memorial_plugin_product_type( $p->ID );
	$cats = wp_get_object_terms( $p->ID, 'mem_cat', array( 'fields' => 'ids' ) );
	$m    = static fn( $k ) => (string) get_post_meta( $p->ID, $k, true );
	return array(
		'id'     => (int) $p->ID,
		'title'  => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
		'status' => 'publish' === $p->post_status ? 'publish' : 'draft',
		'type'   => $type,
		'photos' => memorial_plugin_catalog_photos( memorial_plugin_product_photos( $p->ID ) ),
		'price'  => (int) $m( '_mem_price' ),
		'from'   => '1' === $m( '_mem_price_from' ),
		'unit'   => $m( '_mem_unit' ),
		'sub'    => $m( '_mem_sub' ),
		'specs'  => (object) memorial_plugin_product_spec_map( $p->ID ),
		'cats'   => array_values( array_diff( is_wp_error( $cats ) ? array() : array_map( 'intval', $cats ), array( $type ) ) ),
		'order'  => (int) $p->menu_order,
		'edit'   => (string) get_edit_post_link( $p->ID, 'raw' ),
		'view'   => '' !== trim( wp_strip_all_tags( $p->post_content ) ) ? (string) get_permalink( $p ) : '',
	);
}

/**
 * Types with their specifications and categories, and all products.
 *
 * @return array
 */
function memorial_plugin_products_data() {
	$types = array();
	foreach ( memorial_plugin_manager_types() as $t ) {
		$types[] = array(
			'id'     => (int) $t->term_id,
			'name'   => $t->name,
			'fields' => memorial_plugin_type_fields( (int) $t->term_id ),
			'cats'   => memorial_plugin_manager_cats( (int) $t->term_id ),
		);
	}
	$posts = get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'ASC',
			),
		)
	);
	return array(
		'types'     => $types,
		'items'     => array_map( 'memorial_plugin_manager_product', $posts ),
		'canManage' => current_user_can( 'manage_categories' ),
		'typesUrl'  => admin_url( 'edit.php?post_type=mem_product&page=mem-types' ),
		'trashUrl'  => admin_url( 'edit.php?post_status=trash&post_type=mem_product' ),
	);
}

/**
 * New product of a type (at the end of the type), with a photo or without.
 *
 * @param int    $type  Type ID.
 * @param int    $att   Photo ID or 0.
 * @param string $title Title.
 * @return int Product ID or 0.
 */
function memorial_plugin_cm_create( $type, $att, $title = '' ) {
	$last  = get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'orderby'        => 'menu_order',
			'order'          => 'DESC',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one product.
				array(
					'taxonomy'         => 'mem_cat',
					'terms'            => $type,
					'include_children' => true,
				),
			),
		)
	);
	$order = $last ? (int) $last[0]->menu_order + 1 : 1;
	$id    = wp_insert_post(
		array(
			'post_type'   => 'mem_product',
			'post_status' => 'publish',
			'post_title'  => '' !== $title ? $title : __( 'New product', 'memorial-plugin' ),
			'menu_order'  => $order,
		)
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	wp_set_object_terms( $id, array( $type ), 'mem_cat' );
	update_post_meta( $id, '_mem_price', 0 );
	memorial_plugin_apply_type_defaults( $id, $type );
	if ( $att ) {
		set_post_thumbnail( $id, $att );
	}
	return (int) $id;
}

/**
 * Set the photos of a product: the first is the featured image, the rest go to the gallery.
 *
 * @param int   $id  Product ID.
 * @param int[] $ids Photo IDs.
 */
function memorial_plugin_product_set_photos( $id, $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ), 'wp_attachment_is_image' ) ) );
	$ids ? set_post_thumbnail( $id, $ids[0] ) : delete_post_thumbnail( $id );
	count( $ids ) > 1 ? update_post_meta( $id, '_mem_gallery', implode( ',', array_slice( $ids, 1 ) ) ) : delete_post_meta( $id, '_mem_gallery' );
}

/**
 * Save the fields of a product from the list.
 *
 * @param int   $id     Product ID.
 * @param array $values Field => value.
 */
function memorial_plugin_product_save( $id, $values ) {
	$post = array();
	foreach ( $values as $f => $v ) {
		switch ( $f ) {
			case 'title':
				$t                  = trim( sanitize_text_field( (string) $v ) );
				$post['post_title'] = '' !== $t ? $t : __( 'Untitled', 'memorial-plugin' );
				break;
			case 'status':
				$post['post_status'] = 'publish' === $v ? 'publish' : 'draft';
				break;
			case 'order':
				$post['menu_order'] = (int) $v;
				break;
			case 'price':
				update_post_meta( $id, '_mem_price', absint( preg_replace( '/\D/', '', (string) $v ) ) );
				break;
			case 'from':
				update_post_meta( $id, '_mem_price_from', empty( $v ) ? '' : '1' );
				break;
			case 'unit':
			case 'sub':
				$v = trim( sanitize_text_field( (string) $v ) );
				'' !== $v ? update_post_meta( $id, '_mem_' . $f, $v ) : delete_post_meta( $id, '_mem_' . $f );
				break;
			case 'specs':
				foreach ( (array) $v as $label => $value ) {
					memorial_plugin_set_spec( $id, sanitize_text_field( (string) $label ), (string) $value );
				}
				break;
			case 'cats':
				$type = memorial_plugin_product_type( $id );
				$ids  = array_values(
					array_filter(
						array_map( 'absint', (array) $v ),
						static fn( $c ) => $c && $c !== $type && memorial_plugin_type_root( $c ) === $type
					)
				);
				// Without categories the product lies right in its type; with them — only in them (otherwise the site shows it twice).
				if ( $type ) {
					wp_set_object_terms( $id, $ids ? $ids : array( $type ), 'mem_cat' );
				}
				break;
			case 'photos':
				memorial_plugin_product_set_photos( $id, array_map( static fn( $p ) => is_array( $p ) ? ( $p['id'] ?? 0 ) : $p, (array) $v ) );
				break;
		}
	}
	if ( $post ) {
		$post['ID'] = $id;
		wp_update_post( wp_slash( $post ) );
	}
}

/**
 * What prevents merging products: fields with different values.
 *
 * @param int[] $ids Product IDs.
 * @return string[]
 */
function memorial_plugin_cm_merge_conflicts( array $ids ) {
	$vals = array();
	foreach ( $ids as $id ) {
		$row = array(
			__( 'Price', 'memorial-plugin' )            => (int) get_post_meta( $id, '_mem_price', true ) ? (string) (int) get_post_meta( $id, '_mem_price', true ) : '',
			__( 'Unit', 'memorial-plugin' )             => trim( (string) get_post_meta( $id, '_mem_unit', true ) ),
			__( 'Line under title', 'memorial-plugin' ) => trim( (string) get_post_meta( $id, '_mem_sub', true ) ),
		);
		foreach ( memorial_plugin_product_spec_map( $id ) as $k => $v ) {
			$row[ $k ] = trim( (string) $v );
		}
		foreach ( $row as $k => $v ) {
			if ( '' !== $v && '0' !== $v ) {
				$vals[ $k ][ $v ][] = get_the_title( $id );
			}
		}
	}
	$out = array();
	foreach ( $vals as $k => $variants ) {
		if ( count( $variants ) > 1 ) {
			$out[] = $k . ': ' . implode( ' / ', array_map( static fn( $v, $who ) => $v . ' (' . implode( ', ', $who ) . ')', array_keys( $variants ), $variants ) );
		}
	}
	return $out;
}

/**
 * Merge products into the first one: photos of all, missing fields, all categories; the rest go to the trash
 * and their old addresses lead to the merged product.
 *
 * @param int[] $ids Product IDs, the main one first.
 * @return int Main product ID.
 */
function memorial_plugin_cm_merge( array $ids ) {
	$main   = $ids[0];
	$type   = memorial_plugin_product_type( $main );
	$photos = array();
	foreach ( $ids as $id ) {
		foreach ( memorial_plugin_product_photos( $id ) as $p ) {
			if ( ! in_array( $p, $photos, true ) ) {
				$photos[] = $p;
			}
		}
	}
	if ( $photos ) {
		memorial_plugin_product_set_photos( $main, $photos );
	}
	$cats    = array();
	$is_zero = static fn( $v ) => '' === (string) $v || '0' === (string) $v;
	foreach ( $ids as $id ) {
		if ( $id !== $main ) {
			foreach ( array( '_mem_price', '_mem_price_from', '_mem_unit', '_mem_sub', '_mem_price_old', '_mem_on_order' ) as $k ) {
				$v = get_post_meta( $id, $k, true );
				if ( ! $is_zero( $v ) && $is_zero( get_post_meta( $main, $k, true ) ) ) {
					update_post_meta( $main, $k, $v );
				}
			}
			foreach ( memorial_plugin_product_spec_map( $id ) as $k => $v ) {
				if ( '' !== trim( (string) $v ) && '' === trim( (string) ( memorial_plugin_product_spec_map( $main )[ $k ] ?? '' ) ) ) {
					memorial_plugin_set_spec( $main, $k, $v );
				}
			}
			if ( '' === trim( get_post_field( 'post_content', $main ) ) && '' !== trim( get_post_field( 'post_content', $id ) ) ) {
				wp_update_post(
					array(
						'ID'           => $main,
						'post_content' => wp_slash( get_post_field( 'post_content', $id ) ),
					)
				);
			}
		}
		$cats = array_merge( $cats, array_map( 'intval', wp_get_object_terms( $id, 'mem_cat', array( 'fields' => 'ids' ) ) ) );
	}
	$cats = array_values( array_unique( array_diff( $cats, array( $type ) ) ) );
	wp_set_object_terms( $main, $cats ? $cats : array( $type ), 'mem_cat' );
	foreach ( array_slice( $ids, 1 ) as $id ) {
		update_post_meta( $id, '_mem_merged_into', $main );
		wp_trash_post( $id );
	}
	return $main;
}

/**
 * Copy of a post (product or work) with its meta and terms, right after the original.
 *
 * @param int      $id       Post ID.
 * @param string[] $keys     Meta keys to copy.
 * @param string   $taxonomy Taxonomy to copy.
 * @return int New post ID or 0.
 */
function memorial_plugin_catalog_copy( $id, $keys, $taxonomy ) {
	$p   = get_post( $id );
	$new = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => $p->post_type,
				'post_status'  => $p->post_status,
				/* translators: %s: title of the original. */
				'post_title'   => sprintf( __( '%s (copy)', 'memorial-plugin' ), $p->post_title ),
				'post_content' => $p->post_content,
				'menu_order'   => $p->menu_order,
			)
		)
	);
	if ( is_wp_error( $new ) || ! $new ) {
		return 0;
	}
	foreach ( array_merge( $keys, array( '_thumbnail_id' ) ) as $k ) {
		$v = get_post_meta( $id, $k, true );
		if ( '' !== $v ) {
			update_post_meta( $new, $k, wp_slash( $v ) );
		}
	}
	wp_set_object_terms( $new, wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) ), $taxonomy );
	return (int) $new;
}

/**
 * IDs from a request that are posts of the type the user may edit.
 *
 * @param mixed  $ids  IDs.
 * @param string $type Post type.
 * @return int[]
 */
function memorial_plugin_catalog_ids( $ids, $type ) {
	return array_values(
		array_unique(
			array_filter(
				array_map( 'absint', (array) $ids ),
				static fn( $id ) => $id && get_post_type( $id ) === $type && current_user_can( 'edit_post', $id )
			)
		)
	);
}

/**
 * Bulk actions on products.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function memorial_plugin_products_bulk( WP_REST_Request $request ) {
	$ids = memorial_plugin_catalog_ids( $request['ids'], 'mem_product' );
	$op  = sanitize_key( (string) $request['op'] );
	if ( ! $ids ) {
		return new WP_Error( 'memorial_plugin_nothing', __( 'No products selected.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}
	$out     = array();
	$trashed = array();
	if ( 'order' === $op ) {
		// The IDs in their new order (a whole type or the whole list) get menu_order 1, 2, 3 and so on.
		foreach ( $ids as $i => $id ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'menu_order' => $i + 1,
				)
			);
		}
		return rest_ensure_response(
			array(
				'items'   => array_map( static fn( $id ) => memorial_plugin_manager_product( get_post( $id ) ), $ids ),
				'trashed' => array(),
			)
		);
	}
	if ( 'merge' === $op ) {
		if ( count( $ids ) < 2 ) {
			return new WP_Error( 'memorial_plugin_merge', __( 'Select at least two products.', 'memorial-plugin' ), array( 'status' => 400 ) );
		}
		$conflicts = memorial_plugin_cm_merge_conflicts( $ids );
		if ( $conflicts ) {
			return rest_ensure_response( array( 'conflicts' => $conflicts ) );
		}
		$main = memorial_plugin_cm_merge( $ids );
		return rest_ensure_response(
			array(
				'items'   => array( memorial_plugin_manager_product( get_post( $main ) ) ),
				'trashed' => array_slice( $ids, 1 ),
			)
		);
	}
	foreach ( $ids as $id ) {
		switch ( $op ) {
			case 'price':
				$cur  = (int) get_post_meta( $id, '_mem_price', true );
				$mode = (string) $request['mode'];
				if ( ! $cur && 'set' !== $mode ) {
					continue 2; // “On request” stays as it is when prices go up or down.
				}
				$val = (float) str_replace( ',', '.', (string) $request['value'] );
				$new = match ( $mode ) {
					'pct'   => $cur * ( 1 + $val / 100 ),
					'add'   => $cur + $val,
					'set'   => $val,
					default => $cur,
				};
				$round = max( 1, absint( $request['round'] ) );
				update_post_meta( $id, '_mem_price', max( 0, (int) ( round( $new / $round ) * $round ) ) );
				break;
			case 'cats_add':
			case 'cats_remove':
				$type = memorial_plugin_product_type( $id );
				$cats = array_filter( array_map( 'absint', (array) $request['cats'] ), static fn( $c ) => $c && $c !== $type && memorial_plugin_type_root( $c ) === $type );
				$have = array_diff( array_map( 'intval', wp_get_object_terms( $id, 'mem_cat', array( 'fields' => 'ids' ) ) ), array( $type ) );
				$have = 'cats_add' === $op ? array_unique( array_merge( $have, $cats ) ) : array_diff( $have, $cats );
				wp_set_object_terms( $id, $have ? array_values( $have ) : array( $type ), 'mem_cat' );
				break;
			case 'publish':
			case 'draft':
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => $op,
					)
				);
				break;
			case 'copy':
				$id = memorial_plugin_catalog_copy( $id, array( '_mem_price', '_mem_price_from', '_mem_unit', '_mem_sub', '_mem_specs', '_mem_price_old', '_mem_on_order', '_mem_gallery' ), 'mem_cat' );
				break;
			case 'trash':
				wp_trash_post( $id );
				$trashed[] = $id;
				continue 2;
			default:
				return new WP_Error( 'memorial_plugin_op', __( 'Unknown action.', 'memorial-plugin' ), array( 'status' => 400 ) );
		}
		if ( $id ) {
			$out[] = memorial_plugin_manager_product( get_post( $id ) );
		}
	}
	return rest_ensure_response(
		array(
			'items'   => $out,
			'trashed' => $trashed,
		)
	);
}

/**
 * REST routes of the product list.
 */
function memorial_plugin_products_rest() {
	$ns   = 'memorial-plugin/v1';
	$can  = static fn() => current_user_can( 'edit_posts' );
	$item = static fn( WP_REST_Request $r ) => current_user_can( 'edit_post', (int) $r['id'] ) && 'mem_product' === get_post_type( (int) $r['id'] );
	register_rest_route(
		$ns,
		'/catalog/products',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'callback'            => static fn() => rest_ensure_response( memorial_plugin_products_data() ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => $can,
				'args'                => array(
					'type'   => array(
						'type'     => 'integer',
						'required' => true,
					),
					'photos' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$type = (int) $request['type'];
					if ( ! $type || ! term_exists( $type, 'mem_cat' ) || memorial_plugin_type_root( $type ) !== $type ) {
						return new WP_Error( 'memorial_plugin_type', __( 'Unknown product type.', 'memorial-plugin' ), array( 'status' => 400 ) );
					}
					$out    = array();
					$photos = array_filter( array_map( 'absint', (array) $request['photos'] ), 'wp_attachment_is_image' );
					foreach ( $photos ? $photos : array( 0 ) as $att ) {
						$id = memorial_plugin_cm_create( $type, $att );
						if ( $id ) {
							$out[] = memorial_plugin_manager_product( get_post( $id ) );
						}
					}
					return rest_ensure_response( array( 'items' => $out ) );
				},
			),
		)
	);
	register_rest_route(
		$ns,
		'/catalog/products/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'permission_callback' => $item,
			'args'                => array(
				'values' => array(
					'type'     => 'object',
					'required' => true,
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				memorial_plugin_product_save( (int) $request['id'], (array) $request['values'] );
				return rest_ensure_response( memorial_plugin_manager_product( get_post( (int) $request['id'] ) ) );
			},
		)
	);
	register_rest_route(
		$ns,
		'/catalog/products/bulk',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => $can,
			'args'                => array(
				'ids' => array(
					'type'     => 'array',
					'items'    => array( 'type' => 'integer' ),
					'required' => true,
				),
				'op'  => array(
					'type'     => 'string',
					'enum'     => array( 'order', 'price', 'cats_add', 'cats_remove', 'publish', 'draft', 'copy', 'trash', 'merge' ),
					'required' => true,
				),
			),
			'callback'            => 'memorial_plugin_products_bulk',
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_products_rest' );

/**
 * Container of the list screens (products, works, kinds of works).
 */
function memorial_plugin_catalog_views_container() {
	$screens = array(
		'mem-products'   => 'products',
		'mem-works'      => 'works',
		'mem-work-types' => 'work-types',
	);
	$page    = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only which screen is open.
	if ( ! is_readable( MEMORIAL_PLUGIN_DIR . '/build/catalog/index.asset.php' ) ) {
		echo '<div class="wrap"><p>' . esc_html__( 'The JavaScript build is missing. Run npm run build.', 'memorial-plugin' ) . '</p></div>';
		return;
	}
	echo '<div id="memorial-plugin-catalog" class="wrap memorial-plugin-admin" data-screen="' . esc_attr( $screens[ $page ] ?? 'products' ) . '"></div>';
}

/**
 * Scripts of the list screens.
 */
function memorial_plugin_catalog_views_assets() {
	add_action(
		'admin_enqueue_scripts',
		static function () {
			$asset_file = MEMORIAL_PLUGIN_DIR . '/build/catalog/index.asset.php';
			if ( ! is_readable( $asset_file ) ) {
				return;
			}
			$asset = require $asset_file;
			wp_enqueue_media();
			wp_enqueue_script( 'memorial-plugin-catalog', MEMORIAL_PLUGIN_URL . '/build/catalog/index.js', $asset['dependencies'], $asset['version'], array( 'in_footer' => true ) );
			wp_set_script_translations( 'memorial-plugin-catalog', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
			wp_enqueue_style( 'wp-components' );
			if ( is_readable( MEMORIAL_PLUGIN_DIR . '/build/catalog/style-index.css' ) ) {
				wp_enqueue_style( 'memorial-plugin-catalog', MEMORIAL_PLUGIN_URL . '/build/catalog/style-index.css', array( 'wp-components' ), $asset['version'] );
			}
		}
	);
}

/**
 * The address of a product merged into another one (now in the trash) leads to that product.
 */
function memorial_plugin_merged_redirect() {
	if ( ! is_404() ) {
		return;
	}
	$slug = get_query_var( 'name' ) ? get_query_var( 'name' ) : get_query_var( 'mem_product' );
	if ( ! $slug ) {
		return;
	}
	$find = static fn( $name ) => get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'trash',
			'name'           => $name,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	$old  = $find( $slug . '__trashed' );
	$old  = $old ? $old : $find( $slug );
	$to   = $old ? (int) get_post_meta( $old[0], '_mem_merged_into', true ) : 0;
	if ( $to && 'publish' === get_post_status( $to ) ) {
		wp_safe_redirect( get_permalink( $to ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'memorial_plugin_merged_redirect' );
