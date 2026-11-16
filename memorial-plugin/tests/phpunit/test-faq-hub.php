<?php
/**
 * Page “Questions and answers”: sections, anchors, own sections, FAQPage; llms.txt.
 *
 * @package memorial-plugin
 */

/**
 * FAQ hub and llms.txt tests.
 */
class Test_Memorial_Plugin_Faq_Hub extends WP_UnitTestCase {

	/**
	 * Details block.
	 *
	 * @param string $q      Question.
	 * @param string $a      Answer paragraphs (HTML).
	 * @param string $anchor Anchor.
	 * @return string
	 */
	private function details( $q, $a, $anchor = '' ) {
		$attrs = $anchor ? ' {"anchor":"' . $anchor . '"}' : '';
		$id    = $anchor ? ' id="' . $anchor . '"' : '';
		$body  = '';
		foreach ( (array) $a as $p ) {
			$body .= "<!-- wp:paragraph -->\n<p>" . $p . "</p>\n<!-- /wp:paragraph -->\n";
		}
		return "<!-- wp:details$attrs -->\n<details class=\"wp-block-details\"$id><summary>$q</summary>$body</details>\n<!-- /wp:details -->\n";
	}

	/**
	 * Heading block H2.
	 *
	 * @param string $t      Text.
	 * @param string $anchor Anchor.
	 * @return string
	 */
	private function h2( $t, $anchor = '' ) {
		$attrs = $anchor ? ' {"anchor":"' . $anchor . '"}' : '';
		$id    = $anchor ? ' id="' . $anchor . '"' : '';
		return "<!-- wp:heading$attrs -->\n<h2 class=\"wp-block-heading\"$id>$t</h2>\n<!-- /wp:heading -->\n";
	}

	/**
	 * Pages: a service page with questions and the hub with an own section.
	 *
	 * @return array{0:int,1:int}
	 */
	private function pages() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$svc = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Кремация в Белгороде',
				'post_content' => $this->details( 'Сколько стоит кремация?', array( 'Первый абзац ответа.', 'Второй абзац с подробностями.' ), 'cena-kremacii' )
					. $this->details( 'Где прах?', array( 'Коротко.' ) ),
			)
		);
		$hub = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Вопрос–ответ',
				'post_content' => "<!-- wp:memorial-plugin/faq /-->\n" . $this->h2( 'Оплата', 'oplata' ) . $this->details( 'Можно ли картой?', array( 'Да, картой и переводом.' ) ) . "\n" . $this->details( 'Нужна ли предоплата?', array( 'Нет.' ) )
					. "<!-- wp:paragraph -->\n<p>Просто текст.</p>\n<!-- /wp:paragraph -->\n",
			)
		);
		memorial_plugin_faq_flush();
		return array( $svc, $hub );
	}

	public function test_sections_have_defaults_and_settings() {
		[ $svc, $hub ] = $this->pages();
		$s             = memorial_plugin_faq_sections( $hub );
		$keys          = array_column( $s, 'key' );
		$this->assertSame( array( 'p' . $svc, 'h:oplata' ), $keys );
		$this->assertSame( 'Кремация в Белгороде', str_replace( "\u{00A0}", ' ', $s[0]['label'] ) );
		$this->assertSame( 'oplata', $s[1]['anchor'] );
		$this->assertSame( 'cena-kremacii', $s[0]['qs'][0][2] );
		// Both questions under the heading are in the own section (the first section has the number 0).
		$this->assertCount( 2, $s[1]['qs'] );

		update_post_meta(
			$hub,
			'_mem_faq_sections',
			wp_slash( wp_json_encode(
				array(
					'order' => array( 'h:oplata', 'p' . $svc ),
					'items' => array(
						'p' . $svc => array(
							'label'  => 'Кремация',
							'anchor' => 'Кремация!',
							'more'   => 'Всё о кремации →',
						),
						'h:oplata' => array(
							'label' => 'Оплата',
							'title' => 'нельзя',
						),
					),
				)
			) )
		);
		$s = memorial_plugin_faq_sections( $hub );
		$this->assertSame( array( 'h:oplata', 'p' . $svc ), array_column( $s, 'key' ) );
		$this->assertSame( 'Кремация', $s[1]['label'] );
		$this->assertSame( 'Кремация', $s[1]['title'] );
		$this->assertSame( 'kremaciya', $s[1]['anchor'] );
		// The arrow is put by the site: the text is kept without it.
		$this->assertSame( 'Всё о кремации', $s[1]['more'] );
		$this->assertSame( 'Оплата', $s[0]['title'] );
	}

	public function test_hub_shows_short_answers_and_own_section_once() {
		[ $svc, $hub ] = $this->pages();
		$this->go_to( get_permalink( $hub ) );
		$GLOBALS['post'] = get_post( $hub ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the page being shown.
		setup_postdata( $GLOBALS['post'] );
		$html = apply_filters( 'the_content', get_post( $hub )->post_content );
		// The page section: the first paragraph and “Read more” to the question on its page.
		$this->assertStringContainsString( 'Первый абзац ответа.', $html );
		// The full answer is only in data-t (for the search), not on the screen.
		$this->assertStringNotContainsString( 'Второй абзац с подробностями.', preg_replace( '/\sdata-t="[^"]*"/', '', $html ) );
		$this->assertStringContainsString( get_permalink( $svc ) . '#cena-kremacii', $html );
		// The own section is in the block, not twice; other text of the page stays.
		$this->assertSame( 1, substr_count( $html, 'Можно ли картой?' ) );
		$this->assertSame( 1, substr_count( $html, 'Нужна ли предоплата?' ) );
		$this->assertStringContainsString( 'id="oplata"', $html );
		$this->assertStringContainsString( 'Просто текст.', $html );
		$this->assertStringNotContainsString( 'faq-' . $svc, $html );
	}

	public function test_faqpage_on_the_hub_only_for_own_questions() {
		[ $svc, $hub ] = $this->pages();
		$this->go_to( get_permalink( $hub ) );
		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();
		$this->assertSame( 1, substr_count( $head, '"FAQPage"' ) );
		$this->assertStringContainsString( 'Можно ли картой?', $head );
		$this->assertStringNotContainsString( 'Сколько стоит кремация?', $head );

		$this->go_to( get_permalink( $svc ) );
		ob_start();
		do_action( 'wp_head' );
		$head = ob_get_clean();
		$this->assertStringContainsString( 'Сколько стоит кремация?', $head );
	}

	public function test_link_text_gets_one_arrow() {
		[ $svc, $hub ] = $this->pages();
		update_post_meta( $hub, '_mem_faq_sections', wp_slash( wp_json_encode( memorial_plugin_faq_settings_clean( array( 'items' => array( 'p' . $svc => array( 'more' => 'Всё о кремации →' ) ) ) ) ) ) );
		$this->go_to( get_permalink( $hub ) );
		$GLOBALS['post'] = get_post( $hub ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the page being shown.
		setup_postdata( $GLOBALS['post'] );
		$html = apply_filters( 'the_content', get_post( $hub )->post_content );
		$this->assertStringContainsString( 'Всё о кремации <span aria-hidden="true">→</span>', $html );
		$this->assertSame( 'Всё о кремации', memorial_plugin_faq_no_arrow( 'Всё о кремации  → ' ) );
		// The closing quote of the default text stays.
		$this->assertSame( 'На странице «Кремация»', memorial_plugin_faq_no_arrow( 'На странице «Кремация» →' ) );
		delete_post_meta( $hub, '_mem_faq_sections' );
		$this->assertMatchesRegularExpression( '/[»”]$/u', memorial_plugin_faq_sections( $hub )[0]['more'] );
	}

	public function test_settings_are_cleaned() {
		$v = memorial_plugin_faq_settings_clean( '{"order":["p12","bad key","h:ok"],"items":{"p12":{"label":"<b>Кнопка</b>","anchor":"Мой якорь"},"x":{"label":"нет"}}}' );
		$this->assertSame( array( 'p12', 'h:ok' ), $v['order'] );
		$this->assertSame( 'Кнопка', $v['items']['p12']['label'] );
		$this->assertArrayNotHasKey( 'x', $v['items'] );
	}

	public function test_search_items_point_to_questions() {
		[ $svc, $hub ] = $this->pages();
		$urls          = array_column( memorial_plugin_faq_search_items(), 'u' );
		$this->assertContains( get_permalink( $svc ) . '#cena-kremacii', $urls );
		$this->assertContains( get_permalink( $hub ) . '#' . memorial_plugin_q_anchor( 'Можно ли картой?' ), $urls );
	}

	public function test_llms_txt_is_markdown_with_h1_and_sections() {
		[ , $hub ] = $this->pages();
		$text      = memorial_plugin_llms_build();
		$this->assertMatchesRegularExpression( '/^# \S/', $text );
		$this->assertStringContainsString( get_permalink( $hub ) . '#oplata', $text );
		$this->assertStringContainsString( '## ', $text );
	}
}
