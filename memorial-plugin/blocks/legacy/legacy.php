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

defined( 'ABSPATH' ) || exit;

/** Блоки-обёртки: слаг => [название, описание, иконка Dashicons] */
function memorial_plugin_legacy_blocks() {
	return array(
		'topbar'          => array( __( 'Line above the header: address and hours', 'memorial-plugin' ), __( 'One of the addresses with its opening hours and the night phone line, above the header. The address is chosen in the block settings.', 'memorial-plugin' ), 'location' ),
		'header-contacts' => array( __( 'Header: phone and call back', 'memorial-plugin' ), __( 'Main phone number, “Call me back” button and the call icon for phones.', 'memorial-plugin' ), 'phone' ),
		'tagline'         => array( __( 'Tagline: years in business', 'memorial-plugin' ), __( '“Funeral company in business for over N years” — or the site tagline if the founding year is not set.', 'memorial-plugin' ), 'heart' ),
		'footer-contacts' => array( __( 'Footer: addresses and phones', 'memorial-plugin' ), __( 'Address and phone columns from Memorial → Contacts.', 'memorial-plugin' ), 'phone' ),
		'menu-columns'    => array( __( 'Footer: menu in columns', 'memorial-plugin' ), __( 'Footer menu: items with sub-items become columns, single items go to “Information”.', 'memorial-plugin' ), 'menu' ),
		'footer-legal'    => array( __( 'Footer: copyright and documents', 'memorial-plugin' ), __( 'Copyright years, company details, privacy and cookie policy links.', 'memorial-plugin' ), 'media-document' ),
		'mobile-bar'      => array( __( 'Bottom phone bar on mobiles', 'memorial-plugin' ), __( 'Bottom bar on phones: Call and Message (MAX) or Call me back.', 'memorial-plugin' ), 'smartphone' ),
		'breadcrumbs'     => array( __( 'Breadcrumbs', 'memorial-plugin' ), __( 'Path to the current page; also adds BreadcrumbList structured data.', 'memorial-plugin' ), 'arrow-right-alt2' ),
		'page-products'   => array( __( 'Products of the page (for the template)', 'memorial-plugin' ), __( 'Catalog tiles or product grid for pages set to show products, with the page text above and below.', 'memorial-plugin' ), 'grid-view' ),
		'contacts-page'   => array( __( 'Contacts: the whole page', 'memorial-plugin' ), __( 'Phones, messengers, addresses with routes, maps and company details.', 'memorial-plugin' ), 'id' ),
		'product'         => array( __( 'Product card', 'memorial-plugin' ), __( 'Photo, price, specifications, call buttons and related products of a product: the one that is open (template) or the one chosen in the block settings.', 'memorial-plugin' ), 'products' ),
		'catalog-archive' => array( __( 'Catalog section with sorting', 'memorial-plugin' ), __( 'Sub-sections, sorting and products of a catalog section: the one that is open (template) or the one chosen in the block settings.', 'memorial-plugin' ), 'archive' ),
		'not-found'       => array( __( 'Message “Page not found”', 'memorial-plugin' ), __( '404 message with a call button and catalog sections.', 'memorial-plugin' ), 'warning' ),
	);
}

/**
 * Settings of the structural blocks: slug => attributes (with labels and pickers, see memorial_plugin_block_attributes()).
 *
 * @return array<string,array>
 */
function memorial_plugin_legacy_block_attrs() {
	return array(
		'topbar'          => array(
			'address' => array(
				'type'    => 'integer',
				'default' => 0,
				'label'   => __( 'Address', 'memorial-plugin' ),
				'picker'  => 'address',
			),
			'hours'   => array(
				'type'    => 'boolean',
				'default' => true,
				'label'   => __( 'Opening hours of the address', 'memorial-plugin' ),
			),
			'night'   => array(
				'type'    => 'boolean',
				'default' => true,
				'label'   => __( 'Night phone line', 'memorial-plugin' ),
			),
		),
		'product'         => array(
			'product' => array(
				'type'    => 'integer',
				'default' => 0,
				'label'   => __( 'Product', 'memorial-plugin' ),
				'picker'  => 'product',
			),
		),
		'catalog-archive' => array(
			'term' => array(
				'type'    => 'integer',
				'default' => 0,
				'label'   => __( 'Catalog section', 'memorial-plugin' ),
				'picker'  => 'cat-id',
			),
		),
	);
}

add_action(
	'init',
	function () {
		$all_attrs = memorial_plugin_legacy_block_attrs();
		foreach ( memorial_plugin_legacy_blocks() as $slug => [$title, $desc, $icon] ) {
			$attributes = memorial_plugin_block_attributes( 'memorial-plugin/' . $slug, $all_attrs[ $slug ] ?? array() );
			memorial_plugin_register_block(
				$slug,
				array(
					'api_version'     => 3,
					'title'           => $title,
					'description'     => $desc,
					'category'        => memorial_plugin_block_category( $slug ),
					'icon'            => $icon,
					'textdomain'      => 'memorial-plugin',
					'keywords'        => memorial_plugin_block_keywords_of( $slug ),
					'example'         => memorial_plugin_block_example( $attributes, 1200 ),
					'attributes'      => $attributes,
					'supports'        => memorial_plugin_block_supports( 'structure', array( 'reusable' => false ) ),
					'render_callback' => function ( $attributes, $content, $block ) use ( $slug ) {
						return memorial_plugin_legacy_render( $slug, $block );
					},
				)
			);
		}
	}
);

/**
 * Вывод блока-обёртки: файл render/<слаг>.php печатает разметку.
 *
 * @param string        $slug  Слаг блока.
 * @param WP_Block|null $block Блок (контекст записи и т. п.).
 * @return string
 */
function memorial_plugin_legacy_render( $slug, $block = null ) {
	static $active = array();
	$file          = __DIR__ . '/render/' . $slug . '.php';
	// блок, выводящий текст записи, не может стоять в этом же тексте — иначе бесконечный круг.
	if ( ! is_readable( $file ) || ! empty( $active[ $slug ] ) ) {
		return '';
	}
	$active[ $slug ] = true;
	ob_start();
	try {
		require $file;
	} finally {
		$html = (string) ob_get_clean();
		unset( $active[ $slug ] );
	}
	return $html;
}

/** ID текущей записи для блоков на шаблонах отдельной страницы/товара
 *
 * @param WP_Block $block Block instance.
 */
function memorial_plugin_legacy_post_id( $block = null ) {
	// A product chosen in the block settings.
	$chosen = $block instanceof WP_Block ? (int) ( $block->attributes['product'] ?? 0 ) : 0;
	if ( $chosen && 'mem_product' === get_post_type( $chosen ) ) {
		return $chosen;
	}
	$id = (int) ( $block->context['postId'] ?? 0 );
	return $id ? $id : (int) get_queried_object_id();
}

/** Общая часть блоков (render/parts/<имя>.php) — в своей области видимости, как прежде get_template_part()
 *
 * @param string $name Name.
 */
function memorial_plugin_legacy_part( $name ) {
	$file = __DIR__ . '/render/parts/' . $name . '.php';
	if ( is_readable( $file ) ) {
		( static function () use ( $file ) {
				require $file;
		} )();
	}
}

/*
 * Шаблоны для блоковых тем (WordPress 6.7+ register_block_template): товар, каталог, раздел каталога,
 * страница с товарами. Шапка и подвал — части активной темы; тема может заменить шаблон своим файлом.
 */
add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}
		$templates = array(
			'single-mem_product'  => array( __( 'Product', 'memorial-plugin' ), __( 'A single product from the catalog.', 'memorial-plugin' ) ),
			'archive-mem_product' => array( __( 'Catalog', 'memorial-plugin' ), __( 'All products of the catalog.', 'memorial-plugin' ) ),
			'taxonomy-mem_cat'    => array( __( 'Catalog section', 'memorial-plugin' ), __( 'Products of one catalog section.', 'memorial-plugin' ) ),
			'page-products'       => array( __( 'Page with products', 'memorial-plugin' ), __( 'Pages set to show catalog tiles or products.', 'memorial-plugin' ) ),
		);
		foreach ( $templates as $slug => [$title, $desc] ) {
			register_block_template(
				'memorial-plugin//' . $slug,
				array(
					'title'       => $title,
					'description' => $desc,
					'content'     => (string) file_get_contents( __DIR__ . '/templates/' . $slug . '.html' ),
				)
			);
		}
	}
);

/* Страница с товарами (поле «Товары на странице») — свой шаблон page-products */
add_filter(
	'page_template_hierarchy',
	function ( $templates ) {
		$id = (int) get_queried_object_id();
		if ( $id && get_post_meta( $id, '_mem_products', true ) ) {
			array_unshift( $templates, 'page-products' );
		}
		return $templates;
	}
);

/* «Скрыть фото страницы над текстом» (поле _mem_hide_thumb) — для блока «Изображение записи» */
add_filter(
	'render_block_core/post-featured-image',
	function ( $html, $block ) {
		$id = (int) ( $block['attrs']['postId'] ?? 0 );
		if ( ! $id ) {
			$id = (int) get_queried_object_id();
		}
		return ( $id && is_singular( 'page' ) && get_post_meta( $id, '_mem_hide_thumb', true ) ) ? '' : $html;
	},
	10,
	2
);

/**
 * Is this a preview of a block in the editor (the block renderer of the REST API)?
 *
 * @return bool
 */
function memorial_plugin_is_block_preview() {
	return defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'edit_posts' );
}

/**
 * A note shown only in the editor preview of a block (on the site — nothing).
 *
 * @param string $text Text.
 * @return string
 */
function memorial_plugin_block_note( $text ) {
	return memorial_plugin_is_block_preview() ? '<p class="is-style-note memorial-plugin-block-note">' . esc_html( $text ) . '</p>' : '';
}
