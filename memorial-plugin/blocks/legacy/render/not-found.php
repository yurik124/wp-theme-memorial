<?php
/** <?php esc_html_e( 'Page not found', 'memorial-plugin' ); ?>: венок 404, звонок, «<?php esc_html_e( 'To the home page', 'memorial-plugin' ); ?>», разделы каталога */
defined('ABSPATH') || exit;
$cats = get_terms(['taxonomy' => 'mem_cat', 'parent' => 0, 'hide_empty' => true]); ?>
<div class="wrap nf__in">
    <p class="nf__code" aria-hidden="true"><span class="nf__ring"><?php echo memorial_plugin_laurel('wreath'); ?><b>404</b></span></p>
    <h1><?php esc_html_e( 'Page not found', 'memorial-plugin' ); ?></h1>
    <p class="nf__lead"><?php esc_html_e( 'The link may be out of date or there is a typo in the address. If you need help right now — call, we will tell you what to do.', 'memorial-plugin' ); ?></p>

    <div class="nf__cta">
      <?php if (memorial_plugin_tel()) : ?>
        <a class="btn btn--gold btn--lg" href="tel:<?php echo esc_attr(memorial_plugin_tel()); ?>"><?php echo memorial_plugin_icon('phone'); ?><span class="nowrap"><?php echo esc_html(memorial_plugin_opt('phone')); ?></span></a>
      <?php endif; ?>
      <a class="btn btn--line btn--lg" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e( 'To the home page', 'memorial-plugin' ); ?></a>
    </div>

    <?php if (!is_wp_error($cats) && $cats) : ?>
      <nav class="nf__links" aria-label="<?php esc_attr_e( 'Catalog sections', 'memorial-plugin' ); ?>">
        <p><?php esc_html_e( 'Catalog sections:', 'memorial-plugin' ); ?></p>
        <div class="chips">
          <a class="chip" href="<?php echo esc_url(memorial_plugin_catalog_url()); ?>"><?php esc_html_e( 'The whole catalog', 'memorial-plugin' ); ?></a>
          <?php foreach ($cats as $c) : ?>
            <a class="chip" href="<?php echo esc_url(get_term_link($c)); ?>"><?php echo esc_html($c->name); ?></a>
          <?php endforeach; ?>
        </div>
      </nav>
    <?php endif; ?>
</div>
