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
		$items = array();
		foreach ( $p['rows'] ?? array() as $r ) {
			if ( trim( $r['name'] ?? '' ) === '' ) {
				continue;
			}
			$items[] = array( $r['name'], (int) ( $r['price'] ?? 0 ), ! empty( $r['key'] ) );
		}
		$out[] = array(
			'name'     => $p['name'] ?? '',
			'featured' => ! empty( $p['featured'] ),
			'items'    => $items,
		);
	}
	return apply_filters( 'memorial_plugin_packages', $out );
}

/**
 * Rub.
 *
 * @param int $n Number.
 */
function memorial_plugin_rub( $n ) {
	return number_format( $n, 0, '', "\u{202F}" ) . "\u{00A0}₽";
}
