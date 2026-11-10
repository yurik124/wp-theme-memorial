<?php
/**
 * Title: Важно знать (заметка)
 * Slug: memorial-theme/note
 * Categories: memorial-theme, text
 * Keywords: заметка, важно, примечание
 * Description: Заголовок и короткая заметка на светлой подложке; список с галочками.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:heading -->
<h2 class="wp-block-heading">Важно знать</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-note"} -->
<p class="is-style-note">Короткое пояснение: сроки, условия или что взять с собой.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"is-style-checks"} -->
<ul class="wp-block-list is-style-checks"><!-- wp:list-item -->
<li>Первый пункт</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Второй пункт</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
