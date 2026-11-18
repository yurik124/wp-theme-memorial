<?php
/**
 * «Мемориал → Обработка фото» (pages/photo-tools.php): the formulas, the frame, the processing of a real attachment
 * and the return of the original.
 *
 * @package memorial-plugin
 */

/**
 * Photo tools tests.
 */
class Test_Memorial_Plugin_Photo_Tools extends WP_UnitTestCase {

	/**
	 * A JPEG file: a dark grey square on a light background, a little blue.
	 *
	 * @param int $w Width.
	 * @param int $h Height.
	 * @return string Path.
	 */
	private function jpeg( $w = 1200, $h = 800 ) {
		$im = imagecreatetruecolor( $w, $h );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 225, 228, 240 ) );
		imagefilledrectangle( $im, (int) ( $w * 0.4 ), (int) ( $h * 0.3 ), (int) ( $w * 0.6 ), (int) ( $h * 0.8 ), imagecolorallocate( $im, 60, 62, 80 ) );
		$file = get_temp_dir() . 'photo-' . wp_rand() . '.jpg';
		imagejpeg( $im, $file, 90 );
		return $file;
	}

	/**
	 * A product with a photo of a type.
	 *
	 * @param string $type Root section slug.
	 * @return int Attachment ID.
	 */
	private function product_photo( $type = 'venki' ) {
		$term = term_exists( $type, 'mem_cat' );
		$term = $term ? (int) $term['term_id'] : self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'slug'     => $type,
			)
		);
		$prod = self::factory()->post->create( array( 'post_type' => 'mem_product' ) );
		wp_set_object_terms( $prod, array( $term ), 'mem_cat' );
		$att = self::factory()->attachment->create_upload_object( $this->jpeg(), $prod );
		set_post_thumbnail( $prod, $att );
		return $att;
	}

	public function test_steps_and_defaults() {
		$this->assertSame( '−10', memorial_plugin_photo_steps( 10, 5 )['-10'] );
		$this->assertSame( '+5', memorial_plugin_photo_steps( 10, 5 )['5'] );
		$s = memorial_plugin_photos();
		$this->assertSame( 0, (int) $s['main']['on'], 'Off until the owner switches it on.' );
		$this->assertArrayNotHasKey( 'bg', $s, 'No background replacement on the site (owner, 18.11.2026).' );
		$this->assertArrayNotHasKey( 'srgb', $s['clean'] );
	}

	public function test_luts_stretch_and_keep_order() {
		$im = memorial_plugin_photo_load( $this->jpeg() );
		[ $r, $g, $b ] = memorial_plugin_photo_luts(
			memorial_plugin_photo_sample( $im ),
			array(
				'levels' => 1,
				'wb'     => '100',
				'warmth' => '10',
			)
		);
		$this->assertCount( 256, $r );
		for ( $i = 1; $i < 256; $i++ ) {
			$this->assertGreaterThanOrEqual( $r[ $i - 1 ], $r[ $i ], 'A table never goes down.' );
		}
		// The bluish photo: after the white balance and warmth blue is lowered against red.
		$this->assertGreaterThan( $b[200], $r[200] );
	}

	public function test_frame_of_a_product_and_of_a_work() {
		$im = memorial_plugin_photo_load( $this->jpeg( 1200, 800 ) );
		$bg = memorial_plugin_photo_border( memorial_plugin_photo_sample( $im ) );
		$f  = memorial_plugin_photo_frame(
			$im,
			array(
				'ratio' => '3:4',
				'trim'  => true,
				'pad'   => 6,
			),
			$bg
		);
		$this->assertNotNull( $f );
		$this->assertEqualsWithDelta( 3 / 4, $f[0] / $f[1], 0.01, 'Product: 3:4 canvas.' );
		$this->assertLessThan( 800, $f[1], 'Cropped to the object.' );

		$w = memorial_plugin_photo_frame(
			$im,
			array(
				'ratio' => '1:1',
				'trim'  => false,
				'pad'   => 0,
			),
			$bg
		);
		$this->assertSame( array( 800, 800 ), array( $w[0], $w[1] ), 'Work: cut to the middle.' );
	}

	/**
	 * Own background: the frame of a product stays inside the photo — nothing is added around it.
	 */
	public function test_frame_with_own_background_stays_inside() {
		// A tall object: 3:4 with margins would be taller than the photo.
		$im = imagecreatetruecolor( 900, 1000 );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 225, 228, 240 ) );
		imagefilledrectangle( $im, 300, 20, 600, 980, imagecolorallocate( $im, 60, 62, 80 ) );
		$bg   = memorial_plugin_photo_border( memorial_plugin_photo_sample( $im ) );
		$crop = array(
			'ratio' => '3:4',
			'trim'  => true,
			'pad'   => 6,
		);
		$add  = memorial_plugin_photo_frame( $im, $crop, $bg );
		$this->assertGreaterThan( 1000, $add[1], 'A single background: the canvas grows, the colour fills the rest.' );
		$fit = memorial_plugin_photo_frame( $im, $crop, $bg, true );
		$this->assertLessThanOrEqual( 900, $fit[0] );
		$this->assertLessThanOrEqual( 1000, $fit[1] );
		$this->assertEqualsWithDelta( 3 / 4, $fit[0] / $fit[1], 0.01 );
		$this->assertSame( array( 0, 0 ), array( $fit[6], $fit[7] ), 'Placed at the corner: no margins of colour.' );
		$this->assertSame( array( $fit[0], $fit[1] ), array( $fit[4], $fit[5] ), 'The whole canvas is the photo.' );
	}

	/**
	 * The picture of the logo made on the screen: saved by variant, used when the logo of the site is SVG.
	 */
	public function test_logo_made_on_the_screen() {
		$im = imagecreatetruecolor( 300, 100 );
		imagesavealpha( $im, true );
		imagefill( $im, 0, 0, imagecolorallocatealpha( $im, 0, 0, 0, 127 ) );
		ob_start();
		imagepng( $im );
		$png = 'data:image/png;base64,' . base64_encode( (string) ob_get_clean() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a test image.
		$this->assertWPError( memorial_plugin_photo_logo_save( 'data:text/plain;base64,AAAA', 'k', 'white-1' ) );
		$this->assertWPError( memorial_plugin_photo_logo_save( $png, 'k', '../x' ) );
		$this->assertTrue( memorial_plugin_photo_logo_save( $png, 'k1', 'white-1' ) );
		$this->assertTrue( memorial_plugin_photo_logo_save( $png, 'k2', 'site-0' ) );
		$this->assertFileExists( memorial_plugin_photo_logo_made( 'white-1' ) );
		$this->assertSame( 'white-1', memorial_plugin_photo_logo_variant(), 'Defaults: white, with the name.' );
		$this->assertSame( 'k1', memorial_plugin_photo_logo_source()['keys']->{'white-1'} );
		$this->assertSame( 'site-0', memorial_plugin_photo_logo_variant( array( 'tint' => 'site' ) ) );
		$this->assertSame(
			'white-0-p',
			memorial_plugin_photo_logo_variant(
				array(
					'text'  => 0,
					'plate' => 1,
				)
			),
			'Dark background: “-p”.'
		);
		$this->assertTrue( memorial_plugin_photo_logo_save( $png, 'k3', 'site-1-p' ) );
		wp_delete_file( memorial_plugin_photo_logo_made( 'site-1-p' ) );
		wp_delete_file( memorial_plugin_photo_logo_made( 'white-1' ) );
		wp_delete_file( memorial_plugin_photo_logo_made( 'site-0' ) );
	}

	public function test_process_and_return_the_original() {
		$att  = $this->product_photo( 'pamyatniki' );
		$orig = get_attached_file( $att );
		update_option(
			'mem_photos',
			array(
				'main'  => array(
					'on'      => 1,
					'product' => 1,
				),
				'color' => array(
					'on'     => 1,
					'levels' => 1,
					'wb'     => '50',
				),
				'crop'  => array(
					'on'      => 1,
					'trim'    => 1,
					'product' => '3:4',
					'pad'     => '6',
				),
			)
		);
		$scope = memorial_plugin_photo_scope();
		$this->assertArrayHasKey( $att, $scope );
		$this->assertSame( array( 'product', 'pamyatniki' ), $scope[ $att ] );

		$this->assertSame( 'done', memorial_plugin_photo_process( $att, 'product', 'pamyatniki' ) );
		$new = get_attached_file( $att );
		$this->assertNotSame( $orig, $new );
		$this->assertMatchesRegularExpression( '/-m[0-9a-f]{8}\.jpg$/', $new );
		$this->assertFileExists( $orig, 'The original stays.' );
		$size = getimagesize( $new );
		$this->assertEqualsWithDelta( 3 / 4, $size[0] / $size[1], 0.02 );
		$this->assertSame( 'same', memorial_plugin_photo_process( $att, 'product', 'pamyatniki' ), 'Not twice with the same settings.' );

		$this->assertTrue( memorial_plugin_photo_restore( $att ) );
		$this->assertSame( $orig, get_attached_file( $att ) );
		$this->assertFileDoesNotExist( $new, 'The processed file is removed.' );
	}

	/**
	 * Settings saved before 18.11.2026 with a background and sRGB: nothing waits, the photo is processed here.
	 */
	public function test_old_background_settings_are_ignored() {
		$att = $this->product_photo( 'venki' );
		update_option(
			'mem_photos',
			array(
				'main'  => array(
					'on'      => 1,
					'product' => 1,
				),
				'color' => array( 'on' => 1 ),
				'bg'    => array(
					'on'         => 1,
					'type_venki' => 1,
				),
				'clean' => array( 'srgb' => 1 ),
			)
		);
		$recipe = memorial_plugin_photo_recipe( 'product', 'venki' );
		$this->assertArrayNotHasKey( 'bg', $recipe );
		$this->assertArrayNotHasKey( 'srgb', $recipe );
		$this->assertSame( 'done', memorial_plugin_photo_process( $att, 'product', 'venki' ) );
	}

	public function test_report_finds_small_and_no_description() {
		$att = $this->product_photo( 'groby' );
		update_post_meta( $att, '_wp_attachment_image_alt', '' );
		$report = wp_list_pluck( memorial_plugin_photo_report(), 'problems', 'id' );
		$this->assertContains( 'alt', $report[ $att ] ?? array() );
		$this->assertSame( 0, memorial_plugin_photo_hamming( '1010', '1010' ) );
		$this->assertSame( 2, memorial_plugin_photo_hamming( '1010', '0110' ) );
	}
}
