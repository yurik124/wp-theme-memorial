<?php
/**
 * Home page in blocks: the small blocks the sections of the home page are made of.
 *
 * Texts (eyebrows, headings, paragraphs, lists) are core blocks and are edited in place in the Site Editor;
 * data from other settings (services, catalog tiles, packages, contacts, maps, Yandex reviews) and the
 * decorations (laurel, icons) are small plugin blocks. Sections can be moved, removed and duplicated
 * like any blocks.
 *
 * The sections live in the content of the front page (blocks/home/pages-to-content.php).
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
		'laurel'          => array(
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
		'icon'            => array(
			'title'  => __( 'Icon in a circle', 'memorial-plugin' ),
			'desc'   => __( 'An icon of the site set (candle, cross, phone, clock…) in a round frame. The icon is chosen in the block settings.', 'memorial-plugin' ),
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
		'phone-button'    => array(
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
		'link-button'     => array(
			'title'  => __( 'Link button', 'memorial-plugin' ),
			'desc'   => __( 'A button in the site style (green, light or outlined) that leads to a page of the site or to any address. The text and the page are set in the block settings on the right.', 'memorial-plugin' ),
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
					'label'   => __( 'Page (the link follows the page if it moves)', 'memorial-plugin' ),
					'picker'  => 'page',
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
		'payment'         => array(
			'title'  => __( 'Payment', 'memorial-plugin' ),
			'desc'   => __( 'Ways of payment and the instalment plan from Memorial → Prices → Payment — the same on every page.', 'memorial-plugin' ),
			'icon'   => 'money-alt',
			'attrs'  => array(
				'context' => array(
					'type'    => 'string',
					'default' => '',
					'enum'    => array( '', 'funeral', 'monuments' ),
					'options' => array(
						''          => __( 'All ways of payment', 'memorial-plugin' ),
						'funeral'   => __( 'Funeral (without the instalment plan)', 'memorial-plugin' ),
						'monuments' => __( 'Monuments and landscaping', 'memorial-plugin' ),
					),
					'label'   => __( 'For what', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_payment_render',
		),
		'guarantees'      => array(
			'title'  => __( 'Our guarantees', 'memorial-plugin' ),
			'desc'   => __( 'Guarantees from Memorial → Prices → Guarantees: cards with an icon, a heading and a text — the same on every page.', 'memorial-plugin' ),
			'icon'   => 'shield',
			'attrs'  => array(
				'set'     => array(
					'type'    => 'string',
					'default' => 'all',
					'enum'    => array( 'all', 'funeral', 'monuments' ),
					'options' => array(
						'all'       => __( 'All points', 'memorial-plugin' ),
						'funeral'   => __( 'Funeral (without the instalment plan)', 'memorial-plugin' ),
						'monuments' => __( 'Monuments and landscaping', 'memorial-plugin' ),
					),
					'label'   => __( 'Which points', 'memorial-plugin' ),
				),
				'count'   => array(
					'type'    => 'integer',
					'default' => 0,
					'label'   => __( 'How many (0 — all)', 'memorial-plugin' ),
				),
				'heading' => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Heading of the section', 'memorial-plugin' ),
				),
				'title'   => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Heading (empty — from the settings, “Our guarantees”)', 'memorial-plugin' ),
				),
				'cols'    => array(
					'type'    => 'integer',
					'default' => 3,
					'enum'    => array( 2, 3 ),
					'options' => array(
						2 => '2',
						3 => '3',
					),
					'label'   => __( 'Columns on a computer', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_guarantees_render',
		),
		'benefits'        => array(
			'title'  => __( 'Benefit and payments', 'memorial-plugin' ),
			'desc'   => __( 'The burial benefit and the payment for participants of the special military operation — the sums from Memorial → Prices → Separate prices (codes posobie and vyplata_svo).', 'memorial-plugin' ),
			'icon'   => 'id-alt',
			'attrs'  => array(
				'show'    => array(
					'type'    => 'string',
					'default' => 'both',
					'enum'    => array( 'both', 'posobie', 'svo' ),
					'options' => array(
						'both'    => __( 'The benefit and the payment', 'memorial-plugin' ),
						'posobie' => __( 'Only the social benefit', 'memorial-plugin' ),
						'svo'     => __( 'Only the payment for participants of the special military operation', 'memorial-plugin' ),
					),
					'label'   => __( 'What to show', 'memorial-plugin' ),
				),
				'heading' => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Heading of the section', 'memorial-plugin' ),
				),
				'title'   => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Heading (empty — “Benefit and payments for a burial”)', 'memorial-plugin' ),
				),
				'link'    => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Link “More about the benefit”', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_benefits_render',
		),
		'price-list'      => array(
			'title'  => __( 'Prices one by one', 'memorial-plugin' ),
			'desc'   => __( 'A table “service — price from — more” from the codes of Memorial → Prices → Separate prices. A row whose code has no price is not shown.', 'memorial-plugin' ),
			'icon'   => 'list-view',
			'attrs'  => array(
				'rows'   => array(
					'type'    => 'array',
					'default' => array(),
					'label'   => __( 'Rows', 'memorial-plugin' ),
					'picker'  => 'price-rows',
				),
				'preset' => array(
					'type'    => 'string',
					'default' => '',
					'enum'    => array( '', 'funeral', 'monuments' ),
					'options' => array(
						''          => __( 'None', 'memorial-plugin' ),
						'funeral'   => __( 'Funeral and goods', 'memorial-plugin' ),
						'monuments' => __( 'Monuments and landscaping', 'memorial-plugin' ),
					),
					'label'   => __( 'Ready set when there are no rows of its own', 'memorial-plugin' ),
				),
				'title'  => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Heading (empty — none)', 'memorial-plugin' ),
				),
				'note'   => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Line under the table', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_price_list_render',
		),
		'night-line'      => array(
			'title'  => __( 'Line “Calls at night”', 'memorial-plugin' ),
			'desc'   => __( 'Note about calls at night (Memorial → Contacts).', 'memorial-plugin' ),
			'icon'   => 'clock',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_night',
		),
		'stat'            => array(
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
		'services-grid'   => array(
			'title'  => __( 'Services grid', 'memorial-plugin' ),
			'desc'   => __( 'Service cards of the home page (Memorial → Services), full width.', 'memorial-plugin' ),
			'icon'   => 'screenoptions',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_services',
		),
		'packages-grid'   => array(
			'title'  => __( 'Funeral packages grid', 'memorial-plugin' ),
			'desc'   => __( 'Funeral package cards as on the home page (Memorial → Prices).', 'memorial-plugin' ),
			'icon'   => 'money-alt',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_packages',
		),
		'contacts-list'   => array(
			'title'  => __( 'Contacts list', 'memorial-plugin' ),
			'desc'   => __( 'All phones, emails, messengers and addresses with hours (Memorial → Contacts).', 'memorial-plugin' ),
			'icon'   => 'id',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_contacts',
		),
		'maps'            => array(
			'title'  => __( 'Maps', 'memorial-plugin' ),
			'desc'   => __( 'Map for each address; loads on a click so the page stays fast.', 'memorial-plugin' ),
			'icon'   => 'location-alt',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_maps',
		),
		'review-buttons'  => array(
			'title'  => __( 'Yandex review buttons', 'memorial-plugin' ),
			'desc'   => __( '“Leave a review” and “We are on Yandex Maps” buttons.', 'memorial-plugin' ),
			'icon'   => 'external',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_review_buttons',
		),
		'review-widget'   => array(
			'title'  => __( 'Yandex reviews widget', 'memorial-plugin' ),
			'desc'   => __( 'Official Yandex Maps reviews widget; loads after cookie consent or on a click.', 'memorial-plugin' ),
			'icon'   => 'star-filled',
			'attrs'  => array(),
			'render' => 'memorial_plugin_home_render_review_widget',
		),
		'contacts-badges' => array(
			'title'  => __( 'Opening hours badge', 'memorial-plugin' ),
			'desc'   => __( 'Opening hours of the first address, or the night line when it is set (Memorial → Contacts).', 'memorial-plugin' ),
			'icon'   => 'clock',
			'attrs'  => array(),
			'render' => static fn() => memorial_plugin_home_part( 'contacts-badges' ),
		),
		'contacts-cards'  => array(
			'title'  => __( 'Contact cards', 'memorial-plugin' ),
			'desc'   => __( '“Call”, “Write” and the addresses with directions (Memorial → Contacts).', 'memorial-plugin' ),
			'icon'   => 'id-alt',
			'attrs'  => array(
				'only'      => array(
					'type'    => 'string',
					'default' => 'all',
					'enum'    => array( 'all', 'shop', 'hall' ),
					'options' => array(
						'all'  => __( 'All addresses', 'memorial-plugin' ),
						'shop' => __( 'Only where there is a shop', 'memorial-plugin' ),
						'hall' => __( 'Only where there is the funeral hall', 'memorial-plugin' ),
					),
					'label'   => __( 'Which addresses', 'memorial-plugin' ),
				),
				'landmarks' => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'District, bus stop and landmarks under the address', 'memorial-plugin' ),
				),
				'route'     => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( '“Get directions” link (Yandex Maps)', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_contacts_cards_render',
		),
		'vehicle'         => array(
			'title'  => __( 'Funeral transport', 'memorial-plugin' ),
			'desc'   => __( 'The hearse from Memorial → Transport: photos outside and inside, seats, features, the price of a trip and the call button; a line about the bus. Not for the page of the transportation of the deceased.', 'memorial-plugin' ),
			'icon'   => 'car',
			'attrs'  => array(
				'vehicle' => array(
					'type'    => 'integer',
					'default' => 0,
					'label'   => __( 'Which car (0 — all, 1 — the first in the list…)', 'memorial-plugin' ),
				),
				'price'   => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Price of a trip', 'memorial-plugin' ),
				),
				'bus'     => array(
					'type'    => 'boolean',
					'default' => true,
					'label'   => __( 'Line about the bus', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_vehicle_render',
		),
		'checked'         => array(
			'title'  => __( 'Source and date of the check', 'memorial-plugin' ),
			'desc'   => __( 'Small text at the end of an article: “Checked on … against official sources” with the links. A date older than 12 months is marked in the list of pages.', 'memorial-plugin' ),
			'icon'   => 'yes-alt',
			'attrs'  => array(
				'date'    => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Date of the check', 'memorial-plugin' ),
					'picker'  => 'date',
				),
				'sources' => array(
					'type'    => 'array',
					'default' => array(),
					'label'   => __( 'Sources', 'memorial-plugin' ),
					'picker'  => 'sources',
				),
				'text'    => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Text (empty — “Checked on {date} against official sources”)', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_checked_render',
		),
		'service-area'    => array(
			'title'  => __( 'Where we work', 'memorial-plugin' ),
			'desc'   => __( 'The city, the region and the suburbs with the terms of the visit (Memorial → Contacts → Where we work), one or two lines.', 'memorial-plugin' ),
			'icon'   => 'location-alt',
			'attrs'  => array(
				'context' => array(
					'type'    => 'string',
					'default' => 'funeral',
					'enum'    => array( 'funeral', 'landscaping' ),
					'options' => array(
						'funeral'     => __( 'Funeral: the visit of the agent', 'memorial-plugin' ),
						'landscaping' => __( 'Landscaping: work in the region', 'memorial-plugin' ),
					),
					'label'   => __( 'Which terms', 'memorial-plugin' ),
				),
			),
			'render' => 'memorial_plugin_service_area_render',
		),
		'company-details' => array(
			'title'  => __( 'Company details', 'memorial-plugin' ),
			'desc'   => __( 'Legal name, taxpayer and registration numbers (Memorial → Contacts).', 'memorial-plugin' ),
			'icon'   => 'businessperson',
			'attrs'  => array(),
			'render' => static fn() => memorial_plugin_home_part( 'company-details' ),
		),
	);
}

/**
 * Register the small blocks.
 */
function memorial_plugin_home_blocks_register() {
	foreach ( memorial_plugin_home_blocks_list() as $slug => $def ) {
		$render = $def['render'];
		memorial_plugin_register_block(
			$slug,
			array(
				'api_version'     => 3,
				'title'           => $def['title'],
				'description'     => $def['desc'],
				'category'        => memorial_plugin_block_category( $slug ),
				'icon'            => $def['icon'],
				'textdomain'      => 'memorial-plugin',
				'keywords'        => memorial_plugin_block_keywords_of( $slug ),
				'example'         => memorial_plugin_block_example( $def['attrs'], in_array( $slug, array( 'laurel', 'icon', 'phone-button', 'link-button', 'night-line', 'stat' ), true ) ? 400 : 1000 ),
				'attributes'      => memorial_plugin_block_attributes( 'memorial-plugin/' . $slug, $def['attrs'] ),
				// The grids repeat “Our services” and “Funeral packages”: they stay for the pages that have them.
				'supports'        => memorial_plugin_block_supports( 'full', array( 'inserter' => ! in_array( $slug, array( 'services-grid', 'packages-grid' ), true ) ) ),
				'render_callback' => static function ( $attributes ) use ( $render ) {
					$html = (string) call_user_func( $render, (array) $attributes );
					return '' === $html ? '' : memorial_plugin_add_wrapper_attributes( $html );
				},
			)
		);
	}
}
add_action( 'init', 'memorial_plugin_home_blocks_register', 20 );

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
		return memorial_plugin_block_note( __( 'Link button: write the text of the button and choose the page (or type an address) in the block settings on the right.', 'memorial-plugin' ) );
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
	return memorial_plugin_packages_markup();
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
