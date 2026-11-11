<?php
/**
 * Готовые блоки для страниц («+» → «Паттерны» → «Мемориал») и подсказка по шорткодам:
 * «Страницы → Шорткоды и блоки» (и «Мемориал → Шорткоды и блоки»).
 *
 * Шорткод — то, что считается или берётся из админки само (каталог, цены, цифры, пакеты, отзывы).
 * Паттерн — готовая заготовка оформления, которую вставляют и дописывают своим текстом.
 */
defined('ABSPATH') || exit;

/** Все шорткоды темы: [код, что делает, пример(ы)] */


add_action('init', function () {
    if (!function_exists('register_block_pattern')) return;
    if (!WP_Block_Pattern_Categories_Registry::get_instance()->is_registered('memorial')) {
        register_block_pattern_category('memorial', ['label' => __( 'Memorial', 'memorial-plugin' )]);
    }
    $p = fn($t, $cls = '') => '<!-- wp:paragraph' . ($cls ? ' {"className":"' . $cls . '"}' : '') . ' --><p' . ($cls ? ' class="' . $cls . '"' : '') . '>' . $t . '</p><!-- /wp:paragraph -->';
    $h = fn($t) => '<!-- wp:heading --><h2 class="wp-block-heading">' . $t . '</h2><!-- /wp:heading -->';
    $sc = fn($c) => '<!-- wp:shortcode -->' . $c . '<!-- /wp:shortcode -->';
    $q = fn($question, $answer) => '<!-- wp:details --><details class="wp-block-details"><summary>' . $question . '</summary>' . $p($answer) . '</details><!-- /wp:details -->';
    $pats = [
        'catalog' => [__( 'Catalog of the page with a summary', 'memorial-plugin' ), __( 'A title, the automatic summary “96 models, from 35,000 ₽” and the catalog.', 'memorial-plugin' ),
            $h(__( 'Catalog', 'memorial-plugin' )) . $p('В каталоге [mem_cat_count], цены [mem_cat_price]. Нажмите на фото, чтобы рассмотреть модель крупнее.') . $sc('[mem_catalog]')],
        'summary' => [__( 'Catalog summary by section', 'memorial-plugin' ), __( 'A paragraph with the number of models and the list of sections with prices — everything is counted automatically.', 'memorial-plugin' ),
            $p('В каталоге [mem_cat_count], цены [mem_cat_price].') . $sc('[mem_cat_list]')],
        'cta' => [__( 'Call to action', 'memorial-plugin' ), __( 'A block with the phone. The title and the text are changed in the shortcode.', 'memorial-plugin' ),
            $sc('[mem_cta title="Позвоните — подскажем, с чего начать" text="Звонки принимаем круглосуточно."]')],
        'handmade' => [__( 'We make it ourselves (photo + text)', 'memorial-plugin' ), __( 'A photo of the craftsman’s work and a couple of sentences — for wreaths, portraits on monuments, ribbon printing.', 'memorial-plugin' ),
            '<!-- wp:media-text {"mediaType":"image","className":"is-style-soft"} --><div class="wp-block-media-text is-stacked-on-mobile is-style-soft"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content">'
            . ( '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html__( 'We make it ourselves', 'memorial-plugin' ) . '</h3><!-- /wp:heading -->' )
            . $p(__( 'Tell in 2–3 sentences how and who does it, how long it takes. Choose a photo of the craftsman at work on the left.', 'memorial-plugin' ))
            . '</div></div><!-- /wp:media-text -->'],
        'faq' => [__( 'Frequently asked questions', 'memorial-plugin' ), __( 'A title and three expandable questions. They get onto the “Questions and answers” page automatically.', 'memorial-plugin' ),
            $h(__( 'Frequently asked questions', 'memorial-plugin' )) . $q(__( 'Question?', 'memorial-plugin' ), __( 'Answer.', 'memorial-plugin' )) . $q(__( 'Question?', 'memorial-plugin' ), __( 'Answer.', 'memorial-plugin' )) . $q(__( 'Question?', 'memorial-plugin' ), __( 'Answer.', 'memorial-plugin' ))],
        'price' => [__( 'Price table', 'memorial-plugin' ), __( 'Two columns “Service — Price”. Prices and “₽” do not break, the table stays neat on a phone.', 'memorial-plugin' ),
            '<!-- wp:table {"className":"is-style-price"} --><figure class="wp-block-table is-style-price"><table class="has-fixed-layout"><thead><tr><th>Услуга или товар</th><th>Цена</th></tr></thead><tbody><tr><td>Название</td><td>от 1 000 ₽</td></tr><tr><td>Название</td><td>от 2 000 ₽</td></tr></tbody></table></figure><!-- /wp:table -->'],
        'reviews' => [__( 'Reviews from the guest book (3 latest)', 'memorial-plugin' ), __( 'Three reviews from the guest book with the photo of the page.', 'memorial-plugin' ),
            $h(__( 'Reviews of our customers', 'memorial-plugin' )) . $sc('[mem_bookreviews limit="3" title=""]') . $p(( '<a href="/otzyvy/">' . esc_html__( 'All reviews →', 'memorial-plugin' ) . '</a>' ))],
        'works' => [__( 'Our works (3 examples)', 'memorial-plugin' ), __( 'Three works with photos, year and prices.', 'memorial-plugin' ),
            $h(__( 'Examples of our works', 'memorial-plugin' )) . $sc('[mem_works limit="3" nav="0" sort="0"]')],
        'works-cards' => [__( 'Examples of works as cards (random)', 'memorial-plugin' ), __( 'Cards “photo, title, size and price” from “Our works” — 6 random ones, different every time. A click leads to the work.', 'memorial-plugin' ),
            $h(__( 'Examples of our works', 'memorial-plugin' )) . $sc('[mem_works vid="карточки" chislo="6" poryadok="случайно"]')],
    ];
    foreach ($pats as $slug => [$title, $desc, $content]) {
        register_block_pattern('memorial/' . $slug, ['title' => $title, 'description' => $desc, 'categories' => ['memorial'],
            'content' => function_exists('memorial_plugin_convert_content') ? memorial_plugin_convert_content($content, false) : $content]);
    }
}, 20);


