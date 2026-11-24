<?php
/**
 * Probe on the site copy (PROBE_CLI → `wp eval-file`): what the one-time move memorial_plugin_prices_2 did on
 * real data (the workflow runs admin_init before) — the codes added and the “Payment” blocks of the pages.
 * Not deployed (tests/).
 *
 * @package memorial-plugin
 */

// phpcs:ignoreFile -- a probe script.
$log = get_option( 'memorial_plugin_prices_2_log' );
echo 'flag: ' . var_export( get_option( 'memorial_plugin_prices_2' ), true ) . "\n";
echo 'codes added: ' . implode( ', ', (array) ( $log['codes'] ?? array() ) ) . "\n";
echo 'skipped pages: ' . implode( ', ', (array) ( $log['skipped'] ?? array() ) ) . "\n";
foreach ( array_keys( (array) ( $log['posts'] ?? array() ) ) as $id ) {
	preg_match_all( '/<!--\s+wp:memorial-plugin\/payment[^>]*-->/', (string) get_post_field( 'post_content', $id ), $m );
	echo '/' . get_page_uri( $id ) . '/: ' . implode( ' ', $m[0] ) . "\n";
}
foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 's' => 'memorial-plugin/payment' ) ) as $p ) {
	if ( ! isset( $log['posts'][ $p->ID ] ) ) {
		preg_match_all( '/<!--\s+wp:memorial-plugin\/payment[^>]*-->/', $p->post_content, $m );
		echo 'unchanged /' . get_page_uri( $p ) . '/: ' . implode( ' ', $m[0] ) . "\n";
	}
}
echo "\nОтдельные цены:\n";
foreach ( memorial_plugin_prices( 'other' )['items'] as $it ) {
	echo ' ' . ( $it['code'] ?? '' ) . ' | ' . ( $it['name'] ?? '' ) . ' | ' . ( $it['price'] ?? '' ) . ' | ' . ( $it['updated'] ?? '' ) . "\n";
}
echo "\nОплата (похороны): " . wp_strip_all_tags( memorial_plugin_payment_render( array( 'context' => 'funeral' ) ) ) . "\n";
echo 'Оплата (памятники): ' . wp_strip_all_tags( memorial_plugin_payment_render( array( 'context' => 'monuments' ) ) ) . "\n";
echo 'Гарантии (похороны): ' . wp_strip_all_tags( memorial_plugin_guarantees_render( array( 'set' => 'funeral' ) ) ) . "\n";
echo 'Пособие: ' . wp_strip_all_tags( memorial_plugin_benefits_render() ) . "\n";
echo 'Цены по отдельности (похороны): ' . wp_strip_all_tags( memorial_plugin_price_list_render( array( 'preset' => 'funeral' ) ) ) . "\n";

// A page with the new blocks for the screenshots of the scenario «шаг 2» (only on the copy).
$id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'probe-step2',
		'post_title'   => 'Проверка блоков шага 2',
		'post_content' => '<!-- wp:memorial-plugin/guarantees {"set":"funeral"} /-->' . "\n\n"
			. '<!-- wp:memorial-plugin/guarantees {"set":"monuments","cols":2,"title":"Гарантии на памятники"} /-->' . "\n\n"
			. '<!-- wp:memorial-plugin/benefits /-->' . "\n\n"
			. '<!-- wp:memorial-plugin/price-list {"preset":"monuments","title":"Цены на благоустройство","note":"Итог фиксируется в договоре"} /-->' . "\n\n"
			. '<!-- wp:memorial-plugin/price-list {"rows":[{"code":"venki_ot","label":"Венки","page":"/venki/"},{"code":"dostavka","label":"Доставка венков"},{"code":"lenta","label":"Надпись на ленту"}]} /-->' . "\n\n"
			. '<!-- wp:memorial-plugin/payment {"context":"funeral"} /-->' . "\n\n"
			. '<!-- wp:memorial-plugin/payment {"context":"monuments"} /-->',
	)
);
echo "\nprobe page: " . get_permalink( $id ) . "\n";
