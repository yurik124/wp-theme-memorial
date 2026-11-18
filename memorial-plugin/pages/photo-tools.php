<?php
/**
 * «Мемориал → Обработка фото» (owner, 18.11.2026): one style for the photos of the catalog, of “Our works” and of the
 * pages — colour, the logo in a corner, a single light background for the products, the same proportions, no EXIF,
 * sRGB, and a report of bad photos. Every function is switched on and off by itself, all of them by one switch;
 * the screen shows how a photo looks with the settings before they are saved (src/settings/photo-preview.js — the
 * same formulas as here).
 *
 * Nothing is lost: the original file and its sizes stay where they are, the processed file is a new file next to
 * them (name-m<hash>.<ext>), and the attachment points to it. “Return the original” points the attachment back
 * and removes the processed files. When the settings change, the photos are processed again, a few a minute (WP-Cron).
 *
 * The hosting has no Imagick, only GD: colour, crop, logo and the removal of EXIF are done here; the background
 * and the conversion of colour profiles (P3, Adobe RGB → sRGB) are done in GitHub Actions (.github/workflows/photos.yml):
 * it puts a prepared copy into uploads/memorial-photos/stage/, and the processing here starts from it.
 *
 * Never processed: reviews, the guest book, documents, the logo and the icon of the site, a photo marked
 * “Do not process” (_mem_photo_skip).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Product types (root catalog sections) whose background is replaced by default (owner, 18.11.2026). */
const MEMORIAL_PLUGIN_PHOTO_BG_TYPES = array( 'venki', 'groby', 'metallicheskie-kresty', 'ogradki-2' );

/** The warm light background of the site. */
const MEMORIAL_PLUGIN_PHOTO_BG = '#fbfaf6';

/**
 * Steps from −N to +N for a select (the number field of the screens takes no minus).
 *
 * @param int $max  Largest value.
 * @param int $step Step.
 * @return array<string,string>
 */
function memorial_plugin_photo_steps( $max, $step ) {
	$out = array();
	for ( $v = -$max; $v <= $max; $v += $step ) {
		$out[ (string) $v ] = 0 === $v ? '0' : ( $v > 0 ? '+' . $v : '−' . abs( $v ) );
	}
	return $out;
}

/**
 * Percent options from…max.
 *
 * @param int $max  Largest value.
 * @param int $step Step.
 * @param int $from Smallest value.
 * @return array<string,string>
 */
function memorial_plugin_photo_percents( $max, $step, $from = 0 ) {
	$out = array();
	for ( $v = $from; $v <= $max; $v += $step ) {
		$out[ (string) $v ] = $v . ' %';
	}
	return $out;
}

/**
 * Settings of the screen.
 *
 * @return array
 */
function memorial_plugin_photos_schema() {
	$types = array();
	foreach ( get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'parent'     => 0,
			'hide_empty' => false,
		)
	) as $t ) {
		$types[ 'type_' . $t->slug ] = array(
			'type'  => 'checkbox',
			'label' => $t->name,
		);
	}
	$ratios = array(
		''    => __( 'As it is', 'memorial-plugin' ),
		'1:1' => '1 : 1',
		'3:4' => __( '3 : 4 (upright)', 'memorial-plugin' ),
		'4:3' => __( '4 : 3 (landscape)', 'memorial-plugin' ),
		'3:2' => __( '3 : 2 (landscape)', 'memorial-plugin' ),
	);
	return array(
		'main'   => array(
			'type'   => 'group',
			'label'  => __( 'Processing', 'memorial-plugin' ),
			'help'   => __( 'Switched off — the site shows the photos as they were uploaded (the originals are kept). Which photos are processed: reviews, documents, the logo and a photo marked “Do not process” in the media library never are.', 'memorial-plugin' ),
			'fields' => array(
				'on'      => array(
					'type'  => 'checkbox',
					'label' => __( 'Process the photos', 'memorial-plugin' ),
				),
				'product' => array(
					'type'  => 'checkbox',
					'label' => __( 'Catalog products', 'memorial-plugin' ),
				),
				'work'    => array(
					'type'  => 'checkbox',
					'label' => __( '“Our works”', 'memorial-plugin' ),
				),
				'page'    => array(
					'type'  => 'checkbox',
					'label' => __( 'Photos of the pages', 'memorial-plugin' ),
				),
			),
		),
		'color'  => array(
			'type'   => 'group',
			'label'  => __( 'Colour', 'memorial-plugin' ),
			'help'   => __( 'Auto levels stretch the darkest and the lightest tones; white balance removes a yellow or blue cast of the light. The rest is the same for every photo.', 'memorial-plugin' ),
			'fields' => array(
				'on'       => array(
					'type'  => 'checkbox',
					'label' => __( 'Colour correction', 'memorial-plugin' ),
				),
				'levels'   => array(
					'type'    => 'checkbox',
					'label'   => __( 'Auto levels', 'memorial-plugin' ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'wb'       => array(
					'type'    => 'select',
					'label'   => __( 'White balance', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_percents( 100, 25 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'bright'   => array(
					'type'    => 'select',
					'label'   => __( 'Brightness', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_steps( 30, 5 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'contrast' => array(
					'type'    => 'select',
					'label'   => __( 'Contrast', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_steps( 30, 5 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'warmth'   => array(
					'type'    => 'select',
					'label'   => __( 'Warmth', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_steps( 20, 5 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'sat'      => array(
					'type'    => 'select',
					'label'   => __( 'Saturation', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_steps( 30, 5 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'sharp'    => array(
					'type'    => 'select',
					'label'   => __( 'Sharpness', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_percents( 100, 25 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
			),
		),
		'logo'   => array(
			'type'   => 'group',
			'label'  => __( 'Logo', 'memorial-plugin' ),
			'help'   => __( 'In the lower right corner. Empty file — the logo of the site. On small copies (catalog cards) it is not put: it would be unreadable.', 'memorial-plugin' ),
			'fields' => array(
				'on'      => array(
					'type'  => 'checkbox',
					'label' => __( 'Logo on the photos', 'memorial-plugin' ),
				),
				'file'    => array(
					'type'    => 'image',
					'label'   => __( 'Logo file (PNG with a transparent background)', 'memorial-plugin' ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'size'    => array(
					'type'    => 'select',
					'label'   => __( 'Width of the logo', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_percents( 30, 2, 8 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'opacity' => array(
					'type'    => 'select',
					'label'   => __( 'Opacity', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_percents( 100, 10, 30 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'margin'  => array(
					'type'    => 'select',
					'label'   => __( 'Margin from the edge', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_percents( 6, 1, 1 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'min'     => array(
					'type'    => 'number',
					'label'   => __( 'Not on copies narrower than, px', 'memorial-plugin' ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
			),
		),
		'bg'     => array(
			'type'   => 'group',
			'label'  => __( 'Single background of the products', 'memorial-plugin' ),
			'help'   => __( 'The background around a product is replaced with one light colour. It is done by a free open model in GitHub Actions (the hosting cannot), so a new photo gets its background within an hour; the preview shows the samples of the last run of “Photos — background” in Actions.', 'memorial-plugin' ),
			'fields' => array_merge(
				array(
					'on'    => array(
						'type'  => 'checkbox',
						'label' => __( 'Replace the background', 'memorial-plugin' ),
					),
					'color' => array(
						'type'    => 'text',
						'label'   => __( 'Background colour', 'memorial-plugin' ),
						'show_if' => array( 'on', array( 1, '1', true ) ),
					),
				),
				array_map( static fn( $f ) => $f + array( 'show_if' => array( 'on', array( 1, '1', true ) ) ), $types )
			),
		),
		'crop'   => array(
			'type'   => 'group',
			'label'  => __( 'Proportions and cropping', 'memorial-plugin' ),
			'help'   => __( 'Products: the object is found on its background and put in the middle with even margins, the photo gets the chosen proportions (the missing part is filled with the background, nothing is cut off the product). Works: cut to the proportions around the middle.', 'memorial-plugin' ),
			'fields' => array(
				'on'      => array(
					'type'  => 'checkbox',
					'label' => __( 'Same proportions', 'memorial-plugin' ),
				),
				'trim'    => array(
					'type'    => 'checkbox',
					'label'   => __( 'Products: crop to the object', 'memorial-plugin' ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'product' => array(
					'type'    => 'select',
					'label'   => __( 'Products', 'memorial-plugin' ),
					'options' => $ratios,
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'work'    => array(
					'type'    => 'select',
					'label'   => __( 'Works and pages', 'memorial-plugin' ),
					'options' => $ratios,
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
				'pad'     => array(
					'type'    => 'select',
					'label'   => __( 'Margin around the product', 'memorial-plugin' ),
					'options' => memorial_plugin_photo_percents( 15, 1, 2 ),
					'show_if' => array( 'on', array( 1, '1', true ) ),
				),
			),
		),
		'clean'  => array(
			'type'   => 'group',
			'label'  => __( 'Clean-up', 'memorial-plugin' ),
			'fields' => array(
				'exif' => array(
					'type'  => 'checkbox',
					'label' => __( 'Remove EXIF (camera, time, place of the shot)', 'memorial-plugin' ),
				),
				'srgb' => array(
					'type'  => 'checkbox',
					'label' => __( 'Convert to sRGB (colours the same in every browser; done in GitHub Actions)', 'memorial-plugin' ),
				),
			),
		),
		'report' => array(
			'type'   => 'group',
			'label'  => __( 'Report of bad photos', 'memorial-plugin' ),
			'help'   => __( 'Small, blurred, too dark or too light photos, repeats and photos without a description — a list below the settings, to take or choose better ones.', 'memorial-plugin' ),
			'fields' => array(
				'on' => array(
					'type'  => 'checkbox',
					'label' => __( 'Make the report', 'memorial-plugin' ),
				),
			),
		),
	);
}

/**
 * Defaults: everything off (the owner looks at the preview first); the background types as the owner chose.
 *
 * @return array
 */
function memorial_plugin_photos_defaults() {
	$bg = array(
		'on'    => 0,
		'color' => MEMORIAL_PLUGIN_PHOTO_BG,
	);
	foreach ( MEMORIAL_PLUGIN_PHOTO_BG_TYPES as $slug ) {
		$bg[ 'type_' . $slug ] = 1;
	}
	return array(
		'main'   => array(
			'on'      => 0,
			'product' => 1,
			'work'    => 1,
			'page'    => 0,
		),
		'color'  => array(
			'on'       => 0,
			'levels'   => 1,
			'wb'       => '50',
			'bright'   => '0',
			'contrast' => '5',
			'warmth'   => '5',
			'sat'      => '0',
			'sharp'    => '25',
		),
		'logo'   => array(
			'on'      => 0,
			'file'    => '',
			'size'    => '14',
			'opacity' => '70',
			'margin'  => '3',
			'min'     => 600,
		),
		'bg'     => $bg,
		'crop'   => array(
			'on'      => 0,
			'trim'    => 1,
			'product' => '3:4',
			'work'    => '',
			'pad'     => '6',
		),
		'clean'  => array(
			'exif' => 1,
			'srgb' => 1,
		),
		'report' => array( 'on' => 1 ),
	);
}

/**
 * Settings (saved over the defaults).
 *
 * @return array
 */
function memorial_plugin_photos() {
	return memorial_plugin_settings( 'mem_photos' );
}

add_filter(
	'memorial_plugin_admin_pages',
	static function ( $pages ) {
		$pages['mem_photos'] = array(
			'slug'    => 'mem-photos',
			'title'   => __( 'Photo processing', 'memorial-plugin' ),
			'schema'  => 'memorial_plugin_photos_schema',
			'preview' => 'photos',
			'intro'   => '<p>' . esc_html__( 'One style for the photos of the site. Every change is seen at once in the preview on the right — before saving. After saving the photos are processed in the background, a few a minute; the originals are kept, and everything can be returned.', 'memorial-plugin' ) . '</p>',
		);
		return $pages;
	}
);

/* ---------- Which photos ---------- */

/**
 * The photos the settings apply to: attachment ID => [kind product|work|page, product type slug].
 * Without the switches of “Processing” — every photo that could be processed (for the report and the preview).
 *
 * @param bool $only_chosen Only the kinds chosen in “Processing”.
 * @return array<int,array{0:string,1:string}>
 */
function memorial_plugin_photo_scope( $only_chosen = true ) {
	global $wpdb;
	$s    = memorial_plugin_photos();
	$want = array(
		'product' => ! $only_chosen || ! empty( $s['main']['product'] ),
		'work'    => ! $only_chosen || ! empty( $s['main']['work'] ),
		'page'    => ! $only_chosen || ! empty( $s['main']['page'] ),
	);
	$types = array();
	$out   = array();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one query for every photo of the site.
	$rows = $wpdb->get_results( "SELECT p.ID, p.post_type, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_status IN ('publish','draft','private','future') AND p.post_type IN ('mem_product','mem_work','page') AND m.meta_key IN ('_thumbnail_id','_mem_gallery','_mem_photos') AND m.meta_value <> ''" );
	foreach ( $rows as $r ) {
		$kind = 'mem_product' === $r->post_type ? 'product' : ( 'mem_work' === $r->post_type ? 'work' : 'page' );
		if ( empty( $want[ $kind ] ) || ( 'page' === $kind && '_thumbnail_id' !== $r->meta_key ) ) {
			continue;
		}
		$type = '';
		if ( 'product' === $kind ) {
			if ( ! isset( $types[ $r->ID ] ) ) {
				$cat              = function_exists( 'memorial_plugin_product_cat' ) ? memorial_plugin_product_cat( (int) $r->ID ) : null;
				$up               = $cat ? get_ancestors( $cat->term_id, 'mem_cat' ) : array();
				$root             = $up ? get_term( (int) $up[ count( $up ) - 1 ], 'mem_cat' ) : $cat;
				$types[ $r->ID ] = $root && ! is_wp_error( $root ) ? (string) $root->slug : '';
			}
			$type = $types[ $r->ID ];
		}
		foreach ( array_filter( array_map( 'absint', explode( ',', (string) $r->meta_value ) ) ) as $a ) {
			// A photo of a product and of a page: the product wins (the stricter style).
			if ( ! isset( $out[ $a ] ) || 'product' === $kind ) {
				$out[ $a ] = array( $kind, $type );
			}
		}
	}
	$never = array_filter( array( (int) get_option( 'site_logo' ), (int) get_option( 'site_icon' ) ) );
	foreach ( array_keys( $out ) as $a ) {
		if ( in_array( $a, $never, true ) || get_post_meta( $a, '_mem_photo_skip', true ) || ! wp_attachment_is_image( $a ) ) {
			unset( $out[ $a ] );
		}
	}
	return $out;
}

/**
 * What is done to one photo: the effective settings, without the parts that do not apply to it.
 *
 * @param string $kind product|work|page.
 * @param string $type Product type slug.
 * @return array
 */
function memorial_plugin_photo_recipe( $kind, $type ) {
	$s = memorial_plugin_photos();
	$r = array();
	if ( ! empty( $s['color']['on'] ) ) {
		$r['color'] = array_intersect_key( $s['color'], array_flip( array( 'levels', 'wb', 'bright', 'contrast', 'warmth', 'sat', 'sharp' ) ) );
	}
	$bg = 'product' === $kind && ! empty( $s['bg']['on'] ) && ! empty( $s['bg'][ 'type_' . $type ] );
	if ( $bg ) {
		$r['bg'] = memorial_plugin_photo_hex( (string) ( $s['bg']['color'] ?? '' ) );
	}
	if ( ! empty( $s['crop']['on'] ) ) {
		$ratio = (string) ( 'product' === $kind ? $s['crop']['product'] : $s['crop']['work'] );
		$trim  = 'product' === $kind && ! empty( $s['crop']['trim'] );
		if ( '' !== $ratio || $trim ) {
			$r['crop'] = array(
				'ratio' => $ratio,
				'trim'  => $trim,
				'pad'   => (int) $s['crop']['pad'],
			);
		}
	}
	if ( ! empty( $s['logo']['on'] ) && memorial_plugin_photo_logo_file() ) {
		$r['logo'] = array(
			'size'    => (int) $s['logo']['size'],
			'opacity' => (int) $s['logo']['opacity'],
			'margin'  => (int) $s['logo']['margin'],
			'min'     => (int) $s['logo']['min'],
			'file'    => md5( memorial_plugin_photo_logo_file() . filemtime( memorial_plugin_photo_logo_file() ) ),
		);
	}
	if ( ! empty( $s['clean']['srgb'] ) ) {
		$r['srgb'] = 1;
	}
	if ( ! empty( $s['clean']['exif'] ) ) {
		$r['exif'] = 1;
	}
	return $r;
}

/**
 * A colour #rrggbb (the background of the site when the value is not a colour).
 *
 * @param string $hex Colour.
 * @return string
 */
function memorial_plugin_photo_hex( $hex ) {
	$hex = strtolower( trim( $hex ) );
	return preg_match( '/^#[0-9a-f]{6}$/', $hex ) ? $hex : MEMORIAL_PLUGIN_PHOTO_BG;
}

/**
 * The logo file: the one of the screen, otherwise the logo of the site (a raster image), otherwise the icon of the site.
 *
 * @return string Path or ''.
 */
function memorial_plugin_photo_logo_file() {
	static $file = null;
	if ( null !== $file ) {
		return $file;
	}
	$file = '';
	$url  = (string) ( memorial_plugin_photos()['logo']['file'] ?? '' );
	$ids  = array_filter( array( $url ? attachment_url_to_postid( $url ) : 0, (int) get_option( 'site_logo' ), (int) get_option( 'site_icon' ) ) );
	foreach ( $ids as $id ) {
		$f = (string) get_attached_file( $id );
		if ( $f && is_file( $f ) && preg_match( '/\.(png|webp|jpe?g)$/i', $f ) ) {
			$file = $f;
			break;
		}
	}
	return $file;
}

/* ---------- The processing (GD) ---------- */

/**
 * Load an image file into GD (truecolor, with alpha).
 *
 * @param string $file Path.
 * @return GdImage|false
 */
function memorial_plugin_photo_load( $file ) {
	$data = is_file( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file.
	$im   = $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a broken file gives false.
	if ( ! $im ) {
		return false;
	}
	if ( ! imageistruecolor( $im ) ) {
		imagepalettetotruecolor( $im );
	}
	imagealphablending( $im, true );
	imagesavealpha( $im, true );
	return $im;
}

/**
 * A small copy for the statistics (the longest side at most 200 px) — the browser preview uses the same size.
 *
 * @param GdImage $im Image.
 * @return GdImage
 */
function memorial_plugin_photo_sample( $im ) {
	$w = imagesx( $im );
	$h = imagesy( $im );
	$k = min( 1, 200 / max( $w, $h ) );
	$sw = max( 1, (int) round( $w * $k ) );
	$sh = max( 1, (int) round( $h * $k ) );
	$s  = imagecreatetruecolor( $sw, $sh );
	imagealphablending( $s, false );
	imagesavealpha( $s, true );
	imagefill( $s, 0, 0, imagecolorallocatealpha( $s, 0, 0, 0, 127 ) );
	imagecopyresampled( $s, $im, 0, 0, 0, 0, $sw, $sh, $w, $h );
	return $s;
}

/**
 * The colour tables of the correction — memorial_plugin_photo_luts() and photo-preview.js build them the same way.
 *
 * @param GdImage $sample Small copy.
 * @param array   $c      Colour settings: levels, wb, bright, contrast, warmth.
 * @return array{0:int[],1:int[],2:int[]} R, G, B tables 0…255.
 */
function memorial_plugin_photo_luts( $sample, array $c ) {
	$w    = imagesx( $sample );
	$h    = imagesy( $sample );
	$hist = array( array_fill( 0, 256, 0 ), array_fill( 0, 256, 0 ), array_fill( 0, 256, 0 ) );
	$px   = array();
	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$v = imagecolorat( $sample, $x, $y );
			if ( ( ( $v >> 24 ) & 127 ) > 100 ) {
				continue; // transparent around a product with a new background — not counted.
			}
			$p = array( ( $v >> 16 ) & 255, ( $v >> 8 ) & 255, $v & 255 );
			++$hist[0][ $p[0] ];
			++$hist[1][ $p[1] ];
			++$hist[2][ $p[2] ];
			$px[] = $p;
		}
	}
	$n  = max( 1, count( $px ) );
	$lo = array( 0, 0, 0 );
	$hi = array( 255, 255, 255 );
	if ( ! empty( $c['levels'] ) ) {
		for ( $ch = 0; $ch < 3; $ch++ ) {
			$sum = 0;
			for ( $i = 0; $i < 256; $i++ ) {
				$sum += $hist[ $ch ][ $i ];
				if ( $sum > $n * 0.005 ) {
					$lo[ $ch ] = $i;
					break;
				}
			}
			$sum = 0;
			for ( $i = 255; $i >= 0; $i-- ) {
				$sum += $hist[ $ch ][ $i ];
				if ( $sum > $n * 0.005 ) {
					$hi[ $ch ] = $i;
					break;
				}
			}
			if ( $hi[ $ch ] - $lo[ $ch ] < 32 ) { // almost one tone (a plain background) — not stretched.
				$lo[ $ch ] = 0;
				$hi[ $ch ] = 255;
			}
		}
	}
	// Means after the levels — for the white balance (grey world).
	$mean = array( 0, 0, 0 );
	foreach ( $px as $p ) {
		for ( $ch = 0; $ch < 3; $ch++ ) {
			$mean[ $ch ] += ( $p[ $ch ] - $lo[ $ch ] ) * 255 / ( $hi[ $ch ] - $lo[ $ch ] );
		}
	}
	$mean = array_map( static fn( $m ) => max( 1, $m / $n ), $mean );
	$grey = array_sum( $mean ) / 3;
	$wb   = (int) ( $c['wb'] ?? 0 ) / 100;
	$b    = (int) ( $c['bright'] ?? 0 ) * 1.28;
	$k    = 1 + (int) ( $c['contrast'] ?? 0 ) / 100;
	$warm = (int) ( $c['warmth'] ?? 0 ) * 0.8;
	$luts = array();
	for ( $ch = 0; $ch < 3; $ch++ ) {
		$gain = 1 + ( $grey / $mean[ $ch ] - 1 ) * $wb;
		$t    = array();
		for ( $i = 0; $i < 256; $i++ ) {
			$v  = ( $i - $lo[ $ch ] ) * 255 / ( $hi[ $ch ] - $lo[ $ch ] );
			$v *= $gain;
			$v += $b;
			$v  = ( $v - 128 ) * $k + 128;
			$v += 0 === $ch ? $warm : ( 2 === $ch ? -$warm : 0 );
			$t[ $i ] = (int) max( 0, min( 255, round( $v ) ) );
		}
		$luts[] = $t;
	}
	return $luts;
}

/**
 * Colour correction in place: the tables, the saturation, the sharpness.
 *
 * @param GdImage $im Image.
 * @param array   $c  Colour settings.
 */
function memorial_plugin_photo_color( $im, array $c ) {
	[ $lr, $lg, $lb ] = memorial_plugin_photo_luts( memorial_plugin_photo_sample( $im ), $c );
	$k = 1 + (int) ( $c['sat'] ?? 0 ) / 100;
	$w = imagesx( $im );
	$h = imagesy( $im );
	imagealphablending( $im, false );
	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$v = imagecolorat( $im, $x, $y );
			$r = $lr[ ( $v >> 16 ) & 255 ];
			$g = $lg[ ( $v >> 8 ) & 255 ];
			$b = $lb[ $v & 255 ];
			if ( 1.0 !== (float) $k ) {
				$l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
				$r = (int) max( 0, min( 255, round( $l + ( $r - $l ) * $k ) ) );
				$g = (int) max( 0, min( 255, round( $l + ( $g - $l ) * $k ) ) );
				$b = (int) max( 0, min( 255, round( $l + ( $b - $l ) * $k ) ) );
			}
			imagesetpixel( $im, $x, $y, ( $v & 0x7F000000 ) | ( $r << 16 ) | ( $g << 8 ) | $b );
		}
	}
	$a = (int) ( $c['sharp'] ?? 0 ) / 100 * 0.5;
	if ( $a > 0 ) {
		imageconvolution(
			$im,
			array(
				array( 0, -$a, 0 ),
				array( -$a, 1 + 4 * $a, -$a ),
				array( 0, -$a, 0 ),
			),
			1,
			0
		);
	}
	imagealphablending( $im, true );
}

/**
 * The background colour of a photo: the median of the border of the small copy.
 *
 * @param GdImage $sample Small copy.
 * @return int[] R, G, B.
 */
function memorial_plugin_photo_border( $sample ) {
	$w = imagesx( $sample );
	$h = imagesy( $sample );
	$c = array( array(), array(), array() );
	for ( $x = 0; $x < $w; $x++ ) {
		foreach ( array( 0, $h - 1 ) as $y ) {
			$v      = imagecolorat( $sample, $x, $y );
			$c[0][] = ( $v >> 16 ) & 255;
			$c[1][] = ( $v >> 8 ) & 255;
			$c[2][] = $v & 255;
		}
	}
	for ( $y = 0; $y < $h; $y++ ) {
		foreach ( array( 0, $w - 1 ) as $x ) {
			$v      = imagecolorat( $sample, $x, $y );
			$c[0][] = ( $v >> 16 ) & 255;
			$c[1][] = ( $v >> 8 ) & 255;
			$c[2][] = $v & 255;
		}
	}
	return array_map(
		static function ( $list ) {
			sort( $list );
			return $list[ intdiv( count( $list ), 2 ) ];
		},
		$c
	);
}

/**
 * Where the photo is put on its new canvas: [canvas w, canvas h, source x, y, w, h, x on the canvas, y on the canvas].
 * The same arithmetic as photo-preview.js.
 *
 * @param GdImage $im   Image.
 * @param array   $crop ratio ('3:4'…), trim, pad (%).
 * @param int[]   $bg   Background colour of the photo.
 * @return int[]|null Null — nothing to change.
 */
function memorial_plugin_photo_frame( $im, array $crop, array $bg ) {
	$w  = imagesx( $im );
	$h  = imagesy( $im );
	$bx = 0;
	$by = 0;
	$bw = $w;
	$bh = $h;
	if ( ! empty( $crop['trim'] ) ) {
		$s  = memorial_plugin_photo_sample( $im );
		$sw = imagesx( $s );
		$sh = imagesy( $s );
		$x0 = $sw;
		$y0 = $sh;
		$x1 = -1;
		$y1 = -1;
		for ( $y = 0; $y < $sh; $y++ ) {
			for ( $x = 0; $x < $sw; $x++ ) {
				$v = imagecolorat( $s, $x, $y );
				$a = ( $v >> 24 ) & 127;
				$d = max( abs( ( ( $v >> 16 ) & 255 ) - $bg[0] ), abs( ( ( $v >> 8 ) & 255 ) - $bg[1] ), abs( ( $v & 255 ) - $bg[2] ) );
				if ( $a < 100 && $d > 28 ) {
					$x0 = min( $x0, $x );
					$y0 = min( $y0, $y );
					$x1 = max( $x1, $x );
					$y1 = max( $y1, $y );
				}
			}
		}
		// An object of at least 5 % of the photo; otherwise the background is not plain — no trimming.
		if ( $x1 >= 0 && ( $x1 - $x0 + 1 ) * ( $y1 - $y0 + 1 ) >= 0.05 * $sw * $sh ) {
			$k  = $w / $sw;
			$bx = (int) floor( $x0 * $k );
			$by = (int) floor( $y0 * $k );
			$bw = (int) min( $w - $bx, ceil( ( $x1 - $x0 + 1 ) * $k ) );
			$bh = (int) min( $h - $by, ceil( ( $y1 - $y0 + 1 ) * $k ) );
			$p  = (int) round( max( $bw, $bh ) * (int) $crop['pad'] / 100 );
			$bx -= $p;
			$by -= $p;
			$bw += 2 * $p;
			$bh += 2 * $p;
		}
	}
	$ratio = (string) ( $crop['ratio'] ?? '' );
	if ( preg_match( '/^(\d+):(\d+)$/', $ratio, $m ) ) {
		$r = (int) $m[1] / (int) $m[2];
		if ( empty( $crop['trim'] ) ) {
			// Works: cut to the proportions around the middle, nothing is added.
			if ( $bw / $bh > $r ) {
				$nw  = (int) round( $bh * $r );
				$bx += intdiv( $bw - $nw, 2 );
				$bw  = $nw;
			} else {
				$nh  = (int) round( $bw / $r );
				$by += intdiv( $bh - $nh, 2 );
				$bh  = $nh;
			}
		} elseif ( $bw / $bh > $r ) {
			$nh  = (int) round( $bw / $r );
			$by -= intdiv( $nh - $bh, 2 );
			$bh  = $nh;
		} else {
			$nw  = (int) round( $bh * $r );
			$bx -= intdiv( $nw - $bw, 2 );
			$bw  = $nw;
		}
	}
	if ( 0 === $bx && 0 === $by && $bw === $w && $bh === $h ) {
		return null;
	}
	// The source part inside the photo and its place on the canvas.
	$sx = max( 0, $bx );
	$sy = max( 0, $by );
	$ex = min( $w, $bx + $bw );
	$ey = min( $h, $by + $bh );
	return array( $bw, $bh, $sx, $sy, $ex - $sx, $ey - $sy, $sx - $bx, $sy - $by );
}

/**
 * The logo in the lower right corner, in place.
 *
 * @param GdImage $im   Image.
 * @param array   $logo size, opacity, margin (%).
 */
function memorial_plugin_photo_logo( $im, array $logo ) {
	static $src = null;
	if ( null === $src ) {
		$src = memorial_plugin_photo_load( memorial_plugin_photo_logo_file() );
	}
	if ( ! $src ) {
		return;
	}
	$w  = imagesx( $im );
	$h  = imagesy( $im );
	$lw = max( 1, (int) round( $w * (int) $logo['size'] / 100 ) );
	$lh = max( 1, (int) round( imagesy( $src ) * $lw / imagesx( $src ) ) );
	$m  = (int) round( $w * (int) $logo['margin'] / 100 );
	$l  = imagecreatetruecolor( $lw, $lh );
	imagealphablending( $l, false );
	imagesavealpha( $l, true );
	imagefill( $l, 0, 0, imagecolorallocatealpha( $l, 0, 0, 0, 127 ) );
	imagecopyresampled( $l, $src, 0, 0, 0, 0, $lw, $lh, imagesx( $src ), imagesy( $src ) );
	// Opacity: every pixel of the logo gets more transparent.
	$o = max( 0, min( 100, (int) $logo['opacity'] ) ) / 100;
	for ( $y = 0; $y < $lh; $y++ ) {
		for ( $x = 0; $x < $lw; $x++ ) {
			$v = imagecolorat( $l, $x, $y );
			$a = 127 - (int) round( ( 127 - ( ( $v >> 24 ) & 127 ) ) * $o );
			imagesetpixel( $l, $x, $y, ( $a << 24 ) | ( $v & 0xFFFFFF ) );
		}
	}
	imagealphablending( $im, true );
	imagecopy( $im, $l, $w - $lw - $m, $h - $lh - $m, 0, 0, $lw, $lh );
}

/**
 * Save an image in the format of its file name (EXIF is never written by GD).
 *
 * @param GdImage $im   Image.
 * @param string  $file Path.
 * @return bool
 */
function memorial_plugin_photo_save( $im, $file ) {
	$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	if ( 'png' === $ext ) {
		return imagepng( $im, $file, 7 );
	}
	if ( 'webp' === $ext ) {
		return imagewebp( $im, $file, 84 );
	}
	return imagejpeg( $im, $file, 86 );
}

/**
 * Process one photo: a new file next to the original, the attachment points to it; the old processed files go.
 *
 * @param int    $id   Attachment ID.
 * @param string $kind product|work|page.
 * @param string $type Product type slug.
 * @return string|WP_Error 'done', 'wait' (the background is not ready yet), 'same'.
 */
function memorial_plugin_photo_process( $id, $kind, $type ) {
	$recipe = memorial_plugin_photo_recipe( $kind, $type );
	$ver    = substr( md5( wp_json_encode( $recipe ) ), 0, 8 );
	if ( get_post_meta( $id, '_mem_photo_ver', true ) === $ver ) {
		return 'same';
	}
	$orig = memorial_plugin_photo_original( $id );
	if ( ! $orig ) {
		return new WP_Error( 'memorial_plugin_photo_file', 'no file' );
	}
	// The prepared copy of GitHub Actions (background, sRGB) — when it is needed and ready.
	$base  = $orig['file'];
	$stage = memorial_plugin_photo_stage_file( $id, $recipe );
	if ( ( isset( $recipe['bg'] ) || isset( $recipe['srgb'] ) ) && $stage ) {
		$base = $stage;
	} elseif ( isset( $recipe['bg'] ) && ! $stage ) {
		return 'wait';
	}
	$im = memorial_plugin_photo_load( $base );
	if ( ! $im ) {
		return new WP_Error( 'memorial_plugin_photo_load', 'cannot read' );
	}
	$bg = isset( $recipe['bg'] ) ? sscanf( $recipe['bg'], '#%02x%02x%02x' ) : memorial_plugin_photo_border( memorial_plugin_photo_sample( $im ) );
	// With a new background the colour of the object is corrected first, then it is put on the background colour.
	if ( isset( $recipe['bg'], $recipe['color'] ) ) {
		memorial_plugin_photo_color( $im, $recipe['color'] );
	}
	$f = null;
	if ( isset( $recipe['crop'] ) ) {
		$f = memorial_plugin_photo_frame( $im, $recipe['crop'], $bg );
		if ( $f ) {
			$c = imagecreatetruecolor( $f[0], $f[1] );
			imagefill( $c, 0, 0, imagecolorallocate( $c, $bg[0], $bg[1], $bg[2] ) );
			imagecopy( $c, $im, $f[6], $f[7], $f[2], $f[3], $f[4], $f[5] );
			$im = $c;
		}
	}
	if ( isset( $recipe['bg'] ) && ! $f ) {
		// The copy of Actions is transparent around the object: on the background colour.
		$c = imagecreatetruecolor( imagesx( $im ), imagesy( $im ) );
		imagefill( $c, 0, 0, imagecolorallocate( $c, $bg[0], $bg[1], $bg[2] ) );
		imagealphablending( $c, true );
		imagecopy( $c, $im, 0, 0, 0, 0, imagesx( $im ), imagesy( $im ) );
		$im = $c;
	}
	if ( isset( $recipe['color'] ) && ! isset( $recipe['bg'] ) ) {
		memorial_plugin_photo_color( $im, $recipe['color'] );
	}
	$dir  = dirname( $orig['file'] );
	$name = pathinfo( $orig['file'], PATHINFO_FILENAME );
	$ext  = strtolower( pathinfo( $orig['file'], PATHINFO_EXTENSION ) );
	$new  = $dir . '/' . preg_replace( '/-m[0-9a-f]{8}$/', '', $name ) . '-m' . $ver . '.' . ( 'png' === $ext && ! isset( $recipe['bg'] ) ? 'png' : ( 'png' === $ext ? 'webp' : $ext ) );
	if ( ! memorial_plugin_photo_save( $im, $new ) ) {
		return new WP_Error( 'memorial_plugin_photo_save', 'cannot write' );
	}
	$old = memorial_plugin_photo_files( $id );
	// The sizes from the processed photo without the logo, then the logo on the big ones.
	update_attached_file( $id, $new );
	if ( ! function_exists( 'wp_create_image_subsizes' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$meta = wp_create_image_subsizes( $new, $id );
	if ( isset( $recipe['logo'] ) ) {
		$files = array( $new => imagesx( $im ) );
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
			$files[ $dir . '/' . $size['file'] ] = (int) $size['width'];
		}
		foreach ( $files as $file => $width ) {
			if ( $width >= (int) $recipe['logo']['min'] ) {
				$one = memorial_plugin_photo_load( $file );
				if ( $one ) {
					memorial_plugin_photo_logo( $one, $recipe['logo'] );
					memorial_plugin_photo_save( $one, $file );
				}
			}
		}
	}
	wp_update_attachment_metadata( $id, $meta );
	update_post_meta( $id, '_mem_photo_ver', $ver );
	// The processed files of the previous version (never the original).
	foreach ( $old['processed'] as $file ) {
		if ( is_file( $file ) && ! in_array( $file, memorial_plugin_photo_files( $id )['current'], true ) ) {
			wp_delete_file( $file );
		}
	}
	return 'done';
}

/**
 * The original of a photo: kept in _mem_photo_orig at the first processing — [file, metadata].
 *
 * @param int $id Attachment ID.
 * @return array{file:string,meta:array}|null
 */
function memorial_plugin_photo_original( $id ) {
	$saved = get_post_meta( $id, '_mem_photo_orig', true );
	if ( is_array( $saved ) && ! empty( $saved['file'] ) ) {
		$file = path_join( wp_get_upload_dir()['basedir'], $saved['file'] );
		return is_file( $file ) ? array(
			'file' => $file,
			'meta' => (array) $saved['meta'],
		) : null;
	}
	$file = (string) get_attached_file( $id );
	if ( ! $file || ! is_file( $file ) ) {
		return null;
	}
	update_post_meta(
		$id,
		'_mem_photo_orig',
		array(
			'file' => (string) get_post_meta( $id, '_wp_attached_file', true ),
			'meta' => (array) wp_get_attachment_metadata( $id ),
		)
	);
	return array(
		'file' => $file,
		'meta' => (array) wp_get_attachment_metadata( $id ),
	);
}

/**
 * Files of a photo: the current ones (the attached file and its sizes) and the processed ones (…-m<hash>…).
 *
 * @param int $id Attachment ID.
 * @return array{current:string[],processed:string[]}
 */
function memorial_plugin_photo_files( $id ) {
	$file    = (string) get_attached_file( $id );
	$dir     = dirname( $file );
	$meta    = (array) wp_get_attachment_metadata( $id );
	$current = array( $file );
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $s ) {
		$current[] = $dir . '/' . $s['file'];
	}
	$processed = array_values( array_filter( $current, static fn( $f ) => (bool) preg_match( '/-m[0-9a-f]{8}(-\d+x\d+)?\.[a-z0-9]+$/i', $f ) ) );
	return array(
		'current'   => $current,
		'processed' => $processed,
	);
}

/**
 * Return the original: the attachment points to its first file again, the processed files go.
 *
 * @param int $id Attachment ID.
 * @return bool
 */
function memorial_plugin_photo_restore( $id ) {
	$saved = get_post_meta( $id, '_mem_photo_orig', true );
	if ( ! is_array( $saved ) || empty( $saved['file'] ) ) {
		return false;
	}
	$processed = memorial_plugin_photo_files( $id )['processed'];
	update_post_meta( $id, '_wp_attached_file', $saved['file'] );
	wp_update_attachment_metadata( $id, (array) $saved['meta'] );
	foreach ( $processed as $file ) {
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}
	delete_post_meta( $id, '_mem_photo_ver' );
	delete_post_meta( $id, '_mem_photo_orig' );
	return true;
}

/**
 * The prepared copy of GitHub Actions for this recipe (background colour, sRGB), if it is there.
 *
 * @param int   $id     Attachment ID.
 * @param array $recipe Recipe.
 * @return string Path or ''.
 */
function memorial_plugin_photo_stage_file( $id, array $recipe ) {
	$key  = memorial_plugin_photo_stage_key( $recipe );
	$file = wp_get_upload_dir()['basedir'] . '/memorial-photos/stage/' . (int) $id . '-' . $key . '.png';
	return is_file( $file ) ? $file : '';
}

/**
 * Key of the prepared copy: what GitHub Actions does to it.
 *
 * @param array $recipe Recipe.
 * @return string
 */
function memorial_plugin_photo_stage_key( array $recipe ) {
	return substr( md5( ( $recipe['bg'] ?? '' ) . '|' . ( isset( $recipe['srgb'] ) ? 's' : '' ) ), 0, 8 );
}

/* ---------- The queue (WP-Cron, a few photos a minute) ---------- */

add_filter(
	'cron_schedules', // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- a short queue, stops when empty.
	static function ( $s ) {
		$s['memorial_plugin_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => 'Memorial: every minute',
		);
		return $s;
	}
);

/**
 * One portion: up to 20 seconds of work. Switched off — the processed photos get their originals back.
 *
 * @return array{done:int,wait:int,left:int}
 */
function memorial_plugin_photo_batch() {
	if ( get_transient( 'memorial_plugin_photo_lock' ) ) {
		return array(
			'done' => 0,
			'wait' => 0,
			'left' => -1,
		);
	}
	set_transient( 'memorial_plugin_photo_lock', 1, 2 * MINUTE_IN_SECONDS );
	$start = microtime( true );
	$s     = memorial_plugin_photos();
	$scope = ! empty( $s['main']['on'] ) ? memorial_plugin_photo_scope() : array();
	$done  = 0;
	$wait  = 0;
	$left  = 0;
	// Processed photos that are out of the scope now (switched off, kind not chosen, marked “Do not process”).
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the processed photos.
	foreach ( $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mem_photo_orig'" ) as $id ) {
		if ( ! isset( $scope[ (int) $id ] ) ) {
			if ( microtime( true ) - $start > 20 ) {
				++$left;
				continue;
			}
			memorial_plugin_photo_restore( (int) $id );
			++$done;
		}
	}
	foreach ( $scope as $id => [ $kind, $type ] ) {
		$ver = substr( md5( wp_json_encode( memorial_plugin_photo_recipe( $kind, $type ) ) ), 0, 8 );
		if ( get_post_meta( $id, '_mem_photo_ver', true ) === $ver ) {
			continue;
		}
		if ( microtime( true ) - $start > 20 ) {
			++$left;
			continue;
		}
		$r = memorial_plugin_photo_process( $id, $kind, $type );
		if ( 'wait' === $r ) {
			++$wait;
		} elseif ( 'done' === $r ) {
			++$done;
		}
	}
	if ( ! empty( $s['report']['on'] ) && ( $done || ! get_option( 'memorial_plugin_photos_report' ) ) && microtime( true ) - $start < 40 ) {
		update_option( 'memorial_plugin_photos_report', memorial_plugin_photo_report(), false );
	}
	update_option(
		'memorial_plugin_photos_state',
		array(
			'time' => time(),
			'done' => $done,
			'wait' => $wait,
			'left' => $left,
		),
		false
	);
	if ( ! $left && ! $wait ) {
		wp_clear_scheduled_hook( 'memorial_plugin_photos' );
	}
	delete_transient( 'memorial_plugin_photo_lock' );
	if ( $done && function_exists( 'memorial_plugin_page_cache_clear' ) ) {
		memorial_plugin_page_cache_clear();
	}
	return array(
		'done' => $done,
		'wait' => $wait,
		'left' => $left,
	);
}
add_action( 'memorial_plugin_photos', 'memorial_plugin_photo_batch' );

/** Start the queue (after a save of the settings, after a new photo of a product or a work). */
function memorial_plugin_photo_queue() {
	if ( ! wp_next_scheduled( 'memorial_plugin_photos' ) ) {
		wp_schedule_event( time() + 30, 'memorial_plugin_minute', 'memorial_plugin_photos' );
	}
}
add_action( 'update_option_mem_photos', 'memorial_plugin_photo_queue' );
add_action( 'add_option_mem_photos', 'memorial_plugin_photo_queue' );
foreach ( array( 'save_post_mem_product', 'save_post_mem_work' ) as $memorial_plugin_hook ) {
	add_action(
		$memorial_plugin_hook,
		static function () {
			if ( ! empty( memorial_plugin_photos()['main']['on'] ) ) {
				memorial_plugin_photo_queue();
			}
		}
	);
}

/* ---------- The report ---------- */

/**
 * Bad photos: small, blurred, too dark or light, repeats, without a description.
 *
 * @return array<int,array{id:int,kind:string,problems:string[]}>
 */
function memorial_plugin_photo_report() {
	$out    = array();
	$hashes = array();
	foreach ( memorial_plugin_photo_scope( false ) as $id => [ $kind ] ) {
		$orig = memorial_plugin_photo_original_peek( $id );
		$meta = (array) ( $orig['meta'] ?? array() );
		$p    = array();
		if ( (int) ( $meta['width'] ?? 0 ) && (int) $meta['width'] < 1000 ) {
			$p[] = 'small';
		}
		if ( '' === trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) {
			$p[] = 'alt';
		}
		$im = $orig ? memorial_plugin_photo_load( $orig['file'] ) : false;
		if ( $im ) {
			$s = memorial_plugin_photo_sample( $im );
			[ $luma, $sharp, $hash ] = memorial_plugin_photo_measure( $s );
			if ( $luma < 70 ) {
				$p[] = 'dark';
			} elseif ( $luma > 225 ) {
				$p[] = 'light';
			}
			if ( $sharp < 40 ) {
				$p[] = 'blur';
			}
			foreach ( $hashes as $other => $h ) {
				if ( memorial_plugin_photo_hamming( $hash, $h ) <= 4 ) {
					$p[] = 'repeat:' . $other;
					// The first photo of the pair is marked too.
					$out[ $other ]               = $out[ $other ] ?? array(
						'id'       => $other,
						'kind'     => $kind,
						'problems' => array(),
					);
					$out[ $other ]['problems'][] = 'repeat:' . $id;
					break;
				}
			}
			$hashes[ $id ] = $hash;
		}
		if ( $p ) {
			$out[ $id ] = array(
				'id'       => $id,
				'kind'     => $kind,
				'problems' => $p,
			);
		}
	}
	return array_values( array_filter( $out, static fn( $r ) => ! empty( $r['problems'] ) ) );
}

/**
 * The original of a photo without saving anything (for the report).
 *
 * @param int $id Attachment ID.
 * @return array{file:string,meta:array}|null
 */
function memorial_plugin_photo_original_peek( $id ) {
	$saved = get_post_meta( $id, '_mem_photo_orig', true );
	if ( is_array( $saved ) && ! empty( $saved['file'] ) ) {
		return array(
			'file' => path_join( wp_get_upload_dir()['basedir'], $saved['file'] ),
			'meta' => (array) $saved['meta'],
		);
	}
	$file = (string) get_attached_file( $id );
	return $file ? array(
		'file' => $file,
		'meta' => (array) wp_get_attachment_metadata( $id ),
	) : null;
}

/**
 * Mean brightness, sharpness (variance of the Laplacian) and the difference hash of a small copy.
 *
 * @param GdImage $s Small copy.
 * @return array{0:float,1:float,2:string}
 */
function memorial_plugin_photo_measure( $s ) {
	$w = imagesx( $s );
	$h = imagesy( $s );
	$g = array();
	$sum = 0;
	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$v           = imagecolorat( $s, $x, $y );
			$l           = 0.299 * ( ( $v >> 16 ) & 255 ) + 0.587 * ( ( $v >> 8 ) & 255 ) + 0.114 * ( $v & 255 );
			$g[ $y ][ $x ] = $l;
			$sum        += $l;
		}
	}
	$n   = max( 1, $w * $h );
	$lap = array();
	for ( $y = 1; $y < $h - 1; $y++ ) {
		for ( $x = 1; $x < $w - 1; $x++ ) {
			$lap[] = $g[ $y - 1 ][ $x ] + $g[ $y + 1 ][ $x ] + $g[ $y ][ $x - 1 ] + $g[ $y ][ $x + 1 ] - 4 * $g[ $y ][ $x ];
		}
	}
	$m   = $lap ? array_sum( $lap ) / count( $lap ) : 0;
	$var = $lap ? array_sum( array_map( static fn( $v ) => ( $v - $m ) ** 2, $lap ) ) / count( $lap ) : 0;
	// dHash 9×8.
	$d = imagecreatetruecolor( 9, 8 );
	imagecopyresampled( $d, $s, 0, 0, 0, 0, 9, 8, $w, $h );
	$bits = '';
	for ( $y = 0; $y < 8; $y++ ) {
		for ( $x = 0; $x < 8; $x++ ) {
			$a     = imagecolorat( $d, $x, $y ) & 255;
			$b     = imagecolorat( $d, $x + 1, $y ) & 255;
			$bits .= $a > $b ? '1' : '0';
		}
	}
	return array( $sum / $n, $var, $bits );
}

/**
 * Different bits of two hashes.
 *
 * @param string $a Hash.
 * @param string $b Hash.
 * @return int
 */
function memorial_plugin_photo_hamming( $a, $b ) {
	return strlen( $a ) === strlen( $b ) ? count( array_diff_assoc( str_split( $a ), str_split( $b ) ) ) : 64;
}

/* ---------- REST: samples for the preview, the report, return all ---------- */

add_action(
	'rest_api_init',
	static function () {
		$can = static fn() => function_exists( 'memorial_plugin_can_screen' ) ? memorial_plugin_can_screen( 'mem_photos' ) : current_user_can( 'manage_options' );
		register_rest_route(
			'memorial-plugin/v1',
			'/photos/samples',
			array(
				'methods'             => 'GET',
				'permission_callback' => $can,
				'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( memorial_plugin_photo_samples( (int) $r['id'] ) ),
			)
		);
		register_rest_route(
			'memorial-plugin/v1',
			'/photos/report',
			array(
				'methods'             => 'GET',
				'permission_callback' => $can,
				'callback'            => static function () {
					$state = get_option( 'memorial_plugin_photos_state', array() );
					return rest_ensure_response(
						array(
							'items'     => array_map(
								static fn( $r ) => $r + array(
									'title' => get_the_title( $r['id'] ),
									'thumb' => (string) wp_get_attachment_image_url( $r['id'], 'thumbnail' ),
									'edit'  => get_edit_post_link( $r['id'], 'raw' ),
									'width' => (int) ( ( memorial_plugin_photo_original_peek( $r['id'] )['meta']['width'] ) ?? 0 ),
								),
								(array) get_option( 'memorial_plugin_photos_report', array() )
							),
							'state'     => $state,
							'processed' => count( memorial_plugin_photo_processed_ids() ),
							'scope'     => count( memorial_plugin_photo_scope() ),
						)
					);
				},
			)
		);
		register_rest_route(
			'memorial-plugin/v1',
			'/photos/restore-all',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'callback'            => static function () {
					$opt               = (array) get_option( 'mem_photos', array() );
					$opt['main']       = (array) ( $opt['main'] ?? array() );
					$opt['main']['on'] = 0;
					update_option( 'mem_photos', $opt );
					memorial_plugin_photo_queue();
					return rest_ensure_response( array( 'ok' => true ) );
				},
			)
		);
	}
);

/**
 * IDs of the processed photos.
 *
 * @return int[]
 */
function memorial_plugin_photo_processed_ids() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the processed photos.
	return array_map( 'intval', $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mem_photo_orig'" ) );
}

/**
 * Photos for the preview: the chosen one and a few of every kind — the original, the kind, the product type and the
 * background sample of GitHub Actions; plus the logo.
 *
 * @param int $chosen A photo chosen on the screen.
 * @return array
 */
function memorial_plugin_photo_samples( $chosen = 0 ) {
	$scope = memorial_plugin_photo_scope( false );
	$pick  = array();
	if ( $chosen && isset( $scope[ $chosen ] ) ) {
		$pick[] = $chosen;
	}
	$by = array();
	foreach ( $scope as $id => [ $kind, $type ] ) {
		$by[ $kind . '|' . $type ][] = $id;
	}
	foreach ( $by as $ids ) {
		$pick = array_merge( $pick, array_slice( $ids, 0, 2 ) );
	}
	$pick  = array_slice( array_values( array_unique( $pick ) ), 0, 14 );
	$up    = wp_get_upload_dir();
	$items = array();
	foreach ( $pick as $id ) {
		[ $kind, $type ] = $scope[ $id ];
		$orig            = memorial_plugin_photo_original_peek( $id );
		if ( ! $orig ) {
			continue;
		}
		// The original in a size for the screen (~1024 px), not the processed file.
		$url  = str_replace( $up['basedir'], $up['baseurl'], $orig['file'] );
		$dir  = dirname( $url );
		$best = '';
		$bw   = 0;
		foreach ( (array) ( $orig['meta']['sizes'] ?? array() ) as $s ) {
			if ( $s['width'] >= 700 && ( ! $bw || $s['width'] < $bw ) ) {
				$best = $dir . '/' . $s['file'];
				$bw   = (int) $s['width'];
			}
		}
		$preview = $up['basedir'] . '/memorial-photos/preview/' . $id . '.png';
		$items[] = array(
			'id'    => $id,
			'title' => get_the_title( $id ),
			'kind'  => $kind,
			'type'  => $type,
			'url'   => $best ? $best : $url,
			'bg'    => is_file( $preview ) ? $up['baseurl'] . '/memorial-photos/preview/' . $id . '.png?v=' . filemtime( $preview ) : '',
		);
	}
	$logo = memorial_plugin_photo_logo_file();
	return array(
		'items' => $items,
		'logo'  => $logo ? str_replace( $up['basedir'], $up['baseurl'], $logo ) : '',
		'all'   => array_map(
			static fn( $id ) => array(
				'id'    => $id,
				'title' => get_the_title( $id ),
				'kind'  => $scope[ $id ][0],
			),
			array_keys( $scope )
		),
	);
}

/* «Не обрабатывать» у фото в медиатеке. */
add_filter(
	'attachment_fields_to_edit',
	static function ( $fields, $post ) {
		if ( ! wp_attachment_is_image( $post ) ) {
			return $fields;
		}
		$fields['mem_photo_skip'] = array(
			'label' => __( 'Photo processing', 'memorial-plugin' ),
			'input' => 'html',
			'html'  => '<label><input type="checkbox" name="attachments[' . (int) $post->ID . '][mem_photo_skip]" value="1"' . checked( (bool) get_post_meta( $post->ID, '_mem_photo_skip', true ), true, false ) . '> ' . esc_html__( 'Do not process', 'memorial-plugin' ) . '</label>',
		);
		return $fields;
	},
	10,
	2
);
add_filter(
	'attachment_fields_to_save',
	static function ( $post, $attachment ) {
		if ( ! current_user_can( 'edit_post', $post['ID'] ) ) {
			return $post;
		}
		if ( ! empty( $attachment['mem_photo_skip'] ) ) {
			update_post_meta( $post['ID'], '_mem_photo_skip', 1 );
		} else {
			delete_post_meta( $post['ID'], '_mem_photo_skip' );
		}
		if ( ! empty( memorial_plugin_photos()['main']['on'] ) ) {
			memorial_plugin_photo_queue();
		}
		return $post;
	},
	10,
	2
);
