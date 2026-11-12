<?php
/**
 * Страница с товарами (поле «Товары на странице»): текст до «Далее» — над товарами, после — под товарами
 * (без «Далее» весь текст внизу); тексты — только на первой странице списка. Как прежний page.php темы.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$id   = memorial_plugin_legacy_post_id( $block );
$mode = get_post_meta( $id, '_mem_products', true );
if ( ! $mode ) {
	return;
}
$parts  = get_extended( (string) get_post_field( 'post_content', $id ) );
$top    = '' !== $parts['extended'] ? $parts['main'] : '';
$bottom = '' !== $parts['extended'] ? $parts['extended'] : $parts['main'];
$fmt    = fn( $html ) => trim( $html ) === '' ? '' : apply_filters( 'the_content', $html );
$first  = max( 1, (int) get_query_var( 'paged' ) ) === 1;

$t = $first ? $fmt( $top ) : '';
if ( $t ) {
	echo '<div class="cat-desc content">' . $t . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- term description passed through the_content.
}

$kids = 'all' === $mode ? memorial_plugin_menu_children( $id ) : array();
if ( $kids ) {
	memorial_plugin_page_tiles( $kids ); // плитки = подпункты этой страницы в главном меню.
} elseif ( 'all' === $mode ) {
	$cats = get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'parent'     => 0,
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $cats ) && $cats ) {
		memorial_plugin_cat_tiles( $cats );
	}
} else {
	memorial_plugin_products_grid( (int) $mode, get_permalink( $id ) );
}

$b = $first ? $fmt( $bottom ) : '';
if ( $b ) {
	echo '<div class="content catalog__text">' . $b . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- term description passed through the_content.
}
