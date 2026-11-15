<?php
/**
 * Import of products from a table: reading, the check, applying, the rollback, the example files.
 *
 * @package memorial-plugin
 */

/**
 * Import tests.
 */
class Test_Memorial_Plugin_Catalog_Import extends WP_UnitTestCase {

	/**
	 * Product type.
	 *
	 * @var int
	 */
	private $type = 0;

	/**
	 * Category of the type.
	 *
	 * @var int
	 */
	private $cat = 0;

	/**
	 * Name of the type.
	 *
	 * @var string
	 */
	private $type_name = '';

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->type_name = 'Венки ' . wp_generate_password( 4, false, false );
		$this->type      = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'name'     => $this->type_name,
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
				)
			)
		);
		$this->cat = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'name'     => 'Овальные',
				'parent'   => $this->type,
			)
		);
		delete_option( 'memorial_plugin_import_log' );
	}

	/**
	 * Run an import of rows to the end, as the browser does.
	 *
	 * @param array $rows    Rows with the header.
	 * @param bool  $publish Publish at once.
	 * @return array{preview:array,rows:array}
	 */
	private function import( $rows, $publish = false ) {
		$preview = memorial_plugin_import_preview( $rows, array(), $publish, 'test.csv' );
		$this->assertNotWPError( $preview );
		$out    = array();
		$offset = 0;
		do {
			$res    = memorial_plugin_import_run( $preview['token'], $offset );
			$out    = array_merge( $out, $res['rows'] );
			$offset = $res['next'];
		} while ( ! $res['done'] );
		return array(
			'preview' => $preview,
			'rows'    => $out,
		);
	}

	/**
	 * Header used by the tests.
	 *
	 * @return string[]
	 */
	private function head() {
		return array( 'Артикул', 'Тип', 'Категории', 'Название', 'Цена', 'Единица', 'На сайте', 'Описание', 'Характеристика: Высота' );
	}

	public function test_reads_csv_in_windows_1251_with_commas() {
		$rows = memorial_plugin_import_read_csv( mb_convert_encoding( "Артикул,Название,Цена\r\nM-2,\"Венок, синий\",\"2 500\"\r\n", 'Windows-1251', 'UTF-8' ) );
		$this->assertSame( array( 'M-2', 'Венок, синий', '2 500' ), $rows[1] );
	}

	public function test_reads_the_excel_file_of_the_export() {
		$bytes = memorial_plugin_io_xlsx(
			array(
				'Товары' => array(
					'head' => array( 'Артикул', 'Цена', 'Фото' ),
					'rows' => array( array( 'M-1', 4500, "1.jpg\n2.jpg" ) ),
				),
			)
		);
		$tmp   = wp_tempnam( 'import-test' );
		file_put_contents( $tmp, $bytes ); // phpcs:ignore
		$rows = memorial_plugin_import_read( $tmp, 'x.xlsx' );
		unlink( $tmp ); // phpcs:ignore
		$this->assertSame( array( array( 'Артикул', 'Цена', 'Фото' ), array( 'M-1', '4500', "1.jpg\n2.jpg" ) ), $rows );
	}

	public function test_headers_in_russian_english_and_specs() {
		$map = memorial_plugin_import_map( array( 'артикул', 'Title', 'Цена «от»', 'Характеристика: Высота', 'Spec: Width', 'Заметки' ) );
		$this->assertSame( array( 'sku', 'title', 'from', 'spec:Высота', 'spec:Width' ), array_values( $map['cols'] ) );
		$this->assertSame( array( 'Заметки' ), $map['unknown'] );
	}

	public function test_creates_hidden_products_and_publishes_with_the_checkbox() {
		$res = $this->import(
			array(
				$this->head(),
				array( 'T-1', $this->type_name, 'Овальные', 'Венок «Нежность»', '4 500', 'шт', '', "Первый.\n\nВторой.", '140 см' ),
			)
		);
		$this->assertSame( 1, $res['preview']['count']['create'] );
		$id = memorial_plugin_product_by_sku( 'T-1' );
		$this->assertGreaterThan( 0, $id );
		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( 'Венок «Нежность»', get_the_title( $id ) );
		$this->assertSame( 4500, (int) get_post_meta( $id, '_mem_price', true ) );
		$this->assertSame( 'шт', get_post_meta( $id, '_mem_unit', true ) );
		$this->assertSame( array( 'Высота' => '140 см' ), memorial_plugin_product_spec_map( $id ) );
		$this->assertSame( array( $this->cat ), wp_get_object_terms( $id, 'mem_cat', array( 'fields' => 'ids' ) ) );
		$this->assertSame( "Первый.\n\nВторой.", memorial_plugin_product_desc( get_post( $id ) )['text'] );

		$this->import( array( $this->head(), array( 'T-2', $this->type_name, '', 'Второй', '', '', '', '', '' ) ), true );
		$this->assertSame( 'publish', get_post_status( memorial_plugin_product_by_sku( 'T-2' ) ) );
	}

	public function test_update_by_sku_empty_keeps_and_dash_clears() {
		$this->import( array( $this->head(), array( 'T-1', $this->type_name, '', 'Венок', '4500', 'шт', 'да', 'Текст', '140' ) ) );
		$id  = memorial_plugin_product_by_sku( 'T-1' );
		$res = $this->import( array( $this->head(), array( 'T-1', '', '', '', '5000', '-', '', '', '' ) ) );
		$this->assertSame( 'update', $res['preview']['rows'][0]['action'] );
		$this->assertSame( $id, memorial_plugin_product_by_sku( 'T-1' ) );
		$this->assertSame( 5000, (int) get_post_meta( $id, '_mem_price', true ) );
		$this->assertSame( '', get_post_meta( $id, '_mem_unit', true ) );
		$this->assertSame( 'Венок', get_the_title( $id ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( '140', memorial_plugin_product_spec_map( $id )['Высота'] );
		// The same file again changes nothing.
		$again = memorial_plugin_import_preview( array( $this->head(), array( 'T-1', '', '', '', '5000', '-', '', '', '' ) ), array(), false );
		$this->assertSame( 'same', $again['rows'][0]['action'] );
	}

	public function test_export_then_import_changes_nothing() {
		$this->import( array( $this->head(), array( 'T-1', $this->type_name, 'Овальные', 'Венок «Нежность»', '4500', 'шт', 'да', "А.\n\nБ.", '140 см' ) ) );
		$this->import( array( $this->head(), array( '', $this->type_name, '', 'Без артикула', '', '', 'нет', '', '' ) ) );
		$t       = memorial_plugin_io_table( array( 'type' => $this->type ) );
		$rows    = array_merge( array( $t['head'] ), array_map( static fn( $r ) => array_map( 'strval', $r ), $t['rows'] ) );
		$preview = memorial_plugin_import_preview( $rows, array(), false );
		$this->assertSame( 2, $preview['count']['same'], wp_json_encode( $preview['rows'], JSON_UNESCAPED_UNICODE ) );
		$this->assertSame( 0, $preview['count']['create'] + $preview['count']['update'] + $preview['count']['error'] );
	}

	public function test_errors_skip_the_row() {
		$res  = memorial_plugin_import_preview(
			array(
				$this->head(),
				array( 'E-1', 'Нет такого типа', '', 'А', '', '', '', '', '' ),
				array( 'E-2', $this->type_name, 'Нет такой', 'Б', '', '', '', '', '' ),
				array( 'E-3', $this->type_name, '', 'В', 'дорого', '', 'может', '', '' ),
				array( 'E-4', $this->type_name, '', '', '', '', '', '', '' ),
				array( 'E-5', $this->type_name, '', 'Г', '', '', '', '', '' ),
				array( 'e-5', $this->type_name, '', 'Д', '', '', '', '', '' ),
			),
			array(),
			false
		);
		$acts = array_column( $res['rows'], 'action' );
		$this->assertSame( array( 'error', 'error', 'error', 'error', 'create', 'error' ), $acts );
		$this->assertCount( 2, $res['rows'][2]['errors'] );
	}

	public function test_photo_from_the_library_by_name_gets_alt_title_and_description() {
		$att = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->import(
			array(
				array( 'Артикул', 'Тип', 'Название', 'Описание', 'Фото', 'Alt' ),
				array( 'P-1', $this->type_name, 'Венок', 'Красивый.', 'canola.jpg', '' ),
			)
		);
		$id = memorial_plugin_product_by_sku( 'P-1' );
		$this->assertSame( $att, (int) get_post_thumbnail_id( $id ) );
		$this->assertSame( 'Венок', get_post_meta( $att, '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'Венок', get_the_title( $att ) );
		$this->assertSame( 'Красивый.', get_post( $att )->post_content );
		// An alt from the file replaces it.
		$this->import( array( array( 'Артикул', 'Alt' ), array( 'P-1', 'Венок спереди' ) ) );
		$this->assertSame( 'Венок спереди', get_post_meta( $att, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * A photo of the library without a file (the name is enough for finding it).
	 *
	 * @param string $file  Path in uploads.
	 * @param string $title Title.
	 * @return int
	 */
	private function photo( $file, $title ) {
		return self::factory()->attachment->create(
			array(
				'file'           => $file,
				'post_mime_type' => 'image/webp',
				'post_title'     => $title,
			)
		);
	}

	public function test_same_names_in_the_library_and_twice_in_a_product_change_nothing() {
		// As on the site: venki8.webp in two folders; one product has the same photo twice; a name with two tails.
		$a  = $this->photo( '2021/11/venki8.webp', 'venki8' );
		$b  = $this->photo( '2024/05/venki8.webp', 'venki8' );
		$c  = $this->photo( '2024/05/venki9.webp', 'venki9' );
		$d  = $this->photo( '2021/11/20211112_130501-1-e1641470811619-scaled.webp', '20211112_130501' );
		$p1 = memorial_plugin_cm_create( $this->type, 0, 'Первый' );
		$p2 = memorial_plugin_cm_create( $this->type, 0, 'Второй' );
		memorial_plugin_product_set_photos( $p1, array( $b, $c, $a ) );
		memorial_plugin_product_set_photos( $p2, array( $d ) );
		$t       = memorial_plugin_io_table( array( 'ids' => array( $p1, $p2 ) ) );
		$rows    = array_merge( array( $t['head'] ), array_map( static fn( $r ) => array_map( 'strval', $r ), $t['rows'] ) );
		$preview = memorial_plugin_import_preview( $rows, array(), false );
		$this->assertSame( array( 'same', 'same' ), array_column( $preview['rows'], 'action' ), wp_json_encode( $preview['rows'], JSON_UNESCAPED_UNICODE ) );
	}

	public function test_missing_photo_is_an_error_and_files_are_matched_by_folder() {
		$res = memorial_plugin_import_preview(
			array(
				array( 'Артикул', 'Тип', 'Название', 'Фото' ),
				array( 'F-1', $this->type_name, 'А', 'нет-такого.jpg' ),
				array( 'F-2', $this->type_name, 'Б', '' ),
				array( 'F-3', $this->type_name, 'В', '1.jpg' ),
			),
			memorial_plugin_import_files(
				array(
					array(
						'path' => 'foto/F-2/2.jpg',
						'size' => 10,
						'hash' => '',
					),
					array(
						'path' => 'foto/F-2/1.jpg',
						'size' => 10,
						'hash' => '',
					),
					array(
						'path' => 'foto/F-3/1.jpg',
						'size' => 10,
						'hash' => '',
					),
				)
			),
			false
		);
		$this->assertSame( 'error', $res['rows'][0]['action'] );
		$this->assertSame( 2, $res['rows'][1]['photos']['file'] );
		$this->assertSame( 1, $res['rows'][2]['photos']['file'] );
		$this->assertSame( array( 'foto/f-2/1.jpg', 'foto/f-2/2.jpg', 'foto/f-3/1.jpg' ), $res['need'] );
	}

	public function test_rollback_restores_and_trashes() {
		$this->import( array( $this->head(), array( 'R-1', $this->type_name, '', 'Было', '1000', '', 'да', '', '' ) ) );
		$old = memorial_plugin_product_by_sku( 'R-1' );
		$this->import(
			array(
				$this->head(),
				array( 'R-1', '', '', 'Стало', '2000', '', 'нет', '', '' ),
				array( 'R-2', $this->type_name, '', 'Новый', '', '', '', '', '' ),
			)
		);
		$new = memorial_plugin_product_by_sku( 'R-2' );
		$this->assertSame( 2000, (int) get_post_meta( $old, '_mem_price', true ) );
		$this->assertTrue( memorial_plugin_import_last()['canUndo'] );
		$res = memorial_plugin_import_rollback();
		$this->assertSame( 1, $res['trashed'] );
		$this->assertSame( 'trash', get_post_status( $new ) );
		$this->assertSame( 'Было', get_the_title( $old ) );
		$this->assertSame( 1000, (int) get_post_meta( $old, '_mem_price', true ) );
		$this->assertSame( 'publish', get_post_status( $old ) );
		$this->assertWPError( memorial_plugin_import_rollback() );
	}

	public function test_token_belongs_to_the_user() {
		$preview = memorial_plugin_import_preview( array( $this->head(), array( 'U-1', $this->type_name, '', 'А', '', '', '', '', '' ) ), array(), false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertWPError( memorial_plugin_import_run( $preview['token'], 0 ) );
	}

	public function test_author_may_not_import() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertFalse( memorial_plugin_import_can() );
		$req = new WP_REST_Request( 'POST', '/memorial-plugin/v1/catalog/import/rollback' );
		$this->assertSame( 403, rest_do_request( $req )->get_status() );
	}

	public function test_example_files() {
		foreach ( array( 'xlsx', 'csv', 'zip' ) as $format ) {
			$res = memorial_plugin_import_sample( $format );
			$this->assertNotWPError( $res, $format );
			$this->assertSame( 'catalog-import-example.' . $format, $res['filename'] );
		}
		$tmp = wp_tempnam( 'sample' );
		file_put_contents( $tmp, base64_decode( memorial_plugin_import_sample( 'xlsx' )['file'] ) ); // phpcs:ignore
		$rows = memorial_plugin_import_read( $tmp, 'x.xlsx' );
		unlink( $tmp ); // phpcs:ignore
		$preview = memorial_plugin_import_preview( $rows, array(), false );
		$this->assertSame( 3, count( $preview['rows'] ) );
		// Photos of the example are not in the library and not sent: the rows with photos wait for them.
		$this->assertSame( 'error', $preview['rows'][0]['action'] );
		$this->assertSame( 'create', $preview['rows'][2]['action'] );
	}
}
