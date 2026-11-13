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
}
