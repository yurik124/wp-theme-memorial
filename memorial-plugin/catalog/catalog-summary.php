<?php
/**
 * Цифры каталога в тексте страницы — считаются сами:
 *   [mem_cat_count]              → «96 моделей»
 *   [mem_cat_price]              → «от 35 000 ₽»      [mem_cat_price cena="макс"] → «до 69 000 ₽»
 *   [mem_cat_list]               → список разделов: «С крестом — 21, от 35 000 ₽» со ссылками на раздел
 * Категория — как у [mem_catalog]: своя страница; или cat="номер / адрес / название раздела".
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Категория по номеру, адресу или названию; без cat — категория этой страницы
 *
 * @param mixed $val Val.
 */
function memorial_plugin_summary_cat( $val ) {
	$val = trim( (string) $val );
	if ( '' === $val && ! empty( $GLOBALS['memorial_plugin_cat_ctx'] ) ) {
		$val = (string) (int) $GLOBALS['memorial_plugin_cat_ctx']; // описание раздела каталога.
	}
	$t = memorial_plugin_resolve_cat( $val, get_the_ID() );
	if ( ! $t && '' !== $val ) {
		$t = get_term_by( 'name', $val, 'mem_cat' );
		$t = $t ? $t : null;
	}
	$pt = ! $t && '' === $val && function_exists( 'memorial_plugin_page_term' ) ? memorial_plugin_page_term( (int) get_the_ID() ) : 0;
	if ( $pt ) {
		$t = get_term( $pt, 'mem_cat' );
	}
	return $t && ! is_wp_error( $t ) ? $t : null;
}

/** Опубликованные товары категории со всеми подкатегориями: [число, мин. цена, макс. цена]
 *
 * @param int $term_id Term ID.
 */
function memorial_plugin_cat_stats( $term_id ) {
	static $cache = array();
	if ( isset( $cache[ $term_id ] ) ) {
		return $cache[ $term_id ];
	}
	$ids               = get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy'         => 'mem_cat',
					'terms'            => $term_id,
					'include_children' => true,
				),
			),
		)
	);
	$prices            = array_filter( array_map( fn( $id ) => (int) get_post_meta( $id, '_mem_price', true ), $ids ) );
	$cache[ $term_id ] = array( count( $ids ), $prices ? min( $prices ) : 0, $prices ? max( $prices ) : 0 );
	return $cache[ $term_id ];
}

/** «да / нет» в шорткоде: нет, 0, no, false, off — выключено
 *
 * @param mixed $v Value.
 */
function memorial_plugin_sc_no( $v ) {
	return in_array( mb_strtolower( trim( (string) $v ) ), array( '0', 'нет', 'no', 'false', 'off' ), true ); }

/*
Понятные параметры: cena="макс", ot="нет", rub="нет", slovo="нет", ceny="нет".
	Старая запись (max="1", prefix="0", word="0", price="0") тоже работает.
 */
add_shortcode(
	'mem_cat_count',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'cat'   => '',
				'slovo' => 'да',
				'word'  => '1',
			),
			$atts,
			'mem_cat_count'
		);
		$t = memorial_plugin_summary_cat( $a['cat'] );
		if ( ! $t ) {
			return memorial_plugin_sc_missing( 'mem_cat_count', $a['cat'] );
		}
		[$n]  = memorial_plugin_cat_stats( $t->term_id );
		$word = ! memorial_plugin_sc_no( $a['slovo'] ) && ! memorial_plugin_sc_no( $a['word'] );
		return $n . ( $word ? "\u{00A0}" . _n( 'model', 'models', $n, 'memorial-plugin' ) : '' );
	}
);

add_shortcode(
	'mem_cat_price',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'cat'    => '',
				'cena'   => '',
				'ot'     => 'да',
				'rub'    => 'да',
				'max'    => '0',
				'prefix' => '1',
			),
			$atts,
			'mem_cat_price'
		);
		$t = memorial_plugin_summary_cat( $a['cat'] );
		if ( ! $t ) {
			return memorial_plugin_sc_missing( 'mem_cat_price', $a['cat'] );
		}
		[, $min, $max] = memorial_plugin_cat_stats( $t->term_id );
		$is_max        = preg_match( '/^(макс|max)/u', mb_strtolower( $a['cena'] ) ) || ! memorial_plugin_sc_no( $a['max'] );
		$v             = $is_max ? $max : $min;
		if ( ! $v ) {
			return __( 'on request', 'memorial-plugin' );
		}
		$txt  = memorial_plugin_sc_no( $a['rub'] ) ? number_format( $v, 0, '', "\u{202F}" ) : memorial_plugin_rub( $v );
		$word = ! memorial_plugin_sc_no( $a['ot'] ) && ! memorial_plugin_sc_no( $a['prefix'] );
		/* translators: %s: price */
		return $word ? sprintf( $is_max ? __( 'up to %s', 'memorial-plugin' ) : __( 'from %s', 'memorial-plugin' ), $txt ) : $txt;
	}
);

add_shortcode(
	'mem_cat_list',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'cat'   => '',
				'ceny'  => 'да',
				'price' => '1',
			),
			$atts,
			'mem_cat_list'
		);
		$t = memorial_plugin_summary_cat( $a['cat'] );
		if ( ! $t ) {
			return memorial_plugin_sc_missing( 'mem_cat_list', $a['cat'] );
		}
		$price = ! memorial_plugin_sc_no( $a['ceny'] ) && ! memorial_plugin_sc_no( $a['price'] );
		$items = array();
		foreach ( memorial_plugin_sorted_children( $t->term_id ) as $k ) {
			[$n, $min] = memorial_plugin_cat_stats( $k->term_id );
			if ( ! $n ) {
				continue;
			}
			$items[] = '<li><a href="#' . esc_attr( memorial_plugin_term_anchor( $k ) ) . '">' . esc_html( $k->name ) . '</a> — ' . $n
				. ( $price && $min ? __( ', from ', 'memorial-plugin' ) . esc_html( memorial_plugin_rub( $min ) ) : '' ) . '</li>';
		}
		return $items ? '<ul class="wp-block-list is-style-checks mem-cat-list">' . implode( '', $items ) . '</ul>' : '';
	}
);

/** Раздел не найден: посетителю — ничего, редактору — подсказка
 *
 * @param string $tag Tag.
 * @param mixed  $cat Cat.
 */
function memorial_plugin_sc_missing( $tag, $cat ) {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return '';
	}
	return ( '<mark title="' . esc_html__( 'Only you see this', 'memorial-plugin' ) . '">[' ) . $tag . ': ' . ( '' !== $cat ? __( 'section “', 'memorial-plugin' ) . esc_html( $cat ) . __( '” not found', 'memorial-plugin' ) : __( 'the page has no catalog section — add cat="Section name"', 'memorial-plugin' ) ) . ']</mark>';
}

/** Как сослаться на раздел в шорткоде: по названию, если оно не повторяется, иначе по адресу (slug), в крайнем случае по номеру
 *
 * @param int $term_id Term ID.
 */
function memorial_plugin_cat_ref( $term_id ) {
	$t = get_term( (int) $term_id, 'mem_cat' );
	if ( ! $t || is_wp_error( $t ) ) {
		return (string) $term_id;
	}
	$same = get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'name'       => $t->name,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( count( (array) $same ) === 1 && strpos( $t->name, '"' ) === false && strpos( $t->name, ']' ) === false ) {
		return $t->name;
	}
	return preg_match( '/^[a-z0-9-]+$/', $t->slug ) && ! ctype_digit( $t->slug ) ? $t->slug : (string) $t->term_id;
}

/** Переписывает [mem_cat_*] в понятный вид: cat="86" → cat="Венки", prefix="0" → ot="нет" и т. п.
 *
 * @param string $html Html.
 */
function memorial_plugin_sc_readable( $html ) {
	return preg_replace_callback(
		'/\[(mem_cat_(?:price|count|list))(\s[^\]]*)?\]/u',
		function ( $m ) {
			$at = shortcode_parse_atts( trim( $m[2] ?? '' ) );
			$at = is_array( $at ) ? $at : array();
			if ( ! is_array( $at ) ) {
				return $m[0];
			}
			$out = array();
			if ( isset( $at['cat'] ) && '' !== $at['cat'] ) {
				$out['cat'] = ctype_digit( (string) $at['cat'] ) ? memorial_plugin_cat_ref( (int) $at['cat'] ) : $at['cat'];
			}
			if ( 'mem_cat_price' === $m[1] ) {
				if ( ( isset( $at['max'] ) && ! memorial_plugin_sc_no( $at['max'] ) ) || preg_match( '/^(макс|max)/u', mb_strtolower( $at['cena'] ?? '' ) ) ) {
					$out['cena'] = 'макс';
				}
				if ( ( isset( $at['prefix'] ) && memorial_plugin_sc_no( $at['prefix'] ) ) || ( isset( $at['ot'] ) && memorial_plugin_sc_no( $at['ot'] ) ) ) {
					$out['ot'] = 'нет';
				}
				if ( isset( $at['rub'] ) && memorial_plugin_sc_no( $at['rub'] ) ) {
					$out['rub'] = 'нет';
				}
			} elseif ( 'mem_cat_count' === $m[1] ) {
				if ( ( isset( $at['word'] ) && memorial_plugin_sc_no( $at['word'] ) ) || ( isset( $at['slovo'] ) && memorial_plugin_sc_no( $at['slovo'] ) ) ) {
					$out['slovo'] = 'нет';
				}
			} elseif ( ( isset( $at['price'] ) && memorial_plugin_sc_no( $at['price'] ) ) || ( isset( $at['ceny'] ) && memorial_plugin_sc_no( $at['ceny'] ) ) ) {
					$out['ceny'] = 'нет';
			}
			$s = '[' . $m[1];
			foreach ( $out as $k => $v ) {
				$s .= ' ' . $k . '="' . $v . '"';
			}
			return $s . ']';
		},
		$html
	);
}

/* Разово: из описаний разделов убираем старые «N моделей, цена — от … ₽» — эту строку каталог пишет сам над описанием */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'mem_desc_cleaned' ) || ! current_user_can( 'manage_categories' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'mem_desc_cleaned', 1, false );
		$n = 0;
		foreach ( get_terms(
			array(
				'taxonomy'   => 'mem_cat',
				'hide_empty' => false,
			)
		) as $t ) {
			if ( is_wp_error( $t ) || trim( $t->description ) === '' ) {
				continue;
			}
			$d = preg_replace( '/\s*(?:В\s+(?:каталоге|разделе)\s+)?\d+\s+модел\w*[^.]*?(?:\d[\d\s\x{00A0}\x{202F}]*\s*₽)[^.]*\.?/u', '', $t->description );
			$d = trim( preg_replace( '/\s{2,}/u', ' ', $d ) );
			if ( trim( $t->description ) !== $d ) {
				wp_update_term( $t->term_id, 'mem_cat', array( 'description' => $d ) );
				$n++; }
		}
		if ( $n ) {
			set_transient( 'mem_desc_report', $n, DAY_IN_SECONDS );
		}
	}
);
add_action(
	'admin_notices',
	function () {
		$n = get_transient( 'mem_desc_report' );
		if ( ! $n || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		delete_transient( 'mem_desc_report' );
		echo ( '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'From the descriptions of catalog sections (', 'memorial-plugin' ) ) . (int) $n . ( esc_html__( ') the old “N models, price from … ₽” were removed — the catalog shows these numbers itself and updates them automatically.', 'memorial-plugin' ) . '</p></div>' );
	}
);
