<?php
/**
 * Коды характеристик в названии и описании товара: «Венок {spec:vysota}» → «Венок 120 см».
 *
 * Код — у характеристики типа («Каталог → Типы и категории → Характеристики»), латиницей из названия,
 * хранится вместе с ней и не меняется при переименовании. Значение — как в карточке на сайте (число
 * с единицей типа). Пусто или код не найден — код убирается вместе с пустыми скобками и лишними пробелами.
 *
 * В базе название и описание хранятся с кодами. Подставляет сайт: фильтр the_title (кроме экранов
 * wp-admin), заголовок вкладки, описание в просмотре и на странице товара, Schema.org, SEO-плагины.
 * Экраны каталога в админке, выгрузка и импорт берут post_title как есть.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Код характеристики: латиница, цифры и «_»; из названия, если не задан.
 *
 * @param string $code  Code typed or stored.
 * @param string $label Name of the specification.
 * @return string
 */
function memorial_plugin_spec_code( $code, $label = '' ) {
	$code = function_exists( 'memorial_plugin_value_key' ) ? memorial_plugin_value_key( (string) $code ) : '';
	if ( '' === $code && '' !== $label && function_exists( 'memorial_plugin_value_code_from' ) ) {
		$code = memorial_plugin_value_code_from( $label );
	}
	return substr( $code, 0, 40 );
}

/**
 * Характеристики типа получают коды: свой или из названия; повтор — с номером (vysota, vysota_2).
 *
 * @param array $fields Cleaned fields of a type.
 * @return array
 */
function memorial_plugin_spec_codes_fill( $fields ) {
	$used = array();
	foreach ( $fields as &$f ) {
		$base = memorial_plugin_spec_code( $f['code'] ?? '', $f['label'] ?? '' );
		$base = '' !== $base ? $base : 'spec';
		$code = $base;
		for ( $n = 2; isset( $used[ $code ] ); $n++ ) {
			$code = $base . '_' . $n;
		}
		$used[ $code ] = true;
		$f['code']     = $code;
	}
	unset( $f );
	return $fields;
}

/**
 * Значения характеристик товара по кодам — как их видит посетитель (число с единицей).
 *
 * @param int $post_id Product ID.
 * @return array<string,string> Code => value.
 */
function memorial_plugin_spec_values( $post_id ) {
	$post_id = (int) $post_id;
	$shown = array();
	foreach ( memorial_plugin_product_specs_shown( $post_id ) as [$k, $v] ) {
		if ( '' !== $k ) {
			$shown[ mb_strtolower( $k ) ] = $v;
		}
	}
	$out  = array();
	$type = (int) memorial_plugin_product_type( $post_id );
	foreach ( $type ? memorial_plugin_type_fields( $type, false ) : array() as $f ) {
		$out[ $f['code'] ] = $shown[ mb_strtolower( $f['label'] ) ] ?? '';
	}
	// Характеристика, которой нет в типе (товар перенесли в другой тип), — по коду из её названия.
	foreach ( $shown as $k => $v ) {
		$c = memorial_plugin_spec_code( '', $k );
		if ( '' !== $c && ! isset( $out[ $c ] ) ) {
			$out[ $c ] = $v;
		}
	}
	return $out;
}

/**
 * Подставить значения характеристик в текст.
 *
 * @param string $text    Title or description with {spec:code}.
 * @param int    $post_id Product ID.
 * @return string
 */
function memorial_plugin_spec_fill( $text, $post_id ) {
	$text = (string) $text;
	if ( false === strpos( $text, '{spec:' ) ) {
		return $text;
	}
	$vals = memorial_plugin_spec_values( $post_id );
	$out  = preg_replace_callback(
		'/\{spec:([A-Za-z0-9_\-]+)\}/',
		static fn( $m ) => $vals[ memorial_plugin_spec_code( $m[1] ) ] ?? '',
		$text
	);
	// Пустое значение: без пустых скобок, двойных пробелов и висящих знаков в начале и конце.
	$out = preg_replace( '/[ \t\x{00A0}]*(\(\s*\)|\[\s*\]|«\s*»|"\s*")/u', '', $out );
	$out = preg_replace( '/([ \t\x{00A0}])[ \t\x{00A0}]+/u', '$1', $out );
	$out = preg_replace( '/[ \t\x{00A0}]+([,.;:!?)])/u', '$1', $out );
	$out = preg_replace( '/^[ \t\x{00A0},;:—–-]+|[ \t\x{00A0},;:—–-]+$/u', '', $out );
	return $out;
}

/**
 * Сайт подставляет значения; экраны wp-admin (списки записей, редактор) показывают код.
 */
function memorial_plugin_spec_fill_here() {
	return ! is_admin() || wp_doing_ajax();
}

/* Название товара: карточки, просмотр, страница товара, крошки, Schema.org, «Заказать» и заявка */
add_filter(
	'the_title',
	static function ( $title, $id = 0 ) {
		if ( ! $id || 'mem_product' !== get_post_type( $id ) || ! memorial_plugin_spec_fill_here() ) {
			return $title;
		}
		return memorial_plugin_spec_fill( $title, $id );
	},
	9,
	2
);

/* Заголовок вкладки страницы товара */
add_filter(
	'single_post_title',
	static function ( $title, $post = null ) {
		return $post && 'mem_product' === $post->post_type ? memorial_plugin_spec_fill( $title, $post->ID ) : $title;
	},
	9,
	2
);

/* Описание на странице товара (the_content у товара) */
add_filter(
	'the_content',
	static function ( $content ) {
		$id = (int) get_the_ID();
		return $id && 'mem_product' === get_post_type( $id ) && memorial_plugin_spec_fill_here() ? memorial_plugin_spec_fill( $content, $id ) : $content;
	},
	5
);

/* Отрывок (Schema.org, SEO-плагины) */
add_filter(
	'get_the_excerpt',
	static function ( $excerpt, $post = null ) {
		return $post && 'mem_product' === $post->post_type ? memorial_plugin_spec_fill( $excerpt, $post->ID ) : $excerpt;
	},
	9,
	2
);
