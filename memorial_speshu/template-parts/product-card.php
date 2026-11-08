<?php
/* Карточка товара в сетке (разделы каталога, «товары на странице»).
   Товар без своей страницы (см. плагин: «Каталог → Страницы товаров») открывает фото крупно. */
$mem_id    = get_the_ID();
$mem_page  = !function_exists('mem_product_has_page') || mem_product_has_page($mem_id);
$mem_thumb = get_post_thumbnail_id();
$mem_big   = $mem_thumb ? wp_get_attachment_image_url($mem_thumb, 'large') : '';
$mem_href  = $mem_page ? get_permalink() : $mem_big;
$mem_lb    = !$mem_page && $mem_big ? ' data-lb data-cap="' . esc_attr(get_the_title()) . '"' : '';
// фото другой формы, чем квадрат карточки: поля — размытая копия этого же фото
$mem_bg = $mem_thumb && function_exists('mem_card_ratio_differs') && mem_card_ratio_differs($mem_thumb, 1) && function_exists('mem_blur_bg_url') ? mem_blur_bg_url($mem_thumb) : '';
?>
<article class="card" id="p-<?php echo (int) $mem_id; ?>">
  <?php if ($mem_href) : ?><a class="card__img<?php echo $mem_bg ? ' has-bg' : ''; ?>" href="<?php echo esc_url($mem_href); ?>"<?php echo $mem_lb; ?><?php echo $mem_bg ? ' style="--mbg:url(&quot;' . esc_url($mem_bg) . '&quot;)"' : ''; ?> tabindex="-1" aria-hidden="true">
  <?php else : ?><span class="card__img"><?php endif; ?>
    <?php
    if (has_post_thumbnail()) {
        the_post_thumbnail('medium_large', ['loading' => 'lazy', 'decoding' => 'async']);
    } else {
        echo '<span class="card__noimg">' . mem_laurel('wreath') . '</span>';
    }
    if (get_post_meta($mem_id, '_mem_on_order', true)) {
        echo '<span class="card__tag">Под заказ</span>';
    }
    ?>
  <?php echo $mem_href ? '</a>' : '</span>'; ?>
  <div class="card__body">
    <h3 class="card__title"><?php if ($mem_page) : ?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><?php else : the_title(); endif; ?></h3>
    <div class="card__price"><?php echo function_exists('mem_price_html') ? wp_kses_post(mem_price_html($mem_id)) : ''; ?></div>
    <?php if (function_exists('mem_tel') && mem_tel()) : ?><a class="btn btn--line btn--sm" href="tel:<?php echo esc_attr(mem_tel()); ?>">Заказать</a><?php endif; ?>
  </div>
</article>
