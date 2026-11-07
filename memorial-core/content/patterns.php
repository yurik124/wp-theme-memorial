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
/* Справочник «Шорткоды и блоки» — в admin/help.php */

add_action('init', function () {
    if (!function_exists('register_block_pattern')) return;
    if (!WP_Block_Pattern_Categories_Registry::get_instance()->is_registered('memorial')) {
        register_block_pattern_category('memorial', ['label' => 'Мемориал']);
    }
    $p = fn($t, $cls = '') => '<!-- wp:paragraph' . ($cls ? ' {"className":"' . $cls . '"}' : '') . ' --><p' . ($cls ? ' class="' . $cls . '"' : '') . '>' . $t . '</p><!-- /wp:paragraph -->';
    $h = fn($t) => '<!-- wp:heading --><h2 class="wp-block-heading">' . $t . '</h2><!-- /wp:heading -->';
    $sc = fn($c) => '<!-- wp:shortcode -->' . $c . '<!-- /wp:shortcode -->';
    $q = fn($question, $answer) => '<!-- wp:details --><details class="wp-block-details"><summary>' . $question . '</summary>' . $p($answer) . '</details><!-- /wp:details -->';
    $pats = [
        'catalog' => ['Каталог страницы со сводкой', 'Заголовок, автоматическая сводка «96 моделей, от 35 000 ₽» и каталог.',
            $h('Каталог') . $p('В каталоге [mem_cat_count], цены [mem_cat_price]. Нажмите на фото, чтобы рассмотреть модель крупнее.') . $sc('[mem_catalog]')],
        'summary' => ['Сводка каталога по разделам', 'Абзац с числом моделей и список разделов с ценами — всё считается само.',
            $p('В каталоге [mem_cat_count], цены [mem_cat_price].') . $sc('[mem_cat_list]')],
        'cta' => ['Призыв позвонить', 'Тёмный блок с телефоном. Заголовок и текст меняются в коде шорткода.',
            $sc('[mem_cta title="Позвоните — подскажем, с чего начать" text="Звонки принимаем круглосуточно."]')],
        'handmade' => ['Делаем сами (фото + текст)', 'Фото работы мастера и пара предложений — для венков, портретов на памятниках, печати лент.',
            '<!-- wp:media-text {"mediaType":"image","className":"is-style-soft"} --><div class="wp-block-media-text is-stacked-on-mobile is-style-soft"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content">'
            . '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Делаем сами</h3><!-- /wp:heading -->'
            . $p('Расскажите в 2–3 предложениях, как и кто это делает, сколько времени занимает. Слева выберите фото мастера за работой.')
            . '</div></div><!-- /wp:media-text -->'],
        'faq' => ['Частые вопросы', 'Заголовок и три раскрывающихся вопроса. Они сами попадут на страницу «Вопрос–ответ».',
            $h('Частые вопросы') . $q('Вопрос?', 'Ответ.') . $q('Вопрос?', 'Ответ.') . $q('Вопрос?', 'Ответ.')],
        'price' => ['Таблица цен', 'Две колонки «Услуга — Цена». Цены и «₽» не разрываются, на телефоне таблица остаётся аккуратной.',
            '<!-- wp:table {"className":"is-style-price"} --><figure class="wp-block-table is-style-price"><table class="has-fixed-layout"><thead><tr><th>Услуга или товар</th><th>Цена</th></tr></thead><tbody><tr><td>Название</td><td>от 1 000 ₽</td></tr><tr><td>Название</td><td>от 2 000 ₽</td></tr></tbody></table></figure><!-- /wp:table -->'],
        'reviews' => ['Отзывы из книги (3 последних)', 'Три отзыва из книги отзывов с фото страницы.',
            $h('Отзывы наших клиентов') . $sc('[mem_bookreviews limit="3" title=""]') . $p('<a href="/otzyvy/">Все отзывы →</a>')],
        'works' => ['Наши работы (3 примера)', 'Три работы с фото, годом и ценами.',
            $h('Примеры наших работ') . $sc('[mem_works limit="3" nav="0" sort="0"]')],
        'works-cards' => ['Примеры работ карточками (случайные)', 'Карточки «фото, название, размер и цена» из «Наших работ» — 6 случайных, при каждом открытии другие. Клик ведёт к работе.',
            $h('Примеры наших работ') . $sc('[mem_works vid="карточки" chislo="6" poryadok="случайно"]')],
    ];
    foreach ($pats as $slug => [$title, $desc, $content]) {
        register_block_pattern('memorial/' . $slug, ['title' => $title, 'description' => $desc, 'categories' => ['memorial'], 'content' => $content]);
    }
}, 20);


