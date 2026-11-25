<?php
/**
 * Проверка файлов страниц (memorial-plugin/content/pages/**.html) перед слиянием — без WordPress.
 *
 *   php tools/content-lint.php                 — все файлы страниц;
 *   php tools/content-lint.php a.html b.html   — только эти.
 *
 * Ошибка — CI падает; предупреждение — только сообщение. Что проверяется (ТЗ «Блоки и доработки», раздел 2):
 * заголовок memorial-page и его поля; длина SEO-заголовка и описания в знаках и пикселях; разрешённые блоки;
 * H1, цены цифрами, неизвестные коды {price:…}; запрещённые слова (tools/content-stoplist.txt); внутренние
 * ссылки; транспорт на странице груза 200; повторы H2.
 *
 * Данные сайта (коды цен, адреса страниц и разделов каталога) — tools/content-site.json. Его обновляет
 * GitHub Actions «Данные сайта для проверки текстов» (push ветки sitedata/<ветка>), только чтение с сайта.
 *
 * @package memorial-plugin
 */

// phpcs:disable -- a command-line tool, not a part of the site.

const MEM_LINT_ALLOWED = array(
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
const MEM_LINT_REQUIRED = array( 'slug', 'parent', 'title', 'seo_title', 'seo_description', 'status', 'version' );
// %%mem_код%% в SEO считается как «135 000 ₽» (ТЗ, раздел 2).
const MEM_LINT_VAR_AS = '135 000 ₽';

$root      = dirname( __DIR__ );
$pages     = $root . '/memorial-plugin/content/pages';
$site_file = $root . '/tools/content-site.json';
$files     = array();
// --pages=<папка> и --site=<файл> — для проверки самой проверки (tests/smoke/content-lint.php).
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--pages=' ) ) {
		$pages = realpath( substr( $arg, 8 ) );
	} elseif ( 0 === strpos( $arg, '--site=' ) ) {
		$site_file = substr( $arg, 7 );
	} else {
		$files[] = realpath( $arg ) ?: $arg;
	}
}
if ( ! $files ) {
	$files = is_dir( $pages ) ? memorial_lint_find( $pages ) : array();
}
if ( ! $files ) {
	echo "Файлов страниц нет — проверять нечего.\n";
	exit( 0 );
}

$site      = is_readable( $site_file ) ? json_decode( (string) file_get_contents( $site_file ), true ) : null;
$widths    = json_decode( (string) file_get_contents( $root . '/tools/content-arial-widths.json' ), true )['widths'] ?? array();
$stoplist  = memorial_lint_stoplist( $root . '/tools/content-stoplist.txt' );

$errors   = array();
$warnings = array();
$err      = static function ( $file, $msg ) use ( &$errors ) {
	$errors[] = array( $file, $msg );
};
$warn     = static function ( $file, $msg ) use ( &$warnings ) {
	$warnings[] = array( $file, $msg );
};

if ( ! is_array( $site ) ) {
	$warn( 'tools/content-site.json', 'Нет данных сайта: коды {price:…} и внутренние ссылки не проверены. Обновить: git push origin <ветка>:sitedata/<ветка>.' );
	$site = array();
}
$codes = array_map( 'mb_strtolower', (array) ( $site['codes'] ?? array() ) );

// Все страницы из репозитория (не только проверяемые) — для ссылок и повторов H2.
$all    = is_dir( $pages ) ? memorial_lint_find( $pages ) : array();
$parsed = array();
foreach ( array_unique( array_merge( $all, $files ) ) as $f ) {
	$parsed[ $f ] = memorial_lint_parse( $f, $pages );
}

// Адреса, которые уже есть: страницы сайта, страницы из файлов, разделы каталога.
$known = array( '/' => true );
foreach ( (array) ( $site['pages'] ?? array() ) as $p ) {
	$known[ memorial_lint_norm( is_array( $p ) ? $p[0] : $p ) ] = true;
}
foreach ( (array) ( $site['terms'] ?? array() ) as $t ) {
	$known[ memorial_lint_norm( $t ) ] = true;
}
foreach ( $parsed as $p ) {
	if ( $p['path'] ) {
		$known[ $p['path'] ] = true;
	}
}
$product_base = memorial_lint_norm( (string) ( $site['product_base'] ?? '' ) );

foreach ( $files as $f ) {
	$p   = $parsed[ $f ];
	$rel = 0 === strpos( $f, $root . '/' ) ? substr( $f, strlen( $root ) + 1 ) : $f;
	if ( $p['fatal'] ) {
		$err( $rel, $p['fatal'] );
		continue;
	}
	$h = $p['head'];

	// Заголовок файла.
	foreach ( MEM_LINT_REQUIRED as $k ) {
		if ( ! array_key_exists( $k, $h ) ) {
			$err( $rel, "В заголовке memorial-page нет поля «{$k}»." );
		}
	}
	if ( isset( $h['slug'] ) && ! preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) $h['slug'] ) ) {
		$err( $rel, 'slug — только латиница в нижнем регистре, цифры и дефис: «' . $h['slug'] . '».' );
	}
	if ( isset( $h['parent'] ) && ! preg_match( '#^([a-z0-9-]+(/[a-z0-9-]+)*)?$#', (string) $h['parent'] ) ) {
		$err( $rel, 'parent — путь родителя латиницей без слешей по краям («ritualnye-uslugi») или пусто.' );
	}
	if ( isset( $h['status'] ) && ! in_array( $h['status'], array( 'draft', 'publish' ), true ) ) {
		$err( $rel, 'status — draft или publish.' );
	}
	if ( isset( $h['version'] ) && ( ! is_int( $h['version'] ) || $h['version'] < 1 ) ) {
		$err( $rel, 'version — целое число от 1, растёт при каждой правке файла.' );
	}
	if ( ! $p['is_front'] && isset( $h['slug'], $h['parent'] ) ) {
		$expect = trim( ( '' !== (string) $h['parent'] ? $h['parent'] . '/' : '' ) . $h['slug'], '/' );
		if ( $expect !== $p['file_path'] ) {
			$err( $rel, "Путь файла не совпадает с адресом из заголовка: файл «{$p['file_path']}.html», адрес «{$expect}/»." );
		}
	}
	if ( array_key_exists( 'menu', $h ) && null !== $h['menu'] ) {
		if ( ! is_array( $h['menu'] ) || empty( $h['menu']['parent'] ) || empty( $h['menu']['label'] ) ) {
			$err( $rel, 'menu — null или { "parent": "/…/", "label": "…", "after": "/…/" }.' );
		}
	}

	// SEO: знаки и пиксели (Arial 20 px — заголовок, 14 px — описание).
	foreach ( array(
		'seo_title'       => array( 60, 568, 20, 55, 'SEO-заголовок' ),
		'seo_description' => array( 160, 940, 14, 130, 'SEO-описание' ),
	) as $k => list( $max, $px_max, $size, $soft, $label ) ) {
		if ( ! isset( $h[ $k ] ) ) {
			continue;
		}
		$text = memorial_lint_seo_text( (string) $h[ $k ] );
		$len  = mb_strlen( $text );
		$px   = memorial_lint_px( $text, $size, $widths );
		if ( '' === trim( $text ) ) {
			$err( $rel, "{$label} пустой." );
		} elseif ( $len > $max || $px > $px_max ) {
			$err( $rel, sprintf( '%s длиннее нормы: %d знаков из %d, %d px из %d — «%s».', $label, $len, $max, $px, $px_max, $text ) );
		} elseif ( $len > $soft ) {
			$warn( $rel, sprintf( '%s %d знаков (лучше до %d): может обрезаться.', $label, $len, $soft ) );
		}
		if ( preg_match_all( '/%%mem_([a-z0-9_]+)%%/', (string) $h[ $k ], $m ) ) {
			foreach ( $m[1] as $code ) {
				if ( $codes && ! in_array( $code, $codes, true ) && ! in_array( $code, array( 'phone', 'years', 'tel' ), true ) ) {
					$warn( $rel, "%%mem_{$code}%% — такого кода нет в «Мемориал → Цены → Отдельные цены» (или у него нет галочки «В SEO-плагин»)." );
				}
			}
		}
	}

	// Блоки. Главная (index.html) — исключение для H1: шаблон front-page.html заголовок записи не выводит,
	// H1 стоит в первом экране текста — ровно один.
	$h1_blocks = 0;
	foreach ( $p['blocks'] as $b ) {
		$name = $b['name'];
		if ( 0 !== strpos( $name, 'memorial-plugin/' ) && ! in_array( $name, MEM_LINT_ALLOWED, true ) ) {
			$err( $rel, "Блок {$name} не из списка разрешённых." );
		}
		if ( 'core/heading' === $name ) {
			$level = (int) ( $b['attrs']['level'] ?? 2 );
			if ( 1 === $level ) {
				++$h1_blocks;
				if ( ! $p['is_front'] ) {
					$err( $rel, 'H1 в тексте: заголовок страницы (H1) выводит шаблон из поля title.' );
				}
			} elseif ( ! in_array( $level, array( 2, 3 ), true ) ) {
				$err( $rel, "Заголовок H{$level}: в текстах только H2 и H3." );
			}
		}
		if ( 'memorial-plugin/vehicle' === $name && false !== strpos( $p['path'], 'gruz-200' ) ) {
			$err( $rel, 'Блок «Ритуальный транспорт» на странице груза 200: машины для перевозки бывают разные.' );
		}
		// Адреса страниц в атрибутах (импорт заменит их на ID).
		$links = array();
		if ( in_array( $name, array( 'memorial-plugin/cards', 'memorial-plugin/guide' ), true ) ) {
			foreach ( array( 'pages', 'parent' ) as $a ) {
				if ( isset( $b['attrs'][ $a ] ) && is_string( $b['attrs'][ $a ] ) ) {
					$links = array_merge( $links, array_filter( array_map( 'trim', explode( ',', $b['attrs'][ $a ] ) ) ) );
				}
			}
		}
		if ( 'memorial-plugin/price-list' === $name ) {
			foreach ( (array) ( $b['attrs']['rows'] ?? array() ) as $row ) {
				if ( isset( $row['page'] ) && is_string( $row['page'] ) && '' !== $row['page'] ) {
					$links[] = $row['page'];
				}
				if ( isset( $row['code'] ) && $codes && ! in_array( mb_strtolower( (string) $row['code'] ), $codes, true ) ) {
					$warn( $rel, "«Цены по отдельности»: кода «{$row['code']}» нет в «Мемориал → Цены» на сайте — строка не выведется, пока код не появится." );
				}
			}
		}
		if ( 'memorial-plugin/link-button' === $name ) {
			foreach ( array( 'page', 'url' ) as $a ) {
				if ( isset( $b['attrs'][ $a ] ) && is_string( $b['attrs'][ $a ] ) && '' !== $b['attrs'][ $a ] ) {
					$links[] = $b['attrs'][ $a ];
				}
			}
			if ( isset( $b['attrs']['page'] ) && is_int( $b['attrs']['page'] ) && $b['attrs']['page'] > 0 ) {
				$err( $rel, 'link-button: страница указана числом (ID). В файлах — адресом: "page":"/ceny/". ID на копии и на сайте разные.' );
			}
		}
		foreach ( array( 'memorial-plugin/cards' => 'pages', 'memorial-plugin/guide' => 'pages' ) as $bn => $a ) {
			if ( $name === $bn && isset( $b['attrs'][ $a ] ) && preg_match( '/^\s*\d+(\s*,\s*\d+)*\s*$/', (string) $b['attrs'][ $a ] ) ) {
				$err( $rel, "{$bn}: страницы указаны ID. В файлах — адресами: \"pages\":\"/ritualnye-uslugi/traurnyj-zal/,/ceny/\"." );
			}
		}
		foreach ( $links as $l ) {
			memorial_lint_link( $rel, $l, $known, $product_base, $site, $err );
		}
	}
	if ( preg_match( '/ford\s+transit/iu', $p['text'] ) && false !== strpos( $p['path'], 'gruz-200' ) ) {
		$err( $rel, '«Ford Transit» на странице груза 200: машины для перевозки бывают разные.' );
	}

	// Текст.
	$h1_tags = preg_match_all( '/<h1[\s>]/i', $p['html'] );
	if ( ! $p['is_front'] && $h1_tags ) {
		$err( $rel, 'В тексте есть <h1>: H1 выводит шаблон из поля title.' );
	} elseif ( $p['is_front'] && ( 1 !== $h1_tags || 1 !== $h1_blocks ) ) {
		$err( $rel, "Главная: нужен ровно один H1 — блок «Заголовок» уровня 1 в первом экране (шаблон front-page.html заголовок записи не выводит). Сейчас: блоков H1 — {$h1_blocks}, тегов <h1> — {$h1_tags}." );
	}
	if ( preg_match_all( '/\d[\d\s\x{00A0}\x{202F}]*(?:[.,]\d+)?\s*(?:₽|руб)/iu', $p['text'], $m ) ) {
		$err( $rel, 'Цена цифрами: «' . implode( '», «', array_unique( array_map( 'trim', $m[0] ) ) ) . '». Цены — только кодами {price:код}.' );
	} elseif ( preg_match( '/(?<!\pL)руб/iu', $p['text'] ) ) {
		$err( $rel, '«руб» в тексте: цены — только кодами {price:код}.' );
	}
	// Site values inserted in the editor: <span class="memorial-plugin-value" data-value="price" data-atts="{&quot;id&quot;:&quot;код&quot;}">.
	if ( preg_match_all( '/data-value="price"[^>]*data-atts="[^"]*?(?:&quot;|")id(?:&quot;|"):(?:&quot;|")([^&"]+)/u', $p['html'], $mv ) ) {
		foreach ( array_unique( $mv[1] ) as $code ) {
			if ( $codes && ! in_array( mb_strtolower( trim( $code ) ), $codes, true ) ) {
				$err( $rel, "Неизвестный код цены «{$code}» в «Значении сайта» — его нет в «Мемориал → Цены» на сайте." );
			}
		}
	}
	if ( preg_match_all( '/\{price:([^}]+)\}/u', $p['html'] . ' ' . wp_json_like( $p['blocks'] ), $m ) ) {
		foreach ( array_unique( $m[1] ) as $code ) {
			if ( $codes && ! in_array( mb_strtolower( trim( $code ) ), $codes, true ) ) {
				$err( $rel, "Неизвестный код {price:{$code}} — его нет в «Мемориал → Цены» на сайте." );
			}
		}
	}
	$check = $p['text'] . "\n" . ( $h['title'] ?? '' ) . "\n" . ( $h['seo_title'] ?? '' ) . "\n" . ( $h['seo_description'] ?? '' );
	foreach ( $stoplist as $s ) {
		if ( in_array( $p['path_bare'], $s['except'], true ) ) {
			continue;
		}
		if ( preg_match( $s['re'], memorial_lint_yo( $check ), $m ) ) {
			$err( $rel, "Запрещено: «{$m[0]}» — {$s['why']} (tools/content-stoplist.txt)." );
		}
	}
	// Ссылки в тексте.
	if ( preg_match_all( '/\shref="([^"]+)"/i', $p['html'], $m ) ) {
		foreach ( $m[1] as $l ) {
			memorial_lint_link( $rel, html_entity_decode( $l ), $known, $product_base, $site, $err );
		}
	}
}

// Повторы H2 между страницами.
$h2 = array();
foreach ( $parsed as $f => $p ) {
	foreach ( $p['h2'] as $t ) {
		$h2[ mb_strtolower( $t ) ][ $p['path_bare'] ] = $f;
	}
}
$checked = array_flip( $files );
foreach ( $h2 as $t => $where ) {
	if ( count( $where ) < 2 ) {
		continue;
	}
	$names = array_keys( $where );
	$pair  = (bool) array_filter( $names, static fn( $n ) => false !== strpos( $n, 'eksgumaciya' ) ) && (bool) array_filter( $names, static fn( $n ) => false !== strpos( $n, 'perezahoronenie' ) );
	foreach ( $where as $path => $f ) {
		if ( ! isset( $checked[ $f ] ) ) {
			continue;
		}
		$rel = 0 === strpos( $f, $root . '/' ) ? substr( $f, strlen( $root ) + 1 ) : $f;
		$msg = "H2 «{$t}» повторяется на страницах: /" . implode( '/, /', $names ) . '/.';
		if ( $pair ) {
			$err( $rel, $msg . ' Эксгумация и перезахоронение — разные H2.' );
		} else {
			$warn( $rel, $msg );
		}
	}
}

// Вывод.
$gha = getenv( 'GITHUB_ACTIONS' );
foreach ( array( 'warning' => $warnings, 'error' => $errors ) as $kind => $list ) {
	foreach ( $list as list( $file, $msg ) ) {
		if ( $gha ) {
			echo "::{$kind} file={$file}::" . str_replace( array( "\n", '%' ), array( ' ', '%25' ), $msg ) . "\n";
		}
		fwrite( 'error' === $kind ? STDERR : STDOUT, ( 'error' === $kind ? 'ОШИБКА ' : 'внимание ' ) . "{$file}: {$msg}\n" );
	}
}
printf( "Проверено файлов: %d; ошибок: %d; предупреждений: %d.\n", count( $files ), count( $errors ), count( $warnings ) );
exit( $errors ? 1 : 0 );

/* ---------- Функции ---------- */

/**
 * All page files under a folder.
 *
 * @param string $dir Folder.
 * @return string[]
 */
function memorial_lint_find( $dir ) {
	$out = array();
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
		if ( 'html' === $f->getExtension() ) {
			$out[] = $f->getPathname();
		}
	}
	sort( $out );
	return $out;
}

/**
 * A page file: header, blocks, html, text, H2, address.
 *
 * @param string $file  File.
 * @param string $pages Folder of the page files.
 * @return array
 */
function memorial_lint_parse( $file, $pages ) {
	$raw       = (string) file_get_contents( $file );
	$file_path = 0 === strpos( $file, $pages . '/' ) ? substr( $file, strlen( $pages ) + 1, -5 ) : basename( $file, '.html' );
	$is_front  = 'index' === $file_path;
	$out       = array(
		'fatal'     => '',
		'head'      => array(),
		'blocks'    => array(),
		'html'      => '',
		'text'      => '',
		'h2'        => array(),
		'file_path' => $file_path,
		'is_front'  => $is_front,
		'path'      => $is_front ? '/' : '/' . $file_path . '/',
		'path_bare' => $is_front ? '' : $file_path,
	);
	if ( ! preg_match( '/^\s*<!--\s*memorial-page\s*(\{.*?\})\s*-->/s', $raw, $m ) ) {
		$out['fatal'] = 'Нет заголовка в первой строке: <!-- memorial-page { … } -->.';
		return $out;
	}
	$head = json_decode( $m[1], true );
	if ( ! is_array( $head ) ) {
		$out['fatal'] = 'Заголовок memorial-page — не JSON: ' . json_last_error_msg() . '.';
		return $out;
	}
	$out['head'] = $head;
	$body        = substr( $raw, strlen( $m[0] ) );
	// Blocks as WordPress stores them: <!-- wp:name {attrs} /--> or <!-- wp:name {attrs} --> … <!-- /wp:name -->.
	preg_match_all( '/<!--\s+(\/)?wp:([a-z][a-z0-9_-]*\/)?([a-z][a-z0-9_-]*)\s+(\{(?:(?!-->).)*?\}\s+)?(\/)?-->/s', $body, $mm, PREG_SET_ORDER );
	foreach ( $mm as $b ) {
		if ( '/' === $b[1] ) {
			continue;
		}
		$attrs = isset( $b[4] ) && '' !== trim( $b[4] ) ? json_decode( trim( $b[4] ), true ) : array();
		if ( null === $attrs ) {
			$out['fatal'] = 'Атрибуты блока ' . $b[2] . $b[3] . ' — не JSON.';
			return $out;
		}
		$out['blocks'][] = array(
			'name'  => ( '' !== $b[2] ? $b[2] : 'core/' ) . $b[3],
			'attrs' => (array) $attrs,
		);
	}
	// core/shortcode: only [mem_…].
	if ( preg_match_all( '/<!--\s+wp:shortcode\s+-->(.*?)<!--\s+\/wp:shortcode\s+-->/s', $body, $sc ) ) {
		foreach ( $sc[1] as $code ) {
			if ( ! preg_match( '/^\s*\[mem_[a-z_]+/', $code ) ) {
				$out['blocks'][] = array(
					'name'  => 'shortcode: ' . trim( mb_substr( $code, 0, 40 ) ),
					'attrs' => array(),
				);
			}
		}
	}
	$html        = preg_replace( '/<!--.*?-->/s', '', $body );
	$out['html'] = $html;
	$out['text'] = html_entity_decode( trim( preg_replace( '/\s+/u', ' ', strip_tags( str_replace( '<', ' <', $html ) ) ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $html, $hm ) ) {
		$out['h2'] = array_map( static fn( $t ) => trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $t ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ), $hm[1] );
	}
	return $out;
}

/**
 * Attributes of the blocks as one string (to find {price:…} in them).
 *
 * @param array $blocks Blocks.
 * @return string
 */
function wp_json_like( $blocks ) {
	return (string) json_encode( array_column( $blocks, 'attrs' ), JSON_UNESCAPED_UNICODE );
}

/**
 * SEO text as the snippet shows it: %%mem_…%% → «135 000 ₽», the site name, separators.
 *
 * @param string $s Text with variables.
 * @return string
 */
function memorial_lint_seo_text( $s ) {
	$s = preg_replace( '/%%mem_[a-z0-9_]+%%/', MEM_LINT_VAR_AS, $s );
	$s = str_replace( array( '%%sitetitle%%', '%%sitename%%', '%%sep%%' ), array( 'Мемориал', 'Мемориал', '—' ), $s );
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/**
 * Width of a text in Arial, px.
 *
 * @param string $s      Text.
 * @param int    $size   Font size, px.
 * @param array  $widths Char => width (share of the size).
 * @return int
 */
function memorial_lint_px( $s, $size, $widths ) {
	$w = 0.0;
	foreach ( preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		$w += $widths[ $ch ] ?? 0.6;
	}
	return (int) ceil( $w * $size );
}

/**
 * Stop list: [re, why, except].
 *
 * @param string $file tools/content-stoplist.txt.
 * @return array
 */
function memorial_lint_stoplist( $file ) {
	$out = array();
	foreach ( file( $file, FILE_IGNORE_NEW_LINES ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || '#' === $line[0] ) {
			continue;
		}
		$parts  = array_map( 'trim', explode( ' | ', $line ) );
		$what   = $parts[0];
		$why    = $parts[1] ?? '';
		$except = array();
		foreach ( array_slice( $parts, 2 ) as $x ) {
			if ( preg_match( '/^кроме:\s*(.+)$/u', $x, $m ) ) {
				$except = array_map( static fn( $p ) => trim( $p, " /" ), explode( ',', $m[1] ) );
			}
		}
		$re    = '/' === $what[0] ? $what : '/(?<!\pL)' . preg_quote( memorial_lint_yo( $what ), '/' ) . '(?!\pL)/iu';
		$out[] = array(
			're'     => $re,
			'why'    => $why,
			'except' => $except,
		);
	}
	return $out;
}

/**
 * «ё» as «е» for the search.
 *
 * @param string $s Text.
 * @return string
 */
function memorial_lint_yo( $s ) {
	return str_replace( array( 'ё', 'Ё' ), array( 'е', 'Е' ), $s );
}

/**
 * An address of the site in one form: /path/ (no domain, query, anchor).
 *
 * @param string $url Address.
 * @return string
 */
function memorial_lint_norm( $url ) {
	$url = preg_replace( '#^https?://(www\.)?(memorialbel\.ru|memorialbel\.test-handyhost\.ru)#i', '', trim( $url ) );
	$url = preg_replace( '/[?#].*$/', '', $url );
	if ( '' === $url ) {
		return '/';
	}
	$url = '/' . trim( rawurldecode( $url ), '/' );
	return preg_match( '/\.[a-z0-9]{2,5}$/i', $url ) ? $url : rtrim( $url, '/' ) . '/';
}

/**
 * Does an internal link lead to a page that exists (on the site copy or among the page files)?
 *
 * @param string   $rel          File.
 * @param string   $url          Link.
 * @param array    $known        Known addresses.
 * @param string   $product_base Address of the catalog (products are under it).
 * @param array    $site         Site data.
 * @param callable $err          Error reporter.
 */
function memorial_lint_link( $rel, $url, $known, $product_base, $site, $err ) {
	$url = trim( $url );
	if ( '' === $url || preg_match( '/^(#|tel:|mailto:|sms:|\{)/i', $url ) ) {
		return;
	}
	$internal = '/' === $url[0] || preg_match( '#^https?://(www\.)?memorialbel\.#i', $url );
	if ( ! $internal ) {
		return;
	}
	$path = memorial_lint_norm( $url );
	if ( isset( $known[ $path ] ) || 0 === strpos( $path, '/wp-content/' ) ) {
		return;
	}
	if ( '/' !== $product_base && '' !== $product_base && 0 === strpos( $path, $product_base ) ) {
		return;
	}
	if ( ! $site ) {
		return;
	}
	$err( $rel, "Ссылка на несуществующий адрес {$path} — такой страницы нет ни на сайте, ни среди файлов страниц." );
}
