<?php get_header();
while (have_posts()) : the_post();
    $mode = get_post_meta(get_the_ID(), '_mem_products', true);

    if (!$mode) : // обычная страница ?>
<main id="main" class="section">
  <div class="wrap content">
    <?php mem_crumbs_html(); ?>
    <h1><?php the_title(); ?></h1>
    <?php if (has_post_thumbnail() && !get_post_meta(get_the_ID(), '_mem_hide_thumb', true)) the_post_thumbnail('large', ['loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'page-thumb']); ?>
    <?php the_content(); ?>
  </div>
</main>
<?php
    else : // страница с товарами
        // Текст до блока «Далее» — над товарами, после — под товарами. Без «Далее» весь текст внизу.
        $parts  = get_extended(get_post()->post_content);
        $top    = $parts['extended'] !== '' ? $parts['main'] : '';
        $bottom = $parts['extended'] !== '' ? $parts['extended'] : $parts['main'];
        $fmt    = fn($html) => trim($html) === '' ? '' : apply_filters('the_content', $html);
        $first  = max(1, (int) get_query_var('paged')) === 1; // тексты только на первой странице списка
?>
<main id="main" class="section catalog">
  <div class="wrap">
    <?php mem_crumbs_html(); ?>
    <h1><?php the_title(); ?></h1>
    <?php if ($first && ($t = $fmt($top))) echo '<div class="cat-desc content">' . $t . '</div>'; ?>

    <?php
    if ($mode === 'all' && ($kids = mem_menu_children(get_the_ID()))) {
        mem_page_tiles($kids); // плитки = подпункты этой страницы в главном меню
    } elseif ($mode === 'all') {
        $cats = get_terms(['taxonomy' => 'mem_cat', 'parent' => 0, 'hide_empty' => false]);
        if (!is_wp_error($cats) && $cats) {
            mem_cat_tiles($cats);
        }
    } else {
        mem_products_grid((int) $mode, get_permalink());
    }
    ?>

    <?php if ($first && ($b = $fmt($bottom))) echo '<div class="content catalog__text">' . $b . '</div>'; ?>
  </div>
</main>
<?php
    endif;
endwhile;
get_footer();
