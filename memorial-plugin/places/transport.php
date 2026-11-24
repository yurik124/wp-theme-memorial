<?php
/**
 * «Мемориал → Транспорт» и блок «Ритуальный транспорт» (memorial-plugin/vehicle) — ТЗ «Блоки и доработки»,
 * 24.11.2026, раздел 6: машина (сейчас одна — Ford Transit на 8 мест), фото снаружи и салона, особенности,
 * цена «за рейс» из кода katafalk, строка про автобус (код avtobus).
 * Блок не ставится на страницу груза 200 — это проверяет tools/content-lint.php.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Transport schema.
 *
 * @return array
 */
function memorial_plugin_transport_schema() {
	return array(
		'vehicles' => array(
			'type'     => 'repeater',
			'label'    => __( 'Cars', 'memorial-plugin' ),
			'add'      => __( 'Add a car', 'memorial-plugin' ),
			'collapse' => 'model',
			'summary'  => array( 'seats', 'show' ),
			'fields'   => array(
				'model'    => array(
					'type'        => 'text',
					'label'       => __( 'Model', 'memorial-plugin' ),
					'placeholder' => 'Ford Transit',
				),
				'seats'    => array(
					'type'  => 'number',
					'label' => __( 'Seats for the mourners', 'memorial-plugin' ),
				),
				'features' => array(
					'type'  => 'textarea',
					'label' => __( 'Features — one per line', 'memorial-plugin' ),
					'rows'  => 4,
				),
				'photos'   => array(
					'type'   => 'repeater',
					'label'  => __( 'Photos: outside and inside (2–6)', 'memorial-plugin' ),
					'add'    => __( 'Add a photo', 'memorial-plugin' ),
					'inline' => true,
					'fields' => array(
						'img' => array(
							'type'  => 'image',
							'label' => __( 'Photo', 'memorial-plugin' ),
						),
						'alt' => array(
							'type'  => 'text',
							'label' => __( 'What is in the photo', 'memorial-plugin' ),
						),
					),
				),
				'show'     => array(
					'type'    => 'checkbox',
					'label'   => __( 'Show on the site', 'memorial-plugin' ),
					'short'   => __( 'on the site', 'memorial-plugin' ),
					'default' => 1,
				),
			),
		),
		'terms'    => array(
			'type'   => 'group',
			'label'  => __( 'Price and bus', 'memorial-plugin' ),
			'help'   => __( 'The price of a trip is the price with the code katafalk in Memorial → Prices → Separate prices, the bus — the code avtobus. Empty price — the line is shown without a sum.', 'memorial-plugin' ),
			'fields' => array(
				'bus'      => array(
					'type'  => 'checkbox',
					'label' => __( 'A bus for the mourners can be ordered', 'memorial-plugin' ),
				),
				'bus_text' => array(
					'type'  => 'text',
					'label' => __( 'Line about the bus', 'memorial-plugin' ),
				),
			),
		),
	);
}

/**
 * Transport defaults (the company card: a hearse for 8 seats, a bus on order).
 *
 * @return array
 */
function memorial_plugin_transport_defaults() {
	return array(
		'vehicles' => array(
			array(
				'model'    => 'Ford Transit',
				'seats'    => 8,
				'features' => "Специализированный катафалк\nПровожающие едут вместе с гробом\nВходит в пакеты похорон, можно заказать отдельно",
				'photos'   => array(),
				'show'     => 1,
			),
		),
		'terms'    => array(
			'bus'      => 1,
			'bus_text' => 'Автобус для провожающих — по заказу',
		),
	);
}

add_filter(
	'memorial_plugin_admin_pages',
	static function ( $pages ) {
		$pages['mem_transport'] = array(
			'slug'   => 'mem-transport',
			'title'  => __( 'Transport', 'memorial-plugin' ),
			'schema' => 'memorial_plugin_transport_schema',
			'intro'  => __( 'The hearse and the bus: shown by the “Funeral transport” block (hearse page, funeral pages). The prices are in Memorial → Prices → Separate prices: codes katafalk and avtobus.', 'memorial-plugin' ),
		);
		return $pages;
	}
);

/**
 * Cars shown on the site.
 *
 * @return array<int,array>
 */
function memorial_plugin_vehicles() {
	$out = array();
	foreach ( (array) ( memorial_plugin_settings( 'mem_transport' )['vehicles'] ?? array() ) as $v ) {
		if ( is_array( $v ) && ! empty( $v['show'] ) && '' !== trim( (string) ( $v['model'] ?? '' ) ) ) {
			$out[] = $v;
		}
	}
	return $out;
}

/**
 * One photo: the image of the media library with its sizes (srcset), the click opens it large.
 *
 * @param string $url   Address of the photo.
 * @param string $alt   What is in it.
 * @param bool   $first The big one (eager loading is not needed: the block is not on the first screen).
 * @return string
 */
function memorial_plugin_vehicle_photo( $url, $alt, $first ) {
	$id   = attachment_url_to_postid( $url );
	$full = $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : $url;
	$img  = $id
		? wp_get_attachment_image(
			$id,
			$first ? 'large' : 'medium',
			false,
			array(
				'alt'     => $alt,
				'loading' => 'lazy',
				'sizes'   => $first ? '(max-width: 782px) 100vw, 560px' : '(max-width: 782px) 33vw, 180px',
			)
		)
		: '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';
	return '<a class="mvehicle__photo' . ( $first ? ' is-main' : '' ) . '" href="' . esc_url( $full ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( sprintf( /* translators: %s: what is in the photo. */ __( 'Open the photo larger: %s', 'memorial-plugin' ), $alt ) ) . '">' . $img . '</a>';
}

/**
 * The “Funeral transport” block.
 *
 * @param array $a Attributes: vehicle (0 — all, otherwise the number in the list), price, bus.
 * @return string
 */
function memorial_plugin_vehicle_render( $a = array() ) {
	$cars = memorial_plugin_vehicles();
	$n    = (int) ( $a['vehicle'] ?? 0 );
	if ( $n > 0 ) {
		$cars = isset( $cars[ $n - 1 ] ) ? array( $cars[ $n - 1 ] ) : array();
	}
	if ( ! $cars ) {
		return memorial_plugin_block_note( __( 'Funeral transport: add a car in Memorial → Transport.', 'memorial-plugin' ) );
	}
	$price = function_exists( 'memorial_plugin_price_list_value' ) ? memorial_plugin_price_list_value( 'katafalk' ) : '';
	$html  = '';
	foreach ( $cars as $car ) {
		$photos = array_values( array_filter( (array) ( $car['photos'] ?? array() ), static fn( $p ) => '' !== trim( (string) ( $p['img'] ?? '' ) ) ) );
		$model  = trim( (string) $car['model'] );
		$gal    = '';
		foreach ( array_slice( $photos, 0, 6 ) as $i => $p ) {
			$gal .= memorial_plugin_vehicle_photo( (string) $p['img'], trim( (string) ( $p['alt'] ?? '' ) ) ? trim( (string) $p['alt'] ) : $model, 0 === $i );
		}
		$list = '';
		foreach ( array_filter( array_map( 'trim', explode( "\n", (string) ( $car['features'] ?? '' ) ) ) ) as $f ) {
			$list .= '<li>' . esc_html( memorial_plugin_t( $f ) ) . '</li>';
		}
		$seats = (int) ( $car['seats'] ?? 0 );
		$cost  = '';
		if ( ! isset( $a['price'] ) || ! empty( $a['price'] ) ) {
			$cost = '' !== $price
				/* translators: %s: price of a trip. */
				? sprintf( __( 'Separately — from %s per trip; in the funeral packages it is included.', 'memorial-plugin' ), $price )
				: __( 'Included in the funeral packages; can be ordered separately.', 'memorial-plugin' );
		}
		$html .= '<div class="mvehicle__car' . ( $gal ? '' : ' is-text' ) . '">'
			. ( $gal ? '<div class="mvehicle__gallery">' . $gal . '</div>' : '' )
			. '<div class="mvehicle__body">'
			. '<h3 class="mvehicle__model">' . esc_html( $model ) . '</h3>'
			/* translators: %d: number of seats. */
			. ( $seats ? '<p class="mvehicle__seats">' . esc_html( sprintf( _n( '%d seat for the mourners', '%d seats for the mourners', $seats, 'memorial-plugin' ), $seats ) ) . '</p>' : '' )
			. ( $list ? '<ul class="wp-block-list is-style-checks mvehicle__list">' . $list . '</ul>' : '' )
			. ( '' !== $cost ? '<p class="mvehicle__price">' . esc_html( $cost ) . '</p>' : '' )
			. ( function_exists( 'memorial_plugin_home_render_phone_button' ) ? memorial_plugin_home_render_phone_button(
				array(
					'variant' => 'gold',
					'large'   => true,
				)
			) : '' )
			. '</div></div>';
	}
	$terms = (array) ( memorial_plugin_settings( 'mem_transport' )['terms'] ?? array() );
	$bus   = '';
	if ( ( ! isset( $a['bus'] ) || ! empty( $a['bus'] ) ) && ! empty( $terms['bus'] ) && '' !== trim( (string) ( $terms['bus_text'] ?? '' ) ) ) {
		$bus_price = function_exists( 'memorial_plugin_price_list_value' ) ? memorial_plugin_price_list_value( 'avtobus' ) : '';
		/* translators: %s: price of a trip of the bus. */
		$bus = '<p class="mvehicle__bus">' . memorial_plugin_icon( 'car' ) . esc_html( rtrim( memorial_plugin_t( (string) $terms['bus_text'] ), '.' ) . ( '' !== $bus_price ? ', ' . sprintf( __( 'from %s per trip', 'memorial-plugin' ), $bus_price ) : '' ) . '.' ) . '</p>';
	}
	return '<section class="mvehicle">' . $html . $bus . '</section>';
}
