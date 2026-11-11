<?php
/**
 * Меню → блок «Навигация».
 *
 * Один раз (при включении темы или в админке) главное меню сайта (область «primary» классических меню)
 * копируется в меню блоков (запись wp_navigation «Главное меню»). Блок «Навигация» в шапке без выбранного
 * меню показывает именно его. Классическое меню не удаляется: из него строятся колонки меню в подвале
 * (до этапа 3) и его можно вернуть при откате. Журнал — опция memorial_theme_nav_migrated.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Перенос главного меню в меню блоков.
 */
function memorial_theme_nav_migrate() {
	if ( get_option( 'memorial_theme_nav_migrated' ) || get_stylesheet() !== get_template() ) {
		return;
	}
	$log  = array(
		'time'   => time(),
		'result' => 'no-menu',
	);
	$locs = get_nav_menu_locations();
	if ( empty( $locs['primary'] ) ) {
		$old  = get_option( 'theme_mods_memorial_speshu' );
		$locs = (array) ( is_array( $old ) ? ( $old['nav_menu_locations'] ?? array() ) : array() );
	}
	$menu = isset( $locs['primary'] ) ? wp_get_nav_menu_object( (int) $locs['primary'] ) : false;

	if ( $menu && class_exists( 'WP_Classic_To_Block_Menu_Converter' ) ) {
		$content = WP_Classic_To_Block_Menu_Converter::convert( $menu );
		if ( is_string( $content ) && '' !== $content ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'wp_navigation',
					'post_status'  => 'publish',
					'post_title'   => 'Главное меню',
					'post_content' => $content,
				),
				true
			);
			if ( ! is_wp_error( $id ) ) {
				$log['result']  = 'migrated';
				$log['nav_id']  = (int) $id;
				$log['menu_id'] = (int) $menu->term_id;
			} else {
				$log['result'] = 'error: ' . $id->get_error_message();
			}
		}
	}
	update_option( 'memorial_theme_nav_migrated', $log, false );
}
add_action( 'after_switch_theme', 'memorial_theme_nav_migrate' );
add_action( 'admin_init', 'memorial_theme_nav_migrate' );

/**
 * Блок «Навигация» в шапке без выбранного меню — показывает перенесённое главное меню.
 *
 * @param array $block Разобранный блок.
 * @return array
 */
function memorial_theme_nav_default_ref( $block ) {
	if ( 'core/navigation' !== ( $block['blockName'] ?? '' ) || ! empty( $block['attrs']['ref'] ) ) {
		return $block;
	}
	if ( ! preg_match( '/(^|\s)nav(\s|$)/', (string) ( $block['attrs']['className'] ?? '' ) ) ) {
		return $block;
	}
	$log = get_option( 'memorial_theme_nav_migrated' );
	$id  = is_array( $log ) ? (int) ( $log['nav_id'] ?? 0 ) : 0;
	if ( $id && 'publish' === get_post_status( $id ) ) {
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
	while ( $p->next_tag( array( 'tag_name' => 'UL' ) ) ) {
		if ( $p->has_class( 'wp-block-navigation__container' ) ) {
			$p->remove_class( 'nav' );
			$p->remove_class( 'wp-block-navigation' );
		}
	}
	return $p->get_updated_html();
}
add_filter( 'render_block', 'memorial_theme_nav_list_classes', 10, 2 );
