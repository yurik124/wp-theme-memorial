<?php
/**
 * «Каталог → Перенос со страниц»: один раз превращает галереи-карточки на страницах в товары,
 * а примеры работ (заголовок + фото + таблица цен) — в «Наши работы».
 * По желанию сразу заменяет галереи на странице блоками каталога и работ
 * (прежний вариант страницы остаётся в её «Редакциях»).
 *
 * Экран — DataViews (src/import): список страниц из REST memorial-plugin/v1/import, перенос — способность
 * memorial-plugin/import-from-pages (Abilities API).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		$hook = add_submenu_page( 'options.php', __( 'Moving from pages', 'memorial-plugin' ), __( 'Moving from pages', 'memorial-plugin' ), 'manage_options', 'mem-import', 'memorial_plugin_import_page' );
		if ( $hook ) {
			add_action( 'admin_enqueue_scripts', static fn( $suffix ) => $hook === $suffix && memorial_plugin_import_assets() );
		}
	}
);

/* ---------- Разбор ---------- */

/**
 * Txt.
 *
 * @param string $html Html.
 */
function memorial_plugin_txt( $html ) {
	$t = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return trim( preg_replace( '/[\s\x{00A0}\x{202F}]+/u', ' ', $t ) );
}

/** Подпись под фото → [название, подпись, цена, «от», единица]
 *
 * @param string $html Html.
 */
function memorial_plugin_parse_caption( $html ) {
	$strong = preg_match( '~<strong>(.*?)</strong>~su', $html, $m ) ? memorial_plugin_txt( $m[1] ) : '';
	$rest   = memorial_plugin_txt( preg_replace( '~<strong>.*?</strong>~su', ' · ', $html ) );
	$parts  = array_values( array_filter( array_map( 'trim', array_merge( array( $strong ), preg_split( '/\s*·\s*/u', $rest ) ) ), 'strlen' ) );
	$r      = array(
		'name'  => '',
		'sub'   => array(),
		'price' => 0,
		'from'  => false,
		'unit'  => '',
	);
	foreach ( $parts as $p ) {
		if ( ! $r['price'] && preg_match( '/^(от\s+)?([\d\s]+)\s*₽\s*(.*)$/u', $p, $pm ) ) {
			$r['price'] = (int) preg_replace( '/\D/', '', $pm[2] );
			$r['from']  = '' !== $pm[1];
			$r['unit']  = trim( $pm[3] );
		} elseif ( '' === $r['name'] ) {
			$r['name'] = $p;
		} else {
			$r['sub'][] = $p;
		}
	}
	$r['name'] = preg_replace( '/№\s*/u', "№\u{00A0}", $r['name'] );
	$r['sub']  = implode( ' · ', $r['sub'] );
	return $r;
}

/**
 * Block class.
 *
 * @param mixed $b B.
 */
function memorial_plugin_block_class( $b ) {
	return ' ' . ( $b['attrs']['className'] ?? '' ) . ' '; }

/** Галереи-карточки страницы: [[heading, h_index, g_index, items, ratio, cols, intro, anchor, checked], …]
 *
 * @param mixed $blocks Blocks.
 */
function memorial_plugin_scan_galleries( $blocks ) {
	$out  = array();
	$head = null;
	foreach ( $blocks as $i => $b ) {
		if ( 'core/heading' === $b['blockName'] ) {
			$head = array(
				'text'   => memorial_plugin_txt( $b['innerHTML'] ),
				'i'      => $i,
				'anchor' => preg_match( '~id="([^"]+)"~', $b['innerHTML'], $m ) ? $m[1] : '',
			);
			continue;
		}
		if ( 'core/gallery' !== $b['blockName'] || false === strpos( memorial_plugin_block_class( $b ), ' is-style-cards ' ) ) {
			continue;
		}
		$items  = array();
		$linked = 0;
		$strong = 0;
		$priced = 0;
		foreach ( $b['innerBlocks'] as $img ) {
			if ( 'core/image' !== $img['blockName'] || empty( $img['attrs']['id'] ) ) {
				continue;
			}
			if ( in_array( $img['attrs']['linkDestination'] ?? '', array( 'custom', 'page', 'post' ), true ) ) {
				++$linked;
			}
			$cap = preg_match( '~<figcaption[^>]*>(.*?)</figcaption>~su', $img['innerHTML'], $m ) ? $m[1] : '';
			if ( strpos( $cap, '<strong>' ) !== false ) {
				++$strong;
			}
			if ( strpos( $cap, '₽' ) !== false ) {
				++$priced;
			}
			$items[] = array(
				'id'  => (int) $img['attrs']['id'],
				'cap' => memorial_plugin_parse_caption( $cap ),
			);
		}
		if ( ! $items || $linked > count( $items ) / 2 ) {
			continue; // плитки-ссылки на страницы — это не товары.
		}
		$cls   = memorial_plugin_block_class( $b );
		$ratio = strpos( $cls, ' mem-portrait ' ) !== false ? 'portrait' : ( strpos( $cls, ' mem-tall ' ) !== false ? 'tall' : ( strpos( $cls, ' mem-photo ' ) !== false ? 'photo' : 'land' ) );
		$intro = '';
		if ( $head ) {
			for ( $j = $head['i'] + 1; $j < $i; $j++ ) {
				if ( ( $blocks[ $j ]['blockName'] ?? '' ) === 'core/paragraph' ) {
					$intro .= trim( $blocks[ $j ]['innerHTML'] ) . "\n";
				}
			}
		}
		$out[] = array(
			'heading' => $head['text'] ?? '',
			'h'       => $head['i'] ?? $i,
			'g'       => $i,
			'items'   => $items,
			'ratio'   => $ratio,
			'cols'    => (int) ( $b['attrs']['columns'] ?? 4 ),
			'intro'   => trim( $intro ),
			'anchor'  => $head['anchor'] ?? '',
			'checked' => ( $strong + $priced ) >= count( $items ) / 2,
		);
		$head  = null; // заголовок принадлежит одной галерее.
	}
	return $out;
}

/** Все id картинок в блоке (с вложенными)
 *
 * @param mixed $b B.
 */
function memorial_plugin_block_images( $b ) {
	$ids = array();
	if ( 'core/image' === $b['blockName'] && ! empty( $b['attrs']['id'] ) ) {
		$ids[] = (int) $b['attrs']['id'];
	}
	if ( 'core/media-text' === $b['blockName'] && ! empty( $b['attrs']['mediaId'] ) ) {
		$ids[] = (int) $b['attrs']['mediaId'];
	}
	foreach ( $b['innerBlocks'] ?? array() as $ib ) {
		$ids = array_merge( $ids, memorial_plugin_block_images( $ib ) );
	}
	return $ids;
}

/** Примеры работ: подряд идущие куски «заголовок h3 + фото + таблица цен»
 *
 * @param mixed $blocks Blocks.
 */
function memorial_plugin_scan_works( $blocks ) {
	$works  = array();
	$cur    = null;
	$flush  = function () use ( &$cur, &$works ) {
		if ( $cur ) {
			$works[] = $cur;
		} $cur = null;
	};
	$absorb = function ( $b ) use ( &$cur, &$absorb ) {
		switch ( $b['blockName'] ) {
			case 'core/paragraph':
				$h = trim( $b['innerHTML'] );
				if ( preg_match( '~<strong>\s*Размер участка:?\s*</strong>:?\s*(.*)</p>~su', $h, $m ) ) {
					$cur['size'] = memorial_plugin_txt( $m[1] );
				} else {
					$cur['text'] .= wp_kses_post( preg_replace( '~^<p[^>]*>|</p>$~', '', $h ) ) . "\n\n";
				}
				break;
			case 'core/table':
				if ( preg_match( '~<tbody>(.*?)</tbody>~su', $b['innerHTML'], $m ) && preg_match_all( '~<tr>(.*?)</tr>~su', $m[1], $rows ) ) {
					foreach ( $rows[1] as $row ) {
						preg_match_all( '~<td[^>]*>(.*?)</td>~su', $row, $td );
						if ( ! $td[1] ) {
							continue;
						}
						$cur['rows'][] = array( memorial_plugin_txt( $td[1][0] ), (int) preg_replace( '/\D/', '', memorial_plugin_txt( end( $td[1] ) ) ) );
					}
				}
				if ( ! $cur['rows'] && preg_match( '~<tfoot>.*?<td[^>]*>(.*?)</td>.*<td[^>]*>(.*?)</td>~su', $b['innerHTML'], $f ) ) {
					$cur['rows'][] = array( memorial_plugin_txt( $f[1] ), (int) preg_replace( '/\D/', '', memorial_plugin_txt( $f[2] ) ) ); // в таблице только итог.
				}
				break;
			case 'core/image':
			case 'core/gallery':
				$cur['photos'] = array_merge( $cur['photos'], memorial_plugin_block_images( $b ) );
				break;
		}
	};
	$start  = fn( $title, $i ) => array(
		'title'  => $title,
		'from'   => $i,
		'to'     => $i,
		'size'   => '',
		'text'   => '',
		'rows'   => array(),
		'photos' => array(),
	);
	foreach ( $blocks as $i => $b ) {
		$name = $b['blockName'];
		if ( null === $name ) {
			continue;
		}
		$h3_inside = null;
		if ( 'core/media-text' === $name ) {
			foreach ( $b['innerBlocks'] as $ib ) {
				if ( 'core/heading' === $ib['blockName'] && 3 === ( $ib['attrs']['level'] ?? 2 ) ) {
					$h3_inside = memorial_plugin_txt( $ib['innerHTML'] );
					break; }
			}
		}
		if ( 'core/heading' === $name && 3 === ( $b['attrs']['level'] ?? 2 ) ) {
			$flush();
			$cur = $start( memorial_plugin_txt( $b['innerHTML'] ), $i );
			continue;
		}
		if ( null !== $h3_inside ) {
			$flush();
			$cur = $start( $h3_inside, $i );
			if ( ! empty( $b['attrs']['mediaId'] ) ) {
				$cur['photos'][] = (int) $b['attrs']['mediaId'];
			}
			foreach ( $b['innerBlocks'] as $ib ) {
				if ( 'core/heading' !== $ib['blockName'] ) {
					$absorb( $ib );
				}
			}
			continue;
		}
		if ( ! $cur ) {
			continue;
		}
		if ( in_array( $name, array( 'core/heading', 'core/shortcode', 'core/details', 'core/group', 'core/list', 'core/media-text' ), true ) ) {
			$flush();
			continue; }
		$absorb( $b );
		$cur['to'] = $i;
	}
	$flush();
	// Работы — только если хотя бы у половины кусков есть таблица цен.
	$priced = count( array_filter( $works, fn( $w ) => $w['rows'] ) );
	if ( $priced < 2 || $priced < count( $works ) / 2 ) {
		return array();
	}
	// и только подряд идущие (между ними нет ничего, кроме работ).
	return $works;
}

/**
 * Import candidates.
 */
function memorial_plugin_import_candidates() {
	$res = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	) as $p ) {
		if ( ! has_blocks( $p->post_content ) ) {
			continue;
		}
		$blocks = parse_blocks( $p->post_content );
		$g      = memorial_plugin_scan_galleries( $blocks );
		$w      = memorial_plugin_scan_works( $blocks );
		if ( $g || $w ) {
			$res[ $p->ID ] = array(
				'page'  => $p,
				'gal'   => $g,
				'works' => $w,
			);
		}
	}
	return $res;
}

/* ---------- Экран ---------- */

/**
 * Pages that can be moved, for the screen: galleries with a sample product, works, moved or not.
 *
 * @return array<int,array>
 */
function memorial_plugin_import_items() {
	$items = array();
	foreach ( memorial_plugin_import_candidates() as $pid => $x ) {
		$gals = array();
		foreach ( $x['gal'] as $i => $g ) {
			$s      = $g['items'][0]['cap'];
			$gals[] = array(
				'index'   => (int) $i,
				'heading' => (string) $g['heading'],
				'count'   => count( $g['items'] ),
				'sample'  => trim( $s['name'] . ( $s['sub'] ? ' · ' . $s['sub'] : '' ) . ( $s['price'] ? ' · ' . ( $s['from'] ? __( 'from ', 'memorial-plugin' ) : '' ) . $s['price'] . ' ₽' . ( $s['unit'] ? ' ' . $s['unit'] : '' ) : '' ) ),
				'checked' => (bool) $g['checked'],
			);
		}
		$items[] = array(
			'id'        => (int) $pid,
			'title'     => html_entity_decode( get_the_title( $x['page'] ), ENT_QUOTES, 'UTF-8' ),
			'link'      => (string) get_permalink( $x['page'] ),
			'moved'     => (bool) ( get_post_meta( $pid, '_mem_cat', true ) || get_post_meta( $pid, '_mem_works_done', true ) ),
			'galleries' => $gals,
			'works'     => count( $x['works'] ),
		);
	}
	return $items;
}

/**
 * REST: the list for the screen (GET memorial-plugin/v1/import).
 */
function memorial_plugin_import_rest() {
	register_rest_route(
		'memorial-plugin/v1',
		'/import',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			'callback'            => static fn() => rest_ensure_response( memorial_plugin_import_items() ),
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_import_rest' );

/**
 * Ability memorial-plugin/import-from-pages: the move itself.
 */
function memorial_plugin_import_ability() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}
	wp_register_ability(
		'memorial-plugin/import-from-pages',
		array(
			'label'               => __( 'Move the catalog from pages', 'memorial-plugin' ),
			'description'         => __( 'Turns the chosen “Cards with captions” galleries of pages into products and the examples of works into “Our works”; can replace the galleries on the page with the catalog block (the old version stays in the page’s revisions). Moving again does not create duplicates.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'pages' => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => 200,
						'items'    => array(
							'type'                 => 'object',
							'properties'           => array(
								'id'      => array(
									'type'    => 'integer',
									'minimum' => 1,
								),
								'cat'     => array(
									'type'        => 'string',
									'description' => __( 'Catalog category (empty — the page title).', 'memorial-plugin' ),
								),
								'gal'     => array(
									'type'  => 'array',
									'items' => array(
										'type'    => 'integer',
										'minimum' => 0,
									),
								),
								'works'   => array( 'type' => 'boolean' ),
								'replace' => array( 'type' => 'boolean' ),
							),
							'required'             => array( 'id' ),
							'additionalProperties' => false,
						),
					),
				),
				'required'             => array( 'pages' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'products' => array( 'type' => 'integer' ),
					'works'    => array( 'type' => 'integer' ),
					'skipped'  => array( 'type' => 'integer' ),
					'pages'    => array( 'type' => 'integer' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			'execute_callback'    => static function ( $input ) {
				$selection = array();
				foreach ( (array) ( $input['pages'] ?? array() ) as $page ) {
					$selection[ (int) $page['id'] ] = array(
						'cat'     => (string) ( $page['cat'] ?? '' ),
						'gal'     => array_map( 'absint', (array) ( $page['gal'] ?? array() ) ),
						'works'   => ! empty( $page['works'] ),
						'replace' => ! empty( $page['replace'] ),
					);
				}
				return memorial_plugin_import_run( $selection );
			},
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_init', 'memorial_plugin_import_ability' );

/**
 * Scripts of the screen.
 */
function memorial_plugin_import_assets() {
	$asset_file = MEMORIAL_PLUGIN_DIR . '/build/import/index.asset.php';
	if ( ! is_readable( $asset_file ) ) {
		return;
	}
	$asset = require $asset_file;
	wp_enqueue_script( 'memorial-plugin-import', MEMORIAL_PLUGIN_URL . '/build/import/index.js', $asset['dependencies'], $asset['version'], array( 'in_footer' => true ) );
	wp_set_script_translations( 'memorial-plugin-import', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
	wp_enqueue_style( 'wp-components' );
	if ( is_readable( MEMORIAL_PLUGIN_DIR . '/build/import/style-index.css' ) ) {
		wp_enqueue_style( 'memorial-plugin-import', MEMORIAL_PLUGIN_URL . '/build/import/style-index.css', array( 'wp-components' ), $asset['version'] );
	}
}

/**
 * The screen: the app of src/import is mounted here.
 */
function memorial_plugin_import_page() {
	if ( ! is_readable( MEMORIAL_PLUGIN_DIR . '/build/import/index.asset.php' ) ) {
		echo '<div class="wrap"><p>' . esc_html__( 'The JavaScript build is missing. Run npm run build.', 'memorial-plugin' ) . '</p></div>';
		return;
	}
	echo '<div id="memorial-plugin-import" class="wrap memorial-plugin-admin"></div>';
}

/* ---------- Перенос ---------- */

/**
 * Import term.
 *
 * @param string $name Name.
 * @param int    $parent Parent.
 * @param string $slug Slug.
 */
function memorial_plugin_import_term( $name, $parent, $slug = '' ) {
	$ex = get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'name'       => $name,
			'parent'     => $parent,
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $ex ) && $ex ) {
		return $ex[0]->term_id;
	}
	$args = array( 'parent' => $parent );
	if ( $slug ) {
		$args['slug'] = $slug;
	}
	$t = wp_insert_term( $name, 'mem_cat', $args );
	if ( is_wp_error( $t ) && $slug ) {
		$t = wp_insert_term( $name, 'mem_cat', array( 'parent' => $parent ) ); // такой адрес уже занят.
	}
	return is_wp_error( $t ) ? 0 : $t['term_id'];
}

/**
 * Shortcode block.
 *
 * @param string $code Code.
 */
function memorial_plugin_shortcode_block( $code ) {
	$html = "\n" . $code . "\n";
	return array(
		'blockName'    => 'core/shortcode',
		'attrs'        => array(),
		'innerBlocks'  => array(),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	);
}

/**
 * Move the chosen galleries and works of the pages into products and “Our works”.
 *
 * @param array $selection Page ID => array( 'cat' => category name, 'gal' => gallery indexes,
 *                         'works' => move the works, 'replace' => replace the galleries on the page ).
 * @return array{products:int,works:int,skipped:int,pages:int,message:string}
 */
function memorial_plugin_import_run( $selection ) {
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- many pages and photos in one run.
	}
	$cands      = memorial_plugin_import_candidates();
	$made       = 0;
	$skipped    = 0;
	$works_made = 0;
	$pages      = 0;

	foreach ( $cands as $pid => $x ) {
		$blocks = parse_blocks( $x['page']->post_content );
		$cut    = array(); // [от, до, шорткод]

		// Товары.
		$opts = (array) ( $selection[ $pid ] ?? array() );
		$sel  = array_map( 'absint', (array) ( $opts['gal'] ?? array() ) );
		$gals = array_values( array_intersect_key( $x['gal'], array_flip( $sel ) ) );
		if ( $gals ) {
			$name = sanitize_text_field( (string) ( $opts['cat'] ?? '' ) );
			if ( '' === $name ) {
				$name = $x['page']->post_title;
			}
			$root = memorial_plugin_import_term( $name, 0, $x['page']->post_name );
			if ( ! $root ) {
				continue;
			}
			update_post_meta( $pid, '_mem_cat', $root );
			update_term_meta( $root, 'mem_ratio', $gals[0]['ratio'] );
			update_term_meta( $root, 'mem_cols', $gals[0]['cols'] );
			$multi = count( $gals ) > 1;
			foreach ( $gals as $n => $g ) {
				$term = $root;
				if ( $multi ) {
					$term = memorial_plugin_import_term( $g['heading'] ? $g['heading'] : __( 'Section ', 'memorial-plugin' ) . ( $n + 1 ), $root );
					update_term_meta( $term, 'mem_order', $n + 1 );
					update_term_meta( $term, 'mem_ratio', $g['ratio'] );
					update_term_meta( $term, 'mem_cols', $g['cols'] );
					if ( $g['anchor'] ) {
						update_term_meta( $term, 'mem_anchor', $g['anchor'] );
					}
					// «47 моделей, от 800 ₽» сайт пишет сам — из описания раздела это убираем
					$intro = trim( preg_replace( '/^\s*\d+\s+модел[^.]*\.\s*/u', '', wp_strip_all_tags( $g['intro'] ) ) );
					if ( $intro && ! get_term( $term, 'mem_cat' )->description ) {
						wp_update_term( $term, 'mem_cat', array( 'description' => $intro ) );
					}
				}
				foreach ( $g['items'] as $k => $it ) {
					$src = $pid . ':' . $it['id'];
					if ( get_posts(
						array(
							'post_type'      => 'mem_product',
							'post_status'    => 'any',
							'fields'         => 'ids',
							'posts_per_page' => 1,
							'meta_key'       => '_mem_src',
							'meta_value'     => $src,
						)
					) ) {
						++$skipped;
						continue; }
					$cap = $it['cap'];
					$id  = wp_insert_post(
						array(
							'post_type'   => 'mem_product',
							'post_status' => 'publish',
							'menu_order'  => $k + 1,
							'post_title'  => $cap['name'] ? $cap['name'] : ( $g['heading'] ? $g['heading'] : $name ) . ' ' . ( $k + 1 ),
						)
					);
					if ( is_wp_error( $id ) || ! $id ) {
						continue;
					}
					wp_set_object_terms( $id, array( $term ), 'mem_cat' );
					if ( get_post_type( $it['id'] ) === 'attachment' ) {
						set_post_thumbnail( $id, $it['id'] );
					}
					update_post_meta( $id, '_mem_price', $cap['price'] );
					update_post_meta( $id, '_mem_src', $src );
					if ( $cap['from'] ) {
						update_post_meta( $id, '_mem_price_from', '1' );
					}
					if ( $cap['unit'] ) {
						update_post_meta( $id, '_mem_unit', $cap['unit'] );
					}
					if ( $cap['sub'] ) {
						update_post_meta( $id, '_mem_sub', $cap['sub'] );
					}
					++$made;
				}
			}
			if ( ! empty( $opts['replace'] ) ) {
				if ( $multi ) {
					foreach ( $gals as $n => $g ) {
						$cut[] = array( $g['h'], $g['g'], 0 === $n ? '[mem_catalog]' : '' );
					}
				} else {
					$cut[] = array( $gals[0]['g'], $gals[0]['g'], '[mem_catalog nav="0"]' );
				}
			}
		}

		// Работы.
		if ( $x['works'] && ! empty( $opts['works'] ) ) {
			foreach ( $x['works'] as $k => $w ) {
				$src = $pid . ':' . sanitize_title( $w['title'] );
				if ( get_posts(
					array(
						'post_type'      => 'mem_work',
						'post_status'    => 'any',
						'fields'         => 'ids',
						'posts_per_page' => 1,
						'meta_key'       => '_mem_src',
						'meta_value'     => $src,
					)
				) ) {
					++$skipped;
					continue; }
				$id = wp_insert_post(
					array(
						'post_type'    => 'mem_work',
						'post_status'  => 'publish',
						'menu_order'   => $k + 1,
						'post_title'   => $w['title'],
						'post_content' => memorial_plugin_blocks_from_shortcodes( trim( $w['text'] ) ),
					)
				);
				if ( is_wp_error( $id ) || ! $id ) {
					continue;
				}
				$photos = array_values( array_unique( array_filter( $w['photos'], fn( $a ) => get_post_type( $a ) === 'attachment' ) ) );
				update_post_meta( $id, '_mem_photos', implode( ',', $photos ) );
				if ( $photos ) {
					set_post_thumbnail( $id, $photos[0] );
				}
				update_post_meta( $id, '_mem_size', $w['size'] );
				update_post_meta( $id, '_mem_rows', $w['rows'] );
				update_post_meta( $id, '_mem_src', $src );
				// Год: из текста («2023 г.»), иначе — год загрузки первого фото на сайт.
				$year = preg_match( '/\b(199\d|20[0-4]\d)\b/', $w['title'] . ' ' . $w['text'], $ym ) ? (int) $ym[1]
					: ( $photos ? (int) get_the_date( 'Y', $photos[0] ) : 0 );
				if ( $year ) {
					update_post_meta( $id, '_mem_year', $year );
				}
				++$works_made;
			}
			update_post_meta( $pid, '_mem_works_done', 1 );
			if ( ! empty( $opts['replace'] ) ) {
				$cut[] = array( $x['works'][0]['from'], end( $x['works'] )['to'], '[mem_works]' );
			}
		}

		// Замена на странице: вырезаем куски с конца, чтобы номера не сдвигались.
		if ( $cut ) {
			usort( $cut, fn( $a, $b ) => $b[0] <=> $a[0] );
			foreach ( $cut as [$from, $to, $code] ) {
				array_splice( $blocks, $from, $to - $from + 1, $code ? array( memorial_plugin_shortcode_block( $code ) ) : array() );
			}
			wp_update_post(
				array(
					'ID'           => $pid,
					'post_content' => wp_slash( memorial_plugin_blocks_from_shortcodes( serialize_blocks( $blocks ) ) ),
				)
			);
			++$pages;
		}
	}
	return array(
		'products' => $made,
		'works'    => $works_made,
		'skipped'  => $skipped,
		'pages'    => $pages,
		/* translators: 1: products created, 2: works created, 3: products skipped, 4: pages updated */
		'message'  => sprintf( __( 'Products created: %1$d, works: %2$d. Skipped (already existed): %3$d. Pages updated: %4$d.', 'memorial-plugin' ), $made, $works_made, $skipped, $pages ),
	);
}
