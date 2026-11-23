<?php
/**
 * Разметка услуг Schema.org (Service) на страницах услуг.
 *
 * Включается в редакторе страницы: панель «Разметка услуги» (мета _mem_ld_service, _mem_ld_service_type,
 * _mem_ld_service_price). Название, описание и фото — со страницы, исполнитель — организация с главной
 * (ссылка по @id, seo/schema.php). Цена — только из «Мемориал → Цены» и только если она одна для всех:
 * пакеты похорон (от и до), пакет кремации, отдельная цена по коду; иначе цены в разметке нет
 * (траурный зал: бесплатно только для клиентов, отдельной цены аренды нет — решение владельца 23.11.2026).
 * На странице, у которой есть дочерние страницы услуг, — список этих услуг (ItemList со ссылками).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Price sources for the editor: value => label.
 *
 * @return array<string,string>
 */
function memorial_plugin_service_price_choices() {
	$out = array(
		''          => __( 'No price in the markup', 'memorial-plugin' ),
		'packages'  => __( 'Funeral packages: from the cheapest to the dearest', 'memorial-plugin' ),
		'kremaciya' => __( 'Cremation package', 'memorial-plugin' ),
	);
	foreach ( memorial_plugin_packages() as $p ) {
		if ( '' !== $p['id'] && '' !== trim( $p['name'] ) ) {
			/* translators: %s: package name. */
			$out[ 'pkg:' . $p['id'] ] = sprintf( __( 'Package “%s”', 'memorial-plugin' ), $p['name'] );
		}
	}
	foreach ( memorial_plugin_prices( 'other' )['items'] ?? array() as $it ) {
		$code = memorial_plugin_value_key( (string) ( $it['code'] ?? '' ) );
		if ( '' !== $code ) {
			/* translators: 1: price name, 2: price now. */
			$out[ 'value:' . $code ] = sprintf( __( 'Separate price: %1$s (%2$s)', 'memorial-plugin' ), '' !== trim( (string) ( $it['name'] ?? '' ) ) ? $it['name'] : $code, memorial_plugin_value_by_key( $code ) );
		}
	}
	return $out;
}

/**
 * Sanitize a price source.
 *
 * @param mixed $v Value.
 * @return string
 */
function memorial_plugin_service_price_clean( $v ) {
	$v = trim( (string) $v );
	return preg_match( '/^(packages|kremaciya|pkg:[\w-]+|value:[a-z0-9_]+)$/', $v ) ? $v : '';
}

/**
 * Number of a price text: «от 12 300 ₽» → ['min', 12300], «200–300 ₽» → ['range', 200, 300], «500 ₽» → ['exact', 500].
 *
 * @param string $text Price text.
 * @return array|null
 */
function memorial_plugin_service_price_parse( $text ) {
	$t   = str_replace( array( "\u{00A0}", "\u{202F}", ' ' ), '', (string) $text );
	$num = '(\d+(?:,\d{1,2})?)';
	$f   = static fn( $x ) => (float) str_replace( ',', '.', $x );
	if ( preg_match( '/^' . $num . '[–—-]' . $num . '(?:₽|руб\.?)?$/u', $t, $m ) ) {
		return array( 'range', $f( $m[1] ), $f( $m[2] ) );
	}
	if ( preg_match( '/^(?:от|from)' . $num . '(?:₽|руб\.?)?$/ui', $t, $m ) ) {
		return array( 'min', $f( $m[1] ) );
	}
	if ( preg_match( '/^' . $num . '(?:₽|руб\.?)?$/u', $t, $m ) ) {
		return array( 'exact', $f( $m[1] ) );
	}
	return null;
}

/**
 * Offer of a service by its price source, or null (no price — no offer).
 *
 * @param string $src Price source (memorial_plugin_service_price_choices()).
 * @param string $url Page of the service.
 * @return array|null
 */
function memorial_plugin_service_offer( $src, $url ) {
	$price = null;
	if ( 'packages' === $src ) {
		$crem   = mb_strtolower( trim( memorial_plugin_cremation_pkg()['name'] ) );
		$totals = array();
		foreach ( memorial_plugin_packages() as $p ) {
			if ( ! $p['show'] || ( '' !== $crem && mb_strtolower( trim( $p['name'] ) ) === $crem ) ) {
				continue;
			}
			$sum = array_sum( array_column( $p['items'], 1 ) );
			if ( $sum > 0 ) {
				$totals[] = $sum;
			}
		}
		if ( count( $totals ) > 1 ) {
			$price = array( 'range', min( $totals ), max( $totals ), count( $totals ) );
		} elseif ( $totals ) {
			$price = array( 'exact', $totals[0] );
		}
	} elseif ( 'kremaciya' === $src ) {
		$sum   = memorial_plugin_cremation_total();
		$price = $sum > 0 ? array( 'min', $sum ) : null;
	} elseif ( str_starts_with( $src, 'pkg:' ) ) {
		$p     = memorial_plugin_package_by_id( substr( $src, 4 ) );
		$sum   = $p ? array_sum( array_map( fn( $r ) => (int) ( $r['price'] ?? 0 ), (array) ( $p['rows'] ?? array() ) ) ) : 0;
		$price = $sum > 0 ? array( 'exact', $sum ) : null;
	} elseif ( str_starts_with( $src, 'value:' ) ) {
		$price = memorial_plugin_service_price_parse( memorial_plugin_value_by_key( substr( $src, 6 ) ) );
	}
	if ( ! $price || $price[1] <= 0 ) {
		return null;
	}
	if ( 'range' === $price[0] ) {
		return array_filter(
			array(
				'@type'         => 'AggregateOffer',
				'lowPrice'      => $price[1],
				'highPrice'     => $price[2],
				'offerCount'    => $price[3] ?? null,
				'priceCurrency' => 'RUB',
				'url'           => $url,
			)
		);
	}
	$offer = array(
		'@type'         => 'Offer',
		'price'         => $price[1],
		'priceCurrency' => 'RUB',
		'url'           => $url,
	);
	if ( 'min' === $price[0] ) {
		// «от …»: цена — нижняя граница.
		$offer['priceSpecification'] = array(
			'@type'         => 'PriceSpecification',
			'price'         => $price[1],
			'minPrice'      => $price[1],
			'priceCurrency' => 'RUB',
		);
	}
	return $offer;
}

/**
 * Description of a page for the markup: the SEO description, else the excerpt, else the start of the text.
 *
 * @param int $id Page.
 * @return string
 */
function memorial_plugin_service_desc( $id ) {
	$d = trim( (string) get_post_meta( $id, '_seopress_titles_desc', true ) );
	if ( '' !== $d && ! str_contains( $d, '%%' ) ) {
		return $d;
	}
	if ( has_excerpt( $id ) ) {
		return trim( wp_strip_all_tags( get_the_excerpt( $id ) ) );
	}
	if ( preg_match( '~<p[^>]*>(.*?)</p>~su', (string) get_post_field( 'post_content', $id ), $m ) ) {
		return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $m[1] ) ), 40, '…' );
	}
	return '';
}

/**
 * Photo of a page: the featured image, else the first image of the text.
 *
 * @param int $id Page.
 * @return string URL or ''.
 */
function memorial_plugin_page_image( $id ) {
	$img = get_the_post_thumbnail_url( $id, 'large' );
	if ( $img ) {
		return $img;
	}
	if ( preg_match( '/wp-image-(\d+)/', (string) get_post_field( 'post_content', $id ), $m ) ) {
		$u = wp_get_attachment_image_url( (int) $m[1], 'large' );
		if ( $u ) {
			return $u;
		}
	}
	return '';
}

/**
 * Service markup of a page, or null when the page is not marked as a service.
 *
 * @param int $id Page.
 * @return array|null
 */
function memorial_plugin_service_ld( $id ) {
	if ( '1' !== (string) get_post_meta( $id, '_mem_ld_service', true ) ) {
		return null;
	}
	$url  = (string) get_permalink( $id );
	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Service',
		'@id'      => $url . '#service',
		'name'     => wp_strip_all_tags( get_the_title( $id ) ),
		'url'      => $url,
	);
	$type = trim( (string) get_post_meta( $id, '_mem_ld_service_type', true ) );
	if ( '' !== $type ) {
		$data['serviceType'] = $type;
	}
	$desc = memorial_plugin_service_desc( $id );
	if ( '' !== $desc ) {
		$data['description'] = $desc;
	}
	$img = memorial_plugin_page_image( $id );
	if ( $img ) {
		$data['image'] = $img;
	}
	$data['provider']   = memorial_plugin_schema_org_ref();
	$data['areaServed'] = 'Белгород';
	$offer              = memorial_plugin_service_offer( (string) get_post_meta( $id, '_mem_ld_service_price', true ), $url );
	if ( $offer ) {
		$data['offers'] = $offer;
	}
	return apply_filters( 'memorial_plugin_service_ld', $data, $id );
}

add_action(
	'wp_head',
	static function () {
		if ( ! is_page() || is_front_page() ) {
			return;
		}
		$id = get_queried_object_id();
		$ld = memorial_plugin_service_ld( $id );
		if ( $ld ) {
			memorial_plugin_ld( $ld );
		}
		// Список услуг на странице, у которой они дочерние (например, «Ритуальные услуги»).
		$kids = get_pages(
			array(
				'parent'      => $id,
				'post_status' => 'publish',
				'sort_column' => 'menu_order,post_title',
				'meta_key'    => '_mem_ld_service', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a few pages.
				'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- a few pages.
			)
		);
		if ( count( $kids ) < 2 ) {
			return;
		}
		$list = array();
		foreach ( $kids as $i => $k ) {
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'url'      => get_permalink( $k ),
				'name'     => wp_strip_all_tags( get_the_title( $k ) ),
			);
		}
		memorial_plugin_ld(
			array(
				'@context'        => 'https://schema.org',
				'@type'           => 'ItemList',
				'name'            => wp_strip_all_tags( get_the_title( $id ) ),
				'numberOfItems'   => count( $list ),
				'itemListElement' => $list,
			)
		);
	},
	8
);
