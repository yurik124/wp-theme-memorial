<?php get_header();
$cats = get_terms(['taxonomy' => 'mem_cat', 'parent' => 0, 'hide_empty' => true]); ?>
<main id="main" class="section nf">
  <div class="wrap nf__in">
    <p class="nf__code" aria-hidden="true"><span class="nf__ring"><?php echo memorial_theme_laurel('wreath'); ?><b>404</b></span></p>
    <h1>Страница не найдена</h1>
    <p class="nf__lead">Возможно, ссылка устарела или в адресе опечатка. Если вам нужна помощь прямо сейчас — позвоните, мы подскажем.</p>

    <div class="nf__cta">
      <?php if (memorial_plugin_tel()) : ?>
        <a class="btn btn--gold btn--lg" href="tel:<?php echo esc_attr(memorial_plugin_tel()); ?>"><?php echo memorial_theme_icon('phone'); ?><span class="nowrap"><?php echo esc_html(memorial_plugin_opt('phone')); ?></span></a>
      <?php endif; ?>
      <a class="btn btn--line btn--lg" href="<?php echo esc_url(home_url('/')); ?>">На главную</a>
    </div>

    <?php if (!is_wp_error($cats) && $cats) : ?>
      <nav class="nf__links" aria-label="Разделы каталога">
        <p>Разделы каталога:</p>
        <div class="chips">
          <a class="chip" href="<?php echo esc_url(memorial_plugin_catalog_url()); ?>">Весь каталог</a>
          <?php foreach ($cats as $c) : ?>
            <a class="chip" href="<?php echo esc_url(get_term_link($c)); ?>"><?php echo esc_html($c->name); ?></a>
          <?php endforeach; ?>
        </div>
      </nav>
    <?php endif; ?>
  </div>
</main>
<?php get_footer(); ?>
