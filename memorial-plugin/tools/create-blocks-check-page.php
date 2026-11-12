<?php
/**
 * Для проверок в CI (wp eval-file): страница /blocks-check/ со всеми блоками плагина.
 * На сайт не выгружается (deploy.yml исключает tools/).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

$memorial_plugin_markup = '';
foreach ( array_merge( array_keys( memorial_plugin_legacy_blocks() ), array_keys( memorial_plugin_content_blocks() ), array_keys( memorial_plugin_home_blocks_list() ) ) as $memorial_plugin_slug ) {
	$memorial_plugin_markup .= '<!-- wp:memorial-plugin/' . $memorial_plugin_slug . ' /-->' . "\n";
}
$memorial_plugin_markup .= '<!-- wp:paragraph --><p>Years: <span class="memorial-plugin-value" data-value="years">STALE</span>.</p><!-- /wp:paragraph -->' . "\n";
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

// Page with old shortcodes for the content converter check (tests/e2e/convert.spec.js).
// The manual converter is checked on it, so the automatic conversion after an update (core/shortcodes-compat.php)
// is marked as done for this version and leaves the page alone.
update_option(
	MEMORIAL_PLUGIN_SHORTCODES_MIGRATION,
	array(
		'version'   => MEMORIAL_PLUGIN_VER,
		'converted' => array(),
		'failed'    => array(),
		'done'      => true,
		'seen'      => true,
	),
	false
);
$memorial_plugin_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Convert check',
		'post_name'    => 'convert-check',
		'post_content' => "<!-- wp:paragraph -->\n<p>In business for [mem_years] years, call [mem_phone].</p>\n<!-- /wp:paragraph -->\n\n"
			. "<!-- wp:shortcode -->\n[mem_cta title=\"Convert check heading\" text=\"Convert check text\"]\n<!-- /wp:shortcode -->",
		'meta_input'   => array( '_seopress_titles_title' => 'Convert check — [mem_years] years | %%sitetitle%%' ),
	)
);
echo is_wp_error( $memorial_plugin_id ) ? 'error: ' . esc_html( $memorial_plugin_id->get_error_message() ) : 'page ' . (int) $memorial_plugin_id . "\n";
