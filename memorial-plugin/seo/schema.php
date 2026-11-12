<?php
/**
 * Разметка Schema.org «FuneralHome» на главной и в «Контактах»: название, телефоны, адреса, часы, ИНН.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schema.org.
 *
 * @param array $s Section data.
 */
function memorial_plugin_schema_hours( $s ) {
	if ( ! preg_match( '/(\d{1,2})[:.](\d{2})\D+(\d{1,2})[:.](\d{2})/u', $s, $m ) ) {
		return '';
	}
	$t = sprintf( '%02d:%s-%02d:%s', $m[1], $m[2], $m[3], $m[4] );
	if ( preg_match( '/ежедневно|без выходных/iu', $s ) ) {
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

add_action(
	'wp_head',
	function () {
		if ( ! is_front_page() && ! is_page_template( 'page-kontakty.php' ) && ! is_page( 'kontakty' ) ) {
			return;
		}
		$addrs = memorial_plugin_addrs();
		$data  = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'FuneralHome',
			'name'         => get_bloginfo( 'name' ),
			'url'          => home_url( '/' ),
			'telephone'    => array_map( fn( $p ) => memorial_plugin_tel_of( $p['num'] ), memorial_plugin_phones() ),
			'email'        => memorial_plugin_emails(),
			'address'      => array_map(
				function ( $a ) {
					$zip  = preg_match( '/\b(\d{6})\b/', $a['addr'], $m ) ? $m[1] : '';
					$city = preg_match( '/(?:г\.|город)\s*([^,]+)/u', $a['addr'], $m ) ? trim( $m[1] ) : 'Белгород';
					return array_filter(
						array(
							'@type'           => 'PostalAddress',
							'streetAddress'   => memorial_plugin_short_addr( $a['addr'] ),
							'addressLocality' => $city,
							'postalCode'      => $zip,
							'addressCountry'  => 'RU',
						)
					);
				},
				$addrs
			),
			'taxID'        => preg_replace( '/\D/', '', memorial_plugin_contacts()['legal']['inn'] ?? '' ),
			'areaServed'   => 'Белгород',
			'openingHours' => array_values( array_unique( array_filter( array_map( fn( $a ) => memorial_plugin_schema_hours( $a['hours'] ), $addrs ) ) ) ),
		);
		$data  = array(
			'@context' => $data['@context'],
			'@type'    => $data['@type'],
			'@id'      => home_url( '/#organization' ),
		) + $data;
		// логотип / фото.
		$logo = get_option( 'site_logo' ) ? wp_get_attachment_image_url( (int) get_option( 'site_logo' ), 'full' ) : get_site_icon_url( 512 );
		if ( $logo ) {
			$data['logo']  = $logo;
			$data['image'] = $logo; }
		// юридическое название.
		$ln = trim( memorial_plugin_contacts()['legal']['name'] ?? '' );
		if ( $ln ) {
			$data['legalName'] = $ln;
		}
		// профили на картах и площадках отзывов (Яндекс Карты, 2ГИС, Zoon…) — поисковик связывает сайт с карточкой компании.
		$same = array();
		foreach ( (array) ( get_option( 'mem_reviews' )['sources'] ?? array() ) as $src ) {
			$u = trim( (string) ( $src['link'] ?? '' ) );
			if ( preg_match( '~^https?://~', $u ) ) {
				$same[] = $u;
			}
		}
		$org = memorial_plugin_yandex_org();
		if ( $org && ! array_filter( $same, fn( $u ) => false !== strpos( $u, $org ) ) ) {
			$same[] = 'https://yandex.ru/maps/org/' . $org . '/';
		}
		foreach ( memorial_plugin_messengers() as $k => $u ) {
			if ( 'telegram' === $k ) {
				$same[] = $u;
			}
		}
		$same = array_values( array_unique( array_map( 'esc_url_raw', $same ) ) );
		if ( $same ) {
			$data['sameAs'] = $same;
		}
		// одно значение — без массива.
		if ( empty( $data['taxID'] ) ) {
			unset( $data['taxID'] );
		}
		foreach ( array( 'telephone', 'email', 'address', 'openingHours' ) as $k ) {
			if ( count( $data[ $k ] ) === 1 ) {
				$data[ $k ] = $data[ $k ][0];
			}
			if ( ! $data[ $k ] ) {
				unset( $data[ $k ] );
			}
		}
		memorial_plugin_ld( $data );
	},
	5
);
