<?php
/** Товар: фото, цена, характеристики, кнопки звонка, описание, похожие товары */
defined('ABSPATH') || exit;
    $id   = memorial_plugin_legacy_post_id($block);
    $cat  = memorial_plugin_product_cat($id); ?>
    <div class="product">
      <div class="product__media">
        <?php if (has_post_thumbnail($id)) echo get_the_post_thumbnail($id, 'large', ['fetchpriority' => 'high']); else echo '<span class="card__noimg">' . memorial_plugin_laurel('wreath') . '</span>'; ?>
      </div>
      <div class="product__info">
        <h1><?php echo esc_html(get_the_title($id)); ?></h1>
        <div class="product__price"><?php echo wp_kses_post(memorial_plugin_price_html($id)); ?></div>

        <dl class="specs">
          <?php foreach (memorial_plugin_attrs() as $k => $label) :
              $t = get_the_terms($id, 'mem_' . $k);
              if (!$t || is_wp_error($t)) continue; ?>
            <dt><?php echo esc_html($label); ?></dt>
            <dd><?php echo esc_html(implode(', ', wp_list_pluck($t, 'name'))); ?></dd>
          <?php endforeach; ?>
          <?php if (get_post_meta($id, '_mem_on_order', true)) : ?><dt><?php esc_html_e( 'Availability', 'memorial-plugin' ); ?></dt><dd><?php esc_html_e( 'Made to order', 'memorial-plugin' ); ?></dd><?php endif; ?>
        </dl>

        <?php if (has_excerpt($id)) : ?><div class="about__text"><?php echo wpautop(esc_html(get_the_excerpt($id))); ?></div><?php endif; ?>

        <div class="product__cta">
          <a class="btn btn--gold btn--lg" href="tel:<?php echo esc_attr(memorial_plugin_tel()); ?>"><?php echo memorial_plugin_icon('phone'); ?><span class="nowrap"><?php echo esc_html(memorial_plugin_opt('phone')); ?></span></a>
          <button class="btn btn--line btn--lg" type="button" data-callback><?php esc_html_e( 'Call me back', 'memorial-plugin' ); ?></button>
        </div>
        <p class="product__hint"><?php esc_html_e( 'You can check the availability, sizes and terms by phone — we will help you choose.', 'memorial-plugin' ); ?></p>
      </div>
    </div>

    <?php $text = (string) get_post_field('post_content', $id); if (trim($text) !== '') : ?><div class="content product__desc"><?php echo apply_filters('the_content', $text); ?></div><?php endif; ?>

    <?php if ($cat) :
        $rel = new WP_Query([
            'post_type' => 'mem_product', 'posts_per_page' => 4, 'post__not_in' => [$id],
            'no_found_rows' => true, 'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $cat->term_id]],
        ]);
        if ($rel->have_posts()) : ?>
      <section class="related">
        <h2><?php esc_html_e( 'Similar products', 'memorial-plugin' ); ?></h2>
        <div class="products">
          <?php while ($rel->have_posts()) : $rel->the_post(); memorial_plugin_part('product-card'); endwhile; wp_reset_postdata(); ?>
        </div>
      </section>
    <?php endif; endif; ?>
