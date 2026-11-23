<?php
/**
 * Разметка организации Schema.org.
 *
 * Google (JSON-LD, только главная): главный офис — LocalBusiness с филиалами в department, часы —
 * openingHoursSpecification. https://developers.google.com/search/docs/appearance/structured-data/local-business
 * Яндекс (микроданные, «Контакты» и подвал): каждый филиал — отдельная организация, часы — openingHours.
 * https://yandex.ru/support/webmaster/ru/search-appearance/organization
 * На других страницах — только ссылка на организацию (@id и название): продавец товара, исполнитель услуги.
 * Типа FuneralHome в schema.org нет — LocalBusiness и additionalType «Funeral home».
 *
 * Данные — «Мемориал → Контакты»: адреса (первый — главный офис), часы, ссылки на карточки на Яндекс
 * Картах и в 2ГИС, координаты. Проверка — tests/diag/schema_check.py.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Тип организации в разметке. */
const MEMORIAL_PLUGIN_ORG_TYPE = 'LocalBusiness';

/**
 * Часы работы из текста настроек в формате ISO 8601 («Mo-Sa 09:00-17:00»), иначе ''.
 *
 * @param string $s Text: «Пн–Сб с 9:00 до 17:00», «Ежедневно 8:30–17:00».
 * @return string
 */
function memorial_plugin_schema_hours( $s ) {
	if ( ! preg_match( '/(\d{1,2})[:.](\d{2})\D+(\d{1,2})[:.](\d{2})/u', (string) $s, $m ) ) {
		return '';
	}
	$t = sprintf( '%02d:%s-%02d:%s', $m[1], $m[2], $m[3], $m[4] );
	if ( preg_match( '/ежедневно|без выходных|круглосуточно/iu', $s ) ) {
		return 'Mo-Su ' . $t;
	}
	if ( preg_match( '/пн\S*\s*[-–—]\s*пт|будни/iu', $s ) ) {
		return 'Mo-Fr ' . $t;
	}
	if ( preg_match( '/пн\S*\s*[-–—]\s*сб/iu', $s ) ) {
		return 'Mo-Sa ' . $t;
	}
	return '';
}

/**
 * The same hours as OpeningHoursSpecification (Google).
 *
 * @param string $iso «Mo-Sa 09:00-17:00».
 * @return array
 */
function memorial_plugin_schema_hours_spec( $iso ) {
	$days = array( 'Mo' => 'Monday', 'Tu' => 'Tuesday', 'We' => 'Wednesday', 'Th' => 'Thursday', 'Fr' => 'Friday', 'Sa' => 'Saturday', 'Su' => 'Sunday' );
	if ( ! preg_match( '/^(\w\w)(?:-(\w\w))? (\d\d:\d\d)-(\d\d:\d\d)$/', $iso, $m ) || ! isset( $days[ $m[1] ] ) ) {
		return array();
	}
	$keys = array_keys( $days );
	$from = array_search( $m[1], $keys, true );
	$to   = '' !== $m[2] && isset( $days[ $m[2] ] ) ? array_search( $m[2], $keys, true ) : $from;
	return array(
		'@type'     => 'OpeningHoursSpecification',
		'dayOfWeek' => array_values( array_map( fn( $k ) => $days[ $k ], array_slice( $keys, $from, $to - $from + 1 ) ) ),
		'opens'     => $m[3],
		'closes'    => $m[4],
	);
}

/**
 * PostalAddress of an address line «308023, г. Белгород, ул. Студенческая, 40, корп. 10».
 *
 * @param string $addr Address.
 * @return array
 */
function memorial_plugin_schema_address( $addr ) {
	$zip  = preg_match( '/\b(\d{6})\b/', $addr, $m ) ? $m[1] : '';
	$city = preg_match( '/(?:г\.|город)\s*([^,]+)/u', $addr, $m ) ? trim( $m[1] ) : 'Белгород';
	return array_filter(
		array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => memorial_plugin_short_addr( $addr ),
			'addressLocality' => $city,
			'postalCode'      => $zip,
			'addressCountry'  => 'RU',
		)
	);
}

/**
 * Card of the address on Yandex Maps: https://yandex.ru/maps/org/<id>/ — or ''.
 *
 * @param string $url Link from the settings (any form of a Yandex Maps organization link).
 * @return string
 */
function memorial_plugin_schema_yandex_url( $url ) {
	return preg_match( '~yandex\.\w+/maps/(?:\d+/[\w-]+/)?org/(?:[\w-]+/)?(\d{5,})~', (string) $url, $m ) ? 'https://yandex.ru/maps/org/' . $m[1] . '/' : '';
}

/**
 * Card on 2GIS without the coordinates and the list of branches: https://2gis.ru/<city>/firm/<id> — or ''.
 *
 * @param string $url Link from the settings.
 * @return string
 */
function memorial_plugin_schema_2gis_url( $url ) {
	$url = rawurldecode( (string) $url );
	if ( ! preg_match( '~2gis\.(\w+)/(\w+)/.*?firm/(\d{5,})~', $url, $m ) ) {
		return '';
	}
	return 'https://2gis.' . $m[1] . '/' . $m[2] . '/firm/' . $m[3];
}

/**
 * Coordinates of an address: the “Coordinates” field (latitude, longitude), else the map link (ll= / pt= of
 * Yandex: longitude,latitude), else the 2GIS link (…/longitude,latitude).
 *
 * @param array $a Address of the settings.
 * @return array{0:float,1:float}|null [latitude, longitude]
 */
function memorial_plugin_schema_coords( $a ) {
	$ok = static fn( $lat, $lon ) => $lat > 41 && $lat < 82 && $lon > 19 && $lon < 180 ? array( round( $lat, 6 ), round( $lon, 6 ) ) : null; // Russia.
	if ( preg_match( '/(-?\d{1,3}\.\d+)\s*[,; ]\s*(-?\d{1,3}\.\d+)/', (string) ( $a['geo'] ?? '' ), $m ) ) {
		$r = $ok( (float) $m[1], (float) $m[2] ) ?? $ok( (float) $m[2], (float) $m[1] );
		if ( $r ) {
			return $r;
		}
	}
	if ( preg_match( '/(?:[?&]|&amp;)(?:ll|pt)=(-?\d{1,3}\.\d+)(?:,|%2C)(-?\d{1,3}\.\d+)/i', (string) ( $a['map'] ?? '' ), $m ) ) {
		$r = $ok( (float) $m[2], (float) $m[1] );
		if ( $r ) {
			return $r;
		}
	}
	if ( preg_match( '~/(-?\d{1,3}\.\d+)(?:,|%2C)(-?\d{1,3}\.\d+)~i', (string) ( $a['twogis'] ?? '' ), $m ) ) {
		return $ok( (float) $m[2], (float) $m[1] );
	}
	return null;
}

/**
 * The organization: @id of the main office (the first address) and of the other branches.
 *
 * @param int $i Index of the address in “Memorial → Contacts”.
 * @return string
 */
function memorial_plugin_schema_id( $i = 0 ) {
	return 0 === (int) $i ? home_url( '/#organization' ) : home_url( '/#filial-' . ( (int) $i + 1 ) );
}

/**
 * Branches: one per address of the settings, the first is the main office.
 *
 * @return array<int,array> id, name, address (PostalAddress), hours (ISO), geo, phones, profiles.
 */
function memorial_plugin_schema_branches() {
	$phones = array_values( array_unique( array_filter( array_map( fn( $p ) => memorial_plugin_tel_of( $p['num'] ), memorial_plugin_phones() ) ) ) );
	$out    = array();
	foreach ( memorial_plugin_addrs() as $i => $a ) {
		$out[] = array(
			'id'       => memorial_plugin_schema_id( $i ),
			'name'     => get_bloginfo( 'name' ),
			'address'  => memorial_plugin_schema_address( (string) $a['addr'] ),
			'hours'    => memorial_plugin_schema_hours( (string) ( $a['hours'] ?? '' ) ),
			'geo'      => memorial_plugin_schema_coords( $a ),
			// Телефоны общие: у главного офиса — все, у филиала — основной.
			'phones'   => 0 === $i ? $phones : array_slice( $phones, 0, 1 ),
			'profiles' => array_values( array_filter( array( memorial_plugin_schema_yandex_url( $a['yandex'] ?? '' ), memorial_plugin_schema_2gis_url( $a['twogis'] ?? '' ) ) ) ),
		);
	}
	return $out;
}

/**
 * Logo of the organization: the site logo, else the site icon.
 *
 * @return string
 */
function memorial_plugin_schema_logo() {
	$logo = get_option( 'site_logo' ) ? wp_get_attachment_image_url( (int) get_option( 'site_logo' ), 'full' ) : '';
	return $logo ? $logo : (string) get_site_icon_url( 512 );
}

/**
 * Profiles of the whole organization (sameAs): review sites of “Memorial → Reviews”, without the cards of
 * the branches (they are in their branch) and without messengers.
 *
 * @param array $branches memorial_plugin_schema_branches().
 * @return string[]
 */
function memorial_plugin_schema_same_as( $branches ) {
	$own  = array_merge( ...array_map( fn( $b ) => $b['profiles'], $branches ?: array( array( 'profiles' => array() ) ) ) );
	$keys = array_map( fn( $u ) => preg_replace( '~\D+~', '', $u ), $own );
	$out  = array();
	$raw  = array_map( fn( $s ) => trim( (string) ( $s['link'] ?? '' ) ), (array) ( get_option( 'mem_reviews' )['sources'] ?? array() ) );
	$org  = memorial_plugin_yandex_org();
	if ( $org ) {
		$raw[] = 'https://yandex.ru/maps/org/' . $org . '/';
	}
	foreach ( $raw as $u ) {
		if ( ! preg_match( '~^https?://~', $u ) || preg_match( '~t\.me/|telegram\.|wa\.me/|max\.ru/~', $u ) ) {
			continue;
		}
		$clean = memorial_plugin_schema_yandex_url( $u );
		$clean = $clean ? $clean : memorial_plugin_schema_2gis_url( $u );
		$clean = $clean ? $clean : $u;
		$id    = preg_match( '~(?:org|firm)/(\d{5,})~', $clean, $m ) ? $m[1] : '';
		if ( '' !== $id && array_filter( $keys, fn( $k ) => str_ends_with( $k, $id ) ) ) {
			continue;
		}
		$out[] = esc_url_raw( $clean );
	}
	return array_values( array_unique( array_filter( $out ) ) );
}

/**
 * A reference to the organization for other pages (seller of a product, provider of a service): @id and name —
 * the full description is on the home page.
 *
 * @return array
 */
function memorial_plugin_schema_org_ref() {
	return array(
		'@type' => MEMORIAL_PLUGIN_ORG_TYPE,
		'@id'   => memorial_plugin_schema_id( 0 ),
		'name'  => get_bloginfo( 'name' ),
	);
}

/**
 * The full description of the organization (JSON-LD, Google): the main office with the branches in department.
 *
 * @return array
 */
function memorial_plugin_schema_org() {
	$branches = memorial_plugin_schema_branches();
	$main     = $branches[0] ?? null;
	$data     = array(
		'@context'       => 'https://schema.org',
		'@type'          => MEMORIAL_PLUGIN_ORG_TYPE,
		'@id'            => memorial_plugin_schema_id( 0 ),
		'additionalType' => 'https://www.productontology.org/id/Funeral_home',
		'name'           => get_bloginfo( 'name' ),
		'url'            => home_url( '/' ),
	);
	$one      = static fn( $v ) => is_array( $v ) && count( $v ) === 1 ? $v[0] : $v;
	$place    = static function ( $b ) use ( $one ) {
		$p = array();
		if ( $b['phones'] ) {
			$p['telephone'] = $one( $b['phones'] );
		}
		$p['address'] = $b['address'];
		if ( $b['geo'] ) {
			$p['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $b['geo'][0],
				'longitude' => $b['geo'][1],
			);
			$p['hasMap'] = 'https://yandex.ru/maps/?pt=' . $b['geo'][1] . ',' . $b['geo'][0] . '&z=17';
		}
		if ( $b['hours'] ) {
			$p['openingHoursSpecification'] = array( memorial_plugin_schema_hours_spec( $b['hours'] ) );
		}
		return $p;
	};
	if ( $main ) {
		$data += $place( $main );
	}
	$emails = memorial_plugin_emails();
	if ( $emails ) {
		$data['email'] = $one( $emails );
	}
	$logo = memorial_plugin_schema_logo();
	if ( $logo ) {
		$data['logo']  = $logo;
		$data['image'] = $logo;
	}
	$legal = memorial_plugin_contacts()['legal'] ?? array();
	if ( trim( $legal['name'] ?? '' ) ) {
		$data['legalName'] = trim( $legal['name'] );
	}
	$inn = preg_replace( '/\D/', '', $legal['inn'] ?? '' );
	if ( $inn ) {
		$data['taxID'] = $inn;
	}
	$data['areaServed'] = 'Белгород';
	if ( function_exists( 'memorial_plugin_payment_items' ) && memorial_plugin_payment_items() ) {
		$data['paymentAccepted'] = implode( ', ', array_column( memorial_plugin_payment_items(), 0 ) );
	}
	// «Звонки круглосуточно»: телефон для связи в любое время.
	if ( memorial_plugin_night() && $main && $main['phones'] ) {
		$data['contactPoint'] = array(
			'@type'          => 'ContactPoint',
			'telephone'      => $main['phones'][0],
			'contactType'    => 'customer service',
			'areaServed'     => 'RU',
			'hoursAvailable' => array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
				'opens'     => '00:00',
				'closes'    => '23:59',
			),
		);
	}
	$same = array_merge( $main['profiles'] ?? array(), memorial_plugin_schema_same_as( $branches ) );
	if ( $same ) {
		$data['sameAs'] = array_values( array_unique( $same ) );
	}
	$deps = array();
	foreach ( array_slice( $branches, 1 ) as $b ) {
		$d = array(
			'@type' => MEMORIAL_PLUGIN_ORG_TYPE,
			'@id'   => $b['id'],
			'name'  => $b['name'],
		) + $place( $b );
		if ( $b['profiles'] ) {
			$d['sameAs'] = $b['profiles'];
		}
		$deps[] = $d;
	}
	if ( $deps ) {
		$data['department'] = $deps;
	}
	return apply_filters( 'memorial_plugin_schema_org', $data );
}

add_action(
	'wp_head',
	static function () {
		if ( is_front_page() && memorial_plugin_addrs() ) {
			memorial_plugin_ld( memorial_plugin_schema_org() );
		}
	},
	5
);

/**
 * Microdata of a branch for Yandex: attributes of the element that shows the address.
 * Only the first place that shows the addresses on a page gets them (the “Contacts” cards, else the footer):
 * the same organization is not marked twice on a page.
 *
 * @param int $i Index of the address.
 * @return string Attributes (escaped), '' — no microdata here.
 */
function memorial_plugin_org_micro_attrs( $i ) {
	return ! empty( $GLOBALS['memorial_plugin_org_micro_open'] )
		? ' itemscope itemtype="https://schema.org/' . MEMORIAL_PLUGIN_ORG_TYPE . '" itemid="' . esc_attr( memorial_plugin_schema_id( $i ) ) . '"'
		: '';
}

/**
 * Starts / ends a place that shows the addresses with microdata (call around the loop of the addresses).
 *
 * @param bool $open True — before the loop, false — after it.
 * @return bool Whether this place gets the microdata.
 */
function memorial_plugin_org_micro_place( $open ) {
	if ( $open ) {
		if ( ! empty( $GLOBALS['memorial_plugin_org_micro'] ) ) {
			return false;
		}
		$GLOBALS['memorial_plugin_org_micro']      = true;
		$GLOBALS['memorial_plugin_org_micro_open'] = true;
		return true;
	}
	$GLOBALS['memorial_plugin_org_micro_open'] = false;
	return true;
}

/**
 * Hidden fields of the branch microdata (the visible text of the address stays as it is):
 * name, address, phone, opening hours, coordinates.
 *
 * @param int $i Index of the address.
 * @return string HTML.
 */
function memorial_plugin_org_micro_fields( $i ) {
	$b = memorial_plugin_schema_branches()[ $i ] ?? null;
	if ( ! $b || empty( $GLOBALS['memorial_plugin_org_micro_open'] ) ) {
		return '';
	}
	$meta = static fn( $prop, $v ) => '<meta itemprop="' . esc_attr( $prop ) . '" content="' . esc_attr( (string) $v ) . '">';
	$h    = $meta( 'name', $b['name'] ) . '<link itemprop="url" href="' . esc_url( home_url( '/' ) ) . '">';
	$h   .= '<span itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">';
	foreach ( array( 'streetAddress', 'postalCode', 'addressLocality', 'addressCountry' ) as $k ) {
		if ( ! empty( $b['address'][ $k ] ) ) {
			$h .= $meta( $k, $b['address'][ $k ] );
		}
	}
	$h .= '</span>';
	foreach ( $b['phones'] as $p ) {
		$h .= $meta( 'telephone', $p );
	}
	if ( $b['hours'] ) {
		$h .= $meta( 'openingHours', $b['hours'] );
	}
	if ( $b['geo'] ) {
		$h .= '<span itemprop="geo" itemscope itemtype="https://schema.org/GeoCoordinates">' . $meta( 'latitude', $b['geo'][0] ) . $meta( 'longitude', $b['geo'][1] ) . '</span>';
	}
	return $h;
}

/*
 * OpenGraph: префиксы og: и article: объявлены у <html> — SEOPress выводит article:published_time, а без
 * объявления валидатор Яндекса пишет «префикс article неизвестен».
 */
add_filter(
	'language_attributes',
	static function ( $out, $doctype = 'html' ) {
		if ( is_admin() || 'html' !== $doctype || str_contains( (string) $out, 'prefix=' ) ) {
			return $out;
		}
		return $out . ' prefix="og: https://ogp.me/ns# article: https://ogp.me/ns/article#"';
	},
	10,
	2
);

/**
 * Картинка страницы для соцсетей, когда у страницы нет своей (ни главного фото, ни картинки в настройках
 * SEOPress): первое фото из текста страницы — вместо общей картинки SEOPress. '' — оставить как есть.
 *
 * @return string
 */
function memorial_plugin_social_image() {
	if ( ! is_singular() ) {
		return '';
	}
	$id = get_queried_object_id();
	if ( get_post_meta( $id, '_seopress_social_fb_img', true ) || has_post_thumbnail( $id ) || ! function_exists( 'memorial_plugin_page_image' ) ) {
		return '';
	}
	$img = memorial_plugin_page_image( $id );
	return $img && ! preg_match( '/\.svg(\?|$)/i', $img ) ? $img : '';
}

add_filter(
	'seopress_social_og_thumb',
	static function ( $html ) {
		$img = memorial_plugin_social_image();
		if ( '' === $img ) {
			return $html;
		}
		$alt = wp_strip_all_tags( get_the_title( get_queried_object_id() ) );
		$out = '<meta property="og:image" content="' . esc_url( $img ) . '" />' . "\n";
		if ( str_starts_with( $img, 'https://' ) ) {
			$out .= '<meta property="og:image:secure_url" content="' . esc_url( $img ) . '" />' . "\n";
		}
		return $out . '<meta property="og:image:alt" content="' . esc_attr( $alt ) . '" />' . "\n";
	}
);
add_filter(
	'seopress_social_twitter_card_thumb',
	static function ( $html ) {
		$img = memorial_plugin_social_image();
		return '' === $img ? $html : '<meta name="twitter:image" content="' . esc_url( $img ) . '" />' . "\n";
	}
);

/*
 * SEOPress: своё описание организации (Knowledge Graph) не выводит — организация описана здесь, с тем же @id
 * она была второй с другим типом. WebSite — с названием сайта и ссылкой на организацию.
 */
add_filter( 'seopress_get_json_data_organization', static fn( $json ) => array() );
add_filter(
	'seopress_schemas_website',
	static function ( $json ) {
		if ( ! is_array( $json ) ) {
			return $json;
		}
		$json['name'] = get_bloginfo( 'name' );
		unset( $json['alternateName'] );
		$json['publisher'] = array( '@id' => memorial_plugin_schema_id( 0 ) );
		return $json;
	}
);
