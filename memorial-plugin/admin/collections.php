<?php
/**
 * Simple record lists on DataViews + DataForm (instead of the classic WP_List_Table screens):
 * Memorial → Call-back requests (mem_lead) and Reviews from the guest book (mem_bookrev).
 *
 * Each collection is described once (fields, where a field lives — title, content, date, status,
 * order, featured image or a meta key — and what may be done); the list, filters, the edit form and
 * REST memorial-plugin/v1/collections/<name> are built from that description. Requests are personal
 * data, so the routes check capabilities on every request (core wp/v2 would show published records
 * of a non-public type to anyone).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collections: name => post type, labels, capability, actions, fields.
 *
 * Field: label, type (text, textarea, date, select, boolean, image, url, datetime), source
 * (title, content, date, status, menu_order, thumbnail or meta:<key>), readonly, options, help,
 * cap (who may change it; others see it read-only), hidden (not a column of the list by default).
 * Collection: also trash (false — no “Move to trash”), sort (field, direction of the list),
 * toggle (option, label, cap — one switch of the whole list above it).
 *
 * @return array<string,array>
 */
function memorial_plugin_collections() {
	$branches    = function_exists( 'memorial_plugin_branch_options' ) ? memorial_plugin_branch_options() : array();
	$collections = array(
		'leads'   => array(
			'post_type' => 'mem_lead',
			'title'     => __( 'Call-back requests', 'memorial-plugin' ),
			'intro'     => __( 'Requests from the “Call me back” window. Each one also comes by email; here they are kept even if the email is lost.', 'memorial-plugin' ),
			'cap'       => 'memorial_plugin_leads',
			'create'    => false,
			'fields'    => array(
				'phone' => array(
					'label'    => __( 'Phone', 'memorial-plugin' ),
					'type'     => 'text',
					'source'   => 'title',
					'readonly' => true,
				),
				'name'  => array(
					'label'    => _x( 'Name', 'person', 'memorial-plugin' ),
					'type'     => 'text',
					'source'   => 'meta:_name',
					'readonly' => true,
				),
				'page'  => array(
					'label'    => __( 'From page', 'memorial-plugin' ),
					'type'     => 'pagelink',
					'source'   => 'meta:_page',
					'readonly' => true,
				),
				'date'  => array(
					'label'    => __( 'When', 'memorial-plugin' ),
					'type'     => 'datetime',
					'source'   => 'date',
					'readonly' => true,
				),
				'mail'  => array(
					'label'    => _x( 'Email', 'request column', 'memorial-plugin' ),
					'type'     => 'select',
					'source'   => 'meta:_mem_mail',
					'readonly' => true,
					'options'  => array(
						''       => __( 'not sent (an older request)', 'memorial-plugin' ),
						'sent'   => __( 'Sent', 'memorial-plugin' ),
						'failed' => __( 'Not sent', 'memorial-plugin' ),
					),
				),
				'mailerr' => array(
					'label'    => __( 'Why the email was not sent', 'memorial-plugin' ),
					'type'     => 'textarea',
					'source'   => 'meta:_mem_mail_error',
					'readonly' => true,
					'hidden'   => true,
				),
				'done'  => array(
					'label'  => __( 'Called back', 'memorial-plugin' ),
					'type'   => 'boolean',
					'source' => 'meta:_mem_done',
				),
				'note'  => array(
					'label'  => __( 'Note', 'memorial-plugin' ),
					'type'   => 'textarea',
					'source' => 'meta:_mem_note',
				),
			),
		),
		'bookrev' => array(
			'post_type' => 'mem_bookrev',
			'title'     => __( 'Reviews from the guest book', 'memorial-plugin' ),
			'intro'     => __( 'Reviews retyped from the paper guest book, with a photo of the page. Before uploading a photo, cover the surname, phone and signature of the author unless they agreed to publication.', 'memorial-plugin' ),
			'cap'       => 'memorial_plugin_bookrev',
			'create'    => true,
			'fields'    => array(
				'author' => array(
					'label'       => __( 'Author', 'memorial-plugin' ),
					'type'        => 'text',
					'source'      => 'title',
					'placeholder' => __( 'For example: Elena V.', 'memorial-plugin' ),
				),
				'text'   => array(
					'label'  => __( 'Review', 'memorial-plugin' ),
					'type'   => 'textarea',
					'source' => 'content',
					'help'   => __( 'As written in the book.', 'memorial-plugin' ),
				),
				'bdate'  => array(
					'label'  => __( 'Date in the book', 'memorial-plugin' ),
					'type'   => 'date',
					'source' => 'meta:_mem_bdate',
					'help'   => __( 'If the day cannot be read, use the 1st: the site shows only month and year.', 'memorial-plugin' ),
				),
				'branch' => array(
					'label'   => __( 'Where the book is kept', 'memorial-plugin' ),
					'type'    => 'select',
					'source'  => 'meta:_mem_branch',
					'options' => array_merge( array( '' => __( '— not specified —', 'memorial-plugin' ) ), $branches ),
				),
				'photo'  => array(
					'label'  => __( 'Photo of the page', 'memorial-plugin' ),
					'type'   => 'image',
					'source' => 'thumbnail',
				),
				'status' => array(
					'label'   => __( 'On the site', 'memorial-plugin' ),
					'type'    => 'select',
					'source'  => 'status',
					'options' => array(
						'publish' => __( 'Shown', 'memorial-plugin' ),
						'draft'   => __( 'Hidden', 'memorial-plugin' ),
					),
				),
				'order'  => array(
					'label'  => __( 'Order', 'memorial-plugin' ),
					'type'   => 'integer',
					'source' => 'menu_order',
					'help'   => __( 'Smaller first; equal — newer first.', 'memorial-plugin' ),
				),
			),
		),
	);
	/**
	 * Filters the record lists: a module adds its own (reviews/rewio-archive.php — «Список отзывов»).
	 *
	 * @param array $collections Name => description.
	 */
	return (array) apply_filters( 'memorial_plugin_collections', $collections );
}

/**
 * Value of a field for a post.
 *
 * @param WP_Post $post  Post.
 * @param array   $field Field.
 * @return mixed
 */
function memorial_plugin_collection_get( $post, $field ) {
	$source = $field['source'];
	switch ( $source ) {
		case 'title':
			return html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
		case 'content':
			return (string) $post->post_content;
		case 'date':
			return get_post_time( 'c', false, $post );
		case 'status':
			return 'visible' === $field['type'] ? 'publish' === $post->post_status : $post->post_status;
		case 'menu_order':
			return (int) $post->menu_order;
		case 'thumbnail':
			$id = (int) get_post_thumbnail_id( $post );
			return $id ? array(
				'id'  => $id,
				'url' => (string) wp_get_attachment_image_url( $id, 'thumbnail' ),
			) : null;
	}
	$value = get_post_meta( $post->ID, substr( $source, 5 ), true );
	if ( 'pagelink' === $field['type'] ) {
		return memorial_plugin_collection_pagelink( (string) $value );
	}
	if ( 'integer' === $field['type'] ) {
		return '' === (string) $value ? null : (int) round( (float) $value );
	}
	return 'boolean' === $field['type'] ? (bool) $value : (string) $value;
}

/**
 * Address of a page of the site → its title and address (a long title is shortened in the list).
 *
 * @param string $url Address.
 * @return array{url:string,title:string}|null
 */
function memorial_plugin_collection_pagelink( $url ) {
	if ( '' === $url ) {
		return null;
	}
	$id    = url_to_postid( $url );
	$title = $id ? html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) : '';
	if ( '' === $title ) {
		$path  = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$title = '' === $path ? __( 'Home page', 'memorial-plugin' ) : urldecode( $path );
	}
	return array(
		'url'   => $url,
		'title' => $title,
	);
}

/**
 * Record of a post for the app.
 *
 * @param WP_Post $post Post.
 * @param array   $col  Collection.
 * @return array
 */
function memorial_plugin_collection_item( $post, $col ) {
	$item = array( 'id' => (int) $post->ID );
	foreach ( $col['fields'] as $key => $field ) {
		$item[ $key ] = memorial_plugin_collection_get( $post, $field );
	}
	return $item;
}

/**
 * Save the editable fields from a request to a post.
 *
 * @param int   $id     Post ID (0 — new).
 * @param array $col    Collection.
 * @param array $values Values by field key.
 * @return int|WP_Error Post ID.
 */
function memorial_plugin_collection_save( $id, $col, $values ) {
	$post  = array( 'post_type' => $col['post_type'] );
	$meta  = array();
	$thumb = null;
	foreach ( $col['fields'] as $key => $field ) {
		if ( ! empty( $field['readonly'] ) || ! array_key_exists( $key, $values ) || ( ! empty( $field['cap'] ) && ! current_user_can( $field['cap'] ) ) ) {
			continue;
		}
		$v = $values[ $key ];
		switch ( $field['type'] ) {
			case 'boolean':
				$v = empty( $v ) ? '' : '1';
				break;
			case 'visible': // “Shown on the site” as a checkbox: the status of the record.
				$v = empty( $v ) ? 'draft' : 'publish';
				break;
			case 'integer':
				$v = (int) $v;
				break;
			case 'date':
				$v = preg_match( '/^\d{4}-\d{2}-\d{2}/', (string) $v ) ? substr( (string) $v, 0, 10 ) : '';
				break;
			case 'select':
				$v = array_key_exists( (string) $v, $field['options'] ) ? (string) $v : (string) array_key_first( $field['options'] );
				break;
			case 'textarea':
				$v = sanitize_textarea_field( (string) $v );
				break;
			case 'url':
				$v = esc_url_raw( trim( (string) $v ) );
				break;
			case 'image':
				$v = is_array( $v ) ? absint( $v['id'] ?? 0 ) : absint( $v );
				break;
			default:
				$v = sanitize_text_field( (string) $v );
		}
		switch ( $field['source'] ) {
			case 'title':
				$post['post_title'] = $v;
				break;
			case 'content':
				$post['post_content'] = $v;
				break;
			case 'status':
				$post['post_status'] = $v;
				break;
			case 'menu_order':
				$post['menu_order'] = $v;
				break;
			case 'thumbnail':
				$thumb = $v;
				break;
			default:
				$meta[ substr( $field['source'], 5 ) ] = $v;
		}
	}
	if ( $id ) {
		$post['ID'] = $id;
		$id         = count( $post ) > 2 ? wp_update_post( wp_slash( $post ), true ) : $id;
	} else {
		$post += array( 'post_status' => 'publish' );
		$id    = wp_insert_post( wp_slash( $post ), true );
	}
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	foreach ( $meta as $key => $v ) {
		'' === $v ? delete_post_meta( $id, $key ) : update_post_meta( $id, $key, $v );
	}
	if ( null !== $thumb ) {
		$thumb ? set_post_thumbnail( $id, $thumb ) : delete_post_thumbnail( $id );
	}
	$name = (string) ( $col['name'] ?? '' );
	/**
	 * A record of a list was saved.
	 *
	 * @param string $name   Collection name.
	 * @param int    $id     Post ID.
	 * @param array  $values Values sent (by field key).
	 */
	do_action( 'memorial_plugin_collection_saved', $name, (int) $id, $values );
	return (int) $id;
}

/**
 * Collection by name or an error.
 *
 * @param string $name Collection name.
 * @return array|WP_Error
 */
function memorial_plugin_collection( $name ) {
	$all = memorial_plugin_collections();
	return isset( $all[ $name ] ) ? $all[ $name ] + array( 'name' => $name ) : new WP_Error( 'memorial_plugin_unknown_collection', __( 'Unknown list.', 'memorial-plugin' ), array( 'status' => 404 ) );
}

/**
 * Permission: the collection capability, and for one record — editing that record.
 *
 * @param WP_REST_Request $request Request.
 * @return bool
 */
function memorial_plugin_collection_can( WP_REST_Request $request ) {
	$col = memorial_plugin_collection( (string) $request['name'] );
	if ( is_wp_error( $col ) || ! current_user_can( $col['cap'] ) ) {
		return false;
	}
	$id = (int) $request['id'];
	if ( $id ) {
		$post = get_post( $id );
		return $post && $post->post_type === $col['post_type'] && current_user_can( 'edit_post', $id );
	}
	return true;
}

/**
 * REST routes.
 */
function memorial_plugin_collections_rest() {
	$ns = 'memorial-plugin/v1';
	register_rest_route(
		$ns,
		'/collections/(?P<name>[a-z]+)',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => 'memorial_plugin_collection_can',
				'callback'            => static function ( WP_REST_Request $request ) {
					$col   = memorial_plugin_collection( (string) $request['name'] );
					$posts = get_posts(
						array(
							'post_type'      => $col['post_type'],
							'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
							'posts_per_page' => -1,
							'orderby'        => 'date',
							'order'          => 'DESC',
						)
					);
					$fields = array();
					foreach ( $col['fields'] as $key => $field ) {
						$fields[] = array_merge(
							array( 'id' => $key ),
							array_intersect_key( $field, array_flip( array( 'label', 'type', 'readonly', 'help', 'placeholder', 'hidden' ) ) ),
							! empty( $field['cap'] ) && ! current_user_can( $field['cap'] ) ? array( 'readonly' => true ) : array(),
							isset( $field['options'] ) ? array(
								'options' => array_map(
									static fn( $v, $l ) => array(
										'value' => (string) $v,
										'label' => (string) $l,
									),
									array_keys( $field['options'] ),
									$field['options']
								),
							) : array()
						);
					}
					return rest_ensure_response(
						array(
							'title'  => $col['title'],
							'intro'  => $col['intro'],
							'create' => ! empty( $col['create'] ),
							'trash'  => ! isset( $col['trash'] ) || $col['trash'],
							'sort'   => $col['sort'] ?? null,
							'toggle' => empty( $col['toggle'] ) ? null : array(
								'label' => $col['toggle']['label'],
								'value' => (bool) get_option( $col['toggle']['option'] ),
								'can'   => current_user_can( $col['toggle']['cap'] ?? 'manage_options' ),
							),
							'fields' => $fields,
							'items'  => array_map( static fn( $p ) => memorial_plugin_collection_item( $p, $col ), $posts ),
						)
					);
				},
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$col = memorial_plugin_collection( (string) $request['name'] );
					return ! is_wp_error( $col ) && ! empty( $col['create'] ) && current_user_can( $col['cap'] );
				},
				'callback'            => static function ( WP_REST_Request $request ) {
					$col = memorial_plugin_collection( (string) $request['name'] );
					$id  = memorial_plugin_collection_save( 0, $col, (array) $request['values'] );
					return is_wp_error( $id ) ? $id : rest_ensure_response( memorial_plugin_collection_item( get_post( $id ), $col ) );
				},
			),
		)
	);
	register_rest_route(
		$ns,
		'/collections/(?P<name>[a-z]+)/toggle',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => static function ( WP_REST_Request $request ) {
				$col = memorial_plugin_collection( (string) $request['name'] );
				return ! is_wp_error( $col ) && ! empty( $col['toggle'] ) && current_user_can( $col['cap'] ) && current_user_can( $col['toggle']['cap'] ?? 'manage_options' );
			},
			'args'                => array(
				'value' => array(
					'type'     => 'boolean',
					'required' => true,
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				$col = memorial_plugin_collection( (string) $request['name'] );
				update_option( $col['toggle']['option'], $request['value'] ? 1 : 0, false );
				return rest_ensure_response( array( 'value' => (bool) get_option( $col['toggle']['option'] ) ) );
			},
		)
	);
	register_rest_route(
		$ns,
		'/collections/(?P<name>[a-z]+)/(?P<id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => 'memorial_plugin_collection_can',
				'callback'            => static function ( WP_REST_Request $request ) {
					$col = memorial_plugin_collection( (string) $request['name'] );
					$id  = memorial_plugin_collection_save( (int) $request['id'], $col, (array) $request['values'] );
					return is_wp_error( $id ) ? $id : rest_ensure_response( memorial_plugin_collection_item( get_post( $id ), $col ) );
				},
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'permission_callback' => static function ( WP_REST_Request $request ) {
					return memorial_plugin_collection_can( $request ) && current_user_can( 'delete_post', (int) $request['id'] );
				},
				'callback'            => static function ( WP_REST_Request $request ) {
					$done = wp_trash_post( (int) $request['id'] );
					return rest_ensure_response( array( 'deleted' => (bool) $done ) );
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_collections_rest' );

/**
 * Screens: the list pages replace the classic edit.php of the two post types.
 */
function memorial_plugin_collections_menu() {
	$screens = array(
		'leads'   => array( 'mem-contacts', 'edit.php?post_type=mem_lead' ),
		'bookrev' => array( '', '' ), // no menu item: opened from the “Reviews” tabs, as before.
		'rewio'   => array( 'mem-contacts', '' ), // «Список отзывов» (when the Rewio feature is on).
	);
	foreach ( $screens as $name => $where ) {
		$col = memorial_plugin_collections()[ $name ] ?? null;
		if ( ! $col ) {
			continue;
		}
		$hook = add_submenu_page(
			$where[0],
			$col['title'],
			$col['title'],
			$col['cap'],
			'memorial-plugin-' . $name,
			static function () use ( $name ) {
				echo '<div id="memorial-plugin-collection" class="wrap memorial-plugin-admin" data-name="' . esc_attr( $name ) . '"></div>';
			}
		);
		if ( $hook ) {
			add_action( 'load-' . $hook, 'memorial_plugin_collections_assets' );
		}
		if ( $where[1] ) {
			remove_submenu_page( $where[0], $where[1] );
		}
	}
}
add_action( 'admin_menu', 'memorial_plugin_collections_menu', 20 );

/**
 * The classic list and edit screens of these types lead to the new list.
 */
function memorial_plugin_collections_redirect() {
	global $pagenow;
	$type = sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reading the screen.
	if ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reading the screen.
		$type = (string) get_post_type( absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reading the screen.
	}
	if ( ! in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	foreach ( memorial_plugin_collections() as $name => $col ) {
		if ( $col['post_type'] === $type ) {
			wp_safe_redirect( admin_url( 'admin.php?page=memorial-plugin-' . $name ) );
			exit;
		}
	}
}
add_action( 'admin_init', 'memorial_plugin_collections_redirect' );

/**
 * Scripts of the list screens.
 */
function memorial_plugin_collections_assets() {
	add_action(
		'admin_enqueue_scripts',
		static function () {
			$asset_file = MEMORIAL_PLUGIN_DIR . '/build/collections/index.asset.php';
			if ( ! is_readable( $asset_file ) ) {
				return;
			}
			$asset = require $asset_file;
			wp_enqueue_media();
			wp_enqueue_script(
				'memorial-plugin-collections',
				MEMORIAL_PLUGIN_URL . '/build/collections/index.js',
				$asset['dependencies'],
				$asset['version'],
				array( 'in_footer' => true )
			);
			wp_set_script_translations( 'memorial-plugin-collections', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
			wp_enqueue_style( 'wp-components' );
			if ( is_readable( MEMORIAL_PLUGIN_DIR . '/build/collections/style-index.css' ) ) {
				wp_enqueue_style( 'memorial-plugin-collections', MEMORIAL_PLUGIN_URL . '/build/collections/style-index.css', array( 'wp-components' ), $asset['version'] );
			}
		}
	);
}
