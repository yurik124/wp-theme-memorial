<?php
/**
 * Are the prices up to date? (owner, 26.11.2026)
 *
 *  - Prices: a month after the last change or confirmation — an email and a notice in the admin
 *    «Цены не проверялись с …» with “Prices are up to date”, “Open the prices”, “Remind in 3 days”.
 *    “Prices are up to date” moves the date «Цены обновлены» of the site (mem_prices_changed) and puts today
 *    into “Updated” of every separate price (except the benefits).
 *  - Benefits (codes posobie and vyplata_svo of «Цены → Отдельные цены») — on their own: once a year,
 *    from 1 February (the burial benefit is indexed on 1 February). Their changes and confirmation do not
 *    move the date of the prices; their own date — mem_benefits_checked.
 *  - Emails — «Настройки → Почта» (mem_modules_page['pmail'] and ['bmail']); repeated every 7 days
 *    until confirmed. The email has a link to the admin, the confirmation is made there (not by a link).
 *  - The check — WP-Cron once a day (memorial_plugin_price_check); the state — memorial_plugin_price_check.
 *  - Ability memorial-plugin/confirm-prices and the commands of the palette (src/commands).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Codes of the benefits among the separate prices.
 *
 * @return string[]
 */
function memorial_plugin_benefit_codes() {
	return array( 'posobie', 'vyplata_svo' );
}

/**
 * Is this row of the separate prices a benefit?
 *
 * @param array $row Row of mem_prices['other']['items'].
 * @return bool
 */
function memorial_plugin_price_row_is_benefit( $row ) {
	return in_array( memorial_plugin_value_key( (string) ( $row['code'] ?? '' ) ), memorial_plugin_benefit_codes(), true );
}

/**
 * Hash of the benefit sums of mem_prices.
 *
 * @param mixed $value mem_prices.
 * @return string
 */
function memorial_plugin_benefits_fingerprint( $value ) {
	$out = array();
	foreach ( (array) ( is_array( $value ) ? ( $value['other']['items'] ?? array() ) : array() ) as $row ) {
		if ( is_array( $row ) && memorial_plugin_price_row_is_benefit( $row ) ) {
			$out[] = array( memorial_plugin_value_key( (string) $row['code'] ), (string) ( $row['price'] ?? '' ) );
		}
	}
	return md5( (string) wp_json_encode( $out ) );
}

/**
 * A benefit sum changed on «Цены» — that is a check of the benefits too.
 *
 * @param mixed $old   Old value.
 * @param mixed $value New value.
 */
function memorial_plugin_benefits_changed( $old, $value ) {
	if ( memorial_plugin_benefits_fingerprint( $old ) !== memorial_plugin_benefits_fingerprint( $value ) ) {
		update_option( 'mem_benefits_checked', time(), false );
	}
}
add_action( 'update_option_mem_prices', 'memorial_plugin_benefits_changed', 10, 2 );

/**
 * When the benefits were last checked: the confirmation, else the latest “Updated” of their rows.
 *
 * @return int Timestamp or 0.
 */
function memorial_plugin_benefits_checked_at() {
	$ts = (int) get_option( 'mem_benefits_checked', 0 );
	if ( $ts > 0 ) {
		return $ts;
	}
	foreach ( (array) ( memorial_plugin_prices( 'other' )['items'] ?? array() ) as $row ) {
		if ( is_array( $row ) && memorial_plugin_price_row_is_benefit( $row ) ) {
			$d  = DateTime::createFromFormat( '!d.m.Y', trim( (string) ( $row['updated'] ?? '' ) ), wp_timezone() );
			$ts = $d ? max( $ts, $d->getTimestamp() ) : $ts;
		}
	}
	return $ts;
}

/**
 * The last 1 February up to now (the benefits are checked once a year from this day).
 *
 * @param int $now Now.
 * @return int Timestamp of 1 February 00:00 in the site’s time zone.
 */
function memorial_plugin_benefits_due_from( $now ) {
	$tz   = wp_timezone();
	$year = (int) wp_date( 'Y', $now, $tz );
	$feb  = new DateTime( $year . '-02-01 00:00:00', $tz );
	if ( $feb->getTimestamp() > $now ) {
		$feb = new DateTime( ( $year - 1 ) . '-02-01 00:00:00', $tz );
	}
	return $feb->getTimestamp();
}

/**
 * What to check now.
 *
 * @param int|null $now Now (tests).
 * @return array{prices:bool,benefits:bool,prices_at:int,benefits_at:int}
 */
function memorial_plugin_price_check_due( $now = null ) {
	$now      = $now ?? time();
	$prices   = memorial_plugin_prices_updated_at();
	$benefits = memorial_plugin_benefits_checked_at();
	return array(
		'prices'      => $prices <= 0 || $now >= strtotime( '+1 month', $prices ),
		// Never checked (a new site) — no reminder until the next 1 February.
		'benefits'    => $benefits > 0 && $benefits < memorial_plugin_benefits_due_from( $now ),
		'prices_at'   => $prices,
		'benefits_at' => $benefits,
	);
}

/**
 * Confirm: the prices (or the benefits) are up to date — today’s date in “Updated” of the rows and on the site.
 *
 * @param string $what prices|benefits.
 * @return array{what:string,date:string}
 */
function memorial_plugin_price_confirm( $what ) {
	$what  = 'benefits' === $what ? 'benefits' : 'prices';
	$saved = get_option( 'mem_prices' );
	if ( is_array( $saved ) && ! empty( $saved['other']['items'] ) && is_array( $saved['other']['items'] ) ) {
		$today = wp_date( 'd.m.Y' );
		foreach ( $saved['other']['items'] as &$row ) {
			if ( ! is_array( $row ) || ! in_array( $row['what'] ?? '', array( '', 'custom' ), true ) || '' === trim( (string) ( $row['price'] ?? '' ) ) ) {
				continue;
			}
			if ( memorial_plugin_price_row_is_benefit( $row ) === ( 'benefits' === $what ) ) {
				$row['updated'] = $today;
			}
		}
		unset( $row );
		update_option( 'mem_prices', $saved );
	}
	if ( 'benefits' === $what ) {
		update_option( 'mem_benefits_checked', time(), false );
	} else {
		memorial_plugin_prices_touch();
	}
	$state                    = (array) get_option( 'memorial_plugin_price_check', array() );
	$state[ $what . '_mail' ] = 0;
	$state[ $what . '_by' ]   = get_current_user_id();
	update_option( 'memorial_plugin_price_check', $state, false );
	if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
		memorial_plugin_page_cache_clear();
	}
	return array(
		'what' => $what,
		'date' => wp_date( 'd.m.Y' ),
	);
}

/* ---------- Письма ---------- */

/**
 * Fields of an email on the «Почта» tab.
 *
 * @param string $what prices|benefits.
 * @return array
 */
function memorial_plugin_price_mail_schema( $what ) {
	$benefits = 'benefits' === $what;
	return array(
		'type'   => 'group',
		'tab'    => _x( 'Mail', 'settings tab', 'memorial-plugin' ),
		'label'  => $benefits ? __( 'Reminder to check the benefits', 'memorial-plugin' ) : __( 'Reminder to check the prices', 'memorial-plugin' ),
		'help'   => $benefits
			? __( 'Comes every year from 1 February, every 7 days until the sums are confirmed in the admin (“The sums are up to date”).', 'memorial-plugin' )
			: __( 'Comes a month after the prices were changed or confirmed, every 7 days until they are confirmed in the admin (“Prices are up to date”).', 'memorial-plugin' ),
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
				'rows'  => 7,
				'label' => __( 'Text of the email', 'memorial-plugin' ),
			),
			'test'    => array(
				'type'  => 'button',
				'label' => __( 'Send a test email', 'memorial-plugin' ),
				'path'  => '/memorial-plugin/v1/price-mail-test/' . ( $benefits ? 'benefits' : 'prices' ),
				'help'  => __( 'Sends the email with these settings (also unsaved) to the addresses above.', 'memorial-plugin' ),
			),
		),
	);
}

/**
 * Defaults of an email.
 *
 * @param string $what prices|benefits.
 * @return array
 */
function memorial_plugin_price_mail_defaults( $what ) {
	if ( 'benefits' === $what ) {
		return array(
			'to'      => '',
			'subject' => __( 'Check the benefit sums on {site}', 'memorial-plugin' ),
			'body'    => __( 'The burial benefit and the payments change every year from 1 February. Check the sums on the site:', 'memorial-plugin' ) . "\n\n{sums}\n\n"
				. __( 'Last checked: {date}.', 'memorial-plugin' ) . "\n\n"
				. __( 'Change them or confirm that they are up to date:', 'memorial-plugin' ) . ' {link}',
		);
	}
	return array(
		'to'      => '',
		'subject' => __( 'Check the prices on {site}', 'memorial-plugin' ),
		'body'    => __( 'The prices on the site were last changed or checked on {date}. Please check that they are up to date.', 'memorial-plugin' ) . "\n\n"
			. __( 'Change them or confirm that they are up to date:', 'memorial-plugin' ) . ' {link}',
	);
}

/**
 * Saved settings over the defaults.
 *
 * @param string $what prices|benefits.
 * @return array
 */
function memorial_plugin_price_mail_settings( $what ) {
	$saved = get_option( 'mem_modules_page' );
	$key   = 'benefits' === $what ? 'bmail' : 'pmail';
	$saved = is_array( $saved ) && is_array( $saved[ $key ] ?? null ) ? $saved[ $key ] : array();
	$out   = memorial_plugin_price_mail_defaults( $what );
	foreach ( $saved as $k => $v ) {
		if ( is_string( $v ) && ( '' !== trim( $v ) || 'to' === $k ) ) {
			$out[ $k ] = $v;
		}
	}
	return $out;
}

/**
 * The benefit sums, one per line: “— Name: sum”.
 *
 * @return string
 */
function memorial_plugin_benefits_lines() {
	$lines = array();
	foreach ( (array) ( memorial_plugin_prices( 'other' )['items'] ?? array() ) as $row ) {
		if ( is_array( $row ) && memorial_plugin_price_row_is_benefit( $row ) ) {
			$code    = memorial_plugin_value_key( (string) $row['code'] );
			$sum     = function_exists( 'memorial_plugin_price_value' ) ? (string) memorial_plugin_price_value( $code ) : (string) ( $row['price'] ?? '' );
			$lines[] = '— ' . trim( (string) ( $row['name'] ?? $code ) ) . ': ' . ( '' !== $sum ? $sum : '—' );
		}
	}
	return implode( "\n", $lines );
}

/**
 * Send a reminder.
 *
 * @param string $what     prices|benefits.
 * @param array  $settings Settings (memorial_plugin_price_mail_settings()).
 * @return array{ok:bool,error:string,to:string[]}
 */
function memorial_plugin_price_mail_send( $what, $settings ) {
	$due    = memorial_plugin_price_check_due();
	$at     = 'benefits' === $what ? $due['benefits_at'] : $due['prices_at'];
	$vars   = array(
		'{date}' => $at > 0 ? wp_date( 'd.m.Y', $at ) : '—',
		'{sums}' => memorial_plugin_benefits_lines(),
		'{site}' => home_url( '/' ),
		'{link}' => admin_url( 'admin.php?page=mem-prices' ),
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
 * Once a day: send the reminders that are due (again every 7 days until confirmed).
 *
 * @param int|null $now Now (tests).
 */
function memorial_plugin_price_check_run( $now = null ) {
	$now   = $now ?? time();
	$due   = memorial_plugin_price_check_due( $now );
	$state = (array) get_option( 'memorial_plugin_price_check', array() );
	foreach ( array( 'prices', 'benefits' ) as $what ) {
		if ( ! $due[ $what ] || $now - (int) ( $state[ $what . '_mail' ] ?? 0 ) < 7 * DAY_IN_SECONDS ) {
			continue;
		}
		$res                       = memorial_plugin_price_mail_send( $what, memorial_plugin_price_mail_settings( $what ) );
		$state[ $what . '_mail' ]  = $now;
		$state[ $what . '_error' ] = $res['ok'] ? '' : $res['error'];
	}
	update_option( 'memorial_plugin_price_check', $state, false );
}
add_action( 'memorial_plugin_price_check', 'memorial_plugin_price_check_run' );

add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'memorial_plugin_price_check' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'memorial_plugin_price_check' );
		}
	}
);

/* Пробное письмо: настройки с экрана (и несохранённые). */
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'memorial-plugin/v1',
			'/price-mail-test/(?P<what>prices|benefits)',
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
					$what     = (string) $request['what'];
					$in       = (array) $request['values'];
					$settings = memorial_plugin_price_mail_defaults( $what );
					foreach ( array_keys( $settings ) as $k ) {
						if ( isset( $in[ $k ] ) && is_string( $in[ $k ] ) && ( '' !== trim( $in[ $k ] ) || 'to' === $k ) ) {
							$settings[ $k ] = 'body' === $k ? sanitize_textarea_field( $in[ $k ] ) : sanitize_text_field( $in[ $k ] );
						}
					}
					return memorial_plugin_mail_test_answer( memorial_plugin_price_mail_send( $what, $settings ) );
				},
			)
		);
	}
);

/* ---------- Окно в админке ---------- */

/**
 * May the user confirm the prices? The same right as the «Цены» screen.
 *
 * @return bool
 */
function memorial_plugin_price_check_can() {
	return function_exists( 'memorial_plugin_can_screen' ) ? memorial_plugin_can_screen( 'mem_prices' ) : current_user_can( 'manage_options' );
}

/**
 * What the current user should see now (snoozed reminders are skipped).
 *
 * @return string[] prices / benefits.
 */
function memorial_plugin_price_check_shown() {
	if ( ! memorial_plugin_price_check_can() ) {
		return array();
	}
	$due    = memorial_plugin_price_check_due();
	$snooze = (array) get_user_meta( get_current_user_id(), 'memorial_plugin_price_snooze', true );
	return array_values( array_filter( array( 'prices', 'benefits' ), static fn( $w ) => $due[ $w ] && time() >= (int) ( $snooze[ $w ] ?? 0 ) ) );
}

add_action(
	'admin_notices',
	static function () {
		$done = isset( $_GET['mem_price_ok'] ) ? sanitize_key( wp_unslash( $_GET['mem_price_ok'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only the message after the form below.
		if ( 'prices' === $done || 'benefits' === $done ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( 'benefits' === $done ? __( 'Thank you: the benefit sums are marked as up to date.', 'memorial-plugin' ) : __( 'Thank you: the prices are marked as up to date, the date on the site is today.', 'memorial-plugin' ) ) . '</p></div>';
		}
		$shown = memorial_plugin_price_check_shown();
		if ( ! $shown ) {
			return;
		}
		$due  = memorial_plugin_price_check_due();
		$form = static function ( $what, $act, $label, $primary ) {
			return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
				. '<input type="hidden" name="action" value="memorial_plugin_price_check">'
				. '<input type="hidden" name="what" value="' . esc_attr( $what ) . '">'
				. '<input type="hidden" name="do" value="' . esc_attr( $act ) . '">'
				. wp_nonce_field( 'memorial_plugin_price_check', '_wpnonce', true, false )
				. '<button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form> ';
		};
		foreach ( $shown as $what ) {
			$at = 'benefits' === $what ? $due['benefits_at'] : $due['prices_at'];
			if ( 'benefits' === $what ) {
				$text = sprintf(
					/* translators: %s: date. */
					__( 'Check the benefit sums: from 1 February the burial benefit changes. Last checked: %s.', 'memorial-plugin' ),
					$at > 0 ? wp_date( 'd.m.Y', $at ) : '—'
				);
				$sums = memorial_plugin_benefits_lines();
			} else {
				$text = $at > 0
					/* translators: %s: date. */
					? sprintf( __( 'The prices on the site have not been checked since %s. Are they up to date?', 'memorial-plugin' ), wp_date( 'd.m.Y', $at ) )
					: __( 'Are the prices on the site up to date?', 'memorial-plugin' );
				$sums = '';
			}
			echo '<div class="notice notice-warning mem-price-check"><p><strong>' . esc_html( $text ) . '</strong></p>'
				. ( '' !== $sums ? '<p>' . nl2br( esc_html( $sums ) ) . '</p>' : '' )
				. '<p>'
				. $form( $what, 'ok', 'benefits' === $what ? __( 'The sums are up to date', 'memorial-plugin' ) : __( 'Prices are up to date', 'memorial-plugin' ), true ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the form above, escaped.
				. '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=mem-prices' ) ) . '">' . esc_html( 'benefits' === $what ? __( 'Change the sums', 'memorial-plugin' ) : __( 'Open the prices', 'memorial-plugin' ) ) . '</a> '
				. $form( $what, 'later', __( 'Remind in 3 days', 'memorial-plugin' ), false ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the form above, escaped.
				. '</p></div>';
		}
	}
);

add_action(
	'admin_post_memorial_plugin_price_check',
	static function () {
		check_admin_referer( 'memorial_plugin_price_check' );
		if ( ! memorial_plugin_price_check_can() ) {
			wp_die( esc_html__( 'You may not change the prices.', 'memorial-plugin' ), 403 );
		}
		$what = 'benefits' === sanitize_key( wp_unslash( $_POST['what'] ?? '' ) ) ? 'benefits' : 'prices';
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=mem-prices' );
		if ( 'later' === sanitize_key( wp_unslash( $_POST['do'] ?? '' ) ) ) {
			$snooze          = (array) get_user_meta( get_current_user_id(), 'memorial_plugin_price_snooze', true );
			$snooze[ $what ] = time() + 3 * DAY_IN_SECONDS;
			update_user_meta( get_current_user_id(), 'memorial_plugin_price_snooze', $snooze );
			wp_safe_redirect( remove_query_arg( 'mem_price_ok', $back ) );
			exit;
		}
		memorial_plugin_price_confirm( $what );
		wp_safe_redirect( add_query_arg( 'mem_price_ok', $what, $back ) );
		exit;
	}
);

/* ---------- Способность и палитра команд ---------- */

add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'memorial-plugin/confirm-prices',
			array(
				'label'               => __( 'Confirm the prices are up to date', 'memorial-plugin' ),
				'description'         => __( 'Marks the prices (or the benefit sums) as checked today: the date “Prices updated” on the site and “Updated” of the separate prices.', 'memorial-plugin' ),
				'category'            => 'memorial',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'what' => array(
							'type'    => 'string',
							'enum'    => array( 'prices', 'benefits' ),
							'default' => 'prices',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'what' => array( 'type' => 'string' ),
						'date' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => static fn() => memorial_plugin_price_check_can(),
				'execute_callback'    => static fn( $input = array() ) => memorial_plugin_price_confirm( (string) ( $input['what'] ?? 'prices' ) ),
				'meta'                => array(
					'annotations'  => array( 'idempotent' => true ),
					'public'       => false,
					'show_in_rest' => true,
				),
			)
		);
	}
);
