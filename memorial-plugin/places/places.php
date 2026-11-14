<?php
/**
 * Места, которые заполняются в админке:
 *   «Мемориал → Залы для поминок» → [mem_restaurants] — блок на странице «Поминальный обед»;
 *   «Мемориал → Кладбища»         → [mem_cemeteries]  — список кладбищ; ставится на любую страницу
 *     (страница «Кладбища» создаётся черновиком, ссылки на неё — обычные ссылки в тексте).
 *
 * Залы — без телефонов и сайтов ресторанов: заказ идёт через нас (кнопка «Перезвоните мне»),
 * поэтому блок помогает выбрать, а не уводит посетителя к ресторану.
 * Пустой список — блок не выводится (редактору видна подсказка).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/*
=========================================================
 * Настройки
 * =======================================================
 */

/**
 * Rest schema.
 */
function memorial_plugin_rest_schema() {
	return array(
		'block' => array(
			'type'   => 'group',
			'label'  => __( 'Block on the page', 'memorial-plugin' ),
			'fields' => array(
				'title' => array(
					'type'  => 'text',
					'label' => __( 'Title', 'memorial-plugin' ),
				),
				'text'  => array(
					'type'  => 'textarea',
					'label' => __( 'Text under the title', 'memorial-plugin' ),
					'rows'  => 2,
				),
				'btn'   => array(
					'type'        => 'text',
					'label'       => __( 'Button label at a hall', 'memorial-plugin' ),
					'placeholder' => __( 'Order a meal here', 'memorial-plugin' ),
				),
			),
		),
		'items' => array(
			'type'     => 'repeater',
			'label'    => __( 'Halls and restaurants', 'memorial-plugin' ),
			'add'      => __( 'Add a hall', 'memorial-plugin' ),
			'collapse' => 'name',
			'fields'   => array(
				'name'  => array(
					'type'        => 'text',
					'label'       => __( 'Name', 'memorial-plugin' ),
					'placeholder' => __( 'Cafe “…”', 'memorial-plugin' ),
				),
				'img'   => array(
					'type'  => 'image',
					'label' => __( 'Photo of the hall', 'memorial-plugin' ),
				),
				'addr'  => array(
					'type'        => 'text',
					'label'       => __( 'Address or area', 'memorial-plugin' ),
					'placeholder' => __( '… St., near the … cemetery', 'memorial-plugin' ),
				),
				'seats' => array(
					'type'        => 'number',
					'label'       => __( 'How many guests it holds (up to)', 'memorial-plugin' ),
					'placeholder' => '60',
				),
				'price' => array(
					'type'        => 'text',
					'label'       => __( 'Price', 'memorial-plugin' ),
					'placeholder' => __( 'from 900 ₽ per person', 'memorial-plugin' ),
				),
				'feats' => array(
					'type'        => 'text',
					'label'       => __( 'Features — comma-separated', 'memorial-plugin' ),
					'placeholder' => __( 'parking, a separate hall, a lenten menu', 'memorial-plugin' ),
				),
				'text'  => array(
					'type'  => 'textarea',
					'label' => __( 'About the hall in short (optional)', 'memorial-plugin' ),
					'rows'  => 2,
				),
			),
		),
	);
}
/**
 * Rest defaults.
 */
function memorial_plugin_rest_defaults() {
	return array(
		'block' => array(
			'title' => 'Где провести поминальный обед',
			'text'  => 'Залы, с которыми мы работаем. Договоримся о дате, меню и числе гостей за вас — стоимость та же, что в самом зале.',
			'btn'   => __( 'Order a meal here', 'memorial-plugin' ),
		),
		'items' => array(),
	);
}

/**
 * Cem schema.
 */
function memorial_plugin_cem_schema() {
	return array(
		'items' => array(
			'type'     => 'repeater',
			'label'    => __( 'Cemeteries', 'memorial-plugin' ),
			'add'      => __( 'Add a cemetery', 'memorial-plugin' ),
			'collapse' => 'name',
			'fields'   => array(
				'name'   => array(
					'type'        => 'text',
					'label'       => __( 'Name', 'memorial-plugin' ),
					'placeholder' => __( 'Northern cemetery', 'memorial-plugin' ),
				),
				'status' => array(
					'type'    => 'select',
					'label'   => __( 'Burials', 'memorial-plugin' ),
					'options' => array(
						'open'   => __( 'Active — new burials', 'memorial-plugin' ),
						'family' => __( 'Only burial in a family grave', 'memorial-plugin' ),
						'closed' => __( 'Closed for burials', 'memorial-plugin' ),
					),
				),
				'img'    => array(
					'type'  => 'image',
					'label' => __( 'Photo (optional)', 'memorial-plugin' ),
				),
				'addr'   => array(
					'type'        => 'text',
					'label'       => __( 'Address', 'memorial-plugin' ),
					'placeholder' => __( 'Belgorod district, village of …', 'memorial-plugin' ),
				),
				'dist'   => array(
					'type'        => 'text',
					'label'       => __( 'How far (optional)', 'memorial-plugin' ),
					'placeholder' => __( '12 km from the centre of Belgorod', 'memorial-plugin' ),
				),
				'hours'  => array(
					'type'        => 'text',
					'label'       => __( 'Opening hours (optional)', 'memorial-plugin' ),
					'placeholder' => __( 'Daily from 8:00 to 17:00', 'memorial-plugin' ),
				),
				'text'   => array(
					'type'  => 'textarea',
					'label' => __( 'More: how to arrange a burial, what to keep in mind', 'memorial-plugin' ),
					'rows'  => 4,
					'html'  => true,
				),
				'map'    => array(
					'type'  => 'map',
					'label' => __( 'Map (optional)', 'memorial-plugin' ),
					'help'  => __( 'A link from Yandex Maps. Empty — a map by the address.', 'memorial-plugin' ),
				),
			),
		),
	);
}
/**
 * Cem defaults.
 */
function memorial_plugin_cem_defaults() {
	return array(
		'items' => array(),
	);
}

add_filter(
	'memorial_plugin_admin_pages',
	function ( $pages ) {
		$pages['mem_rest'] = array(
			'slug'   => 'mem-rest',
			'title'  => __( 'Halls for the memorial meal', 'memorial-plugin' ),
			'schema' => 'memorial_plugin_rest_schema',
			'intro'  => __( 'Halls and restaurants for the memorial meal. On a page they are shown by the “Memorial meal halls” block (it is already on the “Memorial meal” page). ', 'memorial-plugin' )
					. __( 'Phones and websites of the restaurants are not shown on the site — a visitor orders through you with the “Call me back” button.', 'memorial-plugin' ),
		);
		$pages['mem_cem']  = array(
			'slug'   => 'mem-cem',
			'title'  => __( 'Cemeteries', 'memorial-plugin' ),
			'schema' => 'memorial_plugin_cem_schema',
			'intro'  => __( 'The list of cemeteries is shown by the “Cemeteries” block on any page (for example, “Cemeteries of Belgorod and the district”). ', 'memorial-plugin' )
					. __( 'Which cemeteries to show and whether to show the filter buttons is set in the block settings. ', 'memorial-plugin' )
					. __( 'Order — with the ↑ ↓ arrows to the right of a cemetery.', 'memorial-plugin' ),
		);
		return $pages;
	}
);

/** Фото по адресу из настроек: <img> с размерами и srcset (если картинка из медиатеки)
 *
 * @param string $url URL.
 * @param string $alt Alt.
 * @param mixed  $sizes Sizes.
 */
function memorial_plugin_place_img( $url, $alt, $sizes ) {
	if ( ! $url ) {
		return '';
	}
	$id = memorial_plugin_attachment_id_by_url( $url );
	if ( $id ) {
		return wp_get_attachment_image(
			$id,
			'medium_large',
			false,
			array(
				'alt'      => $alt,
				'loading'  => 'lazy',
				'decoding' => 'async',
				'sizes'    => $sizes,
			)
		);
	}
	return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
}

/*
=========================================================
 * [mem_restaurants]
 * =======================================================
 */
add_shortcode(
	'mem_restaurants',
	function ( $atts ) {
		$o     = memorial_plugin_settings( 'mem_rest' );
		$a     = shortcode_atts(
			array(
				'title' => $o['block']['title'],
				'text'  => $o['block']['text'],
			),
			$atts,
			'mem_restaurants'
		);
		$items = array_values( array_filter( (array) $o['items'], fn( $r ) => trim( $r['name'] ?? '' ) !== '' ) );
		if ( ! $items ) {
			return current_user_can( 'edit_pages' ) ? ( '<p class="is-style-note">' . esc_html__( 'Memorial meal halls: no halls added — “Memorial → Halls for the memorial meal”. Visitors do not see this message.', 'memorial-plugin' ) . '</p>' ) : '';
		}
		$btn = trim( $o['block']['btn'] ?? '' );
		$btn = '' !== $btn ? $btn : __( 'Order a meal here', 'memorial-plugin' );
		$h   = '<section class="mem-places mem-rest" id="zaly">';
		if ( $a['title'] ) {
			$h .= '<h2>' . esc_html( $a['title'] ) . '</h2>';
		}
		if ( $a['text'] ) {
			$h .= '<p class="mem-places__lead">' . esc_html( $a['text'] ) . '</p>';
		}
		$h .= '<div class="mem-places__grid">';
		foreach ( $items as $r ) {
			$name = trim( $r['name'] );
			$img  = memorial_plugin_place_img( $r['img'] ?? '', $name, '(max-width:600px) 100vw, 33vw' );
			$meta = array();
			if ( ! empty( $r['seats'] ) ) {
				$meta[] = '<span>' . esc_html(
					sprintf(
					/* translators: %d: number of guests */
						_n( 'up to %d guest', 'up to %d guests', (int) $r['seats'], 'memorial-plugin' ),
						(int) $r['seats']
					)
				) . '</span>';
			}
			if ( trim( $r['price'] ?? '' ) ) {
				$meta[] = '<span class="mem-place__price">' . esc_html( trim( $r['price'] ) ) . '</span>';
			}
			$feats = array_filter( array_map( 'trim', explode( ',', (string) ( $r['feats'] ?? '' ) ) ) );
			$h    .= '<article class="mem-place">'
			. ( $img ? '<div class="mem-place__img">' . $img . '</div>' : '' )
			. '<div class="mem-place__body"><h3 class="mem-place__name">' . esc_html( $name ) . '</h3>'
			. ( trim( $r['addr'] ?? '' ) ? '<p class="mem-place__addr">' . memorial_plugin_icon( 'pin' ) . esc_html( trim( $r['addr'] ) ) . '</p>' : '' )
			. ( $meta ? '<p class="mem-place__meta">' . implode( '', $meta ) . '</p>' : '' )
			. ( trim( $r['text'] ?? '' ) ? '<p class="mem-place__text">' . esc_html( trim( $r['text'] ) ) . '</p>' : '' )
			. ( $feats ? '<ul class="mem-place__tags">' . implode( '', array_map( fn( $f ) => '<li>' . esc_html( $f ) . '</li>', $feats ) ) . '</ul>' : '' )
			. '<button class="btn btn--ghost mem-place__btn" type="button" data-callback>' . esc_html( $btn ) . '</button>'
			. '</div></article>';
		}
		return $h . '</div></section>';
	}
);

/*
=========================================================
 * [mem_cemeteries]
 * =======================================================
 */
/**
 * Cem status names.
 */
function memorial_plugin_cem_status_names() {
	return array(
		'open'   => __( 'Active', 'memorial-plugin' ),
		'family' => __( 'Family grave', 'memorial-plugin' ),
		'closed' => __( 'Closed', 'memorial-plugin' ),
	);
}

/**
 * Cem items.
 */
function memorial_plugin_cem_items() {
	return array_values( array_filter( (array) ( memorial_plugin_settings( 'mem_cem' )['items'] ?? array() ), fn( $r ) => trim( $r['name'] ?? '' ) !== '' ) );
}

add_shortcode(
	'mem_cemeteries',
	function ( $atts ) {
		$a     = shortcode_atts(
			array(
				'status' => '',
				'filtr'  => 'да',
			),
			$atts,
			'mem_cemeteries'
		);
		$items = memorial_plugin_cem_items();
		$want  = array();   // status="действующие, подзахоронение".
		foreach ( array_filter( array_map( 'trim', explode( ',', mb_strtolower( $a['status'] ) ) ) ) as $w ) {
			if ( preg_match( '~^(действ|откр|open)~u', $w ) ) {
				$want[] = 'open';
			} elseif ( preg_match( '~^(подзах|родств|family)~u', $w ) ) {
				$want[] = 'family';
			} elseif ( preg_match( '~^(закр|closed)~u', $w ) ) {
				$want[] = 'closed';
			}
		}
		if ( $want ) {
			$items = array_values( array_filter( $items, fn( $r ) => in_array( $r['status'] ?? 'open', $want, true ) ) );
		}
		if ( ! $items ) {
			return current_user_can( 'edit_pages' ) ? ( '<p class="is-style-note">' . esc_html__( 'Cemeteries: no cemeteries added — “Memorial → Cemeteries”. Visitors do not see this message.', 'memorial-plugin' ) . '</p>' ) : '';
		}
		$names = memorial_plugin_cem_status_names();
		$cnt   = array_count_values( array_map( fn( $r ) => $r['status'] ?? 'open', $items ) );
		// Interactivity API: filter and map (assets/js/view/cemeteries.js).
		wp_enqueue_script_module( 'memorial-plugin-cemeteries' );
		$h = '<div class="mem-cem" data-wp-interactive="memorial-plugin/cemeteries" data-wp-context=\'{"filter":""}\'>';
		if ( count( $cnt ) > 1 && ! memorial_plugin_sc_no( $a['filtr'] ) ) {
			$h .= ( '<div class="srow"><div class="srow__in" role="group" aria-label="' . esc_attr__( 'Which cemeteries to show', 'memorial-plugin' ) . '">' )
			. ( '<button type="button" class="chip is-on" data-wp-context=\'{"value":""}\' data-wp-on--click="actions.select" data-wp-class--is-on="state.isSelected" data-wp-bind--aria-pressed="state.isSelected" aria-pressed="true">' . esc_html__( 'All', 'memorial-plugin' ) . ' <small>' ) . count( $items ) . '</small></button>';
			foreach ( $names as $k => $n ) {
				if ( ! empty( $cnt[ $k ] ) ) {
					$h .= '<button type="button" class="chip" data-wp-context=\'' . esc_attr( wp_json_encode( array( 'value' => (string) $k ) ) ) . '\' data-wp-on--click="actions.select" data-wp-class--is-on="state.isSelected" data-wp-bind--aria-pressed="state.isSelected" aria-pressed="false">' . esc_html( $n ) . ' <small>' . (int) $cnt[ $k ] . '</small></button>';
				}
			}
			$h .= '</div></div>';
		}
		$h   .= '<div class="mem-places__grid">';
		$used = array();
		foreach ( $items as $r ) {
			$name          = trim( $r['name'] );
			$st            = $r['status'] ?? 'open';
			$slug          = sanitize_title( $name );
			$slug          = '' !== $slug ? $slug : 'cem';
			$slug          = isset( $used[ $slug ] ) ? $slug . '-' . ( ++$used[ $slug ] ) : $slug;
			$used[ $slug ] = $used[ $slug ] ?? 1;
			$img           = memorial_plugin_place_img( $r['img'] ?? '', $name, '(max-width:600px) 100vw, 33vw' );
			$addr          = trim( $r['addr'] ?? '' );
			$map           = ( $r['map'] ?? '' ) || $addr ? memorial_plugin_map_src( $r['map'] ?? '', $addr ) : '';
			$h            .= '<article class="mem-place mem-cem__item" id="' . esc_attr( $slug ) . '" data-wp-context=\'' . esc_attr( wp_json_encode( array( 'status' => (string) $st ) ) ) . '\' data-wp-bind--hidden="state.isHidden">'
			. ( $img ? '<div class="mem-place__img">' . $img . '</div>' : '' )
			. '<div class="mem-place__body">'
			. '<p class="mem-cem__st mem-cem__st--' . esc_attr( $st ) . '">' . esc_html( $names[ $st ] ?? '' ) . '</p>'
			. '<h2 class="mem-place__name">' . esc_html( $name ) . '</h2>'
			. ( $addr ? '<p class="mem-place__addr">' . memorial_plugin_icon( 'pin' ) . esc_html( $addr ) . '</p>' : '' )
			. ( trim( $r['dist'] ?? '' ) ? '<p class="mem-place__meta"><span>' . esc_html( trim( $r['dist'] ) ) . '</span></p>' : '' )
			. ( trim( $r['hours'] ?? '' ) ? '<p class="mem-place__meta"><span>' . memorial_plugin_icon( 'clock' ) . esc_html( trim( $r['hours'] ) ) . '</span></p>' : '' );
			if ( trim( wp_strip_all_tags( $r['text'] ?? '' ) ) !== '' || $map ) {
				$h .= ( '<details class="mem-cem__more"><summary>' . esc_html__( 'More', 'memorial-plugin' ) ) . ( $map ? __( ' and the map', 'memorial-plugin' ) : '' ) . '</summary>'
				. ( trim( $r['text'] ?? '' ) ? '<div class="mem-cem__text">' . wpautop( wp_kses_post( $r['text'] ) ) . '</div>' : '' )
				. ( $map ? '<div class="mem-cem__map" data-map-src="' . esc_url( $map ) . '" data-map-title="' . esc_attr( __( 'Map: ', 'memorial-plugin' ) . $name ) . '"></div>' : '' )
				. '</details>';
			}
			$h .= '</div></article>';
		}
		return $h . '</div></div>';
	}
);

/* Разметка для поисковиков: список кладбищ (schema.org Cemetery) */
add_action(
	'wp_head',
	function () {
		if ( ! is_page() || ! function_exists( 'memorial_plugin_ld' ) ) {
			return;
		}
		$p = get_queried_object();
		if ( ! $p || ! memorial_plugin_has_content_block( (string) $p->post_content, 'mem_cemeteries' ) ) {
			return;
		}
		$list = array();
		foreach ( memorial_plugin_cem_items() as $i => $r ) {
			$c = array(
				'@type' => 'Cemetery',
				'name'  => trim( $r['name'] ),
			);
			if ( trim( $r['addr'] ?? '' ) ) {
				$c['address'] = trim( $r['addr'] );
			}
			if ( ! empty( $r['img'] ) ) {
				$c['image'] = $r['img'];
			}
			if ( trim( $r['hours'] ?? '' ) ) {
				$c['openingHours'] = trim( $r['hours'] );
			}
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'item'     => $c,
			);
		}
		if ( $list ) {
			memorial_plugin_ld(
				array(
					'@context'        => 'https://schema.org',
					'@type'           => 'ItemList',
					'name'            => get_the_title( $p ),
					'itemListElement' => $list,
				)
			);
		}
	},
	7
);

/*
=========================================================
 * Новые страницы (черновики) и блок залов — один раз, при заходе в админку
 * =======================================================
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'mem_places_setup' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		update_option( 'mem_places_setup', 1, false );
		$made = array();
		$find = function ( $words ) {
			foreach ( get_pages( array( 'post_status' => 'publish,draft,private' ) ) as $p ) {
				foreach ( (array) $words as $w ) {
					if ( mb_stripos( $p->post_title . ' ' . urldecode( $p->post_name ), $w ) !== false ) {
						return $p;
					}
				}
			}
			return null;
		};

		// 1) Блок залов на странице «Поминальный обед»
		$pom = $find( array( 'поминальн', 'pominal' ) );
		if ( $pom && ! memorial_plugin_has_content_block( $pom->post_content, 'mem_restaurants' ) ) {
			$c   = $pom->post_content;
			$sc  = "<!-- wp:memorial-plugin/restaurants /-->\n\n";
			$pos = false;
			foreach ( array( '~<!-- wp:heading[^>]*-->\s*<h2[^>]*>\s*Поминальные традиции~u', '~<!-- wp:heading[^>]*-->\s*<h2[^>]*>\s*Частые вопросы~u', '~<!-- wp:shortcode -->\s*\[mem_cta~u', '~<!-- wp:memorial-plugin/cta[ /]~u' ) as $re ) {
				if ( preg_match( $re, $c, $m, PREG_OFFSET_CAPTURE ) ) {
					$pos = $m[0][1];
					break; }
			}
			$c = false === $pos ? $c . "\n\n" . $sc : substr( $c, 0, $pos ) . $sc . substr( $c, $pos );
			wp_update_post(
				array(
					'ID'           => $pom->ID,
					'post_content' => $c,
				)
			);
			$made[] = '<a href="' . esc_url( get_edit_post_link( $pom->ID ) ) . '">«' . esc_html( $pom->post_title ) . __( '»</a> — the halls block was added (the halls themselves — “Memorial → Halls for the memorial meal”)', 'memorial-plugin' );
		}

		// 3) Страница «Прижизненный договор»
		if ( ! $find( array( 'прижизненн', 'prizhiznenn' ) ) ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => 'Прижизненный договор на ритуальные услуги',
					'post_name'    => 'prizhiznennyj-dogovor',
					'post_content' => wp_slash( memorial_plugin_blocks_from_shortcodes( memorial_plugin_places_tpl( 'dogovor' ) ) ),
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$made[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html__( '“Pre-need funeral contract”', 'memorial-plugin' ) . '</a> — ' . esc_html__( 'a draft with text; check the terms and publish it', 'memorial-plugin' );
			}
		}
		if ( $made ) {
			set_transient( 'mem_places_report', $made, DAY_IN_SECONDS );
		}
	}
);

/*
Страница «Кладбища Белгорода и района» — черновик, если ни на одной странице ещё нет [mem_cemeteries].
	(1.5.0 по ошибке принимал за неё любую страницу со словом «кладбище» в названии и добавлял ссылки-карточки — это убрано.)
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'mem_places_fix1' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		update_option( 'mem_places_fix1', 1, false );
		$o = get_option( 'mem_cem' );
		if ( is_array( $o ) && ( isset( $o['page'] ) || isset( $o['links'] ) ) ) {
			unset( $o['page'], $o['links'] );
			update_option( 'mem_cem', $o ); }
		foreach ( get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => -1,
			)
		) as $p ) {
			if ( memorial_plugin_has_content_block( $p->post_content, 'mem_cemeteries' ) ) {
				return;
			}
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => 'Кладбища Белгорода и района',
				'post_name'    => 'kladbishha',
				'post_content' => wp_slash( memorial_plugin_blocks_from_shortcodes( memorial_plugin_places_tpl( 'cem' ) ) ),
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			$r   = (array) get_transient( 'mem_places_report' );
			$r[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html__( '“Cemeteries of Belgorod and the district”', 'memorial-plugin' ) . '</a> — ' . esc_html__( 'a draft; add the cemeteries in “Memorial → Cemeteries” and publish it', 'memorial-plugin' );
			set_transient( 'mem_places_report', array_filter( $r ), DAY_IN_SECONDS );
		}
	}
);

add_action(
	'admin_notices',
	function () {
		$r = get_transient( 'mem_places_report' );
		if ( ! $r || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		delete_transient( 'mem_places_report' );
		echo '<div class="notice notice-success is-dismissible"><p><b>Memorial Core:</b></p><ul style="list-style:disc;margin-left:2em">';
		foreach ( $r as $line ) {
			echo '<li>' . wp_kses_post( $line ) . '</li>';
		}
		echo '</ul></div>';
	}
);

/** Шаблоны текстов новых страниц
 *
 * @param int $k Key.
 */
function memorial_plugin_places_tpl_raw( $k ) {
	$p   = fn( $t, $cls = '' ) => '<!-- wp:paragraph' . ( $cls ? ' {"className":"' . $cls . '"}' : '' ) . " -->\n<p" . ( $cls ? ' class="' . $cls . '"' : '' ) . ">$t</p>\n<!-- /wp:paragraph -->\n\n";
	$h2  = fn( $t ) => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$t</h2>\n<!-- /wp:heading -->\n\n";
	$ul  = fn( array $li ) => "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode( '', array_map( fn( $x ) => "<!-- wp:list-item -->\n<li>$x</li>\n<!-- /wp:list-item -->", $li ) ) . "</ul>\n<!-- /wp:list -->\n\n";
	$ol  = fn( array $li ) => "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">" . implode( '', array_map( fn( $x ) => "<!-- wp:list-item -->\n<li>$x</li>\n<!-- /wp:list-item -->", $li ) ) . "</ol>\n<!-- /wp:list -->\n\n";
	$faq = fn( $q, $a ) => "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>$q</summary><!-- wp:paragraph -->\n<p>$a</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->\n\n";
	$sc  = fn( $s ) => "<!-- wp:shortcode -->\n$s\n<!-- /wp:shortcode -->\n\n";

	if ( 'cem' === $k ) {
		return $p( 'Кладбища Белгорода и Белгородского района: адреса, время работы и где сейчас возможны новые захоронения. Поможем выбрать место и оформить документы на захоронение.', 'is-style-lead' )
			. $sc( '[mem_cemeteries]' )
			. $h2( 'Как оформить захоронение' )
			. $ol(
				array(
					'Получите медицинское свидетельство о смерти и гербовое свидетельство в ЗАГСе (или через МФЦ).',
					'Выберите кладбище: новое место или подзахоронение в родственную могилу.',
					'Для подзахоронения подготовьте документы на захоронение родственника (удостоверение о захоронении) и подтверждение родства.',
					'Мы согласуем место и время с администрацией кладбища и подготовим могилу.',
				)
			)
			. $h2( 'Частые вопросы' )
			. $faq( 'Можно ли похоронить рядом с родственником?', 'Если кладбище открыто для подзахоронений и на участке есть место — да. Понадобятся документы на родственную могилу. Позвоните — проверим условия конкретного кладбища.' )
			. $faq( 'Сколько стоит место на кладбище?', 'На муниципальных кладбищах место для захоронения предоставляется бесплатно. Платные — услуги: копка могилы, перевозка, установка креста или памятника.' )
			. $faq( 'Вы работаете на всех кладбищах?', 'Да, организуем похороны на любом кладбище Белгорода и района, а также перевозку в другие города.' )
			. $sc( '[mem_cta title="Нужна помощь с выбором кладбища?" text="Подскажем, где есть места, и всё согласуем с администрацией."]' );
	}
	// прижизненный договор.
	return $p( 'Прижизненный договор — это заранее оформленная договорённость о ваших будущих похоронах: вы сами выбираете, как всё пройдёт, и фиксируете стоимость. Близким не придётся принимать решения и искать деньги в тяжёлый момент.', 'is-style-lead' )
		. $h2( 'Зачем заключают прижизненный договор' )
		. $ul(
			array(
				'<strong>Ваша воля будет исполнена</strong> — обряд, место захоронения, гроб, одежда, музыка, поминальный обед.',
				'<strong>Цена не вырастет</strong> — стоимость услуг по договору фиксируется на день заключения.',
				'<strong>Близким не нужно ничего решать</strong> — им достаточно позвонить нам, остальное мы сделаем по договору.',
				'<strong>Можно платить частями</strong> — порядок оплаты обсуждается при заключении договора.',
			)
		)
		. $h2( 'Что можно включить в договор' )
		. $ul(
			array(
				'Вид погребения: захоронение или кремация, кладбище, подзахоронение к родственникам.',
				'Ритуальные товары: гроб, венки, крест, одежда, принадлежности.',
				'Прощание: траурный зал, отпевание, транспорт для гостей.',
				'Поминальный обед, благоустройство могилы, памятник.',
			)
		)
		. $h2( 'Как заключить договор' )
		. $ol(
			array(
				'Позвоните или оставьте заявку — договоримся о встрече в удобное время, можем приехать на дом.',
				'Обсудим ваши пожелания и составим перечень услуг с ценами.',
				'Подпишем договор и согласуем порядок оплаты.',
				'Вы получите экземпляр договора. Расскажите близким, что договор есть и где он хранится.',
			)
		)
		. $h2( 'Частые вопросы' )
		. $faq( 'Можно ли изменить или расторгнуть договор?', 'Да. Пожелания можно изменить, договор — расторгнуть. Условия возврата оплаченных средств прописываются в самом договоре.' )
		. $faq( 'Что делать родственникам, когда придёт время?', 'Позвонить нам по телефону, указанному в договоре. Дальше мы действуем по договору: оформим документы и организуем похороны так, как было решено.' )
		. $faq( 'Можно заключить договор за родственника?', 'Договор заключается с согласия того, чьи похороны в нём описаны. Обсудим ваш случай по телефону.' )
		. $faq( 'Учитывается ли пособие на погребение?', 'Да, социальное пособие на погребение может получить родственник, и его можно направить на оплату услуг. Подробнее — на странице о пособии.' )
		. $sc( '[mem_cta title="Хотите обсудить договор?" text="Расскажем об условиях и ответим на вопросы — бесплатно и без обязательств."]' );
}

/** Заготовка страницы «Кладбища» / «Прижизненный договор». Шорткоды [mem_…] в заготовке сразу становятся блоками и значениями сайта (admin/convert.php).
 *
 * @param int $k Key.
 */
function memorial_plugin_places_tpl( $k ) {
	$content = memorial_plugin_places_tpl_raw( $k );
	return function_exists( 'memorial_plugin_convert_content' ) ? memorial_plugin_convert_content( $content ) : $content;
}

/* Interactivity API: модуль фильтра и карты, производное состояние — и на сервере (первая отрисовка без «мигания») */
add_action(
	'init',
	function () {
		$f = '/assets/js/view/cemeteries.js';
		wp_register_script_module( 'memorial-plugin-cemeteries', MEMORIAL_PLUGIN_URL . $f, array( '@wordpress/interactivity' ), (string) filemtime( MEMORIAL_PLUGIN_DIR . $f ) );
		wp_interactivity_state(
			'memorial-plugin/cemeteries',
			array(
				'isSelected' => static function () {
					$c = wp_interactivity_get_context();
					return ( $c['filter'] ?? '' ) === ( $c['value'] ?? '' );
				},
				'isHidden'   => static function () {
					$c = wp_interactivity_get_context();
					return ( $c['filter'] ?? '' ) !== '' && ( $c['filter'] ?? '' ) !== ( $c['status'] ?? '' );
				},
			)
		);
	}
);
