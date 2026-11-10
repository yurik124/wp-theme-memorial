<?php
/** Каталог или раздел каталога: подразделы, сортировка, товары основного запроса, страницы */
defined('ABSPATH') || exit;
global $wp_query;
$is_cat = is_tax('mem_cat');
$term   = $is_cat ? get_queried_object() : null;
$title  = $is_cat ? single_term_title('', false) : 'Каталог';
$base   = $is_cat ? get_term_link($term) : get_post_type_archive_link('mem_product');
$sort   = sanitize_key($_GET['sort'] ?? '');
$subs   = get_terms(['taxonomy' => 'mem_cat', 'parent' => $is_cat ? $term->term_id : 0, 'hide_empty' => true]);
?>
    <h1><?php echo esc_html($title); ?></h1>
    <?php if ($is_cat && $term->description) echo '<div class="cat-desc">' . wp_kses_post(wpautop($term->description)) . '</div>'; ?>

    <?php if (!is_wp_error($subs) && $subs) : ?>
      <div class="chips">
        <?php foreach ($subs as $s) : ?>
          <a class="chip" href="<?php echo esc_url(get_term_link($s)); ?>"><?php echo esc_html($s->name); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="toolbar">
      <span class="toolbar__count">Товаров: <?php echo (int) $wp_query->found_posts; ?></span>
      <form class="sort" method="get" action="<?php echo esc_url($base); ?>" data-sort>
        <label>
          <span class="screen-reader-text">Сортировка</span>
          <select name="sort">
            <?php foreach (memorial_plugin_sorts() as $v => $l) : ?>
              <option value="<?php echo esc_attr($v); ?>" <?php selected($sort, $v); ?>><?php echo esc_html($l); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <noscript><button class="btn btn--line btn--sm" type="submit">OK</button></noscript>
      </form>
    </div>

    <?php if (have_posts()) : ?>
      <div class="products">
        <?php while (have_posts()) : the_post(); memorial_plugin_part('product-card'); endwhile; wp_reset_postdata(); ?>
      </div>
      <?php the_posts_pagination(['mid_size' => 1, 'prev_text' => '←', 'next_text' => '→']); ?>
    <?php else : ?>
      <div class="empty">
        <p>В этом разделе пока нет товаров. Позвоните — подберём нужное.</p>
        <?php if (memorial_plugin_tel()) : ?><a class="btn btn--gold" href="tel:<?php echo esc_attr(memorial_plugin_tel()); ?>"><?php echo memorial_plugin_icon('phone'); ?><span class="nowrap"><?php echo esc_html(memorial_plugin_opt('phone')); ?></span></a><?php endif; ?>
      </div>
    <?php endif; ?>

