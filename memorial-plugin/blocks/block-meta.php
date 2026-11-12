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
