<?php
/**
 * “Our works” on DataViews (src/catalog), in the same way as the products:
 *  — Works: photos, title, year, plot size, what was done with prices, kinds, “in examples”, order and
 *    visibility are edited in a form right in the list; several works at once — show or hide, add or
 *    remove a kind, copy, move to the trash; a new work is made from photos;
 *  — Kinds of works: add, rename, reorder, delete.
 * The text of a work is edited in the block editor (fields in its sidebar — admin/editor-meta.php).
 *
 * REST memorial-plugin/v1/catalog/works and /catalog/work-types.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menu: “Works” and “Kinds of works” replace the classic screens.
 */
function memorial_plugin_works_menu() {
	$p = 'edit.php?post_type=mem_work';
	foreach (
		array(
			array( 'mem-works', __( 'Works', 'memorial-plugin' ), 'edit_posts', 0 ),
			array( 'mem-work-types', __( 'Kinds of works', 'memorial-plugin' ), 'manage_categories', 1 ),
		) as $s
	) {
		$hook = add_submenu_page( $p, $s[1], $s[1], $s[2], $s[0], 'memorial_plugin_catalog_views_container', $s[3] );
		if ( $hook ) {
			add_action( 'load-' . $hook, 'memorial_plugin_catalog_views_assets' );
		}
	}
	remove_submenu_page( $p, $p );
	remove_submenu_page( $p, 'post-new.php?post_type=mem_work' );
	remove_submenu_page( $p, 'edit-tags.php?taxonomy=mem_work_type&amp;post_type=mem_work' );
	remove_submenu_page( $p, 'edit-tags.php?taxonomy=mem_work_type&post_type=mem_work' );
}
add_action( 'admin_menu', 'memorial_plugin_works_menu', 30 );

/**
 * The classic list of works and of kinds lead to the new screens (the trash stays classic).
 */
function memorial_plugin_works_redirect() {
	// phpcs:disable WordPress.Security.NonceVerification -- only reading which screen is open.
	$keep = array( 'classic', 'post_status', 's', 'trashed', 'untrashed', 'deleted', 'action' );
	foreach ( $keep as $k ) {
		if ( isset( $_GET[ $k ] ) ) {
			return;
		}
	}
	$screen = current_action();
	if ( 'load-edit.php' === $screen && 'mem_work' === ( $_GET['post_type'] ?? '' ) ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=mem_work&page=mem-works' ) );
		exit;
	}
	if ( 'load-edit-tags.php' === $screen && 'mem_work_type' === ( $_GET['taxonomy'] ?? '' ) && empty( $_POST ) ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=mem_work&page=mem-work-types' ) );
		exit;
	}
	// phpcs:enable
}
add_action( 'load-edit.php', 'memorial_plugin_works_redirect' );
add_action( 'load-edit-tags.php', 'memorial_plugin_works_redirect' );

/**
 * Kinds of works in their order.
 *
 * @return array<int,array{id:int,name:string,count:int,order:int}>
 */
function memorial_plugin_wm_kinds() {
	$t = get_terms(
		array(
			'taxonomy'   => 'mem_work_type',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $t ) ) {
		return array();
	}
	usort(
		$t,
		static fn( $a, $b ) => ( (int) get_term_meta( $a->term_id, 'mem_order', true ) <=> (int) get_term_meta( $b->term_id, 'mem_order', true ) ) ?: strcmp( $a->name, $b->name )
	);
	return array_map(
		static fn( $k ) => array(
			'id'    => (int) $k->term_id,
			'name'  => $k->name,
			'count' => (int) $k->count,
			'order' => (int) get_term_meta( $k->term_id, 'mem_order', true ),
		),
		$t
	);
}

/**
 * Work for the list.
 *
 * @param WP_Post $w Work.
 * @return array
 */
function memorial_plugin_wm_item( $w ) {
	$d     = memorial_plugin_work_data( $w->ID );
	$kinds = wp_get_object_terms( $w->ID, 'mem_work_type', array( 'fields' => 'ids' ) );
	return array(
		'id'      => (int) $w->ID,
		'title'   => html_entity_decode( get_the_title( $w ), ENT_QUOTES, 'UTF-8' ),
		'status'  => 'publish' === $w->post_status ? 'publish' : 'draft',
		'year'    => (int) $d['year'],
		'size'    => $d['size'],
		'rows'    => array_map( static fn( $r ) => array( (string) ( $r[0] ?? '' ), (int) ( $r[1] ?? 0 ) ), $d['rows'] ),
		'total'   => (int) $d['total'],
		'photos'  => memorial_plugin_catalog_photos( $d['photos'] ),
		'kinds'   => is_wp_error( $kinds ) ? array() : array_map( 'intval', $kinds ),
		'example' => (bool) get_post_meta( $w->ID, '_mem_example', true ),
		'order'   => (int) $w->menu_order,
		'edit'    => (string) get_edit_post_link( $w->ID, 'raw' ),
	);
}

/**
 * Save the fields of a work from the list.
 *
 * @param int   $id     Work ID.
 * @param array $values Field => value.
 */
function memorial_plugin_wm_save( $id, $values ) {
	$post = array();
	foreach ( $values as $f => $v ) {
		switch ( $f ) {
			case 'title':
				$t                  = trim( sanitize_text_field( (string) $v ) );
				$post['post_title'] = '' !== $t ? $t : __( 'Untitled work', 'memorial-plugin' );
				break;
			case 'status':
				$post['post_status'] = 'publish' === $v ? 'publish' : 'draft';
				break;
			case 'order':
				$post['menu_order'] = (int) $v;
				break;
			case 'year':
				$y = memorial_plugin_work_year( $v );
				$y ? update_post_meta( $id, '_mem_year', $y ) : delete_post_meta( $id, '_mem_year' );
				break;
			case 'size':
				$s = trim( sanitize_text_field( (string) $v ) );
				'' !== $s ? update_post_meta( $id, '_mem_size', $s ) : delete_post_meta( $id, '_mem_size' );
				break;
			case 'rows':
				update_post_meta( $id, '_mem_rows', memorial_plugin_work_rows( $v ) );
				break;
			case 'photos':
				$ph = array_values( array_filter( array_map( static fn( $p ) => absint( is_array( $p ) ? ( $p['id'] ?? 0 ) : $p ), (array) $v ), 'wp_attachment_is_image' ) );
				update_post_meta( $id, '_mem_photos', implode( ',', $ph ) );
				$ph ? set_post_thumbnail( $id, $ph[0] ) : delete_post_thumbnail( $id );
				break;
			case 'kinds':
				$k = array_values( array_filter( array_map( 'absint', (array) $v ), static fn( $t ) => $t && term_exists( $t, 'mem_work_type' ) ) );
				wp_set_object_terms( $id, $k, 'mem_work_type' );
				break;
			case 'example':
				empty( $v ) ? delete_post_meta( $id, '_mem_example' ) : update_post_meta( $id, '_mem_example', '1' );
				break;
		}
	}
	if ( $post ) {
		$post['ID'] = $id;
		wp_update_post( wp_slash( $post ) );
	}
}

/**
 * New work (with photos or without) at the end of the list.
 *
 * @param int[] $photos Photo IDs.
 * @param int   $kind   Kind of work or 0.
 * @return int Work ID or 0.
 */
function memorial_plugin_wm_create( $photos, $kind ) {
	$ph   = array_values( array_filter( array_map( 'absint', $photos ), 'wp_attachment_is_image' ) );
	$last = get_posts(
		array(
			'post_type'      => 'mem_work',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'orderby'        => 'menu_order',
			'order'          => 'DESC',
		)
	);
	$id   = wp_insert_post(
		array(
			'post_type'   => 'mem_work',
			'post_status' => 'publish',
			'post_title'  => __( 'New work', 'memorial-plugin' ),
			'menu_order'  => $last ? (int) $last[0]->menu_order + 1 : 1,
		)
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	update_post_meta( $id, '_mem_photos', implode( ',', $ph ) );
	update_post_meta( $id, '_mem_year', (int) gmdate( 'Y' ) );
	if ( $ph ) {
		set_post_thumbnail( $id, $ph[0] );
	}
	if ( $kind && term_exists( $kind, 'mem_work_type' ) ) {
		wp_set_object_terms( $id, array( $kind ), 'mem_work_type' );
	}
	return (int) $id;
}

/**
 * Bulk actions on works.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function memorial_plugin_works_bulk( WP_REST_Request $request ) {
	$ids = memorial_plugin_catalog_ids( $request['ids'], 'mem_work' );
	$op  = sanitize_key( (string) $request['op'] );
	if ( ! $ids ) {
		return new WP_Error( 'memorial_plugin_nothing', __( 'No works selected.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}
	$kinds   = array_values( array_filter( array_map( 'absint', (array) $request['kinds'] ) ) );
	$out     = array();
	$trashed = array();
	if ( 'order' === $op ) {
		// The IDs in their new order (a whole type or the whole list): menu_order 1, 2, 3…
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
				'items'   => array_map( static fn( $id ) => memorial_plugin_wm_item( get_post( $id ) ), $ids ),
				'trashed' => array(),
			)
		);
	}
	foreach ( $ids as $id ) {
		switch ( $op ) {
			case 'trash':
				wp_trash_post( $id );
				$trashed[] = $id;
				continue 2;
			case 'publish':
			case 'draft':
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => $op,
					)
				);
				break;
			case 'kinds_add':
				wp_set_object_terms( $id, $kinds, 'mem_work_type', true );
				break;
			case 'kinds_remove':
				wp_remove_object_terms( $id, $kinds, 'mem_work_type' );
				break;
			case 'copy':
				$id = memorial_plugin_catalog_copy( $id, array( '_mem_photos', '_mem_size', '_mem_rows', '_mem_year' ), 'mem_work_type' );
				break;
		}
		if ( $id ) {
			$out[] = memorial_plugin_wm_item( get_post( $id ) );
		}
	}
	return rest_ensure_response(
		array(
			'items'   => $out,
			'trashed' => $trashed,
			'kinds'   => memorial_plugin_wm_kinds(),
		)
	);
}

/**
 * Kinds of works: add, rename, order, delete.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function memorial_plugin_work_types_change( WP_REST_Request $request ) {
	$op   = (string) $request['op'];
	$id   = absint( $request['id'] );
	$name = trim( sanitize_text_field( (string) $request['name'] ) );
	$r    = null;
	if ( 'add' === $op ) {
		if ( '' === $name ) {
			return new WP_Error( 'memorial_plugin_name', __( 'The name is empty.', 'memorial-plugin' ), array( 'status' => 400 ) );
		}
		$r = wp_insert_term( $name, 'mem_work_type' );
		if ( ! is_wp_error( $r ) ) {
			update_term_meta( $r['term_id'], 'mem_order', 1000 );
		}
	} elseif ( 'rename' === $op && $id && '' !== $name ) {
		$r = wp_update_term( $id, 'mem_work_type', array( 'name' => $name ) );
	} elseif ( 'delete' === $op && $id ) {
		$r = wp_delete_term( $id, 'mem_work_type' );
	} elseif ( 'order' === $op ) {
		foreach ( array_map( 'absint', (array) $request['ids'] ) as $i => $t ) {
			if ( $t ) {
				update_term_meta( $t, 'mem_order', ( $i + 1 ) * 10 );
			}
		}
	}
	if ( is_wp_error( $r ) ) {
		$r->add_data( array( 'status' => 400 ) );
		return $r;
	}
	return rest_ensure_response( array( 'kinds' => memorial_plugin_wm_kinds() ) );
}

/**
 * REST routes of the works list.
 */
function memorial_plugin_works_rest() {
	$ns  = 'memorial-plugin/v1';
	$can = static fn() => current_user_can( 'edit_posts' );
	register_rest_route(
		$ns,
		'/catalog/works',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'callback'            => static function () {
					$works = get_posts(
						array(
							'post_type'      => 'mem_work',
							'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
							'posts_per_page' => -1,
							'orderby'        => array(
								'menu_order' => 'ASC',
								'date'       => 'ASC',
							),
						)
					);
					return rest_ensure_response(
						array(
							'kinds'     => memorial_plugin_wm_kinds(),
							'items'     => array_map( 'memorial_plugin_wm_item', $works ),
							'canManage' => current_user_can( 'manage_categories' ),
							'kindsUrl'  => admin_url( 'edit.php?post_type=mem_work&page=mem-work-types' ),
							'trashUrl'  => admin_url( 'edit.php?post_status=trash&post_type=mem_work' ),
						)
					);
				},
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => $can,
				'args'                => array(
					'photos' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
					'kind'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$id = memorial_plugin_wm_create( (array) $request['photos'], (int) $request['kind'] );
					return $id ? rest_ensure_response( array( 'items' => array( memorial_plugin_wm_item( get_post( $id ) ) ) ) )
						: new WP_Error( 'memorial_plugin_create', __( 'Could not create the work.', 'memorial-plugin' ), array( 'status' => 500 ) );
				},
			),
		)
	);
	register_rest_route(
		$ns,
		'/catalog/works/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'permission_callback' => static fn( WP_REST_Request $r ) => current_user_can( 'edit_post', (int) $r['id'] ) && 'mem_work' === get_post_type( (int) $r['id'] ),
			'args'                => array(
				'values' => array(
					'type'     => 'object',
					'required' => true,
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				memorial_plugin_wm_save( (int) $request['id'], (array) $request['values'] );
				return rest_ensure_response( memorial_plugin_wm_item( get_post( (int) $request['id'] ) ) );
			},
		)
	);
	register_rest_route(
		$ns,
		'/catalog/works/bulk',
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
					'enum'     => array( 'order', 'publish', 'draft', 'copy', 'trash', 'kinds_add', 'kinds_remove' ),
					'required' => true,
				),
			),
			'callback'            => 'memorial_plugin_works_bulk',
		)
	);
	register_rest_route(
		$ns,
		'/catalog/work-types',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => static fn() => current_user_can( 'manage_categories' ),
			'args'                => array(
				'op' => array(
					'type'     => 'string',
					'enum'     => array( 'add', 'rename', 'delete', 'order' ),
					'required' => true,
				),
			),
			'callback'            => 'memorial_plugin_work_types_change',
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_works_rest' );
