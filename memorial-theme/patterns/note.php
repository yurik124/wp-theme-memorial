<?php
/**
 * Title: Good to know (note)
 * Slug: memorial-theme/note
 * Categories: memorial-theme, text
 * Keywords: note, important, remark
 * Description: A heading and a short note on a light backing; a list with ticks.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Good to know', 'memorial-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-note"} -->
<p class="is-style-note"><?php esc_html_e( 'A short explanation: terms, conditions or what to bring.', 'memorial-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"is-style-checks"} -->
<ul class="wp-block-list is-style-checks"><!-- wp:list-item -->
<li><?php esc_html_e( 'First item', 'memorial-theme' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php esc_html_e( 'Second item', 'memorial-theme' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
