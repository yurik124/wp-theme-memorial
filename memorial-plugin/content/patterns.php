<?php
/**
 * Готовые блоки для страниц («+» → «Паттерны» → «Мемориал») и подсказка по шорткодам:
 * «Страницы → Шорткоды и блоки» (и «Мемориал → Шорткоды и блоки»).
 *
 * Шорткод — то, что считается или берётся из админки само (каталог, цены, цифры, пакеты, отзывы).
 * Паттерн — готовая заготовка оформления, которую вставляют и дописывают своим текстом.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Все шорткоды темы: [код, что делает, пример(ы)] */


add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}
		if ( ! WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'memorial' ) ) {
			register_block_pattern_category( 'memorial', array( 'label' => __( 'Memorial: ready sections', 'memorial-plugin' ) ) );
		}
		$p    = fn( $t, $cls = '' ) => '<!-- wp:paragraph' . ( $cls ? ' {"className":"' . $cls . '"}' : '' ) . ' --><p' . ( $cls ? ' class="' . $cls . '"' : '' ) . '>' . $t . '</p><!-- /wp:paragraph -->';
		$h    = fn( $t ) => '<!-- wp:heading --><h2 class="wp-block-heading">' . $t . '</h2><!-- /wp:heading -->';
		$sc   = fn( $c ) => '<!-- wp:shortcode -->' . $c . '<!-- /wp:shortcode -->';
		$q    = fn( $question, $answer ) => '<!-- wp:details --><details class="wp-block-details"><summary>' . $question . '</summary>' . $p( $answer ) . '</details><!-- /wp:details -->';
		// A price of «Memorial → Prices → Separate prices» as a site value: the page shows the current price.
		$val = static fn( $code ) => function_exists( 'memorial_plugin_inline_value_span' ) ? memorial_plugin_inline_value_span( 'price', array( 'id' => $code ), esc_html( function_exists( 'memorial_plugin_price_value' ) ? (string) memorial_plugin_price_value( $code ) : '' ) ) : '';
		// A paragraph whose text is a field of the settings (blocks/bindings.php): edited on a page, changed everywhere.
		$bound = static fn( $key, $cls = '' ) => '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"memorial-plugin/settings","args":{"key":"' . $key . '"}}}}' . ( $cls ? ',"className":"' . $cls . '"' : '' ) . '} --><p' . ( $cls ? ' class="' . $cls . '"' : '' ) . '></p><!-- /wp:paragraph -->';
		$pats  = array(
			'contacts-shared' => array(
				__( 'Contacts: phone, address and hours (shared texts)', 'memorial-plugin' ),
				__( 'A heading and three lines from Memorial → Contacts. The lines can be edited right on the page — they change on every page and in the settings.', 'memorial-plugin' ),
				$h( __( 'How to reach us', 'memorial-plugin' ) ) . $bound( 'mem_contacts:phones.@first.num', 'has-large-font-size' ) . $bound( 'mem_contacts:addrs.@first.addr' ) . $bound( 'mem_contacts:addrs.@first.hours' ),
			),
			'night-shared' => array(
				__( 'Calls at night (shared text)', 'memorial-plugin' ),
				__( 'The line “Calls outside working hours” from Memorial → Contacts. It can be changed right on the page — the change goes to the settings and to every page that shows it.', 'memorial-plugin' ),
				$bound( 'mem_contacts:night' ),
			),
			'catalog'     => array(
				__( 'Catalog of the page with a summary', 'memorial-plugin' ),
				__( 'A title, the automatic summary “96 models, from 35,000 ₽” and the catalog.', 'memorial-plugin' ),
				$h( __( 'Catalog', 'memorial-plugin' ) ) . $p( 'В каталоге [mem_cat_count], цены [mem_cat_price]. Нажмите на фото, чтобы рассмотреть модель крупнее.' ) . $sc( '[mem_catalog]' ),
			),
			'summary'     => array(
				__( 'Catalog summary by section', 'memorial-plugin' ),
				__( 'A paragraph with the number of models and the list of sections with prices — everything is counted automatically.', 'memorial-plugin' ),
				$p( 'В каталоге [mem_cat_count], цены [mem_cat_price].' ) . $sc( '[mem_cat_list]' ),
			),
			'cta'         => array(
				__( 'Call to action', 'memorial-plugin' ),
				__( 'A block with the phone. The title and the text are changed in the block settings.', 'memorial-plugin' ),
				$sc( '[mem_cta title="Позвоните — подскажем, с чего начать" text="Звонки принимаем круглосуточно."]' ),
			),
			'handmade'    => array(
				__( 'We make it ourselves (photo + text)', 'memorial-plugin' ),
				__( 'A photo of the craftsman’s work and a couple of sentences — for wreaths, portraits on monuments, ribbon printing.', 'memorial-plugin' ),
				'<!-- wp:media-text {"mediaType":"image","className":"is-style-soft"} --><div class="wp-block-media-text is-stacked-on-mobile is-style-soft"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content">'
				. ( '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html__( 'We make it ourselves', 'memorial-plugin' ) . '</h3><!-- /wp:heading -->' )
				. $p( __( 'Tell in 2–3 sentences how and who does it, how long it takes. Choose a photo of the craftsman at work on the left.', 'memorial-plugin' ) )
				. '</div></div><!-- /wp:media-text -->',
			),
			'faq'         => array(
				__( 'Frequently asked questions', 'memorial-plugin' ),
				__( 'A title and three expandable questions. They get onto the “Questions and answers” page automatically.', 'memorial-plugin' ),
				$h( __( 'Frequently asked questions', 'memorial-plugin' ) ) . $q( __( 'Question?', 'memorial-plugin' ), __( 'Answer.', 'memorial-plugin' ) ) . $q( __( 'Question?', 'memorial-plugin' ), __( 'Answer.', 'memorial-plugin' ) ) . $q( __( 'Question?', 'memorial-plugin' ), __( 'Answer.', 'memorial-plugin' ) ),
			),
			'price'       => array(
				__( 'Price table', 'memorial-plugin' ),
				__( 'Two columns “Service — Price”; the prices are site values from Memorial → Prices, never digits. Prices and “₽” do not break, the table stays neat on a phone.', 'memorial-plugin' ),
				'<!-- wp:table {"className":"is-style-price"} --><figure class="wp-block-table is-style-price"><table class="has-fixed-layout"><thead><tr><th>Услуга или товар</th><th>Цена</th></tr></thead><tbody><tr><td>Надпись на ленту</td><td>' . $val( 'lenta' ) . '</td></tr><tr><td>Доставка венков по Белгороду</td><td>' . $val( 'dostavka' ) . '</td></tr></tbody></table></figure><!-- /wp:table -->'
					. $p( 'Цены — только «Значением сайта»: щёлкните в ячейке → на панели блока «Ещё ∨» → «Значение сайта» → «Цена из Мемориал → Цены». Цифрами цены не пишите — они разойдутся с прайсом. Итог фиксируется в договоре.', 'is-style-note' ),
			),
			'reviews'     => array(
				__( 'Reviews from the guest book (3 latest)', 'memorial-plugin' ),
				__( 'Three reviews from the guest book with the photo of the page.', 'memorial-plugin' ),
				$h( __( 'Reviews of our customers', 'memorial-plugin' ) ) . $sc( '[mem_bookreviews limit="3" title=""]' ) . $p( ( '<a href="/otzyvy/">' . esc_html__( 'All reviews →', 'memorial-plugin' ) . '</a>' ) ),
			),
			'works'       => array(
				__( 'Our works (3 examples)', 'memorial-plugin' ),
				__( 'Three works with photos, year and prices.', 'memorial-plugin' ),
				$h( __( 'Examples of our works', 'memorial-plugin' ) ) . $sc( '[mem_works limit="3" nav="0" sort="0"]' ),
			),
			'works-cards' => array(
				__( 'Examples of works as cards (random)', 'memorial-plugin' ),
				__( 'Cards “photo, title, size and price” from “Our works” — 6 random ones, different every time. A click leads to the work.', 'memorial-plugin' ),
				$h( __( 'Examples of our works', 'memorial-plugin' ) ) . $sc( '[mem_works vid="карточки" chislo="6" poryadok="случайно"]' ),
			),
		);
		// ТЗ «Блоки и доработки» 24.11.2026, разделы 10–11 (тексты — из карточки компании).
		$step  = static fn( $ico, $title, $text, $extra = '' ) => '<!-- wp:group {"className":"mem-step ' . $ico . '","layout":{"type":"default"}} --><div class="wp-block-group mem-step ' . $ico . '"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $title . '</h3><!-- /wp:heading -->' . $p( $text ) . $extra . '</div><!-- /wp:group -->';
		$card  = static fn( $title, $text ) => '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"className":"is-style-card","layout":{"type":"default"}} --><div class="wp-block-group is-style-card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $title . '</h3><!-- /wp:heading -->' . $p( $text ) . '</div><!-- /wp:group --></div><!-- /wp:column -->';
		$pats['what-to-do'] = array(
			__( 'What to do when a person has died', 'memorial-plugin' ),
			__( 'Four steps with icons: the call, the protocol and the morgue, the documents for the cemetery, the death certificate; the button “Step by step” and the phone button. The phone is the shared text of Memorial → Contacts.', 'memorial-plugin' ),
			$h( 'Что делать, если умер человек' )
				. '<!-- wp:group {"className":"mem-steps","layout":{"type":"default"}} --><div class="wp-block-group mem-steps">'
				. $step( 'ico-phone', 'Позвоните нам', 'Звонки принимаем круглосуточно — подскажем, что делать прямо сейчас.', $bound( 'mem_contacts:phones.@first.num' ) )
				. $step( 'ico-car', 'Протокол и морг', 'Нужен протокол скорой помощи или участкового. Мы доставим тело в морг областной больницы. Если причина смерти неясна, тело в Бюро СМЭ направляет Следственный комитет.' )
				. $step( 'ico-doc', 'Документы для кладбища', 'Документы для кладбища оформим сами — от вас паспорт и медицинское свидетельство о смерти.' )
				. $step( 'ico-candle', 'Свидетельство о смерти', 'Гербовое свидетельство о смерти вы получаете в ЗАГСе или через МФЦ.' )
				. '</div><!-- /wp:group -->'
				. '<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} --><div class="wp-block-group"><!-- wp:memorial-plugin/link-button {"text":"Подробно по шагам","url":"/chto-delat-esli-umer-chelovek/","variant":"light"} /--><!-- wp:memorial-plugin/phone-button /--></div><!-- /wp:group -->',
		);
		$pats['crematorium'] = array(
			__( 'Is there a crematorium in Belgorod', 'memorial-plugin' ),
			__( 'A heading and a note: there is no crematorium in Belgorod and the region; cremation in Voronezh or Tula, we take the body ourselves.', 'memorial-plugin' ),
			$h( 'Есть ли в Белгороде крематорий' )
				. $p( 'В Белгороде и области крематория нет. Кремация — в Воронеже, реже в Туле; везём сами, сопровождать могут до двух родственников; урна возвращается в тот же день.', 'is-style-note' ),
		);
		$pats['urn'] = array(
			__( 'The urn with the ashes: the options', 'memorial-plugin' ),
			__( 'Three cards: burial in a family grave, keeping with us (paid), handing over; and a note about the columbarium.', 'memorial-plugin' ),
			$h( 'Урна с прахом: что можно сделать' )
				. '<!-- wp:columns --><div class="wp-block-columns">'
				. $card( 'Захоронение в родственную могилу', 'Подзахороним урну в могилу родственников на кладбище.' )
				. $card( 'Хранение у нас', 'Урна может храниться у нас, пока вы решаете, где её похоронить. Хранение платное.' )
				. $card( 'Выдача на руки', 'Отдадим урну родственникам на руки.' )
				. '</div><!-- /wp:columns -->'
				. $p( 'Колумбарий на кладбище по ул. Попова есть, но свободных мест в нём нет.', 'is-style-note' ),
		);
		foreach ( $pats as $slug => [$title, $desc, $content] ) {
			register_block_pattern(
				'memorial/' . $slug,
				array(
					'title'       => $title,
					'description' => $desc,
					'categories'  => array( 'memorial' ),
					'content'     => function_exists( 'memorial_plugin_convert_content' ) ? memorial_plugin_convert_content(
						$content,
						false
					) : $content,
				)
			);
		}
	},
	20
);
