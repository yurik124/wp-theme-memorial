<footer class="footer">
  <div class="wrap footer__in">
    <div>
      <b class="footer__brand"><?php bloginfo('name'); ?></b>
      <?php if (mem_years()) : ?><p>Ритуальные услуги в Белгороде более <?php echo (int) mem_years(); ?> лет.</p><?php endif; ?>
    </div>
    <div>
      <?php foreach (mem_addrs() as $a) : ?>
        <p class="footer__addr"><?php echo esc_html(memorial_addr($a['addr'])); ?><?php if (!empty($a['hours'])) echo '<br><small>' . esc_html($a['hours']) . '</small>'; ?></p>
      <?php endforeach; ?>
    </div>
    <div>
      <p>
        <?php foreach (mem_phones() as $p) : ?>
          <a class="footer__tel" href="tel:<?php echo esc_attr(mem_tel_of($p['num'])); ?>"><?php echo esc_html($p['num']); ?></a><?php if (!empty($p['note'])) echo ' <small>' . esc_html($p['note']) . '</small>'; ?><br>
        <?php endforeach; ?>
        <?php if (mem_night()) : ?><small class="footer__night"><?php echo esc_html(mem_night()); ?></small><br><?php endif; ?>
        <?php foreach (mem_emails() as $m) : ?>
          <a class="footer__mail" href="mailto:<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></a><br>
        <?php endforeach; ?>
      </p>
    </div>
  </div>
  <?php echo mem_footer_nav(); ?>
  <div class="wrap footer__copy">
    <span>© <?php echo esc_html(date('Y') . ' ' . get_bloginfo('name')); ?></span>
    <?php if ($legal = mem_legal_parts()) : ?><span><?php echo esc_html(implode(' · ', $legal)); ?></span><?php endif; ?>
    <?php // документы — страницы выбираются в «Мемориал → Документы»; без плагина — политика из настроек WordPress
    $pd = function_exists('mem_legal_url') ? mem_legal_url('policy') : get_privacy_policy_url();
    $ck = function_exists('mem_legal_url') ? mem_legal_url('cookies') : '';
    if ($pd) : ?><a href="<?php echo esc_url($pd); ?>">Политика обработки персональных данных</a><?php endif; ?>
    <?php if ($ck) : ?><a href="<?php echo esc_url($ck); ?>">Политика использования cookie</a><?php endif; ?>
    <?php if (function_exists('mem_consent_mode') && mem_banner_on() && mem_consent_mode() === 'ask') : ?><a href="#" data-consent-reset>Настройки cookie</a><?php endif; ?>
  </div>
</footer>

<button class="totop" id="totop" type="button" aria-label="Наверх" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg></button>
<?php if (mem_tel()) : ?><a class="fab" href="tel:<?php echo esc_attr(mem_tel()); ?>" aria-label="Позвонить"><?php echo mem_icon('phone'); ?></a><?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
