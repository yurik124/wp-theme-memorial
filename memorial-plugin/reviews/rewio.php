<?php
/**
 * Отзывы через Rewio API (https://docs.rewio.ru/) — Яндекс Карты, 2ГИС, Zoon, Google и другие площадки в одном формате.
 * Настройки: «Мемориал → Отзывы Rewio». Вывод на сайте — шорткод [mem_rewio].
 * Отзывы забираются по расписанию (WP-Cron) и хранятся у нас — страница сайта не ждёт ответа API и не ломается, если он недоступен.
 * Пользователь выбирает филиалы и площадки галочками по названиям — внутренние номера Rewio хранятся в скрытых полях.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

const MEMORIAL_PLUGIN_REWIO_API = 'https://api.rewio.ru/v3';

/* ---------- Настройки ---------- */
/**
 * Rewio schema.
 */
function memorial_plugin_rewio_schema() {
	return array(
		'api'    => array(
			'type'   => 'group',
			'label'  => __( '1. Connection', 'memorial-plugin' ),
			'help'   => __( 'The key is in the app.rewio.ru account (it starts with hrev_). A read-only key is enough. The key is kept on the site’s server, visitors do not see it.', 'memorial-plugin' ),
			'fields' => array(
				'key' => array(
					'type'        => 'secret',
					'label'       => __( 'API key', 'memorial-plugin' ),
					'placeholder' => 'hrev_…',
				),
				'on'  => array(
					'type'  => 'checkbox',
					'label' => __( 'Show the reviews on the site (where the “Rewio reviews” block is)', 'memorial-plugin' ),
				),
			),
		),
		'scope'  => array(
			'type'   => 'group',
			'label'  => __( '2. Which branches and sites', 'memorial-plugin' ),
			'render' => 'memorial_plugin_rewio_picker',
			'class'  => 'mrw-card',
			'fields' => array(
				'objects' => array(
					'type'  => 'text',
					'label' => '',
				),
				'sources' => array(
					'type'  => 'text',
					'label' => '',
				),
				'per'     => array(
					'type'  => 'text',
					'label' => '',
				),
				'tabs'    => array(
					'type'  => 'checkbox',
					'label' => '',
				),
			),
		),
		'filter' => array(
			'type'   => 'group',
			'label'  => __( '3. Which reviews to show', 'memorial-plugin' ),
			'fields' => array(
				'min'    => array(
					'type'    => 'select',
					'label'   => __( 'Rating', 'memorial-plugin' ),
					'options' => array(
						'1' => __( 'All ratings', 'memorial-plugin' ),
						'3' => __( '3★ and higher', 'memorial-plugin' ),
						'4' => __( '4★ and higher', 'memorial-plugin' ),
						'5' => __( 'Only 5★', 'memorial-plugin' ),
					),
				),
				'text'   => array(
					'type'  => 'checkbox',
					'label' => __( 'Only with text (not just stars)', 'memorial-plugin' ),
				),
				'photos' => array(
					'type'  => 'checkbox',
					'label' => __( 'Only with photos', 'memorial-plugin' ),
				),
				'months' => array(
					'type'  => 'number',
					'label' => __( 'Not older than, months (0 — all time)', 'memorial-plugin' ),
				),
				'stop'   => array(
					'type'        => 'textarea',
					'label'       => __( 'Hide reviews with the words (comma-separated)', 'memorial-plugin' ),
					'rows'        => 2,
					'placeholder' => __( 'for example: an employee’s surname', 'memorial-plugin' ),
				),
				'fetch'  => array(
					'type'  => 'number',
					'label' => __( 'How many latest reviews to load from every branch (up to 1000)', 'memorial-plugin' ),
				),
				'gone'   => array(
					'type'  => 'checkbox',
					'label' => __( 'Show reviews deleted on the review site', 'memorial-plugin' ),
					'help'  => __( 'The site keeps its copy of a review that disappeared from the review site; in “Review list” it is marked “Deleted on the review site”. Off — such reviews are not shown on the site.', 'memorial-plugin' ),
				),
			),
		),
		'view'   => array(
			'type'   => 'group',
			'label'  => __( '4. How it looks on the site', 'memorial-plugin' ),
			'fields' => array(
				'title'    => array(
					'type'        => 'text',
					'label'       => __( 'Block title', 'memorial-plugin' ),
					'placeholder' => __( 'Customer reviews', 'memorial-plugin' ),
				),
				'sort'     => array(
					'type'    => 'select',
					'label'   => __( 'Sort order', 'memorial-plugin' ),
					'options' => array(
						'new'    => __( 'Newest first', 'memorial-plugin' ),
						'best'   => __( 'Best first', 'memorial-plugin' ),
						'photos' => __( 'With photos first, then newest', 'memorial-plugin' ),
					),
				),
				'count'    => array(
					'type'  => 'number',
					'label' => __( 'How many to show at once (the rest — with the “Show more” button)', 'memorial-plugin' ),
				),
				'cols'     => array(
					'type'    => 'select',
					'label'   => __( 'Columns on a computer', 'memorial-plugin' ),
					'options' => array(
						'3' => '3',
						'2' => '2',
						'1' => __( '1 (a full-width feed)', 'memorial-plugin' ),
					),
				),
				'clip'     => array(
					'type'  => 'number',
					'label' => __( 'Collapse long text after, characters', 'memorial-plugin' ),
				),
				'summary'  => array(
					'type'  => 'checkbox',
					'label' => __( 'Average rating and the number of reviews at the top', 'memorial-plugin' ),
				),
				'controls' => array(
					'type'  => 'checkbox',
					'label' => __( 'Sorting for visitors: by date and by rating (on the right above the reviews)', 'memorial-plugin' ),
				),
				'pfilter'  => array(
					'type'  => 'checkbox',
					'label' => __( 'Filter by site: clicking a site in the rating summary shows only its reviews', 'memorial-plugin' ),
				),
				'reply'    => array(
					'type'  => 'checkbox',
					'label' => __( 'Company’s reply', 'memorial-plugin' ),
				),
				'photo'    => array(
					'type'  => 'checkbox',
					'label' => __( 'Photos from the reviews', 'memorial-plugin' ),
				),
				'source'   => array(
					'type'  => 'checkbox',
					'label' => __( 'Site name with a link to the review', 'memorial-plugin' ),
				),
				'date'     => array(
					'type'  => 'checkbox',
					'label' => __( 'Review date', 'memorial-plugin' ),
				),
			),
		),
		'cache'  => array(
			'type'   => 'group',
			'label'  => __( '5. Updating', 'memorial-plugin' ),
			'help'   => __( 'Reviews are loaded in the background and kept on the site. If Rewio is temporarily unavailable, the last loaded reviews stay on the site.', 'memorial-plugin' ),
			'fields' => array(
				'every' => array(
					'type'    => 'select',
					'label'   => __( 'How often to check for new reviews', 'memorial-plugin' ),
					'options' => array(
						'1'   => __( 'Every hour', 'memorial-plugin' ),
						'3'   => __( 'Every 3 hours', 'memorial-plugin' ),
						'6'   => __( 'Every 6 hours', 'memorial-plugin' ),
						'12'  => __( 'Twice a day', 'memorial-plugin' ),
						'24'  => __( 'Once a day', 'memorial-plugin' ),
						'168' => __( 'Once a week', 'memorial-plugin' ),
					),
				),
			),
		),
		'notify' => array(
			'type'   => 'group',
			'label'  => __( '6. Notifications', 'memorial-plugin' ),
			'fields' => array(
				'on' => array(
					'type'  => 'checkbox',
					'label' => __( 'Notify about new or deleted reviews by email', 'memorial-plugin' ),
					'help'  => __( 'After a loading that found new reviews or reviews that disappeared from the review site. Where to and the text of the email — “Memorial → Settings → Mail”.', 'memorial-plugin' ),
				),
			),
		),
	);
}

/**
 * Rewio defaults.
 */
function memorial_plugin_rewio_defaults() {
	return array(
		'api'    => array(
			'key' => '',
			'on'  => 0,
		),
		'scope'  => array(
			'objects' => '',
			'sources' => '',
			'per'     => '',
			'tabs'    => 1,
		),
		'filter' => array(
			'min'    => '4',
			'text'   => 1,
			'photos' => 0,
			'months' => 0,
			'stop'   => '',
			'fetch'  => 100,
			// Before 2.1 the switch stood above the review list (option mem_rewio_show_gone).
			'gone'   => (int) (bool) get_option( 'mem_rewio_show_gone' ),
		),
		'view'   => array(
			'title'    => __( 'Customer reviews', 'memorial-plugin' ),
			'sort'     => 'new',
			'count'    => 6,
			'cols'     => '3',
			'clip'     => 320,
			'summary'  => 1,
			'controls' => 1,
			'pfilter'  => 1,
			'reply'    => 1,
			'photo'    => 1,
			'source'   => 1,
			'date'     => 1,
		),
		'cache'  => array( 'every' => '6' ),
		'notify' => array( 'on' => 0 ),
	);
}

add_filter(
	'memorial_plugin_admin_pages',
	function ( $pages ) {
		$pages['mem_rewio'] = array(
			'slug'   => 'mem-rewio',
			'title'  => __( 'Rewio reviews', 'memorial-plugin' ),
			'h1'     => __( 'Reviews', 'memorial-plugin' ),
			'hidden' => true,
			'schema' => 'memorial_plugin_rewio_schema',
			'intro'  => __( 'Reviews from Yandex Maps, 2GIS, Zoon and other sites through the <a href="https://rewio.ru/" target="_blank" rel="noopener">Rewio</a> service — as cards in the site’s design. ', 'memorial-plugin' )
					. __( 'Paste the key and save, tick the branches — the reviews load by themselves. They are shown on a page by the “Rewio reviews” block.', 'memorial-plugin' ),
		);
		return $pages;
	}
);

/**
 * Rewio.
 */
function memorial_plugin_rewio() {
	return memorial_plugin_settings( 'mem_rewio' ); }

/**
 * Per-source selection as stored in the option: "source:id.id;source:id".
 *
 * @param array $per Selected object IDs keyed by source ID.
 * @return string
 */
function memorial_plugin_rewio_per_string( $per ) {
	return implode( ';', array_map( fn( $k, $v ) => $k . ':' . implode( '.', $v ), array_keys( $per ), $per ) );
}

/** Площадки по филиалам: «1645:1.3;1652:1» → [1645 => [1, 3], 1652 => [1]]. Филиала нет в списке — со всех его площадок
 *
 * @param array $s Section data.
 */
function memorial_plugin_rewio_per( $s ) {
	$out = array();
	foreach ( explode( ';', (string) $s ) as $part ) {
		if ( ! preg_match( '/^\s*(\d+)\s*:\s*([\d.]*)\s*$/', $part, $m ) ) {
			continue;
		}
		$out[ (int) $m[1] ] = array_values( array_filter( array_map( 'intval', explode( '.', $m[2] ) ) ) );
	}
	return $out;
}

/**
 * A link for a review that came without its own: the company's page on that review site (the Rewio
 * branch), else the review site itself.
 *
 * @param int   $sid     Review site ID.
 * @param array $sources Review sites of the cache.
 * @return string
 */
function memorial_plugin_rewio_src_url( $sid, $sources ) {
	// The company's reviews page on that site — from a link of another review of the same site:
	// yandex.ru/maps/org/<id>/reviews?… → yandex.ru/maps/org/<id>/reviews, 2gis.ru/reviews/<id>/review/<n> → 2gis.ru/reviews/<id>.
	static $pages = null;
	if ( null === $pages ) {
		$pages = array();
		foreach ( (array) ( memorial_plugin_rewio_cache_get()['groups'] ?? array() ) as $g ) {
			foreach ( (array) ( $g['reviews'] ?? array() ) as $r ) {
				$u = (string) ( $r['url'] ?? '' );
				if ( '' !== $u && ! isset( $pages[ (int) $r['src'] ] ) && preg_match( '~^https?://~', $u ) ) {
					$pages[ (int) $r['src'] ] = preg_replace( '~/review/[^/?#]+/?$~', '', strtok( $u, '?#' ) );
				}
			}
		}
	}
	if ( ! empty( $pages[ $sid ] ) ) {
		return $pages[ $sid ];
	}
	$cat = (array) get_option( 'mem_rewio_catalog', array() );
	foreach ( (array) ( $cat['objects'] ?? array() ) as $o ) {
		if ( ! empty( $o['links'][ $sid ] ) ) {
			return (string) $o['links'][ $sid ];
		}
	}
	$url = (string) ( $sources[ $sid ]['url'] ?? ( $cat['urls'][ $sid ] ?? '' ) );
	if ( '' === $url && function_exists( 'memorial_plugin_logo_domain' ) ) {
		$url = memorial_plugin_logo_domain( (string) ( $sources[ $sid ]['slug'] ?? '' ) );
	}
	if ( '' !== $url && ! preg_match( '~^https?://~', $url ) ) {
		$url = 'https://' . ltrim( $url, '/' );
	}
	return $url;
}

/** Значок площадки — настоящий значок сервиса (reviews/logos.php)
 *
 * @param string $slug Slug.
 * @param string $name Name.
 */
function memorial_plugin_rewio_icon( $slug, $name = '' ) {
	return memorial_plugin_logo_html( $slug, $name ); }
/**
 * Rewio ids.
 *
 * @param array $s Section data.
 */
function memorial_plugin_rewio_ids( $s ) {
	return array_values( array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', (string) $s ) ) ) ); }

/* ---------- Запросы к API ---------- */
/**
 * Rewio get.
 *
 * @param string $path Path.
 * @param array  $args Args.
 * @param string $key Key.
 */
function memorial_plugin_rewio_get( $path, $args = array(), $key = null ) {
	$key = $key ?? trim( (string) ( memorial_plugin_rewio()['api']['key'] ?? '' ) );
	if ( '' === $key ) {
		return new WP_Error( 'nokey', __( 'The API key is not set.', 'memorial-plugin' ) );
	}
	$q = array();
	foreach ( $args as $k => $v ) {
		if ( null === $v || '' === $v || array() === $v ) {
			continue;
		}
		foreach ( (array) $v as $one ) {
			$q[] = rawurlencode( $k ) . '=' . rawurlencode( is_bool( $one ) ? ( $one ? 'true' : 'false' ) : (string) $one );
		}
	}
	$url = MEMORIAL_PLUGIN_REWIO_API . $path . ( $q ? '?' . implode( '&', $q ) : '' );
	$r   = wp_remote_get(
		$url,
		array(
			'timeout' => 30,
			'headers' => array(
				'X-API-Key' => $key,
				'Accept'    => 'application/json',
			),
		)
	);
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'net', __( 'Rewio does not respond (', 'memorial-plugin' ) . $r->get_error_message() . ').' );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$raw  = wp_remote_retrieve_body( $r );
	$body = json_decode( $raw, true );
	if ( 200 !== $code ) {
		$d = is_array( $body ) ? ( $body['detail'] ?? $body['message'] ?? $body['error'] ?? '' ) : '';
		if ( is_array( $d ) ) {
			$d = $d['message'] ?? ( isset( $d[0]['msg'] ) ? implode( '; ', array_map( fn( $e ) => ( $e['loc'] ? implode( '.', (array) $e['loc'] ) . ': ' : '' ) . ( $e['msg'] ?? '' ), $d ) ) : wp_json_encode( $d, JSON_UNESCAPED_UNICODE ) );
		}
		if ( '' === $d && '' !== $raw ) {
			$d = mb_substr( wp_strip_all_tags( $raw ), 0, 160 );
		}
		$hint = array(
			401 => __( 'the key did not fit', 'memorial-plugin' ),
			403 => __( 'the key has no access', 'memorial-plugin' ),
			404 => __( 'not found', 'memorial-plugin' ),
			422 => __( 'the API did not accept the request parameters', 'memorial-plugin' ),
			429 => __( 'too many requests, we will try later', 'memorial-plugin' ),
		)[ $code ] ?? __( 'error ', 'memorial-plugin' ) . $code;
		return new WP_Error( 'http', 'Rewio: ' . $hint . ( $d ? ' — ' . $d : '' ) . '.' );
	}
	return is_array( $body ) ? $body : new WP_Error( 'json', __( 'Rewio returned an unclear response.', 'memorial-plugin' ) );
}

/* ---------- Список филиалов, папок и площадок аккаунта (по названиям) ---------- */
/**
 * Rewio catalog fetch.
 *
 * @param string $key Key.
 */
function memorial_plugin_rewio_catalog_fetch( $key = null ) {
	$src = memorial_plugin_rewio_get( '/sources', array(), $key );
	if ( is_wp_error( $src ) ) {
		return $src;
	}
	$names = array();
	$slugs = array();
	$urls  = array();
	foreach ( $src['data'] ?? array() as $s ) {
		$names[ (int) $s['id'] ] = (string) $s['name'];
		$slugs[ (int) $s['id'] ] = (string) ( $s['slug'] ?? '' );
		$urls[ (int) $s['id'] ]  = (string) ( $s['base_url'] ?? '' ); }
	$obj = memorial_plugin_rewio_get( '/objects', array( 'limit' => 1000 ), $key );
	if ( is_wp_error( $obj ) ) {
		return $obj;
	}
	$objects = array();
	$used    = array();
	foreach ( $obj['data'] ?? array() as $o ) {
		$sids  = array();
		$links = array(); // the branch's own page on each review site — the link of a review that came without one.
		foreach ( $o['links'] ?? array() as $l ) {
			$sid          = (int) $l['source_id'];
			$sids[]       = $sid;
			$used[ $sid ] = true;
			$lu           = (string) ( $l['url'] ?? ( $l['link'] ?? ( $l['source_url'] ?? '' ) ) );
			if ( preg_match( '~^https?://~', $lu ) ) {
				$links[ $sid ] = $lu;
			}
		}
		$objects[] = array(
			'id'      => (int) $o['id'],
			'name'    => (string) $o['name'],
			'count'   => $o['review_count'],
			'status'  => (string) ( $o['scrape_status'] ?? '' ),
			'demo'    => ! empty( $o['is_demo'] ),
			'active'  => ! isset( $o['is_active'] ) || $o['is_active'],
			'sources' => array_values( array_unique( $sids ) ),
			'links'   => $links,
		);
	}
	$folders = array();
	$fo      = memorial_plugin_rewio_get( '/folders', array(), $key );
	if ( ! is_wp_error( $fo ) ) {
		$walk = function ( $f, $prefix ) use ( &$walk, &$folders ) {
			$ids = array_map( fn( $x ) => (int) $x['id'], $f['objects'] ?? array() );
			foreach ( $f['subfolders'] ?? array() as $sf ) {
				$ids = array_merge( $ids, array_map( fn( $x ) => (int) $x['id'], $sf['objects'] ?? array() ) );
			}
			if ( $ids ) {
				$folders[] = array(
					'name'    => $prefix . $f['name'],
					'objects' => array_values( array_unique( $ids ) ),
					'demo'    => ! empty( $f['is_demo'] ),
				);
			}
			foreach ( $f['subfolders'] ?? array() as $sf ) {
				$walk( $sf, $f['name'] . ' / ' );
			}
		};
		foreach ( $fo['data'] ?? array() as $f ) {
			$walk( $f, '' );
		}
	}
	$sources = array();
	foreach ( array_keys( $used ) as $sid ) {
		$sources[ $sid ] = $names[ $sid ] ?? __( 'Site ', 'memorial-plugin' ) . $sid;
	}
	asort( $sources );
	$cat = array(
		'time'        => time(),
		'objects'     => $objects,
		'folders'     => $folders,
		'sources'     => $sources,
		'all_sources' => $names,
		'slugs'       => $slugs,
		'urls'        => $urls,
	);
	foreach ( $sources as $sid => $nm ) {
		memorial_plugin_logo_fetch( memorial_plugin_logo_key( $slugs[ $sid ] ?? '', $nm ), $urls[ $sid ] ?? '' );
	}
	update_option( 'mem_rewio_catalog', $cat, false );
	return $cat;
}

/*
---------- Хранение загруженных отзывов ----------
	В отзывах бывают эмодзи (😊🙏), а таблицы базы на многих хостингах их не принимают — запись молча не проходит.
	Поэтому храним JSON, где все не-латинские символы записаны кодами \uXXXX: такая строка сохраняется в любой базе.
 */
/**
 * Rewio cache set.
 *
 * @param array $data Data.
 */
function memorial_plugin_rewio_cache_set( $data ) {
	$json = wp_json_encode( $data ); // без JSON_UNESCAPED_UNICODE — только ASCII.
	if ( ! $json ) {
		return false;
	}
	update_option( 'mem_rewio_cache', $json, false );
	wp_cache_delete( 'mem_rewio_cache', 'options' );
	return get_option( 'mem_rewio_cache' ) === $json;
}
/**
 * Rewio cache get.
 */
function memorial_plugin_rewio_cache_get() {
	$v = get_option( 'mem_rewio_cache', '' );
	return is_array( $v ) ? $v : ( is_array( json_decode( (string) $v, true ) ) ? json_decode( (string) $v, true ) : array() );
}

/* ---------- Загрузка отзывов ---------- */
/**
 * Rewio status.
 *
 * @param array $data Data.
 */
function memorial_plugin_rewio_status( $data ) {
	update_option( 'mem_rewio_status', $data + array( 'time' => time() ), false );
}

/**
 * Забрать отзывы и сохранить у себя (старые отзывы при ошибке остаются).
 *
 * @throws Exception Caught inside: a failed request is turned into WP_Error.
 * @return true|WP_Error
 */
function memorial_plugin_rewio_refresh() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 180 );
	}
	$log = array();
	try {
		$o   = memorial_plugin_rewio();
		$f   = $o['filter'];
		$sc  = $o['scope'];
		$cat = get_option( 'mem_rewio_catalog' );
		// A list saved before the branches kept their links to the review sites is fetched again.
		if ( ! is_array( $cat ) || empty( $cat['objects'] ) || ! isset( $cat['objects'][0]['links'] ) ) {
			$cat = memorial_plugin_rewio_catalog_fetch();
			if ( is_wp_error( $cat ) ) {
				throw new Exception( $cat->get_error_message() );
			}
		}
		$by_id = array();
		foreach ( $cat['objects'] as $ob ) {
			$by_id[ $ob['id'] ] = $ob;
		}
		// выбранные филиалы; ничего не выбрано — все, кроме демо-примера Rewio.
		$ids = array_values( array_filter( memorial_plugin_rewio_ids( $sc['objects'] ), fn( $id ) => isset( $by_id[ $id ] ) ) );
		if ( ! $ids ) {
			$ids = array_map( fn( $ob ) => $ob['id'], array_filter( $cat['objects'], fn( $ob ) => ! $ob['demo'] ) );
		}
		if ( ! $ids ) {
			throw new Exception( __( 'The Rewio account has no branches — add them in the app.rewio.ru account.', 'memorial-plugin' ) );
		}
		$tabs = ! empty( $sc['tabs'] ) && count( $ids ) > 1;

		$common = array(
			'limit'      => max( 1, min( 1000, (int) ( $f['fetch'] ? $f['fetch'] : 100 ) ) ),
			'sort_by'    => 'review_date',
			'sort_order' => 'desc',
			'min_rating' => max( 1, (int) $f['min'] ),
			'has_text'   => ! empty( $f['text'] ) ? true : null,
			'has_images' => ! empty( $f['photos'] ) ? true : null,
			'source_ids' => null,
		);
		if ( (int) $f['months'] > 0 ) {
			$common['published_from'] = gmdate( 'Y-m-d', strtotime( '-' . (int) $f['months'] . ' months' ) );
		}

		$per    = memorial_plugin_rewio_per( $sc['per'] ?? '' );
		$global = memorial_plugin_rewio_ids( $sc['sources'] ?? '' ); // старая общая настройка площадок.
		$groups = array();
		$fetch  = array(); // what was asked of every branch: how far back the list goes (reviews/rewio-archive.php).
		foreach ( $ids as $id ) {
			$name                 = $by_id[ $id ]['name'];
			$sids                 = $per[ $id ] ?? ( $global ? array_values( array_intersect( $global, $by_id[ $id ]['sources'] ) ) : array() );
			$common['source_ids'] = $sids ? $sids : null;
			$r                    = memorial_plugin_rewio_get( '/reviews', array( 'object_id' => $id ) + $common );
			if ( is_wp_error( $r ) ) {
				throw new Exception( '«' . $name . '»: ' . $r->get_error_message() );
			}
			$reviews  = array_map( 'memorial_plugin_rewio_norm', $r['data'] ?? array() );
			// A review without its own link gets one when shown (memorial_plugin_rewio_src_url()); here the link
			// stays as the site gave it — its disappearance marks the review “changed” (reviews/rewio-archive.php).
			foreach ( $reviews as &$rv ) {
				$rv['obj'] = (int) $id;
			}
			unset( $rv );
			$dates        = array_filter( array_column( $reviews, 'date' ) );
			$fetch[ $id ] = array(
				'n'      => count( $reviews ),
				'limit'  => (int) $common['limit'],
				'oldest' => $dates ? min( $dates ) : '',
				'sids'   => $sids,
			);
			$note     = $r['collecting']['message'] ?? ( $r['access']['message'] ?? '' );
			$log[]    = '«' . $name . __( '»: loaded ', 'memorial-plugin' ) . count( $reviews ) . __( ' of ', 'memorial-plugin' ) . (int) ( $r['total'] ?? count( $reviews ) ) . ( $note ? ' (' . $note . ')' : '' );
			$an       = memorial_plugin_rewio_get(
				'/analytics',
				array(
					'object_id'  => $id,
					'source_ids' => $common['source_ids'],
				)
			);
			$sum      = is_wp_error( $an ) ? null : array(
				'avg'   => $an['summary']['average_rating'] ?? null,
				'count' => (int) ( $an['summary']['review_count'] ?? 0 ),
				'by'    => array_map(
					fn( $b ) => array(
						'id'    => (int) $b['source_id'],
						'name'  => (string) $b['source_name'],
						'avg'   => $b['average_rating'],
						'count' => (int) $b['review_count'],
					),
					$an['by_source'] ?? array()
				),
			);
			$groups[] = array(
				'name'    => $name,
				'reviews' => $reviews,
				'summary' => $sum,
			);
		}
		if ( ! $tabs && count( $groups ) > 1 ) { // одной лентой: отзывы вместе, сводка — средневзвешенная.
			$all  = array();
			$cnt  = 0;
			$sumr = 0;
			$by   = array();
			foreach ( $groups as $g ) {
				$all = array_merge( $all, $g['reviews'] );
				if ( ! $g['summary'] ) {
					continue;
				}
				$cnt  += $g['summary']['count'];
				$sumr += (float) $g['summary']['avg'] * $g['summary']['count'];
				foreach ( $g['summary']['by'] as $b ) {
					$by[ $b['id'] ]           = $by[ $b['id'] ] ?? array(
						'id'    => $b['id'],
						'name'  => $b['name'],
						'count' => 0,
						's'     => 0,
					);
					$by[ $b['id'] ]['count'] += $b['count'];
					$by[ $b['id'] ]['s']     += (float) $b['avg'] * $b['count'];
				}
			}
			foreach ( $by as &$b ) {
				$b['avg'] = $b['count'] ? round( $b['s'] / $b['count'], 2 ) : null;
				unset( $b['s'] );
			} unset( $b );
			$groups = array(
				array(
					'name'    => '',
					'reviews' => $all,
					'summary' => $cnt ? array(
						'avg'   => round( $sumr / $cnt, 2 ),
						'count' => $cnt,
						'by'    => array_values( $by ),
					) : null,
				),
			);
		}
		foreach ( $groups as &$g ) {
			usort( $g['reviews'], fn( $a, $b ) => strcmp( $b['date'], $a['date'] ) );
		}
		unset( $g );
		// Own copy of every review: new, changed and gone from the site are compared with it; what is shown
		// (the version, the link, gone reviews) is built from that copy.
		if ( function_exists( 'memorial_plugin_rewio_archive' ) ) {
			$groups = memorial_plugin_rewio_archive( $groups, $fetch, $common );
		}

		$sources = array();
		foreach ( $cat['all_sources'] ?? $cat['sources'] as $sid => $nm ) {
			$sources[ (int) $sid ] = array(
				'name' => $nm,
				'slug' => $cat['slugs'][ $sid ] ?? '',
				'url'  => $cat['urls'][ $sid ] ?? '',
			);
		}
		$n = 0;
		foreach ( $groups as $g ) {
			$n += count( $g['reviews'] );
		}
		$was = memorial_plugin_rewio_cache_get();
		if ( ! memorial_plugin_rewio_cache_set(
			array(
				'time'    => time(),
				'sources' => $sources,
				'groups'  => $groups,
			)
		) ) {
			throw new Exception( __( 'Reviews were received (', 'memorial-plugin' ) . $n . __( '), but were not saved in the site’s database. Tell the site developer.', 'memorial-plugin' ) );
		}
		// The reviews on the site changed (new, changed, gone, other ratings) — the saved pages are made again.
		if ( wp_json_encode( $was['groups'] ?? array() ) !== wp_json_encode( $groups ) && function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
		memorial_plugin_rewio_status(
			array(
				'ok'    => true,
				'error' => '',
				'log'   => $log,
			)
		);
		return true;
	} catch ( Throwable $e ) {
		memorial_plugin_rewio_status(
			array(
				'ok'    => false,
				'error' => $e->getMessage(),
				'log'   => $log,
			)
		);
		return new WP_Error( 'rewio', $e->getMessage() );
	}
}

/** Копия фото к отзыву на своём сайте (uploads/mem-rewio/). '' — не скачалось (фото тогда не показываем).
 *
 * @param string $url URL.
 */
function memorial_plugin_rewio_local_img( $url ) {
	$url = (string) $url;
	if ( ! preg_match( '~^https?://~', $url ) ) {
		return '';
	}
	$u     = wp_upload_dir();
	$dir   = trailingslashit( $u['basedir'] ) . 'mem-rewio/';
	$ext   = strtolower( pathinfo( (string) parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$ext   = in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ? $ext : 'jpg';
	$file  = md5( $url ) . '.' . $ext;
	$local = trailingslashit( $u['baseurl'] ) . 'mem-rewio/' . $file;
	if ( is_file( $dir . $file ) ) {
		return $local;
	}
	static $budget = 30;                         // не больше 30 новых фото за одно обновление (остальные — в следующий раз).
	if ( $budget-- <= 0 || ! wp_mkdir_p( $dir ) ) {
		return '';
	}
	$r = wp_remote_get(
		$url,
		array(
			'timeout'             => 6,
			'redirection'         => 3,
			'limit_response_size' => 4 * MB_IN_BYTES,
		)
	);
	if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) !== 200 ) {
		return '';
	}
	$type = (string) wp_remote_retrieve_header( $r, 'content-type' );
	$body = wp_remote_retrieve_body( $r );
	if ( strpos( $type, 'image/' ) !== 0 || strlen( $body ) < 200 ) {
		return '';
	}
	return file_put_contents( $dir . $file, $body ) !== false ? $local : '';
}

/**
 * Rewio norm.
 *
 * @param mixed $r R.
 */
function memorial_plugin_rewio_norm( $r ) {
	$imgs = array();
	foreach ( array_slice( $r['images'] ?? array(), 0, 8 ) as $im ) {
		if ( ! empty( $im['image'] ) || ! empty( $im['preview'] ) ) {
			// фото к отзывам скачиваются на свой сайт — посетитель не грузит картинки со сторонних серверов.
			$sm = memorial_plugin_rewio_local_img( $im['preview'] ?? $im['image'] );
			$bg = memorial_plugin_rewio_local_img( $im['image'] ?? $im['preview'] );
			if ( $sm && $bg ) {
				$imgs[] = array(
					's' => $sm,
					'b' => $bg,
				);
			}
		}
	}
	return array(
		'id'     => (int) ( $r['id'] ?? 0 ),
		'author' => '' !== trim( (string) ( $r['author'] ?? '' ) ) ? trim( (string) $r['author'] ) : __( 'Guest', 'memorial-plugin' ),
		'text'   => trim( (string) ( $r['text'] ?? '' ) ),
		'date'   => (string) ( $r['review_date'] ?? '' ),
		'rating' => isset( $r['rating'] ) ? (float) $r['rating'] : null,
		'src'    => (int) ( $r['source_id'] ?? 0 ),
		'slug'   => (string) ( $r['source_slug'] ?? '' ),
		'url'    => (string) ( $r['review_url'] ?? '' ),
		'reply'  => trim( (string) ( $r['reply_text'] ?? '' ) ),
		'rdate'  => (string) ( $r['reply_date'] ?? '' ),
		'imgs'   => $imgs,
	);
}

/* ---------- Расписание ---------- */
add_filter(
	'cron_schedules',
	function ( $s ) {
		foreach ( array( 1, 3, 6, 12, 24, 168 ) as $h ) {
			$s[ 'mem_rewio_' . $h ] = array(
				'interval' => $h * HOUR_IN_SECONDS,
				'display'  => __( 'Rewio: every ', 'memorial-plugin' ) . $h . __( ' h', 'memorial-plugin' ),
			);
		}
		return $s;
	}
);
add_action( 'memorial_plugin_rewio_cron', 'memorial_plugin_rewio_refresh' );
add_action( 'memorial_plugin_rewio_cron_once', 'memorial_plugin_rewio_refresh' );

/**
 * Rewio schedule.
 *
 * @param array $o Options.
 */
function memorial_plugin_rewio_schedule( $o = null ) {
	wp_clear_scheduled_hook( 'memorial_plugin_rewio_cron' );
	$o = $o ? memorial_plugin_merge( memorial_plugin_rewio_defaults(), $o ) : memorial_plugin_rewio();
	if ( trim( $o['api']['key'] ) === '' ) {
		return;
	}
	$h = (int) $o['cache']['every'] ? (int) $o['cache']['every'] : 6;
	wp_schedule_event( time() + $h * HOUR_IN_SECONDS, 'mem_rewio_' . $h, 'memorial_plugin_rewio_cron' );
}
// сохранили настройки: новый ключ — сразу список филиалов; расписание; загрузка отзывов в фоне.
add_action(
	'update_option_mem_rewio',
	function ( $old, $new ) {
		$key     = trim( $new['api']['key'] ?? '' );
		$old_key = trim( $old['api']['key'] ?? '' );
		if ( '' !== $key && $old_key !== $key ) {
			memorial_plugin_rewio_catalog_fetch( $key );
		}
		memorial_plugin_rewio_schedule( $new );
		if ( '' !== $key ) {
			wp_schedule_single_event( time(), 'memorial_plugin_rewio_cron_once' );
		}
	},
	20,
	2
);
add_action(
	'add_option_mem_rewio',
	function ( $name, $val ) {
		if ( trim( $val['api']['key'] ?? '' ) !== '' ) {
			memorial_plugin_rewio_catalog_fetch( trim( $val['api']['key'] ) );
		}
		memorial_plugin_rewio_schedule( $val );
	},
	20,
	2
);
/* плагин выключен — расписание снимается в memorial-core.php (register_deactivation_hook) */
add_action(
	'init',
	function () {
		// расписание потерялось (перенос сайта и т. п.) — восстанавливаем.
		if ( is_admin() && ! wp_next_scheduled( 'memorial_plugin_rewio_cron' ) && trim( memorial_plugin_rewio()['api']['key'] ?? '' ) !== '' ) {
			memorial_plugin_rewio_schedule();
		}
	}
);

/* ---------- Админка: выбор филиалов и площадок галочками, состояние, предпросмотр ---------- */
/**
 * Rewio when.
 *
 * @param string $t Text.
 */
function memorial_plugin_rewio_when( $t ) {
	if ( ! $t ) {
		return '—';
	}
	$d    = wp_date( 'Y-m-d', $t );
	$days = array(
		wp_date( 'Y-m-d' )                          => __( 'today', 'memorial-plugin' ),
		wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) => __( 'yesterday', 'memorial-plugin' ),
		wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) => __( 'tomorrow', 'memorial-plugin' ),
	);
	$day  = $days[ $d ] ?? wp_date( 'j F', $t );
	return $day . __( ' at ', 'memorial-plugin' ) . wp_date( 'H:i', $t );
}

/**
 * Rewio picker.
 *
 * @param string $name Name.
 * @param mixed  $val Val.
 */
function memorial_plugin_rewio_picker( $name, $val ) {
	$o   = memorial_plugin_rewio();
	$key = trim( $o['api']['key'] );
	$val = is_array( $val ) ? $val : array();
	// скрытые поля — сохраняются общей кнопкой «Сохранить»; галочки ниже их заполняют и сохраняют сразу.
	echo '<input type="hidden" name="' . esc_attr( $name ) . '[objects]" value="' . esc_attr( $val['objects'] ?? '' ) . '" data-rw-field="objects">';
	echo '<input type="hidden" name="' . esc_attr( $name ) . '[sources]" value="' . esc_attr( $val['sources'] ?? '' ) . '" data-rw-field="sources">';
	if ( '' === $key ) {
		echo ( '<p class="mrw__empty">' . esc_html__( 'First paste the API key into “1. Connection” and click “Save” — your branches will appear here.', 'memorial-plugin' ) . '</p>' );
		echo '<input type="hidden" name="' . esc_attr( $name ) . '[tabs]" value="' . ( ! empty( $val['tabs'] ) ? 1 : 0 ) . '">';
		return;
	}
	$cat = get_option( 'mem_rewio_catalog' );
	if ( ! is_array( $cat ) || empty( $cat['time'] ) ) {
		$cat = memorial_plugin_rewio_catalog_fetch(); }
	if ( is_wp_error( $cat ) ) {
		echo ( '<p class="mrw__bad">' . esc_html__( 'Could not get the list of branches:', 'memorial-plugin' ) . ' ' ) . esc_html( $cat->get_error_message() ) . '</p>';
		echo ( '<p><button type="button" class="button" data-rw="catalog">' . esc_html__( 'Try again', 'memorial-plugin' ) . '</button></p>' );
		echo '<input type="hidden" name="' . esc_attr( $name ) . '[tabs]" value="' . ( ! empty( $val['tabs'] ) ? 1 : 0 ) . '">';
		return;
	}
	$sel_o  = memorial_plugin_rewio_ids( $val['objects'] ?? '' );
	$per    = memorial_plugin_rewio_per( $val['per'] ?? '' );
	$global = memorial_plugin_rewio_ids( $val['sources'] ?? '' );
	$sname  = fn( $sid ) => $cat['all_sources'][ $sid ] ?? $cat['sources'][ $sid ] ?? __( 'Site', 'memorial-plugin' );
	$sslug  = fn( $sid ) => $cat['slugs'][ $sid ] ?? '';
	echo '<input type="hidden" name="' . esc_attr( $name ) . '[per]" value="' . esc_attr( $val['per'] ?? '' ) . '" data-rw-field="per">';
	?>
	<div class="mrw" data-rw-box>
		<p class="mrw__h"><?php esc_html_e( 'Branches and their sites', 'memorial-plugin' ); ?> <span class="mrw__hint"><?php esc_html_e( 'Tick the branches whose reviews to show (nothing ticked — all of them). Unneeded sites can be unticked for every branch.', 'memorial-plugin' ); ?></span></p>
		<?php if ( $cat['folders'] ) : ?>
		<p class="mrw__quick"><?php esc_html_e( 'Quickly:', 'memorial-plugin' ); ?>
			<?php
			foreach ( $cat['folders'] as $fd ) {
				if ( ! $fd['demo'] ) {
					echo '<button type="button" class="button button-small" data-rw-folder="' . esc_attr( implode( ',', $fd['objects'] ) ) . '">' . esc_html(
						sprintf(
						/* translators: %s: folder name */
							__( 'All from the folder “%s”', 'memorial-plugin' ),
							$fd['name']
						)
					) . '</button> ';}
			}
			?>
			<button type="button" class="button-link" data-rw-folder=""><?php esc_html_e( 'untick all', 'memorial-plugin' ); ?></button>
		</p>
		<?php endif; ?>
		<div class="mrw__objs">
		<?php
		foreach ( $cat['objects'] as $ob ) :
			$on = $per[ $ob['id'] ] ?? ( $global ? array_values( array_intersect( $global, $ob['sources'] ) ) : $ob['sources'] );
			if ( ! $on ) {
				$on = $ob['sources'];
			}
			?>
			<div class="mrw__obj<?php echo $ob['demo'] ? ' is-demo' : ''; ?><?php echo in_array( $ob['id'], $sel_o, true ) ? ' is-on' : ''; ?>" data-rw-objbox="<?php echo (int) $ob['id']; ?>">
			<label class="mrw__objhead">
				<input type="checkbox" data-rw-obj="<?php echo (int) $ob['id']; ?>"<?php checked( in_array( $ob['id'], $sel_o, true ) ); ?>>
				<b><?php echo esc_html( $ob['name'] ); ?></b>
				<?php
				if ( null !== $ob['count'] ) {
					echo '<span class="mrw__cnt">' . (int) $ob['count'] . ' ' . esc_html( _n( 'review', 'reviews', (int) $ob['count'], 'memorial-plugin' ) ) . '</span>';}
				?>
				<?php
				if ( $ob['demo'] ) {
					echo ( '<em class="mrw__tag">' . esc_html__( 'example from Rewio', 'memorial-plugin' ) . '</em>' );}
				?>
				<?php
				if ( ! $ob['active'] ) {
					echo ( '<em class="mrw__tag">' . esc_html__( 'collection is off', 'memorial-plugin' ) . '</em>' );}
				?>
				<?php
				if ( in_array( $ob['status'], array( 'pending', 'in_progress' ), true ) ) {
					echo ( '<em class="mrw__tag is-wait">' . esc_html__( 'Rewio is still collecting reviews', 'memorial-plugin' ) . '</em>' );}
				?>
				<?php
				if ( 'failed' === $ob['status'] ) {
					echo ( '<em class="mrw__tag is-bad">' . esc_html__( 'collection error in Rewio', 'memorial-plugin' ) . '</em>' );}
				?>
			</label>
			<?php if ( $ob['sources'] ) : ?>
			<div class="mrw__srcs">
				<?php foreach ( $ob['sources'] as $sid ) : ?>
				<label class="mrw__src"><input type="checkbox" data-rw-src="<?php echo (int) $sid; ?>"<?php checked( in_array( $sid, $on, true ) ); ?>> <?php echo memorial_plugin_rewio_icon( $sslug( $sid ), $sname( $sid ) ) . ' ' . esc_html( $sname( $sid ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the icon is built with esc_attr()/esc_url(). ?></label>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<?php
		if ( ! $cat['objects'] ) {
			echo ( '<p class="mrw__empty">' . esc_html__( 'The Rewio account has no branches yet — add them in the app.rewio.ru account and click “Update the list”.', 'memorial-plugin' ) . '</p>' );}
		?>
		</div>
		<label class="mrw__tabs"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[tabs]" value="1"<?php checked( ! empty( $val['tabs'] ) ); ?> data-rw-tabs> <?php esc_html_e( 'Every branch in its own tab (otherwise all reviews in one feed)', 'memorial-plugin' ); ?></label>
		<p class="mrw__foot"><span class="mrw__saved" aria-live="polite"></span>
		<button type="button" class="button-link" data-rw="catalog"><?php esc_html_e( 'Update the list of branches', 'memorial-plugin' ); ?></button>
		<span class="mrw__hint">(
		<?php
		echo esc_html(
			sprintf(
			/* translators: %s: when the list was received */
				__( 'list from Rewio: %s', 'memorial-plugin' ),
				memorial_plugin_rewio_when( $cat['time'] )
			)
		);
		?>
		)</span></p>
	</div>
	<?php
}

/* Экран настроек на DataForm: «2. Какие филиалы и площадки» — галочки по списку филиалов из Rewio */
add_filter(
	'memorial_plugin_settings_describe_field',
	function ( $field, $f ) {
		if ( ( $f['render'] ?? '' ) !== 'memorial_plugin_rewio_picker' ) {
			return $field;
		}
		$field['type'] = 'rewio-scope';
		$field['help'] = __( 'Tick the branches whose reviews to show (nothing ticked — all of them), and the sites of each branch.', 'memorial-plugin' );
		if ( trim( memorial_plugin_rewio()['api']['key'] ) === '' ) {
			$field['error'] = __( 'First paste the API key into “1. Connection” and save — your branches will appear here.', 'memorial-plugin' );
			return $field;
		}
		$cat = get_option( 'mem_rewio_catalog' );
		if ( ! is_array( $cat ) || empty( $cat['time'] ) ) {
			$cat = memorial_plugin_rewio_catalog_fetch();
		}
		if ( is_wp_error( $cat ) ) {
			$field['error'] = __( 'Could not get the list of branches: ', 'memorial-plugin' ) . $cat->get_error_message();
			return $field;
		}
		$field['catalog'] = array(
			'objects' => array_map(
				fn( $ob ) => array(
					'id'      => (int) $ob['id'],
					'name'    => (string) $ob['name'],
					'count'   => $ob['count'],
					'demo'    => ! empty( $ob['demo'] ),
					'sources' => array_map(
						'intval',
						(array) $ob['sources']
					),
				),
				(array) $cat['objects']
			),
			'sources' => array_map( 'strval', (array) ( $cat['all_sources'] ?? array() ) + (array) ( $cat['sources'] ?? array() ) ),
			'time'    => memorial_plugin_rewio_when( $cat['time'] ),
		);
		return $field;
	},
	10,
	2
);

/**
 * Styles and script of the Rewio panel — only on its settings screen (Memorial → Reviews → Rewio).
 *
 * @param string $hook_suffix Admin page hook.
 */
function memorial_plugin_rewio_admin_assets( $hook_suffix ) {
	if ( ! str_ends_with( (string) $hook_suffix, '_page_mem-rewio' ) ) {
		return;
	}
	$css = memorial_plugin_asset( '/assets/css/admin/rewio.css' );
	$js  = memorial_plugin_asset( '/assets/js/admin/rewio.js' );
	wp_enqueue_style( 'memorial-plugin-rewio-admin', MEMORIAL_PLUGIN_URL . $css, array(), (string) filemtime( MEMORIAL_PLUGIN_DIR . $css ) );
	wp_enqueue_script( 'memorial-plugin-rewio-admin', MEMORIAL_PLUGIN_URL . $js, array(), (string) filemtime( MEMORIAL_PLUGIN_DIR . $js ), array( 'in_footer' => true ) );
	wp_add_inline_script(
		'memorial-plugin-rewio-admin',
		'window.memorialPluginRewio = ' . wp_json_encode(
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'mem_rewio' ),
				't'     => array(
					'noAnswer'     => __( 'The site did not respond', 'memorial-plugin' ),
					'error'        => __( 'error', 'memorial-plugin' ),
					'timeout'      => __( 'most likely, the server ran out of time', 'memorial-plugin' ),
					'retry'        => __( 'Try again in a minute.', 'memorial-plugin' ),
					'loading'      => __( 'Loading reviews… usually it takes 10–30 seconds', 'memorial-plugin' ),
					'failed'       => __( 'Error', 'memorial-plugin' ),
					'saving'       => __( 'Saving…', 'memorial-plugin' ),
					'saved'        => __( 'Saved ✓ The reviews will update within a minute', 'memorial-plugin' ),
					'notSaved'     => __( 'Not saved', 'memorial-plugin' ),
					'notSavedSave' => __( 'Not saved — click “Save” at the bottom', 'memorial-plugin' ),
					'oneSite'      => __( 'At least one site must stay — otherwise untick the branch itself', 'memorial-plugin' ),
				),
			)
		) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'memorial_plugin_rewio_admin_assets' );

/* Состояние и предпросмотр — над настройками */
add_action(
	'memorial_plugin_admin_after_intro',
	function ( $opt ) {
		if ( 'mem_rewio' !== $opt ) {
			return;
		}
		$o      = memorial_plugin_rewio();
		$st     = get_option( 'mem_rewio_status', array() );
		$c      = memorial_plugin_rewio_cache_get();
		$hidden = array_map( 'intval', (array) get_option( 'mem_rewio_hidden', array() ) );
		$n      = 0;
		foreach ( $c['groups'] ?? array() as $g ) {
			$n += count( $g['reviews'] );
		}
		$once  = wp_next_scheduled( 'memorial_plugin_rewio_cron_once' );
		$next  = $once && $once > time() ? $once : wp_next_scheduled( 'memorial_plugin_rewio_cron' );
		$key   = trim( $o['api']['key'] ) !== '';
		$state = ! $key ? array( 'wait', __( 'Not connected — paste the API key below and save.', 'memorial-plugin' ) )
		: ( ! empty( $st ) && empty( $st['ok'] ) ? array( 'bad', __( 'Could not load the reviews: ', 'memorial-plugin' ) . $st['error'] )
		: ( $n ? array( 'ok', __( 'On the site ', 'memorial-plugin' ) . $n . ' ' . _n( 'review', 'reviews', $n, 'memorial-plugin' ) . ( empty( $o['api']['on'] ) ? __( ' — but showing is off (the tick in “1. Connection”)', 'memorial-plugin' ) : '' ) )
		: array( 'wait', __( 'The reviews are not loaded yet — click “Load reviews now”.', 'memorial-plugin' ) ) ) );
		?>
	<section class="mem-card mrw-state is-<?php echo esc_attr( $state[0] ); ?>">
		<div class="mrw-state__row">
		<span class="mrw-state__dot" aria-hidden="true"></span>
		<div class="mrw-state__txt">
			<b><?php echo esc_html( $state[1] ); ?></b>
			<?php if ( $key ) : ?>
			<span><?php esc_html_e( 'Updated:', 'memorial-plugin' ); ?> <?php echo esc_html( memorial_plugin_rewio_when( $c['time'] ?? 0 ) ); ?>
				<?php
				if ( $next ) {
					echo esc_html__( ' · next check: ', 'memorial-plugin' ) . esc_html( memorial_plugin_rewio_when( $next ) );}
				?>
			</span>
				<?php
				if ( ! empty( $st['log'] ) ) {
					echo '<span class="mrw-state__log">' . esc_html( implode( ' · ', $st['log'] ) ) . '</span>';}
				?>
			<?php endif; ?>
		</div>
		<?php
		if ( $key ) :
			?>
			<button type="button" class="button button-primary" data-rw="refresh"><?php esc_html_e( 'Load reviews now', 'memorial-plugin' ); ?></button><?php endif; ?>
		</div>
		<p class="mrw-state__msg" aria-live="polite"></p>
		<?php if ( $n ) : ?>
		<details class="mrw__prev">
		<summary><?php esc_html_e( 'View the loaded reviews and hide unneeded ones', 'memorial-plugin' ); ?><?php echo $hidden ? esc_html__( ' (hidden: ', 'memorial-plugin' ) . (int) count( $hidden ) . ')' : ''; ?></summary>
			<?php
			foreach ( $c['groups'] as $g ) :
				if ( $g['name'] ) {
					echo '<h3>' . esc_html( $g['name'] ) . '</h3>';
				}
				?>
			<table class="widefat striped"><tbody>
				<?php foreach ( $g['reviews'] as $r ) : ?>
			<tr<?php echo in_array( $r['id'], $hidden, true ) ? ' class="is-hid"' : ''; ?>>
				<td class="mrw__col-hide"><label><input type="checkbox" data-rw-hide="<?php echo (int) $r['id']; ?>"<?php checked( in_array( $r['id'], $hidden, true ) ); ?>> <?php esc_html_e( 'hide', 'memorial-plugin' ); ?></label></td>
				<td class="mrw__col-author"><b><?php echo esc_html( $r['author'] ); ?></b><br><span class="mrw__stars"><?php echo esc_html( str_repeat( '★', (int) round( (float) $r['rating'] ) ) ); ?></span><br><small><?php echo esc_html( ( $c['sources'][ $r['src'] ]['name'] ?? $r['slug'] ) . ' · ' . memorial_plugin_rewio_date( $r['date'] ) ); ?></small></td>
				<td><?php echo esc_html( wp_trim_words( $r['text'], 40, '…' ) ); ?>
					<?php
					if ( $r['imgs'] ) {
						echo ' <small>· ' . esc_html__( 'photos:', 'memorial-plugin' ) . ' ' . count( $r['imgs'] ) . '</small>';}
					?>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php endforeach; ?>
		</details>
		<?php endif; ?>
	</section>
		<?php
	}
);

add_action(
	'wp_ajax_mem_rewio_catalog',
	function () {
		check_ajax_referer( 'mem_rewio' );
		if ( ! memorial_plugin_can_screen( 'mem_rewio' ) ) {
			wp_send_json_error( __( 'Not allowed.', 'memorial-plugin' ) );
		}
		$c = memorial_plugin_rewio_catalog_fetch();
		is_wp_error( $c ) ? wp_send_json_error( $c->get_error_message() ) : wp_send_json_success();
	}
);

add_action(
	'wp_ajax_mem_rewio_scope',
	function () {
		check_ajax_referer( 'mem_rewio' );
		if ( ! memorial_plugin_can_screen( 'mem_rewio' ) ) {
			wp_send_json_error();
		}
		$o          = get_option( 'mem_rewio', array() );
		$o          = is_array( $o ) ? $o : array();
		$o['scope'] = array(
			'objects' => implode( ',', memorial_plugin_rewio_ids( wp_unslash( $_POST['objects'] ?? '' ) ) ),
			'sources' => implode( ',', memorial_plugin_rewio_ids( wp_unslash( $_POST['sources'] ?? '' ) ) ),
			'per'     => memorial_plugin_rewio_per_string( memorial_plugin_rewio_per( wp_unslash( $_POST['per'] ?? '' ) ) ),
			'tabs'    => empty( $_POST['tabs'] ) ? 0 : 1,
		);
		update_option( 'mem_rewio', $o ); // запустит фоновую загрузку отзывов.
		wp_send_json_success();
	}
);

add_action(
	'wp_ajax_mem_rewio_refresh',
	function () {
		check_ajax_referer( 'mem_rewio' );
		if ( ! memorial_plugin_can_screen( 'mem_rewio' ) ) {
			wp_send_json_error( __( 'Not allowed.', 'memorial-plugin' ) );
		}
		$r = memorial_plugin_rewio_refresh();
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		$n = 0;
		foreach ( memorial_plugin_rewio_cache_get()['groups'] ?? array() as $g ) {
			$n += count( $g['reviews'] );
		}
		wp_send_json_success( __( 'Done: loaded ', 'memorial-plugin' ) . $n . ' ' . _n( 'review', 'reviews', $n, 'memorial-plugin' ) . '.' );
	}
);

add_action(
	'wp_ajax_mem_rewio_hide',
	function () {
		check_ajax_referer( 'mem_rewio' );
		if ( ! memorial_plugin_can_screen( 'mem_rewio' ) ) {
			wp_send_json_error();
		}
		$id = absint( $_POST['id'] ?? 0 );
		if ( function_exists( 'memorial_plugin_rewio_set_hidden' ) ) {
			memorial_plugin_rewio_set_hidden( $id, ! empty( $_POST['on'] ) );
			wp_send_json_success();
		}
		$h = array_map( 'intval', (array) get_option( 'mem_rewio_hidden', array() ) );
		$h = ! empty( $_POST['on'] ) ? array_values( array_unique( array_merge( $h, array( $id ) ) ) ) : array_values( array_diff( $h, array( $id ) ) );
		update_option( 'mem_rewio_hidden', $h, false );
		wp_send_json_success();
	}
);

/* ---------- Вывод на сайте ---------- */
/**
 * Rewio date.
 *
 * @param mixed $iso Iso.
 */
function memorial_plugin_rewio_date( $iso ) {
	$t = strtotime( $iso );
	return $t ? wp_date( 'j F Y', $t ) : '';
}

/**
 * Rewio stars.
 *
 * @param mixed  $r R.
 * @param string $cls Cls.
 */
function memorial_plugin_rewio_stars( $r, $cls = 'rwo__stars' ) {
	$r = max( 0, min( 5, (float) $r ) );
	return '<span class="' . $cls . '" style="--r:' . round( $r / 5 * 100, 1 ) . '%" role="img" aria-label="' . esc_attr(
		sprintf(
		/* translators: %s: rating, for example 4,8 */
			__( 'Rating %s out of 5', 'memorial-plugin' ),
			rtrim( rtrim( number_format( $r, 1, ',', '' ), '0' ), ',' )
		)
	) . '"></span>';
}

/**
 * Rewio html.
 *
 * @param array $atts Shortcode attributes.
 */
function memorial_plugin_rewio_html( $atts = array() ) {
	$o = memorial_plugin_rewio();
	$v = $o['view'];
	$a = shortcode_atts(
		array(
			'count' => $v['count'],
			'title' => $v['title'],
			'cols'  => $v['cols'],
		),
		$atts,
		'mem_rewio'
	);
	if ( empty( $o['api']['on'] ) ) {
		return current_user_can( 'manage_options' ) ? ( '<p class="is-style-note">' . esc_html__( 'Rewio reviews: showing the reviews is off — the tick in “Memorial → Reviews → Rewio reviews → 1. Connection”. Only you see this message.', 'memorial-plugin' ) . '</p>' ) : '';
	}
	$c = memorial_plugin_rewio_cache_get();
	// давно не обновлялось (например, отключён WP-Cron) — обновим в фоне, страница не ждёт.
	$every = ( (int) $o['cache']['every'] ? (int) $o['cache']['every'] : 6 ) * HOUR_IN_SECONDS;
	$tried = (int) ( get_option( 'mem_rewio_status', array() )['time'] ?? 0 );
	if ( ! $tried || time() - $tried > $every * 2 ) {
		if ( ! wp_next_scheduled( 'memorial_plugin_rewio_cron_once' ) ) {
			wp_schedule_single_event( time(), 'memorial_plugin_rewio_cron_once' );
		}
	}
	if ( empty( $c['groups'] ) ) {
		return current_user_can( 'manage_options' ) ? ( '<p class="is-style-note">' . esc_html__( 'Rewio reviews: the reviews are not loaded yet — “Memorial → Reviews → Rewio reviews” → “Load reviews now”. Only you see this message.', 'memorial-plugin' ) . '</p>' ) : '';
	}

	$hidden = array_map( 'intval', (array) get_option( 'mem_rewio_hidden', array() ) );
	$stop   = array_filter( array_map( fn( $w ) => mb_strtolower( trim( $w ) ), explode( ',', (string) $o['filter']['stop'] ) ) );
	$src    = $c['sources'] ?? array();
	$clip   = max( 80, (int) $v['clip'] ? (int) $v['clip'] : 320 );
	$count  = max( 1, (int) $a['count'] ? (int) $a['count'] : 6 );

	$groups = array();
	foreach ( $c['groups'] as $g ) {
		$list = array_values(
			array_filter(
				$g['reviews'],
				function ( $r ) use ( $hidden, $stop ) {
					if ( in_array( $r['id'], $hidden, true ) ) {
						return false;
					}
					$t = mb_strtolower( $r['text'] . ' ' . $r['reply'] );
					foreach ( $stop as $w ) {
						if ( '' !== $w && false !== mb_strpos( $t, $w ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
		if ( 'best' === $v['sort'] ) {
			usort( $list, fn( $x, $y ) => array( $y['rating'], $y['date'] ) <=> array( $x['rating'], $x['date'] ) );
		} elseif ( 'photos' === $v['sort'] ) {
			usort( $list, fn( $x, $y ) => array( count( $y['imgs'] ) > 0, $y['date'] ) <=> array( count( $x['imgs'] ) > 0, $x['date'] ) );
		}
		if ( $list ) {
			$groups[] = $g + array( 'list' => $list );
		}
	}
	if ( ! $groups ) {
		return '';
	}

	$uid = 'rwo' . wp_rand( 100, 999 );
	$h   = '<section class="rwo rwo--c' . (int) $a['cols'] . '" data-rwo data-wp-interactive="memorial-plugin/rewio" data-wp-init="callbacks.init" data-step="' . $count . '">';
	if ( trim( $a['title'] ) !== '' ) {
		$h .= '<h2 class="rwo__title">' . esc_html( $a['title'] ) . '</h2>';
	}
	if ( count( $groups ) > 1 ) {
		$h .= ( '<div class="rwo__tabs srow"><div class="srow__in" role="tablist" aria-label="' . esc_attr__( 'Reviews by branch', 'memorial-plugin' ) . '">' );
		foreach ( $groups as $n => $g ) {
			$h .= '<button type="button" role="tab" class="chip rwo__tab" id="' . $uid . 't' . $n . '" aria-controls="' . $uid . 'p' . $n . '" aria-selected="' . ( $n ? 'false' : 'true' ) . '"' . ( $n ? ' tabindex="-1"' : '' ) . '>'
				. esc_html( $g['name'] ? $g['name'] : __( 'Branch ', 'memorial-plugin' ) . ( $n + 1 ) ) . ( isset( $g['summary']['avg'] ) && $g['summary']['avg'] ? ' <small>★ ' . esc_html( number_format( (float) $g['summary']['avg'], 1, ',', '' ) ) . '</small>' : '' ) . '</button>';
		}
		$h .= '</div></div>';
	}
	foreach ( $groups as $n => $g ) {
		$h .= '<div class="rwo__panel" id="' . $uid . 'p' . $n . '"' . ( count( $groups ) > 1 ? ' role="tabpanel" aria-labelledby="' . $uid . 't' . $n . '"' : '' ) . ( $n ? ' hidden' : '' ) . '>';
		// сводка.
		$s = $g['summary'] ?? null;
		if ( ! empty( $v['summary'] ) && $s && $s['avg'] ) {
			$h .= '<div class="rwo__sum"><div class="rwo__avg"><b>' . esc_html( number_format( (float) $s['avg'], 1, ',', '' ) ) . '</b>' . memorial_plugin_rewio_stars( $s['avg'], 'rwo__stars rwo__stars--lg' )
				. '<span>' . (int) $s['count'] . ' ' . esc_html( _n( 'review', 'reviews', (int) $s['count'], 'memorial-plugin' ) ) . '</span></div>';
			// площадки: оценка и число отзывов; при включённом фильтре — нажатие показывает отзывы только этой площадки.
			$loaded = array();
			foreach ( $g['list'] as $r ) {
				if ( $r['src'] ) {
					$loaded[ $r['src'] ] = ( $loaded[ $r['src'] ] ?? 0 ) + 1;
				}
			}
			$filter = ! empty( $v['pfilter'] ) && count( $loaded ) > 1;
			$by     = array_values( array_filter( $s['by'], fn( $b ) => $b['count'] ) );
			if ( $by && ( count( $by ) > 1 || count( $groups ) === 1 ) ) {
				$h .= '<div class="rwo__by"' . ( $filter ? ( ' role="group" aria-label="' . esc_attr__( 'Show the reviews of a site', 'memorial-plugin' ) . '"' ) : '' ) . '>';
				foreach ( $by as $b ) {
					$nm    = $b['name'];
					$inner = memorial_plugin_rewio_icon( $src[ $b['id'] ]['slug'] ?? '', $nm ) . '<span>' . esc_html( $nm ) . '</span> <b>' . esc_html( $b['avg'] ? number_format( (float) $b['avg'], 1, ',', '' ) : '—' ) . '</b><i aria-hidden="true">★</i> <small>' . (int) $b['count'] . '</small>';
					$h    .= $filter && isset( $loaded[ $b['id'] ] )
						? '<button type="button" class="rwo__pl" data-rwo-src="' . (int) $b['id'] . ( '" aria-pressed="false" title="' . esc_attr__( 'Show only the reviews of: ', 'memorial-plugin' ) ) . esc_attr( $nm ) . '">' . $inner . '</button>'
						: '<span class="rwo__pl">' . $inner . '</span>';
				}
				$h .= '</div>';
			}
			$h .= '</div>';
		}
		// сортировка для посетителей — справа над отзывами.
		$note = '';
		if ( ! empty( $v['controls'] ) ) {
			$init = 'best' === $v['sort'] ? 'r' : ( 'new' === $v['sort'] ? 'd' : '' );
			$h   .= ( '<div class="rwo__ctl"><p class="rwo__shown" aria-live="polite"></p><div class="rwo__sort" role="group" aria-label="' . esc_attr__( 'Sorting', 'memorial-plugin' ) . '"><span>' . esc_html__( 'Sorting:', 'memorial-plugin' ) . '</span>' )
				. '<button type="button" data-rwo-sort="d" aria-pressed="' . ( 'd' === $init ? 'true' : 'false' ) . ( '" title="' . esc_attr__( 'Sort by date', 'memorial-plugin' ) . '">' . esc_html__( 'by date', 'memorial-plugin' ) . ' <i aria-hidden="true">↓</i></button>' )
				. '<button type="button" data-rwo-sort="r" aria-pressed="' . ( 'r' === $init ? 'true' : 'false' ) . ( '" title="' . esc_attr__( 'Sort by rating', 'memorial-plugin' ) . '">' . esc_html__( 'by rating', 'memorial-plugin' ) . ' <i aria-hidden="true">↓</i></button></div></div>' );
		} elseif ( ! empty( $v['pfilter'] ) ) {
			$h .= '<div class="rwo__ctl"><p class="rwo__shown" aria-live="polite"></p></div>';
		}
		$h .= '<div class="rwo__list">';
		foreach ( $g['list'] as $i => $r ) {
			$name = $src[ $r['src'] ]['name'] ?? '';
			$ini  = (string) preg_replace( '/[^\p{L}]/u', '', $r['author'] );
			$ini  = mb_strtoupper( mb_substr( '' !== $ini ? $ini : '?', 0, 1 ) );
			$hue  = crc32( $r['author'] ) % 360;
			$long = mb_strlen( $r['text'] ) > $clip;
			$h   .= '<article class="rwo__card" data-d="' . esc_attr( substr( $r['date'], 0, 19 ) ) . '" data-r="' . esc_attr( (string) (float) $r['rating'] ) . '" data-s="' . (int) $r['src'] . '"' . ( $i >= $count ? ' hidden data-more' : '' ) . '>';
			$h   .= '<header class="rwo__head"><span class="rwo__ava" style="--h:' . (int) $hue . '" aria-hidden="true">' . esc_html( $ini ) . '</span>'
				. '<span class="rwo__who"><b>' . esc_html( $r['author'] ) . '</b><span class="rwo__line">'
				. ( $r['rating'] ? memorial_plugin_rewio_stars( $r['rating'] ) : '' )
				. ( ! empty( $v['date'] ) && $r['date'] ? '<time datetime="' . esc_attr( substr( $r['date'], 0, 10 ) ) . '">' . esc_html( memorial_plugin_rewio_date( $r['date'] ) ) . '</time>' : '' ) . '</span></span></header>';
			if ( '' !== $r['text'] ) {
				$h .= '<div class="rwo__text' . ( $long ? ' is-clip' : '' ) . '">' . wpautop( esc_html( $r['text'] ) ) . '</div>'
				. ( $long ? ( '<button type="button" class="rwo__more" aria-expanded="false">' . esc_html__( 'Read more', 'memorial-plugin' ) . '</button>' ) : '' );
			}
			if ( ! empty( $v['photo'] ) && $r['imgs'] ) {
				$h .= '<div class="rwo__imgs">';
				foreach ( $r['imgs'] as $im ) {
					$h .= '<a href="' . esc_url( $im['b'] ) . '" data-lb><img src="' . esc_url( $im['s'] ) . ( '" alt="' . esc_attr__( 'Photo for the review', 'memorial-plugin' ) . '" loading="lazy" decoding="async" referrerpolicy="no-referrer"></a>' );
				}
				$h .= '</div>';
			}
			if ( ! empty( $v['reply'] ) && '' !== $r['reply'] ) {
				$h .= ( '<details class="rwo__reply"><summary>' . esc_html__( 'Company’s reply', 'memorial-plugin' ) . '</summary>' ) . wpautop( esc_html( $r['reply'] ) ) . '</details>';
			}
			if ( ! empty( $v['source'] ) && $name ) {
				$ico = memorial_plugin_rewio_icon( $r['slug'] ? $r['slug'] : ( $src[ $r['src'] ]['slug'] ?? '' ), $name );
				// No link of the review itself — the site's page of the company, at least the review site.
				$url = '' !== $r['url'] ? $r['url'] : memorial_plugin_rewio_src_url( (int) $r['src'], $src );
				$h  .= '<footer class="rwo__src">'
					. ( $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . $ico . esc_html( $name ) . ' ↗</a>' : $ico . esc_html( $name ) ) . '</footer>';
			}
			$h .= '</article>';
		}
		$h .= '</div>';
		if ( count( $g['list'] ) > $count ) {
			$h .= ( '<p class="rwo__morewrap"><button type="button" class="btn btn--line rwo__showmore">' . esc_html__( 'Show more reviews', 'memorial-plugin' ) . '</button></p>' );
		}
		$h .= '</div>';
	}
	return $h . '</section>';
}
add_shortcode( 'mem_rewio', 'memorial_plugin_rewio_html' );
