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

	public function test_gallery_gets_captions_links_and_description() {
		$parent = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'ritualnye-uslugi',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'katafalk',
				'post_parent' => $parent,
			)
		);
		$img     = static fn( $id, $alt, $cap = '' ) => '<!-- wp:image {"id":' . $id . ',"sizeSlug":"large","linkDestination":"media"} --><figure class="wp-block-image size-large"><a href="/p' . $id . '.webp"><img src="/p' . $id . '.webp" alt="' . $alt . '" class="wp-image-' . $id . '"/></a>' . $cap . '</figure><!-- /wp:image -->';
		$gallery = '<!-- wp:gallery {"columns":3,"linkTo":"media"} --><figure class="wp-block-gallery has-nested-images columns-3">'
			. $img( 1598, 'катафалк' ) . $img( 717, '' ) . $img( 1594, 'x', '<figcaption class="wp-element-caption">Своя подпись</figcaption>' )
			. '</figure><!-- /wp:gallery -->';
		$page    = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'fotogalereia',
				'post_content' => $gallery,
			)
		);
		$dry = memorial_plugin_gallery_upgrade_1( true );
		$this->assertSame( 2, $dry['photos'] );
		$this->assertStringNotContainsString( 'figcaption class="wp-element-caption">Катафалк', get_post_field( 'post_content', $page ) );
		memorial_plugin_gallery_upgrade_1();
		$content = get_post_field( 'post_content', $page );
		$this->assertStringContainsString( 'Катафалк Ford Transit на 8 мест для провожающих. <a href="', $content );
		$this->assertStringContainsString( 'alt="Салон катафалка: места для провожающих"', $content );
		$this->assertStringContainsString( 'Своя подпись', $content );
		$this->assertStringNotContainsString( 'на 3 места', $content, 'a caption of the owner stays' );
		$this->assertStringContainsString( 'Фото здания', get_post_meta( $page, '_seopress_titles_desc', true ) );
		$this->assertArrayNotHasKey( 'photos', memorial_plugin_gallery_upgrade_1( true ), 'once more — nothing' );
	}

	public function test_price_table_pattern_has_no_digits() {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'memorial/price' );
		$this->assertStringContainsString( 'data-value="price"', $pattern['content'] );
		$this->assertStringNotContainsString( '1 000 ₽', $pattern['content'] );
	}
}
