<?php
/**
 * Payment, delivery and guarantees on the pages (owner, 17.11.2026).
 *
 *  - “Payment” — a block from «Memorial → Prices → Payment»: the same ways of payment on every page; they also
 *    go into the data for search engines (paymentAccepted of the company, seo/schema.php).
 *  - “Delivery and visits” and “Guarantees” — variations of the core Group block with a ready text: the text
 *    is the page’s own and is edited right in the page editor. The variations are blocks of the inserter
 *    (“+” → Memorial); no settings screen of their own.
 *
 * The three cards look the same: a white card with an icon in a circle, a title and a list (assets/css/blocks.css,
 * .mterms). The icon is the plugin block “Icon in a circle”, so the styles of the plugin are loaded on the page.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ways of payment shown on the site: [[name, note], …].
 *
 * @return array<int,array{0:string,1:string}>
 */
function memorial_plugin_payment_items() {
	$out = array();
	foreach ( (array) ( memorial_plugin_prices( 'pay' )['items'] ?? array() ) as $row ) {
		if ( ! is_array( $row ) || empty( $row['show'] ) || '' === trim( (string) ( $row['name'] ?? '' ) ) ) {
			continue;
		}
		$out[] = array( trim( (string) $row['name'] ), trim( (string) ( $row['note'] ?? '' ) ) );
	}
	return $out;
}

/**
 * The “Payment” block.
 *
 * @return string
 */
function memorial_plugin_payment_render() {
	$items = memorial_plugin_payment_items();
	if ( ! $items ) {
		return '';
	}
	$pay   = memorial_plugin_prices( 'pay' );
	$title = trim( (string) ( $pay['title'] ?? '' ) );
	$title = '' !== $title ? memorial_plugin_t( $title ) : __( 'Payment', 'memorial-plugin' );
	$list  = '';
	foreach ( $items as [ $name, $note ] ) {
		$list .= '<li><b>' . esc_html( memorial_plugin_t( $name ) ) . '</b>' . ( '' !== $note ? ' — ' . esc_html( memorial_plugin_t( $note ) ) : '' ) . '</li>';
	}
	$note = trim( (string) ( $pay['note'] ?? '' ) );
	return '<section class="mterms mterms--pay">'
		. '<span class="feat__ico mterms__ico">' . memorial_plugin_icon( 'ruble' ) . '</span>'
		. '<div class="mterms__body"><h3 class="mterms__title">' . esc_html( $title ) . '</h3>'
		. '<ul class="mterms__list">' . $list . '</ul>'
		. ( '' !== $note ? '<p class="mterms__note">' . esc_html( memorial_plugin_t( $note ) ) . '</p>' : '' )
		. '</div></section>';
}

/**
 * Ready texts of the cards “Delivery and visits” and “Guarantees” (what the site already says; the owner edits them on
 * the pages).
 *
 * @return array<string,array{title:string,icon:string,items:string[]}>
 */
function memorial_plugin_terms_texts() {
	$delivery = '<span class="memorial-plugin-value" data-value="price" data-atts="{&quot;id&quot;:&quot;dostavka&quot;}">' . esc_html( function_exists( 'memorial_plugin_price_value' ) ? (string) memorial_plugin_price_value( 'dostavka' ) : '' ) . '</span>';
	return array(
		'delivery'  => array(
			'title' => 'Доставка и выезд',
			'icon'  => 'car',
			'items' => array(
				'Доставка венков по Белгороду — ' . $delivery,
				'Агент приедет в любое время суток — звонки принимаем круглосуточно',
				'Перевозка умерших по Белгороду, в другие города и регионы',
			),
		),
		'guarantee' => array(
			'title' => 'Гарантии',
			'icon'  => 'shield',
			'items' => array(
				'Гарантия на изготовление и установку памятника',
				'Гарантия на укладку плитки и установку оградки',
				'Хранение памятника до установки — бесплатно',
			),
		),
	);
}

/**
 * Block markup of a card (for the page content and the one-time placement).
 *
 * @param string $kind delivery|guarantee.
 * @return string
 */
function memorial_plugin_terms_markup( $kind ) {
	$t = memorial_plugin_terms_texts()[ $kind ] ?? null;
	if ( ! $t ) {
		return '';
	}
	$li = '';
	foreach ( $t['items'] as $item ) {
		$li .= "<!-- wp:list-item -->\n<li>" . $item . "</li>\n<!-- /wp:list-item -->\n\n";
	}
	return '<!-- wp:group {"metadata":{"name":"' . esc_attr( $t['title'] ) . '"},"className":"mterms mterms--' . $kind . '","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group mterms mterms--' . $kind . '"><!-- wp:memorial-plugin/icon {"name":"' . $t['icon'] . '","className":"mterms__ico"} /-->' . "\n\n"
		. '<!-- wp:group {"className":"mterms__body","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group mterms__body"><!-- wp:heading {"level":3,"className":"mterms__title"} -->' . "\n"
		. '<h3 class="wp-block-heading mterms__title">' . esc_html( $t['title'] ) . "</h3>\n<!-- /wp:heading -->\n\n"
		. '<!-- wp:list {"className":"mterms__list"} -->' . "\n" . '<ul class="wp-block-list mterms__list">' . rtrim( $li ) . "</ul>\n<!-- /wp:list --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->";
}

/*
 * The cards as blocks of the inserter: variations of the Group block with the ready text inside.
 * isActive by the class — the editor shows the name of the card in the list view and the toolbar.
 */
add_filter(
	'get_block_type_variations',
	static function ( $variations, $block_type ) {
		if ( 'core/group' !== $block_type->name ) {
			return $variations;
		}
		$labels = array(
			'delivery'  => array( __( 'Delivery and visits', 'memorial-plugin' ), __( 'A card with the delivery and the visits of the agent. The text is edited right on the page.', 'memorial-plugin' ) ),
			'guarantee' => array( __( 'Guarantees', 'memorial-plugin' ), __( 'A card with the guarantees. The text is edited right on the page.', 'memorial-plugin' ) ),
		);
		foreach ( $labels as $kind => [ $title, $desc ] ) {
			$blocks = parse_blocks( memorial_plugin_terms_markup( $kind ) );
			if ( ! $blocks ) {
				continue;
			}
			$variations[] = array(
				'name'        => 'memorial-' . $kind,
				'title'       => $title,
				'description' => $desc,
				'category'    => 'memorial-services',
				'icon'        => 'guarantee' === $kind ? 'shield' : 'car',
				'keywords'    => array( __( 'delivery', 'memorial-plugin' ), __( 'guarantee', 'memorial-plugin' ) ),
				'scope'       => array( 'inserter' ),
				'attributes'  => $blocks[0]['attrs'],
				'innerBlocks' => memorial_plugin_terms_inner( $blocks[0]['innerBlocks'] ),
				'isActive'    => array( 'className' ),
			);
		}
		return $variations;
	},
	10,
	2
);

/**
 * Parsed blocks → the innerBlocks format of a variation: [name, attributes, inner blocks].
 * The text of a heading or a list item becomes its “content” attribute.
 *
 * @param array $blocks Parsed blocks.
 * @return array
 */
function memorial_plugin_terms_inner( array $blocks ) {
	$out = array();
	foreach ( $blocks as $b ) {
		if ( empty( $b['blockName'] ) ) {
			continue;
		}
		$attrs = (array) $b['attrs'];
		if ( in_array( $b['blockName'], array( 'core/heading', 'core/list-item' ), true ) ) {
			$attrs['content'] = trim( (string) preg_replace( '~^\s*<(h\d|li)[^>]*>|</(h\d|li)>\s*$~', '', (string) $b['innerHTML'] ) );
		}
		$out[] = array( $b['blockName'], $attrs, memorial_plugin_terms_inner( (array) $b['innerBlocks'] ) );
	}
	return $out;
}

/**
 * Where the cards and the reviews stand (owner, 17.11.2026): page path => [blocks, words of the reviews].
 * 'reviews' => '' — all reviews; no 'reviews' key — no reviews block.
 *
 * @return array<string,array{blocks:string[],reviews?:string}>
 */
function memorial_plugin_terms_places_1() {
	$monuments = 'памятн, плитк, оград, благоустр, установ';
	$goods     = 'венок, венк, гроб, крест, корзин';
	$hall      = 'зал, прощан, отпеван, обед, поминк';
	return array(
		'ritualnye-uslugi'                => array(
			'blocks'  => array( 'payment' ),
			'reviews' => '',
		),
		'pomoshh-v-organizaczii-pohoron'  => array(
			'blocks'  => array( 'payment' ),
			'reviews' => '',
		),
		'ritualnye-uslugi/kremacziya'     => array(
			'blocks'  => array( 'payment' ),
			'reviews' => 'кремац, прах, урн',
		),
		'ritualnye-uslugi/traurnyj-zal'   => array(
			'blocks'  => array(),
			'reviews' => $hall,
		),
		'ritualnye-uslugi/pominalnyj-obed' => array(
			'blocks'  => array(),
			'reviews' => $hall,
		),
		'ritualnye-uslugi/gruz-200'       => array(
			'blocks'  => array( 'payment' ),
			'reviews' => 'перевоз, груз, другой город',
		),
		'pamyatniki'                      => array(
			'blocks'  => array( 'payment', 'guarantee' ),
			'reviews' => $monuments,
		),
		'blagoustrojstvo-zahoroneniya'    => array(
			'blocks'  => array( 'payment', 'guarantee' ),
			'reviews' => $monuments,
		),
		'blagoustrojstvo-zahoroneniya/ukladka-plitki-na-kladbishhe-v-belgorode' => array(
			'blocks'  => array( 'payment', 'guarantee' ),
			'reviews' => $monuments,
		),
		'ogradki'                         => array(
			'blocks'  => array( 'payment', 'guarantee' ),
			'reviews' => $monuments,
		),
		'venki'                           => array(
			'blocks'  => array( 'delivery', 'payment' ),
			'reviews' => $goods,
		),
		'groby'                           => array(
			'blocks'  => array( 'delivery', 'payment' ),
			'reviews' => $goods,
		),
		'metallicheskie-kresty'           => array(
			'blocks'  => array( 'delivery', 'payment' ),
			'reviews' => $goods,
		),
		'ritualnye-tovary'                => array(
			'blocks'  => array( 'delivery', 'payment' ),
			'reviews' => $goods,
		),
		'ceny'                            => array( 'blocks' => array( 'payment' ) ),
		'kontakty'                        => array( 'blocks' => array( 'payment', 'delivery' ) ),
	);
}

/**
 * Markup of one block of the placement.
 *
 * @param string $what payment|delivery|guarantee|reviews.
 * @param string $words Words of the reviews.
 * @return string
 */
function memorial_plugin_terms_block( $what, $words = '' ) {
	if ( 'payment' === $what ) {
		return '<!-- wp:memorial-plugin/payment /-->';
	}
	if ( 'reviews' === $what ) {
		return '<!-- wp:memorial-plugin/rewio-reviews ' . ( '' !== $words ? wp_json_encode( array( 'words' => $words ), JSON_UNESCAPED_UNICODE ) . ' ' : '' ) . '/-->';
	}
	return memorial_plugin_terms_markup( $what );
}

/**
 * Whether the page already has the block.
 *
 * @param string $content Page content.
 * @param string $what    payment|delivery|guarantee|reviews.
 * @return bool
 */
function memorial_plugin_terms_has( $content, $what ) {
	$needle = array(
		'payment'   => '<!-- wp:memorial-plugin/payment',
		'reviews'   => '<!-- wp:memorial-plugin/rewio-reviews',
		'delivery'  => 'mterms--delivery',
		'guarantee' => 'mterms--guarantee',
	)[ $what ];
	return false !== strpos( $content, $needle ) || ( 'reviews' === $what && false !== strpos( $content, '[mem_rewio' ) );
}

/**
 * Index of the top-level block before which the new blocks stand: the heading of the frequent questions,
 * otherwise the call to action at the end, otherwise the end of the page.
 *
 * @param array $blocks Top-level parsed blocks.
 * @return int
 */
function memorial_plugin_terms_position( array $blocks ) {
	foreach ( $blocks as $i => $b ) {
		if ( 'core/heading' === ( $b['blockName'] ?? '' ) && preg_match( '/част\w* вопрос|вопрос\w* и ответ/iu', wp_strip_all_tags( (string) $b['innerHTML'] ) ) ) {
			return $i;
		}
	}
	// The last block with content (an empty paragraph at the end does not count).
	$empty = static fn( $b ) => in_array( $b['blockName'], array( null, 'core/paragraph' ), true ) && '' === trim( wp_strip_all_tags( (string) $b['innerHTML'] ) );
	$last  = count( $blocks ) - 1;
	while ( $last >= 0 && $empty( $blocks[ $last ] ) ) {
		--$last;
	}
	if ( $last >= 0 && 'memorial-plugin/cta' === $blocks[ $last ]['blockName'] ) {
		return $last;
	}
	return $last + 1;
}

/**
 * The three questions of the “Payment” section of the “Questions and answers” page.
 *
 * @return array<string,array{0:string,1:string,2:string}> key => [question, answer (HTML), regular expression of a similar question]
 */
function memorial_plugin_terms_faq_1() {
	$list = array();
	$more = array();
	foreach ( memorial_plugin_payment_items() as [ $name, $note ] ) {
		if ( '' !== $note ) {
			$more[] = $name . ' — ' . $note . '.';
		} else {
			$list[] = $list ? mb_strtolower( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 ) : $name;
		}
	}
	$pay      = trim( ( $list ? implode( ', ', $list ) . '.' : '' ) . ' ' . implode( ' ', $more ) );
	$delivery = '<span class="memorial-plugin-value" data-value="price" data-atts="{&quot;id&quot;:&quot;dostavka&quot;}">' . esc_html( function_exists( 'memorial_plugin_price_value' ) ? (string) memorial_plugin_price_value( 'dostavka' ) : '' ) . '</span>';
	return array(
		'payment'   => array( 'Как можно оплатить?', esc_html( $pay ), '/оплат|рассрочк/iu' ),
		'delivery'  => array( 'Есть ли доставка и выезд?', 'Да. Доставка венков по Белгороду — ' . $delivery . '. Агент приедет в любое время суток — звонки принимаем круглосуточно. Перевозим умерших по Белгороду, в другие города и регионы.', '/доставк|выезд/iu' ),
		'guarantee' => array( 'Какая гарантия?', 'Даём гарантию на изготовление и установку памятника, укладку плитки и установку оградки. Хранение памятника до установки — бесплатно.', '/гаранти/iu' ),
	);
}

/**
 * The run. $dry = true — only what would change, nothing is written.
 *
 * @param bool $dry Dry run.
 * @return array Log: posts => [id => old content], titles, skipped.
 */
function memorial_plugin_terms_upgrade_1( $dry = false ) {
	global $wpdb;
	$log   = array( 'time' => time() );
	$write = static function ( $id, $old, $new ) use ( &$log, $dry, $wpdb ) {
		$log['posts'][ $id ] = $old;
		if ( ! $dry ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time move, no revisions.
			clean_post_cache( (int) $id );
		}
	};

	// 1. Cards and reviews on the pages.
	foreach ( memorial_plugin_terms_places_1() as $path => $place ) {
		$page = get_page_by_path( $path );
		if ( ! $page ) {
			$log['skipped'][] = $path;
			continue;
		}
		$content = (string) $page->post_content;
		$add     = array();
		foreach ( $place['blocks'] as $what ) {
			if ( ! memorial_plugin_terms_has( $content, $what ) ) {
				$add[] = memorial_plugin_terms_block( $what );
			}
		}
		if ( array_key_exists( 'reviews', $place ) && ! memorial_plugin_terms_has( $content, 'reviews' ) ) {
			$add[] = memorial_plugin_terms_block( 'reviews', (string) $place['reviews'] );
		}
		if ( ! $add ) {
			continue;
		}
		$blocks = parse_blocks( $content );
		$at     = memorial_plugin_terms_position( $blocks );
		$new    = parse_blocks( implode( "\n\n", $add ) );
		$new    = array_values( array_filter( $new, static fn( $b ) => null !== $b['blockName'] ) );
		array_splice( $blocks, $at, 0, $new );
		$write( $page->ID, $content, serialize_blocks( $blocks ) );
	}

	// 2. Questions in the “Payment” section of “Questions and answers” (none of a similar question on the page).
	$faq = get_page_by_path( 'poleznoe/vopros-otvet' );
	if ( $faq ) {
		$content = (string) $faq->post_content;
		$blocks  = parse_blocks( $content );
		$asked   = array();
		foreach ( $blocks as $b ) {
			if ( 'core/details' === ( $b['blockName'] ?? '' ) && preg_match( '~<summary>(.*?)</summary>~su', (string) $b['innerHTML'], $m ) ) {
				$asked[] = wp_strip_all_tags( $m[1] );
			}
		}
		$add = '';
		foreach ( memorial_plugin_terms_faq_1() as $key => [ $q, $a, $like ] ) {
			if ( array_filter( $asked, static fn( $x ) => preg_match( $like, $x ) && preg_match( '/как|ли|какая|какие|можно/iu', $x ) ) ) {
				$log['faq_skipped'][] = $key;
				continue;
			}
			$add .= '<!-- wp:details -->' . "\n" . '<details class="wp-block-details"><summary>' . esc_html( $q ) . '</summary><!-- wp:paragraph -->' . "\n<p>" . $a . "</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->\n\n";
		}
		if ( '' !== $add ) {
			// After the last question of the “Payment” section; no such section — a new one at the end.
			$at = null;
			foreach ( $blocks as $i => $b ) {
				if ( 'core/heading' === ( $b['blockName'] ?? '' ) && preg_match( '/оплат/iu', wp_strip_all_tags( (string) $b['innerHTML'] ) ) ) {
					$at = $i + 1;
					while ( isset( $blocks[ $at ] ) && in_array( $blocks[ $at ]['blockName'], array( 'core/details', null ), true ) && ( 'core/details' === $blocks[ $at ]['blockName'] || '' === trim( (string) $blocks[ $at ]['innerHTML'] ) ) ) {
						++$at;
					}
					break;
				}
			}
			if ( null === $at ) {
				$add = '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">Оплата</h2>' . "\n<!-- /wp:heading -->\n\n" . $add;
				$at  = count( $blocks );
			}
			$new = array_values( array_filter( parse_blocks( $add ), static fn( $b ) => null !== $b['blockName'] ) );
			array_splice( $blocks, $at, 0, $new );
			$write( $faq->ID, $content, serialize_blocks( $blocks ) );
		}
	}

	// 3. “Coffins”: the price code in the SEO title even without prices (the first move put the plain title).
	$coffins = get_page_by_path( 'groby' );
	$plain   = 'Гробы в Белгороде: цены %%sep%% %%sitetitle%%';
	if ( $coffins && get_post_meta( $coffins->ID, '_seopress_titles_title', true ) === $plain && function_exists( 'memorial_plugin_seo_registry' ) && isset( memorial_plugin_seo_registry()['groby_ot'] ) ) {
		$log['titles'][ $coffins->ID ] = $plain;
		if ( ! $dry ) {
			update_post_meta( $coffins->ID, '_seopress_titles_title', 'Гробы в Белгороде — %%mem_groby_ot%% %%sep%% %%sitetitle%%' );
		}
	}
	return $log;
}

add_action(
	'admin_init',
	static function () {
		if ( get_option( 'memorial_plugin_terms_1' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'memorial_plugin_terms_1', time(), false );
		update_option( 'memorial_plugin_terms_1_log', memorial_plugin_terms_upgrade_1(), false );
		if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
	},
	8
);
