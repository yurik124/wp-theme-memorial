<?php
/**
 * Для проверки микроразметки в CI (wp eval-file, затем tests/diag/schema_check.py): контакты с двумя
 * филиалами, главная, «Контакты», страницы услуг с вопросами, раздел каталога с товарами.
 * На сайт не выгружается (deploy.yml исключает tools/).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

update_option(
	'mem_contacts',
	array_merge(
		(array) get_option( 'mem_contacts', array() ),
		array(
			'phones' => array(
				array(
					'num'  => '+7 (905) 675-22-24',
					'note' => '',
				),
				array(
					'num'  => '+7 (4722) 37-16-58',
					'note' => '',
				),
			),
			'emails' => array( array( 'email' => 'memorialbel@mail.ru' ) ),
			'addrs'  => array(
				array(
					'addr'   => '308023, г. Белгород, ул. Студенческая, 40, корп. 10',
					'hours'  => 'Пн–Сб с 9:00 до 17:00',
					'title'  => '',
					'map'    => '',
					'yandex' => 'https://yandex.ru/maps/org/agentstvo_ritualnykh_uslug_memorial/75717068753/',
					'twogis' => 'https://2gis.ru/belgorod/firm/70000001006496307',
					'geo'    => '50.620132, 36.564591',
				),
				array(
					'addr'   => '308024, г. Белгород, ул. Щорса, 21',
					'hours'  => 'Ежедневно с 8:30 до 17:00',
					'title'  => '',
					'map'    => '',
					'yandex' => 'https://yandex.ru/maps/org/memorial/1023942285/',
					'twogis' => 'https://2gis.ru/belgorod/firm/6474452745374101',
					'geo'    => '50.576618, 36.569422',
				),
			),
			'night'  => 'Звонки круглосуточно',
		)
	)
);

$memorial_plugin_page = static function ( $slug, $title, $content, $parent = 0 ) {
	$found = get_page_by_path( ( $parent ? get_post_field( 'post_name', $parent ) . '/' : '' ) . $slug );
	$data  = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_parent'  => $parent,
		'post_content' => $content,
	);
	if ( $found ) {
		$data['ID'] = $found->ID;
	}
	return (int) wp_insert_post( wp_slash( $data ) );
};
$memorial_plugin_p     = static fn( $t ) => '<!-- wp:paragraph --><p>' . $t . '</p><!-- /wp:paragraph -->';
$memorial_plugin_q     = static fn( $q, $a ) => '<!-- wp:details --><details class="wp-block-details"><summary>' . $q . '</summary><!-- wp:paragraph --><p>' . $a . '</p><!-- /wp:paragraph --></details><!-- /wp:details -->';

$memorial_plugin_home = $memorial_plugin_page( 'glavnaya', 'Главная', $memorial_plugin_p( 'Ритуальные услуги в Белгороде.' ) );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $memorial_plugin_home );
$memorial_plugin_page( 'kontakty', 'Контакты', '<!-- wp:memorial-plugin/contacts-cards /-->' );

$memorial_plugin_hub  = $memorial_plugin_page( 'ritualnye-uslugi', 'Ритуальные услуги', $memorial_plugin_p( 'Все наши услуги.' ) );
$memorial_plugin_hall = $memorial_plugin_page(
	'traurnyj-zal',
	'Траурный зал',
	$memorial_plugin_p( 'Собственный зал для прощания и отпевания.' )
	. $memorial_plugin_q( 'Сколько стоит траурный зал?', 'При организации похорон в нашей компании зал предоставляется бесплатно.' )
	. $memorial_plugin_q( 'Можно ли провести в зале отпевание?', 'Да, в зале можно провести не только прощание, но и отпевание.' ),
	$memorial_plugin_hub
);
$memorial_plugin_crem = $memorial_plugin_page( 'kremacziya', 'Кремация', $memorial_plugin_p( 'Кремация в Туле или Воронеже.' ), $memorial_plugin_hub );
foreach ( array( $memorial_plugin_hall => array( 'Прощание и отпевание в траурном зале', '' ), $memorial_plugin_crem => array( 'Кремация', 'kremaciya' ) ) as $memorial_plugin_id => [ $memorial_plugin_type, $memorial_plugin_price ] ) {
	update_post_meta( $memorial_plugin_id, '_mem_ld_service', '1' );
	update_post_meta( $memorial_plugin_id, '_mem_ld_service_type', $memorial_plugin_type );
	if ( $memorial_plugin_price ) {
		update_post_meta( $memorial_plugin_id, '_mem_ld_service_price', $memorial_plugin_price );
	}
}

// Раздел каталога с товарами: три с ценой, один «по запросу» (в разметку не попадает).
$memorial_plugin_term = term_exists( 'venki-test', 'mem_cat' );
$memorial_plugin_term = $memorial_plugin_term ? (int) $memorial_plugin_term['term_id'] : (int) wp_insert_term( 'Венки (проверка)', 'mem_cat', array( 'slug' => 'venki-test' ) )['term_id'];
foreach ( array( 'Венок № 1' => 2500, 'Венок № 2' => 4500, 'Венок № 3' => 6800, 'Венок № 4' => 0 ) as $memorial_plugin_name => $memorial_plugin_cost ) {
	$memorial_plugin_prod = get_posts(
		array(
			'post_type'   => 'mem_product',
			'title'       => $memorial_plugin_name,
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	$memorial_plugin_pid  = $memorial_plugin_prod ? (int) $memorial_plugin_prod[0] : (int) wp_insert_post(
		array(
			'post_type'   => 'mem_product',
			'post_status' => 'publish',
			'post_title'  => $memorial_plugin_name,
		)
	);
	wp_set_object_terms( $memorial_plugin_pid, array( $memorial_plugin_term ), 'mem_cat' );
	update_post_meta( $memorial_plugin_pid, '_mem_price', $memorial_plugin_cost );
}
$memorial_plugin_cat = $memorial_plugin_page( 'venki-test', 'Венки (проверка)', '<!-- wp:memorial-plugin/catalog {"cat":"venki-test"} /-->' );
update_post_meta( $memorial_plugin_cat, '_mem_cat', $memorial_plugin_term );

if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
	memorial_plugin_page_cache_clear();
}
flush_rewrite_rules();
echo "schema check site: ok\n";
