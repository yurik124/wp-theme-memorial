<?php
/**
 * «Мемориал → Значения для текста и SEO»: свои значения сайта, которые вставляются в текст страниц
 * (кнопка «Значение сайта» на панели абзаца) и в заголовки и описания SEO-плагина.
 *
 * Значение — строка списка: название, код, что считать и, если нужно, раздел каталога или цена:
 *   — своё значение (любой текст, например «24 часа» или «от 3 дней»);
 *   — цена «от» или «до» раздела каталога, число моделей раздела (считаются по товарам);
 *   — цена из «Мемориал → Цены» (кремация, отдельная цена, пакет похорон) или цена услуги;
 *   — годы работы, год основания, телефон, адрес.
 * Галочка «В SEO-плагин» — только отмеченные значения становятся переменными %%mem_<код>%% в SEOPress,
 * Yoast и Rank Math (seo/seo-registry.php), чтобы не засорять их список.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * What a value shows: key => label.
 *
 * @return array<string,string>
 */
function memorial_plugin_values_kinds() {
	return array(
		'custom'    => __( 'Own value (the text below)', 'memorial-plugin' ),
		'cat-min'   => __( 'Starting price of a catalog section (“from …”)', 'memorial-plugin' ),
		'cat-max'   => __( 'Highest price of a catalog section (“up to …”)', 'memorial-plugin' ),
		'cat-count' => __( 'Number of models in a catalog section', 'memorial-plugin' ),
		'price'     => __( 'Price from Memorial → Prices or of a service', 'memorial-plugin' ),
		'years'     => __( 'Years in business', 'memorial-plugin' ),
		'founded'   => __( 'Founding year', 'memorial-plugin' ),
		'phone'     => __( 'Main phone', 'memorial-plugin' ),
		'address'   => __( 'Main address', 'memorial-plugin' ),
	);
}

/**
 * Catalog sections for a list: slug => name (sub-sections indented).
 *
 * @param bool $empty Add “not chosen” first.
 * @return array<string,string>
 */
function memorial_plugin_values_sections( $empty = true ) {
	$out   = $empty ? array( '' => __( '— not chosen —', 'memorial-plugin' ) ) : array();
	$terms = get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return $out;
	}
	$walk = static function ( $parent, $depth ) use ( &$walk, &$out, $terms ) {
		foreach ( $terms as $t ) {
			if ( (int) $t->parent === (int) $parent ) {
				$out[ $t->slug ] = str_repeat( '— ', $depth ) . $t->name;
				$walk( $t->term_id, $depth + 1 );
			}
		}
	};
	$walk( 0, 0 );
	return $out;
}

/**
 * Prices to choose from: 'price:<code>', 'paket:<name>', 'svc:<title>' => “name — price now”.
 *
 * @param bool $empty Add “not chosen” first.
 * @return array<string,string>
 */
function memorial_plugin_values_prices( $empty = true ) {
	$out = $empty ? array( '' => __( '— not chosen —', 'memorial-plugin' ) ) : array();
	if ( function_exists( 'memorial_plugin_price_value' ) ) {
		$out['price:kremaciya'] = __( 'Cremation (total)', 'memorial-plugin' ) . ' — ' . memorial_plugin_price_value( 'kremaciya' );
		foreach ( memorial_plugin_prices( 'other' )['items'] ?? array() as $it ) {
			$code = trim( (string) ( $it['code'] ?? '' ) );
			if ( '' !== $code ) {
				$out[ 'price:' . $code ] = ( '' !== trim( (string) ( $it['name'] ?? '' ) ) ? $it['name'] : $code ) . ' — ' . memorial_plugin_price_value( $code );
			}
		}
		foreach ( memorial_plugin_packages() as $p ) {
			/* translators: %s: funeral package name. */
			$out[ 'paket:' . $p['name'] ] = sprintf( __( 'Package “%s”', 'memorial-plugin' ), $p['name'] ) . ' — ' . memorial_plugin_price_value( '', $p['name'] );
		}
	}
	if ( function_exists( 'memorial_plugin_services_items' ) ) {
		foreach ( memorial_plugin_services_items() as $it ) {
			if ( '' !== trim( (string) ( $it['price'] ?? '' ) ) ) {
				/* translators: %s: service name. */
				$out[ 'svc:' . $it['title'] ] = sprintf( __( 'Service “%s”', 'memorial-plugin' ), $it['title'] ) . ' — ' . memorial_plugin_t( $it['price'] );
			}
		}
	}
	return $out;
}

/**
 * Settings schema.
 *
 * @return array
 */
function memorial_plugin_values_schema() {
	return array(
		'items' => array(
			'type'     => 'repeater',
			'label'    => __( 'Values', 'memorial-plugin' ),
			'add'      => __( 'Add a value', 'memorial-plugin' ),
			'collapse' => 'label',
			'fields'   => array(
				'label'   => array(
					'type'  => 'text',
					'label' => __( 'Name (seen only in the admin)', 'memorial-plugin' ),
				),
				'key'     => array(
					'type'  => 'text',
					'label' => __( 'Code — Latin letters, digits and “_”; in the SEO plugin the value is %%mem_code%%', 'memorial-plugin' ),
				),
				'what'    => array(
					'type'    => 'select',
					'label'   => __( 'What to show', 'memorial-plugin' ),
					'options' => memorial_plugin_values_kinds(),
				),
				'section' => array(
					'type'    => 'select',
					'label'   => __( 'Catalog section — for the prices and the number of models of a section', 'memorial-plugin' ),
					'options' => memorial_plugin_values_sections(),
				),
				'price'   => array(
					'type'    => 'select',
					'label'   => __( 'Price — for “Price from Memorial → Prices or of a service”', 'memorial-plugin' ),
					'options' => memorial_plugin_values_prices(),
				),
				'text'    => array(
					'type'  => 'text',
					'label' => __( 'Own value — for “Own value”', 'memorial-plugin' ),
				),
				'bare'    => array(
					'type'  => 'checkbox',
					'label' => __( 'Number only — without “from”, “up to”, “models” and ₽', 'memorial-plugin' ),
				),
				'seo'     => array(
					'type'  => 'checkbox',
					'label' => __( 'In the SEO plugin — the variable %%mem_code%% for titles and descriptions (SEOPress, Yoast, Rank Math)', 'memorial-plugin' ),
				),
			),
		),
	);
}

/**
 * Defaults: the variables the SEO fields of the site used before this screen.
 *
 * @return array
 */
function memorial_plugin_values_defaults() {
	$row = static fn( $label, $key, $what, $section = '' ) => array(
		'label'   => $label,
		'key'     => $key,
		'what'    => $what,
		'section' => $section,
		'price'   => '',
		'text'    => '',
		'bare'    => 0,
		'seo'     => 1,
	);
	return array(
		'items' => array(
			$row( __( 'Main phone number', 'memorial-plugin' ), 'phone', 'phone' ),
			$row( __( 'Years in business', 'memorial-plugin' ), 'years', 'years' ),
			$row( __( 'Starting price: fences', 'memorial-plugin' ), 'cat_price_ograda', 'cat-min', 'modeli-ogradok-i-czeny' ),
		),
	);
}

/**
 * The values: code => row (rows without a code are skipped).
 *
 * @return array<string,array>
 */
function memorial_plugin_values() {
	$out = array();
	foreach ( memorial_plugin_settings( 'mem_values' )['items'] ?? array() as $row ) {
		$key = memorial_plugin_value_key( (string) ( $row['key'] ?? '' ) );
		if ( '' !== $key && ! isset( $out[ $key ] ) ) {
			$out[ $key ] = $row;
		}
	}
	return $out;
}

/**
 * A code as it is used: lower case Latin letters, digits and “_”.
 *
 * @param string $key Code.
 * @return string
 */
function memorial_plugin_value_key( $key ) {
	return trim( (string) preg_replace( '/[^a-z0-9_]+/', '_', strtolower( trim( $key ) ) ), '_' );
}

/**
 * Plain text of a value (not escaped).
 *
 * @param array $row Value row.
 * @return string
 */
function memorial_plugin_value_text( $row ) {
	$bare = ! empty( $row['bare'] );
	$what = (string) ( $row['what'] ?? 'custom' );
	switch ( $what ) {
		case 'custom':
			return trim( (string) ( $row['text'] ?? '' ) );
		case 'cat-min':
		case 'cat-max':
		case 'cat-count':
			$term = get_term_by( 'slug', (string) ( $row['section'] ?? '' ), 'mem_cat' );
			if ( ! $term || ! function_exists( 'memorial_plugin_cat_stats' ) ) {
				return '';
			}
			[$n, $min, $max] = memorial_plugin_cat_stats( $term->term_id );
			if ( 'cat-count' === $what ) {
				return $bare ? (string) $n : $n . "\u{00A0}" . _n( 'model', 'models', $n, 'memorial-plugin' );
			}
			$v = 'cat-max' === $what ? $max : $min;
			if ( ! $v ) {
				return $bare ? '' : __( 'on request', 'memorial-plugin' );
			}
			if ( $bare ) {
				return number_format( $v, 0, '', "\u{202F}" );
			}
			/* translators: %s: price */
			return sprintf( 'cat-max' === $what ? __( 'up to %s', 'memorial-plugin' ) : __( 'from %s', 'memorial-plugin' ), memorial_plugin_rub( $v ) );
		case 'price':
			$text = memorial_plugin_value_price( (string) ( $row['price'] ?? '' ) );
			return $bare ? trim( (string) preg_replace( '/^(от|до|from|up to)\s+|\s*₽\s*$/u', '', $text ) ) : $text;
		case 'years':
			$years = (int) memorial_plugin_years();
			return $years ? (string) $years : '';
		case 'founded':
			$year = (int) ( memorial_plugin_contacts()['founded'] ?? 0 );
			return $year ? (string) $year : '';
		case 'phone':
			return (string) memorial_plugin_opt( 'phone' );
		case 'address':
			$addrs = memorial_plugin_addrs();
			return $addrs ? (string) $addrs[0]['addr'] : '';
	}
	return '';
}

/**
 * A price chosen in the list memorial_plugin_values_prices().
 *
 * @param string $ref 'price:<code>', 'paket:<name>' or 'svc:<title>'.
 * @return string
 */
function memorial_plugin_value_price( $ref ) {
	[$kind, $name] = array_pad( explode( ':', $ref, 2 ), 2, '' );
	if ( 'price' === $kind && function_exists( 'memorial_plugin_price_value' ) ) {
		return memorial_plugin_price_value( $name );
	}
	if ( 'paket' === $kind && function_exists( 'memorial_plugin_price_value' ) ) {
		return memorial_plugin_price_value( '', $name );
	}
	if ( 'svc' === $kind && function_exists( 'memorial_plugin_services_items' ) ) {
		foreach ( memorial_plugin_services_items() as $it ) {
			if ( (string) $it['title'] === $name ) {
				return trim( wp_strip_all_tags( memorial_plugin_t( (string) ( $it['price'] ?? '' ) ) ) );
			}
		}
	}
	return '';
}

/**
 * Text of a value by its code.
 *
 * @param string $key Code.
 * @return string
 */
function memorial_plugin_value_by_key( $key ) {
	$row = memorial_plugin_values()[ memorial_plugin_value_key( $key ) ] ?? null;
	return $row ? memorial_plugin_value_text( $row ) : '';
}

/* The settings page «Memorial → Values for texts and SEO». */
add_filter(
	'memorial_plugin_admin_pages',
	function ( $pages ) {
		$now = array();
		foreach ( memorial_plugin_values() as $key => $row ) {
			$now[] = ( '' !== trim( (string) $row['label'] ) ? $row['label'] : $key ) . ' — ' . memorial_plugin_value_text( $row ) . ( ! empty( $row['seo'] ) ? ' (' . memorial_plugin_seo_var( $key ) . ')' : '' );
		}
		$pages['mem_values'] = array(
			'slug'   => 'mem-values',
			'title'  => __( 'Values for texts and SEO', 'memorial-plugin' ),
			'schema' => 'memorial_plugin_values_schema',
			'intro'  => '<p>' . esc_html__( 'Values of the site that change by themselves: the starting or highest price of a catalog section, the number of models, a price from Memorial → Prices or of a service, or your own text. In the text of a page a value is put with the “Site value” button of the paragraph toolbar (“My values”). Only the values marked “In the SEO plugin” are added to the SEO plugin as variables %%mem_code%%.', 'memorial-plugin' ) . '</p>'
				. ( $now ? '<p><b>' . esc_html__( 'Values now:', 'memorial-plugin' ) . '</b> ' . esc_html( implode( ' · ', $now ) ) . '</p>' : '' ),
		);
		return $pages;
	}
);
