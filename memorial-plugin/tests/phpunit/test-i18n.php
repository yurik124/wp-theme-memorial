<?php
/**
 * Translations bundled with the plugin.
 *
 * @package memorial-plugin
 */

/**
 * The Russian translation is loaded and used by the plugin’s texts.
 */
class Test_Memorial_Plugin_I18n extends WP_UnitTestCase {

	public function tear_down() {
		unload_textdomain( 'memorial-plugin' );
		parent::tear_down();
	}

	private function load_ru() {
		unload_textdomain( 'memorial-plugin' );
		$this->assertTrue( load_textdomain( 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages/memorial-plugin-ru_RU.mo', 'ru_RU' ) );
	}

	public function test_strings_plurals_and_contexts_are_translated() {
		$this->load_ru();
		$this->assertSame( 'Телефоны', __( 'Phones', 'memorial-plugin' ) );
		$this->assertSame( '%d вопрос', _n( '%d question', '%d questions', 1, 'memorial-plugin' ) );
		$this->assertSame( '%d вопроса', _n( '%d question', '%d questions', 3, 'memorial-plugin' ) );
		$this->assertSame( '%d вопросов', _n( '%d question', '%d questions', 5, 'memorial-plugin' ) );
		$this->assertSame( '%d г.', _x( '%d', 'year', 'memorial-plugin' ) );
	}

	public function test_settings_schema_reads_russian_on_a_russian_site() {
		$this->load_ru();
		$schema = memorial_plugin_contacts_schema();
		$this->assertSame( 'Телефоны', $schema['phones']['label'] );
	}

	public function test_view_module_texts_have_plural_categories() {
		$this->load_ru();
		$texts = memorial_plugin_view_i18n();
		$this->assertSame( 'Показать ещё %d', $texts['showMore'] );
		$this->assertSame( '%d вопроса', $texts['questions']['few'] );
		$this->assertSame( '%d вопросов', $texts['questions']['many'] );
		$this->assertSame( '%d вопрос', $texts['questions']['one'] );
	}

	public function test_english_without_translation() {
		unload_textdomain( 'memorial-plugin' );
		$this->assertSame( 'Phones', __( 'Phones', 'memorial-plugin' ) );
	}
}
