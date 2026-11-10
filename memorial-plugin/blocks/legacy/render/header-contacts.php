<?php
/** Шапка: главный телефон, «Перезвоните мне», значок звонка на телефоне */
defined('ABSPATH') || exit;
if (!memorial_plugin_tel()) return;
?>
<a class="header__phone" href="tel:<?php echo esc_attr(memorial_plugin_tel()); ?>">
  <b class="nowrap"><?php echo esc_html(memorial_plugin_opt('phone')); ?></b>
  <?php if (memorial_plugin_night()) : ?><small>круглосуточно</small><?php endif; ?>
</a>
<button class="btn btn--line header__cb" type="button" data-callback>Перезвоните мне</button>
<a class="header__call" href="tel:<?php echo esc_attr(memorial_plugin_tel()); ?>" aria-label="Позвонить <?php echo esc_attr(memorial_plugin_opt('phone')); ?>"><?php echo memorial_plugin_icon('phone'); ?></a>
