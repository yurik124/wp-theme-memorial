<?php
/**
 * Probe on the site copy (tests/probe/run.env → PROBE_CLI, `wp eval-file`): times of the import of pages from
 * the repository on real data, step by step. Writes two page files, runs, removes them. Not deployed (tests/).
 *
 * @package memorial-plugin
 */

// phpcs:ignoreFile -- a probe script.
$dir = WP_PLUGIN_DIR . '/memorial-plugin/content/pages/ritualnye-uslugi';
wp_mkdir_p( $dir );
$file = static function ( $slug, $head, $body ) use ( $dir ) {
	file_put_contents( "$dir/$slug.html", '<!-- memorial-page ' . wp_json_encode( $head + array( 'slug' => $slug, 'parent' => 'ritualnye-uslugi', 'seo_title' => 'x', 'seo_description' => 'x', 'status' => 'publish', 'version' => 1 ), JSON_UNESCAPED_UNICODE ) . " -->\n<!-- wp:paragraph -->\n<p>$body</p>\n<!-- /wp:paragraph -->\n" );
};
$file( 'eksgumaciya', array( 'title' => 'Эксгумация (проверка)', 'menu' => array( 'parent' => '/ritualnye-uslugi/', 'label' => 'Эксгумация', 'after' => '/ritualnye-uslugi/gruz-200/' ) ), 'Текст.' );
$file( 'katafalk', array( 'title' => 'Катафалк', 'menu' => null ), 'Новый текст.' );
wp_set_current_user( (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 1 ) );
$t   = microtime( true );
$lap = static function ( $what ) use ( &$t ) {
	$now = microtime( true );
	echo str_pad( $what, 40 ) . round( ( $now - $t ) * 1000 ) . " ms\n";
	$t = $now;
};
// Hooks that take long: time of every action while saving.
$slow = array();
add_action(
	'all',
	static function ( $hook ) use ( &$slow ) {
		static $last = null, $at = 0;
		$now = microtime( true );
		if ( null !== $last && $now - $at > 0.5 ) {
			$slow[] = $last . ' → ' . $hook . ': ' . round( ( $now - $at ) * 1000 ) . ' ms';
		}
		$last = $hook;
		$at   = $now;
	}
);
$files = memorial_plugin_repo_files();
$lap( 'files' );
print_r( array_map( static fn( $f ) => $f['error'], $files ) );
$plan = memorial_plugin_repo_plan();
$lap( 'plan' );
print_r( array_map( static fn( $r ) => $r['id'] . ' ' . $r['status'] . ' ' . $r['menu'] . ' ' . $r['message'], $plan ) );
$done = memorial_plugin_repo_apply( array(), 'auto' );
$lap( 'apply' );
print_r( $done );
memorial_plugin_repo_plan();
$lap( 'plan again' );
echo "slow hooks:\n" . implode( "\n", array_slice( $slow, 0, 40 ) ) . "\n";
// The page and the menu after.
$id = memorial_plugin_repo_page_id( 'ritualnye-uslugi/eksgumaciya' );
echo 'page ' . $id . ' ' . get_post_status( $id ) . ' ' . get_permalink( $id ) . "\n";
echo substr( (string) get_post_field( 'post_content', memorial_plugin_main_nav_id() ), 0, 3000 ) . "\n";
// Clean up: the files; the page stays on the copy (the copy is thrown away).
array_map( 'unlink', glob( "$dir/*.html" ) );
rmdir( $dir );
