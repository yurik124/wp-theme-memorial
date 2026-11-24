<?php
/**
 * Blocks of step 2 (prices/price-blocks.php, prices/terms.php): guarantees without the instalment plan for a
 * funeral, the fixed words of the benefits, rows without a price are hidden, “Payment” by the kind of work,
 * the date of a price, the one-time move of 24.11.2026.
 *
 * @package memorial-plugin
 */

/**
 * Price blocks tests.
 */
class Test_Memorial_Plugin_Price_Blocks extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( 'mem_prices' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Prices with own rows of «Separate prices».
	 *
	 * @param array $rows Rows.
	 */
	private function prices( $rows ) {
		$p                   = memorial_plugin_prices_defaults();
		$p['other']['items'] = $rows;
		update_option( 'mem_prices', $p );
	}

	public function test_guarantees_funeral_never_shows_the_instalment_plan() {
		$funeral = memorial_plugin_guarantees_render( array( 'set' => 'funeral' ) );
		$this->assertStringContainsString( 'Цена фиксируется в договоре', $funeral );
		$this->assertStringNotContainsString( 'рассрочк', mb_strtolower( $funeral ) );
		$this->assertStringNotContainsString( 'Храним памятник', $funeral );
		$monuments = memorial_plugin_guarantees_render( array( 'set' => 'monuments' ) );
		$this->assertStringContainsString( 'рассрочка', mb_strtolower( $monuments ) );
		// Even a point “for everything” about an instalment plan is hidden for a funeral.
		$p                                 = memorial_plugin_prices_defaults();
		$p['guarantees']['items'][6]['for'] = '';
		update_option( 'mem_prices', $p );
		$this->assertStringNotContainsString( 'рассрочк', mb_strtolower( memorial_plugin_guarantees_render( array( 'set' => 'funeral' ) ) ) );
		$this->assertSame( 2, substr_count( memorial_plugin_guarantees_render( array( 'count' => 2 ) ), 'mguar__item' ) );
		$this->assertStringNotContainsString( '<h2', memorial_plugin_guarantees_render( array( 'heading' => false ) ) );
	}

	public function test_benefits_words_are_fixed() {
		$this->prices(
			array(
				array(
					'name'  => 'Пособие',
					'what'  => 'custom',
					'price' => '9 678,63',
					'code'  => 'posobie',
				),
				array(
					'name'  => 'СВО',
					'what'  => 'custom',
					'price' => '150 000',
					'code'  => 'vyplata_svo',
				),
			)
		);
		$html = memorial_plugin_benefits_render();
		$this->assertStringContainsString( '150', $html );
		$this->assertStringContainsString( '9', $html );
		$this->assertSame( 2, substr_count( $html, 'mbenefit__item' ) );
		$this->assertStringNotContainsString( 'за вас', $html );
		$this->assertSame( 1, substr_count( memorial_plugin_benefits_render( array( 'show' => 'svo' ) ), 'mbenefit__item' ) );
	}

	public function test_price_list_hides_rows_without_a_price() {
		$this->prices(
			array(
				array(
					'name'  => 'Катафалк',
					'what'  => 'custom',
					'price' => '6000',
					'code'  => 'katafalk',
				),
				array(
					'name'  => 'Автобус',
					'what'  => 'custom',
					'price' => '',
					'code'  => 'avtobus',
				),
			)
		);
		$html = memorial_plugin_price_list_render(
			array(
				'rows' => array(
					array(
						'code' => 'katafalk',
						'unit' => 'trip',
					),
					array( 'code' => 'avtobus' ),
					array( 'code' => 'net_takogo' ),
				),
				'note' => 'Итог фиксируется в договоре',
			)
		);
		$this->assertSame( 1, substr_count( $html, '<tr><td>' ) );
		$this->assertStringContainsString( 'Катафалк', $html );
		$this->assertStringContainsString( 'mprice-list__unit', $html );
		$this->assertStringContainsString( 'Итог фиксируется в договоре', $html );
		// A ready set is used when there are no rows of its own.
		$this->assertStringContainsString( 'Катафалк', memorial_plugin_price_list_render( array( 'preset' => 'funeral' ) ) );
	}

	public function test_payment_by_kind_of_work() {
		$all       = memorial_plugin_payment_render();
		$funeral   = memorial_plugin_payment_render( array( 'context' => 'funeral' ) );
		$monuments = memorial_plugin_payment_render( array( 'context' => 'monuments' ) );
		$this->assertStringContainsString( 'Рассрочка', $all );
		$this->assertStringNotContainsString( 'Рассрочка', $funeral, 'no instalment plan for a funeral' );
		$this->assertStringContainsString( '50%', $funeral );
		$this->assertStringContainsString( 'Рассрочка', $monuments );
		$this->assertStringContainsString( 'Наличными', $funeral );
	}

	public function test_date_is_put_when_the_price_changes() {
		$this->prices(
			array(
				array(
					'_id'     => 'abcdef1234',
					'name'    => 'Лента',
					'what'    => 'custom',
					'price'   => '300',
					'code'    => 'lenta',
					'updated' => '01.01.2026',
				),
			)
		);
		$p = get_option( 'mem_prices' );
		// The same price — the date stays.
		update_option( 'mem_prices', $p );
		$this->assertSame( '01.01.2026', get_option( 'mem_prices' )['other']['items'][0]['updated'] );
		// A new price — today.
		$p['other']['items'][0]['price'] = '350';
		update_option( 'mem_prices', $p );
		$this->assertSame( wp_date( 'd.m.Y' ), get_option( 'mem_prices' )['other']['items'][0]['updated'] );
		// A new price with a date typed by hand — the typed date.
		$p                                 = get_option( 'mem_prices' );
		$p['other']['items'][0]['price']   = '400';
		$p['other']['items'][0]['updated'] = '15.11.2026';
		update_option( 'mem_prices', $p );
		$this->assertSame( '15.11.2026', get_option( 'mem_prices' )['other']['items'][0]['updated'] );
	}

	public function test_one_time_move_codes_and_payment_on_pages() {
		$this->prices(
			array(
				array(
					'name'  => 'Лента',
					'what'  => 'custom',
					'price' => '300',
					'code'  => 'lenta',
				),
			)
		);
		$uslugi = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'ritualnye-uslugi',
				'post_content' => "<!-- wp:paragraph --><p>Текст</p><!-- /wp:paragraph -->\n\n<!-- wp:memorial-plugin/payment /-->",
			)
		);
		$ogradki = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'ogradki',
				'post_content' => '<!-- wp:memorial-plugin/payment {"className":"x"} /-->',
			)
		);
		$dry = memorial_plugin_prices_upgrade_2( true );
		$this->assertContains( 'vyplata_svo', $dry['codes'] );
		$this->assertArrayHasKey( $uslugi, $dry['posts'] );
		$this->assertStringNotContainsString( 'context', get_post_field( 'post_content', $uslugi ), 'a dry run writes nothing' );

		$log = memorial_plugin_prices_upgrade_2();
		$this->assertStringContainsString( '"context":"funeral"', get_post_field( 'post_content', $uslugi ) );
		$this->assertStringContainsString( '"context":"monuments"', get_post_field( 'post_content', $ogradki ) );
		$this->assertStringContainsString( '"className":"x"', get_post_field( 'post_content', $ogradki ) );
		$codes = array_column( get_option( 'mem_prices' )['other']['items'], 'code' );
		$this->assertContains( 'vyplata_svo', $codes );
		$this->assertContains( 'katafalk', $codes );
		$this->assertContains( 'lenta', $codes );
		$this->assertSame( '150 000', get_option( 'mem_prices' )['other']['items'][1]['price'] );
		$this->assertArrayHasKey( 'prices', $log, 'the old option is in the log' );
		// Once more — nothing to do.
		$again = memorial_plugin_prices_upgrade_2( true );
		$this->assertEmpty( $again['codes'] ?? array() );
		$this->assertEmpty( $again['posts'] ?? array() );
	}
}
