<?php
/**
 * Письмо о заявке «Перезвоните мне»: «Мемориал → Настройки → Почта».
 *
 * Кому, тема, текст (простой текст с подстановками {phone}, {name}, {page}, {url}, {time}, {site}, {link}),
 * имя и адрес отправителя. Отправка — core/mail.php (wp_mail(): почта хостинга или плагин почты). У заявки остаётся отметка «Письмо: отправлено / не отправлено» и текст ошибки —
 * видно в «Заявках на обратный звонок». Кнопка «Отправить тестовое письмо» проверяет настройки.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fields of the «Почта» tab.
 *
 * @return array
 */
function memorial_plugin_lead_mail_schema() {
	return array(
		'type'   => 'group',
		'tab'    => _x( 'Mail', 'settings tab', 'memorial-plugin' ),
		'label'  => __( 'Email about a call-back request', 'memorial-plugin' ),
		'help'   => __( 'Every request from the “Call me back” window comes by email and stays in “Memorial → Call-back requests”. The codes for the subject and the text are in “Codes for the emails” above.', 'memorial-plugin' ),
		'fields' => array(
			'to'         => array(
				'type'        => 'emails',
				'label'       => __( 'Where to send requests', 'memorial-plugin' ),
				'placeholder' => __( 'Empty — to the first email of “Contacts”', 'memorial-plugin' ),
				'help'        => __( 'Several addresses — separated by commas.', 'memorial-plugin' ),
			),
			'subject'    => array(
				'type'  => 'text',
				'label' => __( 'Subject', 'memorial-plugin' ),
			),
			'body'       => array(
				'type'  => 'textarea',
				'rows'  => 8,
				'label' => __( 'Text of the email', 'memorial-plugin' ),
			),
			'from_name'  => array(
				'type'        => 'text',
				'label'       => __( 'Sender name', 'memorial-plugin' ),
				'placeholder' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			),
			'from_email' => array(
				'type'        => 'email',
				'label'       => __( 'Sender address', 'memorial-plugin' ),
				'placeholder' => __( 'Empty — as the hosting sends', 'memorial-plugin' ),
				'help'        => __( 'Better an address of the site’s own domain, otherwise mail services may put the email into spam.', 'memorial-plugin' ),
			),
			'test'       => array(
				'type'  => 'button',
				'label' => __( 'Send a test email', 'memorial-plugin' ),
				'path'  => '/memorial-plugin/v1/lead-mail-test',
				'help'  => __( 'Sends an email with these settings (also unsaved) to the addresses above.', 'memorial-plugin' ),
			),
		),
	);
}

/**
 * The tab's defaults: the text as the email was before.
 *
 * @return array
 */
function memorial_plugin_lead_mail_defaults() {
	return array(
		'to'         => '',
		'subject'    => __( 'Call back: {phone} ({name})', 'memorial-plugin' ),
		'body'       => __( 'Call-back request from the site {site}', 'memorial-plugin' ) . "\n\n"
			. __( 'Phone:', 'memorial-plugin' ) . " {phone}\n"
			. __( 'Name:', 'memorial-plugin' ) . " {name}\n"
			. __( 'Page:', 'memorial-plugin' ) . " {page}\n"
			. __( 'Time:', 'memorial-plugin' ) . " {time}\n\n"
			. __( 'All requests:', 'memorial-plugin' ) . ' {link}',
		'from_name'  => '',
		'from_email' => '',
	);
}

/**
 * The saved settings over the defaults.
 *
 * @return array
 */
function memorial_plugin_lead_mail_settings() {
	$saved = get_option( 'mem_modules_page' );
	$saved = is_array( $saved ) && is_array( $saved['mail'] ?? null ) ? $saved['mail'] : array();
	$out   = memorial_plugin_lead_mail_defaults();
	foreach ( $saved as $k => $v ) {
		if ( is_string( $v ) && ( '' !== trim( $v ) || in_array( $k, array( 'to', 'from_name', 'from_email' ), true ) ) ) {
			$out[ $k ] = $v;
		}
	}
	return $out;
}

/**
 * Addresses of the request email (core/mail.php).
 *
 * @param string $to Addresses separated by commas.
 * @return string[]
 */
function memorial_plugin_lead_mail_to( $to ) {
	return memorial_plugin_mail_to( $to );
}

/**
 * Send the email of a request.
 *
 * @param array $lead     phone, name, page (address).
 * @param array $settings Settings (memorial_plugin_lead_mail_settings()).
 * @return array{ok:bool,error:string,to:string[]}
 */
function memorial_plugin_lead_mail_send( $lead, $settings ) {
	$page = (string) ( $lead['page'] ?? '' );
	// {page} — the title of the page, as the “From page” column of “Call-back requests” shows it.
	$link = '' !== $page && function_exists( 'memorial_plugin_collection_pagelink' ) ? memorial_plugin_collection_pagelink( $page ) : null;
	$name = trim( (string) ( $lead['name'] ?? '' ) );
	$vars = array(
		'{phone}' => (string) ( $lead['phone'] ?? '' ),
		'{name}'  => '' !== $name ? $name : '—',
		'{page}'  => '' === $page ? '—' : ( $link ? $link['title'] : urldecode( $page ) ),
		'{url}'   => '' === $page ? '—' : urldecode( $page ),
		// The “Order” button of a product card; empty for a usual request.
		'{product}' => trim( (string) ( $lead['product'] ?? '' ) ),
		'{time}'  => wp_date( 'd.m.Y H:i' ),
		'{site}'  => home_url( '/' ),
		'{link}'  => admin_url( 'admin.php?page=memorial-plugin-leads' ),
	);
	$subject = str_replace( ' ()', '', strtr( (string) $settings['subject'], '' !== $name ? $vars : array( '{name}' => '' ) + $vars ) );
	// A request to check (no mark of the window, a link in the name…): the same email with a mark in the subject.
	if ( ! empty( $lead['check'] ) ) {
		$subject = '[' . __( 'Check', 'memorial-plugin' ) . '] ' . $subject;
	}
	$body    = strtr( (string) $settings['body'], $vars );
	// An order from a product card reaches the email even if the text has no {product}.
	if ( '' !== $vars['{product}'] && false === strpos( (string) $settings['body'], '{product}' ) ) {
		$body = __( 'Order:', 'memorial-plugin' ) . ' ' . $vars['{product}'] . "\n\n" . $body;
	}
	return memorial_plugin_mail_send( memorial_plugin_mail_to( (string) $settings['to'] ), $subject, $body, (string) $settings['from_email'], (string) $settings['from_name'] );
}

/**
 * Email of a new request + a mark on the request (shown in the list).
 *
 * @param int   $id   Request ID.
 * @param array $lead phone, name, page.
 */
function memorial_plugin_lead_mail( $id, $lead ) {
	$res = memorial_plugin_lead_mail_send( $lead, memorial_plugin_lead_mail_settings() );
	if ( $id ) {
		update_post_meta( $id, '_mem_mail', $res['ok'] ? 'sent' : 'failed' );
		$res['ok'] ? delete_post_meta( $id, '_mem_mail_error' ) : update_post_meta( $id, '_mem_mail_error', $res['error'] );
	}
	return $res;
}

/**
 * Many requests in an hour (memorial_plugin_callback_receive): instead of an email for each — one email an hour
 * “many requests, check the list”.
 *
 * @return bool Sent now.
 */
function memorial_plugin_lead_mail_flood() {
	if ( get_transient( 'memorial_plugin_lead_flood' ) ) {
		return false;
	}
	set_transient( 'memorial_plugin_lead_flood', 1, HOUR_IN_SECONDS );
	$settings = memorial_plugin_lead_mail_settings();
	$subject  = '[' . __( 'Check', 'memorial-plugin' ) . '] ' . __( 'Many call-back requests in an hour', 'memorial-plugin' );
	$body     = sprintf(
		/* translators: 1: number of requests, 2: link to the list. */
		__( 'More than %1$d requests came from the site in the last hour — maybe spam. The emails of the next requests wait for an hour; the requests themselves are kept with the mark “Check”: %2$s', 'memorial-plugin' ),
		MEMORIAL_PLUGIN_CALLBACK_HOUR_LIMIT,
		admin_url( 'admin.php?page=memorial-plugin-leads' )
	);
	return (bool) memorial_plugin_mail_send( memorial_plugin_mail_to( (string) $settings['to'] ), $subject, $body, (string) $settings['from_email'], (string) $settings['from_name'] )['ok'];
}

/* Test email: the settings from the screen (also unsaved). */
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'memorial-plugin/v1',
			'/lead-mail-test',
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
					$settings = memorial_plugin_lead_mail_defaults();
					foreach ( array_keys( $settings ) as $k ) {
						if ( isset( $in[ $k ] ) && is_string( $in[ $k ] ) && ( '' !== trim( $in[ $k ] ) || in_array( $k, array( 'to', 'from_name', 'from_email' ), true ) ) ) {
							$settings[ $k ] = 'body' === $k ? sanitize_textarea_field( $in[ $k ] ) : sanitize_text_field( $in[ $k ] );
						}
					}
					$res = memorial_plugin_lead_mail_send(
						array(
							'phone' => '+7 900 000-00-00',
							'name'  => __( 'Test', 'memorial-plugin' ),
							'page'  => home_url( '/' ),
						),
						$settings
					);
					return memorial_plugin_mail_test_answer( $res );
				},
			)
		);
	}
);
