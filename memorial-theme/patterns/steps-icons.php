<?php
/**
 * Title: Steps with icons
 * Slug: memorial-theme/steps-icons
 * Categories: memorial-theme, text
 * Keywords: steps, sequence, instructions
 * Description: Numbered steps with round icons. The icon of a step is set by the group’s class: ico-phone, ico-doc, ico-car, ico-candle and others.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"mem-steps","layout":{"type":"default"}} -->
<div class="wp-block-group mem-steps"><!-- wp:group {"className":"mem-step ico-phone","layout":{"type":"default"}} -->
<div class="wp-block-group mem-step ico-phone"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Call us', 'memorial-theme' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Tell us what happened — we will tell you what to do right now.', 'memorial-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"mem-step ico-doc","layout":{"type":"default"}} -->
<div class="wp-block-group mem-step ico-doc"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Documents', 'memorial-theme' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'We will help to get the certificates and arrange the documents.', 'memorial-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"mem-step ico-candle","layout":{"type":"default"}} -->
<div class="wp-block-group mem-step ico-candle"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Farewell', 'memorial-theme' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'We will arrange the farewell and the last journey.', 'memorial-theme' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
