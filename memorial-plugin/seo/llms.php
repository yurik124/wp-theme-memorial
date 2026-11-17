<?php
/**
 * Файл llms.txt — описание сайта для языковых моделей и ИИ-поиска (https://llmstxt.org): Markdown, заголовок H1,
 * кратко о компании, контакты, разделы сайта со ссылками и описаниями, вопросы и ответы, правила.
 *
 * Готовый файл лежит в корне сайта: его отдаёт веб-сервер без WordPress — быстро (проверка ИИ-доступности
 * не дожидалась ответа WordPress, «Timed out»). Обновляется через минуту после сохранения страниц,
 * меню, товаров и настроек (WP-Cron). Если в корень записать нельзя — тот же текст отдаёт WordPress
 * по адресу /llms.txt в самом начале запроса.
 * Владелец решил (16.11.2026): цены показывать, обучение моделей на текстах сайта разрешено.
 *
 * Файл, который сделал плагин, помечен в опции memorial_plugin_llms_file; чужой файл не перезаписывается.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Короткое описание страницы: «Полезно знать» → краткий ответ, отрывок, иначе первый абзац.
 *
 * @param int $id Page ID.
 * @return string
 */
function memorial_plugin_llms_page_desc( $id ) {
	$p = get_post( $id );
	if ( ! $p ) {
		return '';
	}
	$d = trim( (string) get_post_meta( $id, '_mem_qa', true ) );
	if ( '' === $d ) {
		$d = trim( (string) $p->post_excerpt );
	}
	if ( '' === $d && preg_match( '~<p\b[^>]*>(.*?)</p>~su', (string) $p->post_content, $m ) ) {
		$d = $m[1];
	}
	$d = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( preg_replace( '~\[/?[a-z_]+[^\]]*\]~u', '', html_entity_decode( $d, ENT_QUOTES, 'UTF-8' ) ) ) ) );
	return wp_trim_words( $d, 30, '…' );
}

/**
 * Строка Markdown без символов разметки, которые сломали бы ссылку.
 *
 * @param string $s Text.
 * @return string
 */
function memorial_plugin_llms_text( $s ) {
	return trim( str_replace( array( '[', ']', "\n", "\r" ), array( '(', ')', ' ', ' ' ), html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ) ) );
}

/**
 * Текст llms.txt.
 *
 * @return string
 */
function memorial_plugin_llms_build() {
	$name = memorial_plugin_llms_text( get_bloginfo( 'name' ) );
	$desc = memorial_plugin_llms_text( get_bloginfo( 'description' ) );
	$c    = function_exists( 'memorial_plugin_contacts' ) ? memorial_plugin_contacts() : array();
	$out  = array( '# ' . ( '' !== $name ? $name : wp_parse_url( home_url(), PHP_URL_HOST ) ), '' );
	$out[] = '> ' . ( '' !== $desc ? rtrim( $desc, '. ' ) : __( 'Funeral services company', 'memorial-plugin' ) ) . '. ' . __( 'Belgorod: arranging funerals and cremation, transportation of the deceased, mourning hall, memorial meals, funeral goods, monuments and grave improvement.', 'memorial-plugin' );
	$out[] = '';

	// Контакты.
	$lines = array();
	foreach ( (array) ( $c['phones'] ?? array() ) as $ph ) {
		if ( ! empty( $ph['num'] ) ) {
			$lines[] = '- ' . __( 'Phone', 'memorial-plugin' ) . ': ' . memorial_plugin_llms_text( $ph['num'] );
		}
	}
	foreach ( (array) ( $c['addrs'] ?? array() ) as $ad ) {
		if ( ! empty( $ad['addr'] ) ) {
			$lines[] = '- ' . __( 'Address', 'memorial-plugin' ) . ': ' . memorial_plugin_llms_text( $ad['addr'] ) . ( ! empty( $ad['hours'] ) ? ' (' . memorial_plugin_llms_text( $ad['hours'] ) . ')' : '' );
		}
	}
	foreach ( (array) ( $c['emails'] ?? array() ) as $em ) {
		if ( ! empty( $em['email'] ) ) {
			$lines[] = '- E-mail: ' . sanitize_email( $em['email'] );
		}
	}
	if ( ! empty( $c['night'] ) ) {
		$lines[] = '- ' . memorial_plugin_llms_text( $c['night'] );
	}
	if ( $lines ) {
		$out[] = '## ' . __( 'Contacts', 'memorial-plugin' );
		$out[] = '';
		array_push( $out, ...$lines );
		$out[] = '';
	}

	// Разделы — по главному меню: пункты верхнего уровня — подзаголовки, вложенные — ссылки.
	$items = function_exists( 'memorial_plugin_menu_items' ) ? memorial_plugin_menu_items() : array();
	$link  = static function ( $it ) {
		$d = 'post_type' === $it->type && $it->object_id ? memorial_plugin_llms_page_desc( $it->object_id ) : '';
		return '- [' . memorial_plugin_llms_text( $it->title ) . '](' . esc_url_raw( $it->url ) . ')' . ( '' !== $d ? ': ' . memorial_plugin_llms_text( $d ) : '' );
	};
	$top  = array_filter( $items, static fn( $it ) => ! $it->menu_item_parent && '' !== $it->url && '#' !== $it->url );
	$flat = array();
	foreach ( $top as $it ) {
		$kids = array_filter( $items, static fn( $k ) => (int) $k->menu_item_parent === (int) $it->ID && '' !== $k->url );
		if ( $kids ) {
			$out[] = '## ' . memorial_plugin_llms_text( $it->title );
			$out[] = '';
			if ( '#' !== $it->url && ! str_contains( (string) $it->url, '#' ) ) {
				$out[] = $link( $it );
			}
			foreach ( $kids as $k ) {
				$out[] = $link( $k );
			}
			$out[] = '';
		} else {
			$flat[] = $link( $it );
		}
	}
	if ( $flat ) {
		$out[] = '## ' . __( 'Site pages', 'memorial-plugin' );
		$out[] = '';
		array_push( $out, ...$flat );
		$out[] = '';
	}

	// Вопросы и ответы — разделы страницы «Вопрос–ответ».
	$hub = function_exists( 'memorial_plugin_faq_hub_id' ) ? memorial_plugin_faq_hub_id() : 0;
	if ( $hub ) {
		$out[] = '## ' . __( 'Questions and answers', 'memorial-plugin' );
		$out[] = '';
		$out[] = '- [' . memorial_plugin_llms_text( get_the_title( $hub ) ) . '](' . esc_url_raw( get_permalink( $hub ) ) . '): ' . __( 'answers to frequent questions, by topic', 'memorial-plugin' );
		foreach ( memorial_plugin_faq_sections( $hub ) as $s ) {
			$out[] = '- [' . memorial_plugin_llms_text( $s['title'] ) . '](' . esc_url_raw( get_permalink( $hub ) . '#' . $s['anchor'] ) . ')';
		}
		$out[] = '';
	}

	$out[] = '## ' . __( 'Use of the content', 'memorial-plugin' );
	$out[] = '';
	$out[] = '- ' . __( 'The texts and prices of the site may be used by search engines and AI assistants, including for training language models, with a link to the site.', 'memorial-plugin' );
	$out[] = '- ' . __( 'Prices are given in roubles and may change; the exact cost is confirmed by phone.', 'memorial-plugin' );
	$out[] = '- ' . __( 'Do not use personal data from reviews and requests.', 'memorial-plugin' );
	return implode( "\n", $out ) . "\n";
}

/**
 * Записать llms.txt в корень сайта (только свой файл: чужой не трогаем).
 *
 * @return bool Записан ли файл.
 */
function memorial_plugin_llms_write() {
	$file = ABSPATH . 'llms.txt';
	if ( file_exists( $file ) && ! get_option( 'memorial_plugin_llms_file' ) ) {
		return false;
	}
	global $wp_filesystem;
	require_once ABSPATH . 'wp-admin/includes/file.php';
	if ( 'direct' !== get_filesystem_method( array(), ABSPATH ) || ! WP_Filesystem() ) {
		return false;
	}
	$ok = $wp_filesystem->put_contents( $file, memorial_plugin_llms_build(), FS_CHMOD_FILE );
	if ( $ok ) {
		update_option( 'memorial_plugin_llms_file', time(), false );
		memorial_plugin_llms_charset();
	}
	return (bool) $ok;
}

/**
 * Кодировка llms.txt: веб-сервер отдаёт файл как text/plain без charset, и программы читают кириллицу
 * как Latin-1 (кракозябры). Правило в .htaccess корня сайта (Apache) между своими метками — как WordPress
 * пишет свои правила; без .htaccess (nginx) ничего не делается. Убирается при удалении плагина.
 *
 * @param bool $on Добавить (true) или убрать правило.
 * @return bool Записано ли.
 */
function memorial_plugin_llms_charset( $on = true ) {
	$htaccess = ABSPATH . '.htaccess';
	if ( ! file_exists( $htaccess ) || ! wp_is_writable( $htaccess ) ) {
		return false;
	}
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$rules = $on ? array( '<Files "llms.txt">', 'AddDefaultCharset UTF-8', '</Files>' ) : array();
	return (bool) insert_with_markers( $htaccess, 'Memorial llms.txt', $rules );
}
add_action( 'memorial_plugin_llms', 'memorial_plugin_llms_write' );

/** Обновить llms.txt через минуту — после сохранения (несколько сохранений подряд — одна запись). */
function memorial_plugin_llms_soon() {
	if ( ! wp_next_scheduled( 'memorial_plugin_llms' ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'memorial_plugin_llms' );
	}
}
foreach ( array( 'save_post_page', 'save_post_wp_navigation', 'save_post_mem_product', 'deleted_post' ) as $memorial_plugin_hook ) {
	add_action( $memorial_plugin_hook, 'memorial_plugin_llms_soon' );
}
add_action(
	'updated_option',
	static function ( $option ) {
		if ( in_array( $option, array( 'mem_contacts', 'blogname', 'blogdescription' ), true ) ) {
			memorial_plugin_llms_soon();
		}
	}
);

/* Правило кодировки для уже записанного файла — один раз после обновления плагина. */
add_action(
	'admin_init',
	static function () {
		if ( get_option( 'memorial_plugin_llms_charset' ) || ! get_option( 'memorial_plugin_llms_file' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( 'memorial_plugin_llms_charset', memorial_plugin_llms_charset() ? 1 : -1, false );
	}
);

/* Первый раз — сразу после обновления плагина (файла ещё нет). */
add_action(
	'init',
	static function () {
		if ( ! get_option( 'memorial_plugin_llms_file' ) && ! file_exists( ABSPATH . 'llms.txt' ) && ! get_transient( 'memorial_plugin_llms_try' ) ) {
			set_transient( 'memorial_plugin_llms_try', 1, HOUR_IN_SECONDS );
			memorial_plugin_llms_soon();
		}
	},
	30
);

/* Файла нет (в корень записать нельзя) — отдаём текст сами, в самом начале запроса. */
add_action(
	'parse_request',
	static function ( $wp ) {
		if ( 'llms.txt' !== ( $wp->request ?? '' ) || file_exists( ABSPATH . 'llms.txt' ) ) {
			return;
		}
		$text = get_transient( 'memorial_plugin_llms_text' );
		if ( false === $text ) {
			$text = memorial_plugin_llms_build();
			set_transient( 'memorial_plugin_llms_text', $text, HOUR_IN_SECONDS );
		}
		nocache_headers();
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text file.
		exit;
	},
	0
);
