<?php
/**
 * PHPUnit bootstrap: the WordPress test library of wp-env (tests-cli) and the plugin.
 * Run: npx wp-env run tests-cli --env-cwd=wp-content/plugins/memorial-plugin vendor/bin/phpunit
 *
 * @package memorial-plugin
 */

$memorial_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $memorial_tests_dir ) {
	$memorial_tests_dir = '/wordpress-phpunit';
}
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $memorial_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/memorial-plugin.php';
	}
);

require $memorial_tests_dir . '/includes/bootstrap.php';
