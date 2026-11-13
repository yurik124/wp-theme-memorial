<?php
/**
 * Товар: кнопки «Позвонить» и «Перезвоните мне» с подсказкой.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
if ( ! memorial_plugin_legacy_product_id( $block ) ) {
	return;
}
?>
<div class="product__cta">
	<a class="btn btn--gold btn--lg" href="tel:<?php echo esc_attr( memorial_plugin_tel() ); ?>"><?php echo memorial_plugin_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG of the plugin. ?><span class="nowrap"><?php echo esc_html( memorial_plugin_opt( 'phone' ) ); ?></span></a>
	<button class="btn btn--line btn--lg" type="button" data-callback><?php esc_html_e( 'Call me back', 'memorial-plugin' ); ?></button>
</div>
<p class="product__hint"><?php esc_html_e( 'You can check the availability, sizes and terms by phone — we will help you choose.', 'memorial-plugin' ); ?></p>
