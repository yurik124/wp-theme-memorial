<?php
/**
 * «Мемориал → Цены → Отдельные цены»: цены для текста страниц и SEO-заголовков.
 *
 * Строка списка — своя цена или цена, которую сайт считает сам:
 *   — своя цена (число, диапазон или слова);
 *   — цена «от» или «до» раздела каталога, число моделей раздела (по опубликованным товарам);
 *   — цена услуги из «Мемориал → Услуги».
 * В текст цена вставляется кнопкой «Значение сайта» → «Цена из „Мемориал → Цены“» (по коду строки),
 * в SEO-плагин попадают только строки с галочкой «В SEO-плагин» — как переменные %%mem_<код>%%
 * (seo/seo-registry.php). Код можно не заполнять: при сохранении он делается из названия транслитом.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where a price comes from: key => label.
 *
 * @return array<string,string>
 */
function memorial_plugin_values_kinds() {
	return array(
		'custom'    => __( 'Own price — typed below', 'memorial-plugin' ),
		'cat-min'   => __( 'Lowest price of a catalog section (“from …”)', 'memorial-plugin' ),
		'cat-max'   => __( 'Highest price of a catalog section (“up to …”)', 'memorial-plugin' ),
		'cat-count' => __( 'Number of models in a catalog section', 'memorial-plugin' ),
		'service'   => __( 'Price of a service (Memorial → Services)', 'memorial-plugin' ),
	);
}

/**
 * Catalog sections for a list: slug => name (sub-sections indented).
 *
 * @param bool $empty Add “not chosen” first.
 * @return array<string,string>
 */
function memorial_plugin_values_sections( $empty = true ) {
	$out = $empty ? array( '' => __( '— not chosen —', 'memorial-plugin' ) ) : array();
	if ( ! taxonomy_exists( 'mem_cat' ) ) {
		return $out;
	}
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
 * Services with a price: title => “title — price”.
 *
 * @param bool $empty Add “not chosen” first.
 * @return array<string,string>
 */
function memorial_plugin_values_services( $empty = true ) {
	$out = $empty ? array( '' => __( '— not chosen —', 'memorial-plugin' ) ) : array();
	if ( function_exists( 'memorial_plugin_services_items' ) ) {
		foreach ( memorial_plugin_services_items() as $it ) {
			if ( '' !== trim( (string) ( $it['price'] ?? '' ) ) ) {
				$out[ (string) $it['title'] ] = $it['title'] . ' — ' . wp_strip_all_tags( memorial_plugin_t( (string) $it['price'] ) );
			}
		}
	}
	return $out;
}

/**
 * Prices to choose from in the editor: code => “name — price now” (cremation, separate prices, packages).
 *
 * @return array<string,array{label:string,atts:array}>
 */
function memorial_plugin_values_price_choices() {
	$out = array(
		array(
			'label' => __( 'Cremation (total)', 'memorial-plugin' ) . ' — ' . memorial_plugin_price_value( 'kremaciya' ),
			'atts'  => array( 'id' => 'kremaciya' ),
		),
	);
	foreach ( memorial_plugin_values() as $code => $row ) {
		$out[] = array(
			'label' => ( '' !== trim( (string) ( $row['name'] ?? '' ) ) ? $row['name'] : $code ) . ' — ' . memorial_plugin_value_text( $row ),
			'atts'  => array( 'id' => (string) $code ),
		);
	}
	foreach ( memorial_plugin_packages() as $p ) {
		$out[] = array(
			/* translators: %s: funeral package name. */
			'label' => sprintf( __( 'Package “%s”', 'memorial-plugin' ), $p['name'] ) . ' — ' . memorial_plugin_price_value( '', $p['name'] ),
			'atts'  => array( 'paket' => (string) $p['name'] ),
		);
	}
	return $out;
}

/**
 * The separate prices: code => row (rows without a code are skipped).
 *
 * @return array<string,array>
 */
function memorial_plugin_values() {
	$out = array();
	foreach ( memorial_plugin_prices( 'other' )['items'] ?? array() as $row ) {
		$key = memorial_plugin_value_key( (string) ( $row['code'] ?? '' ) );
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
 * A code from a name: Russian letters in Latin (transliteration), the rest dropped.
 *
 * @param string $name Name.
 * @return string
 */
function memorial_plugin_value_code_from( $name ) {
	$map  = array(
		'а' => 'a',
		'б' => 'b',
		'в' => 'v',
		'г' => 'g',
		'д' => 'd',
		'е' => 'e',
		'ё' => 'e',
		'ж' => 'zh',
		'з' => 'z',
		'и' => 'i',
		'й' => 'y',
		'к' => 'k',
		'л' => 'l',
		'м' => 'm',
		'н' => 'n',
		'о' => 'o',
		'п' => 'p',
		'р' => 'r',
		'с' => 's',
		'т' => 't',
		'у' => 'u',
		'ф' => 'f',
		'х' => 'h',
		'ц' => 'ts',
		'ч' => 'ch',
		'ш' => 'sh',
		'щ' => 'sch',
		'ъ' => '',
		'ы' => 'y',
		'ь' => '',
		'э' => 'e',
		'ю' => 'yu',
		'я' => 'ya',
	);
	$name = strtr( mb_strtolower( trim( (string) $name ) ), $map );
	return substr( memorial_plugin_value_key( $name ), 0, 40 );
}

/**
 * Before the prices are saved: every separate price gets a code (from its name, unique).
 *
 * @param mixed $value New value of mem_prices.
 * @return mixed
 */
function memorial_plugin_values_fill_codes( $value ) {
	if ( ! is_array( $value ) || empty( $value['other']['items'] ) || ! is_array( $value['other']['items'] ) ) {
		return $value;
	}
	$used = array( 'kremaciya' => true );
	foreach ( $value['other']['items'] as $i => $row ) {
		$code = memorial_plugin_value_key( (string) ( $row['code'] ?? '' ) );
		if ( '' === $code ) {
			$base = memorial_plugin_value_code_from( (string) ( $row['name'] ?? '' ) );
			$base = '' !== $base ? $base : 'cena';
			$code = $base;
			for ( $n = 2; isset( $used[ $code ] ); $n++ ) {
				$code = $base . '_' . $n;
			}
		}
		$used[ $code ]                             = true;
		$value['other']['items'][ $i ]['code'] = $code;
	}
	return $value;
}
add_filter( 'pre_update_option_mem_prices', 'memorial_plugin_values_fill_codes' );

/**
 * Plain text of a separate price (not escaped).
 *
 * @param array $row Row of “Separate prices”.
 * @return string
 */
function memorial_plugin_value_text( $row ) {
	$bare = ! empty( $row['bare'] );
	$what = (string) ( $row['what'] ?? 'custom' );
	switch ( $what ) {
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
		case 'service':
			$text = memorial_plugin_value_service( (string) ( $row['service'] ?? '' ) );
			return $bare ? trim( (string) preg_replace( '/^(от|до|from|up to)\s+|\s*₽\s*$/u', '', $text ) ) : $text;
	}
	return function_exists( 'memorial_plugin_price_fmt' ) ? memorial_plugin_price_fmt( (string) ( $row['price'] ?? '' ) ) : trim( (string) ( $row['price'] ?? '' ) );
}

/**
 * Price of a service by its title (Memorial → Services).
 *
 * @param string $title Service title.
 * @return string
 */
function memorial_plugin_value_service( $title ) {
	if ( function_exists( 'memorial_plugin_services_items' ) ) {
		foreach ( memorial_plugin_services_items() as $it ) {
			if ( (string) $it['title'] === $title ) {
				return trim( wp_strip_all_tags( memorial_plugin_t( (string) ( $it['price'] ?? '' ) ) ) );
			}
		}
	}
	return '';
}

/**
 * Text of a separate price by its code.
 *
 * @param string $key Code.
 * @return string
 */
function memorial_plugin_value_by_key( $key ) {
	$row = memorial_plugin_values()[ memorial_plugin_value_key( $key ) ] ?? null;
	return $row ? memorial_plugin_value_text( $row ) : '';
}
