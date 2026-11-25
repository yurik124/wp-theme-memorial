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
}
