<?php
/**
 * Payment, delivery and guarantee cards (prices/terms.php), reviews of a page (reviews/rewio.php),
 * robots.txt (seo/robots.php) and empty SEO prices.
 *
 * @package memorial-plugin
 */

/**
 * Terms and reviews tests.
 */
class Test_Memorial_Plugin_Terms_Reviews extends WP_UnitTestCase {

	public function test_payment_block_lists_the_ways_shown() {
		$html = memorial_plugin_payment_render();
		$this->assertStringContainsString( 'mterms--pay', $html );
		$this->assertStringContainsString( 'Наличными', $html );
		$this->assertStringContainsString( 'своя, без банка', $html );
		update_option(
			'mem_prices',
			array(
				'pay' => array(
					'items' => array(
						array(
							'name' => 'Картой',
							'show' => 1,
						),
						array(
							'name' => 'Скрытый',
							'show' => 0,
						),
					),
				),
			)
		);
		$html = memorial_plugin_payment_render();
		$this->assertStringContainsString( 'Картой', $html );
		$this->assertStringNotContainsString( 'Скрытый', $html );
		update_option( 'mem_prices', array( 'pay' => array( 'items' => array() ) ) );
		$this->assertSame( '', memorial_plugin_payment_render(), 'No ways of payment — no block.' );
	}

	public function test_review_words_any_part_of_a_word() {
		$words = memorial_plugin_rewio_words( 'Памятн, плиТк ;  ;ёлк' );
		$this->assertSame( array( 'памятн', 'плитк', 'елк' ), $words );
		$this->assertTrue( memorial_plugin_rewio_has_words( 'Заказали памятником довольны', $words ) );
		$this->assertTrue( memorial_plugin_rewio_has_words( 'Ёлка у входа', $words ) );
		$this->assertFalse( memorial_plugin_rewio_has_words( 'Спасибо за похороны', $words ) );
	}

	public function test_reviews_of_a_page_by_words_hand_and_guest_book() {
		update_option(
			'mem_rewio',
			array(
				'api'  => array( 'on' => 1 ),
				'view' => array(
					'count' => 6,
					'sort'  => 'new',
				),
			)
		);
		update_option(
			'mem_rewio_cache',
			array(
				'sources' => array(
					1 => array(
						'name' => 'Яндекс Карты',
						'slug' => 'yandex',
					),
				),
				'groups'  => array(
					array(
						'name'    => '',
						'summary' => null,
						'reviews' => array(
							$this->review( 11, 'Памятник поставили быстро' ),
							$this->review( 12, 'Кремация прошла спокойно' ),
						),
					),
				),
			)
		);
		update_option( 'mem_rewio_status', array( 'time' => time() ) );
		$book = self::factory()->post->create(
			array(
				'post_type'    => 'mem_bookrev',
				'post_title'   => 'Анна',
				'post_content' => 'Спасибо за памятник и плитку',
			)
		);

		$all = memorial_plugin_rewio_html( array() );
		$this->assertStringContainsString( 'Памятник поставили', $all );
		$this->assertStringContainsString( 'Кремация прошла', $all );
		$this->assertStringNotContainsString( 'Спасибо за памятник', $all, 'Guest book only when asked.' );

		$words = memorial_plugin_rewio_html( array( 'slova' => 'памятн' ) );
		$this->assertStringContainsString( 'Памятник поставили', $words );
		$this->assertStringNotContainsString( 'Кремация прошла', $words );

		$with_book = memorial_plugin_rewio_html(
			array(
				'slova' => 'памятн',
				'kniga' => 'да',
			)
		);
		$this->assertStringContainsString( 'Спасибо за памятник', $with_book );

		$hand = memorial_plugin_rewio_html( array( 'vybor' => 'b' . $book . ',12' ) );
		$this->assertStringContainsString( 'Кремация прошла', $hand );
		$this->assertStringNotContainsString( 'Памятник поставили', $hand );
		$this->assertStringNotContainsString( 'Спасибо за памятник', $hand, 'A chosen guest book review needs the guest book switched on.' );
		$hand_book = memorial_plugin_rewio_html(
			array(
				'vybor' => 'b' . $book . ',12',
				'kniga' => 'да',
			)
		);
		$this->assertLessThan( strpos( $hand_book, 'Кремация прошла' ), strpos( $hand_book, 'Спасибо за памятник' ), 'Chosen by hand — in the chosen order.' );

		$this->assertSame( '', memorial_plugin_rewio_html( array( 'slova' => 'оградк' ) ), 'No review fits — no block.' );

		$ids = array_column( memorial_plugin_rewio_pick_items(), 'id' );
		$this->assertContains( '11', $ids );
		$this->assertContains( 'b' . $book, $ids );
	}

	public function test_robots_txt_gets_clean_param_only_when_public() {
		$this->assertStringContainsString( 'Clean-param: utm_source&', apply_filters( 'robots_txt', "User-agent: *\n", true ) );
		$this->assertStringContainsString( '&sort', apply_filters( 'robots_txt', "User-agent: *\n", true ) );
		$this->assertStringNotContainsString( 'Clean-param', apply_filters( 'robots_txt', "User-agent: *\nDisallow: /\n", false ) );
	}

	public function test_empty_price_leaves_no_dangling_dash() {
		$this->assertSame( 'Гробы в Белгороде | Мемориал', memorial_plugin_seo_unknown_vars( 'Гробы в Белгороде —  | Мемориал' ) );
		$this->assertSame( 'Оградки — от 1 000 ₽ | Мемориал', memorial_plugin_seo_unknown_vars( 'Оградки — от 1 000 ₽ | Мемориал' ) );
	}

	public function test_placement_dry_run_then_real_run() {
		$venki = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'venki',
				'post_content' => "<!-- wp:paragraph -->\n<p>Венки</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Частые вопросы</h2>\n<!-- /wp:heading -->",
			)
		);
		$ceny  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'ceny',
				'post_content' => '<!-- wp:memorial-plugin/payment /-->',
			)
		);
		$parent = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'poleznoe',
			)
		);
		$faq    = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'vopros-otvet',
				'post_parent'  => $parent,
				'post_content' => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Оплата</h2>\n<!-- /wp:heading -->\n\n<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>Можно ли рассрочку?</summary><!-- wp:paragraph -->\n<p>Да.</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Документы</h2>\n<!-- /wp:heading -->",
			)
		);
		$before = get_post_field( 'post_content', $venki );

		$dry = memorial_plugin_terms_upgrade_1( true );
		$this->assertArrayHasKey( $venki, $dry['posts'] );
		$this->assertArrayNotHasKey( $ceny, $dry['posts'] ?? array(), 'The block is already there.' );
		$this->assertSame( $before, get_post_field( 'post_content', $venki ), 'Dry run writes nothing.' );

		$log  = memorial_plugin_terms_upgrade_1();
		$page = get_post_field( 'post_content', $venki );
		$this->assertSame( $before, $log['posts'][ $venki ] );
		$this->assertStringContainsString( 'mterms--delivery', $page );
		$this->assertStringContainsString( '<!-- wp:memorial-plugin/payment /-->', $page );
		$this->assertStringContainsString( '"words":"венок, венк, гроб, крест, корзин"', $page );
		$this->assertLessThan( strpos( $page, 'Частые вопросы' ), strpos( $page, 'mterms--delivery' ), 'Before the frequent questions.' );

		$questions = get_post_field( 'post_content', $faq );
		$this->assertStringNotContainsString( 'Как можно оплатить?', $questions, 'A similar question (instalments) is already there.' );
		$this->assertStringContainsString( 'Есть ли доставка и выезд?', $questions );
		$this->assertStringContainsString( 'Какая гарантия?', $questions );
		$this->assertLessThan( strpos( $questions, 'Документы' ), strpos( $questions, 'Какая гарантия?' ), 'In the “Payment” section.' );

		// Run again: nothing more.
		$again = memorial_plugin_terms_upgrade_1( true );
		$this->assertArrayNotHasKey( $venki, $again['posts'] ?? array() );
	}

	/**
	 * A review of the cache.
	 *
	 * @param int    $id   Id.
	 * @param string $text Text.
	 * @return array
	 */
	private function review( $id, $text ) {
		return array(
			'id'     => $id,
			'author' => 'Автор ' . $id,
			'rating' => 5,
			'date'   => '2026-10-' . ( $id - 1 ),
			'text'   => $text,
			'reply'  => '',
			'imgs'   => array(),
			'src'    => 1,
			'slug'   => 'yandex',
			'url'    => '',
		);
	}
}
