<?php
/**
 * Data.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Пакеты услуг — редактируются в «Мемориал → Главная страница → Пакеты услуг».
 */
function memorial_plugin_packages() {
	$out = array();
	foreach ( memorial_plugin_prices( 'packages' )['items'] ?? array() as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$items = array();
		foreach ( $p['rows'] ?? array() as $r ) {
			if ( trim( $r['name'] ?? '' ) === '' ) {
				continue;
			}
			$items[] = array( $r['name'], (int) ( $r['price'] ?? 0 ), ! empty( $r['key'] ) );
		}
		$out[] = array(
			'id'        => (string) ( $p['_id'] ?? '' ),
			'name'      => $p['name'] ?? '',
			'featured'  => ! empty( $p['featured'] ),
			// Saved before the mark appeared — shown.
			'show'      => ! array_key_exists( 'show', $p ) || ! empty( $p['show'] ),
			'items'     => $items,
			'total'     => trim( (string) ( $p['total'] ?? '' ) ),
			'link_text' => trim( (string) ( $p['link_text'] ?? '' ) ),
			'link'      => trim( (string) ( $p['link'] ?? '' ) ),
		);
	}
	return apply_filters( 'memorial_plugin_packages', $out );
}

/**
 * Packages marked “Show on the site” that have contents.
 *
 * @return array
 */
function memorial_plugin_packages_shown() {
	return array_values( array_filter( memorial_plugin_packages(), static fn( $p ) => $p['show'] && $p['items'] ) );
}

/**
 * Rub.
 *
 * @param int $n Number.
 */
function memorial_plugin_rub( $n ) {
	return number_format( $n, 0, '', "\u{202F}" ) . "\u{00A0}₽";
}
