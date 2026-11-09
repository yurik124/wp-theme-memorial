<footer class="footer">
  <div class="wrap footer__in">
    <div class="footer__about">
      <a class="footer__logo" href="<?php echo esc_url(home_url('/')); ?>">
        <?php echo memorial_theme_logo_mark(48); ?>
        <b class="footer__brand"><?php bloginfo('name'); ?></b>
      </a>
      <p class="footer__slogan"><?php echo memorial_plugin_years()
          ? esc_html(memorial_theme_nbsp('Ритуальная компания, которая на рынке больше ' . (int) memorial_plugin_years() . ' лет'))
          : esc_html(get_bloginfo('description') ?: 'Ритуальная компания'); ?></p>
    </div>
    <?php if (memorial_plugin_addrs()) : ?>
    <div class="footer__col">
      <p class="fnav__title">Адреса</p>
      <?php foreach (memorial_plugin_addrs() as $a) : ?>
        <p class="footer__addr"><?php echo esc_html(memorial_theme_addr($a['addr'])); ?><?php if (!empty($a['hours'])) echo '<br><small>' . esc_html($a['hours']) . '</small>'; ?></p>
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
  </div>
  <?php echo memorial_theme_footer_nav(); ?>
  <div class="wrap footer__copy">
    <span>© <?php echo esc_html((memorial_theme_founded_year() ? memorial_theme_founded_year() . '–' : '') . date('Y') . ' «' . get_bloginfo('name') . '»'); ?></span>
    <?php if ($legal = memorial_plugin_legal_parts()) : ?><span><?php echo esc_html(implode(' · ', $legal)); ?></span><?php endif; ?>
    <?php // документы — страницы выбираются в «Мемориал → Документы»; без плагина — политика из настроек WordPress
    $pd = function_exists('memorial_plugin_legal_url') ? memorial_plugin_legal_url('policy') : get_privacy_policy_url();
    $ck = function_exists('memorial_plugin_legal_url') ? memorial_plugin_legal_url('cookies') : '';
    if ($pd) : ?><a href="<?php echo esc_url($pd); ?>">Политика обработки персональных данных</a><?php endif; ?>
    <?php if ($ck) : ?><a href="<?php echo esc_url($ck); ?>">Политика использования cookie</a><?php endif; ?>
    <?php if (function_exists('memorial_plugin_consent_mode') && memorial_plugin_banner_on() && memorial_plugin_consent_mode() === 'ask') : ?><a href="#" data-consent-reset>Настройки cookie</a><?php endif; ?>
  </div>
</footer>

<button class="totop" id="totop" type="button" aria-label="Наверх" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg></button>
<?php if (memorial_plugin_tel()) : $max = memorial_theme_max_url(); ?>
<nav class="mbar" aria-label="Связаться">
  <a class="btn btn--gold mbar__call" href="tel:<?php echo esc_attr(memorial_plugin_tel()); ?>"><?php echo memorial_theme_icon('phone'); ?>Позвонить</a>
  <?php if ($max) : ?>
    <a class="btn btn--line mbar__msg" href="<?php echo esc_url($max); ?>" target="_blank" rel="noopener"><?php echo memorial_theme_icon('chat'); ?>Написать в MAX</a>
  <?php else : ?>
    <button class="btn btn--line mbar__msg" type="button" data-callback>Перезвоните мне</button>
  <?php endif; ?>
</nav>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
