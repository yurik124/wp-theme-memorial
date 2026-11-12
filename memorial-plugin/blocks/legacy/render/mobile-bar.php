<?php
/**
 * Нижняя панель на телефоне: «Позвонить» и «Написать в MAX» (или «<?php esc_html_e( 'Call me back', 'memorial-plugin' ); ?>»).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
if ( ! memorial_plugin_tel() ) {
	return;
}
$max = memorial_plugin_max_url();
?>
<nav class="mbar" aria-label="<?php esc_attr_e( 'Contact', 'memorial-plugin' ); ?>">
	<a class="btn btn--gold mbar__call" href="tel:<?php echo esc_attr( memorial_plugin_tel() ); ?>"><?php echo memorial_plugin_icon( 'phone' ); ?><?php esc_html_e( 'Call', 'memorial-plugin' ); ?></a>
	<?php if ( $max ) : ?>
	<a class="btn btn--line mbar__msg" href="<?php echo esc_url( $max ); ?>" target="_blank" rel="noopener"><?php echo memorial_plugin_icon( 'chat' ); ?><?php esc_html_e( 'Write in MAX', 'memorial-plugin' ); ?></a>
	<?php else : ?>
	<button class="btn btn--line mbar__msg" type="button" data-callback><?php esc_html_e( 'Call me back', 'memorial-plugin' ); ?></button>
	<?php endif; ?>
</nav>
