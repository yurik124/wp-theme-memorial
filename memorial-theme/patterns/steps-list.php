<?php
/**
 * Title: How to order (numbered list)
 * Slug: memorial-theme/steps-list
 * Categories: memorial-theme, text
 * Keywords: steps, order, list
 * Description: An introduction and a short list of steps with numbers in circles.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:paragraph {"className":"is-style-lead"} -->
<p class="is-style-lead"><?php esc_html_e( 'Introduction: one or two sentences — what we offer, from what price, how we differ.', 'memorial-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:list {"ordered":true,"className":"is-style-steps"} -->
<ol class="wp-block-list is-style-steps"><!-- wp:list-item -->
<li><?php echo wp_kses_post( __( '<strong>Choose</strong> a model in the catalog — or tell us by phone what you need.', 'memorial-theme' ) ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( '<strong>Call.</strong> We will check the availability, terms and cost.', 'memorial-theme' ) ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php echo wp_kses_post( __( '<strong>Get the order</strong> — in the office or with delivery.', 'memorial-theme' ) ); ?></li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->
