<?php
/**
 * Old addresses (seo/redirects.php), unknown SEO variables (seo/seo-plugins.php) and the one-time move of
 * addresses and SEO titles (seo/seo-upgrade.php).
 *
 * @package memorial-plugin
 */

/**
 * SEO addresses tests.
 */
class Test_Memorial_Plugin_Seo_Addresses extends WP_UnitTestCase {

	public function test_request_path_is_decoded_with_a_trailing_slash() {
		$this->assertSame( '/kresty/', memorial_plugin_request_path( '/kresty?utm_source=ya' ) );
		$this->assertSame( '/index.html', memorial_plugin_request_path( '/index.html' ) );
		$this->assertSame( '/', memorial_plugin_request_path( '' ) );
		$this->assertSame( '/кресты/', memorial_plugin_request_path( '/%D0%BA%D1%80%D0%B5%D1%81%D1%82%D1%8B' ) );
	}

	public function test_old_addresses_lead_to_pages_or_home() {
		$page = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'politika-cookie',
			)
		);
		$this->assertSame( get_permalink( $page ), memorial_plugin_redirect_target( '/politika-ispolzovaniya-cookie/' ) );
		$this->assertSame( home_url( '/' ), memorial_plugin_redirect_target( '/index.html' ) );
		$this->assertSame( '', memorial_plugin_redirect_target( '/unknown/' ) );
		// The page of the target is gone — no redirect (an honest 404).
		$this->assertSame( '', memorial_plugin_redirect_target( '/kresty/' ) );
	}

	public function test_unknown_seo_variable_is_removed_with_its_dash() {
		$this->assertSame( 'Оградки в Белгороде | Мемориал', memorial_plugin_seo_unknown_vars( 'Оградки в Белгороде — %%mem_cat_price_ograda%% | Мемориал' ) );
		$this->assertSame( 'Пособие | Мемориал', memorial_plugin_seo_unknown_vars( 'Пособие: %%mem_x%% | Мемориал' ) );
		$this->assertSame( 'Без переменных', memorial_plugin_seo_unknown_vars( 'Без переменных' ) );
	}

	public function test_links_are_replaced_only_on_this_site() {
		$map  = array( '/ogradki-2/' => '/ogradki/' );
		$text = '<a href="/ogradki-2/">a</a> <a href="http://example.org/ogradki-2/">b</a> <a href="https://other.ru/ogradki-2/">c</a> "url":"https://memorialbel.ru/ogradki-2/"';
		$this->assertSame(
			'<a href="/ogradki/">a</a> <a href="http://example.org/ogradki/">b</a> <a href="https://other.ru/ogradki-2/">c</a> "url":"https://memorialbel.ru/ogradki/"',
			memorial_plugin_seo_1_links( $text, $map )
		);
		// A nested address is not touched: only /posobie-na-pogrebenie/ at the top level is broken.
		$map = array( '/posobie-na-pogrebenie/' => '/poleznoe/posobie-na-pogrebenie/' );
		$this->assertSame( 'href="/poleznoe/posobie-na-pogrebenie/"', memorial_plugin_seo_1_links( 'href="/poleznoe/posobie-na-pogrebenie/"', $map ) );
		$this->assertSame( 'href="/poleznoe/posobie-na-pogrebenie/"', memorial_plugin_seo_1_links( 'href="/posobie-na-pogrebenie/"', $map ) );
	}

	public function test_upgrade_dry_run_then_real_run() {
		$term = self::factory()->term->create(
			array(
				'taxonomy' => 'mem_cat',
				'slug'     => 'ogradki-2',
				'name'     => 'Оградки',
			)
		);
		$prod = self::factory()->post->create( array( 'post_type' => 'mem_product' ) );
		wp_set_object_terms( $prod, array( $term ), 'mem_cat' );
		update_post_meta( $prod, '_mem_price', 1000 );
		$fences  = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'ogradki-2',
			)
		);
		$parent  = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'poleznoe',
			)
		);
		$allow   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'posobie-na-pogrebenie',
				'post_parent' => $parent,
			)
		);
		$cookies = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'politika-cookie',
			)
		);
		$text    = '<!-- wp:paragraph --><p><a href="/ogradki-2/">Оградки</a>, <a href="/posobie-na-pogrebenie/">пособие</a></p><!-- /wp:paragraph -->';
		$linker  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'pamyatniki',
				'post_content' => $text,
			)
		);
		delete_option( 'mem_prices' );

		$dry = memorial_plugin_seo_upgrade_1( true );
		$this->assertSame( array( $fences, 'ogradki-2', 'ogradki' ), $dry['slug'] );
		$this->assertArrayHasKey( $linker, $dry['posts'] );
		$this->assertSame( 'ogradki-2', get_post_field( 'post_name', $fences ), 'Dry run writes nothing.' );
		$this->assertSame( $text, get_post_field( 'post_content', $linker ) );
		$this->assertFalse( get_option( 'mem_prices' ) );
		$this->assertSame( '', get_post_meta( $fences, '_seopress_titles_title', true ) );

		$log = memorial_plugin_seo_upgrade_1();
		$this->assertSame( 'ogradki', get_post_field( 'post_name', $fences ) );
		$this->assertStringContainsString( 'href="/ogradki/"', get_post_field( 'post_content', $linker ) );
		$this->assertStringContainsString( 'href="/poleznoe/posobie-na-pogrebenie/"', get_post_field( 'post_content', $linker ) );
		$this->assertSame( $text, $log['posts'][ $linker ], 'The old text is in the log.' );
		$this->assertSame( 'yes', get_post_meta( $cookies, '_seopress_robots_index', true ) );
		// The “from” price of the section is marked for the SEO plugin and is in the title.
		$this->assertArrayHasKey( 'ogradki_ot', memorial_plugin_seo_registry() );
		$this->assertSame( 'Оградки на кладбище в Белгороде — %%mem_ogradki_ot%% %%sep%% %%sitetitle%%', get_post_meta( $fences, '_seopress_titles_title', true ) );
		$this->assertStringContainsString( '1', memorial_plugin_seo_value( 'ogradki_ot' ) );
		// The burial allowance: with its separate price when the site has one, otherwise the plain variant.
		$this->assertMatchesRegularExpression( '/^Пособие на погребение( — %%mem_posobie%%| в Белгороде): как получить %%sep%% %%sitetitle%%$/u', get_post_meta( $allow, '_seopress_titles_title', true ) );
		// The old address of the page leads to the new one.
		$this->assertSame( get_permalink( $fences ), memorial_plugin_redirect_target( '/ogradki-2/' ) );
	}
}
