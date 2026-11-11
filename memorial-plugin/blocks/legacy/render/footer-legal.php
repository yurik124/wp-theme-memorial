<?php
/** Подвал: © годы, реквизиты, ссылки на политики, «<?php esc_html_e( 'Cookie settings', 'memorial-plugin' ); ?>» */
defined('ABSPATH') || exit;
$year = memorial_plugin_founded_year();
$pd   = function_exists('memorial_plugin_legal_url') ? memorial_plugin_legal_url('policy') : get_privacy_policy_url();
$ck   = function_exists('memorial_plugin_legal_url') ? memorial_plugin_legal_url('cookies') : '';
?>
<div class="wrap footer__copy">
  <span>© <?php echo esc_html(($year ? $year . '–' : '') . date('Y') . ' «' . get_bloginfo('name') . '»'); ?></span>
  <?php if (function_exists('memorial_plugin_legal_parts') && ($legal = memorial_plugin_legal_parts())) : ?><span><?php echo esc_html(implode(' · ', $legal)); ?></span><?php endif; ?>
  <?php if ($pd) : ?><a href="<?php echo esc_url($pd); ?>"><?php esc_html_e( 'Personal data processing policy', 'memorial-plugin' ); ?></a><?php endif; ?>
  <?php if ($ck) : ?><a href="<?php echo esc_url($ck); ?>"><?php esc_html_e( 'Cookie policy', 'memorial-plugin' ); ?></a><?php endif; ?>
  <?php if (function_exists('memorial_plugin_consent_mode') && memorial_plugin_banner_on() && memorial_plugin_consent_mode() === 'ask') : ?><a href="#" data-consent-reset><?php esc_html_e( 'Cookie settings', 'memorial-plugin' ); ?></a><?php endif; ?>
</div>
