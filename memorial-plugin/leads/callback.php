<?php
/**
 * Кнопка «Позвонить»: на телефоне — обычный звонок, на компьютере — MAX и окно «Перезвоните мне».
 * Заявки: письмо на почту + запись в «Мемориал → Заявки» (не потеряются, даже если почта не дошла).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Заявки в админке ---------- */
add_action(
	'init',
	function () {
		register_post_type(
			'mem_lead',
			array(
				'labels'          => array(
					'name'          => __( 'Call requests', 'memorial-plugin' ),
					'singular_name' => __( 'Request', 'memorial-plugin' ),
					'menu_name'     => __( 'Requests', 'memorial-plugin' ),
					'all_items'     => __( 'Requests', 'memorial-plugin' ),
					'edit_item'     => __( 'Request', 'memorial-plugin' ),
					'not_found'     => __( 'No requests yet', 'memorial-plugin' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'mem-contacts',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

/* Список заявок — DataViews (admin/collections.php, «Мемориал → Заявки»). */

/* ---------- Приём заявки ---------- */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'mem/v1',
			'/callback',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'memorial_plugin_callback_receive',
			)
		);
	}
);

/**
 * Callback receive.
 *
 * @param WP_REST_Request $r R.
 */
function memorial_plugin_callback_receive( WP_REST_Request $r ) {
	// защита от ботов: скрытое поле и слишком быстрая отправка.
	if ( $r->get_param( 'website' ) !== '' && $r->get_param( 'website' ) !== null ) {
		return array( 'ok' => true );
	}
	if ( (int) $r->get_param( 'elapsed' ) < 2 ) {
		return new WP_Error( 'fast', __( 'Please try again.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}

	$phone  = sanitize_text_field( (string) $r->get_param( 'phone' ) );
	$digits = preg_replace( '/\D/', '', $phone );
	if ( strlen( $digits ) < 10 || strlen( $digits ) > 15 ) {
		return new WP_Error( 'phone', __( 'Check the phone number.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}
	if ( ! $r->get_param( 'consent' ) ) {
		return new WP_Error( 'consent', __( 'Consent to the data processing is needed.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}

	$ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'mem_cb_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		return new WP_Error( 'limit', __( 'Too many requests. Please call us.', 'memorial-plugin' ), array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, 15 * MINUTE_IN_SECONDS );

	$name = mb_substr( sanitize_text_field( (string) $r->get_param( 'name' ) ), 0, 80 );
	$page = esc_url_raw( (string) $r->get_param( 'page' ) );
	if ( $page && wp_parse_url( $page, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$page = '';
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'mem_lead',
			'post_status' => 'publish',
			'post_title'  => $phone,
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_name', $name );
		update_post_meta( $id, '_page', $page );
		// IP-адрес не сохраняется (минимум персональных данных): он нужен только для ограничения частоты выше.
	}

	// Email (template and addresses — «Мемориал → Настройки → Почта», leads/lead-mail.php).
	if ( function_exists( 'memorial_plugin_lead_mail' ) ) {
		memorial_plugin_lead_mail(
			$id && ! is_wp_error( $id ) ? (int) $id : 0,
			array(
				'phone' => $phone,
				'name'  => $name,
				'page'  => $page,
			)
		);
	}

	return array( 'ok' => true );
}

/*
---------- Окно на сайте ----------
	Блоковая тема: блок «Перезвоните мне» (memorial-plugin/callback-dialog) встаёт в подвал сам (Block Hooks,
	blocks/floating/floating.php). Классическая тема — окно печатается в wp_footer, как раньше.
 */
add_action(
	'wp_footer',
	function () {
		if ( ! wp_is_block_theme() ) {
			echo memorial_plugin_callback_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана с экранированием.
		}},
	5
);

/** Окно «Перезвоните мне» с формой (один раз на странице) */
function memorial_plugin_callback_html() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	ob_start();
	$c     = memorial_plugin_contacts()['call'] ?? array();
	$msg   = memorial_plugin_messengers();
	$cfg   = array(
		'max'    => $msg['max'] ?? '',
		'maxApp' => trim( $c['max_app'] ?? '' ),
		'api'    => esc_url_raw( rest_url( 'mem/v1/callback' ) ),
	);
	$names = array(
		'max'      => 'MAX',
		'telegram' => 'Telegram',
		'whatsapp' => 'WhatsApp',
	);
	?>
<dialog class="cb" id="callback" aria-labelledby="cb-title">
	<button class="cb__x" type="button" data-cb-close aria-label="<?php esc_attr_e( 'Close', 'memorial-plugin' ); ?>">×</button>
	<div class="cb__body">
	<p class="cb__status" data-cb-status hidden></p>
	<h2 id="cb-title"><?php echo esc_html( $c['title'] ?? __( 'We will call you back', 'memorial-plugin' ) ); ?></h2>
	<p class="cb__note"><?php echo esc_html( $c['note'] ?? '' ); ?></p>
	<form class="cb__form" novalidate>
		<label class="cb__f"><span><?php esc_html_e( 'Phone', 'memorial-plugin' ); ?></span>
		<input type="tel" name="phone" required autocomplete="tel" inputmode="tel" placeholder="+7 (___) ___-__-__"></label>
		<label class="cb__f"><span><?php esc_html_e( 'How should we address you', 'memorial-plugin' ); ?> <small><?php esc_html_e( '(optional)', 'memorial-plugin' ); ?></small></span>
		<input type="text" name="name" autocomplete="name" maxlength="80"></label>
		<label class="cb__hp" aria-hidden="true"><?php esc_html_e( 'Do not fill in', 'memorial-plugin' ); ?><input type="text" name="website" tabindex="-1" autocomplete="off"></label>
		<label class="cb__consent"><input type="checkbox" name="consent" value="1" required>
		<span>
		<?php
			printf(
				/* translators: 1: link “consent to personal data processing”, 2: link “the personal data processing policy” */
				esc_html__( 'I give my %1$s in accordance with %2$s', 'memorial-plugin' ),
				memorial_plugin_legal_link( 'consent', __( 'consent to personal data processing', 'memorial-plugin' ), ' target="_blank"' ), // phpcs:ignore WordPress.Security.EscapeOutput -- the link is escaped.
				memorial_plugin_legal_link( 'policy', __( 'the personal data processing policy', 'memorial-plugin' ), ' target="_blank"' ) // phpcs:ignore WordPress.Security.EscapeOutput -- the link is escaped.
			);
		?>
		</span></label>
		<p class="cb__err" role="alert" hidden></p>
		<button class="btn btn--gold btn--lg cb__submit" type="submit"><?php esc_html_e( 'Waiting for the call', 'memorial-plugin' ); ?></button>
	</form>
	<div class="cb__done" hidden>
		<p class="cb__ok"><?php esc_html_e( 'Thank you! The request is accepted — we will call you back soon.', 'memorial-plugin' ); ?></p>
	</div>
	<div class="cb__alt">
		<?php
		if ( memorial_plugin_opt( 'phone' ) ) :
			?>
			<p><?php esc_html_e( 'Or call:', 'memorial-plugin' ); ?> <b class="nowrap"><?php echo esc_html( memorial_plugin_opt( 'phone' ) ); ?></b></p><?php endif; ?>
		<?php if ( $msg ) : ?>
		<p class="cb__msg">
			<?php foreach ( $msg as $k => $url ) : ?>
			<a class="cb__m cb__m--<?php echo esc_attr( $k ); ?>" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( sprintf( /* translators: %s: messenger */ __( 'Write in %s', 'memorial-plugin' ), $names[ $k ] ) ); ?></a>
			<?php endforeach; ?>
		</p>
		<?php endif; ?>
	</div>
	</div>
</dialog>
	<?php wp_print_inline_script_tag( 'window.memCall=' . wp_json_encode( $cfg ) . ';' ); ?>
	<?php
	return (string) ob_get_clean();
}

/* Разовая чистка (2.0): IP-адреса из старых заявок удаляются — они больше не нужны и не хранятся */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'memorial_plugin_lead_ip_removed' ) ) {
			return;
		}
		foreach ( get_posts(
			array(
				'post_type'      => 'mem_lead',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_ip',
			)
		) as $id ) {
			delete_post_meta( $id, '_ip' );
		}
		update_option( 'memorial_plugin_lead_ip_removed', time(), false );
	}
);
