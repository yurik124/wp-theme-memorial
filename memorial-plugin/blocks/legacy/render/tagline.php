<?php
/** «Ритуальная компания, которая на рынке больше N лет» (или описание сайта) */
defined('ABSPATH') || exit;
?>
<p class="footer__slogan"><?php echo memorial_plugin_years()
    ? esc_html(memorial_plugin_nbsp('Ритуальная компания, которая на рынке больше ' . (int) memorial_plugin_years() . ' лет'))
    : esc_html(get_bloginfo('description') ?: 'Ритуальная компания'); ?></p>
