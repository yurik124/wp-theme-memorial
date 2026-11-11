<?php
/**
 * Catalog → Types and categories (src/catalog/types.js): product types on tabs; for a type — its name and
 * description, what a new product gets by default, how the catalog looks on the site, the specifications
 * of the type and its categories (a DataViews list: rename, description, order, delete, add).
 *
 * REST memorial-plugin/v1/catalog/types and /catalog/cats (manage_categories).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menu item; the classic category list leads here (direct links to editing a term stay).
 */
function memorial_plugin_types_menu() {
	$parent = 'edit.php?post_type=mem_product';
	$hook   = add_submenu_page( $parent, __( 'Types and categories', 'memorial-plugin' ), __( 'Types and categories', 'memorial-plugin' ), 'manage_categories', 'mem-types', 'memorial_plugin_types_container', 1 );
	if ( $hook ) {
		add_action( 'load-' . $hook, 'memorial_plugin_catalog_views_assets' );
	}
	remove_submenu_page( $parent, 'edit-tags.php?taxonomy=mem_cat&amp;post_type=mem_product' );
	remove_submenu_page( $parent, 'edit-tags.php?taxonomy=mem_cat&post_type=mem_product' );
}
add_action( 'admin_menu', 'memorial_plugin_types_menu', 31 );

/**
 * Container of the screen.
 */
function memorial_plugin_types_container() {
	if ( ! is_readable( MEMORIAL_PLUGIN_DIR . '/build/catalog/index.asset.php' ) ) {
		echo '<div class="wrap"><p>' . esc_html__( 'The JavaScript build is missing. Run npm run build.', 'memorial-plugin' ) . '</p></div>';
		return;
	}
	echo '<div id="memorial-plugin-catalog" class="wrap" data-screen="types"></div>';
}

/**
 * The classic list of categories leads to the new screen.
 */
function memorial_plugin_types_redirect() {
	// phpcs:disable WordPress.Security.NonceVerification -- only reading which screen is open.
	if ( 'mem_cat' !== ( $_GET['taxonomy'] ?? '' ) || isset( $_GET['classic'] ) || isset( $_GET['action'] ) || ! empty( $_POST ) ) {
		return;
	}
	// phpcs:enable
	wp_safe_redirect( admin_url( 'edit.php?post_type=mem_product&page=mem-types' ) );
	exit;
}
add_action( 'load-edit-tags.php', 'memorial_plugin_types_redirect' );

/**
 * Categories of a type with nesting and number of products.
 *
 * @param int $parent Parent category.
 * @param int $depth  Depth.
 * @return array<int,array>
 */
function memorial_plugin_types_cats( $parent, $depth = 0 ) {
	$out = array();
	foreach ( memorial_plugin_sorted_children( $parent ) as $i => $c ) {
		$out[] = array(
			'id'     => (int) $c->term_id,
			'name'   => $c->name,
			'depth'  => $depth,
			'parent' => (int) $c->parent,
			'count'  => memorial_plugin_cat_count( $c->term_id ),
			'desc'   => $c->description,
			'pos'    => $i,
		);
		$out   = array_merge( $out, memorial_plugin_types_cats( (int) $c->term_id, $depth + 1 ) );
	}
	return $out;
}

/**
 * Everything about one type.
 *
 * @param int $type_id Type ID.
 * @return array|null
 */
function memorial_plugin_types_data( $type_id ) {
	$t = get_term( $type_id, 'mem_cat' );
	if ( ! $t || is_wp_error( $t ) ) {
		return null;
	}
	$page = memorial_plugin_type_page_any( $type_id );
	if ( ! $page && function_exists( 'memorial_plugin_term_page' ) ) {
		$page = memorial_plugin_term_page( $type_id );
	}
	return array(
		'id'       => (int) $type_id,
		'name'     => $t->name,
		'desc'     => $t->description,
		'count'    => memorial_plugin_cat_count( $type_id ),
		'fields'   => memorial_plugin_type_fields( $type_id ),
		'defaults' => memorial_plugin_type_defaults( $type_id ),
		'cats'     => memorial_plugin_types_cats( $type_id ),
		'ratio'    => (string) get_term_meta( $type_id, 'mem_ratio', true ),
		'cols'     => (int) get_term_meta( $type_id, 'mem_cols', true ),
		'page'     => $page ? array(
			'title' => get_the_title( $page ),
			'url'   => (string) get_permalink( $page ),
			'edit'  => (string) get_edit_post_link( $page, 'raw' ),
			'draft' => 'publish' !== get_post_status( $page ),
		) : null,
		'products' => admin_url( 'edit.php?post_type=mem_product&page=mem-products&type=' . $type_id ),
	);
}

/**
 * All types for the screen.
 *
 * @return array
 */
function memorial_plugin_types_all() {
	$ratios = array();
	foreach ( memorial_plugin_ratios() as $value => $label ) {
		$ratios[] = array(
			'value' => (string) $value,
			'label' => (string) $label,
		);
	}
	return array(
		'types'  => array_values( array_filter( array_map( static fn( $t ) => memorial_plugin_types_data( (int) $t->term_id ), memorial_plugin_manager_types() ) ) ),
		'ratios' => $ratios,
		'import' => admin_url( 'admin.php?page=mem-import' ),
	);
}

/**
 * Is it a product type (a top category).
 *
 * @param int $id Term ID.
 * @return bool
 */
function memorial_plugin_is_type( $id ) {
	return $id && term_exists( $id, 'mem_cat' ) && memorial_plugin_type_root( $id ) === $id;
}

/**
 * Save the settings and specifications of a type.
 *
 * @param int   $type   Type ID.
 * @param array $values Values.
 * @return true|WP_Error
 */
function memorial_plugin_types_save( $type, $values ) {
	$term = array();
	if ( isset( $values['name'] ) ) {
		$n = trim( sanitize_text_field( (string) $values['name'] ) );
		if ( '' === $n ) {
			return new WP_Error( 'memorial_plugin_name', __( 'The name is empty.', 'memorial-plugin' ), array( 'status' => 400 ) );
		}
		$term['name'] = $n;
	}
	if ( isset( $values['desc'] ) ) {
		$term['description'] = wp_kses_post( trim( (string) $values['desc'] ) );
	}
	if ( $term ) {
		$r = wp_update_term( $type, 'mem_cat', $term );
		if ( is_wp_error( $r ) ) {
			$r->add_data( array( 'status' => 400 ) );
			return $r;
		}
	}
	if ( isset( $values['ratio'] ) ) {
		$ratio = (string) $values['ratio'];
		array_key_exists( $ratio, memorial_plugin_ratios() ) && '' !== $ratio ? update_term_meta( $type, 'mem_ratio', $ratio ) : delete_term_meta( $type, 'mem_ratio' );
	}
	if ( isset( $values['cols'] ) ) {
		$c = absint( $values['cols'] );
		$c ? update_term_meta( $type, 'mem_cols', min( 6, max( 2, $c ) ) ) : delete_term_meta( $type, 'mem_cols' );
	}
	if ( isset( $values['defaults'] ) ) {
		$d = (array) $values['defaults'];
		update_term_meta(
			$type,
			'mem_defaults',
			array(
				'price' => absint( preg_replace( '/\D/', '', (string) ( $d['price'] ?? '' ) ) ),
				'from'  => empty( $d['from'] ) ? 0 : 1,
				'unit'  => trim( sanitize_text_field( (string) ( $d['unit'] ?? '' ) ) ),
			)
		);
	}
	if ( isset( $values['fields'] ) ) {
		$fields = array_values( array_filter( array_map( 'memorial_plugin_clean_field', (array) $values['fields'] ) ) );
		update_term_meta( $type, 'mem_fields', wp_json_encode( $fields, JSON_UNESCAPED_UNICODE ) );
		// A renamed specification is renamed in all products of the type.
		foreach ( (array) ( $values['renames'] ?? array() ) as $old => $new ) {
			$old = sanitize_text_field( (string) $old );
			$new = sanitize_text_field( (string) $new );
			if ( '' === $old || '' === $new || $old === $new ) {
				continue;
			}
			$ids = get_posts(
				array(
					'post_type'      => 'mem_product',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- admin action.
						array(
							'taxonomy'         => 'mem_cat',
							'terms'            => $type,
							'include_children' => true,
						),
					),
				)
			);
			foreach ( $ids as $pid ) {
				$m = memorial_plugin_product_spec_map( $pid );
				if ( isset( $m[ $old ] ) ) {
					memorial_plugin_set_spec( $pid, $old, '' );
					memorial_plugin_set_spec( $pid, $new, $m[ $old ] );
				}
			}
		}
	}
	return true;
}

/**
 * Delete a category: its products stay in the type (or in their other categories).
 *
 * @param int $id Category ID.
 */
function memorial_plugin_types_delete_cat( $id ) {
	$type = memorial_plugin_type_root( $id );
	$ids  = get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- admin action.
				array(
					'taxonomy'         => 'mem_cat',
					'terms'            => $id,
					'include_children' => false,
				),
			),
		)
	);
	wp_delete_term( $id, 'mem_cat' );
	foreach ( $ids as $pid ) {
		if ( ! wp_get_object_terms( $pid, 'mem_cat', array( 'fields' => 'ids' ) ) ) {
			wp_set_object_terms( $pid, array( $type ), 'mem_cat' );
		}
	}
}

/**
 * REST routes.
 */
function memorial_plugin_types_rest() {
	$ns   = 'memorial-plugin/v1';
	$can  = static fn() => current_user_can( 'manage_categories' );
	$all  = static fn() => rest_ensure_response( memorial_plugin_types_all() );
	$type = static fn( WP_REST_Request $r ) => current_user_can( 'manage_categories' ) && memorial_plugin_is_type( (int) $r['id'] );
	$cat  = static fn( WP_REST_Request $r ) => current_user_can( 'manage_categories' ) && term_exists( (int) $r['id'], 'mem_cat' ) && ! memorial_plugin_is_type( (int) $r['id'] );
	register_rest_route(
		$ns,
		'/catalog/types',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'callback'            => $all,
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => $can,
				'args'                => array(
					'name' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$name = trim( sanitize_text_field( (string) $request['name'] ) );
					$r    = '' !== $name ? wp_insert_term( $name, 'mem_cat' ) : new WP_Error( 'memorial_plugin_name', __( 'The name is empty.', 'memorial-plugin' ) );
					if ( is_wp_error( $r ) ) {
						$r->add_data( array( 'status' => 400 ) );
						return $r;
					}
					update_term_meta( $r['term_id'], 'mem_order', 1000 );
					memorial_plugin_type_page_create( (int) $r['term_id'] ); // A draft page for the new catalog section.
					return rest_ensure_response( array_merge( memorial_plugin_types_all(), array( 'created' => (int) $r['term_id'] ) ) );
				},
			),
		)
	);
	register_rest_route(
		$ns,
		'/catalog/types/(?P<id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => $type,
				'args'                => array(
					'values' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) use ( $all ) {
					$r = memorial_plugin_types_save( (int) $request['id'], (array) $request['values'] );
					return is_wp_error( $r ) ? $r : $all();
				},
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'permission_callback' => $type,
				'callback'            => static function ( WP_REST_Request $request ) use ( $all ) {
					$id = (int) $request['id'];
					if ( memorial_plugin_cat_count( $id ) ) {
						return new WP_Error( 'memorial_plugin_not_empty', __( 'The type has products — move or delete them first.', 'memorial-plugin' ), array( 'status' => 400 ) );
					}
					foreach ( array_reverse( memorial_plugin_types_cats( $id ) ) as $c ) {
						wp_delete_term( $c['id'], 'mem_cat' );
					}
					wp_delete_term( $id, 'mem_cat' );
					return $all();
				},
			),
		)
	);
	register_rest_route(
		$ns,
		'/catalog/types/(?P<id>\d+)/page',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => $type,
			'callback'            => static function ( WP_REST_Request $request ) use ( $all ) {
				return memorial_plugin_type_page_create( (int) $request['id'] ) ? $all()
					: new WP_Error( 'memorial_plugin_page', __( 'Could not create the page.', 'memorial-plugin' ), array( 'status' => 500 ) );
			},
		)
	);
	register_rest_route(
		$ns,
		'/catalog/order',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => $can,
			'args'                => array(
				'ids' => array(
					'type'     => 'array',
					'items'    => array( 'type' => 'integer' ),
					'required' => true,
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) use ( $all ) {
				foreach ( array_map( 'absint', (array) $request['ids'] ) as $i => $id ) {
					if ( $id && term_exists( $id, 'mem_cat' ) ) {
						update_term_meta( $id, 'mem_order', ( $i + 1 ) * 10 );
					}
				}
				return $all();
			},
		)
	);
	register_rest_route(
		$ns,
		'/catalog/cats',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => $can,
			'args'                => array(
				'name'   => array(
					'type'     => 'string',
					'required' => true,
				),
				'parent' => array(
					'type'     => 'integer',
					'required' => true,
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) use ( $all ) {
				$name   = trim( sanitize_text_field( (string) $request['name'] ) );
				$parent = (int) $request['parent'];
				if ( '' === $name || ! $parent || ! term_exists( $parent, 'mem_cat' ) ) {
					return new WP_Error( 'memorial_plugin_cat', __( 'Enter a name and choose where the category goes.', 'memorial-plugin' ), array( 'status' => 400 ) );
				}
				$r = wp_insert_term(
					$name,
					'mem_cat',
					array(
						'parent'      => $parent,
						'description' => wp_kses_post( trim( (string) $request['desc'] ) ),
					)
				);
				if ( is_wp_error( $r ) ) {
					$r->add_data( array( 'status' => 400 ) );
					return $r;
				}
				update_term_meta( $r['term_id'], 'mem_order', 1000 );
				return $all();
			},
		)
	);
	register_rest_route(
		$ns,
		'/catalog/cats/(?P<id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => $cat,
				'callback'            => static function ( WP_REST_Request $request ) use ( $all ) {
					$term = array();
					if ( null !== $request['name'] ) {
						$n = trim( sanitize_text_field( (string) $request['name'] ) );
						if ( '' !== $n ) {
							$term['name'] = $n;
						}
					}
					if ( null !== $request['desc'] ) {
						$term['description'] = wp_kses_post( trim( (string) $request['desc'] ) );
					}
					$r = $term ? wp_update_term( (int) $request['id'], 'mem_cat', $term ) : true;
					if ( is_wp_error( $r ) ) {
						$r->add_data( array( 'status' => 400 ) );
						return $r;
					}
					return $all();
				},
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'permission_callback' => $cat,
				'callback'            => static function ( WP_REST_Request $request ) use ( $all ) {
					memorial_plugin_types_delete_cat( (int) $request['id'] );
					return $all();
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_types_rest' );
