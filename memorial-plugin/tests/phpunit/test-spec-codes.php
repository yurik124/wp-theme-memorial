<?php
/**
 * Коды характеристик {spec:code} в названии и описании товара.
 *
 * @package memorial-plugin
 */

/**
 * Spec codes.
 */
class Test_Memorial_Plugin_Spec_Codes extends WP_UnitTestCase {

	/**
	 * Type ID.
	 *
	 * @var int
	 */
	private $type;

	/**
	 * Product ID.
	 *
	 * @var int
	 */
	private $product;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->type = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'name'     => 'Венки',
			)
		);
		update_term_meta(
			$this->type,
			'mem_fields',
			wp_json_encode(
				array(
					array(
						'label' => 'Высота',
						'type'  => 'number',
						'unit'  => 'см',
					),
					array(
						'label' => 'Ширина',
						'type'  => 'number',
						'unit'  => 'см',
						'code'  => 'shir',
					),
					array(
						'label' => 'Цвет',
						'type'  => 'text',
					),
				),
				JSON_UNESCAPED_UNICODE
			)
		);
		$this->product = self::factory()->post->create(
			array(
				'post_type'    => 'mem_product',
				'post_status'  => 'publish',
				'post_title'   => 'Венок {spec:vysota} ({spec:shir})',
				'post_content' => '<!-- wp:paragraph --><p>Цвет: {spec:tsvet}.</p><!-- /wp:paragraph -->',
			)
		);
		wp_set_object_terms( $this->product, array( $this->type ), 'mem_cat' );
		update_post_meta( $this->product, '_mem_specs', "Высота: 120\nЦвет: белый" );
	}

	/**
	 * Код — латиницей из названия; свой код сохраняется; повтор — с номером.
	 */
	public function test_codes() {
		$codes = array_column( memorial_plugin_type_fields( $this->type, false ), 'code' );
		$this->assertSame( array( 'vysota', 'shir', 'tsvet' ), $codes );
		$this->assertSame(
			array( 'vysota', 'vysota_2' ),
			array_column(
				memorial_plugin_spec_codes_fill(
					array(
						array(
							'label' => 'Высота',
							'code'  => '',
						),
						array(
							'label' => 'Высота',
							'code'  => '',
						),
					)
				),
				'code'
			)
		);
		$this->assertSame( 'shir', memorial_plugin_clean_field( array( 'label' => 'Ширина', 'code' => 'Shir!' ) )['code'] );
	}

	/**
	 * Значение — с единицей; пустое — код убирается вместе со скобками.
	 */
	public function test_fill() {
		$this->assertSame( "Венок 120\u{00A0}см", memorial_plugin_spec_fill( 'Венок {spec:vysota} ({spec:shir})', $this->product ) );
		$this->assertSame( 'Венок', memorial_plugin_spec_fill( 'Венок — {spec:nety}', $this->product ) );
		$this->assertSame( 'Без кодов (как есть)', memorial_plugin_spec_fill( 'Без кодов (как есть)', $this->product ) );
		// Переименование характеристики не меняет код.
		$fields             = memorial_plugin_type_fields( $this->type, false );
		$fields[0]['label'] = 'Высота венка';
		memorial_plugin_types_save( $this->type, array( 'fields' => $fields ) );
		$this->assertSame( 'vysota', memorial_plugin_type_fields( $this->type, false )[0]['code'] );
	}

	/**
	 * Сайт показывает значения; админка каталога — название с кодами и итог.
	 */
	public function test_site_and_admin() {
		$this->assertSame( "Венок 120\u{00A0}см", get_the_title( $this->product ) );
		$GLOBALS['post'] = get_post( $this->product ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the loop of the product page.
		setup_postdata( $GLOBALS['post'] );
		$this->assertStringContainsString( 'Цвет: белый.', apply_filters( 'the_content', get_post( $this->product )->post_content ) );
		wp_reset_postdata();
		$item = memorial_plugin_manager_product( get_post( $this->product ) );
		$this->assertSame( 'Венок {spec:vysota} ({spec:shir})', $item['title'] );
		$this->assertSame( "Венок 120\u{00A0}см", $item['shown'] );
	}
}
