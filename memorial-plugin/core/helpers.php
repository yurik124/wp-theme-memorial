<?php
/**
 * Общие помощники: телефон, годы работы, иконки (SVG-набор внизу страницы).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Контакты и тексты главной редактируются в «Мемориал → Контакты / Главная страница» (core/settings.php).
memorial_plugin_opt() оставлена для совместимости: отдаёт основной телефон, первую почту и т.д.
 *
 * @param string $key Key.
 */
function memorial_plugin_opt( $key ) {
	$c = memorial_plugin_contacts();
	$a = memorial_plugin_addrs();
	$p = memorial_plugin_phones();
	switch ( $key ) {
		case 'phone':
			return $p ? $p[0]['num'] : '';
		case 'email':
			return memorial_plugin_emails()[0] ?? '';
		case 'addr1':
			return $a[0]['addr'] ?? '';
		case 'addr2':
			return $a[1]['addr'] ?? '';
		case 'hours':
			return $a[0]['hours'] ?? '';
		case 'founded':
			return (int) $c['founded'];
		case 'hero_img':
			return memorial_plugin_home( 'hero' )['img'] ?? '';
	}
	return '';
}

/** Лавровая графика от темы (фильтр memorial_plugin_laurel); пусто, если тема её не рисует
 *
 * @param string $kind Kind.
 * @param string $arg Arg.
 */
function memorial_plugin_laurel( $kind, $arg = '' ) {
	return (string) apply_filters( 'memorial_plugin_laurel', '', $kind, $arg );
}

/**
 * Placeholder mark for a product card without a photo: the laurel wreath, or a cross when no theme draws laurels.
 *
 * @return string SVG markup or a character.
 */
function memorial_plugin_noimg_mark() {
	// A product without a photo shows the logo of the site: the theme gives it through the filter
	// (its own logo, the site icon or the laurel mark with the letter); another theme — the logo it has set.
	$logo = (string) apply_filters( 'memorial_plugin_logo', '', 96 );
	if ( '' === $logo ) {
		$id   = (int) get_theme_mod( 'custom_logo' );
		$url  = $id ? wp_get_attachment_image_url( $id, 'medium' ) : get_site_icon_url( 192 );
		$logo = $url ? '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async">' : '';
	}
	if ( '' !== $logo ) {
		return '<span class="memorial-plugin-logo">' . $logo . '</span>';
	}
	$mark = memorial_plugin_laurel( 'wreath' );
	return '' !== $mark ? $mark : '✝';
}

/**
 * Tel.
 */
function memorial_plugin_tel() {
	return memorial_plugin_tel_of( memorial_plugin_opt( 'phone' ) );
}

/**
 * Years.
 */
function memorial_plugin_years() {
	$f = (int) memorial_plugin_contacts()['founded'];
	return $f ? max( 1, (int) wp_date( 'Y' ) - $f ) : 0;
}

/**
 * Картинка из медиатеки нужного размера по адресу оригинала (для фото, заданных в настройках адресом).
 * Не из медиатеки или размера нет — тот же адрес. Так блоки не грузят оригиналы по 200+ КБ.
 *
 * @param string $url URL.
 * @param string $size Size.
 */
function memorial_plugin_img_sized( $url, $size = 'medium_large' ) {
	if ( ! $url ) {
		return $url;
	}
	static $memo = array();
	$k           = $url . '|' . $size;
	if ( isset( $memo[ $k ] ) ) {
		return $memo[ $k ];
	}
	$id         = memorial_plugin_attachment_id_by_url( $url );
	$s          = $id ? wp_get_attachment_image_url( $id, $size ) : '';
	$memo[ $k ] = $s ? $s : $url;
	return $memo[ $k ];
}

/** Номер фото в медиатеке по адресу — по пути после /uploads/, поэтому находит и адреса другого домена (рабочего на тестовом)
 *
 * @param string $url URL.
 */
function memorial_plugin_attachment_id_by_url( $url ) {
	if ( ! preg_match( '~/uploads/(.+?\.(?:jpe?g|png|gif|webp|avif))(?:\?.*)?$~i', (string) $url, $m ) ) {
		return 0;
	}
	$rel = preg_replace( '/-\d+x\d+(?=\.\w+$)/', '', rawurldecode( $m[1] ) );
	global $wpdb;
	$id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN (%s, %s) LIMIT 1",
			$rel,
			preg_replace( '/(\.\w+)$/', '-scaled$1', $rel )
		)
	);
	return $id;
}
