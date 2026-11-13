<?php
/**
 * REST routes of the plugin: every route checks the user’s rights.
 *
 * @package memorial-plugin
 */

/**
 * REST permission tests.
 */
class Test_Memorial_Plugin_Rest extends WP_UnitTestCase {

	/**
	 * Status of a request as a user with the role (or a visitor).
	 *
	 * @param string      $method Method.
	 * @param string      $route  Route.
	 * @param string|null $role   Role or null for a visitor.
	 * @param array       $params Parameters.
	 * @return int
	 */
	private function status( $method, $route, $role, $params = array() ) {
		wp_set_current_user( $role ? self::factory()->user->create( array( 'role' => $role ) ) : 0 );
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return rest_get_server()->dispatch( $request )->get_status();
	}

	public function test_settings_only_for_administrators() {
		$this->assertSame( 401, $this->status( 'GET', '/memorial-plugin/v1/settings/mem_contacts', null ) );
		$this->assertSame( 403, $this->status( 'GET', '/memorial-plugin/v1/settings/mem_contacts', 'editor' ) );
		$this->assertSame( 200, $this->status( 'GET', '/memorial-plugin/v1/settings/mem_contacts', 'administrator' ) );
	}

	public function test_editors_get_prices_but_not_contacts() {
		$this->assertSame( 200, $this->status( 'GET', '/memorial-plugin/v1/settings/mem_prices', 'editor' ) );
		$this->assertSame( 403, $this->status( 'GET', '/memorial-plugin/v1/settings/mem_modules_page', 'editor' ) );
	}

	public function test_leads_are_not_for_subscribers() {
		$this->assertSame( 401, $this->status( 'GET', '/memorial-plugin/v1/collections/leads', null ) );
		$this->assertSame( 403, $this->status( 'GET', '/memorial-plugin/v1/collections/leads', 'subscriber' ) );
	}

	public function test_catalog_changes_need_a_login() {
		$this->assertSame(
			401,
			$this->status(
				'POST',
				'/memorial-plugin/v1/catalog/products/bulk',
				null,
				array(
					'ids' => array( 1 ),
					'op'  => 'trash',
				)
			)
		);
		$this->assertSame( 401, $this->status( 'POST', '/memorial-plugin/v1/catalog/types', null, array( 'name' => 'X' ) ) );
		$this->assertSame( 403, $this->status( 'POST', '/memorial-plugin/v1/catalog/types', 'author', array( 'name' => 'X' ) ) );
	}

	public function test_save_answers_with_the_saved_value() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		// The value is read (and remembered) before the save — as the screen of “Prices” does.
		memorial_plugin_prices( 'packages' );
		$request = new WP_REST_Request( 'POST', '/memorial-plugin/v1/settings/mem_prices' );
		$request->set_param(
			'value',
			array(
				'packages' => array(
					'items' => array(
						array(
							'name' => 'Новый пакет',
							'show' => 1,
							'rows' => array(
								array(
									'name'  => 'Гроб',
									'price' => 5000,
								),
							),
						),
					),
				),
			)
		);
		$data  = rest_get_server()->dispatch( $request )->get_data();
		$names = array_column( $data['value']['packages']['items'], 'name' );
		$this->assertContains( 'Новый пакет', $names, 'a new package stays on the screen after saving' );
	}

	public function test_unknown_settings_page() {
		$this->assertSame( 404, $this->status( 'GET', '/memorial-plugin/v1/settings/nope', 'administrator' ) );
	}
}
