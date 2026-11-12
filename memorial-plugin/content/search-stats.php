<?php
/**
 * Что ищут на сайте: учёт запросов в поиске «Вопрос–ответ» и «Полезно знать».
 *
 *  — браузер отправляет итоговый запрос (выбрали подсказку, нажали «Найти», «Часто ищут» или перестали печатать)
 *    и сколько нашлось — admin-ajax.php?action=mem_sq;
 *  — хранится только сам запрос и счётчики: без IP, cookie и данных посетителя. Номера телефонов, почта, ссылки,
 *    длинные строки не сохраняются; с одного адреса — не больше 40 запросов в час (защита от накрутки);
 *  — «Мемориал → Что ищут на сайте»: частые запросы и запросы, по которым ничего не нашлось;
 *  — «Часто ищут» под полем поиска собирается из частых запросов, по которым есть ответы
 *    (пока статистики мало — показываются слова по умолчанию).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

const MEMORIAL_PLUGIN_SQ_OPT = 'mem_sq_stats';
const MEMORIAL_PLUGIN_SQ_MAX = 600;     // столько разных запросов храним (редкие и старые вытесняются).

/** Запрос для учёта: нижний регистр, без лишних пробелов; null — не сохраняем
 *
 * @param string $q Query.
 */
function memorial_plugin_sq_clean( $q ) {
	$q = trim( preg_replace( '/\s+/u', ' ', mb_strtolower( wp_strip_all_tags( (string) $q ) ) ) );
	$q = str_replace( 'ё', 'е', $q );
	$q = trim( $q, " \t.,!?;:-—«»\"'()" );
	if ( mb_strlen( $q ) < 3 || mb_strlen( $q ) > 60 ) {
		return null;
	}
	if ( preg_match( '/\d[\d\s\-()]{4,}|@|https?:|www\.|\.ru\b|\.com\b/u', $q ) ) {
		return null;   // телефоны, почта, ссылки.
	}
	if ( count( explode( ' ', $q ) ) > 7 ) {
		return null;
	}
	if ( ! preg_match( '/[а-яa-z]{2}/u', $q ) ) {
		return null;
	}
	return $q;
}

/**
 * Sq stats.
 */
function memorial_plugin_sq_stats() {
	$s = get_option( MEMORIAL_PLUGIN_SQ_OPT, array() );
	return is_array( $s ) ? $s : array();
}

/**
 * AJAX beacon: counts what visitors search for on the site (Memorial → What people search for).
 */
function memorial_plugin_sq_ajax() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- anonymous search beacon from cached public pages (a nonce would be stale there); it changes no user data, the input is cleaned and the rate is limited below.
	$q = memorial_plugin_sq_clean( wp_unslash( $_POST['q'] ?? '' ) );
	if ( null === $q ) {
		wp_die( '', '', 204 );
	}
	// не больше 40 запросов в час с одного адреса (адрес не хранится — только его хэш на час).
	$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'mem_sq_' . substr( md5( $ip . wp_salt( 'nonce' ) ), 0, 16 );
	$n   = (int) get_transient( $key );
	if ( $n >= 40 ) {
		wp_die( '', '', 204 );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$found = max( 0, min( 999, (int) ( $_POST['n'] ?? 0 ) ) );
	$where = ( $_POST['w'] ?? '' ) === 'guide' ? 'g' : 'f';
	$s     = memorial_plugin_sq_stats();
	$r     = $s[ $q ] ?? array(
		'c' => 0,
		'n' => 0,
		't' => 0,
		'w' => '',
	);
	++$r['c'];
	$r['n']  = $found;
	$r['t']  = time();
	$r['w']  = strpos( $r['w'], $where ) === false ? $r['w'] . $where : $r['w'];
	$s[ $q ] = $r;
	if ( count( $s ) > MEMORIAL_PLUGIN_SQ_MAX ) {   // вытесняем редкие и давние.
		uasort( $s, fn( $a, $b ) => ( $b['c'] * 10 + $b['t'] / 86400 / 30 ) <=> ( $a['c'] * 10 + $a['t'] / 86400 / 30 ) );
		$s = array_slice( $s, 0, MEMORIAL_PLUGIN_SQ_MAX, true );
	}
	update_option( MEMORIAL_PLUGIN_SQ_OPT, $s, false );
	wp_die( '', '', 204 );
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}
add_action( 'wp_ajax_mem_sq', 'memorial_plugin_sq_ajax' );
add_action( 'wp_ajax_nopriv_mem_sq', 'memorial_plugin_sq_ajax' );

/**
 * «Часто ищут»: частые запросы за последние полгода, по которым есть ответы (короткие — до 3 слов).
 * Нужно хотя бы $min таких запросов, иначе — $fallback.
 *
 * @param array $fallback Fallback.
 * @param int   $limit Limit.
 * @param int   $min Min.
 */
function memorial_plugin_sq_popular( array $fallback, $limit = 6, $min = 4 ) {
	$out  = array();
	$from = time() - 180 * DAY_IN_SECONDS;
	$s    = array_filter( memorial_plugin_sq_stats(), fn( $r, $q ) => $r['n'] > 0 && $r['c'] >= 2 && $r['t'] >= $from && count( explode( ' ', $q ) ) <= 3, ARRAY_FILTER_USE_BOTH );
	uasort( $s, fn( $a, $b ) => $b['c'] <=> $a['c'] );
	foreach ( array_keys( $s ) as $q ) {
		// «пособие» и «пособие на погребение» — не дублируем
		foreach ( $out as $o ) {
			if ( mb_strpos( $q, $o ) !== false || mb_strpos( $o, $q ) !== false ) {
				continue 2;
			}
		}
		$out[] = $q;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return count( $out ) >= $min ? $out : $fallback;
}

/* ---------- Админка: «Мемориал → Что ищут на сайте» — список на DataViews (src/catalog, экран search-stats) ---------- */
add_action(
	'admin_menu',
	function () {
		$hook = add_submenu_page(
			'mem-contacts',
			__( 'What people search for on the site', 'memorial-plugin' ),
			__( 'What people search for on the site', 'memorial-plugin' ),
			'edit_pages',
			'mem-sq',
			function () {
				echo '<div id="memorial-plugin-catalog" class="wrap memorial-plugin-admin" data-screen="search-stats"></div>';
			}
		);
		if ( $hook && function_exists( 'memorial_plugin_catalog_views_assets' ) ) {
			add_action( 'load-' . $hook, 'memorial_plugin_catalog_views_assets' );
		}
	},
	20
);

/** Запросы для списка: запрос, сколько раз, сколько нашлось, где искали, когда последний раз */
function memorial_plugin_sq_items() {
	$out = array();
	foreach ( memorial_plugin_sq_stats() as $q => $r ) {
		$out[] = array(
			'id'    => (string) $q,
			'query' => (string) $q,
			'count' => (int) $r['c'],
			'found' => (int) $r['n'],
			'where' => array_values( array_filter( str_split( (string) ( $r['w'] ? $r['w'] : 'f' ) ) ) ),
			'last'  => gmdate( 'c', (int) $r['t'] ),
		);
	}
	return $out;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'memorial-plugin/v1',
			'/search-stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => fn() => current_user_can( 'edit_pages' ),
					'callback'            => function () {
						$faq = '';
						foreach ( get_pages() as $p ) {
							if ( has_shortcode( $p->post_content, 'mem_faq' ) || has_block( 'memorial-plugin/faq', $p ) ) {
								$faq = get_permalink( $p );
								break; }
						}
						return rest_ensure_response(
							array(
								'items'    => memorial_plugin_sq_items(),
								'faqUrl'   => $faq,
								'canClear' => current_user_can( 'manage_options' ),
							)
						);
					},
				),
				array(
					// Удалить запросы из статистики (пустой список — всю статистику).
					'methods'             => WP_REST_Server::DELETABLE,
					'permission_callback' => fn() => current_user_can( 'manage_options' ),
					'args'                => array(
						'queries' => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'string' ),
							'default' => array(),
						),
					),
					'callback'            => function ( WP_REST_Request $request ) {
						$queries = array_map( 'strval', (array) $request['queries'] );
						if ( $queries ) {
							$s = memorial_plugin_sq_stats();
							foreach ( $queries as $q ) {
								unset( $s[ $q ] );
							}
							update_option( MEMORIAL_PLUGIN_SQ_OPT, $s, false );
						} else {
							delete_option( MEMORIAL_PLUGIN_SQ_OPT );
						}
						return rest_ensure_response( array( 'items' => memorial_plugin_sq_items() ) );
					},
				),
			)
		);
	}
);
