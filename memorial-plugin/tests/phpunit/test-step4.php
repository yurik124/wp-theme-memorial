<?php
/**
 * Step 4 of the spec of 24.11.2026: the landmarks of Studencheskaya (owner), “Write in MAX” in the call to
 * action, the cookie banner out of the snippets, the price table pattern without digits.
 *
 * @package memorial-plugin
 */

/**
 * Step 4 tests.
 */
class Test_Memorial_Plugin_Step4 extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( 'mem_contacts' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_landmarks_fixed_only_where_step3_wrote_them() {
		$c          = memorial_plugin_contacts_defaults();
		$c['addrs'] = array(
			array(
				'addr'      => '308023, г. Белгород, ул. Студенческая, 40, корп. 10',
				'stop'      => 'УТЭП',
				'landmarks' => 'у УТЭПа, у БелГУ',
			),
		);
		update_option( 'mem_contacts', $c );
		$dry = memorial_plugin_contacts_upgrade_4( true );
		$this->assertNotEmpty( $dry['changed'] );
		$this->assertSame( 'УТЭП', get_option( 'mem_contacts' )['addrs'][0]['stop'], 'a dry run writes nothing' );
		memorial_plugin_contacts_upgrade_4();
		$a = get_option( 'mem_contacts' )['addrs'][0];
		$this->assertSame( '', $a['stop'] );
		$this->assertSame( 'рядом с магазином «Гиперстрой», у БелГУ', $a['landmarks'] );
		// The owner wrote his own — it stays.
		$c['addrs'][0]['landmarks'] = 'своё';
		$c['addrs'][0]['stop']      = 'Своя';
		update_option( 'mem_contacts', $c );
		$this->assertEmpty( memorial_plugin_contacts_upgrade_4( true )['changed'] ?? array() );
	}

	public function test_cta_writes_in_max_instead_of_call_back() {
		$c                       = memorial_plugin_contacts_defaults();
		$c['messengers']['max']  = 'https://max.ru/u/memorial';
		update_option( 'mem_contacts', $c );
		$html = do_blocks( '<!-- wp:memorial-plugin/cta /-->' );
		$this->assertStringContainsString( 'href="https://max.ru/u/memorial"', $html );
		$this->assertStringNotContainsString( 'data-callback', $html );
		$c['messengers']['max'] = '';
		update_option( 'mem_contacts', $c );
		$this->assertStringContainsString( 'data-callback', do_blocks( '<!-- wp:memorial-plugin/cta /-->' ) );
	}

	public function test_cookie_banner_is_out_of_snippets() {
		if ( ! function_exists( 'memorial_plugin_consent_banner_html' ) || ! memorial_plugin_banner_on() ) {
			$this->markTestSkipped( 'The banner is off in this setup.' );
		}
		$html = memorial_plugin_consent_banner_html();
		$this->assertStringContainsString( 'data-nosnippet', $html );
		$this->assertStringContainsString( '<!--noindex-->', $html );
		$this->assertStringContainsString( '<!--/noindex-->', $html );
	}

	public function test_price_table_pattern_has_no_digits() {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'memorial/price' );
		$this->assertStringContainsString( 'data-value="price"', $pattern['content'] );
		$this->assertStringNotContainsString( '1 000 ₽', $pattern['content'] );
	}
}
