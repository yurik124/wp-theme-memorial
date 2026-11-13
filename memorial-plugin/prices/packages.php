<?php
/**
 * Карточки пакетов (похороны и кремация — одним списком): главная и блок «Пакеты похорон» на любой
 * странице выглядят одинаково. Показываются пакеты с отметкой «Показывать на сайте»; число колонок на
 * компьютере — «Цены → Пакеты похорон → Колонок на компьютере», неполный ряд стоит по центру.
 * Ключевые позиции («На карточке») — списком с лавровыми листками, весь состав с ценами — свёрнут.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where the link of a package leads: a page of the site (its ID) or an address as typed.
 * A link to the page the card stands on is not shown.
 *
 * @param string $link Page ID or address.
 * @return string URL or ''.
 */
function memorial_plugin_package_link_url( $link ) {
	$link = trim( (string) $link );
	if ( '' === $link ) {
		return '';
	}
	if ( ctype_digit( $link ) ) {
		$id = (int) $link;
		return $id && 'publish' === get_post_status( $id ) && get_the_ID() !== $id ? (string) get_permalink( $id ) : '';
	}
	return $link;
}

/**
 * One package card.
 *
 * @param array  $p    Package (memorial_plugin_packages()).
 * @param array  $s    Settings of the packages group (badge, button text).
 * @param string $tel  Phone for the button.
 * @return string
 */
function memorial_plugin_package_card_html( $p, $s, $tel ) {
	$feat  = ! empty( $p['featured'] );
	$total = array_sum( array_column( $p['items'], 1 ) );
	$keys  = array_filter( $p['items'], fn( $it ) => ! empty( $it[2] ) );
	// ничего не отмечено в настройках — берём главное по названию, иначе первые четыре.
	if ( ! $keys ) {
		$keys = array_filter( $p['items'], fn( $it ) => preg_match( '/^(Гроб|Крест|Катафалк|Венок|Услуги по захоронению)/u', $it[0] ) );
	}
	if ( ! $keys ) {
		$keys = array_slice( $p['items'], 0, 4 );
	}
	$mark = memorial_plugin_laurel( 'leaf' );
	$mark = '' !== $mark ? $mark : memorial_plugin_icon( 'check' );
	$div  = memorial_plugin_laurel( 'divider' );
	$url  = memorial_plugin_package_link_url( $p['link'] ?? '' );

	$h = '<article class="pkg' . ( $feat ? ' pkg--feat' : '' ) . ' reveal">';
	if ( $feat && ! empty( $s['badge'] ) ) {
		$h .= '<span class="pkg__badge">' . memorial_plugin_esc_t( $s['badge'] ) . '</span>';
	}
	$h .= '<h3>' . esc_html( $p['name'] ) . '</h3>';
	if ( $total ) {
		$h .= '<p class="pkg__total">' . esc_html( memorial_plugin_rub( $total ) ) . '</p>';
	}
	if ( $div ) {
		$h .= '<span class="pkg__div" aria-hidden="true">' . $div . '</span>';
	}
	$h .= '<ul class="pkg__key">';
	foreach ( $keys as $it ) {
		$h .= '<li>' . $mark . '<span>' . esc_html( $it[0] ) . '</span></li>';
	}
	// The list has its own class: the theme draws laurel leaves on lists without one (text of a page).
	$h .= '</ul><details class="pkg__list"><summary>' . esc_html__( 'The full contents and prices', 'memorial-plugin' ) . ' (' . count( $p['items'] ) . ')</summary><ul class="pkg__rows">';
	foreach ( $p['items'] as $it ) {
		$h .= '<li><span>' . esc_html( $it[0] ) . '</span>' . ( $it[1] ? '<b>' . esc_html( memorial_plugin_rub( $it[1] ) ) . '</b>' : '' ) . '</li>';
	}
	$h .= '</ul></details>';
	if ( '' !== ( $p['total'] ?? '' ) ) {
		$h .= '<p class="pkg__note">' . esc_html( $p['total'] ) . '</p>';
	}
	if ( $url ) {
		$text = '' !== ( $p['link_text'] ?? '' ) ? preg_replace( '/\s*(→|->)\s*$/u', '', $p['link_text'] ) : __( 'More details', 'memorial-plugin' );
		$h   .= '<a class="link-more pkg__more" href="' . esc_url( $url ) . '">' . esc_html( $text ) . ' <span aria-hidden="true">→</span></a>';
	}
	if ( $tel ) {
		$h .= '<a class="btn ' . ( $feat ? 'btn--gold' : 'btn--line' ) . '" href="tel:' . esc_attr( $tel ) . '">' . memorial_plugin_esc_t( ! empty( $s['btn'] ) ? $s['btn'] : __( 'Order', 'memorial-plugin' ) ) . '</a>';
	}
	return $h . '</article>';
}

/**
 * All package cards — the same markup on the home page and in the block on any page.
 *
 * @return string
 */
function memorial_plugin_packages_markup() {
	$pkgs = memorial_plugin_packages_shown();
	if ( ! $pkgs ) {
		return '';
	}
	$s    = memorial_plugin_prices( 'packages' );
	$cols = max( 1, min( 4, (int) ( $s['cols'] ?? 3 ) ) );
	$tel  = memorial_plugin_tel();
	$h    = '<div class="mem-pkgs"><div class="pkgs" style="--pkg-cols:' . $cols . '">';
	foreach ( $pkgs as $p ) {
		$h .= memorial_plugin_package_card_html( $p, $s, $tel );
	}
	return $h . '</div></div>';
}

/**
 * Packages html (prints).
 */
function memorial_plugin_packages_html() {
	echo memorial_plugin_packages_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_html()/esc_url()/esc_attr(); the laurel is static SVG.
}

add_shortcode(
	'mem_packages',
	// Old attribute cremation="…" is ignored: the cremation package is one of the packages now.
	static fn() => memorial_plugin_packages_markup()
);
