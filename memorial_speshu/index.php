<?php get_header(); ?>
<main id="main" class="section">
  <div class="wrap content">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
      <article <?php post_class('entry'); ?>>
        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <?php the_excerpt(); ?>
      </article>
    <?php endwhile; the_posts_pagination(); else : ?>
      <p>Ничего не найдено.</p>
    <?php endif; ?>
  </div>
</main>
<?php get_footer(); ?>