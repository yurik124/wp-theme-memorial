<?php
/**
 * Контакты: реквизиты компании.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$legal = memorial_plugin_legal_parts();
?>
	<?php if ( $legal ) : ?>
		<section class="kontakty__legal" aria-labelledby="k-legal">
		<h2 id="k-legal"><?php esc_html_e( 'Company details', 'memorial-plugin' ); ?></h2>
		<p><?php echo esc_html( implode( ' · ', $legal ) ); ?></p>
		</section>
	<?php endif; ?>
