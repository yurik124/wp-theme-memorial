<?php
/**
 * Main menu of the site: the Navigation menu (wp_navigation) shown in the header.
 *
 * The Navigation block of the header has no fixed menu in the pattern (the theme cannot know its ID):
 * on the site it shows the menu chosen here — the option memorial_theme_main_nav, otherwise the most recent
 * published Navigation menu, which is also what the Site Editor shows for a block without a menu.
 * Memorial Plugin builds the footer columns, the breadcrumbs and the order of “Questions and answers” from
 * the same menu (filter memorial_plugin_main_nav_id), so one edit in Appearance → Editor → Navigation
 * changes all of them.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * ID of the main Navigation menu, 0 if there is none.
 *
 * @return int
 */
function memorial_theme_main_nav_id() {
	static $id = null;
	if ( null !== $id ) {
		return $id;
	}
	$id = (int) get_option( 'memorial_theme_main_nav' );
	if ( $id && 'publish' === get_post_status( $id ) && 'wp_navigation' === get_post_type( $id ) ) {
		return $id;
	}
	$latest = get_posts(
		array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$id = (int) ( $latest[0] ?? 0 );
	return $id;
}
add_filter( 'memorial_plugin_main_nav_id', fn( $id ) => $id ? $id : memorial_theme_main_nav_id() );

/**
 * The Navigation block of the header without a chosen menu shows the main menu.
 *
 * @param array $block Parsed block.
 * @return array
 */
function memorial_theme_nav_default_ref( $block ) {
	if ( 'core/navigation' !== ( $block['blockName'] ?? '' ) || ! empty( $block['attrs']['ref'] ) ) {
		return $block;
	}
	if ( ! preg_match( '/(^|\s)nav(\s|$)/', (string) ( $block['attrs']['className'] ?? '' ) ) ) {
		return $block;
	}
	$id = memorial_theme_main_nav_id();
	if ( $id ) {
		$block['attrs']['ref'] = $id;
	}
	return $block;
}
add_filter( 'render_block_data', 'memorial_theme_nav_default_ref' );

/**
 * Список пунктов меню без классов самого блока.
 *
 * WordPress 7.1 повторяет у списка пунктов (ul.wp-block-navigation__container) классы блока — «nav»
 * и «wp-block-navigation». Стили шапки написаны для блока: на телефоне закрытое меню прячется, и вместе
 * с блоком прятался и список — бургер открывал пустую полосу. Убираем повтор на сервере, до отрисовки.
 *
 * @param string $html  HTML блока.
 * @param array  $block Блок.
 * @return string
 */
function memorial_theme_nav_list_classes( $html, $block ) {
	if ( 'core/navigation' !== ( $block['blockName'] ?? '' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	// The main menu of the header gets a translated name for screen readers (the pattern has no fixed ariaLabel).
	if ( empty( $block['attrs']['ariaLabel'] ) && preg_match( '/(^|\s)nav(\s|$)/', (string) ( $block['attrs']['className'] ?? '' ) ) && $p->next_tag( array( 'tag_name' => 'NAV' ) ) ) {
		$p->set_attribute( 'aria-label', __( 'Main menu', 'memorial-theme' ) );
	}
	while ( $p->next_tag( array( 'tag_name' => 'UL' ) ) ) {
		if ( $p->has_class( 'wp-block-navigation__container' ) ) {
			$p->remove_class( 'nav' );
			$p->remove_class( 'wp-block-navigation' );
		}
	}
	$html = $p->get_updated_html();
	// Меню по умолчанию (список страниц) вкладывает свой ul прямо в список пунктов: ul > ul — ошибка
	// доступности (в списке — только li). Снимаем обёртку, пункты остаются li основного списка.
	return (string) preg_replace( '#<ul class="wp-block-page-list">(.*)</ul>(\s*</ul>)#s', '$1$2', $html, 1 );
}
add_filter( 'render_block', 'memorial_theme_nav_list_classes', 10, 2 );
