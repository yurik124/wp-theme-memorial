<?php
/**
 * Editor: previews of the plugin blocks with one request (assets/js/editor-render-batch.js).
 *
 * POST /memorial-plugin/v1/render-blocks { requests: [ { path, attributes } ] } — every item is the
 * request the editor would send to /wp/v2/block-renderer/memorial-plugin/<block>. Each item is passed to
 * the WordPress block renderer inside this one PHP process (rest_do_request), so its checks (the block
 * exists, the attributes are valid, the user may edit the post) stay the same. The answer keeps the
 * order: [ { status, body } ].
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Most items in one request. */
const MEMORIAL_PLUGIN_RENDER_BATCH_MAX = 40;

/**
 * REST route of the batch.
 */
function memorial_plugin_render_batch_rest() {
	register_rest_route(
		'memorial-plugin/v1',
		'/render-blocks',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => static function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'requests' => array(
					'type'     => 'array',
					'required' => true,
					'maxItems' => MEMORIAL_PLUGIN_RENDER_BATCH_MAX,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'path'       => array(
								'type'     => 'string',
								'required' => true,
								'pattern'  => '^/wp/v2/block-renderer/memorial-plugin/[a-z0-9-]+(\?.*)?$',
							),
							'attributes' => array(
								'type' => array( 'object', 'null' ),
							),
						),
					),
				),
			),
			'callback'            => 'memorial_plugin_render_batch',
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_render_batch_rest' );

/**
 * Render every item of the batch with the WordPress block renderer.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function memorial_plugin_render_batch( WP_REST_Request $request ) {
	$out = array();
	foreach ( (array) $request['requests'] as $item ) {
		$parts = wp_parse_url( (string) $item['path'] );
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			wp_parse_str( $parts['query'], $query );
		}
		unset( $query['_locale'] ); // The locale of the batch request is already in effect.
		$inner = new WP_REST_Request( isset( $item['attributes'] ) ? 'POST' : 'GET', $parts['path'] );
		$inner->set_query_params( $query );
		if ( isset( $item['attributes'] ) ) {
			$inner->set_body_params( array( 'attributes' => $item['attributes'] ) );
		}
		$response = rest_do_request( $inner );
		$out[]    = array(
			'status' => $response->get_status(),
			'body'   => rest_get_server()->response_to_data( $response, false ),
		);
	}
	return rest_ensure_response( $out );
}

/**
 * Editor script that sends the previews of the plugin blocks as one request.
 */
function memorial_plugin_render_batch_script() {
	$path = '/assets/js/editor-render-batch.js';
	wp_enqueue_script(
		'memorial-plugin-render-batch',
		MEMORIAL_PLUGIN_URL . $path,
		array( 'wp-api-fetch' ),
		(string) filemtime( MEMORIAL_PLUGIN_DIR . $path ),
		false
	);
}
add_action( 'enqueue_block_editor_assets', 'memorial_plugin_render_batch_script' );
