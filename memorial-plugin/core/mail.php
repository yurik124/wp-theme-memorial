<?php
/**
 * Emails of the site («Мемориал → Настройки → Почта»): the common sending of the email about a call-back
 * request (leads/lead-mail.php) and of the email about new and deleted reviews (reviews/rewio-mail.php).
 *
 * Plain text through the standard wp_mail() (the hosting mail or a mail plugin, if one is installed).
 * The sender name and address are the ones of the request email; they are kept even if “Call me back”
 * is switched off.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Addresses: from the field, else the first email of «Контакты», else the site administrator.
 *
 * @param string $to Addresses separated by commas.
 * @return string[]
 */
function memorial_plugin_mail_to( $to ) {
	$list = array_values( array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $to ) ) ), 'is_email' ) );
	if ( ! $list ) {
		$first = function_exists( 'memorial_plugin_emails' ) ? ( memorial_plugin_emails()[0] ?? '' ) : '';
		$list  = array( $first ? $first : (string) get_option( 'admin_email' ) );
	}
	return $list;
}

/**
 * Sender name and address of the request email.
 *
 * @return array{from_name:string,from_email:string,to:string}
 */
function memorial_plugin_mail_sender() {
	$saved = get_option( 'mem_modules_page' );
	$mail  = is_array( $saved ) && is_array( $saved['mail'] ?? null ) ? $saved['mail'] : array();
	return array(
		'from_name'  => (string) ( $mail['from_name'] ?? '' ),
		'from_email' => (string) ( $mail['from_email'] ?? '' ),
		'to'         => (string) ( $mail['to'] ?? '' ),
	);
}

/**
 * Send a plain text email.
 *
 * @param string[] $to         Addresses.
 * @param string   $subject    Subject.
 * @param string   $body       Text.
 * @param string   $from_email Sender address (empty — as the hosting sends).
 * @param string   $from_name  Sender name.
 * @return array{ok:bool,error:string,to:string[]}
 */
function memorial_plugin_mail_send( $to, $subject, $body, $from_email = '', $from_name = '' ) {
	$from    = sanitize_email( (string) $from_email );
	$from_nm = trim( wp_strip_all_tags( (string) $from_name ) );
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
	$ok    = wp_mail( $to, wp_specialchars_decode( (string) $subject ), (string) $body, $headers );
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
		'to'    => (array) $to,
	);
}

/**
 * The answer of a “Send a test email” button.
 *
 * @param array $res Result of memorial_plugin_mail_send().
 * @return WP_REST_Response
 */
function memorial_plugin_mail_test_answer( $res ) {
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
}

/**
 * The codes of the emails — a folded hint on the «Почта» tab.
 *
 * @return string HTML.
 */
function memorial_plugin_mail_codes_html() {
	$row  = static fn( $code, $what ) => '<li><code>' . esc_html( $code ) . '</code> — ' . esc_html( $what ) . '</li>';
	$html = '';
	if ( function_exists( 'memorial_plugin_lead_mail_schema' ) ) {
		$html .= '<p><strong>' . esc_html__( 'Email about a call-back request', 'memorial-plugin' ) . '</strong></p><ul>'
			. $row( '{phone}', __( 'phone of the visitor', 'memorial-plugin' ) )
			. $row( '{name}', __( 'name (“—” if not given)', 'memorial-plugin' ) )
			. $row( '{page}', __( 'title of the page the request was sent from, as in “Call-back requests”', 'memorial-plugin' ) )
			. $row( '{url}', __( 'address of that page', 'memorial-plugin' ) )
			. $row( '{time}', __( 'date and time of the request', 'memorial-plugin' ) )
			. $row( '{site}', __( 'address of the site', 'memorial-plugin' ) )
			. $row( '{link}', __( 'link to “Call-back requests” in the admin', 'memorial-plugin' ) )
			. '</ul>';
	}
	if ( function_exists( 'memorial_plugin_reviews_mail_schema' ) ) {
		$html .= '<p><strong>' . esc_html__( 'Email about new and deleted reviews', 'memorial-plugin' ) . '</strong></p><ul>'
			. $row( '{new}', __( 'how many new reviews', 'memorial-plugin' ) )
			. $row( '{gone}', __( 'how many reviews disappeared from the review site', 'memorial-plugin' ) )
			. $row( '{list}', __( 'the reviews one per line: new or deleted, author, rating, review site, date and the beginning of the text', 'memorial-plugin' ) )
			. $row( '{time}', __( 'date and time of the loading', 'memorial-plugin' ) )
			. $row( '{site}', __( 'address of the site', 'memorial-plugin' ) )
			. $row( '{link}', __( 'link to “Review list” in the admin', 'memorial-plugin' ) )
			. '</ul>';
	}
	return $html;
}
