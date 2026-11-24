<?php
/**
 * BLOCKS.md — the guide to the plugin blocks — stays in step with the blocks (without WordPress).
 *
 *   php tools/blocks-doc.php build  — rewrite the generated lines of BLOCKS.md (attributes from block.json);
 *   php tools/blocks-doc.php check  — every block of memorial-plugin/blocks/types has its section, there is
 *                                     no section of a removed block, the generated lines are up to date (CI).
 *
 * A section of a block starts with a heading that has the block name in backticks:
 *   ### Оплата — `memorial-plugin/payment`
 * and holds one generated line between the marks <!-- auto:payment --> and <!-- /auto -->.
 * A new block: add its section by hand (what it shows, data, settings, where it stands), then `build`.
 *
 * @package memorial-plugin
 */

// phpcs:disable -- a command-line tool, not a part of the site.

$root  = dirname( __DIR__ );
$doc   = $root . '/BLOCKS.md';
$types = $root . '/memorial-plugin/blocks/types';
$mode  = $argv[1] ?? 'check';

if ( ! in_array( $mode, array( 'build', 'check' ), true ) ) {
	fwrite( STDERR, "php tools/blocks-doc.php build|check\n" );
	exit( 2 );
}

// Blocks and their attributes from block.json.
$blocks = array();
foreach ( glob( $types . '/*/block.json' ) as $file ) {
	$json = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $json ) || empty( $json['name'] ) ) {
		fwrite( STDERR, "Не читается: $file\n" );
		exit( 1 );
	}
	$blocks[ substr( $json['name'], strlen( 'memorial-plugin/' ) ) ] = $json;
}
ksort( $blocks );

/**
 * The generated line of a block: its attributes with defaults and the style settings it has.
 *
 * @param array $json block.json.
 * @return string
 */
function memorial_blocks_doc_line( $json ) {
	$attrs = array();
	foreach ( (array) ( $json['attributes'] ?? array() ) as $name => $def ) {
		if ( 'editorCollapsed' === $name ) {
			continue;
		}
		$default = $def['default'] ?? null;
		if ( is_bool( $default ) ) {
			$default = $default ? 'да' : 'нет';
		} elseif ( null === $default || '' === $default ) {
			$default = '—';
		} elseif ( is_array( $default ) ) {
			$default = json_encode( $default, JSON_UNESCAPED_UNICODE );
		}
		$enum    = isset( $def['enum'] ) ? ' из ' . implode( ' / ', $def['enum'] ) : '';
		$attrs[] = '`' . $name . '` = ' . $default . $enum;
	}
	$supports = $json['supports'] ?? array();
	$style    = array();
	foreach ( array(
		'color'                => 'цвет',
		'typography'           => 'шрифт',
		'spacing'              => 'отступы',
		'__experimentalBorder' => 'рамка',
	) as $key => $label ) {
		if ( ! empty( $supports[ $key ] ) ) {
			$style[] = $label;
		}
	}
	$inserter = array_key_exists( 'inserter', $supports ) && false === $supports['inserter'] ? ' · в списке блоков не показывается' : '';
	return 'Атрибуты: ' . ( $attrs ? implode( '; ', $attrs ) : 'нет' )
		. ' · Оформление в редакторе: ' . ( $style ? implode( ', ', $style ) : 'нет' ) . $inserter . '.';
}

if ( ! is_readable( $doc ) ) {
	fwrite( STDERR, "Нет BLOCKS.md\n" );
	exit( 1 );
}
$text   = (string) file_get_contents( $doc );
$errors = array();

// Sections in the guide.
preg_match_all( '/^###\s.*`memorial-plugin\/([a-z0-9-]+)`/m', $text, $m );
$documented = $m[1];
foreach ( array_keys( $blocks ) as $slug ) {
	if ( ! in_array( $slug, $documented, true ) ) {
		$errors[] = "Блока memorial-plugin/$slug нет в BLOCKS.md — добавьте раздел (заголовок ### … `memorial-plugin/$slug` и строку <!-- auto:$slug --><!-- /auto -->).";
	}
}
foreach ( array_unique( $documented ) as $slug ) {
	if ( ! isset( $blocks[ $slug ] ) ) {
		$errors[] = "В BLOCKS.md есть раздел memorial-plugin/$slug, а такого блока нет — уберите раздел или перенесите в «Запланировано».";
	}
}
foreach ( array_count_values( $documented ) as $slug => $n ) {
	if ( $n > 1 ) {
		$errors[] = "Раздел memorial-plugin/$slug в BLOCKS.md встречается $n раза.";
	}
}

// Generated lines.
$next = preg_replace_callback(
	'/<!-- auto:([a-z0-9-]+) -->.*?<!-- \/auto -->/s',
	static function ( $mm ) use ( $blocks, &$errors ) {
		if ( ! isset( $blocks[ $mm[1] ] ) ) {
			$errors[] = "Строка <!-- auto:{$mm[1]} --> — такого блока нет.";
			return $mm[0];
		}
		return '<!-- auto:' . $mm[1] . ' -->' . memorial_blocks_doc_line( $blocks[ $mm[1] ] ) . '<!-- /auto -->';
	},
	$text
);
foreach ( $documented as $slug ) {
	if ( isset( $blocks[ $slug ] ) && false === strpos( $text, '<!-- auto:' . $slug . ' -->' ) ) {
		$errors[] = "У раздела memorial-plugin/$slug нет строки <!-- auto:$slug --><!-- /auto -->.";
	}
}

if ( 'build' === $mode ) {
	if ( $next !== $text ) {
		file_put_contents( $doc, $next );
		echo "BLOCKS.md: строки атрибутов обновлены\n";
	}
} elseif ( $next !== $text ) {
	$errors[] = 'Строки атрибутов в BLOCKS.md устарели (атрибуты блоков менялись) — php tools/blocks-doc.php build.';
}

if ( $errors ) {
	fwrite( STDERR, implode( "\n", $errors ) . "\n" );
	exit( 1 );
}
echo 'BLOCKS.md: ' . count( $blocks ) . " блоков, всё на месте\n";
