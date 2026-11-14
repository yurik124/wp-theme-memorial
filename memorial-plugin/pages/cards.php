<?php
/**
 * [mem_cards] — карточки разделов, собираются сами.
 * Какие страницы: подпункты этой страницы в главном меню (как в меню, в том же порядке),
 * если их нет — дочерние страницы; или списком: pages="pamyatniki, ogradki-2".
 * Карточка: фото (как в плитке каталога: «Изображение записи», точка фокуса, «целиком», фон),
 * название, строка «96 моделей, от 35 000 ₽» (для страниц каталога — считается сама) и/или своя подпись.
 * Своя подпись и название задаются в редакторе той страницы: панель «Плитка и карточка раздела».
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		$auth = fn() => current_user_can( 'edit_pages' );
		foreach ( array( '_mem_card_title', '_mem_card_text' ) as $k ) {
			register_post_meta(
				'page',
				$k,
				array(
					'type'          => 'string',
					'single'        => true,
					'default'       => '',
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}
	}
);

/** Страницы для карточек: [[id, название из меню], …]
 *
 * @param array $list List.
 * @param int   $page_id Page ID.
 */
function memorial_plugin_cards_pages( $list, $page_id ) {
	$out = array();
	if ( trim( $list ) !== '' ) {
		foreach ( array_filter( array_map( 'trim', explode( ',', $list ) ) ) as $v ) {
			$p = ctype_digit( $v ) ? get_post( (int) $v ) : get_page_by_path( trim( $v, '/' ) );
			if ( ! $p && ! ctype_digit( $v ) ) {
				$p = get_posts(
					array(
						'post_type'      => 'page',
						'title'          => $v,
						'posts_per_page' => 1,
					)
				)[0] ?? null;
			}
			if ( $p && 'page' === $p->post_type && 'publish' === $p->post_status ) {
				$out[] = array( $p->ID, '' );
			}
		}
		return $out;
	}
	foreach ( memorial_plugin_menu_children( $page_id ) as $it ) {
		if ( get_post_status( (int) $it->object_id ) === 'publish' ) {
			$out[] = array( (int) $it->object_id, $it->title );
		}
	}
	if ( $out ) {
		return $out;
	}
	foreach ( get_pages(
		array(
			'parent'      => $page_id,
			'sort_column' => 'menu_order,post_title',
			'post_status' => 'publish',
		)
	) as $p ) {
		$out[] = array( $p->ID, '' );
	}
	return $out;
}

/** Самая частая единица цены у товаров раздела: «за метр»
 *
 * @param int $term_id Term ID.
 */
function memorial_plugin_cat_unit( $term_id ) {
	$ids = get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
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
	$u   = array_count_values( array_filter( array_map( fn( $id ) => trim( (string) get_post_meta( $id, '_mem_unit', true ) ), $ids ) ) );
	if ( ! $u ) {
		return '';
	}
	arsort( $u );
	return array_sum( $u ) * 2 > count( $ids ) ? (string) array_key_first( $u ) : ''; // единица у большинства товаров.
}

add_shortcode(
	'mem_cards',
	function ( $atts ) {
		$a     = shortcode_atts(
			array(
				'pages'   => '',     // какие страницы (пусто — подпункты меню / дочерние).
				'roditel' => '',     // разделы другой страницы (ID); пусто — этой.
				'foto'    => 'да',   // фото.
				'chislo'  => 'да',   // «96 моделей»
				'cena'    => 'да',   // «от 35 000 ₽»
				'podpis'  => 'да',   // своя подпись страницы (или её краткое описание).
				'strelka' => 'да',   // «→»
			),
			$atts,
			'mem_cards'
		);
		$parent = (int) $a['roditel'];
		$pages  = memorial_plugin_cards_pages( $a['pages'], $parent && 'publish' === get_post_status( $parent ) ? $parent : (int) get_the_ID() );
		if ( ! $pages ) {
			return current_user_can( 'edit_pages' ) ? ( '<p class="is-style-note">' . esc_html__( 'Section cards: no pages for the cards — add sub-items to this page in the main menu or choose the pages in the block settings.', 'memorial-plugin' ) . '</p>' ) : '';
		}
		$show = fn( $k ) => ! memorial_plugin_sc_no( $a[ $k ] );
		$h    = '<div class="mem-cards">';
		foreach ( $pages as [$pid, $menu_title] ) {
			$title = trim( (string) get_post_meta( $pid, '_mem_card_title', true ) );
			if ( '' === $title ) {
				$title = $menu_title ? $menu_title : get_the_title( $pid );
			}
			$lines = array();
			$term  = memorial_plugin_page_term( $pid );
			if ( $term ) {
				[$n, $min] = memorial_plugin_cat_stats( $term );
				$bits      = array();
				if ( $show( 'chislo' ) && $n ) {
					$bits[] = $n . "\u{00A0}" . _n( 'model', 'models', $n, 'memorial-plugin' );
				}
				if ( $show( 'cena' ) && $min ) {
					$bits[] = sprintf( /* translators: %s: price */ __( 'from %s', 'memorial-plugin' ), memorial_plugin_rub( $min ) ) . ( memorial_plugin_cat_unit( $term ) ? ' ' . memorial_plugin_cat_unit( $term ) : '' );
				}
				if ( $bits ) {
					$lines[] = implode( ', ', $bits );
				}
			}
			if ( $show( 'podpis' ) ) {
				$txt = trim( (string) get_post_meta( $pid, '_mem_card_text', true ) );
				if ( '' === $txt && ! $term && has_excerpt( $pid ) ) {
					$txt = wp_trim_words( get_the_excerpt( $pid ), 14, '…' );
				}
				if ( '' !== $txt ) {
					$lines[] = do_shortcode( $txt );
				}
			}
			$img = '';
			$tid = $show( 'foto' ) ? (int) get_post_thumbnail_id( $pid ) : 0;
			if ( $tid ) {
				$st    = array();
				$focus = '';
				$pos   = (string) get_post_meta( $pid, '_mem_tile_pos', true );
				$fit   = (bool) get_post_meta( $pid, '_mem_tile_fit', true );
				$bg    = sanitize_hex_color( (string) get_post_meta( $pid, '_mem_tile_bg', true ) );
				if ( preg_match( '/^(\d{1,3}(?:\.\d+)?)% (\d{1,3}(?:\.\d+)?)%$/', $pos, $pm ) ) {
					$st[] = 'object-position:' . round( (float) $pm[1] ) . '% ' . round( (float) $pm[2] ) . '%';
					if ( ! $fit ) {
						$focus = round( (float) $pm[1] ) . ' ' . round( (float) $pm[2] );
					}
				}
				if ( $fit ) {
					$st[] = 'object-fit:contain';
				}
				if ( $bg ) {
					$st[] = 'background-color:' . $bg;
				}
				$attr = array(
					'class'    => 'mem-card__img',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => $title,
					'sizes'    => '(max-width:600px) 100vw, 400px',
				);
				if ( $st ) {
					$attr['style'] = implode( ';', $st );
				}
				if ( $focus ) {
					$attr += array(
						'data-focus'          => $focus,
						'data-wp-interactive' => 'memorial-plugin/focus',
						'data-wp-init'        => 'callbacks.init',
					);
				}
				$img = wp_get_attachment_image( $tid, 'medium_large', false, $attr );
			}
			$h .= '<a class="mem-card' . ( $img ? '' : ' mem-card--noimg' ) . '" href="' . esc_url( get_permalink( $pid ) ) . '">'
			. ( $img ? '<span class="mem-card__ph">' . $img . '</span>' : '' )
			. '<span class="mem-card__body"><strong class="mem-card__title">' . esc_html( $title ) . '</strong>'
			. ( $lines ? '<span class="mem-card__sub">' . implode(
				'<br>',
				array_map(
					fn( $l ) => wp_kses(
						$l,
						array(
							'br'     => array(),
							'b'      => array(),
							'strong' => array(),
							'em'     => array(),
						)
					),
					$lines
				)
			) . '</span>' : '' )
				. ( $show( 'strelka' ) ? '<span class="mem-card__go" aria-hidden="true">' . esc_html__( 'More →', 'memorial-plugin' ) . '</span>' : '' )
				. '</span></a>';
		}
		return $h . '</div>';
	}
);

