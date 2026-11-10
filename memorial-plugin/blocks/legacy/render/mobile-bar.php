<?php
/** Нижняя панель на телефоне: «Позвонить» и «Написать в MAX» (или «Перезвоните мне») */
defined('ABSPATH') || exit;
if (!memorial_plugin_tel()) return;
$max = memorial_plugin_max_url();
?>
<nav class="mbar" aria-label="Связаться">
  <a class="btn btn--gold mbar__call" href="tel:<?php echo esc_attr(memorial_plugin_tel()); ?>"><?php echo memorial_plugin_icon('phone'); ?>Позвонить</a>
  <?php if ($max) : ?>
    <a class="btn btn--line mbar__msg" href="<?php echo esc_url($max); ?>" target="_blank" rel="noopener"><?php echo memorial_plugin_icon('chat'); ?>Написать в MAX</a>
  <?php else : ?>
    <button class="btn btn--line mbar__msg" type="button" data-callback>Перезвоните мне</button>
  <?php endif; ?>
</nav>
