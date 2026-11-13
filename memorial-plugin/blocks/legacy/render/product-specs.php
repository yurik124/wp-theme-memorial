<?php
/**
 * Товар: характеристики и краткое описание (отрывок, если он задан).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$id = memorial_plugin_legacy_product_id( $block );
if ( ! $id ) {
	return;
}
?>
<dl class="specs">
	<?php
	foreach ( memorial_plugin_attrs() as $k => $label ) :
		$t = get_the_terms( $id, 'mem_' . $k );
		if ( ! $t || is_wp_error( $t ) ) {
			continue;
		}
		?>
	<dt><?php echo esc_html( $label ); ?></dt>
	<dd><?php echo esc_html( implode( ', ', wp_list_pluck( $t, 'name' ) ) ); ?></dd>
	<?php endforeach; ?>
	<?php if ( get_post_meta( $id, '_mem_on_order', true ) ) : ?>
		<dt><?php esc_html_e( 'Availability', 'memorial-plugin' ); ?></dt><dd><?php esc_html_e( 'Made to order', 'memorial-plugin' ); ?></dd>
	<?php endif; ?>
</dl>
<?php if ( has_excerpt( $id ) ) : ?>
	<div class="about__text"><?php echo wp_kses_post( wpautop( esc_html( get_the_excerpt( $id ) ) ) ); ?></div>
<?php endif; ?>
