<?php
/**
 * Подвал: колонки «Адреса» и «Телефоны».
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( memorial_plugin_addrs() ) : ?>
<div class="footer__col">
	<p class="fnav__title"><?php esc_html_e( 'Addresses', 'memorial-plugin' ); ?></p>
	<?php
	// Микроданные организации для Яндекса — если на странице их ещё нет (seo/schema.php).
	$memorial_plugin_micro = memorial_plugin_org_micro_place( true );
	foreach ( memorial_plugin_addrs() as $i => $a ) :
		?>
	<p class="footer__addr"<?php echo memorial_plugin_org_micro_attrs( $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>><?php echo esc_html( memorial_plugin_addr( $a['addr'] ) ); ?>
		<?php
		if ( ! empty( $a['hours'] ) ) {
			echo '<br><small>' . esc_html( $a['hours'] ) . '</small>';}
		echo memorial_plugin_org_micro_fields( $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function.
		?>
	</p>
		<?php
	endforeach;
	if ( $memorial_plugin_micro ) {
		memorial_plugin_org_micro_place( false );
	}
	?>
</div>
<?php endif; ?>
<?php if ( memorial_plugin_phones() || memorial_plugin_emails() ) : ?>
<div class="footer__col">
	<p class="fnav__title"><?php esc_html_e( 'Phones', 'memorial-plugin' ); ?></p>
	<?php foreach ( memorial_plugin_phones() as $i => $p ) : ?>
	<p class="footer__line"><a class="footer__tel<?php echo $i ? '' : ' footer__tel--main'; ?>" href="tel:<?php echo esc_attr( memorial_plugin_tel_of( $p['num'] ) ); ?>"><?php echo esc_html( $p['num'] ); ?></a>
		<?php
		if ( ! empty( $p['note'] ) ) {
			echo ' <small>' . esc_html( $p['note'] ) . '</small>';}
		?>
	</p>
	<?php endforeach; ?>
	<?php
	if ( memorial_plugin_night() ) :
		?>
		<p class="footer__night"><?php echo esc_html( memorial_plugin_night() ); ?></p><?php endif; ?>
	<?php foreach ( memorial_plugin_emails() as $m ) : ?>
	<p class="footer__line"><a class="footer__mail" href="mailto:<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></a></p>
	<?php endforeach; ?>
</div>
<?php endif; ?>
