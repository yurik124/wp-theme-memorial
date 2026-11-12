<?php
/**
 * Поиск с подсказками — общий для «Вопрос–ответ» [mem_faq] и «Полезно знать» [mem_guide].
 * Работает в браузере (assets/js/view/blocks.js → memSearch, Interactivity API): подсказки по мере набора, подсветка найденного,
 * поиск без учёта окончаний и с синонимами («стоимость» = «цена»), «Часто ищут», счётчик найденного.
 *
 * Плюс якоря у вопросов (блок «Подробности»): /stranica/#v-kak-poluchit-posobie — ссылка сразу раскрывает вопрос.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Якорь вопроса по его тексту
 *
 * @param string $q Query.
 */
function memorial_plugin_q_anchor( $q ) {
	$s = substr( memorial_plugin_slug( $q ), 0, 60 );
	return '' === $s ? '' : 'v-' . rtrim( $s, '-' );
}

/* Вопросы в тексте страниц получают якорь — на них можно сослаться, по ссылке вопрос раскроется */
add_filter(
	'render_block_core/details',
	function ( $html ) {
		if ( is_admin() || ! preg_match( '~<details\b([^>]*)>\s*<summary[^>]*>(.*?)</summary>~su', $html, $m ) || preg_match( '/\sid="/', $m[1] ) ) {
			return $html;
		}
		static $used = array();
		$id          = memorial_plugin_q_anchor( wp_strip_all_tags( $m[2] ) );
		if ( '' === $id ) {
			return $html;
		}
		$used[ $id ] = ( $used[ $id ] ?? 0 ) + 1;
		if ( $used[ $id ] > 1 ) {
			$id .= '-' . $used[ $id ];
		}
		return preg_replace( '~<details\b~', '<details id="' . esc_attr( $id ) . '"', $html, 1 );
	}
);

/**
 * Поле поиска. $o: id, label, placeholder, popular (слова «Часто ищут»), none (текст «ничего не нашли»), where (f / g — для статистики).
 *
 * @param array $o Options.
 */
function memorial_plugin_search_box( array $o ) {
	$id  = $o['id'] ?? 'ms-' . wp_rand( 100, 999 );
	$pop = array_filter( array_map( 'trim', (array) ( $o['popular'] ?? array() ) ) );
	$h   = '<div class="msearch" data-msearch data-log="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-w="' . esc_attr( $o['where'] ?? 'f' ) . '">'
		. '<div class="msearch__bar">'
		. '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html( $o['label'] ?? __( 'Search', 'memorial-plugin' ) ) . '</label>'
		. '<svg class="msearch__ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
		. '<input id="' . esc_attr( $id ) . '" type="search" placeholder="' . esc_attr( $o['placeholder'] ?? __( 'Search', 'memorial-plugin' ) ) . '" autocomplete="off" enterkeyhint="search"'
		. ' role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '-list" data-msearch-q>'
		. ( '<button type="button" class="msearch__x" data-msearch-clear hidden aria-label="' . esc_html__( 'Clear the search', 'memorial-plugin' ) . '">×</button>' )
		. '<ul class="msearch__list" id="' . esc_attr( $id ) . ( '-list" role="listbox" aria-label="' . esc_attr__( 'Suggestions', 'memorial-plugin' ) . '" hidden></ul>' )
		. '</div>';
	if ( $pop ) {
		$h .= ( '<p class="msearch__pop" data-msearch-pop><span>' . esc_html__( 'Frequent searches:', 'memorial-plugin' ) . '</span>' );
		foreach ( $pop as $w ) {
			$h .= '<button type="button" class="msearch__tag" data-q="' . esc_attr( $w ) . '">' . esc_html( $w ) . '</button>';
		}
		$h .= '</p>';
	}
	$h .= '<p class="msearch__status" aria-live="polite" data-msearch-status></p>'
		. '<div class="msearch__none" data-msearch-none hidden><p>' . esc_html( $o['none'] ?? __( 'Nothing found.', 'memorial-plugin' ) ) . '</p>'
		. '<p class="msearch__none-btns">' . ( function_exists( 'memorial_plugin_tel' ) && memorial_plugin_tel() ? '<a class="btn btn--gold" href="tel:' . esc_attr( memorial_plugin_tel() ) . '">' . esc_html( memorial_plugin_opt( 'phone' ) ) . '</a>' : '' )
		. ( '<button class="btn btn--ghost" type="button" data-callback>' . esc_html__( 'Call me back', 'memorial-plugin' ) . '</button></p></div>' )
		. '</div>';
	return $h;
}
