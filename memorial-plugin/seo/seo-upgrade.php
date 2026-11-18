<?php
/**
 * Once (17.11.2026, decisions of the owner): addresses and SEO data of the pages before the move to memorialbel.ru.
 *
 *  1. The “Fences” page gets back the address of the old site: /ogradki-2/ → /ogradki/
 *     (the old address leads to the new one, seo/redirects.php).
 *  2. Links in texts, navigation and templates: /ogradki-2/ → /ogradki/, the broken /posobie-na-pogrebenie/ →
 *     /poleznoe/posobie-na-pogrebenie/.
 *  3. The Cookie policy is not indexed (SEOPress “noindex” of the page).
 *  4. Prices for SEO titles: “from” prices of the catalog sections and the burial allowance get
 *     “In the SEO plugin” in «Memorial → Prices → Separate prices» (%%mem_<code>%% in SEOPress).
 *  5. SEO titles of the pages in SEOPress (the queries of Yandex Wordstat for Belgorod).
 *
 * The flag memorial_plugin_seo_1 is set before the run, so it never repeats. What was before is in the log
 * memorial_plugin_seo_1_log: the slug, the texts of the changed posts, the meta fields, the prices option.
 * Texts are written directly (no revisions, no save filters). Then the page cache is cleared.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO titles: page path => title. {price:section-slug} — the “from” price of that catalog section when it has one
 * (“ — %%mem_<code>%%”, a variable of SEOPress), {value:code} — the same for a separate price;
 * the part in [ ] is used instead when there is no price. {always:section-slug} — the variable even without a price
 * today: it stays empty on the site until the products get prices (owner, 17.11.2026: “Coffins”).
 *
 * @return array<string,string>
 */
function memorial_plugin_seo_titles_1() {
	$t = ' %%sep%% %%sitetitle%%';
	return array(
		''                                       => 'Ритуальные услуги в Белгороде круглосуточно — «Мемориал»',
		'ritualnye-uslugi'                       => 'Ритуальные услуги в Белгороде: цены и состав' . $t,
		'ritualnye-uslugi/kremacziya'            => 'Кремация в Белгороде: цена и оформление' . $t,
		'ritualnye-uslugi/gruz-200'              => 'Перевозка умерших (груз 200) из Белгорода по России и СНГ' . $t,
		'ritualnye-uslugi/traurnyj-zal'          => 'Траурный зал в Белгороде: прощание и отпевание' . $t,
		'ritualnye-uslugi/pominalnyj-obed'       => 'Поминальный обед в Белгороде' . $t,
		'ritualnye-uslugi/pohorony-uchastnikov-svo' => 'Похороны участников СВО в Белгороде' . $t,
		'ritualnye-uslugi/prizhiznennyj-dogovor' => 'Прижизненный договор на похороны в Белгороде' . $t,
		'pomoshh-v-organizaczii-pohoron'         => 'Организация похорон в Белгороде под ключ' . $t,
		'ritualnye-tovary'                       => 'Ритуальные товары в Белгороде: венки, гробы, кресты' . $t,
		'venki'                                  => 'Ритуальные венки и корзины в Белгороде{price:venki}[: цены]' . $t,
		'groby'                                  => 'Гробы в Белгороде{always:groby}' . $t,
		'pamyatniki'                             => 'Памятники на могилу в Белгороде: изготовление{price:pamyatniki}[ и цены]' . $t,
		'ogradki'                                => 'Оградки на кладбище в Белгороде{price:ogradki-2}[: цены]' . $t,
		'metallicheskie-kresty'                  => 'Металлические кресты на могилу в Белгороде{price:metallicheskie-kresty}[: цены]' . $t,
		'blagoustrojstvo-zahoroneniya'           => 'Благоустройство могил в Белгороде под ключ' . $t,
		'blagoustrojstvo-zahoroneniya/ukladka-plitki-na-kladbishhe-v-belgorode' => 'Укладка плитки на могиле и установка памятника в Белгороде' . $t,
		'fotogalereia'                           => 'Фото: здание, траурный зал, транспорт и наши работы' . $t,
		'kontakty'                               => 'Контакты ритуальной службы «Мемориал» в Белгороде: адреса и телефоны',
		'ceny'                                   => 'Цены на ритуальные услуги и товары в Белгороде' . $t,
		'otzyvy'                                 => 'Отзывы о ритуальной службе «Мемориал» в Белгороде',
		'chto-delat-esli-umer-chelovek'          => 'Что делать, если умер человек: порядок действий' . $t,
		'poleznoe'                               => 'Полезно знать: похороны, документы, выплаты' . $t,
		'poleznoe/vopros-otvet'                  => 'Вопросы и ответы о похоронах' . $t,
		'poleznoe/posobie-na-pogrebenie'         => 'Пособие на погребение{value:posobie}[ в Белгороде]: как получить' . $t,
		'poleznoe/kladbishha'                    => 'Кладбища Белгорода и района: адреса и места' . $t,
	);
}

/**
 * Code of a separate price for a catalog section: the existing “from” row (with ₽), otherwise a new one.
 * Marks it “In the SEO plugin”.
 *
 * @param array  $items Rows of separate prices (changed in place).
 * @param string $slug  Section slug.
 * @param string $code  Code for a new row.
 * @return string Code, '' — no such section.
 */
function memorial_plugin_seo_1_price_code( array &$items, $slug, $code ) {
	$term = get_term_by( 'slug', $slug, 'mem_cat' );
	if ( ! $term ) {
		return '';
	}
	foreach ( $items as $i => $r ) {
		if ( is_array( $r ) && 'cat-min' === ( $r['what'] ?? '' ) && ( $r['section'] ?? '' ) === $slug && empty( $r['bare'] ) && '' !== trim( (string) ( $r['code'] ?? '' ) ) ) {
			$items[ $i ]['seo'] = 1;
			return memorial_plugin_value_key( (string) $r['code'] );
		}
	}
	$codes = array_map( static fn( $r ) => is_array( $r ) ? memorial_plugin_value_key( (string) ( $r['code'] ?? '' ) ) : '', $items );
	$base  = $code;
	for ( $n = 2; in_array( $code, $codes, true ); $n++ ) {
		$code = $base . $n;
	}
	$items[] = array(
		/* translators: %s: catalog section. */
		'name'    => sprintf( __( '%s — lowest price', 'memorial-plugin' ), $term->name ),
		'what'    => 'cat-min',
		'section' => $slug,
		'bare'    => 0,
		'seo'     => 1,
		'code'    => $code,
		'_id'     => memorial_plugin_row_id(),
	);
	return $code;
}

/**
 * Hosts of the site in links: the current one and the old domain.
 *
 * @return string Part of a regular expression.
 */
function memorial_plugin_seo_1_hosts() {
	$hosts = array( (string) wp_parse_url( home_url(), PHP_URL_HOST ), 'memorialbel.ru', 'www.memorialbel.ru' );
	return implode( '|', array_map( static fn( $h ) => preg_quote( $h, '~' ), array_unique( array_filter( $hosts ) ) ) );
}

/**
 * Links in a text: old path => new path, only links of this site (after the host or a quote, bracket).
 *
 * @param string $text Text.
 * @param array  $map  Old path => new path.
 * @return string
 */
function memorial_plugin_seo_1_links( $text, array $map ) {
	foreach ( $map as $old => $new ) {
		$text = (string) preg_replace( '~((?:https?:)?//(?:' . memorial_plugin_seo_1_hosts() . ')(?::\d+)?|["\'(])' . preg_quote( $old, '~' ) . '~u', '$1' . $new, $text );
	}
	return $text;
}

/**
 * The run. $dry = true — only what would change (tests and checks), nothing is written.
 *
 * @param bool $dry Dry run.
 * @return array Log.
 */
function memorial_plugin_seo_upgrade_1( $dry = false ) {
	global $wpdb;
	$log = array( 'time' => time() );

	// 1. /ogradki-2/ → /ogradki/: only when no page or attachment at the top level holds the name.
	$fences = get_page_by_path( 'ogradki-2' );
	$taken  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = 'ogradki' AND post_parent = 0 AND post_type IN ( 'page', 'attachment' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time move.
	if ( $fences && ! $fences->post_parent && ! $taken ) {
		$log['slug'] = array( $fences->ID, 'ogradki-2', 'ogradki' );
		if ( ! $dry ) {
			$wpdb->update( $wpdb->posts, array( 'post_name' => 'ogradki' ), array( 'ID' => $fences->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time move, no save filters.
			clean_post_cache( $fences->ID );
		}
	}

	// 2. Links.
	$map = array();
	if ( ! empty( $log['slug'] ) || get_page_by_path( 'ogradki' ) ) {
		$map['/ogradki-2/'] = '/ogradki/';
	}
	if ( get_page_by_path( 'poleznoe/posobie-na-pogrebenie' ) && ! get_page_by_path( 'posobie-na-pogrebenie' ) ) {
		$map['/posobie-na-pogrebenie/'] = '/poleznoe/posobie-na-pogrebenie/';
	}
	if ( $map ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time move.
		$rows = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status IN ( 'publish', 'draft', 'private', 'future', 'pending' ) AND post_type IN ( 'page', 'post', 'mem_product', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_block' ) AND ( post_content LIKE '%/ogradki-2/%' OR post_content LIKE '%/posobie-na-pogrebenie/%' )" );
		foreach ( $rows as $r ) {
			$new = memorial_plugin_seo_1_links( (string) $r->post_content, $map );
			if ( $new === $r->post_content ) {
				continue;
			}
			$log['posts'][ $r->ID ] = $r->post_content;
			if ( ! $dry ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $r->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time move, no revisions.
				clean_post_cache( (int) $r->ID );
			}
		}
	}

	// 3. Cookie policy — noindex.
	$cookies = (int) ( ( get_option( 'mem_legal' )['pages']['cookies'] ?? 0 ) );
	$cookies = $cookies ? $cookies : (int) ( get_page_by_path( 'politika-cookie' )->ID ?? 0 );
	if ( $cookies && 'yes' !== get_post_meta( $cookies, '_seopress_robots_index', true ) ) {
		$log['meta'][ $cookies ]['_seopress_robots_index'] = get_post_meta( $cookies, '_seopress_robots_index', true );
		if ( ! $dry ) {
			update_post_meta( $cookies, '_seopress_robots_index', 'yes' );
		}
	}

	// 4. Prices for titles.
	$opt   = get_option( 'mem_prices' );
	$opt   = is_array( $opt ) ? $opt : array();
	$items = (array) ( $opt['other']['items'] ?? ( function_exists( 'memorial_plugin_prices' ) ? memorial_plugin_prices( 'other' )['items'] ?? array() : array() ) );
	$codes = array(
		'venki'                 => 'venki_ot',
		'groby'                 => 'groby_ot',
		'pamyatniki'            => 'pamyatniki_ot',
		'ogradki-2'             => 'ogradki_ot',
		'metallicheskie-kresty' => 'kresty_ot',
	);
	$price = array();
	foreach ( $codes as $slug => $code ) {
		$price[ $slug ] = memorial_plugin_seo_1_price_code( $items, $slug, $code );
	}
	$value = array();
	foreach ( $items as $i => $r ) {
		if ( is_array( $r ) && 'posobie' === memorial_plugin_value_key( (string) ( $r['code'] ?? '' ) ) ) {
			$items[ $i ]['seo'] = 1;
			$value['posobie']   = 'posobie';
		}
	}
	$opt['other']          = (array) ( $opt['other'] ?? array() );
	$opt['other']['items'] = array_values( $items );
	if ( get_option( 'mem_prices' ) !== $opt ) {
		$log['mem_prices'] = get_option( 'mem_prices' );
		if ( ! $dry ) {
			update_option( 'mem_prices', $opt ); // Before register_setting (admin_init 10): saved as it is, not through the screen sanitizer.
		}
	}

	// 5. Titles.
	foreach ( memorial_plugin_seo_titles_1() as $path => $title ) {
		$id = '' === $path ? (int) get_option( 'page_on_front' ) : (int) ( get_page_by_path( $path )->ID ?? 0 );
		if ( ! $id && 'ogradki' === $path && ! empty( $log['slug'] ) ) {
			$id = (int) $log['slug'][0]; // Dry run: the page still has the old address.
		}
		if ( ! $id ) {
			continue;
		}
		$title = (string) preg_replace_callback(
			'~\{always:([a-z0-9_-]+)\}~u',
			static fn( $m ) => '' !== ( $price[ $m[1] ] ?? '' ) ? ' — ' . memorial_plugin_seo_var( $price[ $m[1] ] ) : '',
			$title
		);
		$title = (string) preg_replace_callback(
			'~\{(price|value):([a-z0-9_-]+)\}\[([^\]]*)\]~u',
			static function ( $m ) use ( $price, $value, $items ) {
				$code = 'price' === $m[1] ? ( $price[ $m[2] ] ?? '' ) : ( $value[ $m[2] ] ?? '' );
				$row  = null;
				foreach ( $items as $r ) {
					if ( is_array( $r ) && '' !== $code && memorial_plugin_value_key( (string) ( $r['code'] ?? '' ) ) === $code ) {
						$row = $r;
					}
				}
				$text = $row && function_exists( 'memorial_plugin_value_text' ) ? (string) memorial_plugin_value_text( $row ) : '';
				// A price is shown only when the section has one (“from 1 000 ₽”), not “on request”.
				if ( '' === $code || '' === $text || ! preg_match( '/\d/u', $text ) ) {
					return $m[3];
				}
				return ' — ' . memorial_plugin_seo_var( $code );
			},
			$title
		);
		$title = trim( (string) preg_replace( '/\s{2,}/u', ' ', $title ) );
		$old   = (string) get_post_meta( $id, '_seopress_titles_title', true );
		if ( $old === $title ) {
			continue;
		}
		$log['titles'][ $id ] = array( $old, $title );
		if ( ! $dry ) {
			update_post_meta( $id, '_seopress_titles_title', $title );
		}
	}
	return $log;
}

add_action(
	'admin_init',
	static function () {
		if ( get_option( 'memorial_plugin_seo_1' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() || ! taxonomy_exists( 'mem_cat' ) ) {
			return;
		}
		update_option( 'memorial_plugin_seo_1', time(), false );
		update_option( 'memorial_plugin_seo_1_log', memorial_plugin_seo_upgrade_1(), false );
		if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
	},
	7
);

/**
 * Once (18.11.2026): the home page and “Funeral services” do not compete for the same query. By the Yandex results for
 * Belgorod, “ритуальные услуги / похоронное бюро / ритуальное агентство / организация похорон + Белгород” is one
 * cluster where the home pages of the companies rank — it is the home page’s; the price queries go to “Prices”;
 * “Funeral services” is the list of services, “Help with the funeral” — how it goes, step by step.
 * Only a title or description that is empty or still the one of the first move is changed — never the owner’s own.
 *
 * @return array<string,array{0:string,1:string,2:string[]}> path ('' — home) => [title, description, old titles that may be replaced]
 */
function memorial_plugin_seo_titles_2() {
	$t = ' %%sep%% %%sitetitle%%';
	return array(
		''                               => array(
			'Ритуальные услуги и организация похорон в Белгороде — «Мемориал»',
			'Ритуальное агентство «Мемориал» в Белгороде: организация похорон под ключ, кремация, свой траурный зал, перевозка умерших. Цены на сайте, звонки круглосуточно.',
			array( 'Ритуальные услуги в Белгороде круглосуточно — «Мемориал»' ),
		),
		'ritualnye-uslugi'               => array(
			'Перечень ритуальных услуг «Мемориала»: похороны, кремация, траурный зал' . $t,
			'Какие ритуальные услуги оказывает «Мемориал» в Белгороде: организация похорон, кремация, прощание в траурном зале, перевозка умерших, поминальный обед. Состав и цены.',
			array( 'Ритуальные услуги в Белгороде: цены и состав' . $t, 'Ритуальные услуги | %%sitetitle%%' ),
		),
		'pomoshh-v-organizaczii-pohoron' => array(
			'Как мы организуем похороны: что входит и как всё проходит' . $t,
			'Что делает «Мемориал», когда вы обращаетесь за помощью: выезд агента, документы, морг, прощание и захоронение — по шагам. Звонки принимаем круглосуточно.',
			array( 'Организация похорон в Белгороде под ключ' . $t ),
		),
		'ceny'                           => array(
			'Сколько стоят похороны в Белгороде: цены на ритуальные услуги' . $t,
			'Цены на похороны и ритуальные услуги в Белгороде: готовые пакеты с полным составом, кремация, отдельные услуги и товары. Цены обновляются вместе с каталогом.',
			array( 'Цены на ритуальные услуги и товары в Белгороде' . $t ),
		),
	);
}

/**
 * The run of memorial_plugin_seo_titles_2(). $dry = true — only what would change.
 *
 * @param bool $dry Dry run.
 * @return array Log: [post id => [field => old value]].
 */
function memorial_plugin_seo_upgrade_2( $dry = false ) {
	$log = array( 'time' => time() );
	foreach ( memorial_plugin_seo_titles_2() as $path => [ $title, $desc, $old ] ) {
		$id = '' === $path ? (int) get_option( 'page_on_front' ) : (int) ( get_page_by_path( $path )->ID ?? 0 );
		if ( ! $id ) {
			continue;
		}
		$now = (string) get_post_meta( $id, '_seopress_titles_title', true );
		if ( '' === $now || in_array( $now, $old, true ) ) {
			$log['posts'][ $id ]['_seopress_titles_title'] = $now;
			if ( ! $dry ) {
				update_post_meta( $id, '_seopress_titles_title', $title );
			}
		}
		// The description: empty, or the old short one of the first site (“Контакты - …”-like texts are not touched).
		$d = (string) get_post_meta( $id, '_seopress_titles_desc', true );
		if ( '' === $d || ( 'ritualnye-uslugi' === $path && false !== strpos( $d, 'весь комплекс услуг' ) ) ) {
			$log['posts'][ $id ]['_seopress_titles_desc'] = $d;
			if ( ! $dry ) {
				update_post_meta( $id, '_seopress_titles_desc', $desc );
			}
		}
	}
	return $log;
}

add_action(
	'admin_init',
	static function () {
		if ( get_option( 'memorial_plugin_seo_2' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() || ! get_option( 'memorial_plugin_seo_1' ) ) {
			return;
		}
		update_option( 'memorial_plugin_seo_2', time(), false );
		update_option( 'memorial_plugin_seo_2_log', memorial_plugin_seo_upgrade_2(), false );
		if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
	},
	9
);
