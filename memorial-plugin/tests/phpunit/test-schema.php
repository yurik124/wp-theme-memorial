<?php
/**
 * Schema.org markup: the organization with branches (seo/schema.php), the services (seo/schema-service.php),
 * the products of a section (seo/seo.php) and the one-time move of 23.11.2026 (seo/seo-upgrade.php).
 *
 * @package memorial-plugin
 */

/**
 * Schema tests.
 */
class Test_Memorial_Plugin_Schema extends WP_UnitTestCase {

	/**
	 * Contacts as on the site: the main office and a branch.
	 */
	private function contacts() {
		update_option(
			'mem_contacts',
			array(
				'phones'     => array(
					array(
						'num'  => '+7 (905) 675-22-24',
						'note' => '',
					),
					array(
						'num'  => '+7 (4722) 37-16-58',
						'note' => '',
					),
				),
				'emails'     => array( array( 'email' => 'memorialbel@mail.ru' ) ),
				'addrs'      => array(
					array(
						'addr'   => '308023, г. Белгород, ул. Студенческая, 40, корп. 10',
						'hours'  => 'Пн–Сб с 9:00 до 17:00',
						'title'  => '',
						'map'    => '',
						'yandex' => 'https://yandex.ru/maps/org/agentstvo_ritualnykh_uslug_memorial/75717068753/',
						'twogis' => 'https://2gis.ru/belgorod/branches/70000001006496308/firm/70000001006496307/36.564591%2C50.620132',
						'geo'    => '',
					),
					array(
						'addr'   => '308024, г. Белгород, ул. Щорса, 21',
						'hours'  => 'Ежедневно с 8:30 до 17:00',
						'title'  => '',
						'map'    => '',
						'yandex' => 'https://yandex.com/maps/org/memorial/1023942285/',
						'twogis' => '',
						'geo'    => '50.576618, 36.569422',
					),
				),
				'night'      => 'Звонки круглосуточно',
				'messengers' => array(
					'max'      => '',
					'telegram' => 'memorialbel',
					'whatsapp' => '',
				),
			)
		);
	}

	public function test_hours_and_addresses() {
		$this->assertSame( 'Mo-Sa 09:00-17:00', memorial_plugin_schema_hours( 'Пн–Сб с 9:00 до 17:00' ) );
		$this->assertSame( 'Mo-Su 08:30-17:00', memorial_plugin_schema_hours( 'Ежедневно 8:30–17:00' ) );
		$this->assertSame( '', memorial_plugin_schema_hours( 'по договорённости' ) );
		$spec = memorial_plugin_schema_hours_spec( 'Mo-Sa 09:00-17:00' );
		$this->assertSame( array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ), $spec['dayOfWeek'] );
		$this->assertSame( '09:00', $spec['opens'] );
		$a = memorial_plugin_schema_address( '308023, г. Белгород, ул. Студенческая, 40, корп. 10' );
		$this->assertSame( 'ул. Студенческая, 40, корп. 10', $a['streetAddress'] );
		$this->assertSame( '308023', $a['postalCode'] );
		$this->assertSame( 'Белгород', $a['addressLocality'] );
	}

	public function test_cards_and_coordinates() {
		$this->assertSame( 'https://yandex.ru/maps/org/75717068753/', memorial_plugin_schema_yandex_url( 'https://yandex.ru/maps/org/agentstvo_ritualnykh_uslug_memorial/75717068753/' ) );
		$this->assertSame( 'https://yandex.ru/maps/org/1023942285/', memorial_plugin_schema_yandex_url( 'https://yandex.com/maps/org/memorial/1023942285/' ) );
		$this->assertSame( '', memorial_plugin_schema_yandex_url( 'https://yandex.ru/maps/4/belgorod/house/ulitsa_shchorsa_21/Z08Y/' ), 'a house is not a card' );
		$this->assertSame( 'https://2gis.ru/belgorod/firm/70000001006496307', memorial_plugin_schema_2gis_url( 'https://2gis.ru/belgorod/search//firm/70000001006496307/36.56459150.620132' ) );
		$this->assertSame( 'https://2gis.ru/belgorod/firm/6474452745374101', memorial_plugin_schema_2gis_url( 'https://2gis.ru/belgorod/branches/70000001006496308/firm/6474452745374101/36.569422%2C50.576618' ) );
		$this->assertSame( array( 50.620132, 36.564591 ), memorial_plugin_schema_coords( array( 'twogis' => 'https://2gis.ru/belgorod/branches/1/firm/70000001006496307/36.564591%2C50.620132' ) ) );
		$this->assertSame( array( 50.576618, 36.569422 ), memorial_plugin_schema_coords( array( 'geo' => '50.576618, 36.569422' ) ) );
		$this->assertSame( array( 50.6, 36.5 ), memorial_plugin_schema_coords( array( 'map' => 'https://yandex.ru/maps/?ll=36.5,50.6&z=16' ) ) );
		$this->assertNull( memorial_plugin_schema_coords( array() ) );
	}

	public function test_organization_with_a_branch() {
		$this->contacts();
		$org = memorial_plugin_schema_org();
		$this->assertSame( 'LocalBusiness', $org['@type'] );
		$this->assertSame( home_url( '/#organization' ), $org['@id'] );
		$this->assertSame( 'ул. Студенческая, 40, корп. 10', $org['address']['streetAddress'] );
		$this->assertSame( array( '+79056752224', '+74722371658' ), $org['telephone'] );
		$this->assertSame( 50.620132, $org['geo']['latitude'] );
		$this->assertSame( '09:00', $org['openingHoursSpecification'][0]['opens'] );
		$this->assertContains( 'https://yandex.ru/maps/org/75717068753/', $org['sameAs'] );
		$this->assertContains( 'https://2gis.ru/belgorod/firm/70000001006496307', $org['sameAs'] );
		$this->assertEmpty( preg_grep( '~t\.me|telegram~', $org['sameAs'] ), 'no Telegram' );
		$this->assertCount( 1, $org['department'] );
		$dep = $org['department'][0];
		$this->assertSame( home_url( '/#filial-2' ), $dep['@id'] );
		$this->assertSame( 'ул. Щорса, 21', $dep['address']['streetAddress'] );
		$this->assertSame( array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ), $dep['openingHoursSpecification'][0]['dayOfWeek'] );
		$this->assertSame( array( 'https://yandex.ru/maps/org/1023942285/' ), $dep['sameAs'] );
		$this->assertSame( 'ContactPoint', $org['contactPoint']['@type'] );
		$this->assertStringNotContainsString( 'FuneralHome', wp_json_encode( $org ) );
		$ref = memorial_plugin_schema_org_ref();
		$this->assertSame( array( '@type', '@id', 'name' ), array_keys( $ref ), 'a reference has no full data' );
	}

	public function test_microdata_once_per_page() {
		$this->contacts();
		unset( $GLOBALS['memorial_plugin_org_micro'], $GLOBALS['memorial_plugin_org_micro_open'] );
		$this->assertTrue( memorial_plugin_org_micro_place( true ) );
		$attrs  = memorial_plugin_org_micro_attrs( 1 );
		$fields = memorial_plugin_org_micro_fields( 1 );
		memorial_plugin_org_micro_place( false );
		$this->assertStringContainsString( 'itemtype="https://schema.org/LocalBusiness"', $attrs );
		$this->assertStringContainsString( 'itemid="' . home_url( '/#filial-2' ) . '"', $attrs );
		$this->assertStringContainsString( 'itemprop="openingHours" content="Mo-Su 08:30-17:00"', $fields );
		$this->assertStringContainsString( 'itemprop="streetAddress" content="ул. Щорса, 21"', $fields );
		$this->assertStringContainsString( 'itemprop="telephone" content="+79056752224"', $fields );
		// The footer after the “Contacts” cards: no second microdata.
		$this->assertFalse( memorial_plugin_org_micro_place( true ) );
		$this->assertSame( '', memorial_plugin_org_micro_attrs( 0 ) );
		$this->assertSame( '', memorial_plugin_org_micro_fields( 0 ) );
		unset( $GLOBALS['memorial_plugin_org_micro'], $GLOBALS['memorial_plugin_org_micro_open'] );
	}

	public function test_service_markup_and_prices() {
		$this->assertSame( array( 'min', 12300.0 ), memorial_plugin_service_price_parse( "от 12\u{202F}300\u{00A0}₽" ) );
		$this->assertSame( array( 'range', 200.0, 300.0 ), memorial_plugin_service_price_parse( '200–300 ₽' ) );
		$this->assertSame( array( 'exact', 500.0 ), memorial_plugin_service_price_parse( '500 ₽' ) );
		$this->assertNull( memorial_plugin_service_price_parse( 'по запросу' ) );

		$hall = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Траурный зал',
				'post_excerpt' => '',
				'post_content' => '<!-- wp:paragraph --><p>Собственный зал для прощания.</p><!-- /wp:paragraph -->',
			)
		);
		$this->assertNull( memorial_plugin_service_ld( $hall ), 'not switched on — no markup' );
		update_post_meta( $hall, '_mem_ld_service', '1' );
		update_post_meta( $hall, '_mem_ld_service_type', 'Прощание и отпевание' );
		$ld = memorial_plugin_service_ld( $hall );
		$this->assertSame( 'Service', $ld['@type'] );
		$this->assertSame( get_permalink( $hall ), $ld['url'] );
		$this->assertSame( 'Прощание и отпевание', $ld['serviceType'] );
		$this->assertSame( 'Собственный зал для прощания.', $ld['description'] );
		$this->assertSame( home_url( '/#organization' ), $ld['provider']['@id'] );
		$this->assertArrayNotHasKey( 'offers', $ld, 'the hall has no price' );

		update_option(
			'mem_prices',
			array(
				'packages' => array(
					'items' => array(
						array(
							'_id'  => 'p1',
							'name' => 'Эконом',
							'show' => 1,
							'rows' => array(
								array(
									'name'  => 'Гроб',
									'price' => 20000,
								),
							),
						),
						array(
							'_id'  => 'p2',
							'name' => 'Премиум',
							'show' => 1,
							'rows' => array(
								array(
									'name'  => 'Гроб',
									'price' => 90000,
								),
							),
						),
						array(
							'_id'  => 'p3',
							'name' => 'Кремация',
							'show' => 1,
							'rows' => array(
								array(
									'name'  => 'Кремация',
									'price' => 58000,
								),
							),
						),
					),
				),
			)
		);
		$offer = memorial_plugin_service_offer( 'packages', 'https://example.com/' );
		$this->assertSame( 'AggregateOffer', $offer['@type'] );
		$this->assertSame( 20000, $offer['lowPrice'] );
		$this->assertSame( 90000, $offer['highPrice'], 'the cremation package is not a funeral package' );
		$offer = memorial_plugin_service_offer( 'kremaciya', 'https://example.com/' );
		$this->assertSame( 58000, $offer['price'] );
		$this->assertSame( 58000, $offer['priceSpecification']['minPrice'] );
		$this->assertNull( memorial_plugin_service_offer( '', 'https://example.com/' ) );
		delete_option( 'mem_prices' );
	}

	public function test_product_belongs_to_the_page_of_its_section() {
		$type = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'name'     => 'Венки',
			)
		);
		$sub  = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'name'     => 'Корзины',
				'parent'   => $type,
			)
		);
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Венки',
			)
		);
		update_post_meta( $page, '_mem_cat', $type );
		$prod = self::factory()->post->create(
			array(
				'post_type'   => 'mem_product',
				'post_title'  => '№ 66',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $prod, array( $sub ), 'mem_cat' );
		delete_transient( 'memorial_plugin_page_map' );
		if ( function_exists( 'memorial_plugin_page_map' ) && memorial_plugin_term_page( $type ) !== $page ) {
			$this->markTestSkipped( 'the page of a section is taken from the page map' );
		}
		$this->assertSame( get_permalink( $page ) . '#p-' . $prod, memorial_plugin_product_home( $prod ), 'a subsection without a page — the page of the upper section' );
	}

	public function test_upgrade_fixes_the_address_and_marks_the_services() {
		update_option(
			'mem_contacts',
			array(
				'addrs' => array(
					array(
						'addr'  => '308000, г. Белгород, ул. Студенческая, 40',
						'hours' => 'Пн–Сб с 9:00 до 17:00',
					),
					array(
						'addr'  => '308024, г. Белгород, ул. Щорса, 21',
						'hours' => 'Ежедневно с 8:30 до 17:00',
					),
				),
			)
		);
		$parent = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'ritualnye-uslugi',
			)
		);
		$hall   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'traurnyj-zal',
				'post_parent' => $parent,
			)
		);
		$crem   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'kremacziya',
				'post_parent' => $parent,
			)
		);
		$dry = memorial_plugin_schema_upgrade_1( true );
		$this->assertArrayHasKey( $hall, $dry['services'] );
		$this->assertSame( '', get_post_meta( $hall, '_mem_ld_service', true ), 'a dry run writes nothing' );

		memorial_plugin_schema_upgrade_1();
		$addrs = get_option( 'mem_contacts' )['addrs'];
		$this->assertSame( '308023, г. Белгород, ул. Студенческая, 40, корп. 10', $addrs[0]['addr'] );
		$this->assertSame( 'https://2gis.ru/belgorod/firm/70000001006496307', $addrs[0]['twogis'] );
		$this->assertSame( '50.576618, 36.569422', $addrs[1]['geo'] );
		$this->assertSame( 'https://yandex.ru/maps/org/memorial/1023942285/', $addrs[1]['yandex'] );
		$this->assertSame( '1', get_post_meta( $hall, '_mem_ld_service', true ) );
		$this->assertSame( '', get_post_meta( $hall, '_mem_ld_service_price', true ), 'the hall — no price' );
		$this->assertSame( 'kremaciya', get_post_meta( $crem, '_mem_ld_service_price', true ) );

		// The second run changes nothing: the address has its building, the services are set.
		$again = memorial_plugin_schema_upgrade_1( true );
		$this->assertArrayNotHasKey( 'addrs', $again );
		$this->assertArrayNotHasKey( 'services', $again );
	}

	public function test_a_question_on_two_pages_is_marked_on_the_deeper_one() {
		if ( ! function_exists( 'memorial_plugin_faq_all' ) ) {
			$this->markTestSkipped( 'questions and answers are switched off' );
		}
		$q      = '<!-- wp:details --><details class="wp-block-details"><summary>Сколько стоит венок?</summary><!-- wp:paragraph --><p>От 800 ₽.</p><!-- /wp:paragraph --></details><!-- /wp:details -->';
		$parent = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $q,
			)
		);
		$child  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_parent'  => $parent,
				'post_content' => $q,
			)
		);
		memorial_plugin_faq_flush();
		$item = array(
			array(
				'@type' => 'Question',
				'name'  => 'Сколько стоит венок?',
			),
		);
		$this->assertSame( array(), memorial_plugin_faq_own_questions( $item, $parent ), 'the upper page does not mark it' );
		$this->assertCount( 1, memorial_plugin_faq_own_questions( $item, $child ) );
		$this->assertCount( 1, memorial_plugin_faq_own_questions( array( array( 'name' => 'Только здесь' ) ), $parent ), 'a question of one page stays' );
	}
}
