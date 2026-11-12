<?php
/**
 * Шапка: главный телефон, «<?php esc_html_e( 'Call me back', 'memorial-plugin' ); ?>», значок звонка на телефоне.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
if ( ! memorial_plugin_tel() ) {
	return;
}
?>
<a class="header__phone" href="tel:<?php echo esc_attr( memorial_plugin_tel() ); ?>">
	<b class="nowrap"><?php echo esc_html( memorial_plugin_opt( 'phone' ) ); ?></b>
	<?php
	if ( memorial_plugin_night() ) :
		?>
		<small><?php esc_html_e( 'round the clock', 'memorial-plugin' ); ?></small><?php endif; ?>
</a>
<button class="btn btn--line header__cb" type="button" data-callback><?php esc_html_e( 'Call me back', 'memorial-plugin' ); ?></button>
<a class="header__call" href="tel:<?php echo esc_attr( memorial_plugin_tel() ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: phone */ __( 'Call %s', 'memorial-plugin' ), memorial_plugin_opt( 'phone' ) ) ); ?>"><?php echo memorial_plugin_icon( 'phone' ); ?></a>
