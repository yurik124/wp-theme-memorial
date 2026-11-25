<?php
/**
 * Probe on the site copy (PROBE_CLI → `wp eval-file`): the prices were last checked 40 days ago and the benefits
 * last year — so the admin shows both reminders. Not deployed (tests/).
 *
 * @package memorial-plugin
 */

// phpcs:ignoreFile -- a probe script.
update_option( 'mem_prices_changed', time() - 40 * DAY_IN_SECONDS, false );
update_option( 'mem_benefits_checked', strtotime( '2025-06-01' ), false );
print_r( memorial_plugin_price_check_due() );
echo 'benefits: ' . memorial_plugin_benefits_lines() . "\n";
echo '2gis: ' . memorial_plugin_home_2gis() . "\n";
// A page with a “Cards” gallery of three photos of the media library — like «Почему выбирают „Мемориал“».
$ids  = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 3, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ) );
$figs = '';
foreach ( $ids as $id ) {
	$figs .= '<!-- wp:image {"id":' . $id . ',"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="' . esc_url( wp_get_attachment_image_url( $id, 'large' ) ) . '" alt="" class="wp-image-' . $id . '"/></figure><!-- /wp:image -->';
}
$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'probe-2611', 'post_title' => 'Проверка 26.11', 'post_content' => '<!-- wp:gallery {"linkTo":"none","className":"is-style-cards"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped is-style-cards">' . $figs . '</figure><!-- /wp:gallery -->' ) );
echo 'probe page: ' . $pid . ' photos: ' . implode( ',', $ids ) . "\n";
