<?php
/**
 * Контакты: карточки «Позвонить», «Написать» и адреса с маршрутом.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$phones = memorial_plugin_phones();
$msg    = memorial_plugin_messengers();
$emails = memorial_plugin_emails();
$addrs  = memorial_plugin_addrs();
// Settings of the “Contact cards” block (core/contacts-more.php): which addresses, landmarks, the route link.
$atts = (array) ( $GLOBALS['memorial_plugin_cards_atts'] ?? array() );
$only = (string) ( $atts['only'] ?? 'all' );
if ( in_array( $only, array( 'shop', 'hall' ), true ) ) {
	$addrs = array_filter( $addrs, static fn( $a ) => ! empty( $a[ $only ] ) );
}
$show_notes = ! isset( $atts['landmarks'] ) || ! empty( $atts['landmarks'] );
$show_route = ! isset( $atts['route'] ) || ! empty( $atts['route'] );
$names  = array(
	'max'      => 'MAX',
	'telegram' => 'Telegram',
	'whatsapp' => 'WhatsApp',
);
$route  = fn( $a ) => 'https://yandex.ru/maps/?rtext=~' . rawurlencode( preg_replace( '/^\d{6},\s*/', '', $a ) ) . '&rtt=auto';
?>
	<div class="kgrid">
		<?php if ( $phones ) : ?>
		<section class="kcard kcard--dark" aria-labelledby="k-call">
		<h2 class="kcard__title" id="k-call"><?php echo memorial_plugin_icon( 'phone' ); ?><?php esc_html_e( 'Call', 'memorial-plugin' ); ?></h2>
		<ul class="kphones">
			<?php foreach ( $phones as $i => $p ) : ?>
			<li class="<?php echo 0 === $i ? 'is-main' : ''; ?>">
				<a href="tel:<?php echo esc_attr( memorial_plugin_tel_of( $p['num'] ) ); ?>"><?php echo esc_html( $p['num'] ); ?></a>
				<?php
				if ( ! empty( $p['note'] ) ) :
					?>
					<small><?php echo esc_html( $p['note'] ); ?></small><?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<button class="btn btn--gold kcard__btn" type="button" data-callback><?php esc_html_e( 'Call me back', 'memorial-plugin' ); ?></button>
		</section>
		<?php endif; ?>

		<?php if ( $msg || $emails ) : ?>
		<section class="kcard" aria-labelledby="k-write">
		<h2 class="kcard__title" id="k-write"><?php echo memorial_plugin_icon( 'chat' ); ?><?php esc_html_e( 'Write', 'memorial-plugin' ); ?></h2>
			<?php if ( $msg ) : ?>
			<div class="kmsg">
				<?php foreach ( $msg as $k => $url ) : ?>
				<a class="kmsg__btn kmsg__btn--<?php echo esc_attr( $k ); ?>" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Написать в <?php echo esc_html( $names[ $k ] ); ?></a>
			<?php endforeach; ?>
			</div>
		<?php endif; ?>
			<?php foreach ( $emails as $m ) : ?>
			<p class="kmail"><?php echo memorial_plugin_icon( 'mail' ); ?><a href="mailto:<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></a></p>
		<?php endforeach; ?>
		<p class="kcard__hint"><?php esc_html_e( 'You can send a photo, the text for a wreath ribbon or questions about documents — we will answer during working hours.', 'memorial-plugin' ); ?></p>
		</section>
		<?php endif; ?>

		<?php
		// Микроданные организации для Яндекса: адреса на «Контактах» — каждый филиал отдельно (seo/schema.php).
		$micro = memorial_plugin_org_micro_place( true );
		foreach ( $addrs as $i => $a ) :
			?>
		<section class="kcard kaddr"<?php echo memorial_plugin_org_micro_attrs( $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>>
		<h2 class="kcard__title"><?php echo memorial_plugin_icon( 'pin' ); ?><?php echo esc_html( ! empty( $a['title'] ) ? $a['title'] : memorial_plugin_short_addr( $a['addr'] ) ); ?></h2>
			<?php
			$kind = function_exists( 'memorial_plugin_addr_kind' ) ? memorial_plugin_addr_kind( $a ) : '';
			if ( '' !== $kind ) :
				?>
				<p class="kaddr__kind"><?php echo esc_html( $kind ); ?></p><?php endif; ?>
		<p class="kaddr__full"><?php echo esc_html( $a['addr'] ); ?></p>
			<?php
			$notes = $show_notes && function_exists( 'memorial_plugin_addr_notes' ) ? memorial_plugin_addr_notes( $a ) : array();
			if ( $notes ) :
				?>
				<p class="kaddr__notes"><?php echo esc_html( implode( ' · ', $notes ) ); ?></p><?php endif; ?>
			<?php
			if ( ! empty( $a['hours'] ) ) :
				?>
				<p class="kaddr__hours"><?php echo memorial_plugin_icon( 'clock' ) . esc_html( $a['hours'] ); ?></p><?php endif; ?>
			<?php if ( $show_route ) : ?>
		<a class="kaddr__route" href="<?php echo esc_url( $route( $a['addr'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions →', 'memorial-plugin' ); ?></a>
			<?php endif; ?>
			<?php echo memorial_plugin_org_micro_fields( $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
		</section>
			<?php
		endforeach;
		if ( $micro ) {
			memorial_plugin_org_micro_place( false );
		}
		?>
	</div>

