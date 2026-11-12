<?php
/**
 * Inline values: site data inside text — the starting price of a catalog section, the number of models,
 * a price from Memorial → Prices, years in business, the phone, company details, a link to a document.
 *
 * In the post content a value is a span with its key and parameters:
 *   <span class="memorial-plugin-value" data-value="cat-price" data-atts='{"cat":"Венки"}'>от 1 200 ₽</span>
 * The text inside is what the value was when it was inserted; on the page it is replaced with the current
 * value on every render. In the editor such a span is the “Site value” text format (src/inline-value).
 *
 * Inline shortcodes ([mem_cat_price], [mem_years]…) are converted to these spans by the content converter;
 * the shortcodes still work (compatibility layer up to 2.1.0, removal in 3.0.0).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline values: key => shortcode that computes it (or a callback), label and the main parameter (for the editor).
 *
 * @return array<string,array{tag?:string,callback?:callable,label:string,param:string,param_label:string}>
 */
function memorial_plugin_inline_values() {
	return array(
		'cat-price'  => array(
			'tag'         => 'mem_cat_price',
			'label'       => __( 'Starting price of a catalog section', 'memorial-plugin' ),
			'param'       => 'cat',
			'param_label' => __( 'Catalog section', 'memorial-plugin' ),
		),
		'cat-max'    => array(
			'tag'         => 'mem_cat_price',
			'fixed'       => array( 'cena' => 'макс' ),
			'label'       => __( 'Highest price of a catalog section (“up to …”)', 'memorial-plugin' ),
			'param'       => 'cat',
			'param_label' => __( 'Catalog section', 'memorial-plugin' ),
		),
		'cat-count'  => array(
			'tag'         => 'mem_cat_count',
			'label'       => __( 'Number of models in a catalog section', 'memorial-plugin' ),
			'param'       => 'cat',
			'param_label' => __( 'Catalog section', 'memorial-plugin' ),
		),
		'price'      => array(
			'tag'         => 'mem_price',
			'label'       => __( 'Price from Memorial → Prices', 'memorial-plugin' ),
			'param'       => 'id',
			'param_label' => __( 'Price', 'memorial-plugin' ),
		),
		'service'    => array(
			'callback'    => static fn( $atts ) => esc_html( memorial_plugin_value_service( (string) ( $atts['title'] ?? '' ) ) ),
			'label'       => __( 'Price of a service (Memorial → Services)', 'memorial-plugin' ),
			'param'       => 'title',
			'param_label' => __( 'Service', 'memorial-plugin' ),
		),
		'years'      => array(
			'tag'         => 'mem_years',
			'label'       => __( 'Years in business', 'memorial-plugin' ),
			'param'       => '',
			'param_label' => '',
		),
		'phone'      => array(
			'tag'         => 'mem_phone',
			'label'       => __( 'Main phone (link)', 'memorial-plugin' ),
			'param'       => '',
			'param_label' => '',
		),
		'operator'   => array(
			'tag'         => 'mem_operator',
			'label'       => __( 'Company details (personal data operator)', 'memorial-plugin' ),
			'param'       => 'chto',
			'param_label' => __( 'What: empty — name and details; адрес (address), почта (email), телефон (phone), сайт (site)', 'memorial-plugin' ),
		),
		'founded'    => array(
			'callback'    => static fn() => (string) (int) ( memorial_plugin_contacts()['founded'] ?? 0 ),
			'label'       => __( 'Founding year', 'memorial-plugin' ),
			'param'       => '',
			'param_label' => '',
		),
		'phone-text' => array(
			'callback'    => static fn() => esc_html( (string) memorial_plugin_opt( 'phone' ) ),
			'label'       => __( 'Main phone (text)', 'memorial-plugin' ),
			'param'       => '',
			'param_label' => '',
		),
		'address'    => array(
			'callback'    => static function () {
				$addrs = memorial_plugin_addrs();
				return $addrs ? esc_html( (string) $addrs[0]['addr'] ) : '';
			},
			'label'       => __( 'Main address', 'memorial-plugin' ),
			'param'       => '',
			'param_label' => '',
		),
		'doc'        => array(
			'tag'         => 'mem_doc',
			'label'       => __( 'Link to a document (privacy policy, consent, cookies)', 'memorial-plugin' ),
			'param'       => 'kto',
			'param_label' => __( 'Document: policy, consent or cookies', 'memorial-plugin' ),
		),
	);
}

/**
 * Inline value key by shortcode tag.
 *
 * @param string $tag Shortcode tag.
 * @return string Key or ''.
 */
function memorial_plugin_inline_value_key( $tag ) {
	foreach ( memorial_plugin_inline_values() as $key => $def ) {
		if ( ( $def['tag'] ?? '' ) === $tag ) {
			return $key;
		}
	}
	return '';
}

/**
 * Compute an inline value (HTML, already escaped by the value function).
 *
 * @param string $key  Value key.
 * @param array  $atts Parameters (shortcode attributes; 'content' — link text for a document).
 * @return string|null Null if the key is unknown or its function is switched off.
 */
function memorial_plugin_inline_value_html( $key, $atts ) {
	$def = memorial_plugin_inline_values()[ $key ] ?? null;
	if ( $def && isset( $def['callback'] ) && is_callable( $def['callback'] ) ) {
		return (string) call_user_func( $def['callback'], (array) $atts );
	}
	$impl = $def && ! empty( $def['tag'] ) ? memorial_plugin_shortcode_impl( $def['tag'] ) : null;
	if ( ! $impl ) {
		return null;
	}
	$content = (string) ( $atts['content'] ?? '' );
	unset( $atts['content'] );
	$atts = array_merge( array_map( 'strval', array_filter( (array) $atts, 'is_scalar' ) ), (array) ( $def['fixed'] ?? array() ) );
	return (string) call_user_func( $impl, $atts, $content, $def['tag'] );
}

/**
 * The span of an inline value.
 *
 * @param string $key   Value key.
 * @param array  $atts  Parameters.
 * @param string $inner HTML inside (the value at the time of inserting).
 * @return string
 */
function memorial_plugin_inline_value_span( $key, $atts, $inner ) {
	$json = $atts ? wp_json_encode( (object) $atts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '';
	return '<span class="memorial-plugin-value" data-value="' . esc_attr( $key ) . '"'
		. ( '' !== $json ? ' data-atts="' . esc_attr( $json ) . '"' : '' ) . '>' . $inner . '</span>';
}

/**
 * Replace inline values in rendered HTML with their current values.
 *
 * @param string $html Block HTML.
 * @return string
 */
function memorial_plugin_inline_values_render( $html ) {
	if ( false === strpos( $html, 'memorial-plugin-value' ) ) {
		return $html;
	}
	return (string) preg_replace_callback(
		'~<span\b[^>]*\bmemorial-plugin-value\b[^>]*>(.*?)</span>~s',
		static function ( $m ) {
			$tag = new WP_HTML_Tag_Processor( $m[0] );
			if ( ! $tag->next_tag( 'span' ) || ! $tag->has_class( 'memorial-plugin-value' ) ) {
				return $m[0];
			}
			$key  = (string) $tag->get_attribute( 'data-value' );
			$atts = json_decode( (string) $tag->get_attribute( 'data-atts' ), true );
			$html = memorial_plugin_inline_value_html( $key, is_array( $atts ) ? $atts : array() );
			if ( null === $html ) {
				return $m[0];
			}
			// The value replaces the text; the span keeps its class (and any colour/format set in the editor).
			return substr( $m[0], 0, strpos( $m[0], '>' ) + 1 ) . $html . '</span>';
		},
		$html
	);
}

/**
 * Current values in blocks with text (paragraphs, lists, tables, headings…).
 *
 * @param string $html  Block HTML.
 * @param array  $block Parsed block.
 * @return string
 */
function memorial_plugin_inline_values_render_block( $html, $block ) {
	// Blocks with inner blocks get already rendered children — no need to compute the values twice.
	if ( ! empty( $block['innerBlocks'] ) ) {
		return $html;
	}
	return memorial_plugin_inline_values_render( $html );
}
add_filter( 'render_block', 'memorial_plugin_inline_values_render_block', 9, 2 );

/**
 * REST: the current value for the editor (GET /memorial-plugin/v1/inline-value?key=…&atts={…}).
 */
function memorial_plugin_inline_values_rest() {
	register_rest_route(
		'memorial-plugin/v1',
		'/inline-value',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => static function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'key'  => array(
					'type'     => 'string',
					'required' => true,
					'enum'     => array_keys( memorial_plugin_inline_values() ),
				),
				'atts' => array(
					'type'    => 'object',
					'default' => array(),
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				$atts = array_map( 'sanitize_text_field', array_filter( (array) $request['atts'], 'is_scalar' ) );
				$html = memorial_plugin_inline_value_html( (string) $request['key'], $atts );
				return rest_ensure_response(
					array(
						'html' => null === $html ? '' : wp_kses_post( $html ),
						'span' => memorial_plugin_inline_value_span( (string) $request['key'], $atts, null === $html ? '' : wp_kses_post( $html ) ),
					)
				);
			},
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_inline_values_rest' );

/**
 * Editor: the “Site value” text format and the list of values for it.
 */
function memorial_plugin_inline_values_editor_assets() {
	$asset_file = MEMORIAL_PLUGIN_DIR . '/build/inline-value/index.asset.php';
	if ( ! is_readable( $asset_file ) ) {
		return;
	}
	$asset = require $asset_file;
	wp_enqueue_script(
		'memorial-plugin-inline-value',
		MEMORIAL_PLUGIN_URL . '/build/inline-value/index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);
	wp_set_script_translations( 'memorial-plugin-inline-value', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
	$values = array();
	foreach ( memorial_plugin_inline_values() as $key => $def ) {
		$values[] = array(
			'key'        => $key,
			'label'      => $def['label'],
			'param'      => $def['param'],
			'paramLabel' => $def['param_label'],
			'choices'    => memorial_plugin_inline_value_choices( $key ),
		);
	}
	wp_add_inline_script(
		'memorial-plugin-inline-value',
		'window.memorialPluginInlineValues = ' . wp_json_encode( $values ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'memorial_plugin_inline_values_editor_assets' );

/**
 * Choices of the parameter of a value for the editor (instead of typing a code): [ { label, atts } ].
 * Null — the parameter is typed (or the value has none).
 *
 * @param string $key Value key.
 * @return array|null
 */
function memorial_plugin_inline_value_choices( $key ) {
	$out = array();
	switch ( $key ) {
		case 'cat-price':
		case 'cat-max':
		case 'cat-count':
			$out[] = array(
				'label' => __( 'The section of the page where the text is', 'memorial-plugin' ),
				'atts'  => new stdClass(),
			);
			foreach ( memorial_plugin_values_sections( false ) as $slug => $name ) {
				$out[] = array(
					'label' => $name,
					'atts'  => array( 'cat' => (string) $slug ),
				);
			}
			return $out;
		case 'price':
			return memorial_plugin_values_price_choices();
		case 'service':
			foreach ( memorial_plugin_values_services( false ) as $title => $label ) {
				$out[] = array(
					'label' => $label,
					'atts'  => array( 'title' => (string) $title ),
				);
			}
			return $out;
		case 'doc':
			foreach ( array(
				'policy'  => __( 'Privacy policy', 'memorial-plugin' ),
				'consent' => __( 'Consent to the processing of personal data', 'memorial-plugin' ),
				'cookies' => __( 'Cookie policy', 'memorial-plugin' ),
			) as $doc => $label ) {
				$out[] = array(
					'label' => $label,
					'atts'  => array( 'kto' => $doc ),
				);
			}
			return $out;
		case 'operator':
			foreach ( array(
				''        => __( 'Name and details', 'memorial-plugin' ),
				'адрес'   => __( 'Address', 'memorial-plugin' ),
				'почта'   => __( 'Email', 'memorial-plugin' ),
				'телефон' => __( 'Phone', 'memorial-plugin' ),
				'сайт'    => __( 'Site', 'memorial-plugin' ),
			) as $what => $label ) {
				$out[] = array(
					'label' => $label,
					'atts'  => '' === $what ? new stdClass() : array( 'chto' => $what ),
				);
			}
			return $out;
	}
	return null;
}
