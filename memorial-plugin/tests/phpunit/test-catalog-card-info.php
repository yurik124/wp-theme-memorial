<?php
/**
 * Описание и характеристики товара — в HTML карточки скрытым блоком, без <template> и без повторов.
 *
 * @package memorial-plugin
 */

/**
 * Card info.
 */
class Test_Memorial_Plugin_Card_Info extends WP_UnitTestCase {

	/**
	 * HTML of a card of a product without its own page.
	 *
	 * @param string $view “card” or “photo”.
	 * @return string
	 */
	private function card( $view ) {
		update_option( 'mem_product_pages', 'none' );
		$type = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'name'     => 'Венки',
			)
		);
		$id   = self::factory()->post->create(
			array(
				'post_type'    => 'mem_product',
				'post_status'  => 'publish',
				'post_title'   => 'Венок «Память»',
				'post_content' => '<p>Живые розы и хвоя, лента с надписью.</p>',
			)
		);
		wp_set_object_terms( $id, array( $type ), 'mem_cat' );
		update_post_meta( $id, '_mem_price', 2500 );
		update_post_meta( $id, '_mem_specs', 'Высота: 120 см' );
		$photo = self::factory()->attachment->create_object(
			'venok.jpg',
			$id,
			array( 'post_mime_type' => 'image/jpeg' )
		);
		set_post_thumbnail( $id, $photo );
		ob_start();
		memorial_plugin_catalog_card(
			get_post( $id ),
			0,
			array(
				'view'  => $view,
				'order' => array(
					'on'   => true,
					'text' => 'Заказать',
				),
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * «Окно товара»: описание и характеристики в HTML, скрыты; название, цена и кнопка — по одному разу.
	 */
	public function test_card_view() {
		$html = $this->card( 'card' );
		if ( false === strpos( $html, 'data-pv' ) ) {
			$this->markTestSkipped( 'No large photo in this test site.' );
		}
		$this->assertStringNotContainsString( '<template', $html );
		$this->assertMatchesRegularExpression( '#<div class="mcard__info" hidden[^>]*>\s*<div class="pv__desc"><p>Живые розы и хвоя, лента с надписью\.</p>#u', $html );
		$this->assertStringContainsString( '<dl class="pv__specs"><div><dt>Высота</dt><dd>120', $html );
		$this->assertStringNotContainsString( 'pv__title', $html );
		$this->assertSame( 1, substr_count( $html, 'data-callback="order"' ) );
		$this->assertSame( 1, substr_count( $html, 'Живые розы' ) );
		$this->assertSame( 1, substr_count( $html, 'mcard__price' ) );
	}

	/**
	 * «Только фото»: без описания, характеристики в скрытом блоке.
	 */
	public function test_photo_view() {
		$html = $this->card( 'photo' );
		if ( false === strpos( $html, 'data-pv' ) ) {
			$this->markTestSkipped( 'No large photo in this test site.' );
		}
		$this->assertStringNotContainsString( 'pv__desc', $html );
		$this->assertStringContainsString( 'pv__specs', $html );
		$this->assertMatchesRegularExpression( '#<div class="mcard__info" hidden#', $html );
	}
}
