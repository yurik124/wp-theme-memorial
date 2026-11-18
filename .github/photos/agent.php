<?php
/**
 * Agent of the «Фото — фон» workflow (.github/workflows/photos.yml): the workflow puts it into wp-content under a
 * random name with a key, calls it and deletes it (as the diagnostics do).
 *
 *  ?k=KEY&do=list  — what GitHub Actions has to prepare: photos that need a new background or an sRGB check
 *                    (pages/photo-tools.php decides), their original files, the key of the prepared copy, the colour;
 *                    plus the samples for the preview of the screen.
 *  ?k=KEY&do=mark  — POST JSON {ok:[id,…]}: photos already in sRGB without a new background — nothing to prepare;
 *                    then the queue of the site is started.
 */
// phpcs:ignoreFile -- a temporary file of the workflow, not a part of the plugin.
if ( ! isset( $_GET['k'] ) || '__KEY__' !== $_GET['k'] ) {
	http_response_code( 404 );
	exit;
}
require dirname( __DIR__ ) . '/wp-load.php';
header( 'Content-Type: application/json; charset=utf-8' );
if ( ! function_exists( 'memorial_plugin_photo_scope' ) ) {
	echo wp_json_encode( array( 'error' => 'no photo processing in the plugin' ) );
	exit;
}
$up = wp_get_upload_dir();
$do = $_GET['do'] ?? 'list';

if ( 'mark' === $do ) {
	$in = json_decode( (string) file_get_contents( 'php://input' ), true );
	foreach ( (array) ( $in['ok'] ?? array() ) as $row ) {
		update_post_meta( (int) $row['id'], '_mem_photo_srgb_ok', (string) $row['key'] );
	}
	memorial_plugin_photo_queue();
	echo wp_json_encode( array( 'ok' => true ) );
	exit;
}

$s     = memorial_plugin_photos();
$items = array();
$on    = ! empty( $s['main']['on'] );
foreach ( memorial_plugin_photo_scope() as $id => [ $kind, $type ] ) {
	if ( ! $on ) {
		break;
	}
	$r = memorial_plugin_photo_recipe( $kind, $type );
	if ( ! isset( $r['bg'] ) && ! isset( $r['srgb'] ) ) {
		continue;
	}
	$key = memorial_plugin_photo_stage_key( $r );
	if ( memorial_plugin_photo_stage_file( $id, $r ) || ( ! isset( $r['bg'] ) && get_post_meta( $id, '_mem_photo_srgb_ok', true ) === $key ) ) {
		continue;
	}
	$orig = memorial_plugin_photo_original_peek( $id );
	if ( ! $orig || ! is_file( $orig['file'] ) ) {
		continue;
	}
	// The biggest file there is: the original before WordPress made it smaller, if it is kept.
	$file = function_exists( 'wp_get_original_image_path' ) && ! get_post_meta( $id, '_mem_photo_orig', true ) ? (string) wp_get_original_image_path( $id ) : '';
	$file = $file && is_file( $file ) ? $file : $orig['file'];
	$items[] = array(
		'id'   => $id,
		'file' => ltrim( str_replace( $up['basedir'], '', $file ), '/' ),
		'key'  => $key,
		'bg'   => isset( $r['bg'] ),
		'srgb' => isset( $r['srgb'] ),
	);
}
// Samples for the preview: two photos of every product type whose background is replaced (also while switched off).
$preview = array();
$by      = array();
foreach ( memorial_plugin_photo_scope( false ) as $id => [ $kind, $type ] ) {
	if ( 'product' === $kind && ! empty( $s['bg'][ 'type_' . $type ] ) && count( $by[ $type ] ?? array() ) < 3 ) {
		$by[ $type ][] = $id;
		$orig          = memorial_plugin_photo_original_peek( $id );
		if ( $orig && is_file( $orig['file'] ) ) {
			$preview[] = array(
				'id'   => $id,
				'file' => ltrim( str_replace( $up['basedir'], '', $orig['file'] ), '/' ),
			);
		}
	}
}
echo wp_json_encode(
	array(
		'uploads' => $up['basedir'],
		'items'   => $items,
		'preview' => $preview,
	)
);
