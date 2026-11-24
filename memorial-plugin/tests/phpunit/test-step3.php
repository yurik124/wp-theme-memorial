<?php
/**
 * Step 3 of the spec of 24.11.2026: landmarks of the addresses, “Where we work”, the one-time move of the
 * hours and landmarks, “Funeral transport”, “Source and date of the check”, the patterns.
 *
 * @package memorial-plugin
 */

/**
 * Step 3 tests.
 */
class Test_Memorial_Plugin_Step3 extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( 'mem_contacts' );
		delete_option( 'mem_transport' );
		delete_option( 'mem_prices' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Contacts with two addresses as on the site before the move.
	 */
	private function contacts() {
		$c          = memorial_plugin_contacts_defaults();
		$c['addrs'] = array(
			array(
				'addr'  => '308023, г. Белгород, ул. Студенческая, 40, корп. 10',
				'hours' => 'Пн–Сб с 9:00 до 17:00',
			),
			array(
				'addr'      => '308024, г. Белгород, ул. Щорса, 21',
				'hours'     => 'Ежедневно с 8:30 до 17:00',
				'landmarks' => 'своё',
			),
		);
		update_option( 'mem_contacts', $c );
	}

	public function test_move_sets_hours_and_fills_empty_landmarks() {
		$this->contacts();
		$dry = memorial_plugin_contacts_upgrade_3( true );
		$this->assertNotEmpty( $dry['changed'] );
		$this->assertSame( 'Пн–Сб с 9:00 до 17:00', get_option( 'mem_contacts' )['addrs'][0]['hours'], 'a dry run writes nothing' );

		memorial_plugin_contacts_upgrade_3();
		$a = get_option( 'mem_contacts' )['addrs'];
		$this->assertSame( 'Пн–Сб 8:30–17:00, вс — выходной', $a[0]['hours'] );
		$this->assertSame( 'Пн–Пт 9:00–18:00, сб–вс 9:00–17:00', $a[1]['hours'] );
		$this->assertSame( 'Восточный округ', $a[0]['district'] );
		$this->assertSame( 1, $a[0]['hall'] );
		$this->assertSame( 'своё', $a[1]['landmarks'], 'a field filled by the owner stays' );
		$this->assertSame( 'Mo-Fr 09:00-18:00, Sa-Su 09:00-17:00', memorial_plugin_schema_hours( $a[1]['hours'] ) );
		$this->assertEmpty( memorial_plugin_contacts_upgrade_3( true )['changed'] ?? array(), 'once more — nothing' );
	}

	public function test_cards_show_landmarks_and_filter_by_kind() {
		$this->contacts();
		memorial_plugin_contacts_upgrade_3();
		$all = memorial_plugin_contacts_cards_render();
		$this->assertStringContainsString( 'Восточный округ · ост. УТЭП · у УТЭПа · у БелГУ', $all );
		$this->assertStringContainsString( 'kaddr__kind', $all );
		$hall = memorial_plugin_contacts_cards_render( array( 'only' => 'hall' ) );
		$this->assertStringContainsString( 'Студенческая', $hall );
		$this->assertStringNotContainsString( 'Щорса', $hall );
		$bare = memorial_plugin_contacts_cards_render(
			array(
				'landmarks' => false,
				'route'     => false,
			)
		);
		$this->assertStringNotContainsString( 'kaddr__notes', $bare );
		$this->assertStringNotContainsString( 'kaddr__route', $bare );
		$branches = memorial_plugin_schema_branches();
		$this->assertStringContainsString( 'у БелГУ', $branches[0]['desc'] );
	}

	public function test_service_area_and_area_served() {
		$funeral = memorial_plugin_service_area_render();
		$this->assertStringContainsString( 'Белгородской области', $funeral );
		$this->assertStringContainsString( 'Дубовое', $funeral );
		$this->assertStringContainsString( 'Выезд агента', $funeral );
		$this->assertStringContainsString( 'по всей области', memorial_plugin_service_area_render( array( 'context' => 'landscaping' ) ) );
		$served = wp_list_pluck( memorial_plugin_schema_area_served(), 'name' );
		$this->assertContains( 'Белгород', $served );
		$this->assertContains( 'Белгородская область', $served );
		$this->assertContains( 'Таврово', $served );
	}

	public function test_vehicle_with_price_and_bus() {
		$html = memorial_plugin_vehicle_render();
		$this->assertStringContainsString( 'Ford Transit', $html );
		$this->assertStringContainsString( '8', $html );
		$this->assertStringContainsString( 'is-style-checks', $html );
		$this->assertStringContainsString( 'mvehicle__bus', $html );
		$this->assertStringNotContainsString( 'mvehicle__bus', memorial_plugin_vehicle_render( array( 'bus' => false ) ) );
		$this->assertStringNotContainsString( 'mvehicle__price', memorial_plugin_vehicle_render( array( 'price' => false ) ) );
		$p                   = memorial_plugin_prices_defaults();
		$p['other']['items'] = array(
			array(
				'name'  => 'Катафалк',
				'what'  => 'custom',
				'price' => '6000',
				'code'  => 'katafalk',
			),
		);
		update_option( 'mem_prices', $p );
		$this->assertMatchesRegularExpression( '/6\D?000/u', memorial_plugin_vehicle_render() );
	}

	public function test_checked_date_and_reminder() {
		$html = memorial_plugin_checked_render(
			array(
				'date'    => '2026-11-24',
				'sources' => array(
					array(
						'title' => 'Социальный фонд России',
						'url'   => 'https://sfr.gov.ru/',
					),
				),
			)
		);
		$this->assertStringContainsString( '24.11.2026', $html );
		$this->assertStringContainsString( 'href="https://sfr.gov.ru/"', $html );
		$this->assertStringNotContainsString( 'nofollow', $html );
		$this->assertTrue( memorial_plugin_checked_old( '2025-01-10', strtotime( '2026-11-24' ) ) );
		$this->assertFalse( memorial_plugin_checked_old( '2026-06-01', strtotime( '2026-11-24' ) ) );
		$old    = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:memorial-plugin/checked {"date":"2024-01-10"} /-->',
			)
		);
		$states = apply_filters( 'display_post_states', array(), $old );
		$this->assertArrayHasKey( 'memorial-plugin-checked', $states );
	}

	public function test_patterns_are_registered() {
		$registry = WP_Block_Patterns_Registry::get_instance();
		foreach ( array( 'memorial/what-to-do', 'memorial/crematorium', 'memorial/urn' ) as $name ) {
			$this->assertTrue( $registry->is_registered( $name ), $name );
		}
		$what = $registry->get_registered( 'memorial/what-to-do' )['content'];
		$this->assertStringContainsString( 'mem_contacts:phones.@first.num', $what, 'the phone is a shared text, not digits' );
		$this->assertDoesNotMatchRegularExpression( '/\+7|\d{3}-\d{2}-\d{2}/', $what );
	}
}
