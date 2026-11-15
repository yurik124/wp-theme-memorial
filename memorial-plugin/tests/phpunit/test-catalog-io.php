<?php
/**
 * Catalog ↔ file: SKU, export to Excel and CSV.
 *
 * @package memorial-plugin
 */

/**
 * SKU and export tests.
 */
class Test_Memorial_Plugin_Catalog_Io extends WP_UnitTestCase {

	/**
	 * Product type.
	 *
	 * @var int
	 */
	private $type = 0;

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->type = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'name'     => 'Венки ' . wp_generate_password( 4, false ),
			)
		);
	}

	/**
	 * A product of the type.
	 *
	 * @param string $title Title.
	 * @return int
	 */
	private function product( $title = 'Венок' ) {
		$id = memorial_plugin_cm_create( $this->type, 0, $title );
		$this->assertGreaterThan( 0, $id );
		return $id;
	}

	public function test_a_new_product_gets_a_sku() {
		$id = $this->product();
		$this->assertSame( 'M-' . $id, get_post_meta( $id, '_mem_sku', true ) );
	}

	public function test_a_copy_gets_its_own_sku() {
		$id   = $this->product();
		$copy = memorial_plugin_catalog_copy( $id, array( '_mem_price', '_mem_specs' ), 'mem_cat' );
		$this->assertSame( 'M-' . $copy, get_post_meta( $copy, '_mem_sku', true ) );
	}

	public function test_sku_is_found_and_must_be_unique() {
		$a = $this->product( 'A' );
		$b = $this->product( 'B' );
		memorial_plugin_product_save( $a, array( 'sku' => ' W-100 ' ) );
		$this->assertSame( 'W-100', get_post_meta( $a, '_mem_sku', true ) );
		$this->assertSame( $a, memorial_plugin_product_by_sku( 'W-100' ) );
		$this->assertSame( 0, memorial_plugin_product_by_sku( 'W-100', $a ) );
		$this->assertWPError( memorial_plugin_sku_error( 'W-100', $b ) );
		$this->assertNull( memorial_plugin_sku_error( 'W-100', $a ) );
		// Another product cannot take it, and an empty value keeps the SKU.
		memorial_plugin_product_save( $b, array( 'sku' => 'W-100' ) );
		$this->assertSame( 'M-' . $b, get_post_meta( $b, '_mem_sku', true ) );
		memorial_plugin_product_save( $a, array( 'sku' => '' ) );
		$this->assertSame( 'W-100', get_post_meta( $a, '_mem_sku', true ) );
		// A product in the trash does not hold its SKU.
		wp_trash_post( $a );
		$this->assertSame( 0, memorial_plugin_product_by_sku( 'W-100' ) );
	}

	public function test_rest_refuses_a_taken_sku() {
		$a = $this->product( 'A' );
		$b = $this->product( 'B' );
		$r = new WP_REST_Request( 'POST', '/memorial-plugin/v1/catalog/products/' . $b );
		$r->set_body_params( array( 'values' => array( 'sku' => 'M-' . $a ) ) );
		$res = rest_do_request( $r );
		$this->assertSame( 400, $res->get_status() );
		$this->assertSame( 'M-' . $b, get_post_meta( $b, '_mem_sku', true ) );
	}

	public function test_migration_gives_skus_once_and_logs_them() {
		$id = $this->product();
		delete_post_meta( $id, '_mem_sku' );
		delete_option( 'memorial_plugin_sku_1' );
		memorial_plugin_sku_migrate();
		$this->assertSame( 'M-' . $id, get_post_meta( $id, '_mem_sku', true ) );
		$this->assertContains( $id, get_option( 'memorial_plugin_sku_log' )['ids'] );
		// The flag stops a second run.
		delete_post_meta( $id, '_mem_sku' );
		memorial_plugin_sku_migrate();
		$this->assertSame( '', get_post_meta( $id, '_mem_sku', true ) );
	}

	public function test_table_has_the_fields_of_a_product() {
		$cat = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'name'     => 'Овальные',
				'parent'   => $this->type,
			)
		);
		$att = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		update_post_meta( $att, '_wp_attachment_image_alt', 'Венок спереди' );
		$id = $this->product( 'Венок «Нежность»' );
		memorial_plugin_product_save(
			$id,
			array(
				'price'  => 4500,
				'from'   => true,
				'unit'   => 'шт',
				'desc'   => "Первый абзац.\n\nВторой.",
				'specs'  => array( 'Высота' => '140 см' ),
				'cats'   => array( $cat ),
				'photos' => array( $att ),
				'status' => 'publish',
			)
		);
		$t   = memorial_plugin_io_table( array( 'type' => $this->type ) );
		$row = array_combine( $t['keys'], $t['rows'][0] );
		$this->assertSame( 'M-' . $id, $row['sku'] );
		$this->assertSame( 'Овальные', $row['cats'] );
		$this->assertSame( 'Венок «Нежность»', $row['title'] );
		$this->assertSame( 4500, $row['price'] );
		$this->assertSame( 'yes', $row['from'] );
		$this->assertSame( 'yes', $row['status'] );
		$this->assertSame( "Первый абзац.\n\nВторой.", $row['desc'] );
		$this->assertMatchesRegularExpression( '/^canola(-\d+)?\.jpg$/', $row['photos'] );
		$this->assertSame( 'Венок спереди', $row['alts'] );
		$this->assertSame( '140 см', $row['spec:Высота'] );
		$this->assertContains( 'Spec: Высота', $t['head'] );
	}

	public function test_export_xlsx_is_a_workbook() {
		$this->product( 'Венок & <розы>' );
		$res = memorial_plugin_io_export(
			array(
				'format' => 'xlsx',
				'type'   => $this->type,
			)
		);
		$this->assertNotWPError( $res );
		$this->assertSame( 1, $res['count'] );
		$this->assertMatchesRegularExpression( '/^catalog-\d{4}-\d\d-\d\d\.xlsx$/', $res['filename'] );
		$tmp = wp_tempnam( 'xlsx-test' );
		file_put_contents( $tmp, base64_decode( $res['file'] ) ); // phpcs:ignore
		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $tmp ) );
		$sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$zip->close();
		unlink( $tmp ); // phpcs:ignore
		$this->assertNotFalse( simplexml_load_string( $sheet ) );
		$this->assertStringContainsString( 'Венок &amp; &lt;розы&gt;', $sheet );
	}

	public function test_export_only_selected_products() {
		$a   = $this->product( 'A' );
		$this->product( 'B' );
		$res = memorial_plugin_io_export( array( 'ids' => array( $a ) ) );
		$this->assertSame( 1, $res['count'] );
		$this->assertStringContainsString( '"A"', $res['csv'] );
		$this->assertStringNotContainsString( '"B"', $res['csv'] );
	}
}
