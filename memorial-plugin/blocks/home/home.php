<?php
/**
 * Home page in blocks: the sections of Memorial → Home page become blocks of the front-page template.
 *
 * Texts (eyebrows, headings, paragraphs, lists) are core blocks and are edited in place in the Site Editor;
 * data from other settings (services, catalog tiles, packages, contacts, maps, Yandex reviews) and the
 * decorations (laurel, icons) are small plugin blocks. Sections can be moved, removed and duplicated
 * like any blocks.
 *
 * One-time transfer (memorial_plugin_home_migrate): the theme’s front-page template with the sections
 * built from the mem_home setting is saved as the site’s own template (Appearance → Editor). After that
 * mem_home is no longer read for the page layout; it stays for rollback until 2.1.0 (delete the saved
 * template in the Site Editor — “Reset” — and the page is built from mem_home again).
 * The log is the memorial_plugin_home_migrated option.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small blocks used by the home sections: slug => title, description, icon, attributes, render function.
 *
 * @return array<string,array>
 */
function memorial_plugin_home_blocks_list() {
	$icons = array_keys( function_exists( 'memorial_plugin_icon_labels' ) ? memorial_plugin_icon_labels() : array( 'phone' => '' ) );
	return array(
		'laurel'         => array(
			'title'  => __( 'Laurel decoration', 'memorial-plugin' ),
			'desc'   => __( 'Laurel branch, divider, leaf or wreath drawn by the theme.', 'memorial-plugin' ),
			'icon'   => 'palmtree',
			'attrs'  => array(
				'kind' => array(
					'type'    => 'string',
					'default' => 'divider',
					'enum'    => array( 'divider', 'branch_plain', 'leaf', 'wreath', 'wreath_berry' ),
					'label'   => __( 'Shape', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_home_render_laurel',
		),
		'icon'           => array(
			'title'  => __( 'Memorial icon', 'memorial-plugin' ),
			'desc'   => __( 'Icon from the Memorial set in a round frame.', 'memorial-plugin' ),
			'icon'   => 'star-empty',
			'attrs'  => array(
				'name' => array(
					'type'    => 'string',
					'default' => 'candle',
					'enum'    => $icons,
					'label'   => __( 'Icon', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_home_render_icon',
		),
		'phone-button'   => array(
			'title'  => __( 'Phone button', 'memorial-plugin' ),
			'desc'   => __( 'Button that calls the main phone number (Memorial → Contacts).', 'memorial-plugin' ),
			'icon'   => 'phone',
			'attrs'  => array(
				'variant' => array(
					'type'    => 'string',
					'default' => 'gold',
					'enum'    => array( 'gold', 'light', 'line' ),
					'label'   => __( 'Style', 'memorial-plugin' ),
				),
				'large'   => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Large', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_home_render_phone_button',
		),
		'link-button'    => array(
			'title'  => __( 'Link button', 'memorial-plugin' ),
			'desc'   => __( 'Button-styled link to a page of the site.', 'memorial-plugin' ),
			'icon'   => 'button',
			'attrs'  => array(
				'text'    => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Text', 'memorial-plugin' ),
				),
				'page'    => array(
					'type'    => 'integer',
					'default' => 0,
					'label'   => __( 'Page ID (the link follows the page if it moves)', 'memorial-plugin' ),
				),
				'url'     => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Or address', 'memorial-plugin' ),
				),
				'variant' => array(
					'type'    => 'string',
					'default' => 'light',
					'enum'    => array( 'gold', 'light', 'line' ),
					'label'   => __( 'Style', 'memorial-plugin' ),
				),
				'large'   => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Large', 'memorial-plugin' ),
				),
				'arrow'   => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Arrow “→”', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_home_render_link_button',
		),
		'night-line'     => array(
			'title'  => __( 'Night phone line', 'memorial-plugin' ),
			'desc'   => __( 'Note about calls at night (Memorial → Contacts).', 'memorial-plugin' ),
			'icon'   => 'clock',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_night',
		),
		'stat'           => array(
			'title'  => __( 'Figure in a laurel wreath', 'memorial-plugin' ),
			'desc'   => __( 'Number with a caption in a laurel wreath; the number counts up when it scrolls into view.', 'memorial-plugin' ),
			'icon'   => 'awards',
			'attrs'  => array(
				'number' => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Number', 'memorial-plugin' ),
				),
				'label'  => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Caption', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_home_render_stat',
		),
		'catalog-tiles'  => array(
			'title'  => __( 'Catalog tiles', 'memorial-plugin' ),
			'desc'   => __( 'Catalog sections as photo tiles (menu sub-items of the catalog page, or all sections).', 'memorial-plugin' ),
			'icon'   => 'screenoptions',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_tiles',
		),
		'services-grid'  => array(
			'title'  => __( 'Services grid', 'memorial-plugin' ),
			'desc'   => __( 'Service cards of the home page (Memorial → Home page → Services), full width.', 'memorial-plugin' ),
			'icon'   => 'screenoptions',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_services',
		),
		'packages-grid'  => array(
			'title'  => __( 'Funeral packages grid', 'memorial-plugin' ),
			'desc'   => __( 'Funeral package cards as on the home page (Memorial → Prices).', 'memorial-plugin' ),
			'icon'   => 'money-alt',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_packages',
		),
		'contacts-list'  => array(
			'title'  => __( 'Contacts list', 'memorial-plugin' ),
			'desc'   => __( 'All phones, emails, messengers and addresses with hours (Memorial → Contacts).', 'memorial-plugin' ),
			'icon'   => 'id',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_contacts',
		),
		'maps'           => array(
			'title'  => __( 'Maps', 'memorial-plugin' ),
			'desc'   => __( 'Map for each address; loads on a click so the page stays fast.', 'memorial-plugin' ),
			'icon'   => 'location-alt',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_maps',
		),
		'review-buttons' => array(
			'title'  => __( 'Yandex review buttons', 'memorial-plugin' ),
			'desc'   => __( '“Leave a review” and “We are on Yandex Maps” buttons.', 'memorial-plugin' ),
			'icon'   => 'external',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_review_buttons',
		),
		'review-widget'  => array(
			'title'  => __( 'Yandex reviews widget', 'memorial-plugin' ),
			'desc'   => __( 'Official Yandex Maps reviews widget; loads after cookie consent or on a click.', 'memorial-plugin' ),
			'icon'   => 'star-filled',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_review_widget',
		),
	);
}

/**
 * Register the small blocks.
 */
function memorial_plugin_home_blocks_register() {
	foreach ( memorial_plugin_home_blocks_list() as $slug => $def ) {
		$render = $def['render'];
		register_block_type(
			'memorial-plugin/' . $slug,
			array(
				'api_version'     => 3,
				'title'           => $def['title'],
				'description'     => $def['desc'],
				'category'        => 'memorial',
				'icon'            => $def['icon'],
				'textdomain'      => 'memorial-plugin',
				'example'         => array(),
				'attributes'      => $def['attrs'],
				'supports'        => array(
					'autoRegister' => true,
					'html'         => false,
				),
				'render_callback' => static function ( $attributes ) use ( $render ) {
					$html = (string) call_user_func( $render, (array) $attributes );
					return '' === $html ? '' : memorial_plugin_add_wrapper_attributes( $html );
				},
			)
		);
	}
}
add_action( 'init', 'memorial_plugin_home_blocks_register', 30 );

/* ---------- Renders ---------- */

/**
 * Laurel decoration.
 *
 * @param array $a Attributes.
 * @return string
 */
function memorial_plugin_home_render_laurel( $a ) {
	$svg = memorial_plugin_laurel( (string) ( $a['kind'] ?? 'divider' ) );
	return '' === $svg ? '' : '<span aria-hidden="true">' . $svg . '</span>';
}

/**
 * Icon in a round frame (as in “Why people trust us”).
 *
 * @param array $a Attributes.
 * @return string
 */
function memorial_plugin_home_render_icon( $a ) {
	$icon = memorial_plugin_icon( (string) ( $a['name'] ?? '' ) );
	return '' === $icon ? '' : '<span class="feat__ico">' . $icon . '</span>';
}

/**
 * Button classes.
 *
 * @param array $a Attributes.
 * @return string
 */
function memorial_plugin_home_btn_class( $a ) {
	$variant = in_array( $a['variant'] ?? '', array( 'gold', 'light', 'line' ), true ) ? $a['variant'] : 'gold';
	return 'btn btn--' . $variant . ( ! empty( $a['large'] ) ? ' btn--lg' : '' );
}

/**
 * Phone button.
 *
 * @param array $a Attributes.
 * @return string
 */
function memorial_plugin_home_render_phone_button( $a ) {
	$tel = memorial_plugin_tel();
	if ( ! $tel ) {
		return '';
	}
	return '<a class="' . esc_attr( memorial_plugin_home_btn_class( $a ) ) . ' btn--tel" href="tel:' . esc_attr( $tel ) . '">'
		. memorial_plugin_icon( 'phone' ) . '<span class="nowrap">' . esc_html( memorial_plugin_opt( 'phone' ) ) . '</span></a>';
}

/**
 * Link button.
 *
 * @param array $a Attributes.
 * @return string
 */
function memorial_plugin_home_render_link_button( $a ) {
	$page = (int) ( $a['page'] ?? 0 );
	$url  = $page && 'publish' === get_post_status( $page ) ? get_permalink( $page ) : (string) ( $a['url'] ?? '' );
	$text = trim( (string) ( $a['text'] ?? '' ) );
	if ( '' === $url || '' === $text ) {
		return '';
	}
	return '<a class="' . esc_attr( memorial_plugin_home_btn_class( $a ) ) . '" href="' . esc_url( $url ) . '">' . esc_html( $text )
		. ( ! empty( $a['arrow'] ) ? ' <span aria-hidden="true">→</span>' : '' ) . '</a>';
}

/**
 * Night phone line.
 *
 * @return string
 */
function memorial_plugin_home_render_night() {
	if ( ! memorial_plugin_tel() || ! memorial_plugin_night() ) {
		return '';
	}
	return '<p class="hero__night">' . memorial_plugin_icon( 'clock' ) . esc_html( memorial_plugin_night() ) . '</p>';
}

/**
 * Figure in a laurel wreath.
 *
 * @param array $a Attributes.
 * @return string
 */
function memorial_plugin_home_render_stat( $a ) {
	$raw = preg_replace( '/\D/', '', (string) ( $a['number'] ?? '' ) );
	$num = '' !== $raw ? number_format( (int) $raw, 0, '', "\u{00A0}" ) : '';
	$cnt = '' !== $raw ? ' data-count="' . (int) $raw . '"' : '';
	return '<div class="stat' . ( mb_strlen( $num ) > 3 ? ' stat--long' : '' ) . '">'
		. '<span class="stat__ring">' . memorial_plugin_laurel( 'wreath_berry' ) . '<b' . $cnt . '>' . esc_html( $num ) . '</b></span>'
		. '<span class="stat__label">' . memorial_plugin_nbsp( esc_html( (string) ( $a['label'] ?? '' ) ) ) . '</span></div>';
}

/**
 * Catalog tiles: menu sub-items of the catalog page, otherwise all top sections.
 *
 * @return string
 */
function memorial_plugin_home_render_tiles() {
	$cats = get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'parent'     => 0,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $cats ) || ! $cats ) {
		return '';
	}
	ob_start();
	$root = memorial_plugin_catalog_page();
	$kids = $root ? memorial_plugin_menu_children( $root ) : array();
	if ( $kids ) {
		memorial_plugin_page_tiles( $kids );
	} else {
		memorial_plugin_cat_tiles( $cats );
	}
	return (string) ob_get_clean();
}

/**
 * Services grid (as the home section).
 *
 * @return string
 */
function memorial_plugin_home_render_services() {
	$items = memorial_plugin_services_items();
	return $items ? memorial_plugin_services_grid( $items ) : '';
}

/**
 * Funeral packages grid (as the home section).
 *
 * @return string
 */
function memorial_plugin_home_render_packages() {
	if ( ! memorial_plugin_packages() ) {
		return '';
	}
	ob_start();
	memorial_plugin_packages_html();
	return trim( (string) ob_get_clean() );
}

/**
 * Output of a shared part (blocks/legacy/render/parts) as a string.
 *
 * @param string $name Part name.
 * @return string
 */
function memorial_plugin_home_part( $name ) {
	ob_start();
	memorial_plugin_legacy_part( $name );
	return trim( (string) ob_get_clean() );
}

/**
 * Contacts list.
 *
 * @return string
 */
function memorial_plugin_home_render_contacts() {
	return memorial_plugin_home_part( 'contacts-list' );
}

/**
 * Maps.
 *
 * @return string
 */
function memorial_plugin_home_render_maps() {
	return memorial_plugin_home_part( 'maps' );
}

/**
 * Yandex Maps organization links: [widget, page, reviews] or null.
 *
 * @return array|null
 */
function memorial_plugin_home_yandex() {
	$org = memorial_plugin_yandex_org();
	if ( ! $org ) {
		return null;
	}
	return array(
		'https://yandex.ru/maps-reviews-widget/' . $org . '?comments',
		'https://yandex.ru/maps/org/' . $org . '/',
		'https://yandex.ru/maps/org/' . $org . '/reviews/',
	);
}

/**
 * “Leave a review” / “We are on Yandex Maps” buttons.
 *
 * @return string
 */
function memorial_plugin_home_render_review_buttons() {
	$y = memorial_plugin_home_yandex();
	if ( ! $y ) {
		return '';
	}
	return '<div class="reviews__btns">'
		. '<a class="btn btn--gold" href="' . esc_url( $y[2] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Leave a review', 'memorial-plugin' ) . '</a>'
		. '<a class="btn btn--line" href="' . esc_url( $y[1] ) . '" target="_blank" rel="noopener">' . esc_html__( 'We are on Yandex Maps', 'memorial-plugin' ) . '</a>'
		. '</div>';
}

/**
 * Yandex reviews widget (loads after consent or on a click — assets/js/view/blocks.js, store memorial-plugin/reviews).
 *
 * @return string
 */
function memorial_plugin_home_render_review_widget() {
	$y = memorial_plugin_home_yandex();
	if ( ! $y ) {
		return '';
	}
	return '<div class="reviews__widget" data-reviews="' . esc_url( $y[0] ) . '"><div class="reviews__ph">'
		. '<span class="reviews__stars" aria-hidden="true">★★★★★</span>'
		. '<p>' . esc_html__( 'Customer reviews on Yandex Maps', 'memorial-plugin' ) . '</p>'
		. '<button class="btn btn--line" type="button" data-reviews-load>' . esc_html__( 'Show reviews', 'memorial-plugin' ) . '</button></div>'
		/* translators: %s: site name. */
		. '<a class="reviews__credit" href="' . esc_url( $y[1] ) . '" target="_blank" rel="noopener">' . esc_html( sprintf( __( '%s on the map — Yandex Maps', 'memorial-plugin' ), get_bloginfo( 'name' ) ) ) . '</a></div>';
}
