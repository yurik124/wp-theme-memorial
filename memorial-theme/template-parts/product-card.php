<?php
/* Карточка товара в сетке (разделы каталога, «товары на странице»).
   Товар без своей страницы (см. плагин: «Каталог → Страницы товаров») открывает фото крупно. */
$memorial_theme_id    = get_the_ID();
$memorial_theme_page  = !function_exists('memorial_plugin_product_has_page') || memorial_plugin_product_has_page($memorial_theme_id);
$memorial_theme_thumb = get_post_thumbnail_id();
$memorial_theme_big   = $memorial_theme_thumb ? wp_get_attachment_image_url($memorial_theme_thumb, 'large') : '';
$memorial_theme_href  = $memorial_theme_page ? get_permalink() : $memorial_theme_big;
$memorial_theme_lb    = !$memorial_theme_page && $memorial_theme_big ? ' data-lb data-cap="' . esc_attr(get_the_title()) . '"' : '';
// фото другой формы, чем квадрат карточки: поля — размытая копия этого же фото
$memorial_theme_bg = $memorial_theme_thumb && function_exists('memorial_plugin_card_ratio_differs') && memorial_plugin_card_ratio_differs($memorial_theme_thumb, 1) && function_exists('memorial_plugin_blur_bg_url') ? memorial_plugin_blur_bg_url($memorial_theme_thumb) : '';
?>
<article class="card" id="p-<?php echo (int) $memorial_theme_id; ?>">
  <?php if ($memorial_theme_href) : ?><a class="card__img<?php echo $memorial_theme_bg ? ' has-bg' : ''; ?>" href="<?php echo esc_url($memorial_theme_href); ?>"<?php echo $memorial_theme_lb; ?><?php echo $memorial_theme_bg ? ' style="--mbg:url(&quot;' . esc_url($memorial_theme_bg) . '&quot;)"' : ''; ?> tabindex="-1" aria-hidden="true">
  <?php else : ?><span class="card__img"><?php endif; ?>
    <?php
    if (has_post_thumbnail()) {
        the_post_thumbnail('medium_large', ['loading' => 'lazy', 'decoding' => 'async']);
    } else {
        echo '<span class="card__noimg">' . memorial_theme_laurel('wreath') . '</span>';
    }
    if (get_post_meta($memorial_theme_id, '_mem_on_order', true)) {
        echo '<span class="card__tag">Под заказ</span>';
    }
    ?>
  <?php echo $memorial_theme_href ? '</a>' : '</span>'; ?>
  <div class="card__body">
    <h3 class="card__title"><?php if ($memorial_theme_page) : ?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><?php else : the_title(); endif; ?></h3>
    <div class="card__price"><?php echo function_exists('memorial_plugin_price_html') ? wp_kses_post(memorial_plugin_price_html($memorial_theme_id)) : ''; ?></div>
    <?php if (function_exists('memorial_plugin_tel') && memorial_plugin_tel()) : ?><a class="btn btn--line btn--sm" href="tel:<?php echo esc_attr(memorial_plugin_tel()); ?>">Заказать</a><?php endif; ?>
  </div>
</article>
