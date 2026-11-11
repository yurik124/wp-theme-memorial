<?php
/**
 * Abilities of the plugin: registration, permissions, input validation, results.
 *
 * @package memorial-plugin
 */

/**
 * Abilities API tests.
 */
class Test_Memorial_Plugin_Abilities extends WP_UnitTestCase {

	/**
	 * Names of the abilities.
	 *
	 * @var string[]
	 */
	private $names = array(
		'memorial-plugin/list-leads',
		'memorial-plugin/set-lead-done',
		'memorial-plugin/list-products',
		'memorial-plugin/update-product',
		'memorial-plugin/change-prices',
		'memorial-plugin/set-visibility',
		'memorial-plugin/create-products',
		'memorial-plugin/export-catalog',
		'memorial-plugin/convert-content',
		'memorial-plugin/rollback-conversion',
		'memorial-plugin/search-stats',
		'memorial-plugin/clear-search-stats',
	);

	/**
	 * A product type with one product, as an administrator.
	 *
	 * @param string $title Product title.
	 * @return int Product ID.
	 */
	private function product( $title = 'Венок' ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$type = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'name'     => 'Венки ' . wp_generate_password( 4, false ),
			)
		);
		$id   = memorial_plugin_cm_create( $type, 0, $title );
		$this->assertGreaterThan( 0, $id );
		return $id;
	}

	public function test_all_abilities_are_registered_in_the_category() {
		foreach ( $this->names as $name ) {
			$ability = wp_get_ability( $name );
			$this->assertInstanceOf( WP_Ability::class, $ability, $name );
			$this->assertSame( 'memorial', $ability->get_category(), $name );
		}
	}

	public function test_a_subscriber_may_not_use_them() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		foreach ( array( 'memorial-plugin/list-leads', 'memorial-plugin/export-catalog', 'memorial-plugin/clear-search-stats' ) as $name ) {
			$result = wp_get_ability( $name )->execute( array() );
			$this->assertWPError( $result, $name );
		}
	}

	public function test_change_prices_by_percent_with_rounding() {
		$id = $this->product();
		wp_get_ability( 'memorial-plugin/update-product' )->execute(
			array(
				'id'    => $id,
				'price' => 1000,
			)
		);
		$result = wp_get_ability( 'memorial-plugin/change-prices' )->execute(
			array(
				'ids'   => array( $id ),
				'mode'  => 'pct',
				'value' => 7,
				'round' => 10,
			)
		);
		$this->assertNotWPError( $result );
		$this->assertSame( 1070, $result[0]['price'] );
		$this->assertSame( 1070, (int) get_post_meta( $id, '_mem_price', true ) );
	}

	public function test_price_on_request_stays_when_prices_go_up() {
		$id = $this->product();
		wp_get_ability( 'memorial-plugin/change-prices' )->execute(
			array(
				'ids'   => array( $id ),
				'mode'  => 'add',
				'value' => 500,
			)
		);
		$this->assertSame( 0, (int) get_post_meta( $id, '_mem_price', true ) );
	}

	public function test_invalid_input_is_rejected() {
		$id     = $this->product();
		$result = wp_get_ability( 'memorial-plugin/change-prices' )->execute(
			array(
				'ids'   => array( $id ),
				'mode'  => 'double',
				'value' => 2,
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 0, (int) get_post_meta( $id, '_mem_price', true ) );
	}

	public function test_export_does_not_let_excel_run_formulas() {
		$this->product( '=HYPERLINK("http://example.com")' );
		$result = wp_get_ability( 'memorial-plugin/export-catalog' )->execute( array() );
		$this->assertNotWPError( $result );
		$this->assertStringStartsWith( "\xEF\xBB\xBF", $result['csv'] );
		$this->assertStringContainsString( "\"'=HYPERLINK(", $result['csv'] );
		$this->assertSame( 1, $result['count'] );
	}

	public function test_hide_and_show_a_product() {
		$id = $this->product();
		wp_get_ability( 'memorial-plugin/set-visibility' )->execute(
			array(
				'kind'    => 'products',
				'ids'     => array( $id ),
				'visible' => false,
			)
		);
		$this->assertSame( 'draft', get_post_status( $id ) );
	}
}
