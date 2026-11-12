<?php
/**
 * Backward compatibility of the old [mem_…] shortcodes (core/shortcodes-compat.php).
 *
 * @package memorial-plugin
 */

/**
 * Deprecated shortcodes, their implementations and the automatic conversion to blocks.
 */
class Test_Memorial_Plugin_Shortcodes_Compat extends WP_UnitTestCase {

	public function test_shortcode_is_deprecated_but_still_rendered() {
		$this->setExpectedDeprecated( '[mem_years]' );
		$this->assertSame( (string) memorial_plugin_years(), trim( wp_strip_all_tags( do_shortcode( '[mem_years]' ) ) ) );
	}

	public function test_blocks_use_the_implementation_without_a_notice() {
		$this->assertIsCallable( memorial_plugin_shortcode_impl( 'mem_cta' ) );
		$html = do_blocks( '<!-- wp:memorial-plugin/cta /-->' );
		$this->assertStringContainsString( 'wp-block-memorial-plugin-cta', $html );
	}

	public function test_generated_content_has_no_shortcodes() {
		$out = memorial_plugin_blocks_from_shortcodes( "<!-- wp:shortcode -->\n[mem_cta]\n<!-- /wp:shortcode -->" );
		$this->assertStringContainsString( '<!-- wp:memorial-plugin/cta', $out );
		$this->assertStringNotContainsString( '[mem_', $out );
	}

	public function test_content_is_converted_after_an_update_and_can_be_rolled_back() {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => "<!-- wp:shortcode -->\n[mem_cta]\n<!-- /wp:shortcode -->",
			)
		);
		delete_option( MEMORIAL_PLUGIN_SHORTCODES_MIGRATION );
		memorial_plugin_shortcodes_migration_schedule();
		memorial_plugin_shortcodes_migrate();

		$state = get_option( MEMORIAL_PLUGIN_SHORTCODES_MIGRATION );
		$this->assertTrue( $state['done'] );
		$this->assertContains( $id, $state['converted'] );
		$this->assertStringContainsString( '<!-- wp:memorial-plugin/cta', get_post( $id )->post_content );

		$this->assertSame( 'restored', memorial_plugin_convert_rollback( $id ) );
		$this->assertStringContainsString( '[mem_cta]', get_post( $id )->post_content );
	}
}
