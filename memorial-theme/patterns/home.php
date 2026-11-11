<?php
/**
 * Title: Home page
 * Slug: memorial-theme/home
 * Categories: memorial-theme
 * Inserter: no
 * Description: The first screen and the sections of the home page. The texts come from “Memorial → Home page” at the moment the theme is activated; after that they are edited right in the template.
 *
 * Разделы строит плагин Memorial Plugin (memorial_plugin_home_markup()): тексты — блоками ядра,
 * данные сайта (услуги, каталог, цены, контакты, карты, отзывы) — его блоками. При первом входе в админку
 * плагин сохраняет шаблон главной с этими разделами как шаблон сайта (разовый перенос).
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'memorial_plugin_home_markup' ) ) {
	echo memorial_plugin_home_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block markup built with escaping.
	return;
}
?>
<!-- wp:group {"tagName":"section","className":"section","layout":{"type":"default"}} -->
<section class="wp-block-group section"><!-- wp:group {"className":"wrap","layout":{"type":"default"}} -->
<div class="wp-block-group wrap"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php bloginfo( 'name' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php bloginfo( 'description' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
