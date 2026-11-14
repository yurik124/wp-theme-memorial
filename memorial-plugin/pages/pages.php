<?php
/**
 * Товары на обычных страницах сайта.
 *
 * В редакторе страницы (справа, блок «Товары на странице») выбирается категория каталога —
 * под текстом страницы появляется сетка карточек с ценами и сортировкой. Адрес и меню остаются прежними.
 * Вариант «Все категории» делает страницу главной страницей каталога: на ней плитки категорий.
 *
 * Ссылки на категории и на каталог по всему сайту ведут на эти страницы,
 * а старые адреса /catalog/... перенаправляются на них (301).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/* Выбор категории — в боковой панели редактора страницы (admin/editor-meta.php, поле _mem_products) */

/* Колонка в списке страниц: видно, где какие товары */
add_filter(
	'manage_page_posts_columns',
	function ( $c ) {
		$c['mem_products'] = __( 'Products', 'memorial-plugin' );
		return $c;
	}
);
add_action(
	'manage_page_posts_custom_column',
	function ( $col, $id ) {
		if ( 'mem_products' !== $col ) {
			return;
		}
		$v = get_post_meta( $id, '_mem_products', true );
		if ( 'all' === $v ) {
			echo esc_html__( 'All categories', 'memorial-plugin' );
		} elseif ( $v && get_term( (int) $v, 'mem_cat' ) instanceof WP_Term ) {
			echo esc_html( get_term( (int) $v, 'mem_cat' )->name );
		} else {
			echo '—';
		}
	},
	10,
	2
);

/* ---------- Связи «категория ↔ страница» ---------- */

/** ['all' => id страницы-каталога, term_id => id страницы, …] */
function memorial_plugin_page_map() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map   = array();
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_mem_products',
			'orderby'        => 'menu_order ID',
			'order'          => 'ASC',
		)
	);
	foreach ( $pages as $id ) {
		$v           = get_post_meta( $id, '_mem_products', true );
		$map[ $v ] ??= $id; // если одну категорию поставили на две страницы — главной считается первая.
	}
	// Страницы с каталогом в тексте ([mem_catalog]).
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_mem_cat',
			'orderby'        => 'menu_order ID',
			'order'          => 'ASC',
		)
	);
	foreach ( $pages as $id ) {
		$map[ (string) get_post_meta( $id, '_mem_cat', true ) ] ??= $id;
	}
	return $map;
}

/**
 * Catalog page.
 */
function memorial_plugin_catalog_page() {
	return (int) ( memorial_plugin_page_map()['all'] ?? 0 ); }
/**
 * Term page.
 *
 * @param int $term_id Term ID.
 */
function memorial_plugin_term_page( $term_id ) {
	return (int) ( memorial_plugin_page_map()[ (string) $term_id ] ?? 0 ); }

/** Ссылка на каталог: страница «Все категории», а если её нет — первая страница с товарами */
function memorial_plugin_catalog_url() {
	$id = memorial_plugin_catalog_page();
	if ( $id ) {
		return get_permalink( $id );
	}
	$map = memorial_plugin_page_map();
	return $map ? get_permalink( reset( $map ) ) : home_url( '/' );
}

add_filter(
	'post_type_archive_link',
	function ( $link, $type ) {
		if ( 'mem_product' === $type && memorial_plugin_page_map() ) {
			return memorial_plugin_catalog_url();
		}
		return $link;
	},
	10,
	2
);

add_filter(
	'term_link',
	function ( $link, $term, $tax ) {
		if ( 'mem_cat' !== $tax ) {
			return $link;
		}
		$id = memorial_plugin_term_page( $term->term_id );
		if ( $id ) {
			return get_permalink( $id );
		}
		// Подкатегория без своей страницы — раздел на странице родителя.
		$t     = $term;
		$guard = 0;
		while ( $t->parent && $guard++ < 5 ) {
			$t = get_term( $t->parent, 'mem_cat' );
			if ( ! $t || is_wp_error( $t ) ) {
				break;
			}
			$id = memorial_plugin_term_page( $t->term_id );
			if ( $id ) {
				return get_permalink( $id ) . '#' . memorial_plugin_term_anchor( $term );
			}
		}
		return $link;
	},
	10,
	3
);

/* Старые адреса /catalog/... → на страницы */
add_action(
	'template_redirect',
	function () {
		$to = '';
		if ( is_post_type_archive( 'mem_product' ) && memorial_plugin_page_map() ) {
			$to = memorial_plugin_catalog_url();
		} elseif ( is_tax( 'mem_cat' ) ) {
			$id = memorial_plugin_term_page( get_queried_object_id() );
			$l  = $id ? '' : get_term_link( get_queried_object() );
			if ( $id ) {
				$to = get_permalink( $id );
			} elseif ( ! is_wp_error( $l ) && false !== strpos( $l, '#' ) ) {
				$to = $l;
			} // подкатегория — раздел на странице родителя.
		}
		// /groby/page/9/ при 2 страницах товаров — на первую страницу
		$paged = (int) get_query_var( 'paged' );
		$mode  = ! $to && $paged > 1 && is_page() ? get_post_meta( get_queried_object_id(), '_mem_products', true ) : '';
		if ( $mode ) {
			$args = array(
				'post_type'      => 'mem_product',
				'post_status'    => 'publish',
				'posts_per_page' => 24,
				'paged'          => $paged,
				'fields'         => 'ids',
			);
			if ( 'all' !== $mode ) {
				$args['tax_query'] = array(
					array(
						'taxonomy' => 'mem_cat',
						'terms'    => (int) $mode,
					),
				);
			}
			$q = new WP_Query( $args );
			if ( 'all' === $mode || $paged > $q->max_num_pages ) {
				$to = get_permalink( get_queried_object_id() );
			}
		}
		if ( $to ) {
			if ( ! empty( $_GET['sort'] ) ) {
				$to = add_query_arg( 'sort', sanitize_key( $_GET['sort'] ), $to );
			}
			wp_safe_redirect( $to, 301 );
			exit;
		}
	}
);

/* /venki/page/2/ — WordPress по умолчанию считает это несуществующей страницей у обычных страниц */
add_filter(
	'redirect_canonical',
	function ( $url ) {
		if ( is_page() && get_query_var( 'paged' ) > 1 && get_post_meta( get_queried_object_id(), '_mem_products', true ) ) {
			return false;
		}
		return $url;
	}
);

/* ---------- Вывод ---------- */

/** Плитки категорий (главная и страница «Все категории») */
/* ---------- Как фото страницы показывается в плитке каталога ---------- */
add_action(
	'init',
	function () {
		$auth = fn() => current_user_can( 'edit_pages' );
		register_post_meta(
			'page',
			'_mem_tile_pos',
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
		register_post_meta(
			'page',
			'_mem_tile_fit',
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
		register_post_meta(
			'page',
			'_mem_hide_thumb',
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
		register_post_meta(
			'page',
			'_mem_tile_bg',
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
	}
);

/** Доп. стиль плитки: точка фокуса (--pos) и «целиком» (--fit)
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_tile_style( $page_id ) {
	$st  = '';
	$pos = (string) get_post_meta( $page_id, '_mem_tile_pos', true );
	// точка фокуса: скрипт сайта ставит её в центр плитки (data-focus), --pos — запасной вариант без скрипта.
	if ( preg_match( '/^(\d{1,3}(?:\.\d+)?)% (\d{1,3}(?:\.\d+)?)%$/', $pos, $m ) ) {
		$st .= ';--pos:' . round( (float) $m[1] ) . '% ' . round( (float) $m[2] ) . '%';
	}
	if ( get_post_meta( $page_id, '_mem_tile_fit', true ) ) {
		$st .= ';--fit:contain';
	}
	$bg = sanitize_hex_color( (string) get_post_meta( $page_id, '_mem_tile_bg', true ) );
	if ( $bg ) {
		$st .= ';--tbg:' . $bg;
	}
	if ( '' !== $st && false !== strpos( $st, '--pos' ) && false === strpos( $st, '--fit' ) ) {
		$st .= '" data-wp-interactive="memorial-plugin/focus" data-wp-init="callbacks.init" data-focus="' . round( (float) $m[1] ) . ' ' . round( (float) $m[2] );
	}
	return $st;
}

add_action(
	'enqueue_block_editor_assets',
	function () {
		$s = get_current_screen();
		if ( ! $s || 'page' !== $s->post_type ) {
			return;
		}
		$f = '/assets/js/tile-panel.js';
		wp_enqueue_script(
			'mem-tile-panel',
			MEMORIAL_PLUGIN_URL . $f,
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-hooks' ),
			filemtime( MEMORIAL_PLUGIN_DIR . $f ),
			true
		);
	}
);

/** Плитка раздела: фото сверху (точка фокуса, «целиком», цвет фона — из панели «Плитка и карточка раздела»), под ним название и «Смотреть →»
 *
 * @param string $url URL.
 * @param string $name Name.
 * @param mixed  $img Img.
 * @param mixed  $st St.
 */
function memorial_plugin_cat_tile_html( $url, $name, $img, $st ) {
	return '<a class="cat reveal" href="' . esc_url( $url ) . '"' . ( $img ? ' style="--img:url(' . esc_url( $img ) . ')' . $st . '"' : '' ) . '>'
		. '<span class="cat__ph" aria-hidden="true"></span>'
		. '<span class="cat__cap"><h3>' . esc_html( $name ) . ( '</h3><span class="cat__more">' . esc_html__( 'View →', 'memorial-plugin' ) . '</span></span></a>' );
}

/**
 * Cat tiles.
 *
 * @param mixed $cats Cats.
 */
function memorial_plugin_cat_tiles( $cats ) {
	echo '<div class="cats">';
	foreach ( $cats as $c ) {
		$page = memorial_plugin_term_page( $c->term_id );
		// Фото плитки: «Изображение записи» страницы категории, иначе — фото первого товара.
		$img = $page ? get_the_post_thumbnail_url( $page, 'medium_large' ) : '';
		$st  = $img ? memorial_plugin_tile_style( $page ) : '';
		if ( ! $img ) {
			$p   = get_posts(
				array(
					'post_type'      => 'mem_product',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_thumbnail_id',
					'tax_query'      => array(
						array(
							'taxonomy' => 'mem_cat',
							'terms'    => $c->term_id,
						),
					),
				)
			);
			$img = $p ? get_the_post_thumbnail_url( $p[0], 'medium_large' ) : '';
		}
		$name = $page ? get_the_title( $page ) : $c->name;
		echo memorial_plugin_cat_tile_html( get_term_link( $c ), $name, $img, $st ?? '' );
	}
	echo '</div>';
}

/** Сетка товаров с сортировкой и страницами. $term_id = 0 — все товары.
 *
 * @param int   $term_id Term ID.
 * @param mixed $base Base.
 */
function memorial_plugin_products_grid( $term_id, $base ) {
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$args  = array(
		'post_type'      => 'mem_product',
		'post_status'    => 'publish',
		'posts_per_page' => 24,
		'paged'          => $paged,
	);
	if ( $term_id ) {
		$args['tax_query'] = array(
			array(
				'taxonomy'         => 'mem_cat',
				'terms'            => $term_id,
				'include_children' => true,
			),
		);
	}
	memorial_plugin_apply_sort( $args );
	$q    = new WP_Query( $args );
	$sort = sanitize_key( $_GET['sort'] ?? '' );

	if ( $term_id ) {
		$subs = get_terms(
			array(
				'taxonomy'   => 'mem_cat',
				'parent'     => $term_id,
				'hide_empty' => true,
			)
		);
		if ( ! is_wp_error( $subs ) && $subs ) {
			echo '<div class="chips">';
			foreach ( $subs as $s ) {
				$pid = memorial_plugin_term_page( $s->term_id );
				echo '<a class="chip" href="' . esc_url( get_term_link( $s ) ) . '">' . esc_html( $pid ? get_the_title( $pid ) : $s->name ) . '</a>';
			}
			echo '</div>';
		}
	}

	if ( ! $q->have_posts() ) {
		echo ( '<div class="empty"><p>' . esc_html__( 'Products will appear soon. Call — we will find what you need and tell you about the prices.', 'memorial-plugin' ) . '</p>' );
		if ( memorial_plugin_tel() ) {
			echo '<a class="btn btn--gold" href="tel:' . esc_attr( memorial_plugin_tel() ) . '">' . memorial_plugin_icon( 'phone' ) . '<span class="nowrap">' . esc_html( memorial_plugin_opt( 'phone' ) ) . '</span></a>';
		}
		echo '</div>';
		return;
	}
	?>
	<div class="toolbar" id="tovary">
		<span class="toolbar__count"><?php echo esc_html( sprintf( /* translators: %d: number of products */ __( 'Products: %d', 'memorial-plugin' ), (int) $q->found_posts ) ); ?></span>
		<?php if ( $q->found_posts > 1 ) : ?>
		<form class="sort" method="get" action="<?php echo esc_url( $base ); ?>" data-sort>
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Sorting', 'memorial-plugin' ); ?></span>
			<select name="sort">
			<?php foreach ( memorial_plugin_sorts() as $v => $l ) : ?>
				<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $sort, $v ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
			</select>
		</label>
		<noscript><button class="btn btn--line btn--sm" type="submit">OK</button></noscript>
		</form>
		<?php endif; ?>
	</div>
	<div class="products">
		<?php
		while ( $q->have_posts() ) :
			$q->the_post();
			memorial_plugin_part( 'product-card' );
endwhile;
		?>
	</div>
	<?php
	if ( $q->max_num_pages > 1 ) {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns escaped markup of core.
		echo '<nav class="pagination" aria-label="' . esc_attr__( 'Pages', 'memorial-plugin' ) . '"><div class="nav-links">'
			. paginate_links(
				array(
					'total'        => $q->max_num_pages,
					'current'      => $paged,
					'mid_size'     => 1,
					'prev_text'    => '←',
					'next_text'    => '→',
					'add_fragment' => '#tovary',
				)
			)
			. '</div></nav>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	wp_reset_postdata();
}

/** Название модели для сравнения: без кавычек, пробелов и регистра
 *
 * @param string $s Name.
 */
function memorial_plugin_norm_name( $s ) {
	$s = mb_strtolower( html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ) );
	return preg_replace( '/[\s«»"\'„“”‹›\-–—.,№#]+/u', '', $s );
}

/** Товары категории страницы (с подкатегориями), по нормализованному названию
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_page_products( $page_id ) {
	$cat = (int) get_post_meta( $page_id, '_mem_cat', true );
	if ( ! $cat ) {
		return array();
	}
	$map = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy'         => 'mem_cat',
					'terms'            => $cat,
					'include_children' => true,
				),
			),
		)
	) as $p ) {
		$map[ memorial_plugin_norm_name( $p->post_title ) ] = $p;
	}
	return $map;
}

/* ---------- Структура главного меню (блок «Навигация») ---------- */

/**
 * ID of the site's main menu — a Navigation menu (wp_navigation). The theme names it (filter
 * memorial_plugin_main_nav_id); in another theme it is the most recent published Navigation menu,
 * the one the Navigation block shows when no menu is chosen.
 *
 * @return int
 */
function memorial_plugin_main_nav_id() {
	$id = (int) apply_filters( 'memorial_plugin_main_nav_id', 0 );
	if ( $id ) {
		return $id;
	}
	$latest = get_posts(
		array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return (int) ( $latest[0] ?? 0 );
}

/**
 * Items of the main menu, in order: [item id => item]. Each item has the fields of a classic menu item
 * that the plugin uses — ID, menu_item_parent, title, url, target, type (post_type / taxonomy / custom),
 * object (page, post…), object_id — built from the Navigation links and submenus of the menu.
 *
 * @return array<int,object>
 */
function memorial_plugin_menu_items() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$items = array();
	$nav   = get_post( memorial_plugin_main_nav_id() );
	if ( ! $nav instanceof WP_Post || 'wp_navigation' !== $nav->post_type ) {
		return $items;
	}
	$next = 0;
	$walk = static function ( $blocks, $parent ) use ( &$walk, &$items, &$next ) {
		foreach ( $blocks as $b ) {
			$name = (string) ( $b['blockName'] ?? '' );
			$a    = (array) ( $b['attrs'] ?? array() );
			if ( in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu', 'core/home-link' ), true ) ) {
				$kind = (string) ( $a['kind'] ?? '' );
				$oid  = (int) ( $a['id'] ?? 0 );
				$url  = (string) ( $a['url'] ?? '' );
				if ( 'core/home-link' === $name ) {
					$url = home_url( '/' );
				} elseif ( $oid && 'post-type' === $kind && get_post_status( $oid ) ) {
					$url = (string) get_permalink( $oid );
				} elseif ( $oid && 'taxonomy' === $kind ) {
					$link = get_term_link( $oid );
					$url  = is_wp_error( $link ) ? $url : $link;
				}
				$label = (string) ( $a['label'] ?? '' );
				if ( '' === $label && 'core/home-link' === $name ) {
					$label = __( 'Home', 'memorial-plugin' );
				}
				$id           = ++$next;
				$items[ $id ] = (object) array(
					'ID'               => $id,
					'menu_item_parent' => $parent,
					'title'            => trim( wp_strip_all_tags( html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) ) ),
					'url'              => $url,
					'target'           => empty( $a['opensInNewTab'] ) ? '' : '_blank',
					'type'             => 'post-type' === $kind ? 'post_type' : ( 'taxonomy' === $kind ? 'taxonomy' : 'custom' ),
					'object'           => (string) ( $a['type'] ?? '' ),
					'object_id'        => $oid,
				);
				$walk( (array) ( $b['innerBlocks'] ?? array() ), $id );
			} elseif ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'], $parent );
			}
		}
	};
	$walk( parse_blocks( (string) $nav->post_content ), 0 );
	return $items;
}

/** Пункт меню, ведущий на страницу
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_menu_item_for( $page_id ) {
	foreach ( memorial_plugin_menu_items() as $it ) {
		if ( 'post_type' === $it->type && (int) $it->object_id === (int) $page_id ) {
			return $it;
		}
	}
	return null;
}

/** Цепочка родительских пунктов меню над страницей: [[название, ссылка], …] (без самой страницы)
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_menu_chain( $page_id ) {
	$it = memorial_plugin_menu_item_for( $page_id );
	if ( ! $it ) {
		return null;
	}
	$items = memorial_plugin_menu_items();
	$out   = array();
	$guard = 0;
	while ( $it->menu_item_parent && isset( $items[ $it->menu_item_parent ] ) && $guard++ < 10 ) {
		$it    = $items[ $it->menu_item_parent ];
		$url   = $it->url && '#' !== $it->url ? $it->url : '';
		$out[] = array( $it->title, $url );
	}
	return array_reverse( $out );
}

/** Подпункты меню под страницей, которые ведут на страницы
 *
 * @param int $page_id Page ID.
 */
function memorial_plugin_menu_children( $page_id ) {
	$it = memorial_plugin_menu_item_for( $page_id );
	if ( ! $it ) {
		return array();
	}
	return array_values( array_filter( memorial_plugin_menu_items(), fn( $c ) => (int) $c->menu_item_parent === (int) $it->ID && 'post_type' === $c->type ) );
}

/** Плитки из подпунктов меню (страница «Все категории»)
 *
 * @param mixed $children Children.
 */
function memorial_plugin_page_tiles( $children ) {
	echo '<div class="cats">';
	foreach ( $children as $c ) {
		$pid  = (int) $c->object_id;
		$img  = get_the_post_thumbnail_url( $pid, 'medium_large' );
		$st   = $img ? memorial_plugin_tile_style( $pid ) : '';
		$mode = get_post_meta( $pid, '_mem_products', true );
		if ( ! $img && $mode && 'all' !== $mode ) {
			$p   = get_posts(
				array(
					'post_type'      => 'mem_product',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_thumbnail_id',
					'tax_query'      => array(
						array(
							'taxonomy' => 'mem_cat',
							'terms'    => (int) $mode,
						),
					),
				)
			);
			$img = $p ? get_the_post_thumbnail_url( $p[0], 'medium_large' ) : '';
		}
		echo memorial_plugin_cat_tile_html( $c->url, $c->title, $img, $st );
	}
	echo '</div>';
}
