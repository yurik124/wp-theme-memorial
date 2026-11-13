<?php
/**
 * Товар: фото (или логотип сайта, если фото нет).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$id = memorial_plugin_legacy_product_id( $block );
if ( ! $id ) {
	return;
}
?>
<div class="product__media">
<?php
if ( has_post_thumbnail( $id ) ) {
	echo get_the_post_thumbnail( $id, 'large', array( 'fetchpriority' => 'high' ) );
} else {
	echo '<span class="card__noimg">' . memorial_plugin_noimg_mark() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the logo: markup of the theme or an escaped <img>.
}
?>
</div>
