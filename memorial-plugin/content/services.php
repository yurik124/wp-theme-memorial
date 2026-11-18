<?php
/**
 * Карточки услуг: блок «Наши услуги» на главной и шорткод [mem_services] для страницы «Услуги».
 *
 *   [mem_services]  — те же карточки, что на главной («Главная страница → Наши услуги»)
 *   [mem_services more="ritualnye-uslugi/pominalnyj-obed,pohorony-uchastnikov-svo"]
 *                   — плюс карточки других страниц по адресу (название, отрывок и фото записи берутся со страницы)
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Пункты услуг из настроек главной, со ссылками и фото */
function memorial_plugin_services_items() {
	$out = array();
	foreach ( memorial_plugin_home( 'services' )['items'] ?? array() as $it ) {
		if ( trim( $it['title'] ?? '' ) === '' ) {
			continue;
		}
		$pid = (int) ( $it['page'] ?? 0 );
		if ( $pid && get_post_status( $pid ) !== 'publish' ) {
			$pid = 0; // черновик — без ссылки.
		}
		$it['pid'] = $pid;
		$it['url'] = $pid ? get_permalink( $pid ) : '';
		if ( empty( $it['img'] ) ) {
			$it['img'] = $pid ? get_the_post_thumbnail_url( $pid, 'medium_large' ) : '';
		}
		$out[] = $it;
	}
	return $out;
}

/**
 * Services grid.
 *
 * @param array $items Items.
 */
function memorial_plugin_services_grid( array $items ) {
	$e = fn( $s ) => esc_html( memorial_plugin_t( $s ) );
	$h = '<div class="svc">';
	foreach ( $items as $it ) {
		$tag = $it['url'] ? 'a' : 'div';
		$h  .= '<' . $tag . ' class="svc__item reveal' . ( $it['img'] ? ' svc__item--img' : '' ) . '"' . ( $it['url'] ? ' href="' . esc_url( $it['url'] ) . '"' : '' ) . '>';
		if ( $it['img'] ) {
			$h .= '<span class="svc__img" style="background-image:url(' . esc_url( memorial_plugin_img_sized( $it['img'] ) ) . ')"></span>';   // не оригинал, а 768 px.
		}
		$h .= '<span class="svc__body"><h3>' . $e( $it['title'] ) . '</h3>';
		if ( ! empty( $it['text'] ) ) {
			$h .= '<p>' . $e( $it['text'] ) . '</p>';
		}
		// «от 36 700 ₽»: «от» — обычным текстом, сумма — крупно
		$price = ! empty( $it['price'] ) ? preg_replace( '/^(от|до)\s+/u', '<small>$1</small> ', $e( $it['price'] ) ) : '';
		$h    .= '<span class="svc__foot">' . ( '' !== $price ? '<b>' . $price . '</b>' : '' ) . ( $it['url'] ? '<span class="svc__more">' . esc_html__( 'More →', 'memorial-plugin' ) . '</span>' : '' ) . '</span>';
		$h    .= '</span></' . $tag . '>';
	}
	return $h . '</div>';
}

add_shortcode(
	'mem_services',
	function ( $atts ) {
		$a     = shortcode_atts( array( 'more' => '' ), $atts, 'mem_services' );
		$items = memorial_plugin_services_items();
		$have  = array_filter( array_column( $items, 'pid' ) );
		foreach ( array_filter( array_map( 'trim', explode( ',', $a['more'] ) ) ) as $path ) {
			$p = ctype_digit( $path ) ? get_post( (int) $path ) : get_page_by_path( trim( $path, '/' ) );
			if ( ! $p || 'publish' !== $p->post_status || in_array( $p->ID, $have, true ) || get_the_ID() === $p->ID ) {
				continue;
			}
			$items[] = array(
				'title' => get_the_title( $p ),
				'text'  => has_excerpt( $p ) ? get_the_excerpt( $p ) : '',
				'price' => '',
				'url'   => get_permalink( $p ),
				'img'   => get_the_post_thumbnail_url( $p, 'medium_large' ),
				'pid'   => $p->ID,
			);
			$have[]  = $p->ID;
		}
		return $items ? '<div class="mem-svc">' . memorial_plugin_services_grid( $items ) . '</div>' : '';
	}
);

/**
 * Once (owner, 18.11.2026): only services in “Our services” — on the home page and on “Funeral services”. The cards of the
 * landscaping and of the funeral goods are removed from «Memorial → Home → Our services»; they stay in the log
 * memorial_plugin_services_1_log (to put them back by hand). The option is written as it is, before the sanitizer of
 * the screen is registered (admin_init 10).
 *
 * @param bool $dry Dry run: only the cards that would go.
 * @return array Removed cards.
 */
function memorial_plugin_services_upgrade_1( $dry = false ) {
	$opt = get_option( 'mem_home' );
	if ( ! is_array( $opt ) || empty( $opt['services']['items'] ) || ! is_array( $opt['services']['items'] ) ) {
		return array();
	}
	$not_services = array( 'blagoustrojstvo-zahoroneniya', 'ritualnye-tovary' );
	$keep         = array();
	$gone         = array();
	foreach ( $opt['services']['items'] as $it ) {
		$pid  = (int) ( $it['page'] ?? 0 );
		$path = $pid ? urldecode( (string) get_page_uri( $pid ) ) : '';
		if ( in_array( $path, $not_services, true ) || preg_match( '/благоустр|ритуальн\w* товар/iu', (string) ( $it['title'] ?? '' ) ) ) {
			$gone[] = $it;
		} else {
			$keep[] = $it;
		}
	}
	if ( $gone && ! $dry ) {
		$opt['services']['items'] = $keep;
		update_option( 'mem_home', $opt );
	}
	return $gone;
}

add_action(
	'admin_init',
	static function () {
		if ( get_option( 'memorial_plugin_services_1' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'memorial_plugin_services_1', time(), false );
		update_option( 'memorial_plugin_services_1_log', memorial_plugin_services_upgrade_1(), false );
		if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
	},
	7
);
