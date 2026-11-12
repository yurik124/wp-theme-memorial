<?php
/**
 * «Мемориал → Документы»: какие страницы сайта — Политика обработки ПД, Согласие на обработку ПД, Политика cookie.
 * Где какая используется (memorial_plugin_legal_link):
 *   — окно «Перезвоните мне»: Согласие (галочка) + Политика обработки ПД;
 *   — плашка cookie: Политика cookie (и ссылки, указанные в тексте плашки);
 *   — подвал (тема): Политика обработки ПД и Политика cookie.
 * Политика обработки ПД заодно становится «Страницей политики конфиденциальности» WordPress (её берут другие плагины).
 * Кнопка «Создать страницы» делает черновики с шаблонами текстов (legal/templates.php) — реквизиты в них подставляются
 * шорткодами из «Мемориал → Контакты» и сами обновляются.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/templates.php';

/** Документы: ключ → [название, для чего] */
function memorial_plugin_legal_docs() {
	return array(
		'policy'  => array( __( 'Personal data processing policy', 'memorial-plugin' ), __( 'the link in the footer and in the “Call me back” window; it is also the WordPress privacy policy page', 'memorial-plugin' ) ),
		'consent' => array( __( 'Consent to personal data processing', 'memorial-plugin' ), __( 'the link at the consent tick in the “Call me back” window', 'memorial-plugin' ) ),
		'cookies' => array( __( 'Cookie policy', 'memorial-plugin' ), __( 'the link in the cookie banner and in the footer', 'memorial-plugin' ) ),
	);
}

/**
 * Legal page options.
 */
function memorial_plugin_legal_page_options() {
	$o = array( '' => __( '— not chosen —', 'memorial-plugin' ) );
	foreach ( get_pages(
		array(
			'post_status' => 'publish,draft,private',
			'sort_column' => 'post_title',
		)
	) as $p ) {
		$o[ (string) $p->ID ] = $p->post_title . ( 'publish' !== $p->post_status ? __( ' (draft)', 'memorial-plugin' ) : '' );
	}
	return $o;
}

/**
 * Legal schema.
 */
function memorial_plugin_legal_schema() {
	$f = array();
	foreach ( memorial_plugin_legal_docs() as $k => [$title, $use] ) {
		$f[ $k ] = array(
			'type'    => 'select',
			'label'   => $title,
			'options' => memorial_plugin_legal_page_options(),
			'help'    => __( 'Where it is used: ', 'memorial-plugin' ) . $use . '.',
		);
	}
	return array(
		'pages' => array(
			'type'   => 'group',
			'label'  => __( 'Document pages', 'memorial-plugin' ),
			'fields' => $f,
		),
	);
}

/**
 * Legal defaults.
 */
function memorial_plugin_legal_defaults() {
	$pp = (int) get_option( 'wp_page_for_privacy_policy' );
	return array(
		'pages' => array(
			'policy'  => $pp && get_post( $pp ) ? (string) $pp : '',
			'consent' => '',
			'cookies' => '',
		),
	);
}

/** ID страницы документа (0 — не выбрана)
 *
 * @param string $key Key.
 */
function memorial_plugin_legal_page( $key ) {
	return (int) ( memorial_plugin_settings( 'mem_legal' )['pages'][ $key ] ?? 0 );
}

/** Адрес опубликованной страницы документа или ''
 *
 * @param string $key Key.
 */
function memorial_plugin_legal_url( $key ) {
	$id = memorial_plugin_legal_page( $key );
	if ( $id && get_post_status( $id ) === 'publish' ) {
		return get_permalink( $id );
	}
	if ( 'policy' === $key ) {
		return get_privacy_policy_url(); // запасной вариант — политика из настроек WordPress.
	}
	return '';
}

/** Ссылка на документ: <a>текст</a>; нет страницы — просто текст
 *
 * @param string $key Key.
 * @param string $text Text.
 * @param string $attrs Block attributes.
 */
function memorial_plugin_legal_link( $key, $text = '', $attrs = '' ) {
	$text = '' !== $text ? $text : memorial_plugin_legal_docs()[ $key ][0];
	$u    = memorial_plugin_legal_url( $key );
	return $u ? '<a href="' . esc_url( $u ) . '"' . $attrs . '>' . esc_html( $text ) . '</a>' : esc_html( $text );
}

/** Текст с метками {policy:…}, {consent:…}, {cookies:…} → со ссылками на документы
 *
 * @param string $text Text.
 */
function memorial_plugin_legal_text( $text ) {
	return preg_replace_callback( '~\{(policy|consent|cookies):([^}]+)\}~u', fn( $m ) => memorial_plugin_legal_link( $m[1], trim( $m[2] ) ), esc_html( $text ) );
}

/* Политика обработки ПД — она же страница политики конфиденциальности WordPress */
add_action(
	'update_option_mem_legal',
	function ( $old, $new ) {
		$id = (int) ( $new['pages']['policy'] ?? 0 );
		if ( $id && get_post_status( $id ) === 'publish' ) {
			update_option( 'wp_page_for_privacy_policy', $id );
		}
	},
	10,
	2
);
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		// опубликовали черновик политики.
		if ( 'publish' === $new && 'page' === $post->post_type && memorial_plugin_legal_page( 'policy' ) === (int) $post->ID ) {
			update_option( 'wp_page_for_privacy_policy', (int) $post->ID );
		}
	},
	10,
	3
);

/* ---------- Страница «Мемориал → Документы» ---------- */
add_filter(
	'memorial_plugin_admin_pages',
	function ( $pages ) {
		$rows = '';
		foreach ( memorial_plugin_legal_docs() as $k => [$title] ) {
			$id    = memorial_plugin_legal_page( $k );
			$st    = ! $id ? ( '<span style="color:#b32d2e">' . esc_html__( 'not chosen', 'memorial-plugin' ) . '</span>' )
			: ( get_post_status( $id ) === 'publish' ? ( '<span style="color:#1a7f37">' . esc_html__( '✔ published', 'memorial-plugin' ) . '</span>' )
			: ( '<span style="color:#9a6700">' . esc_html__( 'draft — check the text and publish it', 'memorial-plugin' ) . '</span>' ) );
			$act   = $id ? ' · <a href="' . esc_url( get_edit_post_link( $id ) ) . ( '">' . esc_html__( 'edit', 'memorial-plugin' ) . '</a> · <a href="' ) . esc_url( get_permalink( $id ) ) . ( '" target="_blank">' . esc_html__( 'open', 'memorial-plugin' ) . '</a>' ) : '';
			$act  .= ' · <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mem_legal_create&doc=' . $k ), 'mem_legal_create' ) ) . '"'
				. ( $id ? ( ' onclick="return confirm(\'' . esc_attr__( 'Create a new draft page from the template and choose it instead of the current one? The current page stays, you can delete it by hand.', 'memorial-plugin' ) . '\')"' ) : '' )
				. ( '>' . esc_html__( 'create from the template', 'memorial-plugin' ) . '</a>' );
			$rows .= '<li><b>' . esc_html( $title ) . '</b>: ' . $st . $act . '</li>';
		}
		$missing            = array_filter( array_keys( memorial_plugin_legal_docs() ), fn( $k ) => ! memorial_plugin_legal_page( $k ) );
		$btn                = $missing ? '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mem_legal_create' ), 'mem_legal_create' ) ) . ( '">' . esc_html__( 'Create the missing pages', 'memorial-plugin' ) . '</a> ' )
		. ( '<span class="description">' . esc_html__( '— drafts with text templates will appear; the company details (sole proprietor, TIN, OGRNIP, address, email) are filled in from “Contacts” automatically.', 'memorial-plugin' ) . '</span></p>' ) : '';
		$pages['mem_legal'] = array(
			'slug'   => 'mem-legal',
			'title'  => __( 'Documents', 'memorial-plugin' ),
			'schema' => 'memorial_plugin_legal_schema',
			'intro'  => '<ul style="margin:.4em 0 .8em">' . $rows . '</ul>' . $btn
				. ( '<p class="description">' . esc_html__( 'Templates are a sample under 152-FZ; check them for your business before publishing (better with a lawyer).', 'memorial-plugin' ) . ' ' )
				. ( esc_html__( 'The cookie banner mode (ask for consent or only notify) — in “Memorial → Analytics”.', 'memorial-plugin' ) . '</p>' ),
		);
		return $pages;
	}
);

add_action(
	'admin_post_mem_legal_create',
	function () {
		if ( ! current_user_can( 'edit_pages' ) || ! check_admin_referer( 'mem_legal_create' ) ) {
			wp_die( esc_html__( 'No access', 'memorial-plugin' ) );
		}
		$opt  = get_option( 'mem_legal' );
		$opt  = is_array( $opt ) ? $opt : memorial_plugin_legal_defaults();
		$only = sanitize_key( $_GET['doc'] ?? '' );
		foreach ( memorial_plugin_legal_docs() as $k => [$title] ) {
			if ( $only && $k !== $only ) {
				continue;
			}
			if ( ! $only && ! empty( $opt['pages'][ $k ] ) && get_post( (int) $opt['pages'][ $k ] ) ) {
				continue;
			}
			$slug = array(
				'policy'  => 'politika-obrabotki-personalnyh-dannyh',
				'consent' => 'soglasie-na-obrabotku-personalnyh-dannyh',
				'cookies' => 'politika-cookie',
			)[ $k ];
			$id   = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => memorial_plugin_legal_template( $k ),
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$opt['pages'][ $k ] = (string) $id;
			}
		}
		update_option( 'mem_legal', $opt );
		wp_safe_redirect( admin_url( 'admin.php?page=mem-legal' ) );
		exit;
	}
);

/*
---------- Шорткоды для текстов документов ----------
 */
/* [mem_operator] — «ИП Красильникова Л. В. (ИНН …, ОГРНИП …)»; [mem_operator chto="адрес|почта|телефон|сайт"] */
add_shortcode(
	'mem_operator',
	function ( $atts ) {
		$a = shortcode_atts( array( 'chto' => '' ), $atts, 'mem_operator' );
		$l = memorial_plugin_contacts()['legal'] ?? array();
		switch ( mb_strtolower( trim( $a['chto'] ) ) ) {
			case 'адрес':
				$ad = memorial_plugin_addrs();
				return esc_html( $ad[0]['addr'] ?? '' );
			case 'почта':
				$e = memorial_plugin_emails();
				return $e ? '<a href="mailto:' . esc_attr( $e[0] ) . '">' . esc_html( $e[0] ) . '</a>' : '';
			case 'телефон':
				return esc_html( memorial_plugin_opt( 'phone' ) );
			case 'сайт':
				return '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( preg_replace( '~^https?://~', '', untrailingslashit( home_url() ) ) ) . '</a>';
		}
		$name = trim( $l['name'] ?? '' );
		$name = '' !== $name ? $name : get_bloginfo( 'name' );
		$req  = array_slice( memorial_plugin_legal_parts(), trim( $l['name'] ?? '' ) ? 1 : 0 );
		return esc_html( $name . ( $req ? ' (' . implode( ', ', $req ) . ')' : '' ) );
	}
);
