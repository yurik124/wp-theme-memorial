<?php
/**
 * The “Call me back” form against bots (leads/callback.php), no comments and XML-RPC (core/no-comments.php),
 * only services in “Our services” (content/services.php).
 *
 * @package memorial-plugin
 */

/**
 * Antispam tests.
 */
class Test_Memorial_Plugin_Antispam extends WP_UnitTestCase {

	/**
	 * A request to the form.
	 *
	 * @param array $params Parameters.
	 * @return mixed
	 */
	private function send( array $params ) {
		$r = new WP_REST_Request( 'POST', '/mem/v1/callback' );
		foreach ( $params + array(
			'phone'   => '+7 (905) 111-22-33',
			'consent' => 1,
			'website' => '',
		) as $k => $v ) {
			$r->set_param( $k, $v );
		}
		return memorial_plugin_callback_receive( $r );
	}

	/**
	 * The last request.
	 *
	 * @return WP_Post|null
	 */
	private function last() {
		$p = get_posts(
			array(
				'post_type'      => 'mem_lead',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		return $p[0] ?? null;
	}

	public function set_up() {
		parent::set_up();
		$_SERVER['REMOTE_ADDR'] = '10.0.0.' . wp_rand( 1, 250 );
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	public function test_token_is_signed_by_the_site() {
		$t = memorial_plugin_callback_token( time() - 10 );
		$this->assertSame( 10, memorial_plugin_callback_token_age( $t ) );
		$this->assertNull( memorial_plugin_callback_token_age( ( time() - 60 ) . '.' . substr( $t, -24 ) ), 'Another time with the old signature.' );
		$this->assertNull( memorial_plugin_callback_token_age( '' ) );
		$this->assertNull( memorial_plugin_callback_token_age( 'abc' ) );
	}

	public function test_honeypot_gives_thanks_without_a_request() {
		$before = (int) wp_count_posts( 'mem_lead' )->publish;
		$this->assertSame( array( 'ok' => true ), $this->send( array( 'website' => 'http://spam' ) ) );
		$this->assertSame( $before, (int) wp_count_posts( 'mem_lead' )->publish );
	}

	public function test_too_fast_is_refused_but_no_upper_limit() {
		$fast = $this->send( array( 'token' => memorial_plugin_callback_token( time() - 1 ) ) );
		$this->assertWPError( $fast );
		$this->assertSame( 'fast', $fast->get_error_code() );

		$this->assertSame( array( 'ok' => true ), $this->send( array( 'token' => memorial_plugin_callback_token( time() - 3 * DAY_IN_SECONDS ) ) ), 'A window opened long ago is fine.' );
		$this->assertSame( '', get_post_meta( $this->last()->ID, '_mem_check', true ) );
	}

	public function test_doubtful_requests_are_kept_with_a_mark() {
		$this->send( array() );
		$this->assertSame( 'token', get_post_meta( $this->last()->ID, '_mem_check', true ), 'Sent not from the window.' );

		$this->send(
			array(
				'token' => memorial_plugin_callback_token( time() - 20 ),
				'name'  => 'Cheap pills http://spam.example',
			)
		);
		$this->assertSame( 'text', get_post_meta( $this->last()->ID, '_mem_check', true ) );

		$this->assertTrue( memorial_plugin_callback_text_suspicious( 'buy at shop.ru now' ) );
		$this->assertFalse( memorial_plugin_callback_text_suspicious( 'Анна Петровна' ) );
		$this->assertFalse( memorial_plugin_callback_text_suspicious( 'Anna' ) );
	}

	public function test_email_subject_of_a_request_to_check() {
		$subject = '';
		add_filter(
			'pre_wp_mail',
			static function ( $r, $atts ) use ( &$subject ) {
				$subject = $atts['subject'];
				return true;
			},
			5,
			2
		);
		update_option(
			'mem_modules_page',
			array(
				'mail' => array(
					'to'      => 'owner@example.org',
					'subject' => 'Call back: {phone}',
					'body'    => '{phone}',
				),
			)
		);
		$this->send( array() );
		$this->assertMatchesRegularExpression( '/^\[[^\]]+\] Call back: /u', $subject );
	}

	public function test_many_requests_in_an_hour_hold_the_emails() {
		for ( $i = 0; $i < MEMORIAL_PLUGIN_CALLBACK_HOUR_LIMIT; $i++ ) {
			self::factory()->post->create(
				array(
					'post_type'   => 'mem_lead',
					'post_status' => 'publish',
				)
			);
		}
		$_SERVER['REMOTE_ADDR'] = '10.0.1.1';
		$this->send( array( 'token' => memorial_plugin_callback_token( time() - 30 ) ) );
		$this->assertSame( 'flood', get_post_meta( $this->last()->ID, '_mem_check', true ) );
		$this->assertSame( 'held', get_post_meta( $this->last()->ID, '_mem_mail', true ) );
	}

	public function test_comments_and_xmlrpc_are_closed() {
		$post = self::factory()->post->create( array( 'comment_status' => 'open' ) );
		$this->assertFalse( comments_open( $post ) );
		$this->assertFalse( pings_open( $post ) );
		$this->assertFalse( apply_filters( 'xmlrpc_enabled', true ) );
		$this->assertSame( array(), apply_filters( 'xmlrpc_methods', array( 'pingback.ping' => 'x' ) ) );
		$this->assertArrayNotHasKey( 'X-Pingback', apply_filters( 'wp_headers', array( 'X-Pingback' => 'x' ) ) );
	}

	public function test_only_services_stay_in_our_services() {
		$tovary = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'ritualnye-tovary',
			)
		);
		$zal    = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'traurnyj-zal',
			)
		);
		update_option(
			'mem_home',
			array(
				'services' => array(
					'items' => array(
						array(
							'title' => 'Траурный зал',
							'page'  => $zal,
						),
						array(
							'title' => 'Ритуальные товары',
							'page'  => $tovary,
						),
						array(
							'title' => 'Благоустройство захоронений',
							'page'  => 0,
						),
					),
				),
			)
		);
		$this->assertCount( 2, memorial_plugin_services_upgrade_1( true ) );
		$this->assertCount( 3, get_option( 'mem_home' )['services']['items'], 'Dry run writes nothing.' );
		memorial_plugin_services_upgrade_1();
		$this->assertSame( array( 'Траурный зал' ), array_column( get_option( 'mem_home' )['services']['items'], 'title' ) );
	}
}
