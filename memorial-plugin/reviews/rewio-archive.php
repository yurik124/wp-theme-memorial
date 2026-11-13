<?php
/**
 * Отзывы Rewio: своя копия каждого отзыва (запись mem_rewio) и «Мемориал → Список отзывов».
 *
 * При каждой загрузке отзывы сравниваются с копией:
 *  - новый — добавляется (его первый вариант запоминается навсегда);
 *  - изменился текст, оценка, ответ компании или пропала ссылка на источник — пометка «изменён на
 *    площадке», первый вариант остаётся, показывается новый (администратор может выбрать первый);
 *  - пропал с площадки — пометка «удалён на площадке», копия остаётся; показывать ли такие отзывы —
 *    общая галочка на странице списка (по умолчанию — нет).
 * Скрытый отзыв — запись в статусе «черновик» (и номер в mem_rewio_hidden, который читает вывод).
 * Сайт по-прежнему показывает готовый набор mem_rewio_cache — он собирается из копий после загрузки и
 * после каждой правки, поэтому страницы не ходят в базу за каждым отзывом.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Type of the copies (no screens of its own: the list is a DataViews screen). */
function memorial_plugin_rewio_type() {
	$cap = 'memorial_plugin_reviews';
	register_post_type(
		'mem_rewio',
		array(
			'label'        => __( 'Review list', 'memorial-plugin' ),
			'public'       => false,
			'show_ui'      => false,
			'show_in_rest' => false,
			'rewrite'      => false,
			'query_var'    => false,
			'supports'     => array( 'title', 'editor', 'custom-fields' ),
			'map_meta_cap' => false,
			'capabilities' => array_fill_keys(
				array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'edit_published_posts', 'delete_published_posts', 'create_posts' ),
				$cap
			),
		)
	);
}
add_action( 'init', 'memorial_plugin_rewio_type' );

/**
 * JSON with only ASCII (emoji in reviews: many hosting databases do not take them).
 *
 * @param mixed $v Value.
 * @return string
 */
function memorial_plugin_rewio_json( $v ) {
	return (string) wp_json_encode( $v );
}

/**
 * Copies of the reviews by the Rewio id: id => WP_Post (all statuses).
 *
 * @return array<int,WP_Post>
 */
function memorial_plugin_rewio_copies() {
	$posts = get_posts(
		array(
			'post_type'              => 'mem_rewio',
			'post_status'            => array( 'publish', 'draft' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);
	$out = array();
	foreach ( $posts as $p ) {
		$out[ (int) get_post_meta( $p->ID, '_rw_id', true ) ] = $p;
	}
	unset( $out[0] );
	return $out;
}

/**
 * Saved data of a copy: current (as on the site now) or first (as first loaded).
 *
 * @param int    $post_id Post ID.
 * @param string $which   'data' or 'first'.
 * @return array
 */
function memorial_plugin_rewio_copy_data( $post_id, $which = 'data' ) {
	$v = json_decode( (string) get_post_meta( $post_id, '_rw_' . $which, true ), true );
	return is_array( $v ) ? $v : array();
}

/**
 * What tells two versions apart: text, rating, company reply, and the link to the source disappearing.
 *
 * @param array $old Saved version.
 * @param array $new Loaded version.
 * @return bool
 */
function memorial_plugin_rewio_differs( $old, $new ) {
	foreach ( array( 'text', 'reply' ) as $k ) {
		if ( trim( (string) ( $old[ $k ] ?? '' ) ) !== trim( (string) ( $new[ $k ] ?? '' ) ) ) {
			return true;
		}
	}
	if ( (float) ( $old['rating'] ?? 0 ) !== (float) ( $new['rating'] ?? 0 ) ) {
		return true;
	}
	return '' !== (string) ( $old['url'] ?? '' ) && '' === (string) ( $new['url'] ?? '' );
}

/**
 * Write a copy (new or changed): the list shows the title, text, rating, site and state.
 *
 * @param int    $post_id Post ID (0 — new).
 * @param array  $r       Review (memorial_plugin_rewio_norm()).
 * @param string $site    Name of the review site.
 * @param bool   $hidden  Hidden on the site.
 * @return int Post ID.
 */
function memorial_plugin_rewio_write( $post_id, $r, $site, $hidden = false ) {
	$post = array(
		'post_type'    => 'mem_rewio',
		'post_title'   => (string) $r['author'],
		'post_content' => (string) $r['text'],
	);
	$ts = strtotime( (string) $r['date'] );
	if ( $ts ) {
		$post['post_date']     = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ) );
		$post['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $ts );
	}
	if ( $post_id ) {
		$post['ID'] = $post_id;
		wp_update_post( wp_slash( $post ) );
	} else {
		$post['post_status'] = $hidden ? 'draft' : 'publish';
		$post_id             = (int) wp_insert_post( wp_slash( $post ) );
		if ( ! $post_id ) {
			return 0;
		}
		update_post_meta( $post_id, '_rw_id', (int) $r['id'] );
		update_post_meta( $post_id, '_rw_first', wp_slash( memorial_plugin_rewio_json( $r ) ) );
	}
	unset( $r['obj'] );
	update_post_meta( $post_id, '_rw_data', wp_slash( memorial_plugin_rewio_json( $r ) ) );
	update_post_meta( $post_id, '_rw_rating', null === $r['rating'] ? '' : (string) $r['rating'] );
	update_post_meta( $post_id, '_rw_site', wp_slash( $site ) );
	return $post_id;
}

/**
 * State of a copy for the list: '' (as on the site), 'changed' or 'gone'.
 *
 * @param int $post_id Post ID.
 */
function memorial_plugin_rewio_state_update( $post_id ) {
	$state = get_post_meta( $post_id, '_rw_gone', true ) ? 'gone' : ( get_post_meta( $post_id, '_rw_changed', true ) ? 'changed' : '' );
	'' === $state ? delete_post_meta( $post_id, '_rw_state' ) : update_post_meta( $post_id, '_rw_state', $state );
}

/**
 * Could the review be in the loaded list, if it were still on the site? Only then its absence means it is
 * gone: a review older than the loaded window, of a site or rating that is not asked, is simply not asked.
 *
 * @param array $r      Saved review.
 * @param array $f      What was asked of its branch: n, limit, oldest, sids.
 * @param array $common Filters of the request: min_rating, has_text, has_images, published_from.
 * @return bool
 */
function memorial_plugin_rewio_was_asked( $r, $f, $common ) {
	if ( ! empty( $f['sids'] ) && ! in_array( (int) ( $r['src'] ?? 0 ), array_map( 'intval', $f['sids'] ), true ) ) {
		return false;
	}
	if ( null !== ( $r['rating'] ?? null ) && (float) $r['rating'] < (float) ( $common['min_rating'] ?? 1 ) ) {
		return false;
	}
	if ( ! empty( $common['has_text'] ) && '' === trim( (string) ( $r['text'] ?? '' ) ) ) {
		return false;
	}
	if ( ! empty( $common['has_images'] ) && empty( $r['imgs'] ) ) {
		return false;
	}
	$date = (string) ( $r['date'] ?? '' );
	if ( ! empty( $common['published_from'] ) && '' !== $date && substr( $date, 0, 10 ) < $common['published_from'] ) {
		return false;
	}
	// The list is full — older reviews than its last one are beyond it, not gone.
	return (int) $f['n'] < (int) $f['limit'] || ( '' !== $date && '' !== (string) $f['oldest'] && $date >= $f['oldest'] );
}

/**
 * Compare the loaded reviews with the copies and build what the site shows.
 *
 * @param array $groups Loaded groups (name, summary, reviews with 'obj').
 * @param array $fetch  What was asked of every branch (object id => n, limit, oldest, sids).
 * @param array $common Filters of the request.
 * @return array Groups to show.
 */
function memorial_plugin_rewio_archive( $groups, $fetch, $common ) {
	$copies = memorial_plugin_rewio_copies();
	$hidden = array_map( 'intval', (array) get_option( 'mem_rewio_hidden', array() ) );
	$names  = memorial_plugin_rewio_site_names();
	$now    = time();
	$seen   = array();
	foreach ( $groups as $gi => $g ) {
		$ids = array();
		foreach ( $g['reviews'] as $r ) {
			$rid = (int) $r['id'];
			if ( ! $rid ) {
				continue;
			}
			$ids[]        = $rid;
			$seen[ $rid ] = true;
			$site         = $names[ (int) $r['src'] ] ?? '';
			if ( ! isset( $copies[ $rid ] ) ) {
				$pid = memorial_plugin_rewio_write( 0, $r, $site, in_array( $rid, $hidden, true ) );
			} else {
				$pid = $copies[ $rid ]->ID;
				$old = memorial_plugin_rewio_copy_data( $pid );
				if ( memorial_plugin_rewio_differs( $old, $r ) ) {
					memorial_plugin_rewio_write( $pid, $r, $site );
					update_post_meta( $pid, '_rw_changed', $now );
				} elseif ( array_diff_key( $r, array( 'obj' => 1 ) ) !== $old ) {
					// Photos, reply date and the like: kept up to date without the “changed” mark.
					update_post_meta( $pid, '_rw_data', wp_slash( memorial_plugin_rewio_json( array_diff_key( $r, array( 'obj' => 1 ) ) ) ) );
				}
				delete_post_meta( $pid, '_rw_gone' ); // back on the site.
			}
			if ( $pid ) {
				update_post_meta( $pid, '_rw_obj', (int) $r['obj'] );
				update_post_meta( $pid, '_rw_group', wp_slash( (string) $g['name'] ) );
				memorial_plugin_rewio_state_update( $pid );
			}
		}
		$groups[ $gi ]['ids'] = $ids;
	}
	// Gone from the site: was in the asked part of its branch and is not there now.
	foreach ( $copies as $rid => $p ) {
		if ( isset( $seen[ $rid ] ) || get_post_meta( $p->ID, '_rw_gone', true ) ) {
			continue;
		}
		$obj = (int) get_post_meta( $p->ID, '_rw_obj', true );
		if ( isset( $fetch[ $obj ] ) && memorial_plugin_rewio_was_asked( memorial_plugin_rewio_copy_data( $p->ID ), $fetch[ $obj ], $common ) ) {
			update_post_meta( $p->ID, '_rw_gone', $now );
			memorial_plugin_rewio_state_update( $p->ID );
		}
	}
	return memorial_plugin_rewio_fill( $groups );
}

/**
 * Names of the review sites: id => name.
 *
 * @return array<int,string>
 */
function memorial_plugin_rewio_site_names() {
	$cat = (array) get_option( 'mem_rewio_catalog', array() );
	return array_map( 'strval', (array) ( $cat['all_sources'] ?? ( $cat['sources'] ?? array() ) ) );
}

/**
 * Reviews of the groups from the copies: the chosen version, the own link, gone reviews if they are shown.
 *
 * @param array $groups Groups with 'ids' (reviews present on the site at the last loading).
 * @return array
 */
function memorial_plugin_rewio_fill( $groups ) {
	if ( ! $groups ) {
		return $groups;
	}
	$copies = memorial_plugin_rewio_copies();
	$gone   = array();
	if ( get_option( 'mem_rewio_show_gone' ) ) {
		foreach ( $copies as $rid => $p ) {
			if ( get_post_meta( $p->ID, '_rw_gone', true ) ) {
				$gone[ (string) get_post_meta( $p->ID, '_rw_group', true ) ][] = $rid;
			}
		}
	}
	$names = array_column( $groups, 'name' );
	foreach ( $gone as $name => $rids ) {
		$gi                     = array_search( (string) $name, $names, true );
		$gi                     = false === $gi ? 0 : $gi;
		$groups[ $gi ]['ids']   = $groups[ $gi ]['ids'] ?? array();
		$groups[ $gi ]['ids']   = array_merge( $groups[ $gi ]['ids'], $rids );
		$groups[ $gi ]['ids']   = array_values( array_unique( $groups[ $gi ]['ids'] ) );
		$groups[ $gi ]['extra'] = true;
	}
	foreach ( $groups as &$g ) {
		$list = array();
		foreach ( (array) ( $g['ids'] ?? array() ) as $rid ) {
			if ( ! isset( $copies[ $rid ] ) ) {
				continue;
			}
			$pid = $copies[ $rid ]->ID;
			$r   = memorial_plugin_rewio_copy_data( $pid );
			if ( ! $r ) {
				continue;
			}
			if ( 'first' === get_post_meta( $pid, '_rw_use', true ) ) {
				$first = memorial_plugin_rewio_copy_data( $pid, 'first' );
				foreach ( array( 'author', 'text', 'rating', 'reply', 'rdate', 'url' ) as $k ) {
					if ( array_key_exists( $k, $first ) ) {
						$r[ $k ] = $first[ $k ];
					}
				}
			}
			$own = (string) get_post_meta( $pid, '_rw_link', true );
			if ( '' !== $own ) {
				$r['url'] = $own;
			}
			$list[] = $r;
		}
		usort( $list, fn( $a, $b ) => strcmp( (string) $b['date'], (string) $a['date'] ) );
		$g['reviews'] = $list;
		unset( $g['extra'] );
	}
	unset( $g );
	return $groups;
}

/**
 * Build the shown set again from the copies (after a change on the list or of the gone setting) — without
 * asking Rewio.
 */
function memorial_plugin_rewio_rebuild() {
	$c = memorial_plugin_rewio_cache_get();
	if ( empty( $c['groups'] ) ) {
		return;
	}
	foreach ( $c['groups'] as &$g ) {
		if ( ! isset( $g['ids'] ) ) {
			$g['ids'] = array_map( static fn( $r ) => (int) $r['id'], (array) ( $g['reviews'] ?? array() ) );
		}
	}
	unset( $g );
	$c['groups'] = memorial_plugin_rewio_fill( $c['groups'] );
	memorial_plugin_rewio_cache_set( $c );
	if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
		memorial_plugin_page_cache_clear();
	}
}

/**
 * Hide or show a review (by the Rewio id or the post ID of its copy).
 *
 * @param int  $id   Rewio id or post ID.
 * @param bool $hide Hide.
 * @return bool Done.
 */
function memorial_plugin_rewio_set_hidden( $id, $hide ) {
	$id   = (int) $id;
	$post = get_post( $id );
	if ( ! $post || 'mem_rewio' !== $post->post_type ) {
		$copies = memorial_plugin_rewio_copies();
		$post   = $copies[ $id ] ?? null;
	}
	$rid = $post ? (int) get_post_meta( $post->ID, '_rw_id', true ) : $id;
	$was  = $post && 'draft' === $post->post_status;
	$want = (bool) $hide;
	if ( $post && $want !== $was ) {
		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => $hide ? 'draft' : 'publish',
			)
		);
	}
	if ( ! $rid ) {
		return false;
	}
	$h = array_map( 'intval', (array) get_option( 'mem_rewio_hidden', array() ) );
	$h = $hide ? array_values( array_unique( array_merge( $h, array( $rid ) ) ) ) : array_values( array_diff( $h, array( $rid ) ) );
	update_option( 'mem_rewio_hidden', $h, false ); // the output reads it; its change clears the page cache.
	return true;
}

/**
 * Once: copies of the reviews that are on the site now (from the shown set), the hidden ones as drafts —
 * so the list is full at once and the next loading compares with them.
 */
function memorial_plugin_rewio_archive_import() {
	if ( get_option( 'memorial_plugin_rewio_archive' ) ) {
		return;
	}
	update_option( 'memorial_plugin_rewio_archive', 1 );
	$c = memorial_plugin_rewio_cache_get();
	if ( empty( $c['groups'] ) ) {
		return;
	}
	$copies = memorial_plugin_rewio_copies();
	$hidden = array_map( 'intval', (array) get_option( 'mem_rewio_hidden', array() ) );
	$names  = memorial_plugin_rewio_site_names();
	foreach ( $c['groups'] as $g ) {
		foreach ( (array) ( $g['reviews'] ?? array() ) as $r ) {
			$rid = (int) ( $r['id'] ?? 0 );
			if ( ! $rid || isset( $copies[ $rid ] ) ) {
				continue;
			}
			$pid = memorial_plugin_rewio_write( 0, $r, $names[ (int) ( $r['src'] ?? 0 ) ] ?? ( $c['sources'][ (int) ( $r['src'] ?? 0 ) ]['name'] ?? '' ), in_array( $rid, $hidden, true ) );
			if ( $pid ) {
				update_post_meta( $pid, '_rw_group', wp_slash( (string) ( $g['name'] ?? '' ) ) );
			}
		}
	}
}
add_action( 'admin_init', 'memorial_plugin_rewio_archive_import' );

/* ---------- «Мемориал → Список отзывов» (DataViews, admin/collections.php) ---------- */

/**
 * The list as a collection: everyone with the right sees and hides / shows reviews; the version and the own
 * link are for administrators.
 *
 * @param array $cols Collections.
 * @return array
 */
function memorial_plugin_rewio_collection( $cols ) {
	$sites = array_values( array_unique( array_filter( memorial_plugin_rewio_site_names() ) ) );
	$cols['rewio'] = array(
		'post_type' => 'mem_rewio',
		'title'     => __( 'Review list', 'memorial-plugin' ),
		'intro'     => __( 'Reviews loaded from Yandex Maps, 2GIS and other sites through Rewio. A hidden review is not shown on the site; the site keeps its copy, so a review changed or deleted on the review site stays here with a mark.', 'memorial-plugin' ),
		'cap'       => 'memorial_plugin_reviews',
		'create'    => false,
		'trash'     => false,
		'sort'      => array(
			'field'     => 'date',
			'direction' => 'desc',
		),
		'toggle'    => array(
			'option' => 'mem_rewio_show_gone',
			'label'  => __( 'Show reviews deleted on the review site', 'memorial-plugin' ),
			'cap'    => 'manage_options',
		),
		'fields'    => array(
			'author' => array(
				'label'    => __( 'Author', 'memorial-plugin' ),
				'type'     => 'text',
				'source'   => 'title',
				'readonly' => true,
			),
			'text'   => array(
				'label'    => __( 'Review', 'memorial-plugin' ),
				'type'     => 'textarea',
				'source'   => 'content',
				'readonly' => true,
			),
			'rating' => array(
				'label'    => __( 'Rating', 'memorial-plugin' ),
				'type'     => 'integer',
				'source'   => 'meta:_rw_rating',
				'readonly' => true,
			),
			'site'   => array(
				'label'    => __( 'Site', 'memorial-plugin' ),
				'type'     => 'select',
				'source'   => 'meta:_rw_site',
				'readonly' => true,
				'options'  => $sites ? array_combine( $sites, $sites ) : array(),
			),
			'date'   => array(
				'label'    => __( 'Date', 'memorial-plugin' ),
				'type'     => 'datetime',
				'source'   => 'date',
				'readonly' => true,
			),
			'state'  => array(
				'label'    => __( 'On the review site', 'memorial-plugin' ),
				'type'     => 'select',
				'source'   => 'meta:_rw_state',
				'readonly' => true,
				'options'  => array(
					''        => __( 'As loaded', 'memorial-plugin' ),
					'changed' => __( 'Changed on the review site', 'memorial-plugin' ),
					'gone'    => __( 'Deleted on the review site', 'memorial-plugin' ),
				),
			),
			'status' => array(
				'label'   => __( 'On our site', 'memorial-plugin' ),
				'type'    => 'select',
				'source'  => 'status',
				'options' => array(
					'publish' => __( 'Shown', 'memorial-plugin' ),
					'draft'   => __( 'Hidden', 'memorial-plugin' ),
				),
			),
			'first'  => array(
				'label'    => __( 'First version', 'memorial-plugin' ),
				'type'     => 'textarea',
				'source'   => 'meta:_rw_first_text',
				'readonly' => true,
				'hidden'   => true,
			),
			'use'    => array(
				'label'   => __( 'Which version to show', 'memorial-plugin' ),
				'type'    => 'select',
				'source'  => 'meta:_rw_use',
				'cap'     => 'manage_options',
				'hidden'  => true,
				'options' => array(
					''      => __( 'As on the review site now', 'memorial-plugin' ),
					'first' => __( 'The first version', 'memorial-plugin' ),
				),
			),
			'link'   => array(
				'label'  => __( 'Own link to the source', 'memorial-plugin' ),
				'type'   => 'url',
				'source' => 'meta:_rw_link',
				'cap'    => 'manage_options',
				'hidden' => true,
				'help'   => __( 'Empty — the link of the review site (or the company page there).', 'memorial-plugin' ),
			),
		),
	);
	return $cols;
}
add_filter( 'memorial_plugin_collections', 'memorial_plugin_rewio_collection' );

/**
 * The first version as text for the list: rating, text and the company reply.
 *
 * @param mixed  $value   Value.
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return mixed
 */
function memorial_plugin_rewio_first_text( $value, $post_id, $key ) {
	if ( '_rw_first_text' !== $key || 'mem_rewio' !== get_post_type( $post_id ) ) {
		return $value;
	}
	if ( ! get_post_meta( $post_id, '_rw_changed', true ) ) {
		return array( '' );
	}
	$f = memorial_plugin_rewio_copy_data( $post_id, 'first' );
	$t = trim( (string) ( $f['text'] ?? '' ) );
	if ( null !== ( $f['rating'] ?? null ) ) {
		$t = '★ ' . $f['rating'] . ( '' !== $t ? "\n" . $t : '' );
	}
	if ( '' !== trim( (string) ( $f['reply'] ?? '' ) ) ) {
		$t .= "\n\n" . __( 'Company’s reply', 'memorial-plugin' ) . ': ' . trim( (string) $f['reply'] );
	}
	if ( '' !== (string) ( $f['url'] ?? '' ) ) {
		$t .= "\n\n" . $f['url'];
	}
	return array( $t );
}
add_filter( 'get_post_metadata', 'memorial_plugin_rewio_first_text', 10, 3 );

/**
 * After a change on the list: the hidden list and the shown set follow.
 *
 * @param string $name    Collection.
 * @param int    $post_id Post ID.
 * @param array  $values  Changed values.
 */
function memorial_plugin_rewio_saved( $name, $post_id, $values ) {
	if ( 'rewio' !== $name ) {
		return;
	}
	if ( array_key_exists( 'status', $values ) ) {
		$hide = 'draft' === get_post_status( $post_id );
		memorial_plugin_rewio_set_hidden( $post_id, $hide );
	}
	if ( array_key_exists( 'use', $values ) || array_key_exists( 'link', $values ) ) {
		memorial_plugin_rewio_rebuild();
	}
}
add_action( 'memorial_plugin_collection_saved', 'memorial_plugin_rewio_saved', 10, 3 );

// The gone setting changed — the shown set follows at once.
add_action( 'update_option_mem_rewio_show_gone', 'memorial_plugin_rewio_rebuild' );
add_action( 'add_option_mem_rewio_show_gone', 'memorial_plugin_rewio_rebuild' );

/* ---------- Способность: скрыть / показать отзыв (кнопки списка, палитра) ---------- */
add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		wp_register_ability(
			'memorial-plugin/set-review-hidden',
			array(
				'label'               => __( 'Hide or show a review', 'memorial-plugin' ),
				'description'         => __( 'Hides a Rewio review on the site or shows it again; the review stays in the list.', 'memorial-plugin' ),
				'category'            => 'memorial',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'ID of the review in the list.', 'memorial-plugin' ),
						),
						'hidden' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer' ),
						'hidden' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => static fn( $input ) => current_user_can( 'memorial_plugin_reviews' ) && 'mem_rewio' === get_post_type( (int) ( $input['id'] ?? 0 ) ),
				'execute_callback'    => static function ( $input ) {
					$id   = (int) $input['id'];
					$hide = ! isset( $input['hidden'] ) || ! empty( $input['hidden'] );
					memorial_plugin_rewio_set_hidden( $id, $hide );
					return array(
						'id'     => $id,
						'hidden' => 'draft' === get_post_status( $id ),
					);
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}
);
