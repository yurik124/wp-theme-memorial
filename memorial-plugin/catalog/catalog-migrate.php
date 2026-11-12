<?php
/**
 * Разовый перенос: таблица моделей на странице каталога (у крестов — «Сравнение моделей»)
 * → характеристики и цены в карточках товаров. Таблица и заголовок над ней убираются со страницы
 * (прежний вариант остаётся в «Редакциях»). Срабатывает один раз при входе администратора в админку.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Название модели для сравнения: без кавычек, пробелов и регистра
 *
 * @param array $s Section data.
 */
function memorial_plugin_norm_name( $s ) {
	$s = mb_strtolower( html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ) );
	return preg_replace( '/[\s«»"\'„“”‹›\-–—.,№#]+/u', '', $s );
}

/** Таблицы страницы: [индекс блока => [шапка, строки]]
 *
 * @param string $content Content.
 */
function memorial_plugin_page_tables( $content ) {
	$out  = array();
	$walk = function ( $blocks, $path ) use ( &$walk, &$out ) {
		foreach ( $blocks as $i => $b ) {
			$p = array_merge( $path, array( $i ) );
			if ( 'core/table' === $b['blockName'] && preg_match( '~<thead>(.*?)</thead>~su', $b['innerHTML'], $th ) ) {
				preg_match_all( '~<th(?:\s[^>]*)?>(.*?)</th>~su', $th[1], $h );
				$rows = array();
				if ( preg_match( '~<tbody>(.*?)</tbody>~su', $b['innerHTML'], $tb ) ) {
					preg_match_all( '~<tr[^>]*>(.*?)</tr>~su', $tb[1], $trs );
					foreach ( $trs[1] as $tr ) {
						preg_match_all( '~<td(?:\s[^>]*)?>(.*?)</td>~su', $tr, $td );
						$rows[] = array_map( fn( $c ) => trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $c ), ENT_QUOTES, 'UTF-8' ) ) ), $td[1] );
					}
				}
				$heads = array_map( fn( $c ) => trim( html_entity_decode( wp_strip_all_tags( $c ), ENT_QUOTES, 'UTF-8' ) ), $h[1] );
				if ( count( $heads ) >= 2 && $rows ) {
					$out[ implode( '.', $p ) ] = array(
						'head' => $heads,
						'rows' => $rows,
					);
				}
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'], $p );
			}
		}
	};
	$walk( parse_blocks( $content ), array() );
	return $out;
}

/** Товары категории страницы (с подкатегориями), по нормализованному названию
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_page_products( $page_id ) {
	$cat = (int) get_post_meta( $page_id, '_mem_cat', true );
	if ( ! $cat ) {
		return array();
	}
	$map = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy'         => 'mem_cat',
					'terms'            => $cat,
					'include_children' => true,
				),
			),
		)
	) as $p ) {
		$map[ memorial_plugin_norm_name( $p->post_title ) ] = $p;
	}
	return $map;
}

/** Строка таблицы → [товар, подпись, цена, «от»]
 *
 * @param string $t Text.
 * @param mixed  $products Products.
 */
function memorial_plugin_specs_match( $t, $products ) {
	$head = $t['head'];
	$pi   = null;
	foreach ( $head as $i => $h ) {
		if ( $i && preg_match( '/цена|стоимост/ui', $h ) ) {
			$pi = $i;
		}
	}
	$res = array();
	foreach ( $t['rows'] as $r ) {
		$name = $r[0] ?? '';
		$n    = memorial_plugin_norm_name( $name );
		$prod = $products[ $n ] ?? null;
		if ( ! $prod && '' !== $n ) { // «М6» ↔ «Крест М6»
			foreach ( $products as $k => $p ) {
				if ( preg_match( '/(^|[^a-zа-яё0-9])' . preg_quote( $n, '/' ) . '$/u', $k ) || str_ends_with( $k, $n ) ) {
					$prod = $p;
					break; }
			}
		}
		$parts = array();
		foreach ( $r as $i => $v ) {
			if ( ! $i || $i === $pi || '' === $v || '—' === $v ) {
				continue;
			}
			$parts[] = trim( $head[ $i ] ?? '' ) . ': ' . $v;
		}
		$price = 0;
		$from  = false;
		if ( null !== $pi && isset( $r[ $pi ] ) && preg_match( '/(\d[\d\s\x{00A0}\x{202F}]*)/u', $r[ $pi ], $m ) ) {
			$price = (int) preg_replace( '/\D/', '', $m[1] );
			$from  = (bool) preg_match( '/^\s*от/ui', $r[ $pi ] );
		}
		$res[] = array(
			'name'  => $name,
			'prod'  => $prod,
			'specs' => implode( "\n", $parts ),
			'price' => $price,
			'from'  => $from,
		);
	}
	return $res;
}


add_action(
	'admin_init',
	function () {
		if ( get_option( 'mem_specs_migrated' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'mem_specs_migrated', 1, false );
		$report = array();
		foreach ( get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'meta_key'       => '_mem_cat',
			)
		) as $pg ) {
			$products = memorial_plugin_page_products( $pg->ID );
			if ( ! $products ) {
				continue;
			}
			foreach ( memorial_plugin_page_tables( $pg->post_content ) as $key => $t ) {
				$m         = memorial_plugin_specs_match( $t, $products );
				$found     = array_filter( $m, fn( $x ) => $x['prod'] );
				$has_price = (bool) array_filter( $m, fn( $x ) => $x['price'] );
				if ( count( $found ) < max( 2, count( $m ) / 2 ) || ! $has_price ) {
					continue; // не таблица моделей.
				}
				foreach ( $found as $x ) {
					$id = $x['prod']->ID;
					if ( '' !== $x['specs'] && ! get_post_meta( $id, '_mem_specs', true ) ) {
						update_post_meta( $id, '_mem_specs', $x['specs'] );
					}
					if ( $x['price'] && ! (int) get_post_meta( $id, '_mem_price', true ) ) {
						update_post_meta( $id, '_mem_price', $x['price'] );
						if ( $x['from'] ) {
							update_post_meta( $id, '_mem_price_from', '1' );
						}
					}
				}
				// убираем таблицу и заголовок прямо над ней.
				$blocks = parse_blocks( $pg->post_content );
				$path   = array_map( 'intval', explode( '.', $key ) );
				$ref    = &$blocks;
				$last   = count( $path ) - 1;
				for ( $i = 0; $i < $last; $i++ ) {
					$ref = &$ref[ $path[ $i ] ]['innerBlocks'];
				}
				$last = end( $path );
				$prev = $last - 1;
				while ( $prev >= 0 && null === $ref[ $prev ]['blockName'] && '' === trim( $ref[ $prev ]['innerHTML'] ) ) {
					$prev--;
				}
				$empty        = array(
					'blockName'    => null,
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				);
				$ref[ $last ] = $empty;
				if ( $prev >= 0 && 'core/heading' === $ref[ $prev ]['blockName'] ) {
					$ref[ $prev ] = $empty;
				}
				unset( $ref );
				wp_update_post(
					array(
						'ID'           => $pg->ID,
						'post_content' => wp_slash( serialize_blocks( $blocks ) ),
					)
				);
				$report[] = '«' . $pg->post_title . __( '»: specifications and prices of ', 'memorial-plugin' ) . count( $found ) . __( ' models were moved to the cards, the table was removed', 'memorial-plugin' );
				break; // одна таблица на страницу.
			}
		}
		if ( $report ) {
			set_transient( 'mem_specs_report', implode( '; ', $report ) . __( '. The previous text of the page is in its “Revisions”.', 'memorial-plugin' ), DAY_IN_SECONDS );
		}
	}
);

add_action(
	'admin_notices',
	function () {
		$r = get_transient( 'mem_specs_report' );
		if ( ! $r || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_transient( 'mem_specs_report' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $r ) . '</p></div>';
	}
);
