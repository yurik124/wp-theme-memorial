<?php
/**
 * Content blocks: the block-level shortcodes ([mem_catalog], [mem_cta], [mem_works]…) as blocks.
 *
 * Each block is registered in PHP only (WordPress 7.0+, supports.autoRegister): the editor builds the
 * settings panel from the attributes and shows a server-rendered preview. The block calls the same
 * function as its shortcode, so the markup on the page is identical; the shortcodes stay as a
 * compatibility layer (full support up to 2.1.0, deprecation notice after, removal in 3.0.0).
 *
 * The same table describes how a shortcode maps to a block — the content converter
 * (admin/convert.php) uses it to turn [mem_…] into blocks and back.
 *
 * Attribute keys:
 *  - type, default, label — block attribute (string, integer or boolean);
 *  - enum                 — allowed values (select control);
 *  - sc                   — shortcode attribute name;
 *  - alias                — other shortcode attribute names with the same meaning (old spelling);
 *  - fmt                  — how the value is written in the shortcode:
 *                           'yesno' (да / нет), '10' (1 / 0), 'random' (случайно / empty),
 *                           an array block value => shortcode value, or empty for as is.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block definitions: block slug => shortcode, texts, icon and attributes.
 *
 * @return array<string,array>
 */
function memorial_plugin_content_blocks() {
	$cat   = array(
		'type'    => 'string',
		'default' => '',
		'label'   => __( 'Catalog section (name or slug; empty — the section of this page)', 'memorial-plugin' ),
		'sc'      => 'cat',
	);
	$count = array(
		'type'    => 'integer',
		'default' => 0,
		'label'   => __( 'How many to show (0 — default)', 'memorial-plugin' ),
		'sc'      => 'chislo',
	);
	$rand  = array(
		'type'    => 'boolean',
		'default' => false,
		'label'   => __( 'Random order (different on every visit)', 'memorial-plugin' ),
		'sc'      => 'poryadok',
		'fmt'     => 'random',
	);
	$price = array(
		'type'    => 'boolean',
		'default' => true,
		'label'   => __( 'Show prices', 'memorial-plugin' ),
		'sc'      => 'cena',
		'fmt'     => 'yesno',
	);
	$title = array(
		'type'    => 'string',
		'default' => '',
		'label'   => __( 'Heading (empty — from settings)', 'memorial-plugin' ),
		'sc'      => 'title',
	);
	$text  = array(
		'type'    => 'string',
		'default' => '',
		'label'   => __( 'Text (empty — from settings)', 'memorial-plugin' ),
		'sc'      => 'text',
	);
	$pop   = array(
		'type'    => 'string',
		'default' => '',
		'label'   => __( '“Often searched” words, comma separated (empty — from search statistics)', 'memorial-plugin' ),
		'sc'      => 'chasto',
	);

	return array(
		'catalog'         => array(
			'tag'   => 'mem_catalog',
			'title' => __( 'Catalog', 'memorial-plugin' ),
			'desc'  => __( 'Products of a catalog section with section links and sorting.', 'memorial-plugin' ),
			'icon'  => 'grid-view',
			'attrs' => array(
				'cat'     => $cat,
				'nav'     => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Links to sub-sections', 'memorial-plugin' ),
					'sc'      => 'nav',
					'fmt'     => '10',
				),
				'sort'    => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Sorting', 'memorial-plugin' ),
					'sc'      => 'sort',
					'fmt'     => '10',
				),
				'heading' => array(
					'type'    => 'string',
					'default' => 'h2',
					'enum'    => array( 'h2', 'h3' ),
					'label'   => __( 'Sub-section heading level', 'memorial-plugin' ),
					'sc'      => 'heading',
				),
				'show'    => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Products before “Show more” (number or “all”; empty — automatic)', 'memorial-plugin' ),
					'sc'      => 'pokazat',
				),
			),
		),
		'category-list'   => array(
			'tag'   => 'mem_cat_list',
			'title' => __( 'Catalog sub-sections list', 'memorial-plugin' ),
			'desc'  => __( 'List of sub-sections of a catalog section with links and starting prices.', 'memorial-plugin' ),
			'icon'  => 'list-view',
			'attrs' => array(
				'cat'    => $cat,
				'prices' => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Show prices', 'memorial-plugin' ),
					'sc'      => 'ceny',
					'alias'   => array( 'price' ),
					'fmt'     => 'yesno',
				),
			),
		),
		'category-photos' => array(
			'tag'   => 'mem_cat_photos',
			'title' => __( 'Catalog photos', 'memorial-plugin' ),
			'desc'  => __( 'Photo grid of products from catalog sections.', 'memorial-plugin' ),
			'icon'  => 'format-gallery',
			'attrs' => array(
				'cat'    => array_merge( $cat, array( 'label' => __( 'Catalog sections, comma separated', 'memorial-plugin' ) ) ),
				'count'  => array_merge( $count, array( 'default' => 6 ) ),
				'random' => $rand,
				'prices' => $price,
			),
		),
		'cemeteries'      => array(
			'tag'   => 'mem_cemeteries',
			'title' => __( 'Cemeteries', 'memorial-plugin' ),
			'desc'  => __( 'Cemeteries with addresses, hours and burial status (Memorial → Cemeteries).', 'memorial-plugin' ),
			'icon'  => 'location-alt',
			'attrs' => array(
				'status' => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Only with status, comma separated (действующие, подзахоронение…)', 'memorial-plugin' ),
					'sc'      => 'status',
				),
				'filter' => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Status filter buttons', 'memorial-plugin' ),
					'sc'      => 'filtr',
					'fmt'     => 'yesno',
				),
			),
		),
		'restaurants'     => array(
			'tag'   => 'mem_restaurants',
			'title' => __( 'Memorial meal halls', 'memorial-plugin' ),
			'desc'  => __( 'Halls for memorial meals with photos and an order button (Memorial → Halls).', 'memorial-plugin' ),
			'icon'  => 'food',
			'attrs' => array(
				'title' => $title,
				'text'  => $text,
			),
		),
		'reviews'         => array(
			'tag'   => 'mem_reviews',
			'title' => __( 'Reviews from review sites', 'memorial-plugin' ),
			'desc'  => __( 'Review widgets and links to review sites (Memorial → Reviews).', 'memorial-plugin' ),
			'icon'  => 'star-filled',
			'attrs' => array(),
		),
		'book-reviews'    => array(
			'tag'   => 'mem_bookreviews',
			'title' => __( 'Guest book reviews', 'memorial-plugin' ),
			'desc'  => __( 'Reviews left on this site.', 'memorial-plugin' ),
			'icon'  => 'book',
			'attrs' => array(
				'limit' => array(
					'type'    => 'integer',
					'default' => -1,
					'label'   => __( 'How many (-1 — all)', 'memorial-plugin' ),
					'sc'      => 'limit',
				),
				'title' => $title,
			),
		),
		'rewio-reviews'   => array(
			'tag'   => 'mem_rewio',
			'title' => __( 'Yandex Maps reviews', 'memorial-plugin' ),
			'desc'  => __( 'Reviews loaded from Yandex Maps by Rewio (Memorial → Rewio reviews).', 'memorial-plugin' ),
			'icon'  => 'star-half',
			'attrs' => array(
				'count' => array(
					'type'    => 'integer',
					'default' => 0,
					'label'   => __( 'How many (0 — from settings)', 'memorial-plugin' ),
					'sc'      => 'count',
				),
				'title' => $title,
				'cols'  => array(
					'type'    => 'integer',
					'default' => 0,
					'label'   => __( 'Columns (0 — from settings)', 'memorial-plugin' ),
					'sc'      => 'cols',
				),
			),
		),
		'services'        => array(
			'tag'   => 'mem_services',
			'title' => __( 'Our services', 'memorial-plugin' ),
			'desc'  => __( 'Service cards with photos and starting prices (Memorial → Home page → Services).', 'memorial-plugin' ),
			'icon'  => 'screenoptions',
			'attrs' => array(
				'more' => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Extra pages, comma separated paths (ritualnye-uslugi/pominalnyj-obed…)', 'memorial-plugin' ),
					'sc'      => 'more',
				),
			),
		),
		'faq'             => array(
			'tag'   => 'mem_faq',
			'title' => __( 'All questions and answers', 'memorial-plugin' ),
			'desc'  => __( 'Questions from all pages (Details blocks) on one page, with search.', 'memorial-plugin' ),
			'icon'  => 'editor-help',
			'attrs' => array(
				'exclude' => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Skip pages (IDs, comma separated)', 'memorial-plugin' ),
					'sc'      => 'exclude',
				),
				'search'  => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Search field', 'memorial-plugin' ),
					'sc'      => 'search',
					'fmt'     => '10',
				),
				'popular' => $pop,
			),
		),
		'guide'           => array(
			'tag'   => 'mem_guide',
			'title' => __( 'Useful to know', 'memorial-plugin' ),
			'desc'  => __( 'Article cards with search: child pages of this or another page, or chosen pages.', 'memorial-plugin' ),
			'icon'  => 'welcome-learn-more',
			'attrs' => array(
				'parent'  => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Parent page (ID or path; empty — this page)', 'memorial-plugin' ),
					'sc'      => 'roditel',
				),
				'pages'   => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Or these pages (IDs, comma separated, in this order)', 'memorial-plugin' ),
					'sc'      => 'stranicy',
				),
				'all'     => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( '“All articles” link', 'memorial-plugin' ),
					'sc'      => 'vse',
					'fmt'     => 'yesno',
				),
				'popular' => $pop,
			),
		),
		'cta'             => array(
			'tag'   => 'mem_cta',
			'title' => __( 'Call to action', 'memorial-plugin' ),
			'desc'  => __( 'Heading, text and call / message buttons with the site phone.', 'memorial-plugin' ),
			'icon'  => 'megaphone',
			'attrs' => array(
				'title' => array_merge( $title, array( 'label' => __( 'Heading (empty — “Call us — we will tell you where to start”)', 'memorial-plugin' ) ) ),
				'text'  => array_merge( $text, array( 'label' => __( 'Text (empty — standard text)', 'memorial-plugin' ) ) ),
			),
		),
		'cremation'       => array(
			'tag'   => 'mem_cremation',
			'title' => __( 'Cremation prices', 'memorial-plugin' ),
			'desc'  => __( 'Cremation price table (Memorial → Prices → Cremation).', 'memorial-plugin' ),
			'icon'  => 'editor-table',
			'attrs' => array(),
		),
		'packages'        => array(
			'tag'   => 'mem_packages',
			'title' => __( 'Funeral packages', 'memorial-plugin' ),
			'desc'  => __( 'Funeral package cards with prices (Memorial → Prices).', 'memorial-plugin' ),
			'icon'  => 'money-alt',
			'attrs' => array(
				'cremation' => array(
					'type'    => 'string',
					'default' => 'auto',
					'enum'    => array( 'auto', 'show', 'hide' ),
					'label'   => __( 'Cremation card (auto — as set in Memorial → Prices)', 'memorial-plugin' ),
					'sc'      => 'cremation',
					'fmt'     => array(
						'auto' => '',
						'show' => '1',
						'hide' => '0',
					),
				),
			),
		),
		'works'           => array(
			'tag'   => 'mem_works',
			'title' => __( 'Our works', 'memorial-plugin' ),
			'desc'  => __( 'Completed works with photos, plot sizes and prices: gallery, cards or photos only.', 'memorial-plugin' ),
			'icon'  => 'format-image',
			'attrs' => array(
				'view'   => array(
					'type'    => 'string',
					'default' => 'gallery',
					'enum'    => array( 'gallery', 'cards', 'photos' ),
					'label'   => __( 'View', 'memorial-plugin' ),
					'sc'      => 'vid',
					'fmt'     => array(
						'gallery' => '',
						'cards'   => 'карточки',
						'photos'  => 'фото',
					),
				),
				'type'   => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Only these types (slugs or names, comma separated)', 'memorial-plugin' ),
					'sc'      => 'type',
				),
				'works'  => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Cards: these works by title, comma separated', 'memorial-plugin' ),
					'sc'      => 'raboty',
				),
				'count'  => $count,
				'limit'  => array(
					'type'    => 'integer',
					'default' => -1,
					'label'   => __( 'Gallery: how many (-1 — all)', 'memorial-plugin' ),
					'sc'      => 'limit',
				),
				'random' => $rand,
				'size'   => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Show plot size', 'memorial-plugin' ),
					'sc'      => 'razmer',
					'fmt'     => 'yesno',
				),
				'prices' => $price,
				'nav'    => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Gallery: type filter', 'memorial-plugin' ),
					'sc'      => 'nav',
					'fmt'     => '10',
				),
				'sort'   => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Gallery: sorting', 'memorial-plugin' ),
					'sc'      => 'sort',
					'fmt'     => '10',
				),
			),
		),
		'cards'           => array(
			'tag'   => 'mem_cards',
			'title' => __( 'Section cards', 'memorial-plugin' ),
			'desc'  => __( 'Cards of child pages (or chosen pages) with photo, product count and starting price.', 'memorial-plugin' ),
			'icon'  => 'index-card',
			'attrs' => array(
				'pages'   => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Pages, comma separated paths (empty — sub-items in the menu or child pages)', 'memorial-plugin' ),
					'sc'      => 'pages',
				),
				'photo'   => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Photo', 'memorial-plugin' ),
					'sc'      => 'foto',
					'fmt'     => 'yesno',
				),
				'number'  => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Number of models', 'memorial-plugin' ),
					'sc'      => 'chislo',
					'fmt'     => 'yesno',
				),
				'prices'  => $price,
				'caption' => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Caption', 'memorial-plugin' ),
					'sc'      => 'podpis',
					'fmt'     => 'yesno',
				),
				'arrow'   => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Arrow “→”', 'memorial-plugin' ),
					'sc'      => 'strelka',
					'fmt'     => 'yesno',
				),
			),
		),
	);
}

/**
 * Shortcode tags of plugin features switched off in Memorial → Features.
 *
 * @return array<string,true>
 */
function memorial_plugin_content_blocks_off() {
	$off  = array();
	$dead = array_flip( memorial_plugin_disabled_files() );
	foreach ( memorial_plugin_optional() as $key => $module ) {
		if ( isset( $dead[ $key ] ) ) {
			foreach ( $module[3] as $tag ) {
				$off[ $tag ] = true;
			}
		}
	}
	return $off;
}

/**
 * Block attribute value → shortcode attribute value.
 *
 * @param array $def   Attribute definition.
 * @param mixed $value Block attribute value.
 * @return string
 */
function memorial_plugin_content_block_sc_value( $def, $value ) {
	$fmt = $def['fmt'] ?? '';
	if ( is_array( $fmt ) ) {
		return (string) ( $fmt[ $value ] ?? '' );
	}
	switch ( $fmt ) {
		case 'yesno':
			return $value ? 'да' : 'нет';
		case '10':
			return $value ? '1' : '0';
		case 'random':
			return $value ? 'случайно' : '';
	}
	return (string) $value;
}

/**
 * Shortcode attribute value → block attribute value (for the content converter).
 *
 * @param array  $def   Attribute definition.
 * @param string $value Shortcode attribute value.
 * @return mixed
 */
function memorial_plugin_content_block_attr_value( $def, $value ) {
	$value = trim( (string) $value );
	$low   = mb_strtolower( $value );
	$fmt   = $def['fmt'] ?? '';
	if ( is_array( $fmt ) ) {
		foreach ( $fmt as $block_value => $sc_value ) {
			if ( mb_strtolower( $sc_value ) === $low ) {
				return $block_value;
			}
		}
		$aliases = array(
			'cards'  => array( 'cards' ),
			'photos' => array( 'photos' ),
		);
		foreach ( $aliases as $block_value => $words ) {
			if ( isset( $fmt[ $block_value ] ) && in_array( $low, $words, true ) ) {
				return $block_value;
			}
		}
		return $def['default'];
	}
	switch ( $fmt ) {
		case 'yesno':
		case '10':
			return ! in_array( $low, array( '0', 'нет', 'no', 'false', 'off' ), true );
		case 'random':
			return in_array( $low, array( 'случайно', 'случайные', 'random' ), true );
	}
	if ( 'integer' === $def['type'] ) {
		return (int) $value;
	}
	if ( 'boolean' === $def['type'] ) {
		return ! in_array( $low, array( '', '0', 'нет', 'no', 'false', 'off' ), true );
	}
	return $value;
}

/**
 * Shortcode attributes for a block: only the ones that differ from the block defaults,
 * so the shortcode keeps its own defaults (texts from settings and so on).
 *
 * @param string $slug       Block slug.
 * @param array  $attributes Block attributes.
 * @return array<string,string>
 */
function memorial_plugin_content_block_atts( $slug, $attributes ) {
	$def  = memorial_plugin_content_blocks()[ $slug ] ?? array( 'attrs' => array() );
	$atts = array();
	foreach ( $def['attrs'] as $name => $attr ) {
		if ( ! array_key_exists( $name, $attributes ) || $attributes[ $name ] === $attr['default'] ) {
			continue;
		}
		$value = memorial_plugin_content_block_sc_value( $attr, $attributes[ $name ] );
		if ( '' !== $value || 'random' === ( $attr['fmt'] ?? '' ) ) {
			$atts[ $attr['sc'] ] = $value;
		}
	}
	return $atts;
}

/**
 * Render a content block: the shortcode function's markup, with the block class and design
 * settings (colours, spacing…) added to its outer element — without an extra wrapper,
 * so the layout stays exactly as with the shortcode.
 *
 * @param string $slug       Block slug.
 * @param array  $attributes Block attributes.
 * @return string
 */
function memorial_plugin_content_block_render( $slug, $attributes ) {
	global $shortcode_tags;
	$def = memorial_plugin_content_blocks()[ $slug ] ?? null;
	if ( ! $def || empty( $shortcode_tags[ $def['tag'] ] ) || ! is_callable( $shortcode_tags[ $def['tag'] ] ) ) {
		return '';
	}
	$html = (string) call_user_func( $shortcode_tags[ $def['tag'] ], memorial_plugin_content_block_atts( $slug, $attributes ), '', $def['tag'] );
	if ( '' === trim( $html ) ) {
		return '';
	}
	return memorial_plugin_add_wrapper_attributes( $html );
}

/**
 * Add the block wrapper attributes (class and style) to the first element of the markup.
 * Must be called while the block is being rendered.
 *
 * @param string $html Block markup.
 * @return string
 */
function memorial_plugin_add_wrapper_attributes( $html ) {
	$wrapper = get_block_wrapper_attributes();
	$tags    = new WP_HTML_Tag_Processor( '<div ' . $wrapper . '>' );
	if ( ! $tags->next_tag() ) {
		return $html;
	}
	$class = (string) $tags->get_attribute( 'class' );
	$style = (string) $tags->get_attribute( 'style' );

	$out = new WP_HTML_Tag_Processor( $html );
	if ( ! $out->next_tag() ) {
		return '<div ' . $wrapper . '>' . $html . '</div>';
	}
	foreach ( preg_split( '/\s+/', trim( $class ) ) as $name ) {
		if ( '' !== $name ) {
			$out->add_class( $name );
		}
	}
	if ( '' !== $style ) {
		$own = trim( (string) $out->get_attribute( 'style' ) );
		$out->set_attribute( 'style', ( '' !== $own ? rtrim( $own, ';' ) . ';' : '' ) . $style );
	}
	return $out->get_updated_html();
}

/**
 * Register the content blocks (except for features switched off in Memorial → Features).
 */
function memorial_plugin_content_blocks_register() {
	$off = memorial_plugin_content_blocks_off();
	foreach ( memorial_plugin_content_blocks() as $slug => $def ) {
		if ( isset( $off[ $def['tag'] ] ) ) {
			continue;
		}
		$attributes = array();
		foreach ( $def['attrs'] as $name => $attr ) {
			$attributes[ $name ] = array_intersect_key( $attr, array_flip( array( 'type', 'default', 'enum', 'label' ) ) );
		}
		register_block_type(
			'memorial-plugin/' . $slug,
			array(
				'api_version'     => 3,
				'title'           => $def['title'],
				'description'     => $def['desc'],
				'category'        => 'memorial',
				'icon'            => $def['icon'],
				'keywords'        => array( $def['tag'], 'memorial' ),
				'textdomain'      => 'memorial-plugin',
				'attributes'      => $attributes,
				'supports'        => array(
					'autoRegister' => true,
					'html'         => false,
					'spacing'      => array( 'margin' => true ),
				),
				'render_callback' => static function ( $attributes ) use ( $slug ) {
					return memorial_plugin_content_block_render( $slug, (array) $attributes );
				},
			)
		);
	}
}
add_action( 'init', 'memorial_plugin_content_blocks_register', 30 );
