<?php
/**
 * Блоки-обёртки (этап 2): шапка, подвал, хлебные крошки, страницы с товарами, главная и др.
 *
 * Тема переходит на HTML-шаблоны (блоковая тема), а данные сайта — телефоны, адреса, цены, каталог —
 * дают блоки плагина. На этапе 2 эти блоки выводят ту же разметку, что прежние PHP-шаблоны темы,
 * поэтому внешний вид и автотесты не меняются. Регистрация — только на PHP (WordPress 7.0+, supports.autoRegister):
 * редактор показывает их предпросмотр с сервера. На этапе 3 они переписываются полноценно
 * (block.json, атрибуты, стили, Interactivity API), а часть заменяется блоками ядра.
 *
 * @package memorial-plugin
 */

defined('ABSPATH') || exit;

/** Блоки-обёртки: слаг => [название, описание, иконка Dashicons] */
function memorial_plugin_legacy_blocks() {
    return [
        'topbar'          => [__('Top bar: address and hours', 'memorial-plugin'), __('Address, opening hours and night phone line above the header.', 'memorial-plugin'), 'location'],
        'header-contacts' => [__('Header: phone and call back', 'memorial-plugin'), __('Main phone number, “Call me back” button and the call icon for phones.', 'memorial-plugin'), 'phone'],
        'tagline'         => [__('Tagline with years in business', 'memorial-plugin'), __('“Funeral company in business for over N years” — or the site tagline if the founding year is not set.', 'memorial-plugin'), 'heart'],
        'footer-contacts' => [__('Footer: addresses and phones', 'memorial-plugin'), __('Address and phone columns from Memorial → Contacts.', 'memorial-plugin'), 'phone'],
        'menu-columns'    => [__('Menu in columns', 'memorial-plugin'), __('Footer menu: items with sub-items become columns, single items go to “Information”.', 'memorial-plugin'), 'menu'],
        'footer-legal'    => [__('Footer: copyright and documents', 'memorial-plugin'), __('Copyright years, company details, privacy and cookie policy links.', 'memorial-plugin'), 'media-document'],
        'mobile-bar'      => [__('Phone bar on mobile', 'memorial-plugin'), __('Bottom bar on phones: Call and Message (MAX) or Call me back.', 'memorial-plugin'), 'smartphone'],
        'breadcrumbs'     => [__('Breadcrumbs', 'memorial-plugin'), __('Path to the current page; also adds BreadcrumbList structured data.', 'memorial-plugin'), 'arrow-right-alt2'],
        'home'            => [__('Home page sections', 'memorial-plugin'), __('First screen and the home sections in the order set in Memorial → Home page.', 'memorial-plugin'), 'admin-home'],
        'page-products'   => [__('Page with products', 'memorial-plugin'), __('Catalog tiles or product grid for pages set to show products, with the page text above and below.', 'memorial-plugin'), 'grid-view'],
        'contacts-page'   => [__('Contacts page', 'memorial-plugin'), __('Phones, messengers, addresses with routes, maps and company details.', 'memorial-plugin'), 'id'],
        'product'         => [__('Product', 'memorial-plugin'), __('Product photo, price, specifications, call buttons and related products.', 'memorial-plugin'), 'products'],
        'catalog-archive' => [__('Catalog section', 'memorial-plugin'), __('Catalog or category: sub-sections, sorting, product grid and pagination.', 'memorial-plugin'), 'archive'],
        'not-found'       => [__('Page not found', 'memorial-plugin'), __('404 message with a call button and catalog sections.', 'memorial-plugin'), 'warning'],
    ];
}

add_filter('block_categories_all', function ($cats) {
    foreach ($cats as $c) if (($c['slug'] ?? '') === 'memorial') return $cats;
    array_unshift($cats, ['slug' => 'memorial', 'title' => __('Memorial', 'memorial-plugin'), 'icon' => null]);
    return $cats;
});

add_action('init', function () {
    foreach (memorial_plugin_legacy_blocks() as $slug => [$title, $desc, $icon]) {
        register_block_type('memorial-plugin/' . $slug, [
            'api_version'     => 3,
            'title'           => $title,
            'description'     => $desc,
            'category'        => 'memorial',
            'icon'            => $icon,
            'textdomain'      => 'memorial-plugin',
            'supports'        => ['autoRegister' => true, 'html' => false, 'reusable' => false],
            'render_callback' => function ($attributes, $content, $block) use ($slug) {
                return memorial_plugin_legacy_render($slug, $block);
            },
        ]);
    }
});

/**
 * Вывод блока-обёртки: файл render/<слаг>.php печатает разметку.
 *
 * @param string        $slug  Слаг блока.
 * @param WP_Block|null $block Блок (контекст записи и т. п.).
 * @return string
 */
function memorial_plugin_legacy_render($slug, $block = null) {
    $file = __DIR__ . '/render/' . $slug . '.php';
    if (!is_readable($file)) return '';
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

/** id текущей записи для блоков на шаблонах отдельной страницы/товара */
function memorial_plugin_legacy_post_id($block = null) {
    $id = (int) ($block->context['postId'] ?? 0);
    return $id ?: (int) get_queried_object_id();
}

/** Общая часть блоков (render/parts/<имя>.php) — в своей области видимости, как прежде get_template_part() */
function memorial_plugin_legacy_part($name) {
    $file = __DIR__ . '/render/parts/' . $name . '.php';
    if (is_readable($file)) (static function () use ($file) { require $file; })();
}
