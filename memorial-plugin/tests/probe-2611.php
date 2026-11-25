<?php
/**
 * Probe on the site copy (PROBE_CLI → `wp eval-file`): the prices were last checked 40 days ago and the benefits
 * last year — so the admin shows both reminders. Not deployed (tests/).
 *
 * @package memorial-plugin
 */

// phpcs:ignoreFile -- a probe script.
update_option( 'mem_prices_changed', time() - 40 * DAY_IN_SECONDS, false );
update_option( 'mem_benefits_checked', strtotime( '2025-06-01' ), false );
print_r( memorial_plugin_price_check_due() );
echo 'benefits: ' . memorial_plugin_benefits_lines() . "\n";
echo '2gis: ' . memorial_plugin_home_2gis() . "\n";
