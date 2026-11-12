<?php
/**
 * The Site Logo block shows the logo in the Site Editor too.
 *
 * On the site the theme draws the logo itself when no logo is chosen: the site icon, otherwise the laurel
 * mark with the first letter of the site title (inc/laurel.php). The Site Logo block in the editor can only
 * show an image from the media library, so without a chosen logo it showed an empty placeholder. Once, the
 * theme puts that same picture into the site_logo option: the site icon, or an SVG file of the laurel mark
 * added to the media library. On the site the picture is still drawn by the theme (the laurel stays inline
 * and takes the colour of the design), so nothing changes there; a logo chosen later replaces it.
 *
 * Log: the memorial_theme_logo_sync option (which attachment was put in).
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;

/** Option: what the theme put into site_logo. */
const MEMORIAL_THEME_LOGO_SYNC = 'memorial_theme_logo_sync';

/**
 * Whether the current site_logo is the picture the theme put there (not a logo chosen by the owner).
 *
 * @return bool
 */
function memorial_theme_logo_is_fallback() {
	$logo = (int) get_option( 'site_logo' );
	$sync = (array) get_option( MEMORIAL_THEME_LOGO_SYNC, array() );
	return $logo && (int) ( $sync['attachment'] ?? 0 ) === $logo;
}

/**
 * SVG file of the laurel mark in the colour of the design.
 *
 * @return string
 */
function memorial_theme_logo_svg() {
	$color = '#5e7052';
	foreach ( (array) ( wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) ?? array() ) as $c ) {
		if ( 'primary' === ( $c['slug'] ?? '' ) && preg_match( '~^#[0-9a-f]{3,8}$~i', (string) ( $c['color'] ?? '' ) ) ) {
			$color = $c['color'];
		}
	}
	$svg = memorial_theme_laurel( 'mark', memorial_theme_logo_letter() );
	$svg = str_replace(
		array( 'class="lv lv--mark" ', ' aria-hidden="true" focusable="false"', 'currentColor', ' vector-effect="non-scaling-stroke"' ),
		array( 'xmlns="http://www.w3.org/2000/svg" width="200" height="200" ', '', $color, '' ),
		$svg
	);
	return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $svg . "\n";
}

/**
 * Put the picture of the theme into site_logo once (admin, by a user who may change the design).
 */
function memorial_theme_logo_sync() {
	if ( get_option( 'site_logo' ) || get_option( MEMORIAL_THEME_LOGO_SYNC ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$icon = (int) get_option( 'site_icon' );
	if ( $icon && wp_attachment_is_image( $icon ) ) {
		update_option( 'site_logo', $icon );
		update_option(
			MEMORIAL_THEME_LOGO_SYNC,
			array(
				'attachment' => $icon,
				'source'     => 'site_icon',
				'time'       => time(),
			),
			false
		);
		return;
	}
	$dir = wp_upload_dir();
	if ( ! empty( $dir['error'] ) ) {
		return;
	}
	$file = trailingslashit( $dir['path'] ) . wp_unique_filename( $dir['path'], 'memorial-logo.svg' );
	if ( false === file_put_contents( $file, memorial_theme_logo_svg() ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a new file in the uploads folder.
		return;
	}
	$id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/svg+xml',
			'post_title'     => get_bloginfo( 'name' ) . ' — ' . __( 'logo', 'memorial-theme' ),
			'post_status'    => 'inherit',
			'guid'           => trailingslashit( $dir['url'] ) . basename( $file ),
		),
		$file,
		0,
		true
	);
	if ( is_wp_error( $id ) ) {
		return;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', get_bloginfo( 'name' ) );
	wp_update_attachment_metadata(
		$id,
		array(
			'width'  => 200,
			'height' => 200,
			'file'   => _wp_relative_upload_path( $file ),
		)
	);
	update_option( 'site_logo', $id );
	update_option(
		MEMORIAL_THEME_LOGO_SYNC,
		array(
			'attachment' => $id,
			'source'     => 'laurel',
			'time'       => time(),
		),
		false
	);
}
add_action( 'admin_init', 'memorial_theme_logo_sync' );
