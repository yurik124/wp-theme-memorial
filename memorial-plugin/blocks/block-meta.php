<?php
/**
 * Shared registration data of the plugin blocks: block supports, inserter keywords and previews.
 *
 * All blocks of the plugin are registered in PHP (PHP-only blocks, supports.autoRegister): their
 * settings in the editor are generated from the attributes, and the output is rendered on the server.
 *
 * Supports. The blocks whose markup has one root element (home and content blocks, floating blocks) take
 * colour, typography, spacing and border from the editor: the wrapper classes and styles go onto that root
 * element (memorial_plugin_add_wrapper_attributes()). The structural blocks of the templates
 * (blocks/legacy: header, footer, product, catalog…) print several root elements into the layout of the
 * theme, so they have no style supports: their look is the theme’s (theme.json and its stylesheet).
 * Layout support is not used: none of the blocks has inner blocks.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block supports for a family of blocks.
 *
 * @param string $kind  'full' — colour, typography, spacing, border; 'structure' — none (output of the template).
 * @param array  $extra Supports to add or override.
 * @return array
 */
function memorial_plugin_block_supports( $kind = 'full', $extra = array() ) {
	$base = array(
		'autoRegister' => true,
		'html'         => false,
	);
	if ( 'full' === $kind ) {
		$base += array(
			'color'                => array(
				'text'       => true,
				'background' => true,
				'link'       => true,
			),
			'typography'           => array(
				'fontSize'   => true,
				'lineHeight' => true,
			),
			'spacing'              => array(
				'margin'  => true,
				'padding' => true,
			),
			// WordPress 7.1 still registers border support under this key on the server.
			'__experimentalBorder' => array(
				'color'  => true,
				'radius' => true,
				'style'  => true,
				'width'  => true,
			),
		);
	}
	return array_merge( $base, $extra );
}

/**
 * Groups of the plugin blocks in the inserter, by purpose: category slug => title.
 *
 * @return array<string,string>
 */
function memorial_plugin_block_categories() {
	return array(
		'memorial-contacts' => __( 'Memorial: contacts', 'memorial-plugin' ),
		'memorial-catalog'  => __( 'Memorial: catalog and prices', 'memorial-plugin' ),
		'memorial-services' => __( 'Memorial: services and information', 'memorial-plugin' ),
		'memorial-reviews'  => __( 'Memorial: reviews', 'memorial-plugin' ),
		'memorial-design'   => __( 'Memorial: decoration and buttons', 'memorial-plugin' ),
		'memorial-layout'   => __( 'Memorial: header, footer and pop-ups', 'memorial-plugin' ),
		'memorial-pages'    => __( 'Memorial: whole pages (for templates)', 'memorial-plugin' ),
	);
}

/**
 * Category of a plugin block in the inserter.
 *
 * @param string $slug Block slug without the namespace.
 * @return string
 */
function memorial_plugin_block_category( $slug ) {
	$groups = array(
		'memorial-contacts' => array( 'phone-button', 'night-line', 'contacts-list', 'contacts-cards', 'contacts-badges', 'maps', 'company-details' ),
		'memorial-catalog'  => array( 'catalog', 'category-list', 'category-photos', 'catalog-tiles', 'cards', 'packages', 'packages-grid', 'cremation' ),
		'memorial-services' => array( 'services', 'services-grid', 'works', 'cemeteries', 'restaurants', 'faq', 'guide', 'cta' ),
		'memorial-reviews'  => array( 'reviews', 'book-reviews', 'rewio-reviews', 'review-buttons', 'review-widget' ),
		'memorial-design'   => array( 'laurel', 'icon', 'stat', 'link-button' ),
		'memorial-layout'   => array( 'topbar', 'header-contacts', 'tagline', 'footer-contacts', 'menu-columns', 'footer-legal', 'mobile-bar', 'breadcrumbs', 'callback-dialog', 'cookie-banner' ),
		'memorial-pages'    => array( 'home', 'page-products', 'contacts-page', 'product', 'catalog-archive', 'not-found' ),
	);
	foreach ( $groups as $category => $slugs ) {
		if ( in_array( $slug, $slugs, true ) ) {
			return $category;
		}
	}
	return 'memorial-services';
}

/**
 * Register the groups of the plugin blocks (before the core categories, in the order above).
 *
 * @param array $categories Block categories.
 * @return array
 */
function memorial_plugin_register_block_categories( $categories ) {
	$have = array_column( $categories, 'slug' );
	$ours = array();
	foreach ( memorial_plugin_block_categories() as $slug => $title ) {
		if ( ! in_array( $slug, $have, true ) ) {
			$ours[] = array(
				'slug'  => $slug,
				'title' => $title,
				'icon'  => null,
			);
		}
	}
	return array_merge( $ours, $categories );
}
add_filter( 'block_categories_all', 'memorial_plugin_register_block_categories' );

/**
 * Inserter keywords of the blocks (translated): slug => keywords.
 *
 * @return array<string,string[]>
 */
function memorial_plugin_block_keywords() {
	return array(
		// Home page.
		'laurel'          => array( __( 'laurel', 'memorial-plugin' ), __( 'divider', 'memorial-plugin' ), __( 'decoration', 'memorial-plugin' ) ),
		'icon'            => array( __( 'icon', 'memorial-plugin' ), __( 'symbol', 'memorial-plugin' ) ),
		'phone-button'    => array( __( 'phone', 'memorial-plugin' ), __( 'call', 'memorial-plugin' ), __( 'button', 'memorial-plugin' ) ),
		'link-button'     => array( __( 'button', 'memorial-plugin' ), __( 'link', 'memorial-plugin' ) ),
		'night-line'      => array( __( 'night', 'memorial-plugin' ), __( 'phone', 'memorial-plugin' ), __( 'hours', 'memorial-plugin' ) ),
		'stat'            => array( __( 'number', 'memorial-plugin' ), __( 'figure', 'memorial-plugin' ), __( 'wreath', 'memorial-plugin' ) ),
		'catalog-tiles'   => array( __( 'catalog', 'memorial-plugin' ), __( 'tiles', 'memorial-plugin' ), __( 'categories', 'memorial-plugin' ) ),
		'services-grid'   => array( __( 'services', 'memorial-plugin' ), __( 'grid', 'memorial-plugin' ) ),
		'packages-grid'   => array( __( 'packages', 'memorial-plugin' ), __( 'prices', 'memorial-plugin' ) ),
		'contacts-list'   => array( __( 'contacts', 'memorial-plugin' ), __( 'address', 'memorial-plugin' ), __( 'phone', 'memorial-plugin' ) ),
		'maps'            => array( __( 'map', 'memorial-plugin' ), __( 'address', 'memorial-plugin' ), __( 'route', 'memorial-plugin' ) ),
		'review-buttons'  => array( __( 'reviews', 'memorial-plugin' ), __( 'Yandex', 'memorial-plugin' ) ),
		'contacts-badges' => array( __( 'hours', 'memorial-plugin' ), __( 'night', 'memorial-plugin' ), __( 'contacts', 'memorial-plugin' ) ),
		'contacts-cards'  => array( __( 'contacts', 'memorial-plugin' ), __( 'phone', 'memorial-plugin' ), __( 'address', 'memorial-plugin' ) ),
		'company-details' => array( __( 'company details', 'memorial-plugin' ), __( 'contacts', 'memorial-plugin' ) ),
		'review-widget'   => array( __( 'reviews', 'memorial-plugin' ), __( 'Yandex', 'memorial-plugin' ), __( 'widget', 'memorial-plugin' ) ),
		// Content.
		'catalog'         => array( __( 'catalog', 'memorial-plugin' ), __( 'products', 'memorial-plugin' ) ),
		'category-list'   => array( __( 'categories', 'memorial-plugin' ), __( 'catalog', 'memorial-plugin' ) ),
		'category-photos' => array( __( 'photos', 'memorial-plugin' ), __( 'catalog', 'memorial-plugin' ) ),
		'cemeteries'      => array( __( 'cemeteries', 'memorial-plugin' ), __( 'places', 'memorial-plugin' ) ),
		'restaurants'     => array( __( 'memorial meal', 'memorial-plugin' ), __( 'halls', 'memorial-plugin' ) ),
		'reviews'         => array( __( 'reviews', 'memorial-plugin' ), __( 'rating', 'memorial-plugin' ) ),
		'book-reviews'    => array( __( 'reviews', 'memorial-plugin' ), __( 'book', 'memorial-plugin' ) ),
		'rewio-reviews'   => array( __( 'reviews', 'memorial-plugin' ), 'Rewio' ),
		'services'        => array( __( 'services', 'memorial-plugin' ) ),
		'faq'             => array( __( 'questions', 'memorial-plugin' ), __( 'FAQ', 'memorial-plugin' ) ),
		'guide'           => array( __( 'guide', 'memorial-plugin' ), __( 'questions', 'memorial-plugin' ) ),
		'cta'             => array( __( 'call to action', 'memorial-plugin' ), __( 'phone', 'memorial-plugin' ) ),
		'cremation'       => array( __( 'cremation', 'memorial-plugin' ), __( 'prices', 'memorial-plugin' ) ),
		'packages'        => array( __( 'packages', 'memorial-plugin' ), __( 'prices', 'memorial-plugin' ) ),
		'works'           => array( __( 'works', 'memorial-plugin' ), __( 'portfolio', 'memorial-plugin' ), __( 'photos', 'memorial-plugin' ) ),
		'cards'           => array( __( 'cards', 'memorial-plugin' ), __( 'pages', 'memorial-plugin' ) ),
		// Floating.
		'callback-dialog' => array( __( 'call back', 'memorial-plugin' ), __( 'form', 'memorial-plugin' ) ),
		'cookie-banner'   => array( __( 'cookie', 'memorial-plugin' ), __( 'consent', 'memorial-plugin' ) ),
		// Structure of the templates.
		'topbar'          => array( __( 'header', 'memorial-plugin' ), __( 'address', 'memorial-plugin' ), __( 'hours', 'memorial-plugin' ) ),
		'header-contacts' => array( __( 'header', 'memorial-plugin' ), __( 'phone', 'memorial-plugin' ) ),
		'tagline'         => array( __( 'footer', 'memorial-plugin' ), __( 'years', 'memorial-plugin' ) ),
		'footer-contacts' => array( __( 'footer', 'memorial-plugin' ), __( 'contacts', 'memorial-plugin' ) ),
		'menu-columns'    => array( __( 'footer', 'memorial-plugin' ), __( 'menu', 'memorial-plugin' ) ),
		'footer-legal'    => array( __( 'footer', 'memorial-plugin' ), __( 'copyright', 'memorial-plugin' ), __( 'privacy', 'memorial-plugin' ) ),
		'mobile-bar'      => array( __( 'mobile', 'memorial-plugin' ), __( 'phone', 'memorial-plugin' ) ),
		'breadcrumbs'     => array( __( 'breadcrumbs', 'memorial-plugin' ), __( 'navigation', 'memorial-plugin' ) ),
		'home'            => array( __( 'home page', 'memorial-plugin' ), __( 'sections', 'memorial-plugin' ) ),
		'page-products'   => array( __( 'products', 'memorial-plugin' ), __( 'catalog', 'memorial-plugin' ) ),
		'contacts-page'   => array( __( 'contacts', 'memorial-plugin' ), __( 'map', 'memorial-plugin' ) ),
		'product'         => array( __( 'product', 'memorial-plugin' ), __( 'catalog', 'memorial-plugin' ) ),
		'catalog-archive' => array( __( 'catalog', 'memorial-plugin' ), __( 'category', 'memorial-plugin' ) ),
		'not-found'       => array( '404', __( 'not found', 'memorial-plugin' ) ),
	);
}

/**
 * Keywords of one block (plus the given extra words).
 *
 * @param string   $slug  Block slug without the namespace.
 * @param string[] $extra Extra keywords (the former shortcode tag, for example).
 * @return string[]
 */
function memorial_plugin_block_keywords_of( $slug, $extra = array() ) {
	return array_values( array_unique( array_merge( memorial_plugin_block_keywords()[ $slug ] ?? array(), $extra, array( 'memorial' ) ) ) );
}

/**
 * Inserter preview: the block with its default attributes, in a viewport wide enough for sections.
 *
 * @param array $attributes Attribute definitions (with defaults).
 * @param int   $width      Preview viewport width.
 * @return array
 */
function memorial_plugin_block_example( $attributes = array(), $width = 800 ) {
	$values = array();
	foreach ( $attributes as $name => $def ) {
		if ( array_key_exists( 'default', $def ) ) {
			$values[ $name ] = $def['default'];
		}
	}
	return array(
		'attributes'    => $values,
		'viewportWidth' => $width,
	);
}

/**
 * Attributes of a plugin block for register_block_type(): the definitions keep only the schema keys.
 * A definition with 'picker' gets a chooser in the block settings (src/block-controls) instead of the
 * field generated by WordPress: 'address', 'cat' (section slug), 'cats' (slugs, comma separated),
 * 'cat-id' (section ID), 'product', 'page' (page ID), 'pages' (page IDs, comma separated).
 * 'empty' — what an empty value means (“the section of this page”).
 *
 * @param string $name Block name.
 * @param array  $defs Attribute name => definition.
 * @return array
 */
function memorial_plugin_block_attributes( $name, $defs ) {
	$out = array();
	foreach ( $defs as $attr => $def ) {
		if ( ! empty( $def['picker'] ) ) {
			memorial_plugin_block_pickers(
				$name,
				$attr,
				array(
					'kind'  => (string) $def['picker'],
					'label' => (string) ( $def['label'] ?? '' ),
					'empty' => (string) ( $def['empty'] ?? '' ),
				)
			);
		}
		$out[ $attr ] = array_intersect_key( $def, array_flip( array( 'type', 'default', 'enum', 'label' ) ) );
	}
	return $out;
}

/**
 * Registry of the choosers: block name => attribute => chooser.
 *
 * @param string|null $name   Block name (to add a chooser).
 * @param string|null $attr   Attribute.
 * @param array|null  $picker Chooser: kind, label, empty.
 * @return array<string,array<string,array>>
 */
function memorial_plugin_block_pickers( $name = null, $attr = null, $picker = null ) {
	static $pickers = array();
	if ( null !== $name && null !== $attr && null !== $picker ) {
		$pickers[ $name ][ $attr ] = $picker;
	}
	return $pickers;
}

/**
 * Attributes with a chooser get no field generated by WordPress (supports.autoRegister),
 * so the block settings do not show the same setting twice; every plugin block can be folded in the editor.
 *
 * @param array  $args Block type arguments.
 * @param string $name Block name.
 * @return array
 */
function memorial_plugin_block_no_auto_controls( $args, $name ) {
	if ( 0 !== strpos( (string) $name, 'memorial-plugin/' ) ) {
		return $args;
	}
	// Folded in the editor (src/block-controls): saved with the block, changes nothing on the site.
	$args['attributes']                    = (array) ( $args['attributes'] ?? array() );
	$args['attributes']['editorCollapsed'] = array(
		'type'    => 'boolean',
		'default' => false,
	);
	$pickers = memorial_plugin_block_pickers()[ $name ] ?? array();
	foreach ( array_keys( $pickers ) as $attr ) {
		if ( isset( $args['attributes'][ $attr ] ) ) {
			unset( $args['attributes'][ $attr ]['autoGenerateControl'] );
		}
	}
	return $args;
}
add_filter( 'register_block_type_args', 'memorial_plugin_block_no_auto_controls', PHP_INT_MAX, 2 );

/**
 * Editor: choosers in the settings of the plugin blocks (build/block-controls).
 */
function memorial_plugin_block_controls_assets() {
	$asset_file = MEMORIAL_PLUGIN_DIR . '/build/block-controls/index.asset.php';
	if ( ! is_readable( $asset_file ) ) {
		return;
	}
	$asset = require $asset_file;
	wp_enqueue_script(
		'memorial-plugin-block-controls',
		MEMORIAL_PLUGIN_URL . '/build/block-controls/index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);
	if ( is_readable( MEMORIAL_PLUGIN_DIR . '/build/block-controls/style-index.css' ) ) {
		wp_enqueue_style( 'memorial-plugin-block-controls', MEMORIAL_PLUGIN_URL . '/build/block-controls/style-index.css', array( 'wp-components' ), $asset['version'] );
	}
	wp_set_script_translations( 'memorial-plugin-block-controls', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
	$addresses = array();
	foreach ( memorial_plugin_addrs() as $i => $a ) {
		$addresses[] = array(
			'value' => $i,
			'label' => memorial_plugin_addr( (string) $a['addr'] ) . ( ! empty( $a['hours'] ) ? ' · ' . $a['hours'] : '' ),
		);
	}
	wp_add_inline_script(
		'memorial-plugin-block-controls',
		'window.memorialPluginBlockControls = ' . wp_json_encode(
			array(
				'pickers'   => memorial_plugin_block_pickers(),
				'addresses' => $addresses,
			)
		) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'memorial_plugin_block_controls_assets' );

/**
 * REST: can a block find its data on this page by itself (GET /memorial-plugin/v1/block-source?block=…&post=…)?
 * The editor shows a chooser instead of an empty preview when it cannot.
 */
function memorial_plugin_block_source_rest() {
	register_rest_route(
		'memorial-plugin/v1',
		'/block-source',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => static function ( WP_REST_Request $request ) {
				$post = (int) $request['post'];
				return $post ? current_user_can( 'edit_post', $post ) : current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'block' => array(
					'type'     => 'string',
					'required' => true,
					'pattern'  => '^memorial-plugin/[a-z0-9-]+$',
				),
				'post'  => array(
					'type'    => 'integer',
					'default' => 0,
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				return rest_ensure_response( memorial_plugin_block_source( (string) $request['block'], (int) $request['post'] ) );
			},
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_block_source_rest' );

/**
 * What a block shows on a page when nothing is chosen in its settings.
 *
 * @param string $block Block name.
 * @param int    $post  Post ID.
 * @return array{found:bool,items:string[]}
 */
function memorial_plugin_block_source( $block, $post ) {
	$items = array();
	switch ( $block ) {
		case 'memorial-plugin/cards':
			foreach ( $post && function_exists( 'memorial_plugin_cards_pages' ) ? memorial_plugin_cards_pages( '', $post ) : array() as $page ) {
				$items[] = '' !== $page[1] ? $page[1] : get_the_title( $page[0] );
			}
			break;
		case 'memorial-plugin/catalog':
		case 'memorial-plugin/category-list':
		case 'memorial-plugin/category-photos':
		case 'memorial-plugin/catalog-archive':
			$term = $post && function_exists( 'memorial_plugin_page_term' ) ? (int) memorial_plugin_page_term( $post ) : 0;
			$term = $term ? get_term( $term, 'mem_cat' ) : null;
			if ( $term && ! is_wp_error( $term ) ) {
				$items[] = $term->name;
			}
			break;
		case 'memorial-plugin/product':
			if ( $post && 'mem_product' === get_post_type( $post ) ) {
				$items[] = get_the_title( $post );
			}
			break;
		default:
			$items[] = '';
	}
	return array(
		'found' => (bool) $items,
		'items' => array_values( array_filter( $items, 'strlen' ) ),
	);
}
