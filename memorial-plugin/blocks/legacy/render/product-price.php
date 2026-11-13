<?php
/**
 * Товар: цена.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$id = memorial_plugin_legacy_product_id( $block );
if ( ! $id ) {
	return;
}
?>
<div class="product__price"><?php echo wp_kses_post( memorial_plugin_price_html( $id ) ); ?></div>
