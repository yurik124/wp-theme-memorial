<?php
/**
 * Owner's tasks of 26.11.2026: links to other sites (seo/external-links.php), the “We are on 2GIS” button
 * (blocks/home/home.php), “More about the benefit” on its own line (prices/price-blocks.php).
 *
 * @package memorial-plugin
 */

/**
 * Tests of the tasks of 26.11.2026.
 */
class Test_Memorial_Plugin_Ui_2611 extends WP_UnitTestCase {

	public function test_external_link_gets_new_tab_and_nofollow() {
		$set  = array(
			'on'   => true,
			'keep' => array(),
		);
		$html = memorial_plugin_links_mark( '<p><a href="https://2gis.ru/belgorod/firm/1" rel="noopener sponsored">2ГИС</a></p>', $set, 'memorialbel.ru' );
		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertMatchesRegularExpression( '~rel="noopener sponsored nofollow"~', $html );
		$this->assertStringContainsString( 'aria-describedby="mem-newtab"', $html );
	}

	public function test_own_and_special_links_are_not_touched() {
		$set = array(
			'on'   => true,
			'keep' => array(),
		);
		foreach ( array(
			'<a href="https://www.memorialbel.ru/ceny/">Цены</a>',
			'<a href="/ceny/">Цены</a>',
			'<a href="tel:+74722000000">Позвонить</a>',
			'<a href="mailto:a@b.ru">Почта</a>',
			'<a href="https://cdn.rewio.ru/1.jpg" data-lb><img src="x.jpg" alt=""></a>',
		) as $in ) {
			$this->assertSame( $in, memorial_plugin_links_mark( $in, $set, 'memorialbel.ru' ), $in );
		}
	}

	public function test_kept_domain_opens_in_new_tab_without_nofollow() {
		$set  = array(
			'on'   => true,
			'keep' => array( 'yandex.ru' ),
		);
		$html = memorial_plugin_links_mark( '<a href="https://maps.yandex.ru/org/1/">Яндекс</a>', $set, 'memorialbel.ru' );
		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertStringNotContainsString( 'nofollow', $html );
	}

	public function test_links_settings_read_domains() {
		update_option(
			'mem_modules_page',
			array(
				'links' => array(
					'on'   => 1,
					'keep' => "https://www.example.ru/page\nfoo.org, bar.net",
				),
			)
		);
		$this->assertSame( array( 'example.ru', 'foo.org', 'bar.net' ), memorial_plugin_links_settings()['keep'] );
		update_option( 'mem_modules_page', array( 'links' => array( 'on' => 0 ) ) );
		$this->assertFalse( memorial_plugin_links_settings()['on'] );
	}

	public function test_2gis_button_takes_the_studencheskaya_address() {
		update_option(
			'mem_contacts',
			array(
				'addrs' => array(
					array(
						'addr'   => '308024, г. Белгород, ул. Щорса, 21',
						'twogis' => 'https://2gis.ru/belgorod/firm/6474452745374101',
					),
					array(
						'addr'   => '308023, г. Белгород, ул. Студенческая, 40, корп. 10',
						'twogis' => 'https://2gis.ru/belgorod/geo/1/firm/70000001006496307/36.5,50.6',
					),
				),
			)
		);
		$this->assertSame( 'https://2gis.ru/belgorod/firm/70000001006496307', memorial_plugin_home_2gis() );
		$this->assertStringContainsString( '70000001006496307', memorial_plugin_home_render_review_buttons() );
	}

	public function test_benefit_more_link_is_its_own_paragraph() {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'poleznoe',
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'posobie-na-pogrebenie',
				'post_parent' => $page,
				'post_status' => 'publish',
			)
		);
		$html = memorial_plugin_benefits_render( array( 'heading' => false ) );
		if ( false === strpos( $html, 'mbenefit__grid' ) ) {
			$this->markTestSkipped( 'No benefit sums in the defaults.' );
		}
		$this->assertMatchesRegularExpression( '~</p><p class="mbenefit__more"><a href="[^"]+posobie-na-pogrebenie~', $html );
	}

	/**
	 * Separate prices with a benefit.
	 *
	 * @return array
	 */
	private function prices_with_benefit() {
		return array(
			'other' => array(
				'items' => array(
					array(
						'name'    => 'Лента',
						'what'    => 'custom',
						'price'   => '500',
						'code'    => 'lenta',
						'updated' => '01.01.2026',
					),
					array(
						'name'    => 'Пособие на погребение',
						'what'    => 'custom',
						'price'   => '10 000',
						'code'    => 'posobie',
						'updated' => '01.02.2026',
					),
				),
			),
		);
	}

	public function test_benefit_change_does_not_move_the_price_date() {
		update_option( 'mem_prices', $this->prices_with_benefit() );
		delete_option( 'mem_prices_changed' );
		delete_option( 'mem_benefits_checked' );
		$v = $this->prices_with_benefit();
		$v['other']['items'][1]['price'] = '11 000';
		update_option( 'mem_prices', $v );
		$this->assertFalse( get_option( 'mem_prices_changed' ), 'a benefit is not a price' );
		$this->assertGreaterThan( 0, (int) get_option( 'mem_benefits_checked' ) );
		$v['other']['items'][0]['price'] = '600';
		update_option( 'mem_prices', $v );
		$this->assertGreaterThan( 0, (int) get_option( 'mem_prices_changed' ) );
	}

	public function test_due_prices_after_a_month_and_benefits_after_february() {
		update_option( 'mem_prices', $this->prices_with_benefit() );
		$tz = wp_timezone();
		update_option( 'mem_prices_changed', ( new DateTime( '2026-10-20 12:00', $tz ) )->getTimestamp() );
		update_option( 'mem_benefits_checked', ( new DateTime( '2026-03-01 12:00', $tz ) )->getTimestamp() );
		$due = memorial_plugin_price_check_due( ( new DateTime( '2026-11-10 12:00', $tz ) )->getTimestamp() );
		$this->assertFalse( $due['prices'] );
		$this->assertFalse( $due['benefits'] );
		$due = memorial_plugin_price_check_due( ( new DateTime( '2026-11-21 12:00', $tz ) )->getTimestamp() );
		$this->assertTrue( $due['prices'], 'a month passed' );
		$due = memorial_plugin_price_check_due( ( new DateTime( '2027-02-01 09:00', $tz ) )->getTimestamp() );
		$this->assertTrue( $due['benefits'], '1 February' );
	}

	public function test_confirm_prices_dates_the_rows_and_the_site_but_not_the_benefits() {
		update_option( 'mem_prices', $this->prices_with_benefit() );
		update_option( 'mem_prices_changed', 1000 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		memorial_plugin_price_confirm( 'prices' );
		$rows = get_option( 'mem_prices' )['other']['items'];
		$this->assertSame( wp_date( 'd.m.Y' ), $rows[0]['updated'] );
		$this->assertSame( '01.02.2026', $rows[1]['updated'], 'the benefit row keeps its date' );
		$this->assertGreaterThan( time() - 60, (int) get_option( 'mem_prices_changed' ) );
		$this->assertFalse( memorial_plugin_price_check_due()['prices'] );

		memorial_plugin_price_confirm( 'benefits' );
		$this->assertSame( wp_date( 'd.m.Y' ), get_option( 'mem_prices' )['other']['items'][1]['updated'] );
		$this->assertGreaterThan( time() - 60, (int) get_option( 'mem_benefits_checked' ) );
	}

	public function test_reminder_email_goes_once_a_week() {
		update_option( 'mem_prices', $this->prices_with_benefit() );
		update_option( 'mem_prices_changed', time() - 40 * DAY_IN_SECONDS );
		delete_option( 'memorial_plugin_price_check' );
		reset_phpmailer_instance();
		memorial_plugin_price_check_run();
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertStringContainsString( wp_date( 'd.m.Y', time() - 40 * DAY_IN_SECONDS ), $mailer->get_sent()->body );
		reset_phpmailer_instance();
		memorial_plugin_price_check_run();
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent(), 'not again the same week' );
	}
}
