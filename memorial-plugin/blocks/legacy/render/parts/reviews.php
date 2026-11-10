<?php
defined('ABSPATH') || exit;
/* Отзывы с Яндекс Карт: официальный виджет. Грузится сам после согласия на cookie, иначе — по кнопке. */
$s   = memorial_plugin_home('reviews');
$org = memorial_plugin_yandex_org();
if (empty($s['show']) || !$org) return;
$src     = 'https://yandex.ru/maps-reviews-widget/' . $org . '?comments';
$page    = 'https://yandex.ru/maps/org/' . $org . '/';
$reviews = 'https://yandex.ru/maps/org/' . $org . '/reviews/';
?>
<section class="section section--alt" id="reviews">
  <div class="wrap reviews">
    <div class="reviews__intro">
      <?php if (!empty($s['eyebrow'])) : ?><p class="eyebrow"><?php echo esc_html(memorial_plugin_t($s['eyebrow'])); ?></p><?php endif; ?>
      <?php if (!empty($s['title'])) : ?><h2><?php echo esc_html(memorial_plugin_t($s['title'])); ?></h2><?php endif; ?>
      <?php if (!empty($s['text'])) : ?><p class="reviews__text"><?php echo nl2br(esc_html(memorial_plugin_t($s['text']))); ?></p><?php endif; ?>
      <div class="reviews__btns">
        <a class="btn btn--gold" href="<?php echo esc_url($reviews); ?>" target="_blank" rel="noopener">Оставить отзыв</a>
        <a class="btn btn--line" href="<?php echo esc_url($page); ?>" target="_blank" rel="noopener">Мы на Яндекс Картах</a>
      </div>
    </div>
    <div class="reviews__widget" data-reviews="<?php echo esc_url($src); ?>">
      <div class="reviews__ph">
        <span class="reviews__stars" aria-hidden="true">★★★★★</span>
        <p>Отзывы клиентов на Яндекс Картах</p>
        <button class="btn btn--line" type="button" data-reviews-load>Показать отзывы</button>
      </div>
      <a class="reviews__credit" href="<?php echo esc_url($page); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_bloginfo('name')); ?> на карте Белгорода — Яндекс Карты</a>
    </div>
  </div>
</section>
