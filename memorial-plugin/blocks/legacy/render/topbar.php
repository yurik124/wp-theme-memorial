<?php
/**
 * Полоса над шапкой: адрес (выбирается в настройках блока, по умолчанию первый), часы работы, ночной телефон.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$memorial_plugin_atts  = $block instanceof WP_Block ? (array) $block->attributes : array();
$memorial_plugin_addrs = memorial_plugin_addrs();
$a0                    = $memorial_plugin_addrs[ (int) ( $memorial_plugin_atts['address'] ?? 0 ) ] ?? ( $memorial_plugin_addrs[0] ?? null );
if ( ! $a0 ) {
	return;
}
?>
<div class="topbar">
	<div class="wrap topbar__in">
	<span><?php echo memorial_plugin_icon( 'pin' ) . esc_html( memorial_plugin_addr( $a0['addr'] ) ); ?></span>
	<?php
	if ( ! empty( $a0['hours'] ) && ( $memorial_plugin_atts['hours'] ?? true ) ) :
		?>
		<span><?php echo memorial_plugin_icon( 'clock' ) . esc_html( $a0['hours'] ); ?></span><?php endif; ?>
	<?php
	if ( memorial_plugin_night() && ( $memorial_plugin_atts['night'] ?? true ) ) :
		?>
		<span class="topbar__night"><?php echo memorial_plugin_icon( 'phone' ) . esc_html( memorial_plugin_night() ); ?></span><?php endif; ?>
	</div>
</div>
