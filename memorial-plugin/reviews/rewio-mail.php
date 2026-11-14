<?php
/**
 * Email about new and deleted Rewio reviews.
 *
 * Switched on in «Мемориал → Отзывы → Отзывы Rewio → 6. Уведомления»; where to, the subject and the text —
 * «Мемориал → Настройки → Почта» (mem_modules_page['rmail']). The email goes after a loading of the reviews
 * (reviews/rewio-archive.php) that found reviews that were not there before or reviews that disappeared
 * from the review site. The very first loading (the site has no copies yet) sends nothing.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fields of the email on the «Почта» tab.
 *
 * @return array
 */
function memorial_plugin_reviews_mail_schema() {
	return array(
		'type'   => 'group',
		'tab'    => _x( 'Mail', 'settings tab', 'memorial-plugin' ),
		'label'  => __( 'Email about new and deleted reviews', 'memorial-plugin' ),
		'help'   => __( 'Comes after a loading of the Rewio reviews that found new reviews or reviews deleted on the review site — if “Notify about new or deleted reviews by email” is ticked in “Memorial → Reviews → Rewio reviews”.', 'memorial-plugin' ),
		'fields' => array(
			'to'      => array(
				'type'        => 'emails',
				'label'       => __( 'Where to send', 'memorial-plugin' ),
				'placeholder' => __( 'Empty — to the addresses of the request email', 'memorial-plugin' ),
				'help'        => __( 'Several addresses — separated by commas.', 'memorial-plugin' ),
			),
			'subject' => array(
				'type'  => 'text',
				'label' => __( 'Subject', 'memorial-plugin' ),
			),
			'body'    => array(
				'type'  => 'textarea',
				'rows'  => 8,
				'label' => __( 'Text of the email', 'memorial-plugin' ),
			),
			'test'    => array(
				'type'  => 'button',
				'label' => __( 'Send a test email', 'memorial-plugin' ),
				'path'  => '/memorial-plugin/v1/reviews-mail-test',
				'help'  => __( 'Sends an example with two reviews with these settings (also unsaved) to the addresses above.', 'memorial-plugin' ),
			),
		),
	);
}

/**
 * Defaults of the email.
 *
 * @return array
 */
function memorial_plugin_reviews_mail_defaults() {
	return array(
		'to'      => '',
		'subject' => __( 'Reviews of {site}: new — {new}, deleted — {gone}', 'memorial-plugin' ),
		'body'    => __( 'Rewio reviews were loaded on the site {site} ({time}).', 'memorial-plugin' ) . "\n\n"
			. __( 'New reviews:', 'memorial-plugin' ) . " {new}\n"
			. __( 'Deleted on the review site:', 'memorial-plugin' ) . " {gone}\n\n"
			. "{list}\n\n"
			. __( 'Show or hide a review on the site:', 'memorial-plugin' ) . ' {link}',
	);
}

/**
 * The saved settings over the defaults.
 *
 * @return array
 */
function memorial_plugin_reviews_mail_settings() {
	$saved = get_option( 'mem_modules_page' );
	$saved = is_array( $saved ) && is_array( $saved['rmail'] ?? null ) ? $saved['rmail'] : array();
	$out   = memorial_plugin_reviews_mail_defaults();
	foreach ( $saved as $k => $v ) {
		if ( is_string( $v ) && ( '' !== trim( $v ) || 'to' === $k ) ) {
			$out[ $k ] = $v;
		}
	}
	return $out;
}

/**
 * One line of the list: new or deleted, author, rating, review site, date, the beginning of the text.
 *
 * @param array  $r    Review: author, rating, date, text.
 * @param string $site Review site.
 * @param bool   $gone Deleted on the review site.
 * @return string
 */
function memorial_plugin_reviews_mail_line( $r, $site, $gone ) {
	$text  = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $r['text'] ?? '' ) ) ) );
	$text  = mb_strlen( $text ) > 160 ? rtrim( mb_substr( $text, 0, 157 ) ) . '…' : $text;
	$date  = (string) ( $r['date'] ?? '' );
	$parts = array_filter(
		array(
			trim( (string) ( $r['author'] ?? '' ) ),
			null !== ( $r['rating'] ?? null ) && '' !== (string) $r['rating'] ? '★ ' . (float) $r['rating'] : '',
			trim( $site ),
			'' !== $date ? wp_date( 'd.m.Y', (int) strtotime( $date ) ) : '',
		)
	);
	return '— ' . ( $gone ? __( 'Deleted:', 'memorial-plugin' ) : __( 'New:', 'memorial-plugin' ) ) . ' ' . implode( ', ', $parts )
		. ( '' !== $text ? "\n  «" . $text . '»' : '' );
}

/**
 * Build and send the email.
 *
 * @param array $new      Lines of new reviews ([review, site]).
 * @param array $gone     Lines of deleted reviews ([review, site]).
 * @param array $settings Settings (memorial_plugin_reviews_mail_settings()).
 * @return array{ok:bool,error:string,to:string[]}
 */
function memorial_plugin_reviews_mail_send( $new, $gone, $settings ) {
	$lines = array();
	foreach ( $new as [$r, $site] ) {
		$lines[] = memorial_plugin_reviews_mail_line( $r, $site, false );
	}
	foreach ( $gone as [$r, $site] ) {
		$lines[] = memorial_plugin_reviews_mail_line( $r, $site, true );
	}
	$vars   = array(
		'{new}'  => (string) count( $new ),
		'{gone}' => (string) count( $gone ),
		'{list}' => implode( "\n\n", $lines ),
		'{time}' => wp_date( 'd.m.Y H:i' ),
		'{site}' => home_url( '/' ),
		'{link}' => admin_url( 'admin.php?page=memorial-plugin-rewio' ),
	);
	$sender = memorial_plugin_mail_sender();
	$to     = '' !== trim( (string) $settings['to'] ) ? (string) $settings['to'] : $sender['to'];
	return memorial_plugin_mail_send(
		memorial_plugin_mail_to( $to ),
		strtr( (string) $settings['subject'], $vars ),
		strtr( (string) $settings['body'], $vars ),
		$sender['from_email'],
		$sender['from_name']
	);
}

/**
 * After a loading: send the email if it is switched on and something new or deleted was found.
 *
 * @param int[] $new_ids  Copies (posts mem_rewio) of new reviews.
 * @param int[] $gone_ids Copies of reviews that disappeared from the review site.
 */
function memorial_plugin_reviews_mail_found( $new_ids, $gone_ids ) {
	if ( ( ! $new_ids && ! $gone_ids ) || empty( memorial_plugin_rewio()['notify']['on'] ) ) {
		return;
	}
	$rows = static fn( $ids ) => array_map(
		static fn( $id ) => array( memorial_plugin_rewio_copy_data( (int) $id ), (string) get_post_meta( (int) $id, '_rw_site', true ) ),
		$ids
	);
	$res  = memorial_plugin_reviews_mail_send( $rows( $new_ids ), $rows( $gone_ids ), memorial_plugin_reviews_mail_settings() );
	update_option(
		'memorial_plugin_reviews_mail_last',
		array(
			'time'  => time(),
			'new'   => count( $new_ids ),
			'gone'  => count( $gone_ids ),
			'ok'    => $res['ok'],
			'error' => $res['error'],
		),
		false
	);
}
add_action( 'memorial_plugin_rewio_found', 'memorial_plugin_reviews_mail_found', 10, 2 );

/* Test email: an example with two reviews, the settings from the screen (also unsaved). */
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'memorial-plugin/v1',
			'/reviews-mail-test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'values' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$in       = (array) $request['values'];
					$settings = memorial_plugin_reviews_mail_defaults();
					foreach ( array_keys( $settings ) as $k ) {
						if ( isset( $in[ $k ] ) && is_string( $in[ $k ] ) && ( '' !== trim( $in[ $k ] ) || 'to' === $k ) ) {
							$settings[ $k ] = 'body' === $k ? sanitize_textarea_field( $in[ $k ] ) : sanitize_text_field( $in[ $k ] );
						}
					}
					$example = static fn( $author, $rating, $text ) => array(
						'author' => $author,
						'rating' => $rating,
						'date'   => wp_date( 'Y-m-d' ),
						'text'   => $text,
					);
					$res     = memorial_plugin_reviews_mail_send(
						array( array( $example( __( 'Test', 'memorial-plugin' ), 5, __( 'An example of a new review.', 'memorial-plugin' ) ), 'Яндекс Карты' ) ),
						array( array( $example( __( 'Test', 'memorial-plugin' ), 4, __( 'An example of a review deleted on the review site.', 'memorial-plugin' ) ), '2ГИС' ) ),
						$settings
					);
					return memorial_plugin_mail_test_answer( $res );
				},
			)
		);
	}
);
