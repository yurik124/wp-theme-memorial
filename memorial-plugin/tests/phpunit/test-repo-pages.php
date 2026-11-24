<?php
/**
 * Pages from the repository (pages/repo-pages.php): a new page with its menu item and links, an untouched
 * page is updated, a page edited on the site and an old page wait for a decision, wrong blocks are refused.
 *
 * @package memorial-plugin
 */

/**
 * Repository pages tests.
 */
class Test_Memorial_Plugin_Repo_Pages extends WP_UnitTestCase {

	/** Folder of the page files of the test. @var string */
	private $dir = '';

	/** Pages of the site. @var int[] */
	private $pages = array();

	public function set_up() {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->pages['uslugi'] = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_name'  => 'ritualnye-uslugi',
				'post_title' => 'Ритуальные услуги',
			)
		);
		$this->pages['zal']    = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'traurnyj-zal',
				'post_title'  => 'Траурный зал',
				'post_parent' => $this->pages['uslugi'],
			)
		);
		$this->pages['nav']    = self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Главное меню',
				'post_content' => '<!-- wp:navigation-submenu {"label":"Ритуальные услуги","type":"page","id":' . $this->pages['uslugi'] . ',"kind":"post-type"} -->'
					. '<!-- wp:navigation-link {"label":"Траурный зал","type":"page","id":' . $this->pages['zal'] . ',"kind":"post-type"} /-->'
					. '<!-- wp:navigation-link {"label":"Груз 200","url":"/ritualnye-uslugi/gruz-200/","kind":"custom"} /-->'
					. '<!-- /wp:navigation-submenu -->'
					. '<!-- wp:navigation-link {"label":"Контакты","url":"/kontakty/","kind":"custom"} /-->',
			)
		);
		$nav = $this->pages['nav'];
		add_filter( 'memorial_plugin_main_nav_id', static fn() => $nav, 99 );
		$this->use_dir( 'a' );
	}

	public function tear_down() {
		remove_all_filters( 'memorial_plugin_repo_dir' );
		remove_all_filters( 'memorial_plugin_main_nav_id', 99 );
		parent::tear_down();
	}

	/**
	 * A fresh folder of page files (the list of files is kept per folder for one request).
	 *
	 * @param string $name Name.
	 */
	private function use_dir( $name ) {
		$this->dir = get_temp_dir() . 'mem-repo-' . $name . '-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->dir . '/ritualnye-uslugi' );
		$dir = $this->dir;
		remove_all_filters( 'memorial_plugin_repo_dir' );
		add_filter( 'memorial_plugin_repo_dir', static fn() => $dir );
	}

	/**
	 * Writes a page file.
	 *
	 * @param string $path    Path without .html.
	 * @param array  $head    Header fields.
	 * @param string $content Blocks.
	 */
	private function file( $path, $head, $content ) {
		$head += array(
			'slug'            => basename( $path ),
			'parent'          => false !== strpos( $path, '/' ) ? dirname( $path ) : '',
			'title'           => 'Страница',
			'seo_title'       => 'SEO-заголовок',
			'seo_description' => 'SEO-описание',
			'status'          => 'publish',
			'version'         => 1,
		);
		file_put_contents( $this->dir . '/' . $path . '.html', '<!-- memorial-page ' . wp_json_encode( $head, JSON_UNESCAPED_UNICODE ) . " -->\n" . $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	private function katafalk( $version = 1, $text = 'Катафалк у «Мемориала» свой.' ) {
		$this->file(
			'ritualnye-uslugi/katafalk',
			array(
				'title'     => 'Катафалк на похороны',
				'seo_title' => 'Катафалк в Белгороде',
				'version'   => $version,
				'menu'      => array(
					'parent' => '/ritualnye-uslugi/',
					'label'  => 'Катафалк',
					'after'  => '/ritualnye-uslugi/traurnyj-zal/',
				),
			),
			"<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->\n\n"
			. '<!-- wp:memorial-plugin/cards {"pages":"/ritualnye-uslugi/traurnyj-zal/,/ceny/"} /-->'
		);
	}

	private function status_of( $path ) {
		foreach ( memorial_plugin_repo_plan() as $row ) {
			if ( $row['id'] === $path ) {
				return $row['status'];
			}
		}
		return '';
	}

	public function test_new_page_with_menu_item_and_links() {
		$this->katafalk();
		$this->assertSame( 'create', $this->status_of( 'ritualnye-uslugi/katafalk' ) );
		$done = memorial_plugin_repo_apply( array(), 'auto' );
		$id   = memorial_plugin_repo_page_id( 'ritualnye-uslugi/katafalk' );
		$this->assertGreaterThan( 0, $id );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( $this->pages['uslugi'], wp_get_post_parent_id( $id ) );
		$this->assertSame( 'Катафалк в Белгороде', get_post_meta( $id, '_seopress_titles_title', true ) );
		$this->assertSame( '1', get_post_meta( $id, '_mem_repo_version', true ) );
		// The address of the hall became its ID; the missing «Prices» page is a warning in the journal.
		$this->assertStringContainsString( '"pages":"' . $this->pages['zal'] . '"', get_post_field( 'post_content', $id ) );
		$this->assertContains( 'warning', wp_list_pluck( $done, 'action' ) );
		// The menu item stands right after «Траурный зал».
		$nav = get_post_field( 'post_content', $this->pages['nav'] );
		$this->assertMatchesRegularExpression( '/"id":' . $this->pages['zal'] . '[^>]*\/-->\s*<!-- wp:navigation-link \{"label":"Катафалк"[^}]*"id":' . $id . '/', $nav );
		// Once more — nothing new: no second menu item.
		memorial_plugin_repo_menu_add(
			array(
				'parent' => '/ritualnye-uslugi/',
				'label'  => 'Катафалк',
			),
			$id,
			'/ritualnye-uslugi/katafalk/'
		);
		$this->assertSame( 1, substr_count( get_post_field( 'post_content', $this->pages['nav'] ), '"label":"Катафалк"' ) );
		$this->assertSame( 'same', $this->status_of( 'ritualnye-uslugi/katafalk' ) );
	}

	public function test_untouched_page_is_updated_edited_page_waits() {
		$this->katafalk();
		memorial_plugin_repo_apply( array(), 'auto' );
		$id = memorial_plugin_repo_page_id( 'ritualnye-uslugi/katafalk' );

		$this->use_dir( 'b' );
		$this->katafalk( 2, 'Новый текст из репозитория.' );
		$this->assertSame( 'update', $this->status_of( 'ritualnye-uslugi/katafalk' ) );
		memorial_plugin_repo_apply( array(), 'auto' );
		$this->assertStringContainsString( 'Новый текст из репозитория.', get_post_field( 'post_content', $id ) );
		$this->assertNotEmpty( wp_get_post_revisions( $id ), 'the text before stays as a revision' );

		// The owner edits the text in the admin area; version 3 of the file must not overwrite it.
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => '<!-- wp:paragraph --><p>Правка владельца.</p><!-- /wp:paragraph -->',
			)
		);
		$this->use_dir( 'c' );
		$this->katafalk( 3, 'Версия три.' );
		$this->assertSame( 'conflict', $this->status_of( 'ritualnye-uslugi/katafalk' ) );
		memorial_plugin_repo_apply( array(), 'auto' );
		$this->assertStringContainsString( 'Правка владельца.', get_post_field( 'post_content', $id ) );
		// «Keep as on the site» — the question is closed for this version.
		memorial_plugin_repo_apply( array( 'ritualnye-uslugi/katafalk' => 'site' ) );
		$this->assertSame( 'kept', $this->status_of( 'ritualnye-uslugi/katafalk' ) );
		// «Take from the repository».
		memorial_plugin_repo_apply( array( 'ritualnye-uslugi/katafalk' => 'repo' ) );
		$this->assertStringContainsString( 'Версия три.', get_post_field( 'post_content', $id ) );
		$this->assertSame( 'same', $this->status_of( 'ritualnye-uslugi/katafalk' ) );
	}

	public function test_old_page_only_through_the_screen() {
		$ceny = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'ceny',
				'post_content' => '<!-- wp:paragraph --><p>Старые цены.</p><!-- /wp:paragraph -->',
			)
		);
		$this->file( 'ceny', array( 'title' => 'Цены' ), "<!-- wp:paragraph -->\n<p>Новые цены {price:lenta}.</p>\n<!-- /wp:paragraph -->" );
		$this->assertSame( 'legacy', $this->status_of( 'ceny' ) );
		memorial_plugin_repo_apply( array(), 'auto' );
		$this->assertStringContainsString( 'Старые цены.', get_post_field( 'post_content', $ceny ) );
		$diff = memorial_plugin_repo_diff( 'ceny' );
		$this->assertStringContainsString( 'Новые цены', $diff['html'] );
		memorial_plugin_repo_apply( array( 'ceny' => 'repo' ) );
		$this->assertStringContainsString( 'Новые цены', get_post_field( 'post_content', $ceny ) );
	}

	public function test_wrong_blocks_are_refused() {
		$this->file( 'ritualnye-uslugi/test', array(), '<!-- wp:buttons --><div class="wp-block-buttons"></div><!-- /wp:buttons -->' );
		$this->assertSame( 'error', $this->status_of( 'ritualnye-uslugi/test' ) );
		memorial_plugin_repo_apply( array(), 'auto' );
		$this->assertSame( 0, memorial_plugin_repo_page_id( 'ritualnye-uslugi/test' ) );
		$log = get_option( 'memorial_plugin_repo_log' );
		$this->assertSame( 'error', end( $log )['action'] );
	}

	public function test_price_codes_become_site_values() {
		$prices                   = memorial_plugin_prices_defaults();
		$prices['other']['items'] = array(
			array(
				'name'  => 'Надпись на ленту',
				'what'  => 'custom',
				'price' => '200–500',
				'code'  => 'lenta',
			),
		);
		update_option( 'mem_prices', $prices );
		$this->file( 'ritualnye-uslugi/lenta', array( 'title' => 'Лента' ), "<!-- wp:paragraph -->\n<p>Надпись на ленту — {price:lenta}.</p>\n<!-- /wp:paragraph -->" );
		memorial_plugin_repo_apply( array(), 'auto' );
		$id      = memorial_plugin_repo_page_id( 'ritualnye-uslugi/lenta' );
		$content = get_post_field( 'post_content', $id );
		$this->assertStringNotContainsString( '{price:', $content );
		$this->assertStringContainsString( 'data-value="price"', $content );
		$this->assertStringContainsString( '200–500', do_blocks( $content ) );
	}

	public function test_screen_needs_the_right_to_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$res = rest_do_request( new WP_REST_Request( 'GET', '/memorial-plugin/v1/repo-pages' ) );
		$this->assertSame( 403, $res->get_status() );
	}
}
