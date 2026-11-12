<?php
/**
 * Abilities API: the main actions of the plugin as abilities with input and output schemas and permission
 * checks. The admin screens (DataViews actions) and the Command Palette call them through the
 * package “wordpress/abilities” (src/shared/abilities.js); the REST API exposes them at /wp-abilities/v1/.
 *
 * Category: memorial. Abilities:
 *  - memorial-plugin/list-leads           — call back requests (readonly);
 *  - memorial-plugin/set-lead-done        — mark a request as called back;
 *  - memorial-plugin/list-products        — products of the catalog (readonly);
 *  - memorial-plugin/update-product       — price, “from”, unit, line under the title, visibility;
 *  - memorial-plugin/change-prices        — prices of several products: percent, roubles or one price;
 *  - memorial-plugin/set-visibility       — show or hide products or works;
 *  - memorial-plugin/create-products      — new products of a type from photos;
 *  - memorial-plugin/export-catalog       — the catalog as CSV (readonly);
 *  - memorial-plugin/convert-content      — shortcodes of a post → blocks (revision kept);
 *  - memorial-plugin/rollback-conversion  — back to the text before the conversion;
 *  - memorial-plugin/search-stats         — what visitors search for (readonly);
 *  - memorial-plugin/clear-search-stats   — delete search statistics (destructive).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category of the plugin’s abilities.
 */
function memorial_plugin_ability_categories() {
	wp_register_ability_category(
		'memorial',
		array(
			'label'       => __( 'Memorial', 'memorial-plugin' ),
			'description' => __( 'Call back requests, catalog, works, content conversion and search statistics of the funeral services site.', 'memorial-plugin' ),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'memorial_plugin_ability_categories' );

/**
 * JSON Schema of a list of IDs.
 *
 * @param string $what What the IDs are.
 * @return array
 */
function memorial_plugin_ability_ids_schema( $what ) {
	return array(
		'type'        => 'array',
		'description' => $what,
		'items'       => array(
			'type'    => 'integer',
			'minimum' => 1,
		),
		'minItems'    => 1,
		'maxItems'    => 500,
	);
}

/**
 * Product in the abilities output.
 *
 * @return array
 */
function memorial_plugin_ability_product_schema() {
	return array(
		'type'       => 'object',
		'properties' => array(
			'id'     => array( 'type' => 'integer' ),
			'title'  => array( 'type' => 'string' ),
			'status' => array(
				'type' => 'string',
				'enum' => array( 'publish', 'draft' ),
			),
			'type'   => array( 'type' => 'integer' ),
			'price'  => array( 'type' => 'integer' ),
			'from'   => array( 'type' => 'boolean' ),
			'unit'   => array( 'type' => 'string' ),
			'sub'    => array( 'type' => 'string' ),
		),
	);
}

/**
 * Product → ability output (without editor links and photos).
 *
 * @param array $p Product from memorial_plugin_manager_product().
 * @return array
 */
function memorial_plugin_ability_product( $p ) {
	return array_intersect_key( $p, array_flip( array( 'id', 'title', 'status', 'type', 'price', 'from', 'unit', 'sub' ) ) );
}

/**
 * Run a REST callback of the plugin and return its data (the abilities reuse the same code as the screens).
 *
 * @param callable $callback REST callback.
 * @param array    $params   Request parameters.
 * @return mixed|WP_Error
 */
function memorial_plugin_ability_rest( $callback, $params ) {
	$request = new WP_REST_Request( 'POST' );
	foreach ( $params as $k => $v ) {
		$request->set_param( $k, $v );
	}
	$res = call_user_func( $callback, $request );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return $res instanceof WP_REST_Response ? $res->get_data() : $res;
}

/**
 * The plugin’s abilities.
 */
function memorial_plugin_abilities() {
	$edit        = static fn() => current_user_can( 'edit_posts' );
	$admin       = static fn() => current_user_can( 'manage_options' );
	$lead        = static fn( $input ) => current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ) && 'mem_lead' === get_post_type( (int) ( $input['id'] ?? 0 ) );
	$meta_public = array(
		'public'       => true,
		'show_in_rest' => true,
	);

	if ( function_exists( 'memorial_plugin_collection' ) ) {
		memorial_plugin_abilities_leads( $edit, $lead );
	}
	if ( function_exists( 'memorial_plugin_products_data' ) ) {
		memorial_plugin_abilities_catalog( $edit, $meta_public );
	}
	if ( function_exists( 'memorial_plugin_convert_post' ) ) {
		memorial_plugin_abilities_convert();
	}
	if ( function_exists( 'memorial_plugin_sq_items' ) ) {
		memorial_plugin_abilities_search( $admin, $meta_public );
	}
}
add_action( 'wp_abilities_api_init', 'memorial_plugin_abilities' );

/**
 * Abilities of the call back requests.
 *
 * @param callable $edit Permission: may edit posts.
 * @param callable $lead Permission: may edit this request.
 */
function memorial_plugin_abilities_leads( $edit, $lead ) {
	wp_register_ability(
		'memorial-plugin/list-leads',
		array(
			'label'               => __( 'List call back requests', 'memorial-plugin' ),
			'description'         => __( 'Requests from the “Call me back” form: phone, name, page, time and whether someone has called back. Newest first.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'properties'           => array(
					'status' => array(
						'type'        => 'string',
						'enum'        => array( 'new', 'done', 'all' ),
						'default'     => 'new',
						'description' => __( 'new — nobody has called back yet.', 'memorial-plugin' ),
					),
					'limit'  => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 200,
						'default' => 20,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'phone' => array( 'type' => 'string' ),
						'name'  => array( 'type' => 'string' ),
						'page'  => array( 'type' => 'string' ),
						'date'  => array( 'type' => 'string' ),
						'done'  => array( 'type' => 'boolean' ),
					),
				),
			),
			'permission_callback' => $edit,
			'execute_callback'    => static function ( $input = array() ) {
				$col    = memorial_plugin_collection( 'leads' );
				$status = $input['status'] ?? 'new';
				$out    = array();
				$posts  = get_posts(
					array(
						'post_type'      => 'mem_lead',
						'post_status'    => 'any',
						'posts_per_page' => 500,
						'orderby'        => 'date',
						'order'          => 'DESC',
					)
				);
				foreach ( $posts as $p ) {
					$item = memorial_plugin_collection_item( $p, $col );
					$done = ! empty( $item['done'] );
					if ( ( 'new' === $status && $done ) || ( 'done' === $status && ! $done ) ) {
						continue;
					}
					$out[] = array(
						'id'    => (int) $item['id'],
						'phone' => (string) ( $item['phone'] ?? '' ),
						'name'  => (string) ( $item['name'] ?? '' ),
						'page'  => (string) ( $item['page'] ?? '' ),
						'date'  => (string) ( $item['date'] ?? '' ),
						'done'  => $done,
					);
					if ( count( $out ) >= (int) ( $input['limit'] ?? 20 ) ) {
						break;
					}
				}
				return $out;
			},
			'meta'                => array(
				'annotations'  => array( 'readonly' => true ),
				// Personal data: only for the site’s own screens and the REST API with a logged-in user, not for agents.
				'public'       => false,
				'show_in_rest' => true,
			),
		)
	);

	wp_register_ability(
		'memorial-plugin/set-lead-done',
		array(
			'label'               => __( 'Mark a request as called back', 'memorial-plugin' ),
			'description'         => __( 'Sets or clears “Called back” of a call back request.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'   => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'done' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'   => array( 'type' => 'integer' ),
					'done' => array( 'type' => 'boolean' ),
				),
			),
			'permission_callback' => $lead,
			'execute_callback'    => static function ( $input ) {
				$id   = (int) $input['id'];
				$done = (bool) ( $input['done'] ?? true );
				memorial_plugin_collection_save( $id, memorial_plugin_collection( 'leads' ), array( 'done' => $done ) );
				return array(
					'id'   => $id,
					'done' => $done,
				);
			},
			'meta'                => array(
				'annotations'  => array( 'idempotent' => true ),
				'public'       => false,
				'show_in_rest' => true,
			),
		)
	);
}

/**
 * Abilities of the catalog and works.
 *
 * @param callable $edit        Permission: may edit posts.
 * @param array    $meta_public Meta of public abilities.
 */
function memorial_plugin_abilities_catalog( $edit, $meta_public ) {
	wp_register_ability(
		'memorial-plugin/list-products',
		array(
			'label'               => __( 'List products', 'memorial-plugin' ),
			'description'         => __( 'Products of the catalog with price and visibility, optionally of one product type.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'properties'           => array(
					'type'   => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => __( 'Product type (top catalog category) ID; 0 — all.', 'memorial-plugin' ),
						'default'     => 0,
					),
					'search' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => memorial_plugin_ability_product_schema(),
			),
			'permission_callback' => $edit,
			'execute_callback'    => static function ( $input = array() ) {
				$data   = memorial_plugin_products_data();
				$type   = (int) ( $input['type'] ?? 0 );
				$search = mb_strtolower( trim( (string) ( $input['search'] ?? '' ) ) );
				$out    = array();
				foreach ( $data['items'] as $p ) {
					if ( ( $type && $p['type'] !== $type ) || ( '' !== $search && ! str_contains( mb_strtolower( $p['title'] ), $search ) ) ) {
						continue;
					}
					$out[] = memorial_plugin_ability_product( $p );
				}
				return $out;
			},
			'meta'                => array_merge( $meta_public, array( 'annotations' => array( 'readonly' => true ) ) ),
		)
	);

	wp_register_ability(
		'memorial-plugin/update-product',
		array(
			'label'               => __( 'Update a product', 'memorial-plugin' ),
			'description'         => __( 'Changes the title, price, “from”, unit, line under the title or visibility of one product.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'title'  => array( 'type' => 'string' ),
					'price'  => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => __( 'Roubles; 0 — “price on request”.', 'memorial-plugin' ),
					),
					'from'   => array( 'type' => 'boolean' ),
					'unit'   => array( 'type' => 'string' ),
					'sub'    => array( 'type' => 'string' ),
					'status' => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => memorial_plugin_ability_product_schema(),
			'permission_callback' => static fn( $input ) => current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ) && 'mem_product' === get_post_type( (int) ( $input['id'] ?? 0 ) ),
			'execute_callback'    => static function ( $input ) {
				$id = (int) $input['id'];
				unset( $input['id'] );
				memorial_plugin_product_save( $id, $input );
				return memorial_plugin_ability_product( memorial_plugin_manager_product( get_post( $id ) ) );
			},
			'meta'                => array_merge( $meta_public, array( 'annotations' => array( 'idempotent' => true ) ) ),
		)
	);

	wp_register_ability(
		'memorial-plugin/change-prices',
		array(
			'label'               => __( 'Change prices', 'memorial-plugin' ),
			'description'         => __( 'Changes the prices of several products: by percent, by roubles or to one price, rounded. Products with the price “on request” stay as they are unless one price is set.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'ids'   => memorial_plugin_ability_ids_schema( __( 'Product IDs.', 'memorial-plugin' ) ),
					'mode'  => array(
						'type' => 'string',
						'enum' => array( 'pct', 'add', 'set' ),
					),
					'value' => array( 'type' => 'number' ),
					'round' => array(
						'type'    => 'integer',
						'enum'    => array( 1, 10, 50, 100 ),
						'default' => 10,
					),
				),
				'required'             => array( 'ids', 'mode', 'value' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => memorial_plugin_ability_product_schema(),
			),
			'permission_callback' => $edit,
			'execute_callback'    => static function ( $input ) {
				$res = memorial_plugin_ability_rest( 'memorial_plugin_products_bulk', array_merge( $input, array( 'op' => 'price' ) ) );
				return is_wp_error( $res ) ? $res : array_map( 'memorial_plugin_ability_product', $res['items'] );
			},
			'meta'                => $meta_public,
		)
	);

	wp_register_ability(
		'memorial-plugin/set-visibility',
		array(
			'label'               => __( 'Show or hide on the site', 'memorial-plugin' ),
			'description'         => __( 'Shows (publishes) or hides (makes drafts of) products or works.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'kind'    => array(
						'type' => 'string',
						'enum' => array( 'products', 'works' ),
					),
					'ids'     => memorial_plugin_ability_ids_schema( __( 'Product or work IDs.', 'memorial-plugin' ) ),
					'visible' => array( 'type' => 'boolean' ),
				),
				'required'             => array( 'kind', 'ids', 'visible' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'changed' => array( 'type' => 'integer' ),
				),
			),
			'permission_callback' => $edit,
			'execute_callback'    => static function ( $input ) {
				$cb = 'works' === $input['kind'] ? 'memorial_plugin_works_bulk' : 'memorial_plugin_products_bulk';
				if ( ! function_exists( $cb ) ) {
					return new WP_Error( 'memorial_plugin_off', __( 'This part of the site is switched off in Memorial → Features.', 'memorial-plugin' ) );
				}
				$res = memorial_plugin_ability_rest(
					$cb,
					array(
						'ids' => $input['ids'],
						'op'  => $input['visible'] ? 'publish' : 'draft',
					)
				);
				return is_wp_error( $res ) ? $res : array( 'changed' => count( $res['items'] ) );
			},
			'meta'                => array_merge( $meta_public, array( 'annotations' => array( 'idempotent' => true ) ) ),
		)
	);

	wp_register_ability(
		'memorial-plugin/create-products',
		array(
			'label'               => __( 'Create products from photos', 'memorial-plugin' ),
			'description'         => __( 'Creates products of a type: one product per photo (or one empty product), at the end of the type, with the type’s default price and specifications.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'type'   => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'photos' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'maxItems' => 100,
						'default'  => array(),
					),
				),
				'required'             => array( 'type' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => memorial_plugin_ability_product_schema(),
			),
			'permission_callback' => static fn() => current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' ),
			'execute_callback'    => static function ( $input ) {
				$type = (int) $input['type'];
				if ( ! memorial_plugin_is_type( $type ) ) {
					return new WP_Error( 'memorial_plugin_type', __( 'Unknown product type.', 'memorial-plugin' ) );
				}
				$photos = array_filter( array_map( 'absint', (array) ( $input['photos'] ?? array() ) ), 'wp_attachment_is_image' );
				$out    = array();
				foreach ( $photos ? $photos : array( 0 ) as $att ) {
					$id = memorial_plugin_cm_create( $type, $att );
					if ( $id ) {
						$out[] = memorial_plugin_ability_product( memorial_plugin_manager_product( get_post( $id ) ) );
					}
				}
				return $out;
			},
			'meta'                => $meta_public,
		)
	);

	wp_register_ability(
		'memorial-plugin/export-catalog',
		array(
			'label'               => __( 'Export the catalog', 'memorial-plugin' ),
			'description'         => __( 'All products (or of one type) as CSV: type, title, price, “from”, unit, line under the title, categories, specifications, visibility.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'properties'           => array(
					'type' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'filename' => array( 'type' => 'string' ),
					'count'    => array( 'type' => 'integer' ),
					'csv'      => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => $edit,
			'execute_callback'    => 'memorial_plugin_export_catalog',
			'meta'                => array_merge( $meta_public, array( 'annotations' => array( 'readonly' => true ) ) ),
		)
	);
}

/**
 * Abilities of the content conversion.
 */
function memorial_plugin_abilities_convert() {
	wp_register_ability(
		'memorial-plugin/convert-content',
		array(
			'label'               => __( 'Convert shortcodes to blocks', 'memorial-plugin' ),
			'description'         => __( 'Replaces the [mem_…] shortcodes of posts with blocks and site values, and SEOPress shortcodes with variables. The text before the conversion is saved as a revision.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'ids' => memorial_plugin_ability_ids_schema( __( 'Post IDs (at most 10 per call).', 'memorial-plugin' ) ),
				),
				'required'             => array( 'ids' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer' ),
						'result' => array(
							'type'        => 'string',
							'description' => __( 'converted, unchanged, no-revision, not-found or an error message.', 'memorial-plugin' ),
						),
					),
				),
			),
			'permission_callback' => static function ( $input ) {
				foreach ( (array) ( $input['ids'] ?? array() ) as $id ) {
					if ( ! current_user_can( 'edit_post', (int) $id ) ) {
						return false;
					}
				}
				return current_user_can( 'edit_posts' );
			},
			'execute_callback'    => static function ( $input ) {
				$out = array();
				foreach ( array_slice( array_map( 'intval', $input['ids'] ), 0, 10 ) as $id ) {
					$out[] = array(
						'id'     => $id,
						'result' => (string) memorial_plugin_convert_post( $id ),
					);
				}
				return $out;
			},
			'meta'                => array(
				'annotations'  => array( 'idempotent' => true ),
				'public'       => false,
				'show_in_rest' => true,
			),
		)
	);

	wp_register_ability(
		'memorial-plugin/rollback-conversion',
		array(
			'label'               => __( 'Roll back a conversion', 'memorial-plugin' ),
			'description'         => __( 'Restores the text and SEO fields of a post from before its conversion to blocks.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'     => array( 'type' => 'integer' ),
					'result' => array(
						'type' => 'string',
						'enum' => array( 'restored', 'not-converted', 'no-revision' ),
					),
				),
			),
			'permission_callback' => static fn( $input ) => current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ),
			'execute_callback'    => static fn( $input ) => array(
				'id'     => (int) $input['id'],
				'result' => (string) memorial_plugin_convert_rollback( (int) $input['id'] ),
			),
			'meta'                => array(
				'public'       => false,
				'show_in_rest' => true,
			),
		)
	);
}

/**
 * Abilities of the search statistics.
 *
 * @param callable $admin       Permission: administrator.
 * @param array    $meta_public Meta of public abilities.
 */
function memorial_plugin_abilities_search( $admin, $meta_public ) {
	wp_register_ability(
		'memorial-plugin/search-stats',
		array(
			'label'               => __( 'What visitors search for', 'memorial-plugin' ),
			'description'         => __( 'Queries of the site search (questions and answers, “Good to know”) with counts; queries that found nothing show which answers the site lacks.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'properties'           => array(
					'nothing_found' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'limit'         => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 500,
						'default' => 50,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array( 'type' => 'string' ),
						'count' => array( 'type' => 'integer' ),
						'found' => array( 'type' => 'integer' ),
						'last'  => array( 'type' => 'string' ),
					),
				),
			),
			'permission_callback' => static fn() => current_user_can( 'edit_pages' ),
			'execute_callback'    => static function ( $input = array() ) {
				$items = memorial_plugin_sq_items();
				if ( ! empty( $input['nothing_found'] ) ) {
					$items = array_values( array_filter( $items, static fn( $i ) => 0 === $i['found'] ) );
				}
				usort( $items, static fn( $a, $b ) => $b['count'] <=> $a['count'] );
				return array_map(
					static fn( $i ) => array_intersect_key( $i, array_flip( array( 'query', 'count', 'found', 'last' ) ) ),
					array_slice( $items, 0, (int) ( $input['limit'] ?? 50 ) )
				);
			},
			'meta'                => array_merge( $meta_public, array( 'annotations' => array( 'readonly' => true ) ) ),
		)
	);

	wp_register_ability(
		'memorial-plugin/clear-search-stats',
		array(
			'label'               => __( 'Clear search statistics', 'memorial-plugin' ),
			'description'         => __( 'Deletes the given queries from the search statistics, or all statistics when no queries are given.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'properties'           => array(
					'queries' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array( 'left' => array( 'type' => 'integer' ) ),
			),
			'permission_callback' => $admin,
			'execute_callback'    => static function ( $input = array() ) {
				$queries = array_map( 'strval', (array) ( $input['queries'] ?? array() ) );
				if ( $queries ) {
					$s = memorial_plugin_sq_stats();
					foreach ( $queries as $q ) {
						unset( $s[ $q ] );
					}
					update_option( MEMORIAL_PLUGIN_SQ_OPT, $s, false );
				} else {
					delete_option( MEMORIAL_PLUGIN_SQ_OPT );
				}
				return array( 'left' => count( memorial_plugin_sq_items() ) );
			},
			'meta'                => array(
				'annotations'  => array(
					'destructive' => true,
					'idempotent'  => true,
				),
				'public'       => false,
				'show_in_rest' => true,
			),
		)
	);
}

/**
 * The catalog as CSV (semicolon, UTF-8 with BOM — opens in Excel as is).
 *
 * @param array $input Input: type.
 * @return array{filename:string,count:int,csv:string}
 */
function memorial_plugin_export_catalog( $input = array() ) {
	$data  = memorial_plugin_products_data();
	$type  = (int) ( $input['type'] ?? 0 );
	$names = array();
	$specs = array();
	$cats  = array();
	foreach ( $data['types'] as $t ) {
		$names[ $t['id'] ] = $t['name'];
		foreach ( $t['fields'] as $f ) {
			$specs[ $f['label'] ] = true;
		}
		foreach ( $t['cats'] as $c ) {
			$cats[ $c['id'] ] = preg_replace( '/^(— )+/u', '', $c['name'] );
		}
	}
	$specs = array_keys( $specs );
	$rows  = array(
		array_merge(
			array(
				__( 'Type', 'memorial-plugin' ),
				__( 'Title', 'memorial-plugin' ),
				__( 'Price', 'memorial-plugin' ),
				__( 'From', 'memorial-plugin' ),
				__( 'Unit', 'memorial-plugin' ),
				__( 'Line under the title', 'memorial-plugin' ),
				__( 'Categories', 'memorial-plugin' ),
			),
			$specs,
			array( __( 'On the site', 'memorial-plugin' ), 'ID' )
		),
	);
	$count = 0;
	foreach ( $data['items'] as $p ) {
		if ( $type && $p['type'] !== $type ) {
			continue;
		}
		$row = array(
			$names[ $p['type'] ] ?? '',
			$p['title'],
			$p['price'] ? (string) $p['price'] : '',
			$p['from'] ? '1' : '',
			$p['unit'],
			$p['sub'],
			implode( ', ', array_filter( array_map( static fn( $c ) => $cats[ $c ] ?? '', $p['cats'] ) ) ),
		);
		$map = (array) $p['specs'];
		foreach ( $specs as $s ) {
			$row[] = (string) ( $map[ $s ] ?? '' );
		}
		$row[]  = 'publish' === $p['status'] ? __( 'shown', 'memorial-plugin' ) : __( 'hidden', 'memorial-plugin' );
		$row[]  = (string) $p['id'];
		$rows[] = $row;
		++$count;
	}
	$csv = "\xEF\xBB\xBF";
	foreach ( $rows as $row ) {
		$csv .= implode(
			';',
			array_map(
				static function ( $v ) {
					$v = (string) $v;
					// A value starting with = + - @ is not run as a formula in Excel.
					if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) {
						$v = "'" . $v;
					}
					return '"' . str_replace( '"', '""', $v ) . '"';
				},
				$row
			)
		) . "\r\n";
	}
	return array(
		'filename' => 'catalog-' . gmdate( 'Y-m-d' ) . '.csv',
		'count'    => $count,
		'csv'      => $csv,
	);
}
