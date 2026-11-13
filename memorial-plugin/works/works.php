<?php
/**
 * «Наши работы» — примеры благоустройства с фото, размером участка и ценами.
 * Добавляются в «Наши работы → Добавить работу», выводятся на странице шорткодом [mem_works].
 *
 *   [mem_works]               — все работы
 *   [mem_works type="plitka"] — только один вид работ
 *   [mem_works limit="3"]     — первые три (например, на странице «Благоустройство»)
 *
 * Карточками (фото + название + размер и цена, клик — к работе на странице со всеми работами):
 *   [mem_works vid="карточки"]                              — 6 работ: отмеченные «В примерах» (если отмеченных нет — любые)
 *   [mem_works vid="карточки" chislo="3" poryadok="случайно"] — 3 случайные, при каждом открытии страницы другие
 *   [mem_works vid="карточки" type="Укладка плитки"]          — только один вид работ (название или адрес вида)
 *   [mem_works vid="карточки" raboty="Плитка «Гжель», …"]    — выбранные работы по названию
 *   razmer="нет" / cena="нет" — без размера участка / без цены
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Якорь работы по названию латиницей: «Плитка «Гжель»…» → #rabota-plitka-gzhel-… — на него можно ссылаться с других страниц
 *
 * @param string $title Title.
 */
function memorial_plugin_work_anchor( $title ) {
	return 'rabota-' . substr( memorial_plugin_slug( $title ), 0, 60 );
}

add_action(
	'init',
	function () {
		register_post_type(
			'mem_work',
			array(
				'labels'        => array(
					'name'          => __( 'Our works', 'memorial-plugin' ),
					'singular_name' => __( 'Work', 'memorial-plugin' ),
					'menu_name'     => __( 'Our works', 'memorial-plugin' ),
					'all_items'     => __( 'All works', 'memorial-plugin' ),
					'add_new'       => __( 'Add a work', 'memorial-plugin' ),
					'add_new_item'  => __( 'New work', 'memorial-plugin' ),
					'edit_item'     => __( 'Edit work', 'memorial-plugin' ),
				),
				'public'        => false,
				'show_ui'       => true,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-format-gallery',
				'menu_position' => 6,
				'supports'      => array( 'title', 'editor', 'page-attributes' ),
			)
		);
		register_taxonomy(
			'mem_work_type',
			'mem_work',
			array(
				'labels'            => array(
					'name'          => __( 'Kinds of works', 'memorial-plugin' ),
					'singular_name' => __( 'Kind of work', 'memorial-plugin' ),
					'add_new_item'  => __( 'Add a kind', 'memorial-plugin' ),
				),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => false,
			)
		);
	}
);

/* Работы — в блочном редакторе: текст в редакторе, фото, размер и цены — в боковой панели (admin/editor-meta.php) */

/**
 * Work data.
 *
 * @param int $id ID.
 */
function memorial_plugin_work_data( $id ) {
	$rows = get_post_meta( $id, '_mem_rows', true );
	$rows = is_array( $rows ) ? $rows : array();
	return array(
		'photos' => array_values( array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $id, '_mem_photos', true ) ) ) ) ),
		'size'   => (string) get_post_meta( $id, '_mem_size', true ),
		'year'   => (int) get_post_meta( $id, '_mem_year', true ) ? (int) get_post_meta( $id, '_mem_year', true ) : '',
		'rows'   => $rows,
		'total'  => array_sum( array_map( fn( $r ) => (int) ( $r[1] ?? 0 ), $rows ) ),
	);
}

/* ---------- Вывод: [mem_works] ---------- */
add_shortcode(
	'mem_works',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'type'     => '',
				'limit'    => -1,
				'sort'     => '1',
				'nav'      => '1',
				'vid'      => '',
				'chislo'   => '',
				'poryadok' => '',
				'raboty'   => '',
				'razmer'   => 'да',
				'cena'     => 'да',
			),
			$atts,
			'mem_works'
		);
		if ( in_array( mb_strtolower( trim( $a['vid'] ) ), array( 'карточки', 'cards' ), true ) ) {
			return memorial_plugin_works_cards( $a );
		}
		if ( in_array( mb_strtolower( trim( $a['vid'] ) ), array( 'фото', 'photos' ), true ) ) {
			return function_exists( 'memorial_plugin_works_photos' ) ? memorial_plugin_works_photos( $a ) : '';
		}
		$a['nav']  = memorial_plugin_sc_no( $a['nav'] ) ? '0' : '1';
		$a['sort'] = memorial_plugin_sc_no( $a['sort'] ) ? '0' : '1';
		$args      = array(
			'post_type'      => 'mem_work',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $a['limit'] ? (int) $a['limit'] : -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'ASC',
			),
		);
		if ( '' !== $a['type'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'mem_work_type',
					'field'    => 'slug',
					'terms'    => memorial_plugin_work_type_slugs( $a['type'] ),
				),
			);
		}
		$works = get_posts( $args );
		if ( ! $works ) {
			return '';
		}

		$types    = array();
		$terms_of = function ( $w ) {
			$t = get_the_terms( $w, 'mem_work_type' );
			return $t && ! is_wp_error( $t ) ? $t : array();
		};
		foreach ( $works as $w ) {
			foreach ( $terms_of( $w ) as $t ) {
				$types[ $t->slug ] = $t->name;
			}
		}
		// порядок кнопок — как в «Наши работы → Виды работ».
		uksort(
			$types,
			function ( $a, $b ) {
				$ta = get_term_by( 'slug', $a, 'mem_work_type' );
				$tb = get_term_by( 'slug', $b, 'mem_work_type' );
				return (int) get_term_meta( $ta->term_id ?? 0, 'mem_order', true ) <=> (int) get_term_meta( $tb->term_id ?? 0, 'mem_order', true );
			}
		);

		$has_year = (bool) array_filter( $works, fn( $w ) => get_post_meta( $w->ID, '_mem_year', true ) );

		ob_start();
		echo '<div class="works" data-works data-wp-interactive="memorial-plugin/works" data-wp-init="callbacks.init">';
		$show_nav = '0' !== $a['nav'] && count( $types ) > 1 && '' === $a['type'];
		// The kind buttons stick to the top of the screen; the sorting stays in its place above the works.
		if ( $show_nav ) {
			echo '<span class="mcat__pin" aria-hidden="true"></span><div class="mcat__bar">';
			echo ( '<div class="mcat__navwrap"><nav class="mcat__nav" aria-label="' . esc_attr__( 'Kinds of works', 'memorial-plugin' ) . '"><button type="button" class="chip is-on" data-type="">' . esc_html__( 'All', 'memorial-plugin' ) . '</button>' );
			foreach ( $types as $slug => $name ) {
				echo '<button type="button" class="chip" data-type="' . esc_attr( $slug ) . '">' . esc_html( $name ) . '</button>';
			}
			echo '</nav></div></div>';
		}
		if ( '0' !== $a['sort'] && count( $works ) > 1 ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup with escaped strings.
			echo ( '<div class="mcat__sortrow"><label class="mcat__sort"><span class="mcat__sortlbl">' . esc_html__( 'Sorting:', 'memorial-plugin' ) . '</span><select data-works-sort data-wp-on--change="actions.sort">' )
				. ( '<option value="">' . esc_html__( 'Default', 'memorial-plugin' ) . '</option>' )
				. ( $has_year ? ( '<option value="new">' . esc_html__( 'Newest first', 'memorial-plugin' ) . '</option>' ) : '' )
				. ( '<option value="asc">' . esc_html__( 'Cheapest first', 'memorial-plugin' ) . '</option><option value="desc">' . esc_html__( 'Most expensive first', 'memorial-plugin' ) . '</option></select></label></div>' );
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<div class="works__list">';
		foreach ( $works as $i => $w ) {
			$d     = memorial_plugin_work_data( $w->ID );
			$slugs = implode( ' ', wp_list_pluck( $terms_of( $w ), 'slug' ) );
			$title = get_the_title( $w );
			$cap   = $title . ( $d['year'] ? ' · ' . (int) $d['year'] : '' );
			echo '<article class="work" id="' . esc_attr( memorial_plugin_work_anchor( $title ) ) . '" data-price="' . (int) $d['total'] . '" data-year="' . (int) $d['year'] . '" data-i="' . (int) $i . '" data-types="' . esc_attr( $slugs ) . '">';
			if ( $d['photos'] ) {
				$main = $d['photos'][0];
				$wbg  = wp_get_attachment_image_url( $main, 'thumbnail' ); // размытый фон, если следующее фото другой формы
				// рамка — по форме главного фото: вертикальное фото и показывается вертикально, крупно (не уже 3:4, не шире 16:10).
				$war = memorial_plugin_img_ratio( $main );
				$war = $war ? $war : 4 / 3;
				$war = max( .75, min( 1.6, $war ) );
				echo '<div class="work__media"><div class="work__stage"><a class="work__img" href="' . esc_url( wp_get_attachment_image_url( $main, 'large' ) ) . '" data-lb data-cap="' . esc_attr( $cap ) . '"'
					. ' style="--war:' . memorial_plugin_ratio_css( $war ) . ( $wbg ? ';--wbg:url(&quot;' . esc_url( $wbg ) . '&quot;)' : '' ) . '">'
					. wp_get_attachment_image(
						$main,
						'large',
						false,
						array(
							'loading' => 'lazy',
							'sizes'   => '(max-width:700px) 100vw, 460px',
							'alt'     => $title,
						)
					);
				if ( $d['year'] ) {
					echo '<span class="work__year">' . (int) $d['year'] . '</span>';
				}
				echo '</a>';
				if ( count( $d['photos'] ) > 1 ) {
					echo ( '<button type="button" class="work__arr work__arr--l" data-dir="-1" aria-label="' . esc_html__( 'Previous photo', 'memorial-plugin' ) . '">' ) . memorial_plugin_chevron( 'l' ) . '</button>'
						. ( '<button type="button" class="work__arr work__arr--r" data-dir="1" aria-label="' . esc_html__( 'Next photo', 'memorial-plugin' ) . '">' ) . memorial_plugin_chevron( 'r' ) . '</button>';
				}
				echo '</div>';
				if ( count( $d['photos'] ) > 1 ) {
					echo '<div class="work__thumbs">';
					foreach ( $d['photos'] as $k => $p ) {
						echo '<a class="work__thumb' . ( $k ? '' : ' is-on' ) . '" href="' . esc_url( wp_get_attachment_image_url( $p, 'large' ) ) . '" data-lb-item data-cap="' . esc_attr( $cap ) . ( '" aria-label="' . esc_attr__( 'Photo', 'memorial-plugin' ) . ' ' ) . (int) ( $k + 1 ) . '">'
							. wp_get_attachment_image(
								$p,
								'thumbnail',
								false,
								array(
									'loading' => 'lazy',
									'alt'     => '',
								)
							) . '</a>';
					}
					echo '</div>';
				}
				echo '</div>';
			}
			echo '<div class="work__body"><h3>' . esc_html( $title ) . '</h3>';
			$facts = array();
			if ( $d['year'] ) {
				$facts[] = ( '<span><strong>' . esc_html__( 'Done in:', 'memorial-plugin' ) . '</strong> ' ) . (int) $d['year'] . '</span>';
			}
			if ( $d['size'] ) {
				$facts[] = ( '<span><strong>' . esc_html__( 'Plot size:', 'memorial-plugin' ) . '</strong> ' ) . esc_html( $d['size'] ) . '</span>';
			}
			if ( $facts ) {
				echo '<p class="work__size">' . implode( '', $facts ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every fact is built with esc_html().
			}
			if ( trim( wp_strip_all_tags( $w->post_content ) ) !== '' ) {
				echo '<div class="work__text">' . wp_kses_post( has_blocks( $w->post_content ) ? do_blocks( $w->post_content ) : wpautop( $w->post_content ) ) . '</div>';
			}
			if ( $d['rows'] ) {
				echo ( '<figure class="wp-block-table is-style-price"><table><thead><tr><th>' . esc_html__( 'What was done', 'memorial-plugin' ) . '</th><th>' . esc_html__( 'Cost', 'memorial-plugin' ) . '</th></tr></thead><tbody>' );
				foreach ( $d['rows'] as $r ) {
					echo '<tr><td>' . esc_html( $r[0] ) . '</td><td>' . ( $r[1] ? esc_html( memorial_plugin_rub( $r[1] ) ) : '—' ) . '</td></tr>';
				}
				echo '</tbody>';
				if ( count( $d['rows'] ) > 1 && $d['total'] ) {
					echo ( '<tfoot><tr><td>' . esc_html__( 'Total', 'memorial-plugin' ) . '</td><td>' ) . esc_html( memorial_plugin_rub( $d['total'] ) ) . '</td></tr></tfoot>';
				}
				echo '</table></figure>';
				echo '<p class="work__note">' . ( $d['year'] ? esc_html( sprintf( /* translators: %d: year */ __( 'Prices of %d.', 'memorial-plugin' ), (int) $d['year'] ) ) . ' ' : '' ) . ( esc_html__( 'Today the cost may differ — we will calculate it by phone for free.', 'memorial-plugin' ) . '</p>' );
			}
			echo '</div></article>';
		}
		echo '</div></div>';
		return ob_get_clean();
	}
);

/** Виды работ из параметра type: адреса или названия через запятую → адреса
 *
 * @param mixed $v Value.
 */
function memorial_plugin_work_type_slugs( $v ) {
	$out = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', (string) $v ) ) ) as $x ) {
		$t     = get_term_by( 'slug', sanitize_title( $x ), 'mem_work_type' );
		$t     = $t ? $t : get_term_by( 'name', $x, 'mem_work_type' );
		$out[] = $t ? $t->slug : $x;
	}
	return $out;
}

/** Адрес страницы со всеми работами ([mem_works] без vid="карточки") — туда ведут карточки */
function memorial_plugin_works_page_url() {
	static $url = null;
	if ( null !== $url ) {
		return $url;
	}
	global $wpdb;
	$url  = '';
	$rows = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[mem\\_works%' ORDER BY menu_order, ID" );
	foreach ( $rows as $r ) {
		if ( preg_match_all( '~\[mem_works(?:\s[^\]]*)?\]~u', $r->post_content, $m ) ) {
			foreach ( $m[0] as $sc ) {
				if ( ! preg_match( '~vid\s*=\s*["\'](карточки|cards)~u', $sc ) ) {
					$url = get_permalink( $r->ID );
					break 2; }
			}
		}
	}
	return $url;
}

/** «В примерах»: отмечается в «Наши работы» (меню «⋯» у работы) или в редакторе работы */
add_action(
	'init',
	function () {
		register_post_meta(
			'mem_work',
			'_mem_example',
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => false,
				'auth_callback' => fn() => current_user_can( 'edit_posts' ),
			)
		);
	}
);

/** Карточки работ: [mem_works vid="карточки" …]
 *
 * @param array $a Arguments.
 */
function memorial_plugin_works_cards( $a ) {
	$n    = max( 1, min( 30, (int) ( $a['chislo'] ? $a['chislo'] : 6 ) ) );
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
	$works = get_posts( $args );
	$works = array_values( array_filter( $works, fn( $w ) => memorial_plugin_work_data( $w->ID )['photos'] ) ); // карточка без фото не нужна.
	if ( trim( $a['raboty'] ) !== '' ) { // выбранные по названию — в том порядке, как перечислены; кавычки любые («», "", „“).
		$norm = fn( $t ) => trim( preg_replace( array( '~[«»"„“”\'`]~u', '~\s*,\s*~u', '~\s+~u' ), array( '', ',', ' ' ), mb_strtolower( html_entity_decode( (string) $t, ENT_QUOTES, 'UTF-8' ) ) ) );
		$list = ',' . $norm( $a['raboty'] ) . ',';
		$pick = array();
		foreach ( $works as $w ) {
			$pos = mb_strpos( $list, ',' . $norm( get_the_title( $w ) ) . ',' );
			if ( false !== $pos ) {
				$pick[ $pos ] = $w;
			}
		}
		ksort( $pick );
		$works = array_values( $pick );
	} else {
		$marked = array_values( array_filter( $works, fn( $w ) => (bool) get_post_meta( $w->ID, '_mem_example', true ) ) );
		if ( $marked ) {
			$works = $marked;
		}
	}
	if ( ! $works ) {
		return current_user_can( 'edit_posts' ) ? ( '<p class="is-style-note">' . esc_html__( 'Our works (cards): no suitable works with photos — add them in “Our works”.', 'memorial-plugin' ) . '</p>' ) : '';
	}
	$ord  = mb_strtolower( trim( $a['poryadok'] ) );
	$rand = in_array( $ord, array( 'случайно', 'случайные', 'random' ), true );
	if ( $rand ) {
		shuffle( $works );
	} elseif ( in_array( $ord, array( 'дешевле', 'дороже' ), true ) ) {
		usort( $works, fn( $x, $y ) => ( memorial_plugin_work_data( $x->ID )['total'] <=> memorial_plugin_work_data( $y->ID )['total'] ) * ( 'дешевле' === $ord ? 1 : -1 ) );
	} elseif ( 'новые' === $ord ) {
		usort( $works, fn( $x, $y ) => (int) memorial_plugin_work_data( $y->ID )['year'] <=> (int) memorial_plugin_work_data( $x->ID )['year'] );
	}
	// случайные: на страницу попадают все (до 30), браузер показывает N случайных — разные при каждом открытии, даже из кэша.
	if ( ! $rand ) {
		$works = array_slice( $works, 0, $n );
	}
	$base = memorial_plugin_works_page_url();
	// общие пропорции карточек — как у большинства фото (фото другой формы показывается целиком, с полями).
	$ar = memorial_plugin_common_ratio( array_map( fn( $w ) => memorial_plugin_work_data( $w->ID )['photos'][0], array_slice( $works, 0, 30 ) ) );
	$h  = '<div class="mem-wcards"' . ( $ar ? ' style="--mem-ar:' . memorial_plugin_ratio_css( $ar ) . '"' : '' ) . ( $rand ? ' data-wcards-rand="' . $n . '" data-wp-interactive="memorial-plugin/works" data-wp-init="callbacks.random"' : '' ) . '>';
	foreach ( array_slice( $works, 0, 30 ) as $i => $w ) {
		$d     = memorial_plugin_work_data( $w->ID );
		$title = get_the_title( $w );
		$sub   = array();
		if ( ! memorial_plugin_sc_no( $a['razmer'] ) && '' !== $d['size'] ) {
			$sub[] = ( '<span class="nowrap">' . esc_html__( 'plot', 'memorial-plugin' ) . ' ' ) . esc_html( $d['size'] ) . '</span>';
		}
		if ( ! memorial_plugin_sc_no( $a['cena'] ) && $d['total'] ) {
			$sub[] = '<span class="nowrap">' . esc_html( memorial_plugin_rub( $d['total'] ) ) . '</span>';
		}
		$href  = $base ? $base . '#' . memorial_plugin_work_anchor( $title ) : ''; // тот же якорь, что у работы в общем списке.
		$img   = wp_get_attachment_image(
			$d['photos'][0],
			'medium_large',
			false,
			array(
				'class'    => 'mem-wcard__img',
				'loading'  => 'lazy',
				'decoding' => 'async',
				'alt'      => wp_strip_all_tags( $title ),
				'sizes'    => '(max-width:600px) 50vw, 300px',
			)
		);
		$inner = '<span class="mem-wcard__ph">' . $img . '</span><span class="mem-wcard__cap"><strong>' . esc_html( $title ) . '</strong>' . implode( ' — ', $sub ) . '</span>';
		$h    .= ( $href ? '<a class="mem-wcard" href="' . esc_url( $href ) . '"' : '<div class="mem-wcard"' ) . ( $i >= $n ? ' hidden' : '' ) . '>' . $inner . ( $href ? '</a>' : '</div>' );
	}
	return $h . '</div>';
}

/*
Разово: галереи-карточки, сделанные вручную и ведущие на работы (ссылки «#rabota-…»), заменяются на
	[mem_works vid="карточки"] — теперь карточки собираются из «Наших работ» и не устаревают.
	Прежний текст страницы остаётся в её «Редакциях».
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'mem_wcards_migrated' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		update_option( 'mem_wcards_migrated', 1, false );
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
			)
		);
		$done  = array();
		foreach ( $pages as $p ) {
			if ( strpos( $p->post_content, '#rabota-' ) === false ) {
				continue;
			}
			$c = preg_replace_callback(
				'~<!-- wp:gallery\b[^>]*-->.*?<!-- /wp:gallery -->~s',
				function ( $m ) {
					if ( strpos( $m[0], '#rabota-' ) === false ) {
						return $m[0];
					}
					$n = max( 1, substr_count( $m[0], '<!-- wp:image' ) );
					return "<!-- wp:shortcode -->\n[mem_works vid=\"карточки\" chislo=\"$n\" poryadok=\"случайно\"]\n<!-- /wp:shortcode -->";
				},
				$p->post_content
			);
			if ( null !== $c && $c !== $p->post_content ) {
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
			set_transient( 'mem_wcards_report', $done, DAY_IN_SECONDS );
		}
	}
);
add_action(
	'admin_notices',
	function () {
		$done = get_transient( 'mem_wcards_report' );
		if ( ! $done || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		delete_transient( 'mem_wcards_report' );
		echo ( '<div class="notice notice-success is-dismissible"><p><b>' . esc_html__( 'Examples of works:', 'memorial-plugin' ) . '</b> ' . esc_html__( 'the cards on the pages “', 'memorial-plugin' ) ) . esc_html( implode( '», «', $done ) )
		. ( esc_html__( '” are now built automatically from “Our works” (the [mem_works vid="карточки"] shortcode). Which works to show — tick “In examples” in “Our works” (the “⋯” menu of a work); without ticks random ones of all are shown. The previous version is in the page’s “Revisions”.', 'memorial-plugin' ) . '</p></div>' );
	}
);
