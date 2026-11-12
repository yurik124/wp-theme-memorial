<?php
/**
 * Фотогалереи, которые собираются сами (страница «Фотогалерея» и любые другие):
 *   [mem_works vid="фото" chislo="12" poryadok="случайно"]   — фото из «Наших работ» (все фото каждой работы)
 *   [mem_cat_photos cat="Венки, Гробы" chislo="6" poryadok="случайно"] — фото товаров из разделов каталога
 * Клик — фото крупно (просмотрщик сайта), подпись — название работы / товара и цена.
 * Случайный порядок — в браузере, при каждом открытии другие фото (и из кэша страниц тоже).
 * Фото другой формы, чем большинство, показывается целиком, поля — размытая копия (как в галереях).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Сетка фото. $items: [['id' => вложение, 'cap' => подпись, 'url' => куда вести (необязательно)]]
 *
 * @param array $items Items.
 * @param int   $n Number.
 * @param mixed $rand Rand.
 * @param int   $cols Cols.
 */
function memorial_plugin_photos_grid( array $items, $n, $rand, $cols = 3 ) {
	$items = array_values( array_filter( $items, fn( $x ) => ! empty( $x['id'] ) ) );
	if ( ! $items ) {
		return '';
	}
	if ( $rand ) {
		shuffle( $items );
	} else {
		$items = array_slice( $items, 0, $n );
	}
	$items = array_slice( $items, 0, max( $n, $rand ? 60 : $n ) );   // для случайного — запас, браузер покажет $n.
	$r     = memorial_plugin_common_ratio( array_column( $items, 'id' ) );
	$r     = $r ? $r : 4 / 3;
	$h     = '<div class="mem-photos" style="--mem-ar:' . memorial_plugin_ratio_css( $r ) . ';--cols:' . (int) $cols . '"' . ( $rand ? ' data-wcards-rand="' . (int) $n . '" data-wp-interactive="memorial-plugin/works" data-wp-init="callbacks.random"' : '' ) . '>';
	foreach ( $items as $i => $x ) {
		$big = wp_get_attachment_image_url( $x['id'], 'large' );
		if ( ! $big ) {
			continue;
		}
		$ir  = memorial_plugin_img_ratio( $x['id'] );
		$odd = $ir && abs( $ir / $r - 1 ) > .12;
		$bg  = $odd ? memorial_plugin_blur_bg_url( $x['id'] ) : '';
		$img = wp_get_attachment_image(
			$x['id'],
			'medium_large',
			false,
			array(
				'loading'  => 'lazy',
				'decoding' => 'async',
				'alt'      => wp_strip_all_tags( $x['cap'] ?? '' ),
				'sizes'    => '(max-width:600px) 50vw, 33vw',
			)
		);
		$h  .= '<a class="mem-photo' . ( $odd ? ' mem-fit' : '' ) . '" href="' . esc_url( $big ) . '" data-lb data-cap="' . esc_attr( wp_strip_all_tags( $x['cap'] ?? '' ) ) . '"'
			. ( $bg ? ' style="--mbg:url(&quot;' . esc_url( $bg ) . '&quot;)"' : '' ) . ( $i >= $n ? ' hidden' : '' ) . '>' . $img
			. ( ! empty( $x['cap'] ) ? '<span class="mem-photo__cap">' . esc_html( $x['cap'] ) . '</span>' : '' ) . '</a>';
	}
	return $h . '</div>';
}

/** Фото «Наших работ» — вызывается из [mem_works vid="фото"]
 *
 * @param array $a Arguments.
 */
function memorial_plugin_works_photos( $a ) {
	$n    = max( 1, min( 60, (int) ( $a['chislo'] ? $a['chislo'] : 12 ) ) );
	$args = array(
		'post_type'      => 'mem_work',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'date'       => 'ASC',
		),
	);
	if ( trim( $a['type'] ) !== '' ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'mem_work_type',
				'field'    => 'slug',
				'terms'    => memorial_plugin_work_type_slugs( $a['type'] ),
			),
		);
	}
	$items = array();
	foreach ( get_posts( $args ) as $w ) {
		$d   = memorial_plugin_work_data( $w->ID );
		$cap = get_the_title( $w ) . ( $d['total'] && ! memorial_plugin_sc_no( $a['cena'] ) ? ' — ' . memorial_plugin_rub( $d['total'] ) : '' );
		foreach ( $d['photos'] as $pid ) {
			$items[] = array(
				'id'  => (int) $pid,
				'cap' => html_entity_decode( $cap, ENT_QUOTES, 'UTF-8' ),
			);
		}
	}
	if ( ! $items ) {
		return current_user_can( 'edit_posts' ) ? ( '<p class="is-style-note">' . esc_html__( 'Our works (photos): there are no photos in “Our works” yet.', 'memorial-plugin' ) . '</p>' ) : '';
	}
	$rand = in_array( mb_strtolower( trim( $a['poryadok'] ) ), array( 'случайно', 'случайные', 'random' ), true );
	return memorial_plugin_photos_grid( $items, $n, $rand, 3 );
}

/* [mem_cat_photos cat="Венки, Гробы" chislo="6" poryadok="случайно" cena="нет"] — фото товаров из разделов каталога */
add_shortcode(
	'mem_cat_photos',
	function ( $atts ) {
		$a   = shortcode_atts(
			array(
				'cat'      => '',
				'chislo'   => '6',
				'poryadok' => '',
				'cena'     => 'да',
			),
			$atts,
			'mem_cat_photos'
		);
		$ids = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $a['cat'] ) ) ) as $c ) {
			$t = memorial_plugin_summary_cat( $c );
			if ( $t ) {
				$ids = array_merge( $ids, array( $t->term_id ), get_term_children( $t->term_id, 'mem_cat' ) );
			}
		}
		if ( ! $ids ) {
			return memorial_plugin_sc_missing( 'mem_cat_photos', $a['cat'] );
		}
		$items = array();
		foreach ( get_posts(
			array(
				'post_type'      => 'mem_product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_thumbnail_id',
				'tax_query'      => array(
					array(
						'taxonomy' => 'mem_cat',
						'terms'    => array_unique( $ids ),
					),
				),
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		) as $p ) {
			$price   = (int) get_post_meta( $p->ID, '_mem_price', true );
			$cap     = get_the_title( $p ) . ( $price && ! memorial_plugin_sc_no( $a['cena'] ) ? ' — ' . memorial_plugin_rub( $price ) : '' );
			$items[] = array(
				'id'  => (int) get_post_thumbnail_id( $p ),
				'cap' => html_entity_decode( $cap, ENT_QUOTES, 'UTF-8' ),
			);
		}
		$n    = max( 1, min( 60, (int) $a['chislo'] ? (int) $a['chislo'] : 6 ) );
		$rand = in_array( mb_strtolower( trim( $a['poryadok'] ) ), array( 'случайно', 'случайные', 'random' ), true );
		return memorial_plugin_photos_grid( $items, $n, $rand, 3 );
	}
);

/** Разделы для «Венки и гробы»: для каждого слова — раздел, название которого с него НАЧИНАЕТСЯ
 *  (чтобы «гроб» не находил «Памятники и надгробия»), с наибольшим числом товаров. */
function memorial_plugin_photos_pick_cats() {
	$cats = array();
	foreach ( array( 'венк', 'гроб' ) as $w ) {
		$best = null;
		$max  = 0;
		foreach ( get_terms(
			array(
				'taxonomy'   => 'mem_cat',
				'hide_empty' => false,
			)
		) as $t ) {
			if ( ! preg_match( '~(^|[^а-яё])' . $w . '~ui', $t->name ) ) {
				continue;
			}
			$n = (int) ( memorial_plugin_cat_stats( $t->term_id )[0] ?? 0 );
			if ( $n > $max ) {
				$max  = $n;
				$best = $t; }
		}
		if ( $best ) {
			$cats[] = memorial_plugin_cat_ref( $best->term_id );
		}
	}
	return $cats;
}

/* Исправление 1.4.0: тогда вместо гробов мог выбраться раздел «Памятники и надгробия» — меняем на правильный */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'mem_photos_fix2' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		update_option( 'mem_photos_fix2', 1, false );
		$cats = memorial_plugin_photos_pick_cats();
		if ( ! $cats ) {
			return;
		}
		foreach ( get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				's'              => 'mem_cat_photos',
			)
		) as $p ) {
			$c = preg_replace_callback(
				'~\[mem_cat_photos\s+cat="([^"]*)"~u',
				function ( $m ) use ( $cats ) {
					$names = '';
					foreach ( array_map( 'trim', explode( ',', $m[1] ) ) as $ref ) {
						$t      = ctype_digit( $ref ) ? get_term( (int) $ref, 'mem_cat' ) : null;
						$names .= ' ' . ( $t && ! is_wp_error( $t ) ? $t->name : $ref );
					}
					return preg_match( '~надгроб|памятник~ui', $names ) ? '[mem_cat_photos cat="' . esc_attr( implode( ', ', $cats ) ) . '"' : $m[0];
				},
				$p->post_content
			);
			if ( $c && $c !== $p->post_content ) {
				wp_update_post(
					array(
						'ID'           => $p->ID,
						'post_content' => wp_slash( $c ),
					)
				);
			}
		}
	}
);

/*
Разово: на странице «Фотогалерея» ручные галереи «Наши работы» и «Венки и гробы» заменяются на собираемые сами.
	Прежний вариант — в «Редакциях» страницы.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'mem_photos_migrated' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		update_option( 'mem_photos_migrated', 1, false );
		$map  = array(
			'Наши работы'   => '[mem_works vid="фото" chislo="12" poryadok="случайно"]',
			'Венки и гробы' => '[mem_cat_photos cat="%s" chislo="6" poryadok="случайно"]',
		);
		$cats = memorial_plugin_photos_pick_cats();
		$done = array();
		foreach ( get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
			)
		) as $p ) {
			$c = $p->post_content;
			foreach ( $map as $head => $sc ) {
				if ( 'Венки и гробы' === $head ) {
					if ( ! $cats ) {
						continue;
					} $sc = sprintf( $sc, implode( ', ', $cats ) ); }
				// заголовок раздела, затем (текст) и первая галерея после него.
				$c2 = preg_replace(
					'~(<h2[^>]*>\s*' . preg_quote( $head, '~' ) . '\s*</h2>.*?)<!-- wp:gallery\b[^>]*-->.*?<!-- /wp:gallery -->~su',
					"$1<!-- wp:shortcode -->\n" . $sc . "\n<!-- /wp:shortcode -->",
					$c,
					1
				);
				if ( null !== $c2 && $c2 !== $c && false !== strpos( $c, 'wp:gallery' ) ) {
					// не перескакивать через следующий заголовок: галерея должна быть в этом же разделе.
					$seg = substr( $c, strpos( $c, $head ) );
					$g   = strpos( $seg, '<!-- wp:gallery' );
					$nh  = strpos( $seg, '<!-- wp:heading', 10 );
					if ( false !== $g && ( false === $nh || $g < $nh ) ) {
						$c = $c2;
					}
				}
			}
			if ( $c !== $p->post_content ) {
				wp_update_post(
					array(
						'ID'           => $p->ID,
						'post_content' => wp_slash( $c ),
					)
				);
				$done[] = get_the_title( $p );
			}
		}
		if ( $done ) {
			set_transient( 'mem_photos_report', $done, DAY_IN_SECONDS );
		}
	}
);
add_action(
	'admin_notices',
	function () {
		$done = get_transient( 'mem_photos_report' );
		if ( ! $done || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		delete_transient( 'mem_photos_report' );
		echo ( '<div class="notice notice-success is-dismissible"><p><b>' . esc_html__( 'Photo gallery:', 'memorial-plugin' ) . '</b> ' . esc_html__( 'on the page “', 'memorial-plugin' ) ) . esc_html( implode( '», «', $done ) )
		. ( esc_html__( '” the photos of “Our works” and of the products are now picked up automatically (the [mem_works vid="фото"] and [mem_cat_photos] shortcodes). The previous version is in the page’s “Revisions”.', 'memorial-plugin' ) . '</p></div>' );
	}
);
