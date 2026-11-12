<?php
/**
 * Contacts list.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit; /* Список контактов: все телефоны, почта, адреса с временем работы */ ?>
<ul class="clist">
	<?php foreach ( memorial_plugin_phones() as $p ) : ?>
	<li><?php echo memorial_plugin_icon( 'phone' ); ?><span>
		<a class="nowrap" href="tel:<?php echo esc_attr( memorial_plugin_tel_of( $p['num'] ) ); ?>"><?php echo esc_html( $p['num'] ); ?></a>
		<?php
		if ( ! empty( $p['note'] ) ) :
			?>
			<small class="clist__note"><?php echo esc_html( $p['note'] ); ?></small><?php endif; ?>
	</span></li>
	<?php endforeach; ?>
	<?php foreach ( memorial_plugin_emails() as $m ) : ?>
	<li><?php echo memorial_plugin_icon( 'mail' ); ?><a href="mailto:<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></a></li>
	<?php endforeach; ?>
	<?php $msg = memorial_plugin_messengers(); if ( $msg ) : ?>
	<li><?php echo memorial_plugin_icon( 'chat' ); ?><span class="clist__msg">
		<?php foreach ( $msg as $k => $url ) : ?>
			<?php
			$memorial_names = array(
				'max'      => 'MAX',
				'telegram' => 'Telegram',
				'whatsapp' => 'WhatsApp',
			);
			?>
		<a class="cb__m" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $memorial_names[ $k ] ?? $k ); ?></a>
		<?php endforeach; ?>
	</span></li>
	<?php endif; ?>
	<?php foreach ( memorial_plugin_addrs() as $a ) : ?>
	<li><?php echo memorial_plugin_icon( 'pin' ); ?><span>
		<?php echo esc_html( memorial_plugin_addr( $a['addr'] ) ); ?>
		<?php
		if ( ! empty( $a['hours'] ) ) :
			?>
			<small class="clist__note"><?php echo memorial_plugin_icon( 'clock' ) . esc_html( $a['hours'] ); ?></small><?php endif; ?>
	</span></li>
	<?php endforeach; ?>
</ul>
