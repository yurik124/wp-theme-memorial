<?php
/**
 * Оформление текстов страниц (блочный редактор):
 * — стили блоков: выбираются справа в «Стили» у выделенного блока;
 * — блок-призыв [mem_cta] с телефоном и кнопкой «Перезвоните мне» (телефон берётся из настроек);
 * — раскрывающиеся вопросы (блок «Подробности») автоматически размечаются для поисковиков как FAQ.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/* Стили текста страниц (content.css) подключает тема — это оформление. */

add_action(
	'init',
	function () {
		$styles = array(
			'core/table'      => array( array( 'price', __( 'Price list', 'memorial-plugin' ) ) ),
			'core/list'       => array( array( 'steps', __( 'Steps', 'memorial-plugin' ) ), array( 'checks', __( 'Ticks', 'memorial-plugin' ) ) ),
			'core/paragraph'  => array( array( 'lead', __( 'Lead text', 'memorial-plugin' ) ), array( 'note', __( 'Note', 'memorial-plugin' ) ) ),
			'core/group'      => array( array( 'card', __( 'Card', 'memorial-plugin' ) ) ),
			'core/media-text' => array( array( 'soft', __( 'Soft card', 'memorial-plugin' ) ) ),
			'core/gallery'    => array( array( 'cards', __( 'Cards with captions', 'memorial-plugin' ) ) ),
		);
		foreach ( $styles as $block => $list ) {
			foreach ( $list as [$name, $label] ) {
				register_block_style(
					$block,
					array(
						'name'  => $name,
						'label' => $label,
					)
				);
			}
		}
	}
);

/* ---------- Блок-призыв ---------- */
add_shortcode(
	'mem_cta',
	function ( $atts ) {
		$a   = shortcode_atts(
			array(
				'title' => __( 'Call — we will tell you where to start', 'memorial-plugin' ),
				'text'  => __( 'We will answer your questions and help with the documents and the cost.', 'memorial-plugin' ),
			),
			$atts,
			'mem_cta'
		);
		$tel = memorial_plugin_tel();
		ob_start(); ?>
	<aside class="mem-cta">
		<div class="mem-cta__txt">
		<p class="mem-cta__title"><?php echo esc_html( $a['title'] ); ?></p>
		<?php
		if ( $a['text'] ) :
			?>
			<p class="mem-cta__sub"><?php echo esc_html( $a['text'] ); ?></p><?php endif; ?>
		</div>
		<div class="mem-cta__btns">
		<?php
		if ( $tel ) :
			?>
			<a class="btn btn--gold" href="tel:<?php echo esc_attr( $tel ); ?>"><?php echo memorial_plugin_icon( 'phone' ); ?><span class="nowrap"><?php echo esc_html( memorial_plugin_opt( 'phone' ) ); ?></span></a><?php endif; ?>
		<button class="btn btn--ghost" type="button" data-callback><?php esc_html_e( 'Call me back', 'memorial-plugin' ); ?></button>
		</div>
	</aside>
		<?php
		return ob_get_clean();
	}
);

/* Сколько лет компании (считается от года основания в настройках): [mem_years] */
add_shortcode( 'mem_years', fn() => (string) memorial_plugin_years() );

/* Основной телефон в тексте: [mem_phone] */
add_shortcode(
	'mem_phone',
	function () {
		$tel = memorial_plugin_tel();
		return $tel ? '<a class="nowrap" href="tel:' . esc_attr( $tel ) . '">' . esc_html( memorial_plugin_opt( 'phone' ) ) . '</a>' : '';
	}
);

/* ---------- Частые вопросы → разметка FAQPage ---------- */
/**
 * Collect faq.
 *
 * @param mixed $blocks Blocks.
 * @param mixed $out Out.
 */
function memorial_plugin_collect_faq( $blocks, &$out ) {
	foreach ( $blocks as $b ) {
		if ( 'core/details' === $b['blockName'] && preg_match( '~<summary[^>]*>(.*?)</summary>~s', $b['innerHTML'], $m ) ) {
			$q   = trim( wp_strip_all_tags( $m[1] ) );
			$ans = '';
			foreach ( $b['innerBlocks'] as $ib ) {
				$ans .= render_block( $ib );
			}
			$ans = do_shortcode( $ans ); // цены из шорткодов — числами, а не «[mem_price …]».
			$ans = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $ans ) ) );
			if ( $q && $ans ) {
				$out[] = array(
					'@type'          => 'Question',
					'name'           => $q,
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $ans,
					),
				);
			}
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			memorial_plugin_collect_faq( $b['innerBlocks'], $out );
		}
	}
}

add_action(
	'wp_head',
	function () {
		if ( ! is_singular() || is_front_page() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post || strpos( $post->post_content, 'wp:details' ) === false ) {
			return;
		}
		$faq = array();
		memorial_plugin_collect_faq( parse_blocks( $post->post_content ), $faq );
		if ( count( $faq ) >= 2 ) {
			memorial_plugin_ld(
				array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => $faq,
				)
			);
		}
	},
	7
);

/* Готовый узор «Шаги с иконками»: «+» → «Паттерны» → «Мемориал» */
add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}
		register_block_pattern_category( 'memorial', array( 'label' => __( 'Memorial: ready sections', 'memorial-plugin' ) ) );
		$step = fn( $ico, $t, $p ) => '<!-- wp:group {"className":"mem-step ' . $ico . '","layout":{"type":"default"}} --><div class="wp-block-group mem-step ' . $ico . '">'
		. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $t . '</h3><!-- /wp:heading -->'
		. '<!-- wp:paragraph --><p>' . $p . '</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
		register_block_pattern(
			'memorial/steps',
			array(
				'title'       => __( 'Steps with icons', 'memorial-plugin' ),
				'categories'  => array( 'memorial' ),
				'description' => __( 'Steps in order. The icon of a step is set in “Additional CSS class(es)” of the step’s group: ico-phone, ico-doc, ico-car and so on.', 'memorial-plugin' ),
				'content'     => '<!-- wp:group {"className":"mem-steps","layout":{"type":"default"}} --><div class="wp-block-group mem-steps">'
					. $step( 'ico-phone', __( 'First step', 'memorial-plugin' ), __( 'What to do.', 'memorial-plugin' ) ) . $step( 'ico-doc', __( 'Second step', 'memorial-plugin' ), __( 'What to do.', 'memorial-plugin' ) ) . $step( 'ico-check', __( 'Third step', 'memorial-plugin' ), __( 'What to do.', 'memorial-plugin' ) )
					. '</div><!-- /wp:group -->',
			)
		);
	}
);

/**
 * Таблицы: чтобы на узком экране не отрывались «₽», разряды чисел и короткие предлоги —
 * тема сама ставит неразрывные пробелы, что бы ни ввели в редакторе.
 * Короткие ячейки (цены, «по каталогу») помечаются классом mem-nw — они не переносятся.
 *
 * @param string $html Html.
 */
function memorial_plugin_table_typo( $html ) {
	$fix = function ( $text ) {
		$t = preg_replace( '/(\d)[ \x{00A0}\x{202F}](?=\d{3}(?!\d))/u', "$1\u{202F}", $text );   // 35 000 → разряды вместе
		$t = preg_replace( '/(\d)[ \x{00A0}]?(₽|руб\.?|р\.)/u', "$1\u{00A0}$2", $t );            // 500 ₽
		$t = preg_replace( '/(^|[\s(«])(от|до|по|за|на|в|с|к|и|из|для)[ ](?=\S)/u', "$1$2\u{00A0}", $t ); // «по каталогу», «от 800»
		$t = preg_replace( '/(\d)\s*([–—-])\s*(\d)/u', '$1$2$3', $t );                                  // 200–300
		return $t;
	};
	return preg_replace_callback(
		'~<(td|th)(\s[^>]*)?>(.*?)</\1>~su',
		function ( $m ) use ( $fix ) {
			$inner = preg_replace_callback( '~(^|>)([^<]+)~u', fn( $x ) => $x[1] . $fix( $x[2] ), $m[3] );
			$plain = trim( html_entity_decode( wp_strip_all_tags( $inner ), ENT_QUOTES, 'UTF-8' ) );
			$attrs = $m[2] ?? '';
			$len   = mb_strlen( $plain );
			// ≤ 18 символов — не переносятся нигде; до 40 — не переносятся на ПК, где места хватает
			$cls = '' === $plain ? '' : ( $len <= 18 ? 'mem-nw' : ( $len <= 40 ? 'mem-nw-d' : '' ) );
			if ( $cls ) {
				$attrs = preg_match( '/class="/', $attrs ) ? preg_replace( '/class="/', 'class="' . $cls . ' ', $attrs, 1 ) : $attrs . ' class="' . $cls . '"';
			}
			return '<' . $m[1] . $attrs . '>' . $inner . '</' . $m[1] . '>';
		},
		$html
	);
}
add_filter( 'render_block_core/table', fn( $html ) => is_admin() ? $html : memorial_plugin_table_stack( memorial_plugin_table_typo( $html ) ) );

/**
 * Таблицы из 3+ колонок на телефоне превращаются в карточки: каждая строка — карточка,
 * у каждого значения подпись из шапки таблицы («Ширина × высота: 700 × 2050 мм»).
 *
 * @param string $html Html.
 */
function memorial_plugin_table_stack( $html ) {
	if ( ! preg_match( '~<thead>(.*?)</thead>~su', $html, $th ) ) {
		return $html;
	}
	preg_match_all( '~<th(?:\s[^>]*)?>(.*?)</th>~su', $th[1], $heads );
	$labels = array_map( fn( $h ) => trim( html_entity_decode( wp_strip_all_tags( $h ), ENT_QUOTES, 'UTF-8' ) ), $heads[1] );
	if ( count( $labels ) < 3 ) {
		return $html;
	}
	$html = preg_replace_callback(
		'~<tbody>(.*?)</tbody>~su',
		function ( $tb ) use ( $labels ) {
			return '<tbody>' . preg_replace_callback(
				'~<tr([^>]*)>(.*?)</tr>~su',
				function ( $tr ) use ( $labels ) {
					$i     = 0;
					$cells = preg_replace_callback(
						'~<td(\s[^>]*)?>~u',
						function ( $td ) use ( $labels, &$i ) {
							$l = $labels[ $i++ ] ?? '';
							return '' === $l ? $td[0] : '<td' . ( $td[1] ?? '' ) . ' data-label="' . esc_attr( $l ) . '">';
						},
						$tr[2]
					);
					return '<tr' . $tr[1] . '>' . $cells . '</tr>';
				},
				$tb[1]
			) . '</tbody>';
		},
		$html
	);
	return preg_replace( '/<figure class="wp-block-table/', '<figure class="mem-stack wp-block-table', $html, 1 );
}

/** Текст → латиница для якорей: «Каталог памятников» → katalog-pamyatnikov
 *
 * @param string $text Text.
 */
function memorial_plugin_slug( $text ) {
	$map = array(
		'а' => 'a',
		'б' => 'b',
		'в' => 'v',
		'г' => 'g',
		'д' => 'd',
		'е' => 'e',
		'ё' => 'e',
		'ж' => 'zh',
		'з' => 'z',
		'и' => 'i',
		'й' => 'y',
		'к' => 'k',
		'л' => 'l',
		'м' => 'm',
		'н' => 'n',
		'о' => 'o',
		'п' => 'p',
		'р' => 'r',
		'с' => 's',
		'т' => 't',
		'у' => 'u',
		'ф' => 'f',
		'х' => 'h',
		'ц' => 'c',
		'ч' => 'ch',
		'ш' => 'sh',
		'щ' => 'sch',
		'ъ' => '',
		'ы' => 'y',
		'ь' => '',
		'э' => 'e',
		'ю' => 'yu',
		'я' => 'ya',
	);
	$t   = strtr( mb_strtolower( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ), $map );
	return trim( preg_replace( '/[^a-z0-9]+/', '-', $t ), '-' );
}

/*
Заголовки разделов в тексте страниц получают якорь по своему тексту, если его не задали вручную:
	«Каталог памятников» → /pamyatniki/#katalog-pamyatnikov. Так на раздел можно сослаться с другой страницы.
 */
add_filter(
	'render_block_core/heading',
	function ( $html, $block ) {
		if ( is_admin() || ! preg_match( '~^(\s*<h([23]))(\b[^>]*)>(.*?)</h\2>~su', $html, $m ) || preg_match( '/\sid="/', $m[3] ) ) {
			return $html;
		}
		$slug = memorial_plugin_slug( $m[4] );
		if ( '' === $slug || is_numeric( $slug ) ) {
			return $html;
		}
		return preg_replace( '~^(\s*<h[23])~', '$1 id="' . esc_attr( substr( $slug, 0, 70 ) ) . '"', $html, 1 );
	},
	10,
	2
);
