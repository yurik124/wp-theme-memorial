<?php
/**
 * Блок «Источник и дата проверки» (memorial-plugin/checked) — ТЗ «Блоки и доработки» 24.11.2026, раздел 9:
 * мелкий текст на светлой подложке в конце статьи: «Проверено {дата} по официальным источникам» и ссылки на
 * Соцфонд, закон о погребении, администрацию. Дата старше 12 месяцев — метка «проверить актуальность» в
 * списке страниц админки.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Date of the check as d.m.Y; '' if it is not a date.
 *
 * @param string $date «2026-11-24» (or «24.11.2026»).
 * @return string
 */
function memorial_plugin_checked_date( $date ) {
	$date = trim( (string) $date );
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
		return $m[3] . '.' . $m[2] . '.' . $m[1];
	}
	return preg_match( '/^\d{2}\.\d{2}\.\d{4}$/', $date ) ? $date : '';
}

/**
 * Is the check older than 12 months?
 *
 * @param string $date Date of the check.
 * @param int    $now  Now (timestamp).
 * @return bool
 */
function memorial_plugin_checked_old( $date, $now = 0 ) {
	$d = memorial_plugin_checked_date( $date );
	if ( '' === $d ) {
		return false;
	}
	$time = strtotime( implode( '-', array_reverse( explode( '.', $d ) ) ) . ' 00:00:00 UTC' );
	return $time && $time < strtotime( '-12 months', $now ? $now : time() );
}

/**
 * The block.
 *
 * @param array $a Attributes: date, sources [{title, url}], text.
 * @return string
 */
function memorial_plugin_checked_render( $a = array() ) {
	$date    = memorial_plugin_checked_date( (string) ( $a['date'] ?? '' ) );
	$sources = array_values(
		array_filter(
			(array) ( $a['sources'] ?? array() ),
			static fn( $s ) => is_array( $s ) && '' !== trim( (string) ( $s['url'] ?? '' ) )
		)
	);
	if ( '' === $date && ! $sources ) {
		return memorial_plugin_block_note( __( 'Source and date of the check: set the date of the check and the sources in the block settings.', 'memorial-plugin' ) );
	}
	$text = trim( (string) ( $a['text'] ?? '' ) );
	if ( '' === $text ) {
		/* translators: %s: date of the check. */
		$text = '' !== $date ? sprintf( __( 'Checked on %s against official sources', 'memorial-plugin' ), '{date}' ) : __( 'Official sources', 'memorial-plugin' );
	}
	$text  = str_replace( array( '{дата}', '{date}' ), $date, $text );
	$links = array();
	foreach ( $sources as $s ) {
		$title   = trim( (string) ( $s['title'] ?? '' ) );
		$url     = trim( (string) $s['url'] );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( '' !== $title ? $title : (string) wp_parse_url( $url, PHP_URL_HOST ) ) . '</a>';
	}
	return '<aside class="mchecked">'
		. '<p class="mchecked__text">' . memorial_plugin_icon( 'check' ) . '<span>' . esc_html( rtrim( $text, '.' ) ) . ( $links ? ':' : '.' ) . '</span></p>'
		. ( $links ? '<p class="mchecked__links">' . implode( ' · ', $links ) . '</p>' : '' )
		. '</aside>';
}

/*
 * Список страниц: «проверить актуальность», если дата проверки в блоке старше 12 месяцев.
 */
add_filter(
	'display_post_states',
	static function ( $states, $post ) {
		if ( ! $post instanceof WP_Post || false === strpos( (string) $post->post_content, '<!-- wp:memorial-plugin/checked' ) ) {
			return $states;
		}
		if ( preg_match_all( '/<!--\s+wp:memorial-plugin\/checked\s+(\{.*?\})\s+\/-->/s', (string) $post->post_content, $m ) ) {
			foreach ( $m[1] as $json ) {
				$attrs = json_decode( $json, true );
				if ( is_array( $attrs ) && memorial_plugin_checked_old( (string) ( $attrs['date'] ?? '' ) ) ) {
					$states['memorial-plugin-checked'] = __( 'check whether it is up to date', 'memorial-plugin' );
					break;
				}
			}
		}
		return $states;
	},
	10,
	2
);
