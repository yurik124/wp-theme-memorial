<?php
/**
 * Контакты, шаг 3 ТЗ «Блоки и доработки» (24.11.2026):
 *  - у адреса — что здесь (магазин, траурный зал), округ, остановка, ориентиры, парковка; строка под адресом
 *    в «Карточках контактов» и описание филиала в микроданных;
 *  - «География работы» (memorial-plugin/service-area) и areaServed организации;
 *  - разовый перенос memorial_plugin_contacts_3: часы и ориентиры адресов из карточки компании.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parts of the grey line under an address: «Восточный округ · ост. УТЭП · у БелГУ · парковка».
 *
 * @param array $a Address of «Memorial → Contacts».
 * @return string[]
 */
function memorial_plugin_addr_notes( $a ) {
	$out = array();
	if ( '' !== trim( (string) ( $a['district'] ?? '' ) ) ) {
		$out[] = trim( (string) $a['district'] );
	}
	if ( '' !== trim( (string) ( $a['stop'] ?? '' ) ) ) {
		$stop  = trim( (string) $a['stop'] );
		$out[] = preg_match( '/^ост/iu', $stop ) ? $stop : 'ост. ' . $stop;
	}
	foreach ( array_filter( array_map( 'trim', explode( ',', (string) ( $a['landmarks'] ?? '' ) ) ) ) as $l ) {
		$out[] = $l;
	}
	if ( ! empty( $a['parking'] ) ) {
		$out[] = __( 'parking', 'memorial-plugin' );
	}
	return $out;
}

/**
 * What is at an address, for people: «Магазин и траурный зал».
 *
 * @param array $a Address.
 * @return string
 */
function memorial_plugin_addr_kind( $a ) {
	if ( ! empty( $a['shop'] ) && ! empty( $a['hall'] ) ) {
		return __( 'Shop and funeral hall', 'memorial-plugin' );
	}
	if ( ! empty( $a['hall'] ) ) {
		return __( 'Funeral hall', 'memorial-plugin' );
	}
	return ! empty( $a['shop'] ) ? __( 'Shop', 'memorial-plugin' ) : '';
}

/**
 * The “Contact cards” block: the attributes go to the part of the template.
 *
 * @param array $a Attributes: only (all | shop | hall), landmarks, route.
 * @return string
 */
function memorial_plugin_contacts_cards_render( $a = array() ) {
	$GLOBALS['memorial_plugin_cards_atts'] = (array) $a;
	$html                                  = memorial_plugin_home_part( 'contacts-cards' );
	unset( $GLOBALS['memorial_plugin_cards_atts'] );
	return $html;
}

/* ---------- География работы ---------- */

/**
 * Settings of the area: line, places, funeral, landscaping.
 *
 * @return array<string,string>
 */
function memorial_plugin_area() {
	return array_map( static fn( $v ) => trim( (string) $v ), (array) ( memorial_plugin_contacts()['area'] ?? array() ) );
}

/**
 * Suburbs and villages of the area.
 *
 * @return string[]
 */
function memorial_plugin_area_places() {
	return array_values( array_filter( array_map( 'trim', preg_split( '/[,;\n]+/u', memorial_plugin_area()['places'] ?? '' ) ) ) );
}

/**
 * The “Where we work” block: one or two lines with a map pin.
 *
 * @param array $a Attributes: context (funeral | landscaping).
 * @return string
 */
function memorial_plugin_service_area_render( $a = array() ) {
	$area    = memorial_plugin_area();
	$context = 'landscaping' === ( $a['context'] ?? '' ) ? 'landscaping' : 'funeral';
	$line    = $area['line'] ?? '';
	$places  = memorial_plugin_area_places();
	if ( $places ) {
		/* translators: %s: suburbs and villages, comma separated. */
		$line .= ( '' !== $line ? '. ' : '' ) . sprintf( __( 'Suburbs: %s', 'memorial-plugin' ), implode( ', ', $places ) );
	}
	$terms = $area[ $context ] ?? '';
	if ( '' === $line && '' === $terms ) {
		return '';
	}
	return '<div class="marea">'
		. '<span class="marea__ico" aria-hidden="true">' . memorial_plugin_icon( 'pin' ) . '</span>'
		. '<div class="marea__text">'
		. ( '' !== $line ? '<p class="marea__line">' . esc_html( rtrim( $line, '.' ) . '.' ) . '</p>' : '' )
		. ( '' !== $terms ? '<p class="marea__terms">' . esc_html( rtrim( $terms, '.' ) . '.' ) . '</p>' : '' )
		. '</div></div>';
}

/**
 * areaServed of the organization: the city, the region and the suburbs.
 *
 * @return array<int,array>
 */
function memorial_plugin_schema_area_served() {
	$out = array(
		array(
			'@type' => 'City',
			'name'  => 'Белгород',
		),
	);
	if ( preg_match( '/област/iu', memorial_plugin_area()['line'] ?? '' ) ) {
		$out[] = array(
			'@type' => 'AdministrativeArea',
			'name'  => 'Белгородская область',
		);
	}
	foreach ( memorial_plugin_area_places() as $p ) {
		$out[] = array(
			'@type' => 'Place',
			'name'  => $p,
		);
	}
	return $out;
}

/* ---------- Разовый перенос memorial_plugin_contacts_3 ---------- */

/**
 * The addresses of the company card (owner, 24.11.2026: the hours are those of the card): a word of the
 * address => fields. Hours are set always; the other fields only where they are empty.
 *
 * @return array<string,array>
 */
function memorial_plugin_contacts_card_3() {
	return array(
		'Студенческая' => array(
			'hours'     => 'Пн–Сб 8:30–17:00, вс — выходной',
			'shop'      => 1,
			'hall'      => 1,
			'district'  => 'Восточный округ',
			'stop'      => 'УТЭП',
			'landmarks' => 'у УТЭПа, у БелГУ',
		),
		'Щорса'        => array(
			'hours'     => 'Пн–Пт 9:00–18:00, сб–вс 9:00–17:00',
			'district'  => 'Западный округ',
			'stop'      => 'Костюкова',
			'landmarks' => 'Харьковская гора, Океан, район телевышки',
		),
	);
}

/**
 * The run. $dry = true — only what would change, nothing is written.
 *
 * @param bool $dry Dry run.
 * @return array Log: contacts (old option), changed => [address => [field => [old, new]]].
 */
function memorial_plugin_contacts_upgrade_3( $dry = false ) {
	$log   = array( 'time' => time() );
	$saved = get_option( 'mem_contacts' );
	// Never saved — the addresses are the defaults; the screen value is taken as it is shown.
	$value = is_array( $saved ) ? $saved : memorial_plugin_contacts();
	if ( empty( $value['addrs'] ) ) {
		$value['addrs'] = memorial_plugin_contacts()['addrs'];
	}
	foreach ( (array) $value['addrs'] as $i => $a ) {
		foreach ( memorial_plugin_contacts_card_3() as $word => $fields ) {
			if ( false === mb_strpos( (string) ( $a['addr'] ?? '' ), $word ) ) {
				continue;
			}
			foreach ( $fields as $k => $v ) {
				$old = $a[ $k ] ?? '';
				if ( 'hours' !== $k && '' !== (string) $old && 0 !== $old ) {
					continue;
				}
				if ( (string) $old === (string) $v ) {
					continue;
				}
				$log['changed'][ $a['addr'] ][ $k ] = array( $old, $v );
				$value['addrs'][ $i ][ $k ]         = $v;
			}
		}
	}
	// The number that is not the company's (stop list of the spec): only noted, the owner removes it.
	foreach ( (array) ( $value['phones'] ?? array() ) as $p ) {
		if ( preg_match( '/910\D*737\D*16\D*58/', (string) ( $p['num'] ?? '' ) ) ) {
			$log['foreign_phone'] = $p['num'];
		}
	}
	if ( ! empty( $log['changed'] ) ) {
		$log['contacts'] = $saved;
		if ( ! $dry ) {
			// As is, not through the sanitizer of the screen (RUKOVODSTVO 8.4).
			update_option( 'mem_contacts', $value );
		}
	}
	return $log;
}

add_action(
	'admin_init',
	static function () {
		if ( get_option( 'memorial_plugin_contacts_3' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'memorial_plugin_contacts_3', time(), false );
		update_option( 'memorial_plugin_contacts_3_log', memorial_plugin_contacts_upgrade_3(), false );
		if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
	},
	8
);
