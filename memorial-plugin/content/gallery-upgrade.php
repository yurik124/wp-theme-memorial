<?php
/**
 * Разовый перенос memorial_plugin_gallery_1 (ТЗ «Блоки и доработки» 24.11.2026, раздел 12; решения владельца
 * 25.11.2026): у фото «Фотогалереи» — подпись и ссылка «Подробнее →» на страницу услуги, описание фото (alt),
 * если его нет; SEO-описание страницы, если оно пустое. Нажатие на фото по-прежнему открывает его крупно.
 * Фото с подписью, сделанной владельцем, не трогаются. Журнал — memorial_plugin_gallery_1_log.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Photos of the gallery page: attachment ID => [caption, page path].
 *
 * @return array<int,array{0:string,1:string}>
 */
function memorial_plugin_gallery_captions_1() {
	$building = array( 'Здание «Мемориала» на ул. Студенческой, 40', 'kontakty' );
	$hall     = array( 'Траурный зал — прощание и отпевание', 'ritualnye-uslugi/traurnyj-zal' );
	$grey     = array( 'Катафалк Ford Transit на 8 мест для провожающих', 'ritualnye-uslugi/katafalk' );
	$salon    = array( 'Салон катафалка: места для провожающих', 'ritualnye-uslugi/katafalk' );
	$silver   = array( 'Катафалк Ford Transit на 3 места для провожающих', 'ritualnye-uslugi/katafalk' );
	return array(
		714  => $building,
		713  => $building,
		1683 => $hall,
		1637 => $hall,
		1598 => $grey,
		1597 => $grey,
		717  => $salon,
		716  => $salon,
		1594 => $silver,
		1595 => $silver,
	);
}

/**
 * Text of an image block with the caption, the link and the alt.
 *
 * @param string $html    innerHTML of the core/image block.
 * @param string $caption Caption.
 * @param string $url     Address of the page (empty — no link).
 * @return string
 */
function memorial_plugin_gallery_caption_html( $html, $caption, $url ) {
	if ( false !== strpos( $html, '<figcaption' ) ) {
		return $html;
	}
	$html = preg_replace( '/(<img\b[^>]*\balt=")("[^>]*>)/', '$1' . esc_attr( $caption ) . '$2', $html, 1 );
	$cap  = '<figcaption class="wp-element-caption">' . esc_html( $caption ) . ( '' !== $url ? '. <a href="' . esc_url( $url ) . '">Подробнее →</a>' : '' ) . '</figcaption>';
	return (string) preg_replace( '~</figure>\s*$~', $cap . '</figure>', $html, 1 );
}

/**
 * Address of a published page or post by its path; '' if there is none.
 *
 * @param string $path Path without slashes at the ends.
 * @return string
 */
function memorial_plugin_gallery_target_url( $path ) {
	$target = get_page_by_path( $path );
	if ( ! $target ) {
		// A page of another post type (as «Катафалк» may be) — find it by the address.
		$id     = url_to_postid( home_url( '/' . $path . '/' ) );
		$target = $id ? get_post( $id ) : null;
	}
	return $target && 'publish' === $target->post_status ? (string) get_permalink( $target ) : '';
}

/**
 * The run. $dry = true — only what would change, nothing is written.
 *
 * @param bool $dry Dry run.
 * @return array Log: post => [id => old content], desc => [id => old], photos (count).
 */
function memorial_plugin_gallery_upgrade_1( $dry = false ) {
	global $wpdb;
	$log  = array( 'time' => time() );
	$page = get_page_by_path( 'fotogalereia' );
	if ( ! $page ) {
		$log['skipped'] = 'fotogalereia';
		return $log;
	}
	$map    = memorial_plugin_gallery_captions_1();
	$count  = 0;
	$walk   = static function ( $blocks ) use ( &$walk, $map, &$count ) {
		foreach ( $blocks as &$b ) {
			$id = (int) ( $b['attrs']['id'] ?? 0 );
			if ( 'core/image' === ( $b['blockName'] ?? '' ) && isset( $map[ $id ] ) ) {
				list( $caption, $path ) = $map[ $id ];
				$url                    = memorial_plugin_gallery_target_url( $path );
				$new                    = memorial_plugin_gallery_caption_html( (string) $b['innerHTML'], $caption, $url );
				if ( $new !== $b['innerHTML'] ) {
					$b['innerHTML']    = $new;
					$b['innerContent'] = array( $new );
					++$count;
				}
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$b['innerBlocks'] = $walk( $b['innerBlocks'] );
			}
		}
		unset( $b );
		return $blocks;
	};
	$content = (string) $page->post_content;
	$new     = serialize_blocks( $walk( parse_blocks( $content ) ) );
	if ( $count && $new !== $content ) {
		$log['posts'][ $page->ID ] = $content;
		$log['photos']             = $count;
		if ( ! $dry ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $page->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time move, no revisions.
			clean_post_cache( (int) $page->ID );
		}
	}
	// The description of the page for search engines (without it they took the text of the cookie banner).
	if ( '' === trim( (string) get_post_meta( $page->ID, '_seopress_titles_desc', true ) ) ) {
		$log['desc'][ $page->ID ] = '';
		if ( ! $dry ) {
			update_post_meta( $page->ID, '_seopress_titles_desc', 'Фото здания, траурного зала, торгового зала и катафалков «Мемориала», примеры благоустройства могил в Белгороде.' );
		}
	}
	return $log;
}

add_action(
	'admin_init',
	static function () {
		if ( get_option( 'memorial_plugin_gallery_1' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'memorial_plugin_gallery_1', time(), false );
		update_option( 'memorial_plugin_gallery_1_log', memorial_plugin_gallery_upgrade_1(), false );
		if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
	},
	9
);
