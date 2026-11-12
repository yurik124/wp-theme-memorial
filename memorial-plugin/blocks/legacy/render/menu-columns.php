<?php
/**
 * Меню в подвале колонками: пункты с подпунктами — колонки с заголовком, одиночные пункты — колонка «Информация».
 * Меню — область «footer», иначе «primary» (классические меню WordPress; на этапе 3 — навигация из блоков).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$locs = get_nav_menu_locations();
$menu = ! empty( $locs['footer'] ) ? $locs['footer'] : ( $locs['primary'] ?? 0 );
if ( ! $menu ) { // у блоковой темы областей меню нет — берём меню, которое было в подвале у прежней темы.
	$old  = (array) ( get_option( 'theme_mods_memorial_speshu' )['nav_menu_locations'] ?? array() );
	$menu = ! empty( $old['footer'] ) ? $old['footer'] : ( $old['primary'] ?? 0 );
}
$items = $menu ? wp_get_nav_menu_items( $menu ) : array();
if ( ! $items ) {
	return;
}
$top  = array();
$kids = array();
foreach ( $items as $it ) {
	if ( (int) $it->menu_item_parent ) {
		$kids[ (int) $it->menu_item_parent ][] = $it;
	} else {
		$top[] = $it;
	}
}
$link   = fn( $it ) => '<a href="' . esc_url( $it->url ) . '"' . ( $it->target ? ' target="' . esc_attr( $it->target ) . '" rel="noopener"' : '' ) . '>' . esc_html( $it->title ) . '</a>';
$cols   = '';
$single = '';
foreach ( $top as $it ) {
	if ( ! empty( $kids[ $it->ID ] ) ) {
		$head  = in_array( trim( $it->url ), array( '', '#' ), true ) ? esc_html( $it->title ) : $link( $it );
		$cols .= '<div class="fnav__col"><p class="fnav__title">' . $head . '</p><ul>';
		foreach ( $kids[ $it->ID ] as $k ) {
			$cols .= '<li>' . $link( $k ) . '</li>';
		}
		$cols .= '</ul></div>';
	} else {
		$single .= '<li>' . $link( $it ) . '</li>';
	}
}
if ( $single ) {
	$cols .= '<div class="fnav__col"><p class="fnav__title">' . esc_html__( 'Information', 'memorial-plugin' ) . '</p><ul>' . $single . '</ul></div>';
}
echo '<nav class="wrap fnav" aria-label="' . esc_attr__( 'Footer menu', 'memorial-plugin' ) . '">' . $cols . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- columns built above with esc_html()/esc_url().
