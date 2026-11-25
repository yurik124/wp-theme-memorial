<?php
/**
 * Блоки цен и условий (ТЗ «Блоки и доработки» 24.11.2026, шаг 2):
 *  - «Наши гарантии» (memorial-plugin/guarantees) — пункты из «Мемориал → Цены → Гарантии»;
 *  - «Пособие и выплаты» (memorial-plugin/benefits) — суммы из кодов posobie и vyplata_svo, текст задан жёстко;
 *  - «Цены по отдельности» (memorial-plugin/price-list) — таблица «услуга — цена от — подробнее» из кодов;
 *  - дата «Обновлено» у отдельных цен ставится сама, когда цена меняется;
 *  - «Цены обновлены» (значение сайта prices-updated, в файлах страниц — {prices_updated}): месяц и год
 *    последней правки цен — пакетов, кремации, отдельных цен или цены товара каталога;
 *  - разовый перенос memorial_plugin_prices_2: код vyplata_svo, коды наборов «Цен по отдельности» (без цен —
 *    их вписывает владелец), вариант «Оплаты» на страницах по разделу сайта.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Наши гарантии ---------- */

/**
 * The points by default (from the company card).
 *
 * @return array<int,array>
 */
function memorial_plugin_guarantees_defaults() {
	$p = static fn( $icon, $title, $text, $for = '' ) => array(
		'icon'  => $icon,
		'title' => $title,
		'text'  => $text,
		'for'   => $for,
		'show'  => 1,
	);
	return array(
		$p( 'doc', 'Цена фиксируется в договоре', 'Сумма, которую мы назвали, записывается в договор и не растёт по ходу работ.' ),
		$p( 'car', 'Бесплатный выезд агента', 'По Белгороду агент приедет в течение часа или в удобное вам время — бесплатно.' ),
		$p( 'heart', 'С вами работают 1–2 человека', 'Один-два сотрудника ведут всё от начала до конца, без колл-центров и пересказов.' ),
		$p( 'phone', 'Приезжаем только по вашему звонку', 'Не сотрудничаем со скорой и полицией по передаче адресов — никто не придёт к вам без звонка.' ),
		$p( 'check', 'Гарантии на работы', 'Гарантия на изготовление и установку записывается в акт выполненных работ.', 'monuments' ),
		$p( 'building', 'Храним памятник бесплатно', 'Готовый памятник бесплатно хранится у нас до установки.', 'monuments' ),
		$p( 'ruble', 'Своя рассрочка до 12 месяцев', 'Без банков и справок — на памятники и благоустройство.', 'monuments' ),
	);
}

/**
 * Points of a set: funeral — “for everything” and “funeral” (never the instalment plan), monuments — “for everything”
 * and “monuments”, all — every point.
 *
 * @param string $set funeral | monuments | all.
 * @return array<int,array>
 */
function memorial_plugin_guarantees_items( $set = 'all' ) {
	$out = array();
	foreach ( (array) ( memorial_plugin_prices( 'guarantees' )['items'] ?? array() ) as $it ) {
		if ( ! is_array( $it ) || empty( $it['show'] ) || '' === trim( (string) ( $it['title'] ?? '' ) ) ) {
			continue;
		}
		$for = (string) ( $it['for'] ?? '' );
		if ( 'all' !== $set && '' !== $for && $for !== $set ) {
			continue;
		}
		// The rule of the spec: no instalment plan for a funeral, whatever the point says.
		if ( 'funeral' === $set && preg_match( '/рассрочк/iu', $it['title'] . ' ' . ( $it['text'] ?? '' ) ) ) {
			continue;
		}
		$out[] = $it;
	}
	return array_slice( $out, 0, 8 );
}

/**
 * The “Our guarantees” block.
 *
 * @param array $a Attributes: set, count, title, heading, cols.
 * @return string
 */
function memorial_plugin_guarantees_render( $a = array() ) {
	$set   = (string) ( $a['set'] ?? 'all' );
	$set   = in_array( $set, array( 'funeral', 'monuments', 'all' ), true ) ? $set : 'all';
	$items = memorial_plugin_guarantees_items( $set );
	$count = (int) ( $a['count'] ?? 0 );
	if ( $count > 0 ) {
		$items = array_slice( $items, 0, $count );
	}
	if ( ! $items ) {
		return '';
	}
	$cols  = 2 === (int) ( $a['cols'] ?? 3 ) ? 2 : 3;
	$title = trim( (string) ( $a['title'] ?? '' ) );
	if ( '' === $title ) {
		$title = trim( (string) ( memorial_plugin_prices( 'guarantees' )['title'] ?? '' ) );
	}
	$title = '' !== $title ? memorial_plugin_t( $title ) : __( 'Our guarantees', 'memorial-plugin' );
	$html  = '';
	foreach ( $items as $it ) {
		$html .= '<div class="mguar__item">'
			. '<span class="feat__ico mguar__ico">' . memorial_plugin_icon( (string) ( $it['icon'] ?? 'shield' ) ) . '</span>'
			. '<h3 class="mguar__name">' . esc_html( memorial_plugin_t( (string) $it['title'] ) ) . '</h3>'
			. ( '' !== trim( (string) ( $it['text'] ?? '' ) ) ? '<p class="mguar__text">' . esc_html( memorial_plugin_t( (string) $it['text'] ) ) . '</p>' : '' )
			. '</div>';
	}
	return '<section class="mguar">'
		. ( ! isset( $a['heading'] ) || ! empty( $a['heading'] ) ? '<h2 class="wp-block-heading mguar__title">' . esc_html( $title ) . '</h2>' : '' )
		. '<div class="mguar__grid mguar__grid--' . $cols . '">' . $html . '</div></section>';
}

/* ---------- Пособие и выплаты ---------- */

/**
 * The “Benefits and payments” block. The words are fixed: the company gives the documents, the relative claims
 * the payment (never “we will claim it for you”).
 *
 * @param array $a Attributes: show, title, heading, link.
 * @return string
 */
function memorial_plugin_benefits_render( $a = array() ) {
	$show  = (string) ( $a['show'] ?? 'both' );
	$show  = in_array( $show, array( 'both', 'posobie', 'svo' ), true ) ? $show : 'both';
	$cards = array();
	$page  = static function ( $path ) {
		$p = get_page_by_path( $path );
		return $p && 'publish' === $p->post_status ? (string) get_permalink( $p ) : '';
	};
	if ( 'svo' !== $show ) {
		$sum = (string) memorial_plugin_price_value( 'posobie' );
		if ( '' !== $sum ) {
			$cards[] = array( 'doc', __( 'Social benefit for a burial', 'memorial-plugin' ), $sum, __( 'through the Social Fund of Russia', 'memorial-plugin' ), '' );
		}
	}
	if ( 'posobie' !== $show ) {
		$sum = (string) memorial_plugin_price_value( 'vyplata_svo' );
		if ( '' !== $sum ) {
			$cards[] = array( 'shield', __( 'Payment for the burial of a participant of the special military operation', 'memorial-plugin' ), $sum, __( 'in the Belgorod region, through the military enlistment office', 'memorial-plugin' ), $page( 'ritualnye-uslugi/pohorony-uchastnikov-svo' ) );
		}
	}
	if ( ! $cards ) {
		return memorial_plugin_block_note( __( 'Benefits and payments: fill in the prices with the codes posobie and vyplata_svo in Memorial → Prices → Separate prices.', 'memorial-plugin' ) );
	}
	$html = '';
	foreach ( $cards as [ $icon, $name, $sum, $how, $url ] ) {
		$html .= '<div class="mbenefit__item">'
			. '<span class="feat__ico mbenefit__ico">' . memorial_plugin_icon( $icon ) . '</span>'
			. '<h3 class="mbenefit__name">' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</h3>'
			. '<p class="mbenefit__sum nowrap">' . esc_html( $sum ) . '</p>'
			. '<p class="mbenefit__how">' . esc_html( $how ) . '</p>'
			. '</div>';
	}
	$title = trim( (string) ( $a['title'] ?? '' ) );
	$title = '' !== $title ? $title : __( 'Benefit and payments for a burial', 'memorial-plugin' );
	$more  = ! isset( $a['link'] ) || ! empty( $a['link'] ) ? $page( 'poleznoe/posobie-na-pogrebenie' ) : '';
	return '<section class="mbenefit">'
		. ( ! isset( $a['heading'] ) || ! empty( $a['heading'] ) ? '<h2 class="wp-block-heading mbenefit__title">' . esc_html( $title ) . '</h2>' : '' )
		. '<div class="mbenefit__grid mbenefit__grid--' . count( $cards ) . '">' . $html . '</div>'
		. '<p class="mbenefit__note">' . esc_html__( 'We give the full set of documents for claiming the payments. The payment is claimed by a relative.', 'memorial-plugin' ) . '</p>'
		. ( '' !== $more ? '<p class="mbenefit__more"><a href="' . esc_url( $more ) . '">' . esc_html__( 'More about the benefit', 'memorial-plugin' ) . ' <span aria-hidden="true">→</span></a></p>' : '' )
		. '</section>';
}

/* ---------- Цены по отдельности ---------- */

/**
 * Units of a price row.
 *
 * @return array<string,string>
 */
function memorial_plugin_price_list_units() {
	return array(
		''      => __( 'no unit', 'memorial-plugin' ),
		'm2'    => __( 'per m²', 'memorial-plugin' ),
		'km'    => __( 'per km', 'memorial-plugin' ),
		'trip'  => __( 'per trip', 'memorial-plugin' ),
		'metre' => __( 'per running metre', 'memorial-plugin' ),
	);
}

/**
 * The sets of rows by default: [code, name, page path, unit]. Codes without a price on the site are not shown.
 *
 * @return array<string,array{label:string,rows:array}>
 */
function memorial_plugin_price_list_presets() {
	$r = static fn( $code, $label, $page = '', $unit = '' ) => array(
		'code'  => $code,
		'label' => $label,
		'page'  => $page,
		'unit'  => $unit,
	);
	return array(
		'funeral'   => array(
			'label' => __( 'Funeral and goods', 'memorial-plugin' ),
			'rows'  => array(
				$r( 'kremaciya', 'Кремация', '/ritualnye-uslugi/kremacziya/' ),
				$r( 'venki_ot', 'Венки', '/venki/' ),
				$r( 'groby_ot', 'Гробы', '/groby/' ),
				$r( 'kresty_ot', 'Кресты', '/metallicheskie-kresty/' ),
				$r( 'gruz_200', 'Перевозка умерших (груз 200)', '/ritualnye-uslugi/gruz-200/', 'km' ),
				$r( 'katafalk', 'Катафалк', '/ritualnye-uslugi/katafalk/', 'trip' ),
				$r( 'avtobus', 'Автобус на похороны', '', 'trip' ),
			),
		),
		'monuments' => array(
			'label' => __( 'Monuments and landscaping', 'memorial-plugin' ),
			'rows'  => array(
				$r( 'pamyatniki_ot', 'Памятники', '/pamyatniki/' ),
				$r( 'ustanovka_pamyatnika', 'Установка памятника', '/blagoustrojstvo-zahoroneniya/ustanovka-pamyatnikov/' ),
				$r( 'demontazh', 'Демонтаж старого памятника' ),
				$r( 'plitka', 'Укладка плитки', '/blagoustrojstvo-zahoroneniya/ukladka-plitki-na-kladbishhe-v-belgorode/', 'm2' ),
				$r( 'kroshka', 'Мраморная крошка', '', 'm2' ),
				$r( 'ogradka', 'Оградка', '/ogradki/', 'metre' ),
				$r( 'ustanovka_ogradki', 'Установка оградки', '/ogradki/' ),
				$r( 'uhod_za_mogiloj', 'Уход за могилой' ),
			),
		),
	);
}

/**
 * Is there a price for the code on the site (an own price that is empty — no)?
 *
 * @param string $code Code.
 * @return string The price text or ''.
 */
function memorial_plugin_price_list_value( $code ) {
	$code = trim( (string) $code );
	if ( '' === $code ) {
		return '';
	}
	foreach ( memorial_plugin_prices( 'other' )['items'] ?? array() as $it ) {
		if ( strcasecmp( trim( (string) ( $it['code'] ?? '' ) ), $code ) === 0 && in_array( $it['what'] ?? '', array( '', 'custom' ), true ) && '' === trim( (string) ( $it['price'] ?? '' ) ) ) {
			return '';
		}
	}
	return trim( (string) memorial_plugin_price_value( $code ) );
}

/**
 * Address of a row page: a page ID or a path («/ceny/»).
 *
 * @param mixed $page ID or path.
 * @return string
 */
function memorial_plugin_price_list_url( $page ) {
	if ( is_numeric( $page ) && (int) $page > 0 ) {
		return 'publish' === get_post_status( (int) $page ) ? (string) get_permalink( (int) $page ) : '';
	}
	$path = trim( (string) $page, '/' );
	if ( '' === $path ) {
		return '';
	}
	$p = get_page_by_path( $path );
	return $p && 'publish' === $p->post_status ? (string) get_permalink( $p ) : '';
}

/**
 * The “Prices one by one” block.
 *
 * @param array $a Attributes: rows, preset, title, note.
 * @return string
 */
function memorial_plugin_price_list_render( $a = array() ) {
	$rows = is_array( $a['rows'] ?? null ) ? $a['rows'] : array();
	if ( ! $rows ) {
		$rows = memorial_plugin_price_list_presets()[ (string) ( $a['preset'] ?? '' ) ]['rows'] ?? array();
	}
	$units = memorial_plugin_price_list_units();
	$body  = '';
	foreach ( $rows as $row ) {
		$value = memorial_plugin_price_list_value( $row['code'] ?? '' );
		if ( '' === $value ) {
			continue;
		}
		$label = trim( (string) ( $row['label'] ?? '' ) );
		if ( '' === $label ) {
			$label = (string) ( memorial_plugin_values()[ memorial_plugin_value_key( (string) $row['code'] ) ]['name'] ?? $row['code'] );
		}
		$unit  = (string) ( $row['unit'] ?? '' );
		$unit  = '' !== $unit && isset( $units[ $unit ] ) ? ' <span class="mprice-list__unit">' . esc_html( $units[ $unit ] ) . '</span>' : '';
		$url   = memorial_plugin_price_list_url( $row['page'] ?? '' );
		$body .= '<tr><td>' . esc_html( memorial_plugin_t( $label ) ) . '</td>'
			. '<td class="nowrap">' . esc_html( $value ) . $unit . '</td>'
			. '<td class="mprice-list__more">' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html__( 'more', 'memorial-plugin' ) . ' <span aria-hidden="true">→</span></a>' : '' ) . '</td></tr>';
	}
	if ( '' === $body ) {
		return memorial_plugin_block_note( __( 'Prices one by one: no row has a price yet. Fill in the prices of the codes in Memorial → Prices → Separate prices.', 'memorial-plugin' ) );
	}
	$title = trim( (string) ( $a['title'] ?? '' ) );
	$note  = trim( (string) ( $a['note'] ?? '' ) );
	return '<section class="mprice-list">'
		. ( '' !== $title ? '<h2 class="wp-block-heading mprice-list__title">' . esc_html( memorial_plugin_t( $title ) ) . '</h2>' : '' )
		. '<figure class="wp-block-table is-style-price"><table><thead><tr><th scope="col">' . esc_html__( 'Price list item', 'memorial-plugin' ) . '</th><th scope="col">' . esc_html__( 'Price', 'memorial-plugin' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Page', 'memorial-plugin' ) . '</span></th></tr></thead>'
		. '<tbody>' . $body . '</tbody></table></figure>'
		. ( '' !== $note ? '<p class="mprice-list__note">' . esc_html( memorial_plugin_t( $note ) ) . '</p>' : '' )
		. '</section>';
}

/* ---------- Дата «Обновлено» у отдельных цен ---------- */

/**
 * When an own price changes (or a new price is added) and its date was not changed by hand, the date is today.
 *
 * @param mixed $value New value of mem_prices.
 * @param mixed $old   Old value.
 * @return mixed
 */
function memorial_plugin_prices_stamp( $value, $old ) {
	if ( ! is_array( $value ) || empty( $value['other']['items'] ) || ! is_array( $value['other']['items'] ) ) {
		return $value;
	}
	$before = array();
	foreach ( (array) ( $old['other']['items'] ?? array() ) as $row ) {
		$key = (string) ( $row['_id'] ?? '' );
		if ( '' === $key ) {
			$key = 'c:' . ( $row['code'] ?? '' );
		}
		$before[ $key ] = $row;
	}
	foreach ( $value['other']['items'] as &$row ) {
		if ( ! is_array( $row ) || ! in_array( $row['what'] ?? '', array( '', 'custom' ), true ) || '' === trim( (string) ( $row['price'] ?? '' ) ) ) {
			continue;
		}
		$was = $before[ (string) ( $row['_id'] ?? '' ) ] ?? ( $before[ 'c:' . ( $row['code'] ?? '' ) ] ?? null );
		if ( $was && trim( (string) ( $was['price'] ?? '' ) ) === trim( (string) $row['price'] ) ) {
			continue;
		}
		if ( $was && (string) ( $was['updated'] ?? '' ) !== (string) ( $row['updated'] ?? '' ) ) {
			continue; // The date was changed by hand together with the price.
		}
		$row['updated'] = wp_date( 'd.m.Y' );
	}
	unset( $row );
	return $value;
}
add_filter( 'pre_update_option_mem_prices', 'memorial_plugin_prices_stamp', 10, 2 );

/* ---------- Когда последний раз менялись цены ---------- */

/**
 * The prices part of mem_prices: packages, cremation, codes with their prices. Texts of payment and
 * guarantees are not prices — their edits do not move the date.
 *
 * @param mixed $value mem_prices.
 * @return string Hash.
 */
function memorial_plugin_prices_fingerprint( $value ) {
	$value = is_array( $value ) ? $value : array();
	$other = array();
	foreach ( (array) ( $value['other']['items'] ?? array() ) as $row ) {
		if ( is_array( $row ) ) {
			$other[] = array( (string) ( $row['code'] ?? '' ), (string) ( $row['price'] ?? '' ), (string) ( $row['what'] ?? '' ) );
		}
	}
	return md5(
		(string) wp_json_encode(
			array(
				(array) ( $value['packages']['items'] ?? array() ),
				(array) ( $value['cremation']['rows'] ?? array() ),
				$other,
			)
		)
	);
}

/**
 * Remember the moment the prices changed.
 */
function memorial_plugin_prices_touch() {
	update_option( 'mem_prices_changed', time(), false );
}

/**
 * “Memorial → Prices” saved: the date moves only if a price, a package or the cremation changed.
 *
 * @param mixed $old   Old value.
 * @param mixed $value New value.
 */
function memorial_plugin_prices_changed( $old, $value ) {
	if ( memorial_plugin_prices_fingerprint( $old ) !== memorial_plugin_prices_fingerprint( $value ) ) {
		memorial_plugin_prices_touch();
	}
}
add_action( 'update_option_mem_prices', 'memorial_plugin_prices_changed', 10, 2 );

/**
 * The price of a catalog product changed (the “from” prices of the sections are counted from them).
 *
 * @param int    $meta_id   Meta ID.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Key.
 */
function memorial_plugin_prices_product_changed( $meta_id, $object_id, $meta_key ) {
	if ( '_mem_price' === $meta_key && 'mem_product' === get_post_type( $object_id ) ) {
		memorial_plugin_prices_touch();
	}
}
add_action( 'updated_post_meta', 'memorial_plugin_prices_product_changed', 10, 3 );
add_action( 'added_post_meta', 'memorial_plugin_prices_product_changed', 10, 3 );

/**
 * When the prices were last changed. Before the first change after the update — the latest
 * “Updated” date of the separate prices.
 *
 * @return int Timestamp or 0.
 */
function memorial_plugin_prices_updated_at() {
	$ts = (int) get_option( 'mem_prices_changed', 0 );
	if ( $ts > 0 ) {
		return $ts;
	}
	foreach ( (array) ( memorial_plugin_prices( 'other' )['items'] ?? array() ) as $row ) {
		$d = DateTime::createFromFormat( '!d.m.Y', trim( (string) ( $row['updated'] ?? '' ) ), wp_timezone() );
		if ( $d ) {
			$ts = max( $ts, $d->getTimestamp() );
		}
	}
	return $ts;
}

/**
 * “November 2026” — the month and year the prices were last changed (lower case, as inside a sentence).
 *
 * @return string
 */
function memorial_plugin_prices_updated_text() {
	$ts = memorial_plugin_prices_updated_at();
	return $ts > 0 ? mb_strtolower( wp_date( 'F Y', $ts ) ) : '';
}

/* ---------- Разовый перенос memorial_plugin_prices_2 ---------- */

/**
 * Pages and the kind of work of their “Payment” block (owner, 24.11.2026): services and goods — funeral;
 * monuments, landscaping, fences — monuments. Prices and contacts stay for everything.
 *
 * @return array<string,string>
 */
function memorial_plugin_payment_contexts_2() {
	return array(
		'ritualnye-uslugi'               => 'funeral',
		'pomoshh-v-organizaczii-pohoron' => 'funeral',
		'ritualnye-uslugi/kremacziya'    => 'funeral',
		'ritualnye-uslugi/gruz-200'      => 'funeral',
		'ritualnye-tovary'               => 'funeral',
		'venki'                          => 'funeral',
		'groby'                          => 'funeral',
		'metallicheskie-kresty'          => 'funeral',
		'pamyatniki'                     => 'monuments',
		'blagoustrojstvo-zahoroneniya'   => 'monuments',
		'blagoustrojstvo-zahoroneniya/ukladka-plitki-na-kladbishhe-v-belgorode' => 'monuments',
		'ogradki'                        => 'monuments',
	);
}

/**
 * The run. $dry = true — only what would change, nothing is written.
 *
 * @param bool $dry Dry run.
 * @return array Log: prices (old option), posts => [id => old content], added codes, skipped pages.
 */
function memorial_plugin_prices_upgrade_2( $dry = false ) {
	global $wpdb;
	$log = array( 'time' => time() );

	// 1. Codes: the payment for participants of the special military operation and the rows of the price sets.
	$saved = get_option( 'mem_prices' );
	if ( is_array( $saved ) ) {
		$items = (array) ( $saved['other']['items'] ?? memorial_plugin_prices_defaults()['other']['items'] );
		$have  = array_map( static fn( $r ) => memorial_plugin_value_key( (string) ( $r['code'] ?? '' ) ), $items );
		$add   = array(
			array(
				'name'    => 'Выплата на погребение участника СВО (Белгородская область)',
				'what'    => 'custom',
				'price'   => '150 000',
				'code'    => 'vyplata_svo',
				'seo'     => 1,
				'updated' => wp_date( 'd.m.Y' ),
			),
		);
		foreach ( memorial_plugin_price_list_presets() as $preset ) {
			foreach ( $preset['rows'] as $row ) {
				$add[] = array(
					'name'  => $row['label'],
					'what'  => 'custom',
					'price' => '',
					'code'  => $row['code'],
				);
			}
		}
		foreach ( $add as $row ) {
			if ( in_array( $row['code'], $have, true ) || in_array( $row['code'], array( 'kremaciya' ), true ) ) {
				continue;
			}
			$have[]           = $row['code'];
			$items[]          = $row + array( '_id' => memorial_plugin_row_id() );
			$log['codes'][]   = $row['code'];
		}
		if ( ! empty( $log['codes'] ) ) {
			$log['prices'] = $saved;
			if ( ! $dry ) {
				$saved['other']['items'] = $items;
				// As is, not through the sanitizer of the screen (RUKOVODSTVO 8.4); the date stamp is not needed here.
				remove_filter( 'pre_update_option_mem_prices', 'memorial_plugin_prices_stamp', 10 );
				update_option( 'mem_prices', $saved );
				add_filter( 'pre_update_option_mem_prices', 'memorial_plugin_prices_stamp', 10, 2 );
			}
		}
	}

	// 2. “Payment” on the pages: the kind of work by the section of the site.
	foreach ( memorial_plugin_payment_contexts_2() as $path => $context ) {
		$page = get_page_by_path( $path );
		if ( ! $page ) {
			$log['skipped'][] = $path;
			continue;
		}
		$content = (string) $page->post_content;
		$new     = preg_replace_callback(
			'/<!--\s+wp:memorial-plugin\/payment(\s+(\{.*?\}))?\s+\/-->/s',
			static function ( $m ) use ( $context ) {
				$attrs = isset( $m[2] ) ? (array) json_decode( $m[2], true ) : array();
				if ( ! empty( $attrs['context'] ) ) {
					return $m[0];
				}
				$attrs['context'] = $context;
				return '<!-- wp:memorial-plugin/payment ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' /-->';
			},
			$content
		);
		if ( $new === $content ) {
			continue;
		}
		$log['posts'][ $page->ID ] = $content;
		if ( ! $dry ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $page->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time move, no revisions.
			clean_post_cache( (int) $page->ID );
		}
	}
	return $log;
}

add_action(
	'admin_init',
	static function () {
		if ( get_option( 'memorial_plugin_prices_2' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'memorial_plugin_prices_2', time(), false );
		update_option( 'memorial_plugin_prices_2_log', memorial_plugin_prices_upgrade_2(), false );
		if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
	},
	8
);
