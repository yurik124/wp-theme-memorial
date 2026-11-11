<?php
/**
 * Removing the plugin.
 *
 * @package memorial-plugin
 */

/**
 * uninstall.php keeps the content by default and deletes everything only when asked.
 */
class Test_Memorial_Plugin_Uninstall extends WP_UnitTestCase {

	public function test_default_removes_only_temporary_data_and_full_mode_removes_content() {
		$product = self::factory()->post->create( array( 'post_type' => 'mem_product' ) );
		update_post_meta( $product, '_mem_price', 1500 );
		set_transient( 'mem_places_report', 'x', HOUR_IN_SECONDS );
		update_option( 'mem_contacts', array( 'phones' => array() ) );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'memorial_plugin_rewio_cron' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'memorial-plugin/memorial-plugin.php' );
		}
		require MEMORIAL_PLUGIN_DIR . '/uninstall.php';
		wp_cache_flush();

		$this->assertFalse( get_transient( 'mem_places_report' ) );
		$this->assertFalse( wp_next_scheduled( 'memorial_plugin_rewio_cron' ) );
		$this->assertNotNull( get_post( $product ), 'products stay by default' );
		$this->assertNotFalse( get_option( 'mem_contacts' ), 'settings stay by default' );

		memorial_plugin_uninstall_site( true );
		$this->assertNull( get_post( $product ) );
		$this->assertFalse( get_option( 'mem_contacts' ) );
		$this->assertSame( '', get_post_meta( $product, '_mem_price', true ) );
	}
}
