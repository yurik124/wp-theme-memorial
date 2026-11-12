<?php
/**
 * Product card.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/*
Карточка товара в сетке (разделы каталога, «товары на странице»).
	Товар без своей страницы (см. плагин: «Каталог → Страницы товаров») открывает фото крупно.
 */
$memorial_plugin_id    = get_the_ID();
$memorial_plugin_page  = ! function_exists( 'memorial_plugin_product_has_page' ) || memorial_plugin_product_has_page( $memorial_plugin_id );
$memorial_plugin_thumb = get_post_thumbnail_id();
$memorial_plugin_big   = $memorial_plugin_thumb ? wp_get_attachment_image_url( $memorial_plugin_thumb, 'large' ) : '';
$memorial_plugin_href  = $memorial_plugin_page ? get_permalink() : $memorial_plugin_big;
$memorial_plugin_lb    = ! $memorial_plugin_page && $memorial_plugin_big ? ' data-lb data-cap="' . esc_attr( get_the_title() ) . '"' : '';
// фото другой формы, чем квадрат карточки: поля — размытая копия этого же фото.
$memorial_plugin_bg = $memorial_plugin_thumb && function_exists( 'memorial_plugin_card_ratio_differs' ) && memorial_plugin_card_ratio_differs( $memorial_plugin_thumb, 1 ) && function_exists( 'memorial_plugin_blur_bg_url' ) ? memorial_plugin_blur_bg_url( $memorial_plugin_thumb ) : '';
?>
<article class="card" id="p-<?php echo (int) $memorial_plugin_id; ?>">
	<?php
	if ( $memorial_plugin_href ) :
		?>
		<a class="card__img<?php echo $memorial_plugin_bg ? ' has-bg' : ''; ?>" href="<?php echo esc_url( $memorial_plugin_href ); ?>"<?php echo $memorial_plugin_lb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute built with esc_attr() above. ?><?php echo $memorial_plugin_bg ? ' style="--mbg:url(&quot;' . esc_url( $memorial_plugin_bg ) . '&quot;)"' : ''; ?> tabindex="-1" aria-hidden="true">
		<?php
	else :
		?>
		<span class="card__img"><?php endif; ?>
	<?php
	if ( has_post_thumbnail() ) {
		the_post_thumbnail(
			'medium_large',
			array(
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
	} else {
		echo '<span class="card__noimg">' . memorial_plugin_noimg_mark() . '</span>';
	}
	if ( get_post_meta( $memorial_plugin_id, '_mem_on_order', true ) ) {
		echo '<span class="card__tag">' . esc_html__( 'Made to order', 'memorial-plugin' ) . '</span>';
	}
	?>
	<?php echo $memorial_plugin_href ? '</a>' : '</span>'; ?>
	<div class="card__body">
	<h3 class="card__title">
	<?php
	if ( $memorial_plugin_page ) :
		?>
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		<?php
else :
		the_title();
endif;
?>
</h3>
	<div class="card__price"><?php echo function_exists( 'memorial_plugin_price_html' ) ? wp_kses_post( memorial_plugin_price_html( $memorial_plugin_id ) ) : ''; ?></div>
	<?php
	if ( function_exists( 'memorial_plugin_tel' ) && memorial_plugin_tel() ) :
		?>
		<a class="btn btn--line btn--sm" href="tel:<?php echo esc_attr( memorial_plugin_tel() ); ?>"><?php esc_html_e( 'Order', 'memorial-plugin' ); ?></a><?php endif; ?>
	</div>
</article>
