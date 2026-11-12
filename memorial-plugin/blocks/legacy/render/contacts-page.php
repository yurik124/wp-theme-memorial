<?php
/**
 * Страница «Контакты»: телефоны, мессенджеры, адреса с маршрутом, карты, текст страницы, реквизиты.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$id    = memorial_plugin_legacy_post_id( $block );
$addrs = memorial_plugin_addrs();
?>
	<header class="kontakty__head">
		<h1><?php echo esc_html( get_the_title( $id ) ); ?></h1>
		<p class="kontakty__lead">
		<?php memorial_plugin_legacy_part( 'contacts-badges' ); ?>
		<?php esc_html_e( 'Call — we will tell you what to do and take care of everything.', 'memorial-plugin' ); ?>
		</p>
	</header>

	<?php memorial_plugin_legacy_part( 'contacts-cards' ); ?>

	<?php if ( $addrs ) : ?>
		<section class="kontakty__maps" aria-label="<?php esc_attr_e( 'Map', 'memorial-plugin' ); ?>">
		<?php memorial_plugin_legacy_part( 'maps' ); ?>
		</section>
	<?php endif; ?>

	<?php $text = trim( (string) get_post_field( 'post_content', $id ) ); if ( '' !== $text ) : ?>
		<div class="content kontakty__text"><?php echo apply_filters( 'the_content', $text ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.Security.EscapeOutput.OutputNotEscaped -- core filter; post content is filtered by WordPress itself. ?></div>
	<?php endif; ?>

	<?php memorial_plugin_legacy_part( 'company-details' ); ?>
