<?php
/**
 * Страницы из репозитория: файлы memorial-plugin/content/pages/<адрес>.html попадают на сайт тем же путём,
 * что и код (файл → проверка в CI → слияние → деплой → вход администратора в админку).
 *
 * Файл: первая строка — заголовок <!-- memorial-page { … } --> (slug, parent, title, seo_title,
 * seo_description, status, template, menu, featured_image, version), дальше — разметка блоков.
 * Проверка файлов до слияния — tools/content-lint.php.
 *
 * Импорт (ТЗ «Блоки и доработки» 24.11.2026, раздел 1):
 * - нет страницы — создаётся;
 * - страница создана импортом, текст на сайте не правили (хеш _mem_repo_hash) и версия файла выросла —
 *   обновляется, перед записью сохраняется ревизия;
 * - текст правили в админке — «конфликт»: решает владелец на экране «Мемориал → Страницы из репозитория»;
 * - старая страница, созданная до модуля, — только через экран, с показом разницы;
 * - блоки не из списка разрешённых — ошибка, страница не трогается;
 * - адреса страниц в атрибутах блоков (cards, guide, link-button) заменяются на ID;
 * - пункт меню только добавляется (никогда не удаляется и не переименовывается).
 * Сам по себе импорт запускается при заходе администратора в админку, когда файлы изменились, и делает
 * только безопасное: создаёт и обновляет нетронутые страницы. Журнал — memorial_plugin_repo_log.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Версия модуля импорта (флаг memorial_plugin_repo_ver). */
const MEMORIAL_PLUGIN_REPO_VER = 1;

/** Блоки, которые можно импортировать (как в tools/content-lint.php); блоки плагина — все. */
const MEMORIAL_PLUGIN_REPO_BLOCKS = array(
	'core/paragraph',
	'core/heading',
	'core/list',
	'core/list-item',
	'core/table',
	'core/details',
	'core/image',
	'core/gallery',
	'core/group',
	'core/columns',
	'core/column',
	'core/media-text',
	'core/separator',
	'core/shortcode',
);

/**
 * Folder of the page files.
 *
 * @return string
 */
function memorial_plugin_repo_dir() {
	return (string) apply_filters( 'memorial_plugin_repo_dir', MEMORIAL_PLUGIN_DIR . '/content/pages' );
}

/**
 * The page files: [path => [file, head, body, error]]. Path — the address without slashes at the ends
 * ('' — the front page, «ritualnye-uslugi/katafalk»).
 *
 * @return array<string,array>
 */
function memorial_plugin_repo_files() {
	static $cache = array();
	$dir = memorial_plugin_repo_dir();
	if ( isset( $cache[ $dir ] ) ) {
		return $cache[ $dir ];
	}
	$out = array();
	if ( ! is_dir( $dir ) ) {
		$cache[ $dir ] = $out;
		return $out;
	}
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $f ) {
		if ( 'html' !== $f->getExtension() ) {
			continue;
		}
		$rel  = str_replace( '\\', '/', substr( $f->getPathname(), strlen( $dir ) + 1, -5 ) );
		$path = 'index' === $rel ? '' : $rel;
		$out[ $path ] = memorial_plugin_repo_read( $f->getPathname(), $rel );
	}
	ksort( $out );
	$cache[ $dir ] = $out;
	return $out;
}

/**
 * One page file. The file is only read and parsed, never run.
 *
 * @param string $file Full path.
 * @param string $rel  Path inside the folder without “.html”.
 * @return array{file:string,head:array,body:string,error:string}
 */
function memorial_plugin_repo_read( $file, $rel ) {
	$raw = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file of the plugin.
	$out = array(
		'file'  => $rel . '.html',
		'head'  => array(),
		'body'  => '',
		'error' => '',
	);
	if ( ! preg_match( '/^\s*<!--\s*memorial-page\s*(\{.*?\})\s*-->/s', $raw, $m ) ) {
		$out['error'] = __( 'No memorial-page header in the first line.', 'memorial-plugin' );
		return $out;
	}
	$head = json_decode( $m[1], true );
	if ( ! is_array( $head ) ) {
		$out['error'] = __( 'The memorial-page header is not JSON.', 'memorial-plugin' );
		return $out;
	}
	foreach ( array( 'slug', 'title', 'status', 'version' ) as $k ) {
		if ( empty( $head[ $k ] ) ) {
			/* translators: %s: field name. */
			$out['error'] = sprintf( __( 'The header has no “%s” field.', 'memorial-plugin' ), $k );
			return $out;
		}
	}
	$out['head'] = $head + array(
		'parent'          => '',
		'seo_title'       => '',
		'seo_description' => '',
		'template'        => '',
		'menu'            => null,
		'featured_image'  => '',
	);
	$out['body'] = trim( substr( $raw, strlen( $m[0] ) ) );
	$bad         = memorial_plugin_repo_bad_blocks( parse_blocks( $out['body'] ) );
	if ( $bad ) {
		/* translators: %s: block names. */
		$out['error'] = sprintf( __( 'Blocks that cannot be imported: %s.', 'memorial-plugin' ), implode( ', ', array_unique( $bad ) ) );
	}
	return $out;
}

/**
 * Blocks of a text that are not allowed (and text outside blocks).
 *
 * @param array $blocks Parsed blocks.
 * @return string[]
 */
function memorial_plugin_repo_bad_blocks( $blocks ) {
	$bad = array();
	foreach ( $blocks as $b ) {
		$name = (string) ( $b['blockName'] ?? '' );
		if ( '' === $name ) {
			if ( '' !== trim( (string) ( $b['innerHTML'] ?? '' ) ) ) {
				$bad[] = __( 'text outside blocks', 'memorial-plugin' );
			}
			continue;
		}
		if ( 0 !== strpos( $name, 'memorial-plugin/' ) && ! in_array( $name, MEMORIAL_PLUGIN_REPO_BLOCKS, true ) ) {
			$bad[] = $name;
		}
		if ( 'core/shortcode' === $name && ! preg_match( '/^\s*\[mem_[a-z_]+/', (string) $b['innerHTML'] ) ) {
			$bad[] = 'core/shortcode';
		}
		$bad = array_merge( $bad, memorial_plugin_repo_bad_blocks( (array) ( $b['innerBlocks'] ?? array() ) ) );
	}
	return $bad;
}

/**
 * The page of a path, 0 if none. '' — the front page.
 *
 * @param string $path Address without slashes at the ends.
 * @return int
 */
function memorial_plugin_repo_page_id( $path ) {
	$path = trim( (string) $path, '/' );
	if ( '' === $path ) {
		return (int) get_option( 'page_on_front' );
	}
	$page = get_page_by_path( $path, OBJECT, 'page' );
	return $page ? (int) $page->ID : 0;
}

/**
 * Text of a file as it goes into post_content: addresses of pages in the block attributes become IDs
 * (IDs on the site copy and on the live site differ, so the files keep addresses).
 *
 * @param string $body    Block markup of the file.
 * @param array  $missing Receives addresses of pages that do not exist yet.
 * @return string
 */
function memorial_plugin_repo_content( $body, &$missing = array() ) {
	$walk = static function ( $blocks ) use ( &$walk, &$missing ) {
		foreach ( $blocks as &$b ) {
			$name = (string) ( $b['blockName'] ?? '' );
			$a    = (array) ( $b['attrs'] ?? array() );
			if ( in_array( $name, array( 'memorial-plugin/cards', 'memorial-plugin/guide' ), true ) ) {
				if ( isset( $a['pages'] ) && is_string( $a['pages'] ) && preg_match( '#/#', $a['pages'] ) ) {
					$ids = array();
					foreach ( array_filter( array_map( 'trim', explode( ',', $a['pages'] ) ) ) as $p ) {
						$id = memorial_plugin_repo_page_id( $p );
						if ( $id ) {
							$ids[] = $id;
						} else {
							$missing[] = $p;
						}
					}
					$a['pages'] = implode( ',', $ids );
				}
				if ( isset( $a['parent'] ) && is_string( $a['parent'] ) && false !== strpos( $a['parent'], '/' ) ) {
					$id = memorial_plugin_repo_page_id( $a['parent'] );
					if ( ! $id ) {
						$missing[] = $a['parent'];
					}
					$a['parent'] = 'memorial-plugin/cards' === $name ? $id : ( $id ? (string) $id : '' );
				}
			}
			if ( 'memorial-plugin/link-button' === $name && isset( $a['page'] ) && is_string( $a['page'] ) ) {
				$id = memorial_plugin_repo_page_id( $a['page'] );
				if ( $id ) {
					$a['page'] = $id;
				} else {
					// The page is not there yet: the button leads by the address until the next import.
					$missing[] = $a['page'];
					$a['url']  = home_url( '/' . trim( $a['page'], '/' ) . '/' );
					$a['page'] = 0;
				}
			}
			$b['attrs'] = $a;
			if ( ! empty( $b['innerBlocks'] ) ) {
				$b['innerBlocks'] = $walk( $b['innerBlocks'] );
			}
		}
		unset( $b );
		return $blocks;
	};
	return serialize_blocks( $walk( parse_blocks( $body ) ) );
}

/**
 * Hash of a text as it is stored.
 *
 * @param string $content post_content.
 * @return string
 */
function memorial_plugin_repo_hash( $content ) {
	return md5( str_replace( "\r\n", "\n", trim( (string) $content ) ) );
}

/**
 * What the import would do with every file — nothing is written.
 *
 * @return array<int,array> Rows: id (path), path, file, title, version, page, site_version, status, message, menu.
 */
function memorial_plugin_repo_plan() {
	$rows = array();
	foreach ( memorial_plugin_repo_files() as $path => $f ) {
		$h   = $f['head'];
		$row = array(
			'id'           => '' === $path ? '/' : $path,
			'path'         => '/' . ( '' === $path ? '' : $path . '/' ),
			'file'         => $f['file'],
			'title'        => (string) ( $h['title'] ?? '' ),
			'version'      => (int) ( $h['version'] ?? 0 ),
			'page'         => 0,
			'site_version' => 0,
			'status'       => 'error',
			'message'      => $f['error'],
			'menu'         => '',
			'edit'         => '',
			'view'         => '',
		);
		if ( '' === $f['error'] ) {
			$row = memorial_plugin_repo_plan_row( $path, $f, $row );
		}
		$rows[] = $row;
	}
	return $rows;
}

/**
 * The plan of one file.
 *
 * @param string $path Path.
 * @param array  $f    File.
 * @param array  $row  Row to fill.
 * @return array
 */
function memorial_plugin_repo_plan_row( $path, $f, $row ) {
	$h  = $f['head'];
	$id = memorial_plugin_repo_page_id( $path );
	if ( ! $id && '' !== (string) $h['parent'] && ! memorial_plugin_repo_page_id( $h['parent'] ) && ! isset( memorial_plugin_repo_files()[ trim( $h['parent'], '/' ) ] ) ) {
		$row['status'] = 'error';
		/* translators: %s: address of the parent page. */
		$row['message'] = sprintf( __( 'There is no parent page /%s/.', 'memorial-plugin' ), trim( $h['parent'], '/' ) );
		return $row;
	}
	$row['menu'] = memorial_plugin_repo_menu_state( $h['menu'], $id, $row['path'] );
	if ( ! $id ) {
		$row['status']  = 'create';
		$row['message'] = __( 'The page will be created.', 'memorial-plugin' );
		return $row;
	}
	$row['page']         = $id;
	$row['edit']         = (string) get_edit_post_link( $id, 'raw' );
	$row['view']         = (string) get_permalink( $id );
	$row['site_version'] = (int) get_post_meta( $id, '_mem_repo_version', true );
	$stored              = (string) get_post_meta( $id, '_mem_repo_hash', true );
	$skip                = (int) get_post_meta( $id, '_mem_repo_keep', true );
	$now                 = memorial_plugin_repo_hash( get_post_field( 'post_content', $id ) );
	if ( '' === $stored ) {
		// A page made before the module (home page, cremation, wreaths…): only through the screen.
		if ( $skip >= $row['version'] ) {
			$row['status']  = 'kept';
			$row['message'] = __( 'You chose to keep the text of the site for this version of the file.', 'memorial-plugin' );
		} else {
			$row['status']  = 'legacy';
			$row['message'] = __( 'The page was made before the import. Look at the difference and choose.', 'memorial-plugin' );
		}
		return $row;
	}
	if ( $row['version'] <= $row['site_version'] ) {
		$row['status']  = 'same';
		$row['message'] = $now === $stored ? __( 'Up to date.', 'memorial-plugin' ) : __( 'Up to date; the text was edited on the site after the import.', 'memorial-plugin' );
		return $row;
	}
	if ( $now === $stored ) {
		$row['status']  = 'update';
		$row['message'] = __( 'The page will be updated (a revision is kept).', 'memorial-plugin' );
	} elseif ( $skip >= $row['version'] ) {
		$row['status']  = 'kept';
		$row['message'] = __( 'You chose to keep the text of the site for this version of the file.', 'memorial-plugin' );
	} else {
		$row['status']  = 'conflict';
		$row['message'] = __( 'The text was edited on the site after the import. Look at the difference and choose.', 'memorial-plugin' );
	}
	return $row;
}

/**
 * Writes the plan: creates and updates; conflicts and old pages — only by a choice.
 *
 * @param array<string,string> $choices Path => 'repo' (take the file) | 'site' (keep the site text).
 * @param string               $how     'screen' | 'auto'.
 * @return array<int,array> Journal entries of this run.
 */
function memorial_plugin_repo_apply( $choices = array(), $how = 'screen' ) {
	$files   = memorial_plugin_repo_files();
	$done    = array();
	$retry   = array();
	$changed = false;
	// Parents first: «ritualnye-uslugi» before «ritualnye-uslugi/katafalk».
	uksort(
		$files,
		static function ( $a, $b ) {
			$depth = substr_count( $a, '/' ) <=> substr_count( $b, '/' );
			return 0 !== $depth ? $depth : strcmp( $a, $b );
		}
	);
	foreach ( $files as $path => $f ) {
		$key = '' === $path ? '/' : $path;
		if ( '' !== $f['error'] ) {
			$done[] = memorial_plugin_repo_entry( $key, 'error', $f['error'] );
			continue;
		}
		$row    = memorial_plugin_repo_plan_row(
			$path,
			$f,
			array(
				'version'      => (int) $f['head']['version'],
				'path'         => '/' . ( '' === $path ? '' : $path . '/' ),
				'page'         => 0,
				'site_version' => 0,
				'status'       => '',
				'message'      => '',
				'menu'         => '',
			)
		);
		$choice = (string) ( $choices[ $key ] ?? '' );
		$status = $row['status'];
		if ( in_array( $status, array( 'conflict', 'legacy', 'kept' ), true ) && 'site' === $choice ) {
			update_post_meta( $row['page'], '_mem_repo_keep', (int) $f['head']['version'] );
			$done[] = memorial_plugin_repo_entry( $key, 'kept', __( 'The text of the site is kept.', 'memorial-plugin' ), $row['page'] );
			continue;
		}
		$write = 'create' === $status || 'update' === $status || ( in_array( $status, array( 'conflict', 'legacy', 'kept' ), true ) && 'repo' === $choice );
		if ( 'error' === $status ) {
			$done[] = memorial_plugin_repo_entry( $key, 'error', $row['message'] );
			continue;
		}
		$ok = true;
		if ( $write ) {
			$missing = array();
			$result  = memorial_plugin_repo_write( $path, $f, $row['page'], $missing );
			$done[]  = $result;
			$changed = true;
			$ok      = 'error' !== $result['action'];
			if ( $missing ) {
				$retry[ $path ] = $result['page'];
			}
		}
		if ( $ok && is_array( $f['head']['menu'] ) ) {
			$page = memorial_plugin_repo_page_id( $path );
			if ( $page ) {
				$menu = memorial_plugin_repo_menu_add( $f['head']['menu'], $page, $row['path'] );
				if ( $menu ) {
					$done[]  = memorial_plugin_repo_entry( $key, 'menu', $menu, $page );
					$changed = true;
				}
			}
		}
	}
	// Links to pages created later in the same run: once more, now that they exist.
	foreach ( $retry as $path => $page ) {
		$missing = array();
		$content = memorial_plugin_repo_content( $files[ $path ]['body'], $missing );
		if ( $page && memorial_plugin_repo_hash( $content ) !== memorial_plugin_repo_hash( get_post_field( 'post_content', $page ) ) ) {
			memorial_plugin_repo_save_content( $page, $content );
		}
		foreach ( array_unique( $missing ) as $m ) {
			/* translators: %s: address of a page. */
			$done[] = memorial_plugin_repo_entry( '' === $path ? '/' : $path, 'warning', sprintf( __( 'A link leads to %s — there is no such page yet.', 'memorial-plugin' ), '/' . trim( $m, '/' ) . '/' ), $page );
		}
	}
	if ( $changed ) {
		memorial_plugin_repo_after();
	}
	memorial_plugin_repo_log_add( $done, $how );
	update_option( 'memorial_plugin_repo_ver', MEMORIAL_PLUGIN_REPO_VER, false );
	return $done;
}

/**
 * Creates or updates one page from its file.
 *
 * @param string $path    Path.
 * @param array  $f       File.
 * @param int    $id      Existing page (0 — create).
 * @param array  $missing Receives addresses of pages that do not exist yet.
 * @return array Journal entry.
 */
function memorial_plugin_repo_write( $path, $f, $id, &$missing ) {
	$h       = $f['head'];
	$key     = '' === $path ? '/' : $path;
	$content = memorial_plugin_repo_content( $f['body'], $missing );
	$parent  = '' === trim( (string) $h['parent'], '/' ) ? 0 : memorial_plugin_repo_page_id( $h['parent'] );
	$status  = in_array( $h['status'], array( 'draft', 'publish' ), true ) ? $h['status'] : 'draft';
	$before  = 0;
	$existed = (bool) $id;
	if ( $id ) {
		// The text before the import stays as a revision (Pages → the page → Revisions).
		$before = (int) wp_save_post_revision( $id );
		$res    = wp_update_post(
			wp_slash(
				array(
					'ID'           => $id,
					'post_title'   => (string) $h['title'],
					'post_content' => $content,
					'post_status'  => '' === $path ? get_post_status( $id ) : $status,
				)
			),
			true
		);
	} else {
		$res = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_title'   => (string) $h['title'],
					'post_name'    => (string) $h['slug'],
					'post_parent'  => $parent,
					'post_status'  => $status,
					'post_content' => $content,
				)
			),
			true
		);
	}
	if ( is_wp_error( $res ) ) {
		return memorial_plugin_repo_entry( $key, 'error', $res->get_error_message(), $id );
	}
	$id = (int) $res;
	if ( '' !== (string) $h['template'] ) {
		update_post_meta( $id, '_wp_page_template', sanitize_file_name( (string) $h['template'] ) );
	}
	if ( '' !== (string) $h['seo_title'] ) {
		update_post_meta( $id, '_seopress_titles_title', (string) $h['seo_title'] );
	}
	if ( '' !== (string) $h['seo_description'] ) {
		update_post_meta( $id, '_seopress_titles_desc', (string) $h['seo_description'] );
	}
	if ( '' !== (string) $h['featured_image'] ) {
		$img = memorial_plugin_repo_attachment( (string) $h['featured_image'] );
		if ( $img ) {
			set_post_thumbnail( $id, $img );
		}
	}
	update_post_meta( $id, '_mem_repo_version', (int) $h['version'] );
	update_post_meta( $id, '_mem_repo_hash', memorial_plugin_repo_hash( get_post_field( 'post_content', $id, 'raw' ) ) );
	update_post_meta( $id, '_mem_repo_file', (string) $f['file'] );
	delete_post_meta( $id, '_mem_repo_keep' );
	$msg = $before
		/* translators: 1: file version, 2: revision ID. */
		? sprintf( __( 'Updated to version %1$d; the previous text is revision %2$d.', 'memorial-plugin' ), (int) $h['version'], $before )
		/* translators: %d: file version. */
		: sprintf( __( 'Written, version %d.', 'memorial-plugin' ), (int) $h['version'] );
	return memorial_plugin_repo_entry( $key, $existed ? 'updated' : 'created', $msg, $id, $before );
}

/**
 * New text of a page that the import made (links resolved on a second pass); the hash follows.
 *
 * @param int    $id      Page.
 * @param string $content Text.
 */
function memorial_plugin_repo_save_content( $id, $content ) {
	wp_update_post(
		wp_slash(
			array(
				'ID'           => $id,
				'post_content' => $content,
			)
		)
	);
	update_post_meta( $id, '_mem_repo_hash', memorial_plugin_repo_hash( get_post_field( 'post_content', $id, 'raw' ) ) );
}

/**
 * An image of the media library by its file name.
 *
 * @param string $name «katafalk-ford-transit.jpg».
 * @return int
 */
function memorial_plugin_repo_attachment( $name ) {
	global $wpdb;
	$name = sanitize_file_name( $name );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one lookup by the file name while importing.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND ( meta_value = %s OR meta_value LIKE %s ) LIMIT 1", $name, '%/' . $wpdb->esc_like( $name ) ) );
}

/**
 * A journal entry.
 *
 * @param string $path     Path.
 * @param string $action   created | updated | kept | menu | warning | error.
 * @param string $message  What happened.
 * @param int    $page     Page.
 * @param int    $revision Revision with the text before.
 * @return array
 */
function memorial_plugin_repo_entry( $path, $action, $message, $page = 0, $revision = 0 ) {
	return array(
		'path'     => (string) $path,
		'action'   => $action,
		'message'  => (string) $message,
		'page'     => (int) $page,
		'revision' => (int) $revision,
	);
}

/**
 * Adds entries to the journal (the latest 200 are kept).
 *
 * @param array  $entries Entries.
 * @param string $how     'screen' | 'auto'.
 */
function memorial_plugin_repo_log_add( $entries, $how ) {
	if ( ! $entries ) {
		return;
	}
	$log  = (array) get_option( 'memorial_plugin_repo_log', array() );
	$time = time();
	$user = wp_get_current_user()->display_name;
	foreach ( $entries as $e ) {
		$log[] = $e + array(
			'time' => $time,
			'user' => $user,
			'how'  => $how,
		);
	}
	update_option( 'memorial_plugin_repo_log', array_slice( $log, -200 ), false );
}

/**
 * After writing: page cache, questions of the “Questions and answers” hub, the menu.
 */
function memorial_plugin_repo_after() {
	if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
		memorial_plugin_page_cache_clear();
	}
	if ( function_exists( 'memorial_plugin_faq_flush' ) ) {
		memorial_plugin_faq_flush();
	}
	delete_transient( 'memorial_plugin_menu_urls' );
	if ( function_exists( 'memorial_plugin_menu_items' ) ) {
		memorial_plugin_menu_items( true );
	}
	// SEOPress builds its XML sitemap on request: a new page is there at once.
	do_action( 'memorial_plugin_repo_imported' );
}

/* ---------- Меню ---------- */

/**
 * An address in one form for comparing: /path/.
 *
 * @param string $url Address or path.
 * @return string
 */
function memorial_plugin_repo_norm( $url ) {
	$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== trim( $home, '/' ) && 0 === strpos( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) - 1 );
	}
	$path = trim( rawurldecode( $path ), '/' );
	return '' === $path ? '/' : '/' . $path . '/';
}

/**
 * Address of a menu item.
 *
 * @param array $a Attributes of a navigation link or submenu.
 * @return string
 */
function memorial_plugin_repo_item_path( $a ) {
	$id = (int) ( $a['id'] ?? 0 );
	if ( $id && 'post-type' === ( $a['kind'] ?? '' ) && get_post_status( $id ) ) {
		return memorial_plugin_repo_norm( (string) get_permalink( $id ) );
	}
	return memorial_plugin_repo_norm( (string) ( $a['url'] ?? '' ) );
}

/**
 * Is the page in the menu already, and can it be added? '' — no menu in the file.
 *
 * @param array|null $menu Menu of the file.
 * @param int        $id   Page (0 — not yet).
 * @param string     $path /path/ of the page.
 * @return string has | add | error text
 */
function memorial_plugin_repo_menu_state( $menu, $id, $path ) {
	if ( ! is_array( $menu ) ) {
		return '';
	}
	$nav = get_post( function_exists( 'memorial_plugin_main_nav_id' ) ? memorial_plugin_main_nav_id() : 0 );
	if ( ! $nav instanceof WP_Post ) {
		return __( 'no main menu', 'memorial-plugin' );
	}
	$blocks = parse_blocks( (string) $nav->post_content );
	if ( memorial_plugin_repo_menu_find( $blocks, $id, $path ) ) {
		return 'has';
	}
	$parent = memorial_plugin_repo_norm( (string) ( $menu['parent'] ?? '/' ) );
	if ( '/' !== $parent && ! memorial_plugin_repo_menu_parent( $blocks, $parent ) ) {
		/* translators: %s: address of the parent menu item. */
		return sprintf( __( 'no menu item %s', 'memorial-plugin' ), $parent );
	}
	return 'add';
}

/**
 * Is there a menu item of the page (by ID or address)?
 *
 * @param array  $blocks Blocks of the menu.
 * @param int    $id     Page.
 * @param string $path   /path/.
 * @return bool
 */
function memorial_plugin_repo_menu_find( $blocks, $id, $path ) {
	foreach ( $blocks as $b ) {
		if ( in_array( $b['blockName'] ?? '', array( 'core/navigation-link', 'core/navigation-submenu' ), true ) ) {
			$a = (array) $b['attrs'];
			if ( ( $id && (int) ( $a['id'] ?? 0 ) === $id ) || memorial_plugin_repo_item_path( $a ) === $path ) {
				return true;
			}
		}
		if ( ! empty( $b['innerBlocks'] ) && memorial_plugin_repo_menu_find( $b['innerBlocks'], $id, $path ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Is there a menu item with this address (a future parent)?
 *
 * @param array  $blocks Blocks of the menu.
 * @param string $path   /path/.
 * @return bool
 */
function memorial_plugin_repo_menu_parent( $blocks, $path ) {
	return memorial_plugin_repo_menu_find( $blocks, 0, $path );
}

/**
 * Adds the page to the main menu (only adds; nothing is removed or renamed).
 *
 * @param array  $menu { parent, label, after } of the file.
 * @param int    $id   Page.
 * @param string $path /path/ of the page.
 * @return string What was done ('' — nothing).
 */
function memorial_plugin_repo_menu_add( $menu, $id, $path ) {
	$nav_id = function_exists( 'memorial_plugin_main_nav_id' ) ? memorial_plugin_main_nav_id() : 0;
	$nav    = get_post( $nav_id );
	if ( ! $nav instanceof WP_Post ) {
		return '';
	}
	$blocks = parse_blocks( (string) $nav->post_content );
	if ( memorial_plugin_repo_menu_find( $blocks, $id, $path ) ) {
		return '';
	}
	$parent = memorial_plugin_repo_norm( (string) ( $menu['parent'] ?? '/' ) );
	$after  = isset( $menu['after'] ) && '' !== (string) $menu['after'] ? memorial_plugin_repo_norm( (string) $menu['after'] ) : '';
	$link   = array(
		'blockName'    => 'core/navigation-link',
		'attrs'        => array(
			'label' => (string) ( $menu['label'] ?? get_the_title( $id ) ),
			'type'  => 'page',
			'id'    => $id,
			'url'   => (string) get_permalink( $id ),
			'kind'  => 'post-type',
		),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
	$put    = static function ( $items ) use ( $link, $after ) {
		$out = array();
		$in  = false;
		foreach ( $items as $b ) {
			$out[] = $b;
			if ( ! $in && '' !== $after && in_array( $b['blockName'] ?? '', array( 'core/navigation-link', 'core/navigation-submenu' ), true ) && memorial_plugin_repo_item_path( (array) $b['attrs'] ) === $after ) {
				$out[] = $link;
				$in    = true;
			}
		}
		if ( ! $in ) {
			$out[] = $link;
		}
		return $out;
	};
	$added = false;
	if ( '/' === $parent ) {
		$blocks = $put( $blocks );
		$added  = true;
	} else {
		$walk   = static function ( $items ) use ( &$walk, $parent, $put, &$added ) {
			foreach ( $items as &$b ) {
				if ( $added ) {
					break;
				}
				$name = $b['blockName'] ?? '';
				if ( in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu' ), true ) && memorial_plugin_repo_item_path( (array) $b['attrs'] ) === $parent ) {
					// A plain link becomes a submenu with the same label and address.
					$b['blockName']    = 'core/navigation-submenu';
					$b['innerBlocks']  = $put( (array) ( $b['innerBlocks'] ?? array() ) );
					$b['innerContent'] = array_fill( 0, count( $b['innerBlocks'] ), null );
					$b['innerHTML']    = '';
					$added             = true;
					break;
				}
				if ( ! empty( $b['innerBlocks'] ) ) {
					$b['innerBlocks'] = $walk( $b['innerBlocks'] );
					if ( $added ) {
						$b['innerContent'] = memorial_plugin_repo_inner_content( $b );
					}
				}
			}
			unset( $b );
			return $items;
		};
		$blocks = $walk( $blocks );
	}
	if ( ! $added ) {
		return '';
	}
	wp_update_post(
		wp_slash(
			array(
				'ID'           => $nav_id,
				'post_content' => serialize_blocks( $blocks ),
			)
		)
	);
	/* translators: 1: menu label, 2: address of the parent item. */
	return sprintf( __( 'Menu item “%1$s” added under %2$s.', 'memorial-plugin' ), $link['attrs']['label'], '/' === $parent ? __( 'the top level', 'memorial-plugin' ) : $parent );
}

/**
 * The innerContent of a block whose inner blocks changed in number: the HTML around stays, one place per block.
 *
 * @param array $b Block.
 * @return array
 */
function memorial_plugin_repo_inner_content( $b ) {
	$html  = array_values( array_filter( (array) $b['innerContent'], 'is_string' ) );
	$first = $html[0] ?? '';
	$last  = count( $html ) > 1 ? end( $html ) : '';
	return array_merge( array( $first ), array_fill( 0, count( $b['innerBlocks'] ), null ), array( $last ) );
}

/* ---------- Разница ---------- */

/**
 * Difference between the text on the site and the file (core wp_text_diff, plain text of the blocks).
 *
 * @param string $path Path ('/' — the front page).
 * @return array{title:string,html:string}
 */
function memorial_plugin_repo_diff( $path ) {
	$path  = '/' === $path ? '' : trim( $path, '/' );
	$files = memorial_plugin_repo_files();
	if ( ! isset( $files[ $path ] ) || '' !== $files[ $path ]['error'] ) {
		return array(
			'title' => '',
			'html'  => '',
		);
	}
	$id   = memorial_plugin_repo_page_id( $path );
	$site = $id ? (string) get_post_field( 'post_content', $id ) : '';
	$repo = memorial_plugin_repo_content( $files[ $path ]['body'] );
	if ( ! function_exists( 'wp_text_diff' ) ) {
		require_once ABSPATH . WPINC . '/pluggable.php';
	}
	$html = wp_text_diff(
		memorial_plugin_repo_plain( $site ),
		memorial_plugin_repo_plain( $repo ),
		array(
			'title_left'  => __( 'On the site now', 'memorial-plugin' ),
			'title_right' => __( 'In the file', 'memorial-plugin' ),
		)
	);
	return array(
		'title' => (string) ( $files[ $path ]['head']['title'] ?? '' ),
		'html'  => '' === $html ? '<p>' . esc_html__( 'The texts are the same.', 'memorial-plugin' ) . '</p>' : $html,
	);
}

/**
 * A text for the difference: one line per block, block names kept so the structure is visible.
 *
 * @param string $content post_content.
 * @return string
 */
function memorial_plugin_repo_plain( $content ) {
	$content = preg_replace( '/<!--\s+(wp:[^\s]+)[^>]*?-->/', "\n[$1]\n", (string) $content );
	$content = preg_replace( '/<!--\s+\/wp:[^\s]+\s+-->/', "\n", $content );
	$content = wp_strip_all_tags( str_replace( array( '</p>', '</li>', '</h2>', '</h3>', '<br>' ), "\n", $content ) );
	return trim( preg_replace( "/\n\s*\n+/", "\n", html_entity_decode( $content, ENT_QUOTES, 'UTF-8' ) ) );
}

/* ---------- Запуск ---------- */

/**
 * Fingerprint of the files (path, version, size): the import runs by itself only when it changes.
 *
 * @return string
 */
function memorial_plugin_repo_fingerprint() {
	$parts = array();
	foreach ( memorial_plugin_repo_files() as $path => $f ) {
		$parts[] = $path . ':' . ( $f['head']['version'] ?? 0 ) . ':' . md5( $f['body'] );
	}
	return md5( implode( '|', $parts ) );
}

/*
 * После деплоя: при заходе администратора в админку — создать и обновить нетронутые страницы.
 * Конфликты и старые страницы ждут решения на экране.
 */
add_action(
	'admin_init',
	static function () {
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! current_user_can( 'manage_options' ) || ! is_dir( memorial_plugin_repo_dir() ) ) {
			return;
		}
		$print = memorial_plugin_repo_fingerprint();
		if ( get_option( 'memorial_plugin_repo_seen' ) === $print ) {
			return;
		}
		// The mark first, so a failed run does not repeat on every screen.
		update_option( 'memorial_plugin_repo_seen', $print, false );
		memorial_plugin_repo_apply( array(), 'auto' );
	},
	30
);

/* Напоминание: есть страницы, которые ждут решения. */
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || ! is_dir( memorial_plugin_repo_dir() ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, 'mem-repo-pages' ) ) {
			return;
		}
		$waiting = count( array_filter( memorial_plugin_repo_plan(), static fn( $r ) => in_array( $r['status'], array( 'conflict', 'legacy', 'error' ), true ) ) );
		if ( ! $waiting ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of pages. */
					_n( 'Pages from the repository: %d page waits for your decision.', 'Pages from the repository: %d pages wait for your decision.', $waiting, 'memorial-plugin' ),
					$waiting
				)
			),
			esc_url( admin_url( 'admin.php?page=mem-repo-pages' ) ),
			esc_html__( 'Open', 'memorial-plugin' )
		);
	}
);

/* Экран «Мемориал → Страницы из репозитория» (src/catalog/repo-pages.js). */
add_action(
	'admin_menu',
	static function () {
		$hook = add_submenu_page(
			'mem-contacts',
			__( 'Pages from the repository', 'memorial-plugin' ),
			__( 'Pages from the repository', 'memorial-plugin' ),
			'manage_options',
			'mem-repo-pages',
			static function () {
				echo '<div id="memorial-plugin-catalog" class="wrap memorial-plugin-admin" data-screen="repo-pages"></div>';
			},
			90
		);
		if ( $hook && function_exists( 'memorial_plugin_catalog_views_assets' ) ) {
			add_action( 'load-' . $hook, 'memorial_plugin_catalog_views_assets' );
		}
	},
	30
);

/**
 * Data of the screen.
 *
 * @return array
 */
function memorial_plugin_repo_screen_data() {
	$log = array_reverse( (array) get_option( 'memorial_plugin_repo_log', array() ) );
	foreach ( $log as $i => &$e ) {
		$e['id'] = $i + 1;
	}
	unset( $e );
	return array(
		'items' => memorial_plugin_repo_plan(),
		'log'   => array_slice( $log, 0, 100 ),
		'dir'   => 'memorial-plugin/content/pages',
	);
}

add_action(
	'rest_api_init',
	static function () {
		$can = static fn() => current_user_can( 'manage_options' );
		register_rest_route(
			'memorial-plugin/v1',
			'/repo-pages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => $can,
					'callback'            => static fn() => rest_ensure_response( memorial_plugin_repo_screen_data() ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can,
					'args'                => array(
						'choices' => array(
							'type'                 => 'object',
							'default'              => array(),
							'additionalProperties' => array(
								'type' => 'string',
								'enum' => array( 'repo', 'site' ),
							),
						),
					),
					'callback'            => static function ( WP_REST_Request $request ) {
						$done = memorial_plugin_repo_apply( (array) $request['choices'], 'screen' );
						update_option( 'memorial_plugin_repo_seen', memorial_plugin_repo_fingerprint(), false );
						return rest_ensure_response( memorial_plugin_repo_screen_data() + array( 'done' => $done ) );
					},
				),
			)
		);
		register_rest_route(
			'memorial-plugin/v1',
			'/repo-pages/diff',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'args'                => array(
					'path' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'callback'            => static fn( WP_REST_Request $request ) => rest_ensure_response( memorial_plugin_repo_diff( (string) $request['path'] ) ),
			)
		);
	}
);
