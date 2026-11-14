<?php
/**
 * Яндекс.Метрика: счётчик + цели «Звонок» (phone_click) и «Письмо» (email_click).
 * Номер счётчика вводится в «Мемориал → Аналитика». К каждой цели передаётся, где нажали
 * (шапка, первый экран, карточка товара…) — это видно в отчёте «Параметры визитов».
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Metrika schema.
 */
function memorial_plugin_metrika_schema() {
	return array(
		'counter' => array(
			'type'   => 'group',
			'label'  => __( 'Counter', 'memorial-plugin' ),
			'fields' => array(
				'load'        => array(
					'type'    => 'select',
					'label'   => __( 'How Yandex Metrica loads', 'memorial-plugin' ),
					'options' => array(
						'consent' => __( 'After cookie consent (recommended): the counter starts only after the visitor clicks “Accept”', 'memorial-plugin' ),
						'always'  => __( 'Always: the counter loads on every page without waiting (deferred); the cookie notice is still shown', 'memorial-plugin' ),
						'off'     => __( 'Off: no Metrica code on the site — connect it through a tag manager if needed', 'memorial-plugin' ),
					),
					'help'    => __( '“After cookie consent” and “Always” also set how the cookie bar works. With “Off” the bar follows the setting below.', 'memorial-plugin' ),
				),
				'id'          => array(
					'type'  => 'number',
					'label' => __( 'Yandex Metrica counter number', 'memorial-plugin' ),
					'help'  => __( 'Digits only, for example 98765432. Empty — Metrica is off on the site.', 'memorial-plugin' ),
				),
				'webvisor'    => array(
					'type'  => 'checkbox',
					'label' => __( 'Webvisor — recording how visitors view the site', 'memorial-plugin' ),
				),
				'skip_admins' => array(
					'type'  => 'checkbox',
					'label' => __( 'Do not count visits of those logged into the admin (your own visits will not spoil the statistics)', 'memorial-plugin' ),
				),
				'clean_utm'   => array(
					'type'  => 'checkbox',
					'label' => __( 'Remove ad tags from the address bar (utm_…, yclid, gclid…): a visitor sees and copies a clean address. Metrica still gets the address with the tags — the sources in the reports are not lost', 'memorial-plugin' ),
				),
			),
		),
		'consent' => array(
			'type'   => 'group',
			'label'  => __( 'Cookie banner', 'memorial-plugin' ),
			'help'   => __( 'Links in the text: {cookies:text} — to the Cookie policy, {policy:text} — to the Personal data policy, {consent:text} — to the Consent (the pages are chosen in “Memorial → Documents”). Without the tags a link to the Cookie policy is added at the end automatically.', 'memorial-plugin' ),
			'fields' => array(
				'mode'        => array(
					'type'    => 'select',
					'label'   => __( 'How it works', 'memorial-plugin' ),
					'options' => array(
						'ask'    => __( 'Ask for consent: Metrica and the codes below start after “Accept” (the strict reading of 152-FZ)', 'memorial-plugin' ),
						'notice' => __( 'Only notify: “By continuing to use the site…” — Metrica works at once, the “Got it” button', 'memorial-plugin' ),
						'off'    => __( 'No banner: Metrica works at once', 'memorial-plugin' ),
					),
				),
				'text'        => array(
					'type'  => 'textarea',
					'label' => __( 'Banner text — “Ask for consent”', 'memorial-plugin' ),
					'rows'  => 3,
				),
				'text_notice' => array(
					'type'  => 'textarea',
					'label' => __( 'Banner text — “Only notify”', 'memorial-plugin' ),
					'rows'  => 3,
				),
				'code'        => array(
					'type'  => 'code',
					'label' => __( 'Other counters — start only after consent', 'memorial-plugin' ),
					'rows'  => 8,
					'help'  => __( 'Paste here the whole codes of the VK pixel, call tracking (Gudok) and so on — together with the <script> tags.', 'memorial-plugin' ),
				),
			),
		),
	);
}

/**
 * Metrika defaults.
 */
function memorial_plugin_metrika_defaults() {
	$old  = get_option( 'mem_metrika' ); // до 1.1: галочка «Спрашивать согласие».
	$mode = is_array( $old ) && isset( $old['consent']['on'] ) && ! $old['consent']['on'] ? 'off' : 'ask';
	return array(
		'counter' => array(
			'load'        => 'consent',
			'id'          => '',
			'webvisor'    => 1,
			'skip_admins' => 1,
			'clean_utm'   => 1,
		),
		'consent' => array(
			'mode'        => $mode,
			'code'        => '',
			'text'        => 'Мы используем cookie и Яндекс Метрику, чтобы понимать, как посетители пользуются сайтом. Нажимая «Принять», вы соглашаетесь на обработку этих данных в соответствии с {cookies:Политикой использования cookie}.',
			'text_notice' => 'Продолжая использовать сайт, вы соглашаетесь с обработкой персональных данных, собираемых посредством метрической программы «Яндекс Метрика», в соответствии с {cookies:Политикой использования cookie} и {policy:Политикой обработки персональных данных}.',
		),
	);
}

add_filter(
	'memorial_plugin_admin_pages',
	function ( $pages ) {
		$pages['mem_metrika'] = array(
			'slug'   => 'mem-metrika',
			'title'  => __( 'Analytics', 'memorial-plugin' ),
			'schema' => 'memorial_plugin_metrika_schema',
			'intro'  => ( '<b>' . esc_html__( 'How to set up call tracking:', 'memorial-plugin' ) . '</b></p><ol class="mem-steps">' )
				. ( '<li>' . esc_html__( 'Create a counter at', 'memorial-plugin' ) . ' <a href="https://metrika.yandex.ru/add" target="_blank" rel="noopener">metrika.yandex.ru</a> ' . esc_html__( 'and paste its number below. Save.', 'memorial-plugin' ) . '</li>' )
				. ( '<li>' . esc_html__( 'In Metrica open the counter →', 'memorial-plugin' ) . ' <b>' . esc_html__( 'Goals', 'memorial-plugin' ) . '</b> → <b>' . esc_html__( 'Add a goal', 'memorial-plugin' ) . '</b> ' . esc_html__( '→ type', 'memorial-plugin' ) . ' <b>' . esc_html__( '“JavaScript event”', 'memorial-plugin' ) . '</b>. ' )
				. ( esc_html__( 'Name “Call”, identifier', 'memorial-plugin' ) . ' <code>phone_click</code>' . esc_html__( '. Optionally more goals:', 'memorial-plugin' ) . ' <code>callback_sent</code> ' . esc_html__( '“Call request”,', 'memorial-plugin' ) . ' <code>max_click</code> ' . esc_html__( '“Going to MAX”,', 'memorial-plugin' ) . ' <code>email_click</code> ' . esc_html__( '“Email”.', 'memorial-plugin' ) . '</li>' )
				. ( '<li>' . esc_html__( 'In a day or two look at:', 'memorial-plugin' ) . ' <b>' . esc_html__( 'Reports → Sources → Summary', 'memorial-plugin' ) . '</b>' . esc_html__( ', choose the “Call” goal at the top — you will see where the callers came from (Yandex, Google, maps, ads).', 'memorial-plugin' ) . ' ' )
				. ( esc_html__( 'In', 'memorial-plugin' ) . ' <b>' . esc_html__( 'Reports → Content → Visit parameters', 'memorial-plugin' ) . '</b> ' . esc_html__( 'shows which button is clicked more often: the header, the first screen, a product card and so on.', 'memorial-plugin' ) . '</li>' )
				. ( '</ol><p class="description">' . esc_html__( 'The “Call” goal is sent when the number is tapped on a mobile phone; on a computer MAX or the request window opens instead of a call, they are counted by the goals', 'memorial-plugin' ) . ' <code>max_click</code> ' . esc_html__( 'and', 'memorial-plugin' ) . ' <code>callback_sent</code>' . esc_html__( '. If a person copied the number from the screen by hand, Metrica does not see such a call — exact counting of all calls needs call tracking.', 'memorial-plugin' ) ),
		);
		return $pages;
	}
);

/** Номер счётчика, если Метрику нужно выводить на этой странице */
/**
 * Как загружается Метрика: consent — после согласия, always — сразу, off — не подключается.
 *
 * @return string
 */
function memorial_plugin_metrika_load() {
	$l = memorial_plugin_settings( 'mem_metrika' )['counter']['load'] ?? 'consent';
	return in_array( $l, array( 'consent', 'always', 'off' ), true ) ? $l : 'consent';
}

/**
 * Metrika id.
 */
function memorial_plugin_metrika_id() {
	$m  = memorial_plugin_settings( 'mem_metrika' )['counter'];
	$id = memorial_plugin_metrika_load() === 'off' ? 0 : (int) ( $m['id'] ?? 0 );
	if ( ! $id || is_admin() || is_customize_preview() ) {
		return 0;
	}
	if ( ! empty( $m['skip_admins'] ) && current_user_can( 'edit_posts' ) ) {
		return 0;
	}
	return $id;
}

/**
 * Consent cfg.
 */
function memorial_plugin_consent_cfg() {
	return memorial_plugin_settings( 'mem_metrika' )['consent'] ?? array();
}

/** Режим плашки: ask — спрашивать согласие, notice — только уведомлять, off — без плашки.
 *  Режим загрузки Метрики главнее: «после согласия» — всегда спрашиваем, «всегда» — всегда уведомляем
 *  (плашка нужна и виджету отзывов); при «выключено» — как выбрано в настройке плашки. */
function memorial_plugin_consent_mode() {
	$load = memorial_plugin_metrika_load();
	if ( 'consent' === $load ) {
		return 'ask';
	}
	if ( 'always' === $load ) {
		return 'notice';
	}
	$m = memorial_plugin_consent_cfg()['mode'] ?? 'ask';
	return in_array( $m, array( 'ask', 'notice', 'off' ), true ) ? $m : 'ask';
}

/** Текст плашки со ссылками на документы */
function memorial_plugin_consent_text() {
	$c    = memorial_plugin_consent_cfg();
	$t    = (string) ( memorial_plugin_consent_mode() === 'notice' ? ( $c['text_notice'] ?? '' ) : ( $c['text'] ?? '' ) );
	$html = function_exists( 'memorial_plugin_legal_text' ) ? memorial_plugin_legal_text( $t ) : esc_html( preg_replace( '~\{\w+:([^}]+)\}~u', '$1', $t ) );
	$u    = ! preg_match( '~\{\w+:~', $t ) && function_exists( 'memorial_plugin_legal_url' ) ? memorial_plugin_legal_url( 'cookies' ) : '';
	if ( $u ) {
		$html .= ' <a href="' . esc_url( $u ) . ( '">' . esc_html__( 'Cookie policy', 'memorial-plugin' ) . '</a>' );
	}
	return $html;
}

/** Нужно ли что-то запускать на этой странице (счётчик или доп. коды) */
function memorial_plugin_tracking_on() {
	if ( is_admin() || is_customize_preview() ) {
		return false;
	}
	$m = memorial_plugin_settings( 'mem_metrika' )['counter'];
	if ( ! empty( $m['skip_admins'] ) && current_user_can( 'edit_posts' ) ) {
		return false;
	}
	$id = memorial_plugin_metrika_load() === 'off' ? 0 : (int) ( $m['id'] ?? 0 );
	return (bool) ( $id || trim( memorial_plugin_consent_cfg()['code'] ?? '' ) );
}

/** Показывать плашку согласия: всем посетителям, включая администратора (он должен её видеть),
 *  даже без счётчика — cookie ставят и сторонние скрипты (коллтрекинг, пиксели, виджеты). */
function memorial_plugin_banner_on() {
	return memorial_plugin_consent_mode() !== 'off' && ! is_admin() && ! is_customize_preview();
}

/*
Чистый адрес: метки рекламы убираются из адресной строки сразу при открытии страницы.
	Исходный адрес (с метками) запоминается в memOrigUrl — Метрика отправит просмотр именно с ним (см. load() ниже).
 */
add_action(
	'wp_head',
	function () {
		if ( is_admin() || empty( memorial_plugin_settings( 'mem_metrika' )['counter']['clean_utm'] ) ) {
			return;
		}
		wp_print_inline_script_tag(
			'(function(){try{var u=new URL(location.href),p=u.searchParams,k=[];p.forEach(function(v,n){if(/^utm_|^(yclid|gclid|fbclid|ysclid|msclkid|_openstat|roistat|etext)$/i.test(n))k.push(n)});'
			. 'if(!k.length)return;window.memOrigUrl=location.href;k.forEach(function(n){p.delete(n)});var q=p.toString();'
			. "history.replaceState(history.state,'',u.pathname+(q?'?'+q:'')+u.hash)}catch(e){}})();"
		);
	},
	1
);

/* Счётчик: заготовка ym() сразу (цели копятся), сам tag.js — только после согласия */
add_action(
	'wp_head',
	function () {
		if ( ! memorial_plugin_tracking_on() && ! memorial_plugin_banner_on() ) {
			return;
		}
		$id  = memorial_plugin_tracking_on() ? memorial_plugin_metrika_id() : 0; // администратору счётчик не запускаем.
		$cfg = array(
			'id'   => $id,
			'need' => memorial_plugin_consent_mode() === 'ask',   // ждать «Принять».
			'mode' => memorial_plugin_consent_mode(),
			'opts' => array(
				'clickmap'            => true,
				'trackLinks'          => true,
				'accurateTrackBounce' => true,
				'webvisor'            => ! empty( memorial_plugin_settings( 'mem_metrika' )['counter']['webvisor'] ),
			),
			'log'  => esc_url_raw( rest_url( 'mem/v1/consent' ) ),
		);
		$js  = 'window.memTrack=' . wp_json_encode( $cfg ) . ';';
		if ( $id ) {
			$js .= 'window.memYm=' . (int) $id . ';window.ym=window.ym||function(){(ym.a=ym.a||[]).push(arguments)};ym.l=1*new Date();';
		}
		wp_print_inline_script_tag( $js );
	},
	2
);

/* Загрузчик: Метрика + доп. коды; плашка согласия (assets/js/consent.js, в подвале) */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! memorial_plugin_tracking_on() && ! memorial_plugin_banner_on() ) {
			return;
		}
		$js = memorial_plugin_asset( '/assets/js/consent.js' );
		wp_enqueue_script( 'memorial-plugin-consent', MEMORIAL_PLUGIN_URL . $js, array(), (string) filemtime( MEMORIAL_PLUGIN_DIR . $js ), array( 'in_footer' => true ) );
	}
);

add_action(
	'wp_footer',
	function () {
		if ( ! memorial_plugin_tracking_on() && ! memorial_plugin_banner_on() ) {
			return;
		}
		$c = memorial_plugin_consent_cfg();
		if ( memorial_plugin_tracking_on() && trim( $c['code'] ?? '' ) ) {
			echo '<template id="mem-after-consent">' . $c['code'] . '</template>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- counter code, saved whole only by a user with unfiltered_html (core/settings.php).
		}
		// плашка: в блоковой теме — блок memorial-plugin/cookie-banner в подвале (Block Hooks), в классической — здесь.
		if ( ! wp_is_block_theme() ) {
			echo memorial_plugin_consent_banner_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана с экранированием.
		}
	},
	5 // before the footer scripts (consent.js reads the template and the banner).
);

/** Плашка о cookie (один раз на странице; пусто, если плашка не нужна) */
function memorial_plugin_consent_banner_html() {
	static $done = false;
	if ( $done || ! memorial_plugin_banner_on() ) {
		return '';
	}
	$done   = true;
	$notice = memorial_plugin_consent_mode() === 'notice';
	ob_start();
	?>
<div class="consent<?php echo $notice ? ' consent--notice' : ''; ?>" id="consent" role="region" aria-label="Cookie" hidden>
	<p><?php echo memorial_plugin_consent_text(); ?></p>
	<div class="consent__btns">
	<?php if ( $notice ) : ?>
	<button class="btn btn--gold btn--sm" type="button" data-consent="ok"><?php esc_html_e( 'Got it', 'memorial-plugin' ); ?></button>
	<?php else : ?>
	<button class="btn btn--gold btn--sm" type="button" data-consent="yes"><?php esc_html_e( 'Accept', 'memorial-plugin' ); ?></button>
	<button class="btn btn--line btn--sm" type="button" data-consent="no"><?php esc_html_e( 'Decline', 'memorial-plugin' ); ?></button>
	<?php endif; ?>
	</div>
</div>
	<?php
	return (string) ob_get_clean();
}

/* Журнал согласий: сколько раз в день нажали «Принять» / «Отклонить» (без персональных данных) */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'mem/v1',
			'/consent',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $r ) {
					// один голос в сутки с адреса (хранится только хэш адреса, на сутки) — журнал нельзя «накрутить».
					$key = 'mem_cl_' . substr( md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) . wp_salt( 'nonce' ) ), 0, 16 );
					if ( get_transient( $key ) ) {
						return array( 'ok' => true );
					}
					set_transient( $key, 1, DAY_IN_SECONDS );
					$v                 = $r->get_param( 'choice' ) === 'yes' ? 'yes' : 'no';
					$log               = get_option( 'mem_consent_log', array() );
					$day               = wp_date( 'Y-m-d' );
					$log[ $day ][ $v ] = ( $log[ $day ][ $v ] ?? 0 ) + 1;
					if ( count( $log ) > 400 ) {
						$log = array_slice( $log, -400, null, true );
					}
					update_option( 'mem_consent_log', $log, false );
					return array( 'ok' => true );
				},
			)
		);
	}
);
