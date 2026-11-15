<?php
/**
 * Страница «Вопрос–ответ» (блок «Все вопросы и ответы», шорткод [mem_faq]): вопросы со всех опубликованных
 * страниц — из блоков «Подробности» — и свои разделы самой страницы.
 *
 * Разделы:
 *  - раздел страницы — вопросы одной страницы сайта: короткий ответ (первый абзац) и «Подробнее» — ссылка
 *    на этот вопрос на его странице; внизу ссылка на страницу;
 *  - свой раздел — на самой странице «Вопрос–ответ»: заголовок H2 и сразу под ним блоки «Подробности».
 *    Его вопросы есть только здесь: ответ полностью, разметка FAQPage — только у них.
 *
 * Название кнопки, заголовок, якорь, текст ссылки и порядок разделов — в боковой панели редактора
 * страницы «Вопрос–ответ» → «Разделы вопросов» (мета _mem_faq_sections). По умолчанию: кнопка — пункт меню
 * (иначе название страницы), порядок — как в меню, свои разделы — в конце.
 *
 *   [mem_faq exclude="12,34"]     — без разделов этих страниц (номера страниц)
 *   [mem_faq search="0"]          — без поля поиска
 *   [mem_faq chasto="документы, морг"] — свои слова «Часто ищут»
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Якорь вопроса: заданный в блоке «Подробности» (Дополнительно → Якорь HTML) или из текста вопроса.
 *
 * @param array  $b Block.
 * @param string $q Question.
 * @return string
 */
function memorial_plugin_faq_block_anchor( $b, $q ) {
	$id = (string) ( $b['attrs']['anchor'] ?? '' );
	if ( '' === $id && preg_match( '~^\s*<details\b[^>]*\sid="([^"]+)"~', (string) $b['innerHTML'], $m ) ) {
		$id = $m[1];
	}
	return '' !== $id ? $id : memorial_plugin_q_anchor( $q );
}

/** Вопросы одного набора блоков: [вопрос, ответ HTML, якорь]
 *
 * @param array $blocks Blocks.
 * @param array $out Out.
 */
function memorial_plugin_faq_from_blocks( array $blocks, array &$out ) {
	foreach ( $blocks as $b ) {
		if ( 'core/details' === $b['blockName'] ) {
			$qa = memorial_plugin_faq_details( $b );
			if ( $qa ) {
				$out[] = $qa;
			}
			continue;
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			memorial_plugin_faq_from_blocks( $b['innerBlocks'], $out );
		}
	}
}

/**
 * Вопрос и ответ одного блока «Подробности».
 *
 * @param array $b Block core/details.
 * @return array{0:string,1:string,2:string}|null
 */
function memorial_plugin_faq_details( $b ) {
	if ( ! preg_match( '~<summary[^>]*>(.*?)</summary>~s', (string) $b['innerHTML'], $m ) ) {
		return null;
	}
	$q = trim( wp_strip_all_tags( $m[1] ) );
	$a = '';
	foreach ( $b['innerBlocks'] as $ib ) {
		$a .= render_block( $ib );
	}
	$a = do_shortcode( $a );
	if ( '' === $q || '' === trim( wp_strip_all_tags( $a ) ) ) {
		return null;
	}
	return array( $q, $a, memorial_plugin_faq_block_anchor( $b, $q ) );
}

/**
 * Страница «Вопрос–ответ» — первая опубликованная страница с этим блоком.
 *
 * @return int
 */
function memorial_plugin_faq_hub_id() {
	$id = get_transient( 'mem_faq_hub' );
	if ( false !== $id ) {
		return (int) $id;
	}
	$id = 0;
	foreach ( get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	) as $p ) {
		if ( memorial_plugin_has_content_block( $p->post_content, 'mem_faq' ) ) {
			$id = (int) $p->ID;
			break;
		}
	}
	set_transient( 'mem_faq_hub', $id, DAY_IN_SECONDS );
	return $id;
}

/** Вопросы страниц сайта, по страницам (без страницы «Вопрос–ответ»). Кэш сбрасывается при сохранении страниц и меню. */
function memorial_plugin_faq_all() {
	$cached = get_transient( 'mem_faq_all_2' );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	// порядок разделов — как в главном меню, остальные страницы — по названию; подпись пункта меню — кнопка раздела.
	$order = array();
	$menu  = array();
	$i     = 0;
	foreach ( memorial_plugin_menu_items() as $it ) {
		if ( 'page' === $it->object && ! isset( $order[ (int) $it->object_id ] ) ) {
			$order[ (int) $it->object_id ] = $i++;
			$menu[ (int) $it->object_id ]  = trim( wp_strip_all_tags( (string) $it->title ) );
		}
	}
	$pages = array_filter(
		get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		),
		fn( $p ) => strpos( $p->post_content, 'wp:details' ) !== false
	);
	$out   = array();
	foreach ( $pages as $p ) {
		if ( memorial_plugin_has_content_block( $p->post_content, 'mem_faq' ) || post_password_required( $p ) ) {
			continue;
		}
		$qs = array();
		memorial_plugin_faq_from_blocks( parse_blocks( $p->post_content ), $qs );
		if ( $qs ) {
			$out[] = array(
				'id'    => $p->ID,
				'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
				'menu'  => $menu[ $p->ID ] ?? '',
				'url'   => get_permalink( $p ),
				'qs'    => $qs,
				'o'     => $order[ $p->ID ] ?? 999,
			);
		}
	}
	usort( $out, fn( $a, $b ) => $a['o'] <=> $b['o'] ? $a['o'] <=> $b['o'] : strcmp( $a['title'], $b['title'] ) );
	set_transient( 'mem_faq_all_2', $out, DAY_IN_SECONDS );
	return $out;
}

/** Сброс кэша вопросов. */
function memorial_plugin_faq_flush() {
	delete_transient( 'mem_faq_all_2' );
	delete_transient( 'mem_faq_hub' );
}
add_action( 'save_post_page', 'memorial_plugin_faq_flush' );
add_action( 'deleted_post', 'memorial_plugin_faq_flush' );
add_action( 'save_post_wp_navigation', 'memorial_plugin_faq_flush' );

/**
 * Свои разделы страницы «Вопрос–ответ»: заголовок H2 и сразу под ним блоки «Подробности».
 *
 * @param string $content Содержимое страницы.
 * @return array<int,array{title:string,anchor:string,qs:array,hide:string[]}>
 */
function memorial_plugin_faq_own( $content ) {
	$out  = array();
	$cur  = null;
	$head = null;
	$walk = static function ( $blocks ) use ( &$walk, &$out, &$cur, &$head ) {
		foreach ( $blocks as $b ) {
			$name = (string) ( $b['blockName'] ?? '' );
			if ( '' === $name ) {
				continue; // пустые строки между блоками.
			}
			if ( 'core/heading' === $name && 2 === (int) ( $b['attrs']['level'] ?? 2 ) ) {
				$cur  = null;
				$head = $b;
				continue;
			}
			if ( 'core/details' === $name && ( $head || $cur ) ) {
				$qa = memorial_plugin_faq_details( $b );
				if ( ! $qa ) {
					continue;
				}
				if ( $head ) {
					$title = trim( wp_strip_all_tags( (string) $head['innerHTML'] ) );
					$id    = (string) ( $head['attrs']['anchor'] ?? '' );
					if ( '' === $id && preg_match( '~<h2\b[^>]*\sid="([^"]+)"~', (string) $head['innerHTML'], $m ) ) {
						$id = $m[1];
					}
					$out[] = array(
						'title'  => $title,
						'anchor' => '' !== $id ? $id : memorial_plugin_slug( $title ),
						'qs'     => array(),
						'hide'   => array( memorial_plugin_faq_print( $head ) ),
					);
					$cur   = count( $out ) - 1;
					$head  = null;
				}
				$out[ $cur ]['qs'][]   = $qa;
				$out[ $cur ]['hide'][] = memorial_plugin_faq_print( $b );
				continue;
			}
			$cur  = null;
			$head = null;
			if ( ! empty( $b['innerBlocks'] ) ) {
				$walk( $b['innerBlocks'] );
				$cur  = null;
				$head = null;
			}
		}
	};
	$walk( parse_blocks( (string) $content ) );
	return array_values( array_filter( $out, static fn( $s ) => '' !== $s['title'] && $s['qs'] ) );
}

/**
 * Отпечаток блока — чтобы не показывать свои разделы на странице второй раз (их показывает блок).
 *
 * @param array $b Block.
 * @return string
 */
function memorial_plugin_faq_print( $b ) {
	return md5( (string) $b['blockName'] . '|' . (string) $b['innerHTML'] );
}

/**
 * Якорь латиницей: буквы, цифры, дефис (кириллица — транслитом).
 *
 * @param string $v Text.
 * @return string
 */
function memorial_plugin_faq_anchor_clean( $v ) {
	$v = function_exists( 'memorial_plugin_slug' ) ? memorial_plugin_slug( $v ) : sanitize_title( $v );
	return trim( preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( $v ) ), '-' );
}

/**
 * Настройки разделов страницы «Вопрос–ответ» как сохранены: order — ключи по порядку, items — ключ => поля.
 *
 * @param mixed $raw JSON or array.
 * @return array{order:string[],items:array<string,array<string,string>>}
 */
function memorial_plugin_faq_settings_clean( $raw ) {
	$v     = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	$v     = is_array( $v ) ? $v : array();
	$key   = static fn( $k ) => is_string( $k ) && preg_match( '/^(p\d+|h:[a-z0-9_-]{1,80})$/', $k ) ? $k : '';
	$order = array_values( array_unique( array_filter( array_map( $key, (array) ( $v['order'] ?? array() ) ) ) ) );
	$items = array();
	foreach ( (array) ( $v['items'] ?? array() ) as $k => $f ) {
		if ( '' === $key( $k ) || ! is_array( $f ) ) {
			continue;
		}
		$row = array(
			'label'  => mb_substr( trim( sanitize_text_field( (string) ( $f['label'] ?? '' ) ) ), 0, 60 ),
			'title'  => mb_substr( trim( sanitize_text_field( (string) ( $f['title'] ?? '' ) ) ), 0, 160 ),
			'anchor' => mb_substr( memorial_plugin_faq_anchor_clean( (string) ( $f['anchor'] ?? '' ) ), 0, 80 ),
			'more'   => mb_substr( trim( sanitize_text_field( (string) ( $f['more'] ?? '' ) ) ), 0, 160 ),
		);
		$row = array_filter( $row, 'strlen' );
		if ( $row ) {
			$items[ $k ] = $row;
		}
	}
	return array(
		'order' => $order,
		'items' => $items,
	);
}

/**
 * Разделы страницы «Вопрос–ответ» по порядку, с подписями и якорями.
 *
 * @param int $hub Страница «Вопрос–ответ».
 * @return array<int,array> key, kind (page|own), id, label, title, anchor, url, more, qs, defaults.
 */
function memorial_plugin_faq_sections( $hub ) {
	$hub  = (int) $hub;
	$set  = memorial_plugin_faq_settings_clean( $hub ? get_post_meta( $hub, '_mem_faq_sections', true ) : '' );
	$list = array();
	foreach ( memorial_plugin_faq_all() as $g ) {
		$label = '' !== $g['menu'] ? $g['menu'] : $g['title'];
		$def   = array(
			'label'  => $label,
			'title'  => $label,
			'anchor' => memorial_plugin_slug( $label ),
			/* translators: %s: page title */
			'more'   => sprintf( __( 'More — on the page “%s” →', 'memorial-plugin' ), $label ),
		);
		$list[ 'p' . $g['id'] ] = array(
			'kind'     => 'page',
			'id'       => (int) $g['id'],
			'url'      => $g['url'],
			'qs'       => $g['qs'],
			'page'     => $g['title'],
			'defaults' => $def,
		);
	}
	$hub_post = $hub ? get_post( $hub ) : null;
	foreach ( $hub_post ? memorial_plugin_faq_own( $hub_post->post_content ) : array() as $s ) {
		$anchor       = memorial_plugin_faq_anchor_clean( $s['anchor'] );
		$key          = 'h:' . substr( $anchor, 0, 80 );
		$list[ $key ] = array(
			'kind'     => 'own',
			'id'       => 0,
			'url'      => get_permalink( $hub_post ),
			'qs'       => $s['qs'],
			'page'     => '',
			'defaults' => array(
				'label'  => $s['title'],
				'title'  => $s['title'],
				'anchor' => $anchor,
				'more'   => '',
			),
		);
	}
	// Порядок: как сохранён, остальные — страницы как в меню, затем свои.
	$keys = array_values( array_unique( array_merge( array_values( array_intersect( $set['order'], array_keys( $list ) ) ), array_keys( $list ) ) ) );
	$out  = array();
	$used = array();
	foreach ( $keys as $k ) {
		$s    = $list[ $k ];
		$mine = $set['items'][ $k ] ?? array();
		// У своего раздела заголовок и якорь — из самого заголовка на странице; меняется только кнопка.
		$f = 'own' === $s['kind'] ? array_intersect_key( $mine, array( 'label' => 1 ) ) + $s['defaults'] : $mine + $s['defaults'];
		if ( 'page' === $s['kind'] && ! isset( $mine['title'] ) && isset( $mine['label'] ) ) {
			$f['title'] = $mine['label'];
		}
		$base   = '' !== $f['anchor'] ? $f['anchor'] : 'razdel';
		$anchor = $base;
		for ( $n = 2; isset( $used[ $anchor ] ); $n++ ) {
			$anchor = $base . '-' . $n;
		}
		$used[ $anchor ] = true;
		$out[]           = array(
			'key'    => $k,
			'anchor' => $anchor,
		) + $f + $s;
	}
	return $out;
}

/**
 * Начало ответа для раздела страницы: первый абзац (или начало текста) и есть ли продолжение.
 *
 * @param string $html Ответ.
 * @return array{0:string,1:bool}
 */
function memorial_plugin_faq_short( $html ) {
	$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
	if ( preg_match( '~<p\b[^>]*>.*?</p>~su', $html, $m ) && '' !== trim( wp_strip_all_tags( $m[0] ) ) ) {
		$short = $m[0];
	} else {
		$short = '<p>' . esc_html( wp_trim_words( $plain, 40 ) ) . '</p>';
	}
	$len = mb_strlen( trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $short ) ) ) );
	return array( $short, mb_strlen( $plain ) > $len + 10 );
}

/**
 * Вопросы разделов для поиска «Полезно знать»: вопрос, текст, адрес вопроса, раздел, номер страницы.
 *
 * @return array<int,array{q:string,t:string,u:string,g:string,page:int}>
 */
function memorial_plugin_faq_search_items() {
	$hub = memorial_plugin_faq_hub_id();
	$out = array();
	foreach ( $hub ? memorial_plugin_faq_sections( $hub ) : array_map(
		static fn( $g ) => array(
			'kind'  => 'page',
			'id'    => $g['id'],
			'url'   => $g['url'],
			'label' => $g['title'],
			'qs'    => $g['qs'],
		),
		memorial_plugin_faq_all()
	) as $s ) {
		foreach ( $s['qs'] as $qa ) {
			$out[] = array(
				'q'    => $qa[0],
				't'    => $qa[1],
				'u'    => $s['url'] . '#' . ( $qa[2] ?? memorial_plugin_q_anchor( $qa[0] ) ),
				'g'    => $s['label'],
				'page' => (int) $s['id'],
			);
		}
	}
	return $out;
}

add_shortcode(
	'mem_faq',
	function ( $atts ) {
		$a        = shortcode_atts(
			array(
				'exclude' => '',
				'search'  => '1',
				'chasto'  => '',
			),
			$atts,
			'mem_faq'
		);
		$skip     = array_map( 'intval', array_filter( explode( ',', $a['exclude'] ) ) );
		$sections = array_values( array_filter( memorial_plugin_faq_sections( (int) get_the_ID() ), fn( $s ) => ! in_array( $s['id'], $skip, true ) ) );
		if ( ! $sections ) {
			return current_user_can( 'edit_pages' ) ? ( '<p class="is-style-note">' . esc_html__( 'Questions and answers: there are no questions on the pages yet (“Details” blocks).', 'memorial-plugin' ) . '</p>' ) : '';
		}

		$total = array_sum( array_map( fn( $s ) => count( $s['qs'] ), $sections ) );
		ob_start();
		echo '<div class="mfaq" data-faq data-wp-interactive="memorial-plugin/faq" data-wp-init="callbacks.init">';
		if ( '0' !== $a['search'] ) {
			echo memorial_plugin_search_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				array(
					'id'          => 'mfaq-q',
					'label'       => __( 'Search the questions', 'memorial-plugin' ),
					'placeholder' => __( 'For example: documents', 'memorial-plugin' ),
					// «Часто ищут»: заданные в шорткоде слова, иначе — из статистики поиска (content/search-stats.php).
					'popular'     => trim( $a['chasto'] ) !== '' ? explode( ',', $a['chasto'] ) : ( function_exists( 'memorial_plugin_sq_popular' ) ? memorial_plugin_sq_popular( array( 'документы', 'стоимость', 'пособие', 'кремация', 'морг', 'памятник' ) ) : array( 'документы', 'стоимость', 'пособие', 'кремация', 'морг', 'памятник' ) ),
					'none'        => __(
						'There is no such question on the site yet. Call or leave your number — we will answer.',
						'memorial-plugin'
					),
				)
			);
		}
		if ( count( $sections ) > 1 ) {
			// Темы — фильтр: показывают вопросы одной темы на этой же странице.
			echo ( '<div class="srow"><div class="mfaq__toc srow__in" role="group" aria-label="' . esc_attr__( 'Question topics', 'memorial-plugin' ) . '"><button type="button" class="chip is-on" data-faq-group="" aria-pressed="true">' . esc_html__( 'All', 'memorial-plugin' ) . ' <small>' ) . (int) $total . '</small></button>';
			foreach ( $sections as $s ) {
				echo '<button type="button" class="chip" data-faq-group="' . esc_attr( $s['anchor'] ) . '" aria-pressed="false">' . esc_html( $s['label'] ) . ' <small>' . (int) count( $s['qs'] ) . '</small></button>';
			}
			echo '</div></div>';
		}
		$used = array();
		foreach ( $sections as $s ) {
			echo '<section class="mfaq__group" id="' . esc_attr( $s['anchor'] ) . '"><h2 class="mfaq__title">' . esc_html( $s['title'] ) . '</h2>';
			foreach ( $s['qs'] as $qa ) {
				[ $q, $ans ] = $qa;
				$id          = $qa[2] ?? memorial_plugin_q_anchor( $q );
				$used[ $id ] = ( $used[ $id ] ?? 0 ) + 1;
				$own_id      = $used[ $id ] > 1 ? $id . '-' . $used[ $id ] : $id;
				$full        = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $ans ) ) );
				if ( 'page' === $s['kind'] ) {
					// Раздел страницы: начало ответа и «Подробнее» — к этому вопросу на его странице.
					[ $short, $more ] = memorial_plugin_faq_short( $ans );
					$body             = $short . ( $more ? '<p class="mfaq__qmore"><a href="' . esc_url( $s['url'] . '#' . $id ) . '">' . esc_html__( 'More details', 'memorial-plugin' ) . ' <span aria-hidden="true">→</span></a></p>' : '' );
				} else {
					$body = $ans;
				}
				echo '<details class="wp-block-details" id="' . esc_attr( $own_id ) . '" data-g="' . esc_attr( $s['label'] ) . '" data-t="' . esc_attr( mb_substr( $full, 0, 1500 ) ) . '"><summary>' . esc_html( $q ) . '</summary>' . $body . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the answer is post content passed through the_content.
			}
			if ( 'page' === $s['kind'] && '' !== $s['more'] ) {
				echo '<p class="mfaq__more"><a href="' . esc_url( $s['url'] ) . '">' . esc_html( $s['more'] ) . '</a></p>';
			}
			echo '</section>';
		}
		echo '</div>';
		return ob_get_clean();
	}
);

/**
 * Свои разделы показывает блок «Все вопросы и ответы» — на странице они не повторяются.
 *
 * @param string $html  Block HTML.
 * @param array  $block Block.
 * @return string
 */
function memorial_plugin_faq_hide_own( $html, $block ) {
	static $prints = array();
	$name = (string) ( $block['blockName'] ?? '' );
	if ( ( 'core/heading' !== $name && 'core/details' !== $name ) || is_admin() || wp_is_json_request() ) {
		return $html;
	}
	$post = get_post();
	if ( ! $post || 'page' !== $post->post_type || ! memorial_plugin_has_content_block( $post->post_content, 'mem_faq' ) ) {
		return $html;
	}
	if ( ! isset( $prints[ $post->ID ] ) ) {
		$prints[ $post->ID ] = array();
		foreach ( memorial_plugin_faq_own( $post->post_content ) as $s ) {
			foreach ( $s['hide'] as $h ) {
				$prints[ $post->ID ][ $h ] = true;
			}
		}
	}
	return isset( $prints[ $post->ID ][ memorial_plugin_faq_print( $block ) ] ) ? '' : $html;
}
add_filter( 'render_block', 'memorial_plugin_faq_hide_own', 5, 2 );

/* Разметка FAQPage на странице «Вопрос–ответ» — только свои вопросы: вопросы других страниц размечены у себя
   (Google: один и тот же вопрос размечается на сайте один раз). */
add_action(
	'wp_head',
	function () {
		if ( ! is_page() || ! memorial_plugin_has_content_block( (string) get_queried_object()->post_content, 'mem_faq' ) || ! function_exists( 'memorial_plugin_ld' ) ) {
			return;
		}
		$items = array();
		foreach ( memorial_plugin_faq_own( (string) get_queried_object()->post_content ) as $s ) {
			foreach ( $s['qs'] as $qa ) {
				$items[] = array(
					'@type'          => 'Question',
					'name'           => $qa[0],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $qa[1] ) ) ),
					),
				);
			}
		}
		if ( $items ) {
			memorial_plugin_ld(
				array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => $items,
				)
			);
		}
	},
	7
);

/**
 * REST: разделы страницы «Вопрос–ответ» для настройки в редакторе (по сохранённому содержимому).
 */
function memorial_plugin_faq_rest() {
	register_rest_route(
		'memorial-plugin/v1',
		'/faq/sections',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => static fn( WP_REST_Request $r ) => current_user_can( 'edit_post', (int) $r['hub'] ),
			'args'                => array(
				'hub' => array(
					'type'     => 'integer',
					'required' => true,
				),
			),
			'callback'            => static function ( WP_REST_Request $r ) {
				memorial_plugin_faq_flush();
				return rest_ensure_response(
					array_map(
						static fn( $s ) => array(
							'key'      => $s['key'],
							'kind'     => $s['kind'],
							'page'     => $s['page'],
							'count'    => count( $s['qs'] ),
							'defaults' => $s['defaults'],
						),
						memorial_plugin_faq_sections( (int) $r['hub'] )
					)
				);
			},
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_faq_rest' );
