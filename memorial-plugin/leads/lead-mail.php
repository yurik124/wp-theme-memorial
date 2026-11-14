<?php
/**
 * Письмо о заявке «Перезвоните мне»: «Мемориал → Настройки → Почта».
 *
 * Кому, тема, текст (простой текст с подстановками {phone}, {name}, {page}, {time}, {site}, {link}),
 * имя и адрес отправителя. Отправка — стандартная wp_mail() (почта хостинга или плагин почты, если он
 * стоит). У заявки остаётся отметка «Письмо: отправлено / не отправлено» и текст ошибки —
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
		'help'   => __( 'Every request from the “Call me back” window comes by email and stays in “Memorial → Call-back requests”. In the subject and the text: {phone} — phone, {name} — name, {page} — the page it was sent from, {time} — date and time, {site} — the site address, {link} — all requests in the admin.', 'memorial-plugin' ),
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
 * Addresses: from the tab, else the first email of «Контакты», else the site administrator.
 *
 * @param string $to Addresses separated by commas.
 * @return string[]
 */
function memorial_plugin_lead_mail_to( $to ) {
	$list = array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $to ) ) ), 'is_email' ) );
	if ( ! $list ) {
		$first = function_exists( 'memorial_plugin_emails' ) ? ( memorial_plugin_emails()[0] ?? '' ) : '';
		$list  = array( $first ? $first : (string) get_option( 'admin_email' ) );
	}
	return $list;
}

/**
 * Send the email of a request.
 *
 * @param array $lead     phone, name, page (address).
 * @param array $settings Settings (memorial_plugin_lead_mail_settings()).
 * @return array{ok:bool,error:string,to:string[]}
 */
function memorial_plugin_lead_mail_send( $lead, $settings ) {
	$page  = (string) ( $lead['page'] ?? '' );
	$title = '';
	if ( '' !== $page ) {
		$pid   = url_to_postid( $page );
		$title = $pid ? html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' ) : '';
	}
	$name = trim( (string) ( $lead['name'] ?? '' ) );
	$vars = array(
		'{phone}' => (string) ( $lead['phone'] ?? '' ),
		'{name}'  => '' !== $name ? $name : '—',
		'{page}'  => '' === $page ? '—' : ( '' !== $title ? $title . ' — ' : '' ) . urldecode( $page ),
		'{time}'  => wp_date( 'd.m.Y H:i' ),
		'{site}'  => home_url( '/' ),
		'{link}'  => admin_url( 'admin.php?page=memorial-plugin-leads' ),
	);
	$subject = str_replace( ' ()', '', strtr( (string) $settings['subject'], '' !== $name ? $vars : array( '{name}' => '' ) + $vars ) );
	$body    = strtr( (string) $settings['body'], $vars );
	$to      = memorial_plugin_lead_mail_to( (string) $settings['to'] );
	$from    = sanitize_email( (string) $settings['from_email'] );
	$from_nm = trim( wp_strip_all_tags( (string) $settings['from_name'] ) );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( $from && is_email( $from ) ) {
		$headers[] = 'From: ' . ( '' !== $from_nm ? '"' . str_replace( '"', '', $from_nm ) . '" ' : '' ) . '<' . $from . '>';
	}
	$name_filter = static fn() => $from_nm;
	if ( ! $from && '' !== $from_nm ) {
		add_filter( 'wp_mail_from_name', $name_filter );
	}
	$error = '';
	$catch = static function ( $e ) use ( &$error ) {
		$error = $e instanceof WP_Error ? $e->get_error_message() : __( 'The email was not accepted.', 'memorial-plugin' );
	};
	add_action( 'wp_mail_failed', $catch );
	// What the mail program of the hosting prints (warnings) must not get into the answer of the site.
	ob_start();
	$ok    = wp_mail( $to, wp_specialchars_decode( $subject ), $body, $headers );
	$noise = trim( (string) ob_get_clean() );
	remove_action( 'wp_mail_failed', $catch );
	if ( ! $ok && '' === $error && '' !== $noise ) {
		$error = wp_strip_all_tags( $noise );
	}
	remove_filter( 'wp_mail_from_name', $name_filter );
	if ( ! $ok && '' === $error ) {
		$error = __( 'The hosting did not accept the email (wp_mail() returned false).', 'memorial-plugin' );
	}
	return array(
		'ok'    => (bool) $ok,
		'error' => $error,
		'to'    => $to,
	);
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
					return rest_ensure_response(
						array(
							'ok'      => $res['ok'],
							'message' => $res['ok']
								/* translators: %s: email addresses. */
								? sprintf( __( 'The email was handed to the hosting for %s. If it does not come in a few minutes, look in “Spam”; if it is not there either, the hosting does not deliver mail — ask its support.', 'memorial-plugin' ), implode( ', ', $res['to'] ) )
								/* translators: %s: the error. */
								: sprintf( __( 'The email was not sent: %s', 'memorial-plugin' ), $res['error'] ),
						)
					);
				},
			)
		);
	}
);
