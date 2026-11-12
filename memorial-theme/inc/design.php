<?php
/**
 * Оформление сайта — стили сайта WordPress (Внешний вид → Редактор → Стили).
 *
 * Палитра, шрифты и размеры — в theme.json, готовые схемы — styles/*.json («лавр» — сама тема,
 * «бронза», «камень», «тёмная»). Прежняя страница «Мемориал → Оформление» (настройка mem_design) убрана:
 * её значения один раз переносятся в стили сайта (inc/design-migrate.php), сама настройка остаётся
 * в базе для отката до 2.1.0.
 *
 * Здесь — только то, что нужно переносу: старые схемы и шрифты, значения по умолчанию, контраст.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;

/** Цвета, которыми окрашен сайт. Ключ = CSS-переменная. */
function memorial_theme_color_keys() {
	return array(
		'dark'   => array( __( 'Background of the header bars and the footer (light in the light scheme)', 'memorial-theme' ), '#f2efe6' ),
		'dark2'  => array( __( 'Background of the bars — second shade', 'memorial-theme' ), '#e6ebdf' ),
		'gold'   => array( __( 'Accent: buttons, icons, laurel', 'memorial-theme' ), '#5e7052' ),
		'gold2'  => array( __( 'Light accent: ornament, dividers', 'memorial-theme' ), '#a9b796' ),
		'gold3'  => array( __( 'Dark accent: links, eyebrows, hover', 'memorial-theme' ), '#3f4d36' ),
		'bg'     => array( __( 'Page background', 'memorial-theme' ), '#fbfaf6' ),
		'bg2'    => array( __( 'Background of alternating blocks', 'memorial-theme' ), '#f2efe6' ),
		'ink'    => array( __( 'Main text', 'memorial-theme' ), '#2a2c28' ),
		'mute'   => array( __( 'Secondary text', 'memorial-theme' ), '#66675e' ),
		'line'   => array( __( 'Borders and dividers', 'memorial-theme' ), '#e2ded2' ),
		'sage'   => array( __( 'Sage backing: “What to do”, numbers, footer', 'memorial-theme' ), '#e6ebdf' ),
		'bronze' => array( __( 'Bronze: thin lines, laurel berries (in small doses)', 'memorial-theme' ), '#a88a5a' ),
	);
}

/** Готовые цветовые схемы (все светлые: тёмный цвет — только для текста) */
function memorial_theme_color_presets() {
	$k = array_keys( memorial_theme_color_keys() );
	$p = fn( $name, ...$c ) => array(
		'name' => $name,
		'c'    => array_combine( $k, $c ),
	);
	// dark       dark2      gold       gold2      gold3      bg         bg2        ink        mute       line       sage       bronze.
	return array(
		'laurel'        => $p( __( 'Bright memory — laurel (main)', 'memorial-theme' ), '#f2efe6', '#e6ebdf', '#5e7052', '#a9b796', '#3f4d36', '#fbfaf6', '#f2efe6', '#2a2c28', '#66675e', '#e2ded2', '#e6ebdf', '#a88a5a' ),
		'laurel_bronze' => $p( __( 'Bright memory — bronze', 'memorial-theme' ), '#f2efe6', '#ece6da', '#8c6f45', '#cdbb98', '#5c4628', '#fbfaf6', '#f2efe6', '#2a2c28', '#66675e', '#e2ded2', '#ede7db', '#a88a5a' ),
		'stone'         => $p( __( 'Light stone (grey-blue)', 'memorial-theme' ), '#f0f0ec', '#e5e9ec', '#56687a', '#aebac6', '#3a4a5a', '#fafaf8', '#f0f0ec', '#2a2c2e', '#62666b', '#e0e0da', '#e5e9ec', '#a88a5a' ),
	);
}

/** Ключ схемы по умолчанию (её цвета прописаны в main.css) */
function memorial_theme_default_preset() {
	return 'laurel'; }

/** Встроенные шрифты: ключ => [название, CSS-стек, для заголовков, для текста] */
function memorial_theme_builtin_fonts() {
	return array(
		'lora'      => array( 'Lora', '"Lora",Georgia,"Times New Roman",serif' ),
		'cormorant' => array( 'Cormorant Garamond', '"Cormorant Garamond",Georgia,"Times New Roman",serif' ),
		'georgia'   => array( __( 'Georgia (system serif)', 'memorial-theme' ), 'Georgia,"Times New Roman",serif' ),
		'system'    => array( __( 'System sans-serif (as in Windows/phone)', 'memorial-theme' ), 'system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif' ),
	);
}

/** Все шрифты: встроенные + загруженные в админке */
function memorial_theme_all_fonts() {
	$out = memorial_theme_builtin_fonts();
	foreach ( (array) ( get_option( 'mem_design' )['fonts']['custom'] ?? array() ) as $f ) {
		$name = trim( $f['name'] ?? '' );
		if ( '' === $name || empty( $f['r'] ) ) {
			continue;
		}
		$out[ 'c_' . sanitize_key( sanitize_title( $name ) ) ] = array( $name . __( ' (custom)', 'memorial-theme' ), '"' . str_replace( '"', '', $name ) . '",' . ( ! empty( $f['serif'] ) ? 'Georgia,serif' : 'system-ui,Arial,sans-serif' ), $f );
	}
	return $out;
}

/** Сохранённое «Оформление» поверх значений по умолчанию. Работает и без плагина (тогда — прямо из базы). */
function memorial_theme_design_opts() {
	if ( function_exists( 'memorial_plugin_settings' ) ) {
		return memorial_plugin_settings( 'mem_design' );
	}
	static $d = null;
	if ( null === $d ) {
		$merge = function ( $def, $saved ) use ( &$merge ) {
			if ( ! is_array( $saved ) ) {
				return $def;
			}
			foreach ( $def as $k => $v ) {
				if ( array_key_exists( $k, $saved ) ) {
					$def[ $k ] = is_array( $v ) && $v && array_keys( $v ) !== range( 0, count( $v ) - 1 ) ? $merge( $v, $saved[ $k ] ) : $saved[ $k ];
				}
			}
			return $def;
		};
		$d     = $merge( memorial_theme_design_defaults(), get_option( 'mem_design' ) );
	}
	return $d;
}

/**
 * Design defaults.
 */
function memorial_theme_design_defaults() {
	return array(
		'colors' => array( 'preset' => memorial_theme_default_preset() ) + array_fill_keys( array_keys( memorial_theme_color_keys() ), '' ),
		'fonts'  => array(
			'head'   => 'cormorant',
			'body'   => 'system',
			'custom' => array(),
		),
		'text'   => array(
			'scale' => '100',
			'head'  => '100',
		),
		'img'    => array( 'webp' => (int) get_option( 'mem_webp', 0 ) ),
	);
}

/** Контраст двух цветов #rrggbb по WCAG (1…21)
 *
 * @param array $a Arguments.
 * @param mixed $b B.
 */
function memorial_theme_contrast( $a, $b ) {
	$l       = function ( $h ) {
		$h = ltrim( (string) $h, '#' );
		if ( strlen( $h ) === 3 ) {
			$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		$c = array_map( fn( $i ) => hexdec( substr( $h, $i, 2 ) ) / 255, array( 0, 2, 4 ) );
		$c = array_map( fn( $v ) => $v <= .03928 ? $v / 12.92 : ( ( $v + .055 ) / 1.055 ) ** 2.4, $c );
		return .2126 * $c[0] + .7152 * $c[1] + .0722 * $c[2];
	};
	[$x, $y] = array( $l( $a ), $l( $b ) );
	return ( max( $x, $y ) + .05 ) / ( min( $x, $y ) + .05 );
}

/** Цвет из палитры стилей сайта (с учётом выбранной схемы и правок пользователя), например 'base'
 *
 * @param string $slug Slug.
 * @param string $fallback Fallback.
 */
function memorial_theme_palette_color( $slug, $fallback = '' ) {
	$palette = wp_get_global_settings( array( 'color', 'palette' ) );
	foreach ( array( 'custom', 'theme' ) as $origin ) {
		foreach ( (array) ( $palette[ $origin ] ?? array() ) as $c ) {
			if ( ( $c['slug'] ?? '' ) === $slug && ! empty( $c['color'] ) ) {
				return $c['color'];
			}
		}
	}
	return $fallback;
}

/**
 * Цвет панели браузера на телефоне — под фон страницы.
 */
function memorial_theme_theme_color() {
	return memorial_theme_palette_color( 'base', '#fbfaf6' );
}

/* Значения по умолчанию для настройки mem_design — функция темы (нужно переносу и откату) */
add_filter( 'memorial_plugin_settings_defaults', fn( $fn, $name ) => 'mem_design' === $name ? 'memorial_theme_design_defaults' : $fn, 10, 2 );
