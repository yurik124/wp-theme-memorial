<article class="card">
  <a class="card__img" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
    <?php
    if (has_post_thumbnail()) {
        the_post_thumbnail('medium_large', ['loading' => 'lazy', 'decoding' => 'async']);
    } else {
        echo '<span class="card__noimg">✝</span>';
    }
    if (get_post_meta(get_the_ID(), '_mem_on_order', true)) {
        echo '<span class="card__tag">Под заказ</span>';
    }
    ?>
  </a>
  <div class="card__body">
    <h3 class="card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
    <div class="card__price"><?php echo wp_kses_post(mem_price_html(get_the_ID())); ?></div>
    <a class="btn btn--line btn--sm" href="tel:<?php echo esc_attr(mem_tel()); ?>">Заказать</a>
  </div>
</article>