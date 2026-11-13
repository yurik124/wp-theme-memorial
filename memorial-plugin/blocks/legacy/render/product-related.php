<?php
/**
 * Товар: похожие товары — ещё четыре из того же раздела каталога.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;
$id  = memorial_plugin_legacy_product_id( $block );
$cat = $id ? memorial_plugin_product_cat( $id ) : null;
if ( ! $cat ) {
	return;
}
$rel = new WP_Query(
	array(
		'post_type'      => 'mem_product',
		'posts_per_page' => 4,
		'post__not_in'   => array( $id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- one ID.
		'no_found_rows'  => true,
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- products of one section.
			array(
				'taxonomy' => 'mem_cat',
				'terms'    => $cat->term_id,
			),
		),
	)
);
if ( ! $rel->have_posts() ) {
	return;
}
?>
<section class="related">
<h2><?php esc_html_e( 'Similar products', 'memorial-plugin' ); ?></h2>
<div class="products">
	<?php
	while ( $rel->have_posts() ) :
		$rel->the_post();
		memorial_plugin_part( 'product-card' );
	endwhile;
	wp_reset_postdata();
	?>
</div>
</section>
