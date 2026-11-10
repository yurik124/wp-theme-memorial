<?php
/** Подвал: колонки «Адреса» и «Телефоны» */
defined('ABSPATH') || exit;
?>
<?php if (memorial_plugin_addrs()) : ?>
<div class="footer__col">
  <p class="fnav__title">Адреса</p>
  <?php foreach (memorial_plugin_addrs() as $a) : ?>
    <p class="footer__addr"><?php echo esc_html(memorial_plugin_addr($a['addr'])); ?><?php if (!empty($a['hours'])) echo '<br><small>' . esc_html($a['hours']) . '</small>'; ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (memorial_plugin_phones() || memorial_plugin_emails()) : ?>
<div class="footer__col">
  <p class="fnav__title">Телефоны</p>
  <?php foreach (memorial_plugin_phones() as $i => $p) : ?>
    <p class="footer__line"><a class="footer__tel<?php echo $i ? '' : ' footer__tel--main'; ?>" href="tel:<?php echo esc_attr(memorial_plugin_tel_of($p['num'])); ?>"><?php echo esc_html($p['num']); ?></a><?php if (!empty($p['note'])) echo ' <small>' . esc_html($p['note']) . '</small>'; ?></p>
  <?php endforeach; ?>
  <?php if (memorial_plugin_night()) : ?><p class="footer__night"><?php echo esc_html(memorial_plugin_night()); ?></p><?php endif; ?>
  <?php foreach (memorial_plugin_emails() as $m) : ?>
    <p class="footer__line"><a class="footer__mail" href="mailto:<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></a></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>
