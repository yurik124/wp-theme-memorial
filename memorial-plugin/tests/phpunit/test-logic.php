<?php
/**
 * Sanitizing and merging of the plugin’s data.
 *
 * @package memorial-plugin
 */

/**
 * Pure logic tests.
 */
class Test_Memorial_Plugin_Logic extends WP_UnitTestCase {

	public function test_work_rows_drop_empty_rows_and_keep_numbers() {
		$this->assertSame(
			array( array( 'Памятник', 12000 ), array( 'Плитка', 5 ) ),
			memorial_plugin_work_rows(
				array(
					array( 'Памятник', '12 000 ₽' ),
					array( '', '' ),
					array( '<b>Плитка</b>', 5 ),
				)
			)
		);
	}

	public function test_work_year_range() {
		$this->assertSame( 0, memorial_plugin_work_year( 1980 ) );
		$this->assertSame( 2020, memorial_plugin_work_year( '2020' ) );
		$this->assertSame( 0, memorial_plugin_work_year( (int) gmdate( 'Y' ) + 5 ) );
	}

	public function test_settings_merge_groups_by_key_and_replaces_lists() {
		$this->assertSame(
			array(
				'phones' => array( '3' ),
				'night'  => array(
					'on'   => 1,
					'text' => 'b',
				),
			),
			memorial_plugin_settings_merge(
				array(
					'phones' => array( '1', '2' ),
					'night'  => array(
						'on'   => 1,
						'text' => 'a',
					),
				),
				array(
					'phones' => array( '3' ),
					'night'  => array( 'text' => 'b' ),
				)
			)
		);
	}

	public function test_page_products_value_accepts_only_known_categories() {
		$this->assertSame( 'all', memorial_plugin_page_products_value( 'all' ) );
		$this->assertSame( '', memorial_plugin_page_products_value( '999999' ) );
		$cat = self::factory()->term->create( array( 'taxonomy' => 'mem_cat' ) );
		$this->assertSame( (string) $cat, memorial_plugin_page_products_value( (string) $cat ) );
	}

	public function test_theme_bridge_maps_missing_presets() {
		add_filter(
			'wp_theme_json_data_theme',
			static function ( $data ) {
				return $data->update_with(
					array(
						'version'  => 3,
						'settings' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'accent-1',
										'color' => '#123456',
										'name'  => 'Accent 1',
									),
									array(
										'slug'  => 'foreground',
										'color' => '#111111',
										'name'  => 'Foreground',
									),
								),
							),
						),
					)
				);
			}
		);
		wp_clean_theme_json_cache();
		$css = memorial_plugin_theme_bridge_css();
		$this->assertStringContainsString( '--wp--preset--color--contrast:var(--wp--preset--color--foreground)', $css );
	}
	public function test_money_keeps_roubles() {
		$this->assertSame( 2500, memorial_plugin_money( '2 500' ) );
		$this->assertSame( 2500, memorial_plugin_money( 'от 2500 ₽' ) );
		$this->assertSame( 2501, memorial_plugin_money( '2500,50' ) );
		$this->assertSame( 2501, memorial_plugin_money( 2500.5 ) );
		$this->assertSame( 0, memorial_plugin_money( '' ) );
	}

	public function test_prices_change_only_as_asked() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$a = self::factory()->post->create( array( 'post_type' => 'mem_product' ) );
		$b = self::factory()->post->create( array( 'post_type' => 'mem_product' ) );
		update_post_meta( $a, '_mem_price', 1000 );
		update_post_meta( $b, '_mem_price', 0 );
		$bulk = function ( $params ) use ( $a, $b ) {
			$r = new WP_REST_Request( 'POST', '/memorial-plugin/v1/catalog/products/bulk' );
			foreach ( array_merge( array( 'ids' => array( $a, $b ) ), $params ) as $k => $v ) {
				$r->set_param( $k, $v );
			}
			return rest_get_server()->dispatch( $r )->get_status();
		};
		// One price: exactly as typed, not rounded to tens.
		$this->assertSame( 200, $bulk( array( 'op' => 'price', 'mode' => 'set', 'value' => 1799, 'round' => 10 ) ) );
		$this->assertSame( 1799, (int) get_post_meta( $a, '_mem_price', true ) );
		$this->assertSame( 1799, (int) get_post_meta( $b, '_mem_price', true ) );
		// Percent: computed and rounded; another product is not touched.
		$c = self::factory()->post->create( array( 'post_type' => 'mem_product' ) );
		update_post_meta( $c, '_mem_price', 3000 );
		$this->assertSame( 200, $bulk( array( 'op' => 'price', 'mode' => 'pct', 'value' => 10, 'round' => 10 ) ) );
		$this->assertSame( 1980, (int) get_post_meta( $a, '_mem_price', true ) );
		$this->assertSame( 3000, (int) get_post_meta( $c, '_mem_price', true ) );
		// The same request twice gives the same result as twice by hand, nothing else.
		$this->assertSame( 200, $bulk( array( 'op' => 'price', 'mode' => 'add', 'value' => 20, 'round' => 1 ) ) );
		$this->assertSame( 2000, (int) get_post_meta( $a, '_mem_price', true ) );
		// Saving one field of a product does not touch its price.
		$r = new WP_REST_Request( 'PUT', '/memorial-plugin/v1/catalog/products/' . $a );
		$r->set_param( 'values', array( 'sub' => 'высота 90 см' ) );
		rest_get_server()->dispatch( $r );
		$this->assertSame( 2000, (int) get_post_meta( $a, '_mem_price', true ) );
		$r->set_param( 'values', array( 'price' => '2500,50' ) );
		rest_get_server()->dispatch( $r );
		$this->assertSame( 2501, (int) get_post_meta( $a, '_mem_price', true ) );
	}

	public function test_cremation_becomes_a_package_once() {
		delete_option( 'memorial_plugin_prices_pkg_merge' );
		delete_option( 'memorial_plugin_cremation_pkg' );
		update_option(
			'mem_prices',
			array(
				'packages'  => array(
					'items' => array(
						array(
							'name' => 'Эконом',
							'rows' => array(
								array(
									'name'  => 'Гроб',
									'price' => 7000,
								),
							),
						),
					),
				),
				'cremation' => array(
					'rows'       => array(
						array(
							'name'  => 'Кремирование',
							'price' => 21500,
							'key'   => 1,
						),
						array(
							'name'  => 'Урна',
							'price' => 4500,
						),
					),
					'total'      => 'Итого с доставкой',
					'card_title' => '',
				),
			)
		);
		memorial_plugin_prices_cremation_to_packages();
		memorial_plugin_prices_cremation_to_packages(); // the second call changes nothing.
		$saved = get_option( 'mem_prices' );
		$items = $saved['packages']['items'];
		$this->assertCount( 2, $items );
		$this->assertSame( 1, $items[0]['show'], 'old packages are shown' );
		$this->assertSame( 'Итого с доставкой', $items[1]['total'] );
		$this->assertSame( (string) get_option( 'memorial_plugin_cremation_pkg' ), $items[1]['_id'] );
		$this->assertArrayHasKey( 'cremation', $saved, 'the old group stays for a rollback' );
		$this->assertSame( 26000, memorial_plugin_cremation_total() );
		$this->assertStringContainsString( 'Итого с доставкой', do_shortcode( '[mem_cremation]' ) );
		$this->assertCount( 2, memorial_plugin_packages_shown() );
	}

	public function test_hidden_package_is_not_on_the_site() {
		update_option(
			'mem_prices',
			array(
				'packages' => array(
					'cols'  => '3',
					'items' => array(
						array(
							'name' => 'Виден',
							'show' => 1,
							'rows' => array(
								array(
									'name'  => 'Гроб',
									'price' => 100,
								),
							),
						),
						array(
							'name' => 'Скрыт',
							'show' => 0,
							'rows' => array(
								array(
									'name'  => 'Крест',
									'price' => 200,
								),
							),
						),
					),
				),
			)
		);
		$html = memorial_plugin_packages_markup();
		$this->assertStringContainsString( 'Виден', $html );
		$this->assertStringNotContainsString( 'Скрыт', $html );
		$this->assertStringContainsString( '--pkg-cols:3', $html );
		$this->assertStringContainsString( 'class="pkg__rows"', $html );
	}

	public function test_link_field_keeps_a_page_or_an_address() {
		$schema = array( 'l' => array( 'type' => 'link' ) );
		$this->assertSame( '12', memorial_plugin_sanitize( $schema, array( 'l' => '12' ) )['l'] );
		$this->assertSame( 'https://example.ru/a', memorial_plugin_sanitize( $schema, array( 'l' => 'https://example.ru/a' ) )['l'] );
		$this->assertSame( '', memorial_plugin_sanitize( $schema, array( 'l' => 'javascript:alert(1)' ) )['l'] );
	}
}
