<?php
/**
 * Страница для нового типа товара: черновик по тому же образцу, что у остальных каталогов.
 * Создаётся при добавлении типа в «Каталог → Типы и категории» (catalog-types-admin.php) или кнопкой там же.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/*
=========================================================
 * Страница для нового типа товара: черновик по тому же образцу, что у остальных каталогов
 * =======================================================
 */

/** Страница, привязанная к типу (в том числе черновик)
 *
 * @param int $type_id Type ID.
 */
function memorial_plugin_type_page_any( $type_id ) {
	$p = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'meta_key'       => '_mem_cat',
			'meta_value'     => (int) $type_id,
			'fields'         => 'ids',
		)
	);
	return $p ? (int) $p[0] : 0;
}

/** Содержимое страницы: вступление, призыв позвонить, каталог, как заказать, вопросы, призыв
 *
 * @param int $type_id Type ID.
 */
function memorial_plugin_type_page_content_raw( $type_id ) {
	$t    = get_term( $type_id, 'mem_cat' );
	$name = $t ? $t->name : 'Товары';
	$low  = mb_strtolower( $name );
	$p    = fn( $text, $cls = '' ) => '<!-- wp:paragraph' . ( $cls ? ' {"className":"' . $cls . '"}' : '' ) . ' -->' . "\n"
		. '<p' . ( $cls ? ' class="' . $cls . '"' : '' ) . '>' . $text . '</p>' . "\n<!-- /wp:paragraph -->";
	$h    = fn( $text ) => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . $text . "</h2>\n<!-- /wp:heading -->";
	$sc   = fn( $code ) => "<!-- wp:shortcode -->\n" . $code . "\n<!-- /wp:shortcode -->";
	$list = fn( $items, $cls, $ol = false ) => '<!-- wp:list {' . ( $ol ? '"ordered":true,' : '' ) . '"className":"' . $cls . '"} -->' . "\n"
		. '<' . ( $ol ? 'ol' : 'ul' ) . ' class="wp-block-list ' . $cls . '">'
		. implode( '', array_map( fn( $i ) => "<!-- wp:list-item -->\n<li>" . $i . "</li>\n<!-- /wp:list-item -->", $items ) )
		. '</' . ( $ol ? 'ol' : 'ul' ) . ">\n<!-- /wp:list -->";
	$q    = fn( $question, $answer ) => "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . $question . '</summary>' . $p( $answer ) . "</details>\n<!-- /wp:details -->";
	return implode(
		"\n\n",
		array(
			$p( '[Вступление: 1–2 предложения — что предлагаем, от какой цены, чем отличаемся. Например: «' . esc_html( $name ) . ' в Белгороде — в наличии и под заказ, от … ₽. Поможем выбрать и доставим».]', 'is-style-lead' ),
			$sc( '[mem_cta title="Поможем выбрать" text="Позвоните — подскажем по моделям и ценам, примем заказ."]' ),
			$h( 'Каталог: ' . esc_html( $low ) ),
			$sc( '[mem_catalog cat="' . (int) $type_id . '"]' ),
			$h( 'Как заказать' ),
			$list( array( '<strong>Выберите</strong> модель в каталоге — или расскажите по телефону, что нужно.', '<strong>Позвоните.</strong> Уточним наличие, сроки и стоимость.', '<strong>Получите заказ</strong> — [в офисе / с доставкой, впишите как у вас].' ), 'is-style-steps', true ),
			$h( 'Частые вопросы' ),
			$q( 'Сколько стоят ' . esc_html( $low ) . '?', '[Ответ: от … до … ₽, от чего зависит цена.]' ),
			$q( 'Есть ли доставка?', '[Ответ: как и куда доставляете, сколько стоит.]' ),
			$q( 'Можно ли заказать нужный размер или цвет?', '[Ответ.]' ),
			$sc( '[mem_cta]' ),
		)
	) . "\n";
}

/** Создать черновик страницы типа (если её ещё нет)
 *
 * @param int $type_id Type ID.
 */
function memorial_plugin_type_page_create( $type_id ) {
	$id = memorial_plugin_type_page_any( $type_id );
	if ( $id ) {
		return $id;
	}
	$t = get_term( $type_id, 'mem_cat' );
	if ( ! $t || is_wp_error( $t ) ) {
		return 0;
	}
	$parent = get_page_by_path( 'ritualnye-tovary' );
	$id     = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => $t->name,
			'post_parent'  => $parent && 'publish' === $parent->post_status ? $parent->ID : 0,
			'post_content' => wp_slash( memorial_plugin_blocks_from_shortcodes( memorial_plugin_type_page_content( $type_id ) ) ),
		)
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	update_post_meta( $id, '_mem_cat', (int) $type_id );
	return $id;
}

/** Заготовка страницы раздела каталога. Шорткоды [mem_…] в заготовке сразу становятся блоками и значениями сайта (admin/convert.php).
 *
 * @param int $type_id Type ID.
 */
function memorial_plugin_type_page_content( $type_id ) {
	$content = memorial_plugin_type_page_content_raw( $type_id );
	return function_exists( 'memorial_plugin_convert_content' ) ? memorial_plugin_convert_content( $content ) : $content;
}
