<?php
/**
 * Товар целиком — для вставки выбранного товара на обычную страницу: фото, название, цена, характеристики,
 * кнопки звонка, описание, похожие товары. Собирается из тех же частей, что и шаблон товара
 * (templates/single-mem_product.html: блоки ядра + product-media, product-price, product-specs,
 * product-buy, product-related).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$id = memorial_plugin_legacy_post_id( $block );
if ( 'mem_product' !== get_post_type( $id ) ) {
	echo memorial_plugin_block_note( __( 'Product card: choose the product in the block settings (“What to show”). In the product template the block shows the product that is open.', 'memorial-plugin' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function.
	return;
}
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- markup of the parts, escaped in their files.
?>
<div class="product">
	<?php echo memorial_plugin_legacy_render( 'product-media', $block ); ?>
	<div class="product__info">
	<h1><?php echo esc_html( get_the_title( $id ) ); ?></h1>
	<?php
	echo memorial_plugin_legacy_render( 'product-price', $block );
	echo memorial_plugin_legacy_render( 'product-specs', $block );
	echo memorial_plugin_legacy_render( 'product-buy', $block );
	?>
	</div>
</div>
<?php
$text = (string) get_post_field( 'post_content', $id );
if ( '' !== trim( $text ) ) :
	?>
	<div class="content product__desc"><?php echo apply_filters( 'the_content', $text ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter. ?></div>
	<?php
endif;
echo memorial_plugin_legacy_render( 'product-related', $block );
