<?php
/**
 * Контакты: часы работы или ночная линия — значки в начале страницы.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$addrs = memorial_plugin_addrs();
$hours = $addrs[0]['hours'] ?? '';
?>
		<?php
		/* Часы есть у каждого адреса; наверху — только если не указано про ночные звонки */
		if ( $hours && ! memorial_plugin_night() ) :
			?>
		<span class="kontakty__badge"><?php echo memorial_plugin_icon( 'clock' ) . esc_html( $hours ); ?></span><?php endif; ?>
		<?php
		if ( memorial_plugin_night() ) :
			?>
			<span class="kontakty__badge kontakty__badge--night"><?php echo memorial_plugin_icon( 'phone' ) . esc_html( memorial_plugin_night() ); ?></span><?php endif; ?>
