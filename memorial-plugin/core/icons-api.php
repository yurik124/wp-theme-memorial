<?php
/**
 * Site icons in the WordPress icon registry (SVG Icon API, WordPress 7.1+).
 *
 * The icons are drawn with lines (stroke 1.5, round caps). The 7.1 registry keeps only filled shapes,
 * so assets/icons/*.svg are the same icons with the stroke turned into filled outlines
 * (Inkscape “Stroke to Path”, checked pixel by pixel against the original — tools/icons, workflow
 * “Иконки → SVG Icon API”). They are registered as the “Memorial” collection: memorial_plugin_icon()
 * renders them with wp_get_icon(), and they are available in the core Icon block.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Icons of the collection: name => label.
 *
 * @return array<string,string>
 */
function memorial_plugin_icon_labels() {
	return array(
		'phone'    => __( 'Phone', 'memorial-plugin' ),
		'pin'      => __( 'Map pin', 'memorial-plugin' ),
		'clock'    => __( 'Clock', 'memorial-plugin' ),
		'mail'     => __( 'Email', 'memorial-plugin' ),
		'check'    => __( 'Check mark', 'memorial-plugin' ),
		'chat'     => __( 'Message', 'memorial-plugin' ),
		'candle'   => __( 'Candle', 'memorial-plugin' ),
		'flower'   => __( 'Flower', 'memorial-plugin' ),
		'building' => __( 'Building', 'memorial-plugin' ),
		'doc'      => __( 'Document', 'memorial-plugin' ),
		'shield'   => __( 'Shield with a check mark', 'memorial-plugin' ),
		'car'      => __( 'Car', 'memorial-plugin' ),
		'heart'    => __( 'Heart', 'memorial-plugin' ),
		'ruble'    => __( 'Ruble', 'memorial-plugin' ),
		'cross'    => __( 'Orthodox cross', 'memorial-plugin' ),
	);
}

/**
 * Register the collection and its icons.
 */
function memorial_plugin_icons_register() {
	if ( ! function_exists( 'wp_register_icon_collection' ) || ! function_exists( 'wp_register_icon' ) ) {
		return;
	}
	wp_register_icon_collection(
		'memorial-plugin',
		array(
			'label'       => __( 'Memorial', 'memorial-plugin' ),
			'description' => __( 'Line icons of the Memorial site: phone, address, hours, services.', 'memorial-plugin' ),
		)
	);
	foreach ( memorial_plugin_icon_labels() as $name => $label ) {
		$file = MEMORIAL_PLUGIN_DIR . '/assets/icons/' . $name . '.svg';
		if ( is_readable( $file ) ) {
			wp_register_icon(
				'memorial-plugin/' . $name,
				array(
					'label'     => $label,
					'file_path' => $file,
				)
			);
		}
	}
}
add_action( 'init', 'memorial_plugin_icons_register' );

/**
 * An icon from the registry (or '' when the registry or the icon is missing).
 *
 * @param string $name Icon name.
 * @return string
 */
function memorial_plugin_icon_from_registry( $name ) {
	if ( ! function_exists( 'wp_get_icon' ) || ! did_action( 'init' ) ) {
		return '';
	}
	return (string) wp_get_icon(
		'memorial-plugin/' . $name,
		array(
			'size'  => null,
			'class' => 'ico ico--fill',
		)
	);
}
