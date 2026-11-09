<?php get_header(); ?>
<main id="main" class="section">
  <div class="wrap content">
    <?php if (function_exists('memorial_plugin_crumbs_html')) memorial_plugin_crumbs_html(); ?>
    <?php if (is_singular()) : // запись блога и т. п. — заголовок и текст целиком
      while (have_posts()) : the_post(); ?>
      <article <?php post_class('entry'); ?>>
        <h1><?php the_title(); ?></h1>
        <?php the_content(); ?>
      </article>
    <?php endwhile; else : ?>
      <h1><?php echo esc_html(is_home() ? (get_the_title((int) get_option('page_for_posts')) ?: 'Статьи') : (is_search() ? 'Поиск: ' . get_search_query() : wp_strip_all_tags(get_the_archive_title()))); ?></h1>
      <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article <?php post_class('entry'); ?>>
          <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
          <?php the_excerpt(); ?>
        </article>
      <?php endwhile; the_posts_pagination(); else : ?>
        <p>Ничего не найдено.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<?php get_footer(); ?>
