<?php
/**
 * Разовый перенос «Мемориал → Оформление» (настройка mem_design) в стили сайта WordPress.
 *
 * Что переносится:
 *  — готовая схема: «бронза» и «камень» → палитра соответствующего варианта стилей (styles/*.json);
 *  — свои цвета → правки палитры (base, primary, contrast…); цвет текста на кнопке подбирается по контрасту, как раньше;
 *  — шрифты заголовков и текста, размер текста и заголовков → дополнительный CSS стилей сайта
 *    (переменные --serif, --sans, --fs, --fs-h, которыми пользуется main.css), свои загруженные шрифты — там же.
 * Если всё было по умолчанию («лавр», Cormorant + системный, 100 %), переносить нечего — стили сайта не трогаются.
 * Если в стилях сайта уже есть правки пользователя, перенос их не перезаписывает и только отмечается в журнале.
 *
 * Настройка mem_design не удаляется (откат до 2.1.0); журнал — опция memorial_theme_design_migrated.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Соответствие старых имён цветов (CSS-переменные «Оформления») и слагов палитры theme.json.
 *
 * @return array<string,string>
 */
function memorial_theme_design_color_map() {
	return array(
		'bg'     => 'base',
		'bg2'    => 'base-2',
		'sage'   => 'surface',
		'gold'   => 'primary',
		'gold3'  => 'secondary',
		'gold2'  => 'tertiary',
		'bronze' => 'accent',
		'ink'    => 'contrast',
		'mute'   => 'contrast-2',
		'line'   => 'line',
	);
}

/**
 * Палитра из файла темы: theme.json или вариант стилей styles/<имя>.json.
 *
 * @param string $file Путь относительно папки темы.
 * @return array<int,array<string,string>>
 */
function memorial_theme_palette_from_file( $file ) {
	$path = get_template_directory() . '/' . $file;
	if ( ! is_readable( $path ) ) {
		return array();
	}
	$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- локальный файл темы.
	return (array) ( $json['settings']['color']['palette'] ?? array() );
}

/**
 * Что перенести: палитра (или null, если она как в теме) и дополнительный CSS (или '').
 *
 * @param array $design Значение mem_design (с подставленными значениями по умолчанию).
 * @return array{palette: ?array, css: string, notes: string[]}
 */
function memorial_theme_design_migration_plan( $design ) {
	$notes   = array();
	$preset  = $design['colors']['preset'] ?? 'laurel';
	$variant = array(
		'laurel_bronze' => 'styles/bronze.json',
		'stone'         => 'styles/stone.json',
	)[ $preset ] ?? 'theme.json';
	$theme   = memorial_theme_palette_from_file( 'theme.json' );
	$palette = memorial_theme_palette_from_file( $variant );

	$by_slug = array();
	foreach ( $palette as $i => $c ) {
		$by_slug[ $c['slug'] ] = $i;
	}
	$custom = false;
	foreach ( memorial_theme_design_color_map() as $old => $slug ) {
		$value = strtolower( trim( (string) ( $design['colors'][ $old ] ?? '' ) ) );
		if ( '' !== $value && preg_match( '/^#[0-9a-f]{6}$/', $value ) && isset( $by_slug[ $slug ] ) ) {
			$palette[ $by_slug[ $slug ] ]['color'] = $value;
			$custom                                = true;
		}
	}
	foreach ( array( 'dark', 'dark2' ) as $old ) {
		if ( ! empty( $design['colors'][ $old ] ) ) {
			$notes[] = "Свой цвет «{$old}» (бывшие тёмные плашки) не переносится: в светлых схемах он совпадает с фоном разделов.";
		}
	}
	// Текст на кнопке с заливкой: белый, если читается, иначе основной цвет текста (как считало «Оформление»).
	if ( $custom && isset( $by_slug['on-primary'], $by_slug['primary'], $by_slug['contrast'] ) ) {
		$primary = $palette[ $by_slug['primary'] ]['color'];
		$ink     = $palette[ $by_slug['contrast'] ]['color'];
		$white   = memorial_theme_contrast( $primary, '#ffffff' );
		$palette[ $by_slug['on-primary'] ]['color'] = ( $white >= 4.5 || $white >= memorial_theme_contrast( $primary, $ink ) ) ? '#ffffff' : $ink;
	}
	$palette_changed = wp_json_encode( $palette ) !== wp_json_encode( $theme );

	// Шрифты и размер текста — через переменные main.css.
	$vars = array();
	$all  = memorial_theme_all_fonts();
	$head = $design['fonts']['head'] ?? 'cormorant';
	$body = $design['fonts']['body'] ?? 'system';
	if ( 'cormorant' !== $head && isset( $all[ $head ] ) ) {
		$vars[] = '--serif:' . $all[ $head ][1];
	}
	if ( 'system' !== $body && isset( $all[ $body ] ) ) {
		$vars[] = '--sans:' . $all[ $body ][1];
	}
	foreach ( array( 'scale' => '--fs', 'head' => '--fs-h' ) as $key => $var ) {
		$pct = max( 70, min( 150, (int) ( $design['text'][ $key ] ?? 100 ) ? (int) ( $design['text'][ $key ] ?? 100 ) : 100 ) );
		if ( 100 !== $pct ) {
			$vars[] = $var . ':' . rtrim( rtrim( number_format( $pct / 100, 2, '.', '' ), '0' ), '.' );
		}
	}
	$css = '';
	foreach ( $all as $font ) {
		if ( empty( $font[2] ) ) {
			continue;
		}
		$c      = $font[2];
		$family = str_replace( '"', '', trim( $c['name'] ) );
		foreach ( array( array( 'r', 'normal', '400 500' ), array( 'b', 'normal', '600 800' ), array( 'i', 'italic', '400 500' ) ) as $face ) {
			if ( empty( $c[ $face[0] ] ) ) {
				continue;
			}
			$css .= '@font-face{font-family:"' . $family . '";font-style:' . $face[1] . ';font-weight:' . $face[2] . ';font-display:swap;src:url(' . esc_url_raw( $c[ $face[0] ] ) . ')}';
		}
	}
	if ( $vars ) {
		// :root:root — сильнее, чем :root в main.css, в каком бы порядке ни подключились стили.
		$css .= ':root:root{' . implode( ';', $vars ) . '}';
	}

	return array(
		'palette' => $palette_changed ? $palette : null,
		'css'     => $css,
		'notes'   => $notes,
	);
}

/**
 * Перенос: один раз, когда активна эта тема.
 */
function memorial_theme_design_migrate() {
	if ( get_option( 'memorial_theme_design_migrated' ) || get_stylesheet() !== get_template() ) {
		return;
	}
	$log  = array(
		'time'   => time(),
		'result' => 'nothing',
	);
	$raw  = get_option( 'mem_design' );
	$plan = is_array( $raw ) ? memorial_theme_design_migration_plan( memorial_theme_design_opts() ) : null;

	if ( $plan && ( $plan['palette'] || '' !== $plan['css'] ) ) {
		$post_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		$post    = $post_id ? get_post( $post_id ) : null;
		$data    = $post ? json_decode( $post->post_content, true ) : null;
		$data    = is_array( $data ) ? $data : array();
		$touched = ! empty( $data['settings'] ) || ! empty( $data['styles'] );

		if ( ! $post ) {
			$log['result'] = 'no-global-styles-post';
		} elseif ( $touched ) {
			$log['result'] = 'skipped-user-styles-exist';
		} else {
			$data['version']                    = 3;
			$data['isGlobalStylesUserThemeJSON'] = true;
			if ( $plan['palette'] ) {
				$data['settings']['color']['palette']['theme'] = $plan['palette'];
			}
			if ( '' !== $plan['css'] ) {
				$data['styles']['css'] = $plan['css'];
			}
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => wp_slash( wp_json_encode( $data ) ),
				)
			);
			$log['result'] = 'migrated';
		}
		$log += $plan;
	}
	update_option( 'memorial_theme_design_migrated', $log, false );
}
add_action( 'after_switch_theme', 'memorial_theme_design_migrate' );
add_action( 'admin_init', 'memorial_theme_design_migrate' );
