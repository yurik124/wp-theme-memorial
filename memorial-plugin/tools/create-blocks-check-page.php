<?php
/**
 * Для проверок в CI (wp eval-file): страница /blocks-check/ со всеми блоками плагина.
 * На сайт не выгружается (deploy.yml исключает tools/).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

$memorial_plugin_markup = '';
foreach ( array_keys( memorial_plugin_legacy_blocks() ) as $memorial_plugin_slug ) {
	$memorial_plugin_markup .= '<!-- wp:memorial-plugin/' . $memorial_plugin_slug . ' /-->' . "\n";
}
$memorial_plugin_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Blocks check',
		'post_name'    => 'blocks-check',
		'post_content' => $memorial_plugin_markup,
	)
);
echo is_wp_error( $memorial_plugin_id ) ? 'error: ' . esc_html( $memorial_plugin_id->get_error_message() ) : 'page ' . (int) $memorial_plugin_id . "\n";
