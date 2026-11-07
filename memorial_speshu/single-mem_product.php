<?php get_header();
while (have_posts()) : the_post();
    $id   = get_the_ID();
    $cat  = mem_product_cat($id); ?>
<main id="main" class="section">
  <div class="wrap">
    <?php mem_crumbs_html(); ?>

    <div class="product">
      <div class="product__media">
        <?php if (has_post_thumbnail()) the_post_thumbnail('large', ['fetchpriority' => 'high']); else echo '<span class="card__noimg">✝</span>'; ?>
      </div>
      <div class="product__info">
        <h1><?php the_title(); ?></h1>
        <div class="product__price"><?php echo wp_kses_post(mem_price_html($id)); ?></div>

        <dl class="specs">
          <?php foreach (mem_attrs() as $k => $label) :
              $t = get_the_terms($id, 'mem_' . $k);
              if (!$t || is_wp_error($t)) continue; ?>
            <dt><?php echo esc_html($label); ?></dt>
            <dd><?php echo esc_html(implode(', ', wp_list_pluck($t, 'name'))); ?></dd>
          <?php endforeach; ?>
          <?php if (get_post_meta($id, '_mem_on_order', true)) : ?><dt>Наличие</dt><dd>Под заказ</dd><?php endif; ?>
        </dl>

        <?php if (has_excerpt()) : ?><div class="about__text"><?php the_excerpt(); ?></div><?php endif; ?>

        <div class="product__cta">
          <a class="btn btn--gold btn--lg" href="tel:<?php echo esc_attr(mem_tel()); ?>"><?php echo mem_icon('phone'); ?><span class="nowrap"><?php echo esc_html(mem_opt('phone')); ?></span></a>
        </div>
      </div>
    </div>

    <?php if (get_the_content()) : ?><div class="content product__desc"><?php the_content(); ?></div><?php endif; ?>

    <?php if ($cat) :
        $rel = new WP_Query([
            'post_type' => 'mem_product', 'posts_per_page' => 4, 'post__not_in' => [$id],
            'no_found_rows' => true, 'tax_query' => [['taxonomy' => 'mem_cat', 'terms' => $cat->term_id]],
        ]);
        if ($rel->have_posts()) : ?>
      <section class="related">
        <h2>Похожие товары</h2>
        <div class="products">
          <?php while ($rel->have_posts()) : $rel->the_post(); get_template_part('template-parts/product-card'); endwhile; wp_reset_postdata(); ?>
        </div>
      </section>
    <?php endif; endif; ?>
  </div>
</main>
<?php endwhile; get_footer(); ?>