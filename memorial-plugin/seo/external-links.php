<?php
/**
 * Links to other sites (owner, 26.11.2026): open in a new tab and carry rel="nofollow".
 *
 * Switched in «Мемориал → Настройки → Функции → Ссылки на другие сайты» (mem_modules_page['links']).
 * Done when a page is shown (the filter render_block), the texts in the base are not changed.
 * Not touched: links of the site itself (also with www.), tel:, mailto:, anchors, photos opened by
 * the viewer of the site (data-lb, data-lb-item, data-pv). Words “sponsored” and “ugc” already in rel stay.
 * A screen reader hears “opens in a new tab” (aria-describedby → one hidden text at the end of the page).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings of the links: on, and the domains that keep a normal (followed) link.
 *
 * @return array{on:bool,keep:string[]}
 */
function memorial_plugin_links_settings() {
	$s    = (array) ( memorial_plugin_settings( 'mem_modules_page' )['links'] ?? array() );
	$keep = preg_split( '~[\s,;]+~', strtolower( (string) ( $s['keep'] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY );
	$keep = array_values(
		array_filter(
			array_map(
				static fn( $d ) => preg_replace( '~^www\.~', '', (string) ( wp_parse_url( str_contains( $d, '//' ) ? $d : '//' . $d, PHP_URL_HOST ) ?? '' ) ),
				$keep
			)
		)
	);
	return array(
		'on'   => ! isset( $s['on'] ) || ! empty( $s['on'] ),
		'keep' => $keep,
	);
}

/**
 * Host without “www.”.
 *
 * @param string $host Host.
 * @return string
 */
function memorial_plugin_links_host( $host ) {
	return preg_replace( '~^www\.~', '', strtolower( (string) $host ) );
}

/**
 * Is the host one of the list (the domain itself or its subdomain)?
 *
 * @param string   $host Host without www.
 * @param string[] $list Domains.
 * @return bool
 */
function memorial_plugin_links_host_in( $host, $list ) {
	foreach ( $list as $d ) {
		if ( $host === $d || str_ends_with( $host, '.' . $d ) ) {
			return true;
		}
	}
	return false;
}

/**
 * External links of an HTML piece: target="_blank", rel="noopener nofollow" (nofollow — not for the kept domains).
 *
 * @param string        $html HTML.
 * @param array|null    $set  Settings (memorial_plugin_links_settings()); null — the saved ones.
 * @param string|null   $own  Host of the site; null — home_url().
 * @return string
 */
function memorial_plugin_links_mark( $html, $set = null, $own = null ) {
	if ( ! is_string( $html ) || false === stripos( $html, '<a' ) || ! preg_match( '~href\s*=\s*["\']?(https?:)?//~i', $html ) ) {
		return $html;
	}
	$set = $set ?? memorial_plugin_links_settings();
	$own = memorial_plugin_links_host( $own ?? (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$p   = new WP_HTML_Tag_Processor( $html );
	$hit = false;
	while ( $p->next_tag( 'A' ) ) {
		$href = trim( (string) $p->get_attribute( 'href' ) );
		if ( ! preg_match( '~^(https?:)?//~i', $href ) || null !== $p->get_attribute( 'data-lb' ) || null !== $p->get_attribute( 'data-lb-item' ) || null !== $p->get_attribute( 'data-pv' ) ) {
			continue;
		}
		$host = memorial_plugin_links_host( (string) wp_parse_url( $href, PHP_URL_HOST ) );
		if ( '' === $host || $host === $own ) {
			continue;
		}
		$rel = preg_split( '~\s+~', strtolower( trim( (string) $p->get_attribute( 'rel' ) ) ), -1, PREG_SPLIT_NO_EMPTY );
		$rel = array_merge( $rel, array( 'noopener' ) );
		if ( ! memorial_plugin_links_host_in( $host, $set['keep'] ) ) {
			$rel[] = 'nofollow';
		}
		$p->set_attribute( 'rel', implode( ' ', array_unique( $rel ) ) );
		$p->set_attribute( 'target', '_blank' );
		if ( null === $p->get_attribute( 'aria-describedby' ) ) {
			$p->set_attribute( 'aria-describedby', 'mem-newtab' );
		}
		$hit = true;
	}
	if ( $hit ) {
		$GLOBALS['memorial_plugin_links_used'] = true;
	}
	return $p->get_updated_html();
}

add_filter(
	'render_block',
	static function ( $html ) {
		if ( is_admin() || wp_is_serving_rest_request() || is_feed() || ! memorial_plugin_links_settings()['on'] ) {
			return $html;
		}
		return memorial_plugin_links_mark( $html );
	},
	20
);

/* Текст для диктора «откроется в новой вкладке» — один на страницу, если есть внешние ссылки. */
add_action(
	'wp_footer',
	static function () {
		if ( ! empty( $GLOBALS['memorial_plugin_links_used'] ) ) {
			echo '<span id="mem-newtab" hidden>' . esc_html__( 'opens in a new tab', 'memorial-plugin' ) . '</span>';
		}
	},
	99
);
