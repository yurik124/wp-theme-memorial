<?php
/**
 * Страница «Отзывы»: шорткод [mem_reviews].
 * Источники отзывов (Яндекс Карты, 2ГИС, Google и любые другие, по филиалам) задаются в «Мемориал → Отзывы».
 * Виджеты сторонних сервисов загружаются после согласия на cookie или по кнопке.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Review services.
 */
function memorial_plugin_review_services() {
	return array(
		'yandex' => __( 'Yandex Maps', 'memorial-plugin' ),
		'2gis'   => __( '2GIS', 'memorial-plugin' ),
		'google' => __( 'Google Maps', 'memorial-plugin' ),
		'avito'  => __( 'Avito', 'memorial-plugin' ),
		'other'  => __( 'Another service', 'memorial-plugin' ),
	);
}

/**
 * Reviews schema.
 */
function memorial_plugin_reviews_schema() {
	return array(
		'sources' => array(
			'type'     => 'repeater',
			'label'    => __( 'Where people write reviews about us', 'memorial-plugin' ),
			'add'      => __( 'Add a service or a branch', 'memorial-plugin' ),
			'collapse' => 'branch',
			'open'     => true,
			'help'     => __( 'A row for every service and branch. The order is changed with the ↑ ↓ arrows to the right of a row. Write the rating and the number of reviews as in the service itself — visitors see them.', 'memorial-plugin' ),
			'fields'   => array(
				'service' => array(
					'type'    => 'select',
					'label'   => __( 'Service', 'memorial-plugin' ),
					'options' => memorial_plugin_review_services(),
				),
				'name'    => array(
					'type'  => 'text',
					'label' => __( 'Service name (only for “Another service”)', 'memorial-plugin' ),
				),
				'branch'  => array(
					'type'        => 'text',
					'label'       => __( 'Branch', 'memorial-plugin' ),
					'placeholder' => __( 'for example: 40 Studencheskaya St.', 'memorial-plugin' ),
				),
				'link'    => array(
					'type'        => 'text',
					'label'       => __( 'Link to your card in the service', 'memorial-plugin' ),
					'placeholder' => 'https://yandex.ru/maps/org/…/1234567890/',
					'help'        => __( 'For Yandex Maps the “Reviews” block shows the review feed by the link itself. For 2GIS and others — the “Read reviews” and “Leave a review” buttons.', 'memorial-plugin' ),
				),
				'rating'  => array(
					'type'        => 'text',
					'label'       => __( 'Rating (optional)', 'memorial-plugin' ),
					'placeholder' => '4,9',
				),
				'count'   => array(
					'type'        => 'text',
					'label'       => __( 'Number of reviews (optional)', 'memorial-plugin' ),
					'placeholder' => '120',
				),
				'embed'   => array(
					'type'  => 'code',
					'label' => __( 'Widget code (optional)', 'memorial-plugin' ),
					'rows'  => 4,
					'help'  => __( 'If the service or a third-party review aggregator has a code for websites — paste it here whole. It is shown on the tab of this branch.', 'memorial-plugin' ),
				),
			),
		),
		'texts'   => array(
			'type'   => 'group',
			'label'  => __( 'Texts on the page', 'memorial-plugin' ),
			'fields' => array(
				'lead' => array(
					'type'  => 'textarea',
					'label' => __( 'Text above the reviews', 'memorial-plugin' ),
					'rows'  => 2,
				),
				'ask'  => array(
					'type'  => 'textarea',
					'label' => __( 'Request to leave a review (under the reviews)', 'memorial-plugin' ),
					'rows'  => 2,
				),
			),
		),
	);
}

/**
 * Reviews defaults.
 */
function memorial_plugin_reviews_defaults() {
	$org  = function_exists( 'memorial_plugin_yandex_org' ) ? memorial_plugin_yandex_org() : '';
	$addr = function_exists( 'memorial_plugin_addrs' ) ? ( memorial_plugin_addrs()[0]['addr'] ?? '' ) : '';
	return array(
		'sources' => $org ? array(
			array(
				'service' => 'yandex',
				'name'    => '',
				'branch'  => $addr ? memorial_plugin_short_addr( $addr ) : '',
				'link'    => 'https://yandex.ru/maps/org/' . $org . '/',
				'rating'  => '',
				'count'   => '',
				'embed'   => '',
			),
		) : array(),
		'texts'   => array(
			'lead' => 'Мы не убираем и не правим отзывы — здесь они показываются прямо из сервисов, где их оставили.',
			'ask'  => 'Если мы помогли вашей семье, напишите пару слов о нашей работе — это поможет другим людям в трудную минуту.',
		),
	);
}

add_filter(
	'memorial_plugin_admin_pages',
	function ( $pages ) {
		$pages['mem_reviews'] = array(
			'slug'      => 'mem-reviews',
			'title'     => __( 'Reviews', 'memorial-plugin' ),
			'schema'    => 'memorial_plugin_reviews_schema',
			'intro_tab' => true,
			'intro'     => __( 'Review services for the “Reviews” page: on the page they are shown by the “Reviews” block. ', 'memorial-plugin' )
					. __( 'On the home page the reviews are blocks of the page content: edit them in Pages → Home.', 'memorial-plugin' ),
		);
		return $pages;
	}
);

/** Номер организации из ссылки Яндекс Карт
 *
 * @param mixed $link Link.
 */
function memorial_plugin_yandex_id( $link ) {
	return preg_match( '~/org/(?:[^/]+/)?(\d{6,})~', (string) $link, $m ) || preg_match( '~^\s*(\d{6,})\s*$~', (string) $link, $m ) ? $m[1] : '';
}

/** Ссылка «оставить отзыв» для сервиса
 *
 * @param array $s Section data.
 */
function memorial_plugin_review_add_link( $s ) {
	$link = trim( $s['link'] ?? '' );
	$id   = 'yandex' === $s['service'] ? memorial_plugin_yandex_id( $link ) : '';
	if ( $id ) {
		return 'https://yandex.ru/maps/org/' . $id . '/reviews/?add-review=true';
	}
	if ( '2gis' === $s['service'] && $link ) {
		return rtrim( preg_replace( '~/tab/.*$~', '', $link ), '/' ) . '/tab/reviews';
	}
	return $link;
}

/** Оценка из настроек («4,9») числом
 *
 * @param array $s Section data.
 */
function memorial_plugin_review_rating( $s ) {
	return (float) str_replace( ',', '.', preg_replace( '/[^\d,.]/', '', (string) ( $s['rating'] ?? '' ) ) ); }

/** Строка «4,9 ★★★★★ 120 отзывов» — звёзды закрашены по оценке
 *
 * @param array $s Section data.
 */
function memorial_plugin_review_rating_html( $s ) {
	$r = memorial_plugin_review_rating( $s );
	if ( $r <= 0 ) {
		return '';
	}
	$n = (int) preg_replace( '/\D/', '', (string) ( $s['count'] ?? '' ) );
	return '<span class="mrev__rate"><b>' . esc_html( number_format( min( 5, $r ), 1, ',', '' ) ) . '</b>' . ( function_exists( 'memorial_plugin_rewio_stars' ) ? memorial_plugin_rewio_stars( min( 5, $r ) ) : '' )
		. ( $n ? '<small>' . $n . ' ' . esc_html( _n( 'review', 'reviews', $n, 'memorial-plugin' ) ) . '</small>' : '' ) . '</span>';
}

add_shortcode(
	'mem_reviews',
	function () {
		$o    = memorial_plugin_settings( 'mem_reviews' );
		$src  = array_values( array_filter( $o['sources'] ?? array(), fn( $s ) => trim( $s['link'] ?? '' ) !== '' || trim( $s['embed'] ?? '' ) !== '' ) );
		$book = function_exists( 'memorial_plugin_bookreviews_html' ) ? memorial_plugin_bookreviews_html() : '';
		if ( ! $src ) {
			return $book ? $book : ( current_user_can( 'manage_options' ) ? ( '<p class="is-style-note">' . esc_html__( 'Reviews block: add the review services in “Memorial → Reviews”.', 'memorial-plugin' ) . '</p>' ) : '' );
		}
		$names = memorial_plugin_review_services();
		$label = fn( $s ) => 'other' === $s['service'] && '' !== trim( $s['name'] ?? '' ) ? trim( $s['name'] ) : ( $names[ $s['service'] ] ?? __( 'Reviews', 'memorial-plugin' ) );
		// заглушка до загрузки виджета — одинаковая у всех сервисов: значок, название, филиал, оценка звёздами.
		$ph = fn( $s ) => '<p class="mrev__phsvc">' . memorial_plugin_logo_html( $s['service'], $label( $s ) ) . '<span>' . esc_html( $label( $s ) ) . ( trim( $s['branch'] ?? '' ) !== '' ? ' · ' . esc_html( $s['branch'] ) : '' ) . '</span></p>'
		. memorial_plugin_review_rating_html( $s );

		ob_start();
		echo '<div class="mrev">';
		if ( trim( $o['texts']['lead'] ?? '' ) !== '' ) {
			echo '<p class="mrev__lead">' . esc_html( $o['texts']['lead'] ) . '</p>';
		}

		// Карточки сервисов.
		echo '<ul class="mrev__cards">';
		foreach ( $src as $s ) {
			$add = memorial_plugin_review_add_link( $s );
			echo '<li class="mrev__card mrev__card--' . esc_attr( $s['service'] ) . '">';
			echo '<span class="mrev__svc">' . memorial_plugin_logo_html( $s['service'], $label( $s ) ) . esc_html( $label( $s ) ) . '</span>';
			if ( trim( $s['branch'] ?? '' ) !== '' ) {
				echo '<span class="mrev__branch">' . esc_html( $s['branch'] ) . '</span>';
			}
			echo memorial_plugin_review_rating_html( $s );
			echo '<span class="mrev__btns">';
			if ( trim( $s['link'] ?? '' ) !== '' ) {
				echo '<a href="' . esc_url( $s['link'] ) . ( '" target="_blank" rel="noopener">' . esc_html__( 'Read reviews ↗', 'memorial-plugin' ) . '</a>' );
			}
			if ( $add && $add !== $s['link'] ) {
				echo '<a href="' . esc_url( $add ) . ( '" target="_blank" rel="noopener">' . esc_html__( 'Leave a review ↗', 'memorial-plugin' ) . '</a>' );
			}
			echo '</span></li>';
		}
		echo '</ul>';

		// Ленты отзывов: Яндекс — официальный виджет, остальные — код из настроек.
		$feeds = array();
		foreach ( $src as $s ) {
			$id = 'yandex' === $s['service'] ? memorial_plugin_yandex_id( $s['link'] ) : '';
			if ( $id || trim( $s['embed'] ?? '' ) !== '' ) {
				$feeds[] = $s + array( 'yid' => $id );
			}
		}
		if ( $feeds ) {
			$uid = 'mrev' . wp_rand( 100, 999 );
			echo '<div class="mrev__feeds" data-tabs data-wp-interactive="memorial-plugin/reviews" data-wp-init="callbacks.tabs">';
			if ( count( $feeds ) > 1 ) {
				echo ( '<div class="mrev__tabs" role="tablist" aria-label="' . esc_html__( 'Reviews by branch', 'memorial-plugin' ) . '">' );
				foreach ( $feeds as $n => $s ) {
					$t = $label( $s ) . ( trim( $s['branch'] ?? '' ) !== '' ? ' · ' . $s['branch'] : '' );
					echo '<button type="button" role="tab" class="maps__tab" id="' . esc_attr( $uid . 't' . $n ) . '" aria-controls="' . esc_attr( $uid . 'p' . $n ) . '" aria-selected="' . ( $n ? 'false' : 'true' ) . '" tabindex="' . ( $n ? '-1' : '0' ) . '">' . memorial_plugin_logo_html( $s['service'], $label( $s ) ) . esc_html( $t ) . '</button>';
				}
				echo '</div>';
			}
			foreach ( $feeds as $n => $s ) {
				echo '<div class="mrev__feed" id="' . esc_attr( $uid . 'p' . $n ) . '"' . ( count( $feeds ) > 1 ? ' role="tabpanel" aria-labelledby="' . esc_attr( $uid . 't' . $n ) . '"' : '' ) . ( $n ? ' hidden' : '' ) . '>';
				if ( $s['yid'] ) {
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- the placeholder and link are built with esc_html()/esc_url().
					echo '<div class="reviews__widget" data-reviews="' . esc_url( 'https://yandex.ru/maps-reviews-widget/' . $s['yid'] . '?comments' ) . '">'
						. '<div class="reviews__ph">' . $ph( $s ) . ( '<button class="btn btn--line" type="button" data-reviews-load>' . esc_html__( 'Show reviews', 'memorial-plugin' ) . '</button></div>' )
						. '<a class="reviews__credit" href="' . esc_url( 'https://yandex.ru/maps/org/' . $s['yid'] . '/' ) . '" target="_blank" rel="noopener">' . esc_html( get_bloginfo( 'name' ) ) . ( ' — ' . esc_html__( 'Yandex Maps', 'memorial-plugin' ) . '</a></div>' );
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					// код виджета: вставляется только после согласия на cookie или по кнопке.
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- widget code is saved whole only by a user with unfiltered_html (core/settings.php); the placeholder is escaped.
					echo '<div class="mrev__embed" data-embed-code data-wp-interactive="memorial-plugin/reviews" data-wp-init="callbacks.embed"><template>' . $s['embed'] . '</template>'
						. '<div class="reviews__ph">' . $ph( $s ) . ( '<button class="btn btn--line" type="button" data-embed-code-load>' . esc_html__( 'Show reviews', 'memorial-plugin' ) . '</button></div></div>' );
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo $book; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- reviews from the book, built with esc_html() (bookreviews.php); shown under the widgets.
		if ( trim( $o['texts']['ask'] ?? '' ) !== '' ) {
			echo '<p class="mrev__ask">' . esc_html( $o['texts']['ask'] ) . '</p>';
		}
		echo '</div>';
		return ob_get_clean();
	}
);

/* ---------- Один пункт меню «Отзывы» с вкладками ---------- */
/**
 * Reviews tabs.
 */
function memorial_plugin_reviews_tabs() {
	return array(
		'services' => array( __( 'Services and widgets', 'memorial-plugin' ), admin_url( 'admin.php?page=mem-reviews' ) ),
		'rewio'    => array( __( 'Rewio reviews', 'memorial-plugin' ), admin_url( 'admin.php?page=mem-rewio' ) ),
		'book'     => array( __( 'From the guest book', 'memorial-plugin' ), admin_url( 'admin.php?page=memorial-plugin-bookrev' ) ),
	);
}
add_action(
	'memorial_plugin_admin_tabs',
	function ( $opt ) {
		if ( 'mem_reviews' === $opt ) {
			memorial_plugin_admin_tabs( memorial_plugin_reviews_tabs(), 'services' );
		}
		if ( 'mem_rewio' === $opt ) {
			memorial_plugin_admin_tabs( memorial_plugin_reviews_tabs(), 'rewio' );
		}
	}
);
// список и правка отзывов из книги: те же вкладки, в меню подсвечен пункт «Отзывы».
add_action(
	'all_admin_notices',
	function () {
		$sc = get_current_screen();
		if ( $sc && in_array( $sc->id, array( 'edit-mem_bookrev', 'admin_page_memorial-plugin-bookrev' ), true ) ) {
			memorial_plugin_admin_tabs( memorial_plugin_reviews_tabs(), 'book' );
		}
	}
);
add_filter(
	'parent_file',
	function ( $f ) {
		$sc = get_current_screen();
		if ( $sc && ( 'mem_bookrev' === $sc->post_type || 'mem-rewio' === ( $_GET['page'] ?? '' ) ) ) {
			global $submenu_file;
			$submenu_file = 'mem-reviews'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- highlights the menu item, the documented way in wp-admin.
			return 'mem-contacts';
		}
		return $f;
	}
);
