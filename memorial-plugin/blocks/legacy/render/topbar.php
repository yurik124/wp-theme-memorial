<?php
/**
 * Полоса над шапкой: первый адрес, часы работы, ночной телефон.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$a0 = memorial_plugin_addrs()[0] ?? null;
if ( ! $a0 ) {
	return;
}
?>
<div class="topbar">
	<div class="wrap topbar__in">
	<span><?php echo memorial_plugin_icon( 'pin' ) . esc_html( memorial_plugin_addr( $a0['addr'] ) ); ?></span>
	<?php
	if ( ! empty( $a0['hours'] ) ) :
		?>
		<span><?php echo memorial_plugin_icon( 'clock' ) . esc_html( $a0['hours'] ); ?></span><?php endif; ?>
	<?php
	if ( memorial_plugin_night() ) :
		?>
		<span class="topbar__night"><?php echo memorial_plugin_icon( 'phone' ) . esc_html( memorial_plugin_night() ); ?></span><?php endif; ?>
	</div>
</div>
