<?php
/**
 * «Хлебные крошки» и разметка Schema.org для поисковиков:
 * BreadcrumbList на всех внутренних страницах, Product на странице товара.
 * Видимые крошки и разметка строятся из одного списка — поэтому всегда совпадают.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Основная категория товара — самая глубокая из отмеченных
 *
 * @param int $id ID.
 */
function memorial_plugin_product_cat( $id ) {
	$cats = get_the_terms( $id, 'mem_cat' );
	if ( ! $cats || is_wp_error( $cats ) ) {
		return null;
	}
	usort( $cats, fn( $a, $b ) => count( get_ancestors( $b->term_id, 'mem_cat' ) ) <=> count( get_ancestors( $a->term_id, 'mem_cat' ) ) );
	return $cats[0];
}

/** Цепочка категорий от верхней до $term включительно
 *
 * @param WP_Term $term Term.
 */
function memorial_plugin_cat_chain( $term ) {
	$out = array();
	foreach ( array_reverse( get_ancestors( $term->term_id, 'mem_cat' ) ) as $a ) {
		$t = get_term( $a, 'mem_cat' );
		if ( $t && ! is_wp_error( $t ) ) {
			$out[] = array( memorial_plugin_term_name( $t ), get_term_link( $t ) );
		}
	}
	$out[] = array( memorial_plugin_term_name( $term ), get_term_link( $term ) );
	return $out;
}

/** Название категории: заголовок привязанной страницы, если она есть
 *
 * @param string $t Text.
 */
function memorial_plugin_term_name( $t ) {
	$pid = function_exists( 'memorial_plugin_term_page' ) ? memorial_plugin_term_page( $t->term_id ) : 0;
	return $pid ? get_the_title( $pid ) : $t->name;
}

/** Крошки страницы по главному меню (страница + её родительские пункты). null — страницы нет в меню.
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_page_crumbs( $page_id ) {
	$chain = function_exists( 'memorial_plugin_menu_chain' ) ? memorial_plugin_menu_chain( $page_id ) : null;
	if ( null === $chain ) {
		return null;
	}
	$chain[] = array( get_the_title( $page_id ), get_permalink( $page_id ) );
	return $chain;
}

/** Список крошек: [[название, ссылка], ...] */
function memorial_plugin_crumbs() {
	// In the editor the block is drawn by a REST request with the edited page as the global post:
	// the crumbs of that page, as on the site.
	$editing = defined( 'REST_REQUEST' ) && REST_REQUEST && get_post() instanceof WP_Post && 'page' === get_post_type();
	if ( $editing ) {
		if ( (int) get_option( 'page_on_front' ) === (int) get_the_ID() ) {
			return array();
		}
		$id    = (int) get_the_ID();
		$chain = memorial_plugin_page_crumbs( $id );
		$c     = array( array( __( 'Home', 'memorial-plugin' ), home_url( '/' ) ) );
		if ( $chain ) {
			return array_merge( $c, $chain );
		}
		foreach ( array_reverse( get_post_ancestors( $id ) ) as $a ) {
			$c[] = array( get_the_title( $a ), get_permalink( $a ) );
		}
		$c[] = array( get_the_title( $id ), get_permalink( $id ) );
		return $c;
	}
	if ( is_front_page() ) {
		return array();
	}
	$c       = array( array( __( 'Home', 'memorial-plugin' ), home_url( '/' ) ) );
	$root    = memorial_plugin_catalog_page();
	$catalog = array( $root ? get_the_title( $root ) : __( 'Catalog', 'memorial-plugin' ), memorial_plugin_catalog_url() );

	if ( is_post_type_archive( 'mem_product' ) ) {
		$c[] = $catalog;
	} elseif ( is_tax( 'mem_cat' ) ) {
		$c[] = $catalog;
		$c   = array_merge( $c, memorial_plugin_cat_chain( get_queried_object() ) );
	} elseif ( is_singular( 'mem_product' ) ) {
		$id    = get_queried_object_id();
		$cat   = memorial_plugin_product_cat( $id );
		$pid   = $cat ? memorial_plugin_term_page( $cat->term_id ) : 0;
		$chain = $pid ? memorial_plugin_page_crumbs( $pid ) : array();
		if ( $chain ) {
			$c = array_merge( $c, $chain ); // как в меню: Главная / Ритуальные товары / Венки / товар.
		} else {
			$c[] = $catalog;
			if ( $cat ) {
				$c = array_merge( $c, memorial_plugin_cat_chain( $cat ) );
			}
		}
		$c[] = array( get_the_title( $id ), get_permalink( $id ) );
	} elseif ( is_page() && memorial_plugin_page_crumbs( get_queried_object_id() ) ) {
		$c = array_merge( $c, memorial_plugin_page_crumbs( get_queried_object_id() ) );
	} elseif ( is_page() ) {
		$id  = get_queried_object_id();
		$anc = get_post_ancestors( $id );
		// страница с товарами показывается «внутри» каталога, даже если в WordPress она не дочерняя.
		$mode = get_post_meta( $id, '_mem_products', true );
		if ( $mode && 'all' !== $mode && $root && $root !== $id && ! in_array( $root, $anc, true ) ) {
			$c[] = $catalog;
			// родительские категории — тоже страницами.
			$t = get_term( (int) $mode, 'mem_cat' );
			if ( $t && ! is_wp_error( $t ) ) {
				foreach ( array_reverse( get_ancestors( $t->term_id, 'mem_cat' ) ) as $a ) {
					$at = get_term( $a, 'mem_cat' );
					if ( $at && ! is_wp_error( $at ) ) {
						$c[] = array( memorial_plugin_term_name( $at ), get_term_link( $at ) );
					}
				}
			}
		}
		foreach ( array_reverse( $anc ) as $a ) {
			$c[] = array( get_the_title( $a ), get_permalink( $a ) );
		}
		$c[] = array( get_the_title( $id ), get_permalink( $id ) );
	} elseif ( is_404() ) {
		$c[] = array( __( 'Page not found', 'memorial-plugin' ), '' );
	} elseif ( is_singular() ) {
		$c[] = array( get_the_title(), get_permalink() );
	} else {
		$c[] = array( wp_get_document_title(), '' );
	}
	return $c;
}

/**
 * Crumbs html.
 */
function memorial_plugin_crumbs_html() {
	$c = memorial_plugin_crumbs();
	if ( count( $c ) < 2 ) {
		return;
	}
	echo ( '<nav class="crumbs" aria-label="' . esc_html__( 'Breadcrumbs', 'memorial-plugin' ) . '">' );
	$last = count( $c ) - 1;
	foreach ( $c as $i => $item ) {
		if ( $i === $last ) {
			echo '<span aria-current="page">' . esc_html( $item[0] ) . '</span>';
		} else {
			echo '<a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a> / ';
		}
	}
	echo '</nav>';
}

/**
 * Ld.
 *
 * @param array $data Data.
 */
function memorial_plugin_ld( $data ) {
	wp_print_inline_script_tag( wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ), array( 'type' => 'application/ld+json' ) );
}

add_action(
	'wp_head',
	function () {
		if ( is_404() ) {
			return;
		}

		// Хлебные крошки.
		$c = memorial_plugin_crumbs();
		if ( count( $c ) >= 2 ) {
			$list = array();
			foreach ( $c as $i => $item ) {
				$el = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => wp_strip_all_tags( $item[0] ),
				);
				if ( $item[1] ) {
					$el['item'] = $item[1];
				}
				$list[] = $el;
			}
			memorial_plugin_ld(
				array(
					'@context'        => 'https://schema.org',
					'@type'           => 'BreadcrumbList',
					'itemListElement' => $list,
				)
			);
		}

		// Товар.
		if ( is_singular( 'mem_product' ) ) {
			$id   = get_queried_object_id();
			$post = get_post( $id );
			$desc = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40, '…' );
			$p    = array(
				'@context' => 'https://schema.org',
				'@type'    => 'Product',
				'name'     => get_the_title( $id ),
				'url'      => get_permalink( $id ),
			);
			if ( $desc ) {
				$p['description'] = $desc;
			}
			$img = get_the_post_thumbnail_url( $id, 'large' );
			if ( $img ) {
				$p['image'] = $img;
			}
			$cat = memorial_plugin_product_cat( $id );
			if ( $cat ) {
				$p['category'] = implode( ' > ', array_column( memorial_plugin_cat_chain( $cat ), 0 ) );
			}
			$p['brand'] = array(
				'@type' => 'Brand',
				'name'  => get_bloginfo( 'name' ),
			);

			// Характеристики (материал, цвет…).
			$props = array();
			foreach ( memorial_plugin_attrs() as $k => $label ) {
				$t = get_the_terms( $id, 'mem_' . $k );
				if ( $t && ! is_wp_error( $t ) ) {
					$props[] = array(
						'@type' => 'PropertyValue',
						'name'  => $label,
						'value' => implode( ', ', wp_list_pluck( $t, 'name' ) ),
					);
				}
			}
			if ( $props ) {
				$p['additionalProperty'] = $props;
			}

			// Цена: товары «по запросу» публикуем без предложения — цену поисковикам не выдумываем.
			$price = (int) get_post_meta( $id, '_mem_price', true );
			if ( $price > 0 ) {
				$avail       = get_post_meta( $id, '_mem_on_order', true ) ? 'https://schema.org/PreOrder' : 'https://schema.org/InStock';
				$seller      = array(
					'@type' => 'Organization',
					'name'  => get_bloginfo( 'name' ),
				);
				$p['offers'] = get_post_meta( $id, '_mem_price_from', true )
				? array(
					'@type'         => 'AggregateOffer',
					'lowPrice'      => $price,
					'priceCurrency' => 'RUB',
					'availability'  => $avail,
					'url'           => get_permalink( $id ),
					'seller'        => $seller,
				)
				: array(
					'@type'         => 'Offer',
					'price'         => $price,
					'priceCurrency' => 'RUB',
					'availability'  => $avail,
					'url'           => get_permalink( $id ),
					'seller'        => $seller,
				);
			}
			memorial_plugin_ld( $p );
		}
	},
	6
);

/*
All in One SEO выводит свои «хлебные крошки» по структуре страниц — убираем, чтобы в разметке
 * остались наши, совпадающие с видимыми на сайте (по меню). Остальная разметка AIOSEO не трогается.
 */
add_filter(
	'aioseo_schema_output',
	function ( $graphs ) {
		if ( ! is_array( $graphs ) ) {
			return $graphs;
		}
		return array_values( array_filter( $graphs, fn( $g ) => ( $g['@type'] ?? '' ) !== 'BreadcrumbList' ) );
	}
);
