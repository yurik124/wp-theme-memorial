<?php
/**
 * Типы товаров = категории верхнего уровня каталога («Гробы», «Венки», «Кресты», «Памятники»…).
 * У каждого типа — свой набор характеристик (текст, число с единицей, выбор из списка) и свои категории (подкатегории).
 * Значения характеристик товара хранятся строками «Название: значение» в _mem_specs — их и показывает карточка на сайте.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Тип товара: верхняя категория, к которой относится категория $term_id
 *
 * @param int $term_id Term ID.
 */
function memorial_plugin_type_root( $term_id ) {
	$anc = get_ancestors( (int) $term_id, 'mem_cat', 'taxonomy' );
	return $anc ? (int) end( $anc ) : (int) $term_id;
}

/** Тип товара по товару
 *
 * @param int $post_id Post ID.
 */
function memorial_plugin_product_type( $post_id ) {
	$terms = wp_get_object_terms( $post_id, 'mem_cat', array( 'fields' => 'ids' ) );
	return ( ! is_wp_error( $terms ) && $terms ) ? memorial_plugin_type_root( $terms[0] ) : 0;
}

/**
 * Характеристики типа: [['label' => 'Высота', 'type' => 'number', 'unit' => 'см', 'options' => []], …]
 * Если ещё не настроены — собираются из уже заполненных характеристик товаров этого типа (как текстовые).
 *
 * @param int  $type_id Type ID.
 * @param bool $guess Guess.
 */
function memorial_plugin_type_fields( $type_id, $guess = true ) {
	$raw = get_term_meta( $type_id, 'mem_fields', true );
	$f   = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( is_array( $f ) && $f ) {
		return array_values( array_filter( array_map( 'memorial_plugin_clean_field', $f ) ) );
	}
	if ( ! $guess ) {
		return array();
	}
	$labels = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy'         => 'mem_cat',
					'terms'            => $type_id,
					'include_children' => true,
				),
			),
		)
	) as $pid ) {
		foreach ( memorial_plugin_product_specs( $pid ) as [$k] ) {
			if ( '' !== $k ) {
				$labels[ $k ] = true;
			}
		}
	}
	return array_map(
		fn( $l ) => array(
			'label'   => $l,
			'type'    => 'text',
			'unit'    => '',
			'options' => array(),
			'default' => '',
		),
		array_keys( $labels )
	);
}

/**
 * Clean field.
 *
 * @param mixed $f Field.
 */
function memorial_plugin_clean_field( $f ) {
	$label = trim( sanitize_text_field( $f['label'] ?? '' ) );
	if ( '' === $label ) {
		return null;
	}
	$type = in_array( $f['type'] ?? '', array( 'text', 'number', 'list' ), true ) ? $f['type'] : 'text';
	$opts = $f['options'] ?? array();
	if ( is_string( $opts ) ) {
		$opts = explode( ',', $opts );
	}
	$opts = array_values( array_unique( array_filter( array_map( fn( $o ) => trim( sanitize_text_field( $o ) ), (array) $opts ), 'strlen' ) ) );
	$def  = trim( sanitize_text_field( $f['default'] ?? '' ) );
	if ( 'number' === $type ) {
		$def = preg_replace( '/[^\d.,-]/', '', $def );
	}
	if ( 'list' === $type && '' !== $def && ! in_array( $def, $opts, true ) ) {
		$def = '';
	}
	return array(
		'label'   => $label,
		'type'    => $type,
		'unit'    => 'number' === $type ? trim( sanitize_text_field( $f['unit'] ?? '' ) ) : '',
		'options' => 'list' === $type ? $opts : array(),
		'default' => $def,
	);
}

/** Значения характеристик товара по названиям: ['Высота' => '120 см', …]
 *
 * @param int $post_id Post ID.
 */
function memorial_plugin_product_spec_map( $post_id ) {
	$m = array();
	foreach ( memorial_plugin_product_specs( $post_id ) as [$k, $v] ) {
		$m[ $k ] = $v;
	}
	return $m;
}

/** Записать одну характеристику; порядок строк — как в настройках типа, остальные строки сохраняются в конце
 *
 * @param int    $post_id Post ID.
 * @param string $label Label.
 * @param mixed  $value Value.
 */
function memorial_plugin_set_spec( $post_id, $label, $value ) {
	$map   = memorial_plugin_product_spec_map( $post_id );
	$value = trim( sanitize_text_field( $value ) );
	if ( '' === $value ) {
		unset( $map[ $label ] );
	} else {
		$map[ $label ] = $value;
	}
	$order = array_column( memorial_plugin_type_fields( memorial_plugin_product_type( $post_id ), false ), 'label' );
	$lines = array();
	foreach ( $order as $l ) {
		if ( isset( $map[ $l ] ) ) {
			$lines[] = $l . ': ' . $map[ $l ];
			unset( $map[ $l ] ); }
	}
	foreach ( $map as $k => $v ) {
		$lines[] = ( '' !== $k ? $k . ': ' : '' ) . $v;
	}
	$lines ? update_post_meta( $post_id, '_mem_specs', implode( "\n", $lines ) ) : delete_post_meta( $post_id, '_mem_specs' );
}

/* Удалили тип — удаляем и его настройки (WordPress делает это сам вместе с term meta) */

/** Значения по умолчанию для новых товаров типа: цена, «от», единица
 *
 * @param int $type_id Type ID.
 */
function memorial_plugin_type_defaults( $type_id ) {
	$d = get_term_meta( $type_id, 'mem_defaults', true );
	$d = is_array( $d ) ? $d : array();
	return array(
		'price' => (int) ( $d['price'] ?? 0 ) ? (int) $d['price'] : '',
		'from'  => ! empty( $d['from'] ),
		'unit'  => (string) ( $d['unit'] ?? '' ),
		'cats'  => array_values( array_filter( array_map( 'absint', (array) ( $d['cats'] ?? array() ) ), static fn( $c ) => $c && term_exists( $c, 'mem_cat' ) ) ),
	);
}

/**
 * Ways to order the products of a type or a category on the site.
 *
 * @return array<string,string> Key => label.
 */
function memorial_plugin_sort_options() {
	return array(
		'new'        => __( 'Newest first', 'memorial-plugin' ),
		'old'        => __( 'Oldest first', 'memorial-plugin' ),
		'price_asc'  => __( 'Cheapest first', 'memorial-plugin' ),
		'price_desc' => __( 'Most expensive first', 'memorial-plugin' ),
	);
}

/**
 * Order of the products of a term: its own setting, otherwise of the nearest ancestor that has one.
 * Nothing set anywhere (terms made before this setting) — '' : the former order, as arranged by hand.
 *
 * @param int $term_id Term ID.
 * @return string '' or a key of memorial_plugin_sort_options().
 */
function memorial_plugin_term_sort( $term_id ) {
	$guard = 0;
	while ( $term_id && $guard++ < 10 ) {
		$s = (string) get_term_meta( $term_id, 'mem_sort', true );
		if ( isset( memorial_plugin_sort_options()[ $s ] ) ) {
			return $s;
		}
		$t       = get_term( $term_id, 'mem_cat' );
		$term_id = $t && ! is_wp_error( $t ) ? (int) $t->parent : 0;
	}
	return '';
}

/**
 * Products in the order of the setting.
 *
 * @param WP_Post[] $posts Products (in the former order).
 * @param string    $sort  '' or a key of memorial_plugin_sort_options().
 * @return WP_Post[]
 */
function memorial_plugin_sort_products( array $posts, $sort ) {
	if ( '' === $sort || count( $posts ) < 2 ) {
		return $posts;
	}
	$price = static fn( $p ) => (int) get_post_meta( $p->ID, '_mem_price', true );
	usort(
		$posts,
		static function ( $a, $b ) use ( $sort, $price ) {
			switch ( $sort ) {
				case 'old':
					return strcmp( $a->post_date, $b->post_date ) ? strcmp( $a->post_date, $b->post_date ) : $a->ID <=> $b->ID;
				case 'price_asc':
				case 'price_desc':
					$pa = $price( $a );
					$pb = $price( $b );
					// “Price on request” (0) always goes last.
					if ( ! $pa || ! $pb ) {
						return $pa === $pb ? $b->ID <=> $a->ID : ( $pa ? -1 : 1 );
					}
					if ( $pa !== $pb ) {
						return 'price_asc' === $sort ? $pa <=> $pb : $pb <=> $pa;
					}
					return $b->ID <=> $a->ID;
				default:
					return strcmp( $b->post_date, $a->post_date ) ? strcmp( $b->post_date, $a->post_date ) : $b->ID <=> $a->ID;
			}
		}
	);
	return $posts;
}

/**
 * A new type or category shows its newest products first.
 *
 * @param int $term_id Term ID.
 */
function memorial_plugin_new_term_sort( $term_id ) {
	if ( '' === (string) get_term_meta( $term_id, 'mem_sort', true ) ) {
		update_term_meta( $term_id, 'mem_sort', 'new' );
	}
}
add_action( 'created_mem_cat', 'memorial_plugin_new_term_sort' );

/** Применить значения по умолчанию к новому товару
 *
 * @param int $post_id Post ID.
 * @param int $type_id Type ID.
 */
function memorial_plugin_apply_type_defaults( $post_id, $type_id ) {
	$d = memorial_plugin_type_defaults( $type_id );
	if ( $d['price'] ) {
		update_post_meta( $post_id, '_mem_price', $d['price'] );
	}
	if ( $d['from'] ) {
		update_post_meta( $post_id, '_mem_price_from', '1' );
	}
	if ( '' !== $d['unit'] ) {
		update_post_meta( $post_id, '_mem_unit', $d['unit'] );
	}
	// Categories of a new product: with them the product lies only in them, not right in the type.
	$cats = array_values( array_filter( $d['cats'], static fn( $c ) => $c !== (int) $type_id && memorial_plugin_type_root( $c ) === (int) $type_id ) );
	if ( $cats ) {
		wp_set_object_terms( $post_id, $cats, 'mem_cat' );
	}
	$lines = array();
	foreach ( memorial_plugin_type_fields( $type_id, false ) as $f ) {
		if ( ( $f['default'] ?? '' ) === '' ) {
			continue;
		}
		$lines[] = $f['label'] . ': ' . $f['default'] . ( 'number' === $f['type'] && $f['unit'] ? ' ' . $f['unit'] : '' );
	}
	if ( $lines ) {
		update_post_meta( $post_id, '_mem_specs', implode( "\n", $lines ) );
	}
}
