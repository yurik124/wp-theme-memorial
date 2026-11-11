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
}
