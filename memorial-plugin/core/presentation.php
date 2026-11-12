<?php
/**
 * Помощники вывода, которые раньше жили в теме (functions.php): блоки плагина собирают разметку сами,
 * а тема переходит на HTML-шаблоны и больше не содержит PHP-шаблонов вывода (этап 2).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Адрес для посетителей — без почтового индекса в начале («308000, г. Белгород…» → «г. Белгород…»)
 *
 * @param array $a Arguments.
 */
function memorial_plugin_addr( $a ) {
	return trim( preg_replace( '/^\s*\d{6}\s*,?\s*/u', '', (string) $a ) );
}

/**
 * Неразрывные пробелы, чтобы строка не рвалась некрасиво: «с 2003 года», «в Белгороде», «36 700 ₽» —
 * короткие предлоги и союзы держатся со следующим словом, число — со словом после него.
 *
 * @param array $s Section data.
 */
function memorial_plugin_nbsp( $s ) {
	$s = (string) $s;
	if ( '' === $s ) {
		return $s;
	}
	$nb = "\u{00A0}";
	$s  = preg_replace( '/(?<![\p{L}\d\-])(с|со|в|во|к|ко|и|а|о|об|у|по|от|до|на|за|из|не|но|ни|для|без|под|при|над)\s+(?=[\p{L}\d«"(])/iu', '$1' . $nb, $s );
	$s  = preg_replace( '/(\d)\s+(?=(?:год|года|лет|г\.|₽|руб|км|м\b|мин|час|чел|шт|%))/u', '$1' . $nb, $s );
	$s  = preg_replace( '/(\d)\s(?=\d{3}(?!\d))/u', '$1' . $nb, $s );
	$s  = preg_replace( '/\s+([·—])\s/u', $nb . '$1 ', $s );
	return $s;
}
add_filter(
	'the_title',
	function ( $t, $id = 0 ) {
		return is_admin() ? $t : memorial_plugin_nbsp( $t );
	},
	20,
	2
);

/** Текст из настроек с подстановками {years} и т. п., экранированный и с неразрывными пробелами
 *
 * @param array $s Section data.
 */
function memorial_plugin_e( $s ) {
	return memorial_plugin_nbsp( esc_html( memorial_plugin_t( $s ) ) );
}

/**
 * Text from the settings with its {placeholders} filled in, escaped for HTML (without non-breaking spaces).
 *
 * @param string $s Text.
 * @return string
 */
function memorial_plugin_esc_t( $s ) {
	return esc_html( memorial_plugin_t( $s ) );
}

/** Заголовок раздела: надзаголовок, лавровый разделитель, H2
 *
 * @param array $sec Sec.
 */
function memorial_plugin_sec_head( $sec ) {
	if ( empty( $sec['eyebrow'] ) && empty( $sec['title'] ) ) {
		return '';
	}
	$h = '<header class="sec-head">';
	if ( ! empty( $sec['eyebrow'] ) ) {
		$h .= '<p class="eyebrow">' . memorial_plugin_e( $sec['eyebrow'] ) . '</p>';
	}
	$h .= '<span class="sec-head__div">' . memorial_plugin_laurel( 'divider' ) . '</span>';
	if ( ! empty( $sec['title'] ) ) {
		$h .= '<h2>' . memorial_plugin_e( $sec['title'] ) . '</h2>';
	}
	return $h . '</header>';
}

/** Фото первого экрана: [для ПК — как загружено, для телефона — 1024 px из медиатеки (если есть)]
 *
 * @param string $url URL.
 */
function memorial_plugin_hero_srcs( $url ) {
	$m  = $url;
	$id = memorial_plugin_attachment_id_by_url( $url );
	$l  = $id ? wp_get_attachment_image_src( $id, 'large' ) : false;
	if ( $l && $l[1] < ( wp_get_attachment_image_src( $id, 'full' )[1] ?? 0 ) ) {
		$m = $l[0];
	}
	return array( $url, $m );
}

/** Год основания (для «© 2003–2026»), 0 — если не задан */
function memorial_plugin_founded_year() {
	$y = (int) memorial_plugin_opt( 'founded' );
	if ( $y > 1900 ) {
		return $y;
	}
	return memorial_plugin_years() ? (int) wp_date( 'Y' ) - (int) memorial_plugin_years() : 0;
}

/** Ссылка на MAX из «Мемориал → Контакты» */
function memorial_plugin_max_url() {
	return ( (array) memorial_plugin_messengers() )['max'] ?? '';
}
