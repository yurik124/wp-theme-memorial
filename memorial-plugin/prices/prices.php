<?php
/**
 * «Мемориал → Цены»: пакеты похорон, пакет кремации, отдельные цены.
 * Цены выводятся на страницах шорткодами и меняются сразу везде:
 *   [mem_packages]                 — карточки пакетов похорон
 *   [mem_cremation]                — таблица «Пакет кремации» с итогом
 *   [mem_price id="kremaciya"]     — итог кремации, «58 000 ₽»
 *   [mem_price paket="Эконом"]     — итог пакета похорон
 *   [mem_price id="lenta"]         — цена из «Отдельные цены» по коду
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prices schema.
 */
function memorial_plugin_prices_schema() {
	return array(
		'packages'  => array(
			'type'   => 'group',
			'label'  => __( 'Funeral packages', 'memorial-plugin' ),
			'help'   => __( 'Shown on the home page and by the “Funeral packages” block on any page.', 'memorial-plugin' ),
			'fields' => array(
				'badge' => array(
					'type'  => 'text',
					'label' => __( 'Label on the highlighted package', 'memorial-plugin' ),
				),
				'btn'   => array(
					'type'  => 'text',
					'label' => __( 'Button text', 'memorial-plugin' ),
				),
				'items' => array(
					'type'     => 'repeater',
					'label'    => __( 'Packages', 'memorial-plugin' ),
					'add'      => __( 'Add a package', 'memorial-plugin' ),
					'collapse' => 'name',
					'fields'   => array(
						'name'     => array(
							'type'  => 'text',
							'label' => __( 'Package name', 'memorial-plugin' ),
						),
						'featured' => array(
							'type'  => 'checkbox',
							'label' => __( 'Highlight (accent frame and the label)', 'memorial-plugin' ),
						),
						'rows'     => array(
							'type'   => 'repeater',
							'label'  => __( 'Contents of the package — the total is counted automatically. “On the card” — 3–5 main items, visible without opening the list', 'memorial-plugin' ),
							'add'    => __( 'Add a line item', 'memorial-plugin' ),
							'inline' => true,
							'fields' => array(
								'name'  => array(
									'type'  => 'text',
									'label' => __( 'Line item', 'memorial-plugin' ),
								),
								'price' => array(
									'type'  => 'number',
									'label' => __( 'Price, ₽', 'memorial-plugin' ),
								),
								'key'   => array(
									'type'  => 'checkbox',
									'label' => __( 'On the card', 'memorial-plugin' ),
								),
							),
						),
					),
				),
			),
		),
		'cremation' => array(
			'type'   => 'group',
			'label'  => __( 'Cremation package', 'memorial-plugin' ),
			'help'   => __( 'The table on the cremation page — the “Cremation” block. The total is counted automatically; in the text of other pages it is put with the “Site value” button → “Price”.', 'memorial-plugin' ),
			'fields' => array(
				'head_l'     => array(
					'type'  => 'text',
					'label' => __( 'Left column title', 'memorial-plugin' ),
				),
				'head_r'     => array(
					'type'  => 'text',
					'label' => __( 'Right column title', 'memorial-plugin' ),
				),
				'rows'       => array(
					'type'   => 'repeater',
					'label'  => __( 'What is included. “On the card” — the items visible on the cremation card without opening the list', 'memorial-plugin' ),
					'add'    => __( 'Add a line item', 'memorial-plugin' ),
					'inline' => true,
					'fields' => array(
						'name'  => array(
							'type'  => 'text',
							'label' => __( 'Line item', 'memorial-plugin' ),
						),
						'price' => array(
							'type'  => 'number',
							'label' => __( 'Price, ₽', 'memorial-plugin' ),
						),
						'key'   => array(
							'type'  => 'checkbox',
							'label' => __( 'On the card', 'memorial-plugin' ),
						),
					),
				),
				'total'      => array(
					'type'  => 'text',
					'label' => __( 'Caption of the total row', 'memorial-plugin' ),
				),
				'card'       => array(
					'type'  => 'checkbox',
					'label' => __( 'The “Cremation” card next to the funeral packages (where the “Funeral packages” block is; on other pages — the “Cremation card” setting of the block)', 'memorial-plugin' ),
				),
				'card_title' => array(
					'type'        => 'text',
					'label'       => __( 'Card title', 'memorial-plugin' ),
					'placeholder' => __( 'Cremation', 'memorial-plugin' ),
				),
			),
		),
		'other'     => array(
			'type'   => 'group',
			'label'  => __( 'Separate prices', 'memorial-plugin' ),
			'help'   => __( 'Prices mentioned in the texts of pages. In the text a price is put with the “Site value” button of the paragraph toolbar → “Price”. The price can be a number (500), a range (200–300) or words (free) — the ₽ sign is added to numbers by itself.', 'memorial-plugin' ),
			'fields' => array(
				'items' => array(
					'type'   => 'repeater',
					'label'  => __( 'Prices', 'memorial-plugin' ),
					'add'    => __( 'Add a price', 'memorial-plugin' ),
					'inline' => true,
					'fields' => array(
						'name'  => array(
							'type'  => 'text',
							'label' => __( 'Name', 'memorial-plugin' ),
						),
						'price' => array(
							'type'  => 'text',
							'label' => __( 'Price', 'memorial-plugin' ),
						),
						'code'  => array(
							'type'  => 'text',
							'label' => __( 'Code (Latin letters)', 'memorial-plugin' ),
						),
					),
				),
			),
		),
	);
}

/**
 * Prices defaults.
 */
function memorial_plugin_prices_defaults() {
	// пакеты раньше хранились в настройках главной — переносим оттуда.
	$old  = get_option( 'mem_home' );
	$home = memorial_plugin_home_defaults()['prices'];
	$src  = is_array( $old ) && ! empty( $old['prices']['items'] ) ? $old['prices'] : $home;
	return array(
		'packages'  => array(
			'badge' => $src['badge'] ?? $home['badge'],
			'btn'   => $src['btn'] ?? $home['btn'],
			'items' => $src['items'] ?? $home['items'],
		),
		'cremation' => array(
			'head_l'     => __( 'What is included', 'memorial-plugin' ),
			'head_r'     => __( 'Cost', 'memorial-plugin' ),
			'rows'       => array(
				array(
					'name'  => 'Транспортировка тела до крематория',
					'price' => 20000,
				),
				array(
					'name'  => 'Гроб в комплекте',
					'price' => 7000,
				),
				array(
					'name'  => 'Кремирование тела',
					'price' => 21500,
				),
				array(
					'name'  => 'Урна для праха с сумкой и капсулой',
					'price' => 4500,
				),
				array(
					'name'  => 'Выдача праха в день кремирования',
					'price' => 5000,
				),
			),
			'total'      => 'Итого с доставкой до крематория',
			'card'       => 1,
			'card_title' => '',
		),
		'other'     => array(
			'items' => array(
				array(
					'name'  => 'Надпись на ленту',
					'price' => '200–300',
					'code'  => 'lenta',
				),
				array(
					'name'  => 'Доставка венков по Белгороду',
					'price' => '500',
					'code'  => 'dostavka',
				),
				array(
					'name'  => 'Социальное пособие на погребение (государственное, обновляется 1 февраля)',
					'price' => '9 678,63',
					'code'  => 'posobie',
				),
			),
		),
	);
}

/**
 * Prices.
 *
 * @param mixed $section Section.
 */
function memorial_plugin_prices( $section ) {
	return memorial_plugin_settings( 'mem_prices' )[ $section ] ?? array(); }

add_filter(
	'memorial_plugin_admin_pages',
	function ( $pages ) {
		$vals = array( __( 'Cremation', 'memorial-plugin' ) . ' — ' . memorial_plugin_price_value( 'kremaciya' ) );
		foreach ( memorial_plugin_prices( 'other' )['items'] ?? array() as $it ) {
			if ( trim( $it['code'] ?? '' ) !== '' ) {
				$vals[] = ( '' !== trim( $it['name'] ?? '' ) ? $it['name'] : $it['code'] ) . ' — ' . memorial_plugin_price_value( $it['code'] );
			}
		}
		foreach ( memorial_plugin_packages() as $p ) {
			$vals[] = $p['name'] . ' — ' . memorial_plugin_rub( array_sum( array_column( $p['items'], 1 ) ) );
		}
		$pages['mem_prices'] = array(
			'slug'   => 'mem-prices',
			'title'  => __( 'Prices', 'memorial-plugin' ),
			'schema' => 'memorial_plugin_prices_schema',
			'intro'  => '<p>' . esc_html__( 'Prices from here are put into the pages automatically — change them only here. In the text of a page a price is put with the “Site value” button of the paragraph toolbar; catalog prices and the number of models are counted from the products.', 'memorial-plugin' ) . '</p>'
				. '<p><b>' . esc_html__( 'Prices now:', 'memorial-plugin' ) . '</b> ' . esc_html( implode( ' · ', $vals ) ) . '</p>'
				. memorial_plugin_prices_audit_html(),
		);
		return $pages;
	}
);

/* ---------- Вывод ---------- */

/**
 * Cremation total.
 */
function memorial_plugin_cremation_total() {
	return array_sum( array_map( fn( $r ) => (int) ( $r['price'] ?? 0 ), memorial_plugin_prices( 'cremation' )['rows'] ?? array() ) );
}

/** Число или диапазон → «500 ₽», «200–300 ₽»; слова — как есть
 *
 * @param mixed $v Value.
 */
function memorial_plugin_price_fmt( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( preg_match( '/^(\d[\d\s\x{00A0}\x{202F}]*(?:,\d{1,2})?)(?:\s*[–—-]\s*(\d[\d\s\x{00A0}\x{202F}]*(?:,\d{1,2})?))?\s*(?:₽|руб\.?)?$/u', $v, $m ) ) {
		$n = function ( $x ) {
			[$int, $kop] = array_pad( explode( ',', $x, 2 ), 2, '' );
			return number_format( (int) preg_replace( '/\D/', '', $int ), 0, '', "\u{202F}" ) . ( '' !== $kop ? ',' . $kop : '' );
		};
		return $n( $m[1] ) . ( isset( $m[2] ) && '' !== $m[2] ? '–' . $n( $m[2] ) : '' ) . "\u{00A0}₽";
	}
	return $v;
}

/**
 * Price value.
 *
 * @param int    $id ID.
 * @param string $paket Paket.
 */
function memorial_plugin_price_value( $id, $paket = '' ) {
	if ( '' !== $paket ) {
		foreach ( memorial_plugin_packages() as $p ) {
			if ( mb_strtolower( trim( $p['name'] ) ) === mb_strtolower( trim( $paket ) ) ) {
				return memorial_plugin_rub( array_sum( array_column( $p['items'], 1 ) ) );
			}
		}
		return '';
	}
	if ( 'kremaciya' === $id ) {
		return memorial_plugin_rub( memorial_plugin_cremation_total() );
	}
	foreach ( memorial_plugin_prices( 'other' )['items'] ?? array() as $it ) {
		if ( trim( $it['code'] ?? '' ) !== '' && strcasecmp( trim( $it['code'] ), $id ) === 0 ) {
			return memorial_plugin_price_fmt( $it['price'] ?? '' );
		}
	}
	return '';
}

add_shortcode(
	'mem_price',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'id'    => '',
				'paket' => '',
			),
			$atts,
			'mem_price'
		);
		return esc_html( memorial_plugin_price_value( trim( $a['id'] ), $a['paket'] ) );
	}
);

add_shortcode(
	'mem_cremation',
	function () {
		$c    = memorial_plugin_prices( 'cremation' );
		$rows = array_filter( $c['rows'] ?? array(), fn( $r ) => trim( $r['name'] ?? '' ) !== '' );
		if ( ! $rows ) {
			return '';
		}
		$h = '<figure class="wp-block-table is-style-price mem-cremation"><table><thead><tr><th>' . esc_html( $c['head_l'] ? $c['head_l'] : __( 'What is included', 'memorial-plugin' ) ) . '</th><th>' . esc_html( $c['head_r'] ? $c['head_r'] : __( 'Cost', 'memorial-plugin' ) ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$h .= '<tr><td>' . esc_html( $r['name'] ) . '</td><td class="mem-nw">' . ( (int) $r['price'] ? esc_html( memorial_plugin_rub( (int) $r['price'] ) ) : '' ) . '</td></tr>';
		}
		$h .= '</tbody><tfoot><tr><td>' . esc_html( $c['total'] ? $c['total'] : __( 'Total', 'memorial-plugin' ) ) . '</td><td class="mem-nw">' . esc_html( memorial_plugin_rub( memorial_plugin_cremation_total() ) ) . '</td></tr></tfoot></table></figure>';
		return $h;
	}
);

/*
---------- Разовый перевод страниц на шорткоды ----------
	Заменяем только то, что точно совпадает с текущими ценами; правка сохраняется как обычная редакция страницы,
	её можно откатить в «Редакциях».
 */
/**
 * Prices migrate.
 */
function memorial_plugin_prices_migrate() {
	$nbsp_re = '(?:\s|&nbsp;|\x{00A0}|\x{202F})';
	$num     = fn( $v ) => preg_replace( '/(\d)(?=(\d{3})+$)/', '$1' . $nbsp_re . '?', (string) $v ); // 58000 → 58(?:…)?000
	$done    = array();
	$pages   = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'posts_per_page' => -1,
		)
	);
	$saved   = get_option( 'mem_prices' );
	// в уже сохранённые «Отдельные цены» добавляем пособие, если его там нет.
	if ( is_array( $saved ) && ! in_array( 'posobie', array_column( $saved['other']['items'] ?? array(), 'code' ), true ) ) {
		$def                       = memorial_plugin_prices_defaults()['other']['items'];
		$saved['other']['items'][] = $def[ count( $def ) - 1 ];
		update_option( 'mem_prices', $saved );
	}

	foreach ( $pages as $p ) {
		$c    = $p->post_content;
		$orig = $c;

		// 1) таблица кремации → [mem_cremation]; если цен ещё не сохраняли — берём их из таблицы
		$c = preg_replace_callback(
			'~<!-- wp:table\b[^>]*-->.*?<!-- /wp:table -->~s',
			function ( $m ) use ( &$saved ) {
				if ( mb_stripos( $m[0], 'Кремирование' ) === false ) {
					return $m[0];
				}
				if ( ! is_array( $saved ) && preg_match_all( '~<tr><td>(.*?)</td><td>(.*?)</td></tr>~su', $m[0], $rr, PREG_SET_ORDER ) ) {
					$rows  = array();
					$total = '';
					foreach ( $rr as $r ) {
						$name = trim( wp_strip_all_tags( $r[1] ) );
						if ( preg_match( '/^итого/ui', $name ) ) {
							$total = $name;
							continue; }
						$rows[] = array(
							'name'  => $name,
							'price' => (int) preg_replace( '/\D/', '', html_entity_decode( $r[2] ) ),
						);
					}
					if ( $rows ) {
						$d                      = memorial_plugin_prices_defaults();
						$d['cremation']['rows'] = $rows;
						if ( $total ) {
							$d['cremation']['total'] = $total;
						}
						$saved = $d;
						update_option( 'mem_prices', $d );
					}
				}
				return "<!-- wp:shortcode -->\n[mem_cremation]\n<!-- /wp:shortcode -->";
			},
			$c
		);

		$krem = (int) array_sum( array_map( fn( $r ) => (int) ( $r['price'] ?? 0 ), ( is_array( $saved ) ? $saved : memorial_plugin_prices_defaults() )['cremation']['rows'] ) );
		// 2) итог кремации в тексте
		if ( $krem ) {
			$c = preg_replace( '/' . $num( $krem ) . $nbsp_re . '?(?:₽|рублей|руб\.?)/u', '[mem_price id="kremaciya"]', $c );
		}
		// 3) пособие на погребение, лента и доставка венков
		$c = preg_replace( '/9' . $nbsp_re . '?678,63' . $nbsp_re . '?(?:₽|рублей|руб\.?)/u', '[mem_price id="posobie"]', $c );
		$c = preg_replace( '/200' . $nbsp_re . '?[–—-]' . $nbsp_re . '?300' . $nbsp_re . '?(?:₽|рублей|руб\.?)/u', '[mem_price id="lenta"]', $c );
		$c = preg_replace( '/(Доставк[аи][^<]{0,60}?(?:<\/(?:strong|b)>)?' . $nbsp_re . '*(?:[—–-]' . $nbsp_re . '*|<\/td><td>))500' . $nbsp_re . '?(?:₽|рублей|руб\.?)/u', '$1[mem_price id="dostavka"]', $c );
		// 4) цифры каталога (число моделей, цены «от / до») — везде, где они совпадают с каталогом
		$c = memorial_plugin_catalog_numbers( $c, memorial_plugin_page_term( $p->ID ) );
		// 5) все шорткоды каталога — в понятный вид: cat="Венки" ot="нет" вместо cat="86" prefix="0"
		$c = memorial_plugin_sc_readable( $c );

		if ( $c !== $orig ) {
			wp_update_post(
				array(
					'ID'           => $p->ID,
					'post_content' => wp_slash( memorial_plugin_blocks_from_shortcodes( $c ) ),
				)
			);
			$done[] = $p->ID;
		}
	}
	// описания разделов каталога.
	foreach ( get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'hide_empty' => false,
		)
	) as $t ) {
		if ( '' === $t->description ) {
			continue;
		}
		$d = memorial_plugin_sc_readable( memorial_plugin_catalog_numbers( $t->description, $t->term_id ) );
		if ( $d !== $t->description ) {
			wp_update_term( $t->term_id, 'mem_cat', array( 'description' => $d ) );
		}
	}
	return $done;
}

/** Раздел каталога страницы: «Товары на странице» или блок [mem_catalog] в тексте
 *
 * @param mixed $pid Pid.
 */
function memorial_plugin_page_term( $pid ) {
	$v = get_post_meta( $pid, '_mem_products', true );
	if ( is_numeric( $v ) && (int) $v > 0 ) {
		return (int) $v;
	}
	return (int) get_post_meta( $pid, '_mem_cat', true );
}

/** Основа слова для поиска названия раздела в тексте: «Оградки» → «оград», «Венки» → «вен»
 *
 * @param string $text Text.
 */
function memorial_plugin_stems( $text ) {
	$w   = preg_split( '/[^\p{L}]+/u', str_replace( 'ё', 'е', mb_strtolower( $text ) ), -1, PREG_SPLIT_NO_EMPTY );
	$out = array();
	foreach ( $w as $x ) {
		if ( mb_strlen( $x ) >= 4 ) {
			$out[] = mb_substr( $x, 0, max( 3, mb_strlen( $x ) - 2 ) );
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Заменяет в HTML цифры каталога на шорткоды: «72 модели», «от 800 ₽», «от 800 до 9 500 ₽», «до 6 800 ₽», «Венки — 47, от 800 ₽».
 * Раздел для каждой фразы (абзаца, пункта списка, подписи, строки таблицы, вопроса) ищется так:
 * ссылка на страницу раздела → название раздела в тексте → раздел самой страницы.
 * Заменяется только число, которое СОВПАДАЕТ с текущими данными найденного раздела — остальное не трогаем.
 *
 * @param string $html Html.
 * @param int    $page_term Page term.
 */
function memorial_plugin_catalog_numbers( $html, $page_term = 0 ) {
	static $terms = null;
	if ( null === $terms ) {
		$terms = array();
		foreach ( get_terms(
			array(
				'taxonomy'   => 'mem_cat',
				'hide_empty' => false,
			)
		) as $t ) {
			$st = memorial_plugin_cat_stats( $t->term_id );
			if ( $st[0] ) {
				$terms[ $t->term_id ] = array(
					'stems' => memorial_plugin_stems( $t->name ),
					'st'    => $st,
				);
			}
		}
	}
	if ( ! $terms ) {
		return $html;
	}
	$nbsp_re = '(?:\s|&nbsp;|\x{00A0}|\x{202F})';
	$num_re  = '(\d{1,3}(?:' . $nbsp_re . '?\d{3})*)';
	$rub_re  = $nbsp_re . '*(?:₽|руб\.?|рублей)';
	$int     = fn( $x ) => (int) preg_replace( '/\D/', '', html_entity_decode( $x ) );

	// разделам, вставленным без cat="…" на странице раздела, проставляем раздел — чтобы цифра была верной и в «Вопрос–ответ».
	if ( $page_term ) {
		$html = preg_replace( '/\[(mem_cat_(?:price|count|list))(?![^\]]*\bcat=)/', '[$1 cat="' . $page_term . '"', $html );
	}

	return preg_replace_callback(
		'~<(p|li|figcaption|tr|h[1-6]|summary|dt|dd)\b[^>]*>.*?</\1>~su',
		function ( $seg ) use ( $terms, $page_term, $nbsp_re, $num_re, $rub_re, $int ) {
			$s = $seg[0];
			if ( ! preg_match( '/\d/', $s ) ) {
				return $s;
			}
			// кандидаты по порядку.
			$cand = array();
			if ( preg_match_all( '~href="([^"]+)"~', $s, $hm ) ) {
				foreach ( $hm[1] as $u ) {
					$pid = url_to_postid( html_entity_decode( $u ) );
					$tid = $pid ? memorial_plugin_page_term( $pid ) : 0;
					if ( $tid && isset( $terms[ $tid ] ) ) {
						$cand[] = $tid;
					}
				}
			}
			$txt    = str_replace( 'ё', 'е', mb_strtolower( html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES, 'UTF-8' ) ) );
			$scored = array();
			foreach ( $terms as $tid => $t ) {
				if ( ! $t['stems'] ) {
					continue;
				}
				$hit = 0;
				foreach ( $t['stems'] as $st ) {
					if ( preg_match( '/(?<!\p{L})' . preg_quote( $st, '/' ) . '/u', $txt ) ) {
						$hit++;
					}
				}
				if ( $hit >= min( 2, count( $t['stems'] ) ) ) {
					$scored[ $tid ] = $hit * 10 + count( $t['stems'] );
				}
			}
			arsort( $scored );
			$cand = array_merge( $cand, array_keys( $scored ) );
			if ( $page_term && isset( $terms[ $page_term ] ) ) {
				$cand[] = $page_term;
			}
			$cand = array_values( array_unique( $cand ) );
			if ( ! $cand ) {
				return $s;
			}
			$find = function ( $test ) use ( $cand, $terms ) {
				foreach ( $cand as $tid ) {
					if ( $test( $terms[ $tid ]['st'] ) ) {
						return $tid;
					}
				}
				return 0;
			};
			// «от X до Y ₽»
			$s = preg_replace_callback(
				'/(?<![\d\p{L}])([Оо]т)' . $nbsp_re . '+' . $num_re . '(?:' . $rub_re . ')?' . $nbsp_re . '+(до)' . $nbsp_re . '+' . $num_re . $rub_re . '/u',
				function ( $m ) use ( $find, $int ) {
					$a   = $int( $m[2] );
					$b   = $int( $m[4] );
					$tid = $find( fn( $st ) => $st[1] === $a && $st[2] === $b );
					return $tid ? $m[1] . "\u{00A0}" . '[mem_cat_price cat="' . $tid . '" prefix="0" rub="0"] ' . $m[3] . "\u{00A0}" . '[mem_cat_price cat="' . $tid . '" max="1" prefix="0"]' : $m[0];
				},
				$s
			);
			// «от X ₽»
			$s = preg_replace_callback(
				'/(?<![\d\p{L}])([Оо]т)' . $nbsp_re . '+' . $num_re . $rub_re . '/u',
				function ( $m ) use ( $find, $int ) {
					$a   = $int( $m[2] );
					$tid = $find( fn( $st ) => $st[1] === $a );
					return $tid ? $m[1] . "\u{00A0}" . '[mem_cat_price cat="' . $tid . '" prefix="0"]' : $m[0];
				},
				$s
			);
			// «до Y ₽»
			$s = preg_replace_callback(
				'/(?<![\d\p{L}])([Дд]о)' . $nbsp_re . '+' . $num_re . $rub_re . '/u',
				function ( $m ) use ( $find, $int ) {
					$a   = $int( $m[2] );
					$tid = $find( fn( $st ) => $st[2] === $a );
					return $tid ? $m[1] . "\u{00A0}" . '[mem_cat_price cat="' . $tid . '" max="1" prefix="0"]' : $m[0];
				},
				$s
			);
			// «72 модели»
			$s = preg_replace_callback(
				'/(?<![\d,.\x{202F}])(\d+)' . $nbsp_re . '+(?:моделей|модели|модель)(?!\p{L})/u',
				function ( $m ) use ( $find ) {
					$n   = (int) $m[1];
					$tid = $n > 1 ? $find( fn( $st ) => $st[0] === $n ) : 0;
					return $tid ? '[mem_cat_count cat="' . $tid . '"]' : $m[0];
				},
				$s
			);
			// «Венки — 47, от …»
			$s = preg_replace_callback(
				'/([—–]' . $nbsp_re . '*)(\d+)(?=,' . $nbsp_re . '*[Оо]т)/u',
				function ( $m ) use ( $find ) {
					$n   = (int) $m[2];
					$tid = $n > 1 ? $find( fn( $st ) => $st[0] === $n ) : 0;
					return $tid ? $m[1] . '[mem_cat_count cat="' . $tid . '" word="0"]' : $m[0];
				},
				$s
			);
			return $s;
		},
		$html
	);
}

add_action(
	'admin_init',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// кнопка «Перевести ещё раз» на странице «Цены».
		if ( isset( $_GET['mem_reprice'] ) && wp_verify_nonce( $_GET['mem_reprice'], 'mem_reprice' ) ) {
			update_option( 'mem_prices_migrated_pages', memorial_plugin_prices_migrate(), false );
			wp_safe_redirect( admin_url( 'admin.php?page=mem-prices&repriced=1' ) );
			exit;
		}
		if ( (int) get_option( 'mem_prices_migrated' ) >= 4 ) {
			return; // 2 — цифры каталога, 3 — пособие, 4 — понятные шорткоды
		}
		update_option( 'mem_prices_migrated', 4, false );
		update_option( 'mem_prices_migrated_pages', memorial_plugin_prices_migrate(), false );
		// пакеты из настроек главной сохраняем в «Цены», чтобы они не потерялись при следующем сохранении главной.
		if ( get_option( 'mem_prices' ) === false ) {
			add_option( 'mem_prices', memorial_plugin_prices_defaults() );
		}
	}
);

/* ---------- Проверка: цены в текстах, которые не меняются сами ---------- */
/** Фразы с ценой из текста: целые предложения / строки таблицы, где есть «₽» или «руб»
 *
 * @param string $html Html.
 */
function memorial_plugin_price_phrases( $html ) {
	$t   = preg_replace( '/\[[a-z_]+[^\]]*\]/', '', (string) $html );                      // то, что уже считается само.
	$t   = preg_replace( '~</(td)>~', ' — ', $t );
	$t   = preg_replace( '~</(tr|p|li|h\d|summary|div|figcaption)>|<br\s*/?>~', "\n", $t );
	$t   = html_entity_decode( wp_strip_all_tags( $t ), ENT_QUOTES, 'UTF-8' );
	$out = array();
	foreach ( preg_split( '/\n+|(?<=[.!?])\s+(?=[А-ЯЁA-Z«])/u', $t ) as $ph ) {
		$ph = trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', $ph ), " —\t" );
		if ( '' === $ph || ! preg_match( '/\d\s?(?:₽|руб)/u', $ph ) ) {
			continue;
		}
		if ( preg_match( '/участ\w*\s*\d+(?:,\d+)?\s*[×x]\s*\d/u', $ph ) ) {
			continue; // примеры выполненных работ «участок 2,5 × 3 м — 197 400 ₽» — это не прайс.
		}
		$out[] = mb_strimwidth( $ph, 0, 140, '…' );
	}
	return array_values( array_unique( $out ) );
}

/**
 * Prices audit.
 */
function memorial_plugin_prices_audit() {
	$out   = array();
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
		)
	);
	foreach ( $pages as $p ) {
		$ph = memorial_plugin_price_phrases( $p->post_content );
		if ( $ph ) {
			$out[] = array( $p, $ph );
		}
	}
	foreach ( get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'hide_empty' => false,
		)
	) as $term ) {
		$ph = memorial_plugin_price_phrases( $term->description );
		if ( $ph ) {
			$out[] = array( $term, $ph );
		}
	}
	return $out;
}

/**
 * Prices audit html.
 */
function memorial_plugin_prices_audit_html() {
	if ( ! is_admin() || ( $_GET['page'] ?? '' ) !== 'mem-prices' ) {
		return '';
	}
	$done = (array) get_option( 'mem_prices_migrated_pages', array() );
	$h    = '';
	if ( $done ) {
		$h .= ( '<br><br><b>' . esc_html__( 'Switched to automatic prices:', 'memorial-plugin' ) . '</b> ' ) . implode( ', ', array_map( fn( $id ) => '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>', $done ) )
			. __( '. The previous text is kept in the page’s “Revisions”.', 'memorial-plugin' );
	}
	$btn = ' <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=mem-prices' ), 'mem_reprice', 'mem_reprice' ) ) . ( '">' . esc_html__( 'Switch the prices in the texts to automatic ones', 'memorial-plugin' ) . '</a>' )
		. ( ' <span style="color:#666">' . esc_html__( '— the cremation total, the ribbon, the delivery and the catalog prices that match the current ones in the texts of pages become site values that change by themselves.', 'memorial-plugin' ) . '</span>' );
	if ( ! empty( $_GET['repriced'] ) ) {
		$h .= ( '<br><br>' . esc_html__( '✔ Done.', 'memorial-plugin' ) );
	}
	$list = memorial_plugin_prices_audit();
	if ( ! $list ) {
		return $h . ( '<br><br>' . esc_html__( '✔ No prices written into the texts by hand were found.', 'memorial-plugin' ) );
	}
	$h .= '<br><br>' . $btn;
	$h .= ( '<br><br><b>' . esc_html__( 'Prices written into the text by hand — they do not change by themselves', 'memorial-plugin' ) . '</b> ' . esc_html__( '(check whether they are up to date; replace them with a site value if you like):', 'memorial-plugin' ) . '<ul style="list-style:disc;margin-left:1.5em">' );
	foreach ( $list as [$obj, $lines] ) {
		$link = $obj instanceof WP_Term ? get_edit_term_link( $obj ) : get_edit_post_link( $obj->ID );
		$name = $obj instanceof WP_Term ? sprintf( /* translators: %s: catalog section */ __( 'Catalog section “%s”', 'memorial-plugin' ), $obj->name ) : $obj->post_title;
		$h   .= '<li style="margin-bottom:.6em"><a href="' . esc_url( $link ) . '"><b>' . esc_html( $name ) . '</b></a> (' . ( $obj instanceof WP_Term ? __( 'section description', 'memorial-plugin' ) : __( 'page', 'memorial-plugin' ) ) . ')<br>'
			. implode( '<br>', array_map( fn( $l ) => '— <i>' . esc_html( $l ) . '</i>', $lines ) ) . '</li>';
	}
	return $h . '</ul>';
}
