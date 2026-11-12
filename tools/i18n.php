<?php
/**
 * Translations of the plugin and the theme without WP-CLI.
 *
 *   php tools/i18n.php pot     — languages/<domain>.pot for the plugin and the theme (PHP, JS, headers, theme.json);
 *   php tools/i18n.php build   — languages/<domain>-<locale>.mo from every .po, and for the plugin also
 *                                <domain>-<locale>-<script handle>.json for its admin scripts;
 *   php tools/i18n.php check   — every string of the .pot is translated in every .po and the compiled files are fresh.
 *
 * The .po files are the source of the translations; edit them (Poedit or by hand) and run “build”.
 * CI also compares the .pot with what `wp i18n make-pot` finds.
 *
 * @package memorial-plugin
 */

// phpcs:disable -- a command-line tool, not a part of the site.

const MEMORIAL_I18N_PROJECTS = [
	'memorial-plugin' => [ 'dir' => 'memorial-plugin', 'header' => 'memorial-plugin.php', 'js' => [ 'src' ] ],
	'memorial-theme'  => [ 'dir' => 'memorial-theme', 'header' => 'style.css', 'js' => [] ],
];
// Admin scripts of the plugin that call wp_set_script_translations() (handle => source folder).
const MEMORIAL_I18N_SCRIPTS = [
	'memorial-plugin-catalog'      => 'catalog',
	'memorial-plugin-settings'     => 'settings',
	'memorial-plugin-collections'  => 'collections',
	'memorial-plugin-commands'     => 'commands',
	'memorial-plugin-convert'      => 'convert',
	'memorial-plugin-editor-meta'  => 'editor-meta',
	'memorial-plugin-inline-value' => 'inline-value',
	'memorial-plugin-block-controls' => 'block-controls',
];
const MEMORIAL_I18N_FUNCS = [
	// name => [ argument roles ]: s — singular, p — plural, c — context, d — domain, n — number (skipped)
	'__' => 'sd', '_e' => 'sd', 'esc_html__' => 'sd', 'esc_html_e' => 'sd', 'esc_attr__' => 'sd', 'esc_attr_e' => 'sd',
	'_x' => 'scd', '_ex' => 'scd', 'esc_html_x' => 'scd', 'esc_attr_x' => 'scd',
	'_n' => 'spnd', '_nx' => 'spncd', '_n_noop' => 'spd', '_nx_noop' => 'spcd',
];
const MEMORIAL_I18N_RU_PLURAL = 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);';

$root = dirname( __DIR__ );
$cmd  = $argv[1] ?? 'check';
$fail = 0;

// compare <domain> <file.pot made by `wp i18n make-pot`>: every string WP-CLI finds is in our .pot
if ( 'compare' === $cmd ) {
	$domain = $argv[2];
	$ours   = memorial_i18n_read_po( $root . '/' . MEMORIAL_I18N_PROJECTS[ $domain ]['dir'] . "/languages/$domain.pot" );
	$theirs = memorial_i18n_read_po( $argv[3] );
	$diff   = array_diff( array_keys( $theirs ), array_keys( $ours ) );
	foreach ( $diff as $key ) {
		echo "::error::$domain: WP-CLI finds “" . str_replace( "\4", ' | ', $key ) . "”, it is missing in languages/$domain.pot\n";
	}
	echo count( $theirs ) - 1 . " strings found by WP-CLI, missing in ours: " . count( $diff ) . "\n";
	exit( $diff ? 1 : 0 );
}

foreach ( MEMORIAL_I18N_PROJECTS as $domain => $project ) {
	$dir  = $root . '/' . $project['dir'];
	$lang = $dir . '/languages';
	if ( 'pot' === $cmd ) {
		@mkdir( $lang );
		$entries = memorial_i18n_extract( $dir, $domain, $project );
		file_put_contents( "$lang/$domain.pot", memorial_i18n_po( $entries, $domain, null ) );
		echo "$domain.pot: " . count( $entries ) . " strings\n";
		continue;
	}
	$pot = memorial_i18n_read_po( "$lang/$domain.pot" );
	foreach ( glob( "$lang/$domain-*.po" ) as $po_file ) {
		$locale = substr( basename( $po_file, '.po' ), strlen( $domain ) + 1 );
		$po     = memorial_i18n_read_po( $po_file );
		if ( 'build' === $cmd ) {
			file_put_contents( "$lang/$domain-$locale.mo", memorial_i18n_mo( $po, $po['']['Plural-Forms'] ?? '' ) );
			if ( 'memorial-plugin' === $domain ) {
				memorial_i18n_json( $dir, $lang, $domain, $locale, $po );
			}
			echo basename( $po_file ) . ": compiled\n";
			continue;
		}
		// check
		$missing = [];
		foreach ( $pot as $key => $entry ) {
			if ( '' === $key ) {
				continue;
			}
			$tr = $po[ $key ]['msgstr'] ?? [];
			if ( ! $tr || in_array( '', $tr, true ) ) {
				$missing[] = str_replace( "\4", ' | ', $key );
			}
		}
		if ( $missing ) {
			$fail = 1;
			echo "::error::" . basename( $po_file ) . ': ' . count( $missing ) . " strings are not translated:\n  " . implode( "\n  ", array_slice( $missing, 0, 40 ) ) . "\n";
		}
		$mo = "$lang/$domain-$locale.mo";
		if ( ! is_file( $mo ) || memorial_i18n_mo( $po, $po['']['Plural-Forms'] ?? '' ) !== file_get_contents( $mo ) ) {
			$fail = 1;
			echo "::error::" . basename( $mo ) . " is not built from the .po — run: php tools/i18n.php build\n";
		}
		if ( ! $missing ) {
			echo basename( $po_file ) . ': all ' . ( count( $pot ) - 1 ) . " strings are translated\n";
		}
	}
	if ( 'check' === $cmd ) {
		$fresh = memorial_i18n_extract( $dir, $domain, $project );
		$diff  = array_diff( array_keys( $fresh ), array_keys( $pot ) );
		if ( $diff ) {
			$fail = 1;
			echo "::error::$domain.pot is out of date (" . count( $diff ) . " new strings, for example “" . str_replace( "\4", ' | ', reset( $diff ) ) . "”) — run: php tools/i18n.php pot\n";
		}
	}
}
exit( $fail );

/* ---------- Extraction ---------- */

function memorial_i18n_extract( $dir, $domain, $project ) {
	$entries = [];
	$add     = static function ( $msgid, $ctx, $plural, $ref, $comment ) use ( &$entries ) {
		if ( '' === $msgid ) {
			return;
		}
		$key = ( null !== $ctx ? $ctx . "\4" : '' ) . $msgid;
		$e   = $entries[ $key ] ?? [ 'msgid' => $msgid, 'msgctxt' => $ctx, 'plural' => $plural, 'refs' => [], 'comments' => [] ];
		$e['refs'][] = $ref;
		if ( $comment && ! in_array( $comment, $e['comments'], true ) ) {
			$e['comments'][] = $comment;
		}
		if ( $plural ) {
			$e['plural'] = $plural;
		}
		$entries[ $key ] = $e;
	};
	// Headers.
	$file = "$dir/{$project['header']}";
	$head = file_get_contents( $file, false, null, 0, 8192 );
	foreach ( [ 'Plugin Name', 'Plugin URI', 'Theme Name', 'Theme URI', 'Description', 'Author', 'Author URI' ] as $field ) {
		if ( preg_match( '/^[\s\*#@]*' . $field . ':(.*)$/mi', $head, $m ) ) {
			$add( trim( $m[1] ), null, null, basename( $file ), $field . ' of the ' . ( str_ends_with( $file, '.css' ) ? 'theme' : 'plugin' ) );
		}
	}
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $f ) {
		$path = $f->getPathname();
		$rel  = substr( $path, strlen( $dir ) + 1 );
		if ( preg_match( '#(^|/)(vendor|node_modules|build|tests|languages)/#', $rel ) ) {
			continue;
		}
		if ( str_ends_with( $path, '.php' ) ) {
			memorial_i18n_php( file_get_contents( $path ), $rel, $domain, $add );
			if ( str_starts_with( $rel, 'patterns/' ) ) {
				$src = file_get_contents( $path );
				foreach ( [ 'Title' => 'Pattern title', 'Description' => 'Pattern description' ] as $field => $ctx ) {
					if ( preg_match( '/^\s*\*\s*' . $field . ':(.*)$/m', $src, $m ) ) {
						$add( trim( $m[1] ), $ctx, null, $rel, null );
					}
				}
				if ( preg_match( '/^\s*\*\s*Keywords:(.*)$/m', $src, $m ) ) {
					foreach ( array_filter( array_map( 'trim', explode( ',', $m[1] ) ) ) as $keyword ) {
						$add( $keyword, 'Pattern keyword', null, $rel, null );
					}
				}
			}
		} elseif ( str_ends_with( $path, '.js' ) && ! str_ends_with( $path, '.min.js' ) ) {
			foreach ( $project['js'] as $js_dir ) {
				if ( str_starts_with( $rel, $js_dir . '/' ) ) {
					memorial_i18n_js( file_get_contents( $path ), $rel, $domain, $add );
				}
			}
		} elseif ( str_ends_with( $rel, '/block.json' ) ) {
			// As WordPress translates block.json: title, description, keywords with their contexts.
			$block = json_decode( file_get_contents( $path ), true );
			if ( ! empty( $block['title'] ) ) {
				$add( $block['title'], 'block title', null, $rel, null );
			}
			if ( ! empty( $block['description'] ) ) {
				$add( $block['description'], 'block description', null, $rel, null );
			}
			foreach ( (array) ( $block['keywords'] ?? [] ) as $keyword ) {
				$add( $keyword, 'block keyword', null, $rel, null );
			}
		} elseif ( preg_match( '#^(theme\.json|styles/[^/]+\.json)$#', $rel ) ) {
			memorial_i18n_theme_json( json_decode( file_get_contents( $path ), true ), $rel, $add );
		}
	}
	ksort( $entries, SORT_STRING );
	return $entries;
}

function memorial_i18n_theme_json( $data, $rel, $add ) {
	if ( ! empty( $data['title'] ) ) {
		$add( $data['title'], 'Style variation name', null, $rel, null );
	}
	$s    = $data['settings'] ?? [];
	$list = [
		'Color name'       => $s['color']['palette'] ?? [],
		'Gradient name'    => $s['color']['gradients'] ?? [],
		'Font family name' => $s['typography']['fontFamilies'] ?? [],
		'Font size name'   => $s['typography']['fontSizes'] ?? [],
		'Shadow name'      => $s['shadow']['presets'] ?? [],
		'Space size name'  => $s['spacing']['spacingSizes'] ?? [],
	];
	foreach ( $list as $ctx => $items ) {
		foreach ( $items as $item ) {
			if ( ! empty( $item['name'] ) ) {
				$add( $item['name'], $ctx, null, $rel, null );
			}
		}
	}
	foreach ( $data['templateParts'] ?? [] as $part ) {
		if ( ! empty( $part['title'] ) ) {
			$add( $part['title'], 'Template part name', null, $rel, null );
		}
	}
	foreach ( $data['customTemplates'] ?? [] as $tpl ) {
		if ( ! empty( $tpl['title'] ) ) {
			$add( $tpl['title'], 'Custom template name', null, $rel, null );
		}
	}
}

function memorial_i18n_php( $src, $rel, $domain, $add ) {
	$toks    = token_get_all( $src );
	$n       = count( $toks );
	$comment = null;
	for ( $i = 0; $i < $n; $i++ ) {
		$t = $toks[ $i ];
		if ( is_array( $t ) && in_array( $t[0], [ T_COMMENT, T_DOC_COMMENT ], true ) ) {
			if ( preg_match( '/translators:\s*(.*?)\s*(\*\/)?$/s', $t[1], $m ) ) {
				$comment = 'translators: ' . trim( preg_replace( '/\s*\n\s*\*?\s*/', ' ', $m[1] ) );
			}
			continue;
		}
		if ( ! is_array( $t ) || T_STRING !== $t[0] || ! isset( MEMORIAL_I18N_FUNCS[ $t[1] ] ) ) {
			if ( is_array( $t ) && T_WHITESPACE === $t[0] ) {
				continue;
			}
			if ( ';' === $t || '{' === $t || '}' === $t ) {
				$comment = null;
			}
			continue;
		}
		// the previous significant token must not be “->”, “::” or “function”
		$j = $i - 1;
		while ( $j >= 0 && is_array( $toks[ $j ] ) && T_WHITESPACE === $toks[ $j ][0] ) {
			$j--;
		}
		if ( $j >= 0 && is_array( $toks[ $j ] ) && in_array( $toks[ $j ][0], [ T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ], true ) ) {
			continue;
		}
		$k = $i + 1;
		while ( $k < $n && is_array( $toks[ $k ] ) && T_WHITESPACE === $toks[ $k ][0] ) {
			$k++;
		}
		if ( '(' !== ( $toks[ $k ] ?? '' ) ) {
			continue;
		}
		// arguments: only literal strings are taken; anything else ends the argument
		$args  = [];
		$cur   = null;
		$depth = 0;
		$plain = true;
		for ( $k++; $k < $n; $k++ ) {
			$a = $toks[ $k ];
			if ( '(' === $a || '[' === $a ) {
				$depth++;
				$plain = false;
				continue;
			}
			if ( ')' === $a || ']' === $a ) {
				if ( 0 === $depth ) {
					$args[] = $plain ? $cur : null;
					break;
				}
				$depth--;
				continue;
			}
			if ( ',' === $a && 0 === $depth ) {
				$args[] = $plain ? $cur : null;
				$cur    = null;
				$plain  = true;
				continue;
			}
			if ( 0 !== $depth ) {
				continue;
			}
			if ( is_array( $a ) && in_array( $a[0], [ T_WHITESPACE, T_COMMENT ], true ) ) {
				continue;
			}
			if ( is_array( $a ) && T_CONSTANT_ENCAPSED_STRING === $a[0] && null === $cur ) {
				$cur = memorial_i18n_php_string( $a[1] );
				continue;
			}
			$plain = false;
		}
		$roles = MEMORIAL_I18N_FUNCS[ $t[1] ];
		$vals  = [];
		foreach ( str_split( $roles ) as $idx => $role ) {
			$vals[ $role ] = $args[ $idx ] ?? null;
		}
		if ( ( $vals['d'] ?? null ) !== $domain || null === ( $vals['s'] ?? null ) ) {
			continue;
		}
		$add( $vals['s'], $vals['c'] ?? null, $vals['p'] ?? null, $rel . ':' . $t[2], $comment );
		$comment = null;
	}
}

function memorial_i18n_php_string( $lit ) {
	if ( "'" === $lit[0] ) {
		return str_replace( [ "\\'", '\\\\' ], [ "'", '\\' ], substr( $lit, 1, -1 ) );
	}
	return stripcslashes( substr( $lit, 1, -1 ) );
}

function memorial_i18n_js( $src, $rel, $domain, $add ) {
	// strings of the JS file are replaced by placeholders, so that the calls can be found by a simple pattern
	$strings = [];
	$code    = preg_replace_callback(
		'/\/\/[^\n]*|\/\*.*?\*\/|\'(?:\\\\.|[^\'\\\\\n])*\'|"(?:\\\\.|[^"\\\\\n])*"|`(?:\\\\.|[^`\\\\])*`/s',
		static function ( $m ) use ( &$strings ) {
			$s = $m[0];
			if ( str_starts_with( $s, '//' ) || str_starts_with( $s, '/*' ) ) {
				return preg_match( '/translators:/', $s ) ? '⟪C' . array_push( $strings, $s ) . '⟫' : str_repeat( ' ', 0 );
			}
			if ( '`' === $s[0] ) {
				return '⟪T⟫';
			}
			return '⟪S' . array_push( $strings, json_decode( '"' . str_replace( [ "\\'", '"' ], [ "'", '\\"' ], substr( $s, 1, -1 ) ) . '"' ) ?? substr( $s, 1, -1 ) ) . '⟫';
		},
		$src
	);
	$names = implode( '|', array_map( 'preg_quote', array_keys( MEMORIAL_I18N_FUNCS ) ) );
	preg_match_all( '/(?<![\w.$])(' . $names . ')\s*\(\s*((?:⟪[SCT]\d*⟫|[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/u', $code, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
	foreach ( $calls as $c ) {
		$func  = $c[1][0];
		$args  = preg_split( '/,(?![^(]*\))/', $c[2][0] );
		$roles = MEMORIAL_I18N_FUNCS[ $func ];
		$vals  = [];
		foreach ( str_split( $roles ) as $idx => $role ) {
			$arg = trim( $args[ $idx ] ?? '' );
			$vals[ $role ] = preg_match( '/^⟪S(\d+)⟫$/u', $arg, $m ) ? $strings[ $m[1] - 1 ] : null;
		}
		if ( ( $vals['d'] ?? null ) !== $domain || null === $vals['s'] ) {
			continue;
		}
		// translators comment right before the call
		$before  = substr( $code, max( 0, $c[0][1] - 400 ), min( 400, $c[0][1] ) );
		$comment = null;
		if ( preg_match( '/⟪C(\d+)⟫[\s(,]*$/u', $before, $m ) && preg_match( '/translators:\s*(.*?)\s*(\*\/)?$/s', $strings[ $m[1] - 1 ], $mm ) ) {
			$comment = 'translators: ' . trim( $mm[1] );
		}
		$line = substr_count( substr( $code, 0, $c[0][1] ), "\n" ) + 1;
		$add( $vals['s'], $vals['c'] ?? null, $vals['p'] ?? null, $rel . ':' . $line, $comment );
	}
}

/* ---------- .po / .pot ---------- */

function memorial_i18n_quote( $s ) {
	$s = addcslashes( $s, "\\\"\t" );
	$s = str_replace( "\n", "\\n", $s );
	return '"' . $s . '"';
}

function memorial_i18n_po( $entries, $domain, $locale, $translations = [] ) {
	$h  = "# Translation of $domain.\n# This file is distributed under the same license as the $domain package.\n";
	$h .= "msgid \"\"\nmsgstr \"\"\n";
	$head = [
		'Project-Id-Version' => $domain,
		'MIME-Version'       => '1.0',
		'Content-Type'       => 'text/plain; charset=UTF-8',
		'Content-Transfer-Encoding' => '8bit',
		'X-Domain'           => $domain,
	];
	if ( $locale ) {
		$head['Language']     = $locale;
		$head['Plural-Forms'] = MEMORIAL_I18N_RU_PLURAL;
	} else {
		$head['Plural-Forms'] = 'nplurals=INTEGER; plural=EXPRESSION;';
	}
	foreach ( $head as $k => $v ) {
		$h .= memorial_i18n_quote( "$k: $v\n" ) . "\n";
	}
	foreach ( $entries as $key => $e ) {
		$h .= "\n";
		foreach ( $e['comments'] as $c ) {
			$h .= "#. $c\n";
		}
		$h .= '#: ' . implode( ' ', array_slice( array_unique( $e['refs'] ), 0, 6 ) ) . "\n";
		if ( preg_match( '/%(\d\$)?[sdf]/', $e['msgid'] ) ) {
			$h .= "#, php-format\n";
		}
		if ( null !== $e['msgctxt'] ) {
			$h .= 'msgctxt ' . memorial_i18n_quote( $e['msgctxt'] ) . "\n";
		}
		$h .= 'msgid ' . memorial_i18n_quote( $e['msgid'] ) . "\n";
		$tr = $translations[ $key ] ?? null;
		if ( $e['plural'] ) {
			$h .= 'msgid_plural ' . memorial_i18n_quote( $e['plural'] ) . "\n";
			$n  = $locale ? 3 : 2;
			for ( $i = 0; $i < $n; $i++ ) {
				$h .= "msgstr[$i] " . memorial_i18n_quote( $tr[ $i ] ?? '' ) . "\n";
			}
		} else {
			$h .= 'msgstr ' . memorial_i18n_quote( $tr[0] ?? '' ) . "\n";
		}
	}
	return $h;
}

function memorial_i18n_read_po( $file ) {
	$out = [];
	if ( ! is_file( $file ) ) {
		return [ '' => [] ];
	}
	$cur  = null;
	$last = null;
	$flush = static function () use ( &$cur, &$out ) {
		if ( null === $cur ) {
			return;
		}
		$key = ( null !== ( $cur['msgctxt'] ?? null ) ? $cur['msgctxt'] . "\4" : '' ) . $cur['msgid'];
		if ( '' === $key ) {
			$head = [];
			foreach ( explode( "\n", $cur['msgstr'][0] ?? '' ) as $line ) {
				if ( str_contains( $line, ':' ) ) {
					[ $k, $v ] = explode( ':', $line, 2 );
					$head[ trim( $k ) ] = trim( $v );
				}
			}
			$out[''] = $head;
		} else {
			ksort( $cur['msgstr'] );
			$out[ $key ] = $cur;
		}
		$cur = null;
	};
	foreach ( file( $file, FILE_IGNORE_NEW_LINES ) as $line ) {
		if ( '' === trim( $line ) || '#' === $line[0] ) {
			continue;
		}
		if ( preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+"(.*)"$/', $line, $m ) ) {
			$val = stripcslashes( $m[3] );
			if ( 'msgctxt' === $m[1] || ( 'msgid' === $m[1] && ( null === $cur || isset( $cur['msgstr'] ) ) ) ) {
				if ( null !== $cur && isset( $cur['msgstr'] ) ) {
					$flush();
				}
			}
			$cur ??= [ 'msgctxt' => null, 'msgid' => null, 'plural' => null ];
			if ( 'msgctxt' === $m[1] ) {
				$cur['msgctxt'] = $val;
				$last = [ 'msgctxt' ];
			} elseif ( 'msgid' === $m[1] ) {
				$cur['msgid'] = $val;
				$last = [ 'msgid' ];
			} elseif ( 'msgid_plural' === $m[1] ) {
				$cur['plural'] = $val;
				$last = [ 'plural' ];
			} else {
				$idx = '' === ( $m[2] ?? '' ) ? 0 : (int) $m[2];
				$cur['msgstr'][ $idx ] = $val;
				$last = [ 'msgstr', $idx ];
			}
		} elseif ( preg_match( '/^"(.*)"$/', $line, $m ) && $last ) {
			$val = stripcslashes( $m[1] );
			if ( 'msgstr' === $last[0] ) {
				$cur['msgstr'][ $last[1] ] .= $val;
			} else {
				$cur[ $last[0] ] .= $val;
			}
		}
	}
	$flush();
	$out[''] ??= [];
	return $out;
}

/* ---------- .mo and JSON ---------- */

function memorial_i18n_mo( $po, $plural_forms ) {
	$items = [ '' => "Content-Type: text/plain; charset=UTF-8\nPlural-Forms: $plural_forms\n" ];
	foreach ( $po as $key => $e ) {
		if ( '' === $key || empty( $e['msgstr'] ) || in_array( '', $e['msgstr'], true ) ) {
			continue;
		}
		$orig           = $key . ( $e['plural'] ? "\0" . $e['plural'] : '' );
		$items[ $orig ] = implode( "\0", $e['msgstr'] );
	}
	ksort( $items, SORT_STRING );
	$n        = count( $items );
	$o_table  = '';
	$t_table  = '';
	$o_data   = '';
	$t_data   = '';
	$o_offset = 28 + 16 * $n;
	$keys     = array_keys( $items );
	$o_lens   = [];
	foreach ( $keys as $k ) {
		$o_lens[] = [ strlen( $k ), strlen( $o_data ) ];
		$o_data  .= $k . "\0";
	}
	$t_offset = $o_offset + strlen( $o_data );
	$t_lens   = [];
	foreach ( $keys as $k ) {
		$t_lens[] = [ strlen( $items[ $k ] ), strlen( $t_data ) ];
		$t_data  .= $items[ $k ] . "\0";
	}
	foreach ( $o_lens as [ $len, $off ] ) {
		$o_table .= pack( 'VV', $len, $o_offset + $off );
	}
	foreach ( $t_lens as [ $len, $off ] ) {
		$t_table .= pack( 'VV', $len, $t_offset + $off );
	}
	return pack( 'VVVVVVV', 0x950412de, 0, $n, 28, 28 + 8 * $n, 0, 0 ) . $o_table . $t_table . $o_data . $t_data;
}

function memorial_i18n_json( $dir, $lang, $domain, $locale, $po ) {
	// JS strings of every admin script: those found in its source folder and in src/shared
	$js = [];
	memorial_i18n_js_keys( $dir, $domain, $js );
	foreach ( MEMORIAL_I18N_SCRIPTS as $handle => $folder ) {
		$messages = [ '' => [ 'domain' => 'messages', 'plural-forms' => $po['']['Plural-Forms'] ?? '', 'lang' => $locale ] ];
		foreach ( $js as $key => $folders ) {
			if ( ! array_intersect( $folders, [ $folder, 'shared' ] ) || empty( $po[ $key ]['msgstr'] ) ) {
				continue;
			}
			$messages[ $key ] = array_values( $po[ $key ]['msgstr'] );
		}
		$json = [
			'domain'      => 'messages',
			'locale_data' => [ 'messages' => $messages ],
		];
		file_put_contents( "$lang/$domain-$locale-$handle.json", json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
	}
}

function memorial_i18n_js_keys( $dir, $domain, &$js ) {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$dir/src", FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $f ) {
		if ( ! str_ends_with( $f->getPathname(), '.js' ) ) {
			continue;
		}
		$rel    = substr( $f->getPathname(), strlen( "$dir/src/" ) );
		$folder = explode( '/', $rel )[0];
		memorial_i18n_js(
			file_get_contents( $f->getPathname() ),
			$rel,
			$domain,
			static function ( $msgid, $ctx ) use ( &$js, $folder ) {
				$key          = ( null !== $ctx ? $ctx . "\4" : '' ) . $msgid;
				$js[ $key ][] = $folder;
			}
		);
	}
}
