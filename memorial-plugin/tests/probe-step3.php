<?php
/**
 * Probe on the site copy (PROBE_CLI → `wp eval-file`): what the one-time move memorial_plugin_contacts_3 did on
 * real data (the workflow runs admin_init before) and a page with the blocks and patterns of step 3 for the
 * screenshots. Not deployed (tests/).
 *
 * @package memorial-plugin
 */

// phpcs:ignoreFile -- a probe script.
$log = get_option( 'memorial_plugin_contacts_3_log' );
echo 'flag: ' . var_export( get_option( 'memorial_plugin_contacts_3' ), true ) . "\n";
print_r( $log['changed'] ?? 'nothing changed' );
echo 'foreign phone: ' . ( $log['foreign_phone'] ?? '—' ) . "\n";
foreach ( memorial_plugin_addrs() as $a ) {
	echo $a['addr'] . ' | ' . $a['hours'] . ' → ' . memorial_plugin_schema_hours( $a['hours'] ) . ' | ' . implode( ' · ', memorial_plugin_addr_notes( $a ) ) . "\n";
}
echo 'areaServed: ' . wp_json_encode( memorial_plugin_schema_area_served(), JSON_UNESCAPED_UNICODE ) . "\n";
$reg  = WP_Block_Patterns_Registry::get_instance();
$what = $reg->get_registered( 'memorial/what-to-do' )['content'] ?? '';
$id   = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'probe-step3',
		'post_title'   => 'Проверка блоков шага 3',
		'post_content' => '<!-- wp:memorial-plugin/vehicle /-->' . "\n\n"
			. '<!-- wp:memorial-plugin/service-area /-->' . "\n\n"
			. '<!-- wp:memorial-plugin/service-area {"context":"landscaping"} /-->' . "\n\n"
			. $what . "\n\n"
			. ( $reg->get_registered( 'memorial/crematorium' )['content'] ?? '' ) . "\n\n"
			. ( $reg->get_registered( 'memorial/urn' )['content'] ?? '' ) . "\n\n"
			. '<!-- wp:memorial-plugin/checked {"date":"2026-11-24","sources":[{"title":"Социальный фонд России","url":"https://sfr.gov.ru/"},{"title":"Закон «О погребении и похоронном деле»","url":"http://pravo.gov.ru/"}]} /-->',
	)
);
echo 'probe page: ' . get_permalink( $id ) . "\n";
