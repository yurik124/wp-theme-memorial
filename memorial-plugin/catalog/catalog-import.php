<?php
/**
 * Import of products from Excel (.xlsx) or CSV with photos (files or a ZIP opened by the browser).
 *
 * The way: the browser sends the table and the list of photo files it has (path, size, SHA-1) →
 * preview: what each row would do, nothing is written (the parsed table waits in a transient under a
 * token) → the browser uploads the photos that are needed, one per request → the rows are applied in
 * portions with the same function as the product window (memorial_plugin_product_save).
 *
 * Rules of a cell: empty — the field is not changed; “-” — the field is cleared. The SKU finds a product
 * to update; a row with an unknown or empty SKU makes a new product (hidden, unless “Publish at once”).
 * Photos: names from the “Photos” column — first a file sent with the import, then the media library by
 * name; with the column empty — the files in a folder named as the SKU (foto/M-1024/1.jpg). A file that
 * was imported before (same SHA-1) or has the same name and size as a photo of the library is not
 * uploaded again.
 *
 * Log of the last import (memorial_plugin_import_log, not autoloaded): created products, the values of
 * the updated ones and of their photos before the import — “Roll back the last import” restores them
 * (created products go to the trash, nothing is deleted).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Rows applied per request. */
const MEMORIAL_PLUGIN_IMPORT_PORTION = 10;

/** Largest table file, bytes. */
const MEMORIAL_PLUGIN_IMPORT_MAX_TABLE = 10485760;

/**
 * Text for comparing: lower case, “ё” as “е”, single spaces, without quotes.
 *
 * @param string $v Text.
 * @return string
 */
function memorial_plugin_import_norm( $v ) {
	$v = mb_strtolower( trim( (string) $v ) );
	$v = str_replace( array( 'ё', '«', '»', '“', '”', '"', "\u{00A0}" ), array( 'е', '', '', '', '', '', ' ' ), $v );
	return trim( preg_replace( '/\s+/u', ' ', $v ) );
}

/**
 * Headers the import understands: key => names (the header of the export, English, synonyms).
 *
 * @return array<string,string[]>
 */
function memorial_plugin_import_aliases() {
	$extra = array(
		'sku'    => array( 'sku', 'артикул', 'код', 'код товара' ),
		'type'   => array( 'type', 'тип', 'тип товара' ),
		'cats'   => array( 'categories', 'category', 'категории', 'категория' ),
		'title'  => array( 'title', 'name', 'название', 'наименование' ),
		'price'  => array( 'price', 'цена', 'цена, ₽', 'цена, руб' ),
		'from'   => array( 'price “from”', 'from', 'цена от', 'цена «от»', 'от' ),
		'old'    => array( 'old price', 'старая цена' ),
		'unit'   => array( 'unit', 'единица', 'ед.', 'ед. изм.' ),
		'sub'    => array( 'line under the title', 'строка под названием' ),
		'order'  => array( 'made to order', 'под заказ' ),
		'status' => array( 'on the site', 'на сайте', 'показывать', 'опубликовать' ),
		'desc'   => array( 'description', 'описание' ),
		'photos' => array( 'photos', 'photo', 'фото', 'фотографии', 'изображения' ),
		'alts'   => array( 'alt', 'альт', 'alt фото', 'описание фото для незрячих' ),
	);
	foreach ( memorial_plugin_io_columns() as $key => $header ) {
		$extra[ $key ][] = $header;
	}
	return array_map( static fn( $list ) => array_unique( array_map( 'memorial_plugin_import_norm', $list ) ), $extra );
}

/**
 * Columns of the file: column number => key (“spec:<name>” for a specification).
 *
 * @param string[] $head First row.
 * @return array{cols:array<int,string>,unknown:string[],labels:array<string,string>}
 */
function memorial_plugin_import_map( $head ) {
	$aliases = memorial_plugin_import_aliases();
	/* translators: %s: name of a specification. Only the part before %s is used to find such columns. */
	$spec_prefixes = array_unique( array( 'spec:', 'характеристика:', memorial_plugin_import_norm( strstr( __( 'Spec: %s', 'memorial-plugin' ), '%s', true ) ) ) );
	$cols          = array();
	$unknown       = array();
	$labels        = array();
	foreach ( $head as $i => $h ) {
		$h = trim( (string) $h );
		$n = memorial_plugin_import_norm( $h );
		if ( '' === $n ) {
			continue;
		}
		$key = '';
		foreach ( $aliases as $k => $names ) {
			if ( in_array( $n, $names, true ) ) {
				$key = $k;
				break;
			}
		}
		if ( '' === $key ) {
			foreach ( $spec_prefixes as $p ) {
				$p = rtrim( $p );
				if ( '' !== $p && str_starts_with( $n, $p ) ) {
					$label = trim( mb_substr( $h, mb_strlen( $p ) ) );
					$key   = '' !== $label ? 'spec:' . sanitize_text_field( $label ) : '';
					break;
				}
			}
		}
		if ( '' === $key || in_array( $key, $cols, true ) ) {
			$unknown[] = $h;
			continue;
		}
		$cols[ $i ]     = $key;
		$labels[ $key ] = $h;
	}
	return array(
		'cols'    => $cols,
		'unknown' => $unknown,
		'labels'  => $labels,
	);
}

/**
 * Read a table: rows of strings, the first is the header.
 *
 * @param string $path Temporary file.
 * @param string $name File name as sent (for the extension).
 * @return array<int,string[]>|WP_Error
 */
function memorial_plugin_import_read( $path, $name ) {
	$ext = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
	if ( ! is_readable( $path ) || filesize( $path ) > MEMORIAL_PLUGIN_IMPORT_MAX_TABLE ) {
		return new WP_Error( 'memorial_plugin_import_file', __( 'The table file could not be read or is larger than 10 MB.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}
	if ( 'xlsx' === $ext ) {
		$rows = memorial_plugin_import_read_xlsx( $path );
	} elseif ( in_array( $ext, array( 'csv', 'txt' ), true ) ) {
		$rows = memorial_plugin_import_read_csv( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- an uploaded temporary file.
	} else {
		return new WP_Error( 'memorial_plugin_import_type', __( 'The table must be an Excel file (.xlsx) or CSV.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}
	// Without empty rows at the end and the apostrophe the export puts before = + - @ for Excel.
	$rows = array_map(
		static fn( $r ) => array_map(
			static fn( $v ) => preg_match( "/^'[=+\\-@]/", (string) $v ) ? substr( (string) $v, 1 ) : (string) $v,
			$r
		),
		$rows
	);
	return array_values( array_filter( $rows, static fn( $r ) => '' !== trim( implode( '', $r ) ) ) );
}

/**
 * Rows of a CSV: UTF-8 (with or without BOM) or Windows-1251; separator ; , or tab — by the first line.
 *
 * @param string $text Contents.
 * @return array<int,string[]>
 */
function memorial_plugin_import_read_csv( $text ) {
	if ( str_starts_with( $text, "\xEF\xBB\xBF" ) ) {
		$text = substr( $text, 3 );
	} elseif ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
		$text = (string) mb_convert_encoding( $text, 'UTF-8', 'Windows-1251' );
	}
	$first = strtok( $text, "\n" );
	$count = array();
	foreach ( array( ';', ',', "\t" ) as $d ) {
		$count[ $d ] = substr_count( (string) $first, $d );
	}
	arsort( $count );
	$sep = (string) array_key_first( $count );
	$fh  = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- memory stream.
	fwrite( $fh, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- memory stream.
	rewind( $fh );
	$rows = array();
	while ( false !== ( $r = fgetcsv( $fh, 0, $sep, '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- reading rows.
		if ( array( null ) !== $r ) {
			$rows[] = array_map( static fn( $v ) => str_replace( "\r\n", "\n", (string) $v ), $r );
		}
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- memory stream.
	return $rows;
}

/**
 * Rows of the first sheet of an Excel workbook (without libraries: ZIP + XML).
 *
 * @param string $path File.
 * @return array<int,string[]>|WP_Error
 */
function memorial_plugin_import_read_xlsx( $path ) {
	$bad = new WP_Error( 'memorial_plugin_import_xlsx', __( 'The Excel file could not be read. Save it again in Excel as “Excel Workbook (.xlsx)” or as CSV.', 'memorial-plugin' ), array( 'status' => 400 ) );
	if ( ! class_exists( 'ZipArchive' ) ) {
		return $bad;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return $bad;
	}
	$get   = static function ( $name ) use ( $zip ) {
		$xml = $zip->getFromName( $name );
		if ( false === $xml ) {
			return null;
		}
		$prev = libxml_use_internal_errors( true );
		$doc  = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET );
		libxml_use_internal_errors( $prev );
		return false === $doc ? null : $doc;
	};
	$book  = $get( 'xl/workbook.xml' );
	$rels  = $get( 'xl/_rels/workbook.xml.rels' );
	$sheet = 'xl/worksheets/sheet1.xml';
	if ( $book && $rels && isset( $book->sheets->sheet[0] ) ) {
		$rid = (string) $book->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
		foreach ( $rels->Relationship as $rel ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XML.
			if ( (string) $rel['Id'] === $rid ) {
				$target = ltrim( (string) $rel['Target'], '/' );
				$sheet  = str_starts_with( $target, 'xl/' ) ? $target : 'xl/' . $target;
			}
		}
	}
	$strings = array();
	$shared  = $get( 'xl/sharedStrings.xml' );
	if ( $shared ) {
		foreach ( $shared->si as $si ) {
			$strings[] = memorial_plugin_import_xlsx_text( $si );
		}
	}
	$doc = $get( $sheet );
	$zip->close();
	if ( ! $doc || ! isset( $doc->sheetData ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XML.
		return $bad;
	}
	$rows = array();
	foreach ( $doc->sheetData->row as $row ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XML.
		$line = array();
		$next = 0;
		foreach ( $row->c as $c ) {
			$col = preg_match( '/^([A-Z]+)/', (string) $c['r'], $m ) ? memorial_plugin_import_col_index( $m[1] ) : $next;
			$t   = (string) $c['t'];
			if ( 's' === $t ) {
				$v = $strings[ (int) $c->v ] ?? '';
			} elseif ( 'inlineStr' === $t ) {
				$v = memorial_plugin_import_xlsx_text( $c->is );
			} elseif ( 'b' === $t ) {
				$v = '1' === (string) $c->v ? 'yes' : 'no';
			} else {
				$v = (string) $c->v;
				// A whole number Excel keeps as 4500 or 4.5E3 — as typed.
				if ( is_numeric( $v ) && round( (float) $v ) === (float) $v && abs( (float) $v ) < 1e15 ) {
					$v = (string) (int) round( (float) $v );
				}
			}
			$line[ $col ] = str_replace( "\r\n", "\n", $v );
			$next         = $col + 1;
		}
		if ( $line ) {
			$rows[] = array_replace( array_fill( 0, max( array_keys( $line ) ) + 1, '' ), $line );
		}
	}
	return $rows;
}

/**
 * Text of a cell of Excel: plain or of several runs of formatting.
 *
 * @param SimpleXMLElement $node <si> or <is>.
 * @return string
 */
function memorial_plugin_import_xlsx_text( $node ) {
	if ( isset( $node->t ) ) {
		return (string) $node->t;
	}
	$s = '';
	foreach ( $node->r as $r ) {
		$s .= (string) $r->t;
	}
	return $s;
}

/**
 * Column number of letters: A — 0, AA — 26.
 *
 * @param string $letters Letters.
 * @return int
 */
function memorial_plugin_import_col_index( $letters ) {
	$n = 0;
	foreach ( str_split( $letters ) as $ch ) {
		$n = $n * 26 + ( ord( $ch ) - 64 );
	}
	return $n - 1;
}

/**
 * A list in a cell: one value per line, or separated by semicolons.
 *
 * @param string $cell Cell.
 * @return string[]
 */
function memorial_plugin_import_list( $cell ) {
	$cell = trim( (string) $cell );
	if ( '' === $cell ) {
		return array();
	}
	$parts = preg_split( str_contains( $cell, "\n" ) ? '/\R/u' : '/;/u', $cell );
	return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
}

/**
 * Yes or no in a cell: true, false or null (not understood).
 *
 * @param string $v Cell.
 * @return bool|null
 */
function memorial_plugin_import_bool( $v ) {
	$v = memorial_plugin_import_norm( $v );
	if ( in_array( $v, array( 'yes', 'да', 'д', '1', '+', 'true', 'y', 'x', 'х', 'v', '✓', memorial_plugin_import_norm( memorial_plugin_io_yes() ) ), true ) ) {
		return true;
	}
	if ( in_array( $v, array( 'no', 'нет', 'н', '0', 'false', 'n', memorial_plugin_import_norm( __( 'no', 'memorial-plugin' ) ) ), true ) ) {
		return false;
	}
	return null;
}

/**
 * Key of a photo name for finding it: the name without the folder, the extension and “-scaled”, as WordPress
 * makes a file name (lower case, latin letters when Cyr To Lat is on).
 *
 * @param string $name File name or title of an attachment.
 * @return string
 */
function memorial_plugin_import_photo_key( $name ) {
	$base = preg_replace( '/\.(jpe?g|png|webp|gif|avif|heic)$/i', '', wp_basename( str_replace( '\\', '/', (string) $name ) ) );
	$base = preg_replace( '/-(scaled|e\d{10,})$/i', '', $base );
	return sanitize_title( $base );
}

/**
 * Photos of the media library by key: key => attachment IDs (by the file name and by the title, which keeps
 * the name of the file as it was uploaded).
 *
 * @param bool $reset Read again.
 * @return array<string,int[]>
 */
function memorial_plugin_import_media_index( $reset = false ) {
	static $index = null;
	if ( null !== $index && ! $reset ) {
		return $index;
	}
	global $wpdb;
	$index = array();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one read of the library for the import.
	$rows = $wpdb->get_results(
		"SELECT p.ID, p.post_title, m.meta_value AS file FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file'
		WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' ORDER BY p.ID"
	);
	foreach ( $rows as $r ) {
		foreach ( array_unique( array( memorial_plugin_import_photo_key( (string) $r->file ), memorial_plugin_import_photo_key( (string) $r->post_title ) ) ) as $k ) {
			if ( '' !== $k ) {
				$index[ $k ][] = (int) $r->ID;
			}
		}
	}
	return $index;
}

/**
 * Photo imported before with the same contents (SHA-1 of the file).
 *
 * @param string $hash SHA-1.
 * @return int Attachment ID or 0.
 */
function memorial_plugin_import_media_by_hash( $hash ) {
	global $wpdb;
	if ( ! preg_match( '/^[a-f0-9]{40}$/', (string) $hash ) ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a lookup by a meta value.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mem_import_hash' AND meta_value = %s ORDER BY post_id LIMIT 1", $hash ) );
}

/**
 * Size of the uploaded original of an attachment.
 *
 * @param int $id Attachment ID.
 * @return int
 */
function memorial_plugin_import_media_size( $id ) {
	$file = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $id ) : '';
	$file = $file ? $file : get_attached_file( $id );
	return $file && file_exists( $file ) ? (int) filesize( $file ) : 0;
}

/**
 * Normalised path of a file sent with the import.
 *
 * @param string $path Path.
 * @return string
 */
function memorial_plugin_import_path( $path ) {
	$path = mb_strtolower( trim( str_replace( '\\', '/', (string) $path ) ) );
	return ltrim( preg_replace( '~^\./~', '', $path ), '/' );
}

/**
 * Where a photo of a row comes from.
 *
 * @param string $name  Name from the cell.
 * @param array  $files Files sent: path => [size, hash].
 * @param string $sku   SKU of the row (a file in a folder named so is preferred).
 * @return array{source:string,path?:string,id?:int,error?:string}
 */
function memorial_plugin_import_find_photo( $name, $files, $sku ) {
	$want  = memorial_plugin_import_path( $name );
	$found = array();
	foreach ( $files as $path => $f ) {
		if ( $path === $want || str_ends_with( $path, '/' . $want ) ) {
			$found[] = $path;
		}
	}
	if ( count( $found ) > 1 && '' !== $sku ) {
		$in    = array_values( array_filter( $found, static fn( $p ) => in_array( mb_strtolower( $sku ), explode( '/', $p ), true ) ) );
		$found = $in ? $in : $found;
	}
	if ( count( $found ) > 1 ) {
		return array(
			'source' => 'missing',
			/* translators: %s: file name. */
			'error'  => sprintf( __( 'There are several files named %s — write the folder too, e.g. foto/M-1/1.jpg.', 'memorial-plugin' ), $name ),
		);
	}
	$key   = memorial_plugin_import_photo_key( $name );
	$index = memorial_plugin_import_media_index();
	if ( $found ) {
		$f  = $files[ $found[0] ];
		$id = memorial_plugin_import_media_by_hash( $f['hash'] );
		// The same file is already in the library: imported before, or uploaded by hand (same name and size).
		foreach ( $id ? array() : ( $index[ $key ] ?? array() ) as $cand ) {
			if ( $f['size'] && memorial_plugin_import_media_size( $cand ) === $f['size'] ) {
				$id = $cand;
				break;
			}
		}
		return $id ? array(
			'source' => 'media',
			'id'     => $id,
		) : array(
			'source' => 'file',
			'path'   => $found[0],
		);
	}
	if ( ! empty( $index[ $key ] ) ) {
		return array(
			'source' => 'media',
			'id'     => (int) $index[ $key ][0],
		);
	}
	return array(
		'source' => 'missing',
		/* translators: %s: file name. */
		'error'  => sprintf( __( 'Photo %s was not found among the files or in the media library.', 'memorial-plugin' ), $name ),
	);
}

/**
 * Types and categories for finding them by name.
 *
 * @param bool $reset Read again.
 * @return array{types:array<string,int>,cats:array<int,array{path:array<string,int>,leaf:array<string,int[]>}>,fields:array<int,string[]>}
 */
function memorial_plugin_import_terms( $reset = false ) {
	static $out = null;
	if ( null !== $out && ! $reset ) {
		return $out;
	}
	$out = array(
		'types'  => array(),
		'cats'   => array(),
		'fields' => array(),
	);
	foreach ( memorial_plugin_manager_types() as $t ) {
		$tid = (int) $t->term_id;

		$out['types'][ memorial_plugin_import_norm( html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ) ) ] = $tid;

		$out['fields'][ $tid ] = array_column( memorial_plugin_type_fields( $tid, false ), 'label' );
		$out['cats'][ $tid ]   = array(
			'path' => array(),
			'leaf' => array(),
		);
		foreach ( memorial_plugin_io_cat_paths( $tid ) as $cid => $path ) {
			$out['cats'][ $tid ]['path'][ memorial_plugin_import_norm( preg_replace( '~\s*/\s*~u', '/', $path ) ) ] = $cid;

			$leaf = memorial_plugin_import_norm( preg_replace( '~^.*/\s*~u', '', $path ) );

			$out['cats'][ $tid ]['leaf'][ $leaf ][] = $cid;
		}
	}
	return $out;
}

/**
 * What a row of the table does to the catalog — nothing is written.
 *
 * @param array<string,string> $cells   Key => cell.
 * @param array                $ctx     files (path => [size, hash]), publish, labels (key => header).
 * @return array Plan: n, sku, title, id, action (create|update|same|error), values, meta, type, photos, alts, changes, errors, warnings.
 */
function memorial_plugin_import_plan( $cells, $ctx ) {
	$terms  = memorial_plugin_import_terms();
	$labels = $ctx['labels'] ?? array();
	$label  = static fn( $k ) => $labels[ $k ] ?? ( memorial_plugin_io_columns()[ $k ] ?? $k );
	$cell   = static fn( $k ) => trim( (string) ( $cells[ $k ] ?? '' ) );
	$errors = array();
	$warn   = array();
	$values = array();
	$meta   = array();
	$change = array();

	$sku = memorial_plugin_sku_clean( $cell( 'sku' ) );
	$id  = '' !== $sku ? memorial_plugin_product_by_sku( $sku ) : 0;
	$p   = $id ? get_post( $id ) : null;

	// Title.
	$title = $cell( 'title' );
	if ( '-' === $title ) {
		$errors[] = __( 'The title cannot be cleared.', 'memorial-plugin' );
	} elseif ( '' !== $title ) {
		$title = sanitize_text_field( $title );
		if ( ! $p || html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ) !== $title ) {
			$values['title'] = $title;
			$change[]        = $label( 'title' );
		}
	} elseif ( ! $p ) {
		$errors[] = __( 'A new product needs a title.', 'memorial-plugin' );
	}

	// Type.
	$cur_type = $p ? memorial_plugin_product_type( $id ) : 0;
	$type     = $cur_type;
	if ( '' !== $cell( 'type' ) && '-' !== $cell( 'type' ) ) {
		$type = $terms['types'][ memorial_plugin_import_norm( $cell( 'type' ) ) ] ?? 0;
		if ( ! $type ) {
			/* translators: %s: product type from the file. */
			$errors[] = sprintf( __( 'There is no product type “%s”. Add it in “Catalog → Types and categories” or correct the name.', 'memorial-plugin' ), $cell( 'type' ) );
		} elseif ( $p && $type !== $cur_type ) {
			$change[] = $label( 'type' );
		}
	} elseif ( ! $p ) {
		$errors[] = __( 'A new product needs a type.', 'memorial-plugin' );
	}

	// Categories (inside the type): a path “Live / Roses” or a name if it is the only one.
	if ( $type && '' !== $cell( 'cats' ) ) {
		$ids = array();
		foreach ( '-' === $cell( 'cats' ) ? array() : memorial_plugin_import_list( $cell( 'cats' ) ) as $name ) {
			$n    = memorial_plugin_import_norm( preg_replace( '~\s*/\s*~u', '/', $name ) );
			$cid  = $terms['cats'][ $type ]['path'][ $n ] ?? 0;
			$leaf = $terms['cats'][ $type ]['leaf'][ $n ] ?? array();
			if ( ! $cid && 1 === count( $leaf ) ) {
				$cid = $leaf[0];
			}
			if ( $cid ) {
				$ids[] = $cid;
			} elseif ( count( $leaf ) > 1 ) {
				/* translators: %s: category. */
				$errors[] = sprintf( __( 'There are several categories “%s” — write the path, e.g. Live / Roses.', 'memorial-plugin' ), $name );
			} else {
				/* translators: %s: category. */
				$errors[] = sprintf( __( 'There is no category “%s” in this type.', 'memorial-plugin' ), $name );
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		$now = $p ? array_values( array_diff( array_map( 'intval', wp_get_object_terms( $id, 'mem_cat', array( 'fields' => 'ids' ) ) ), array( $type ) ) ) : array();
		sort( $now );
		if ( ! $p || $ids !== $now || $type !== $cur_type ) {
			$values['cats'] = $ids;
			if ( $p && $ids !== $now ) {
				$change[] = $label( 'cats' );
			}
		}
	} elseif ( $p && $type !== $cur_type ) {
		$values['cats'] = array();
	}

	// Prices.
	foreach ( array(
		'price' => '_mem_price',
		'old'   => '_mem_price_old',
	) as $k => $mk ) {
		$v = $cell( $k );
		if ( '' === $v ) {
			continue;
		}
		if ( '-' !== $v && ! preg_match( '/\d/', $v ) ) {
			/* translators: 1: column, 2: value. */
			$errors[] = sprintf( __( '%1$s “%2$s” is not a number.', 'memorial-plugin' ), $label( $k ), $v );
			continue;
		}
		$new = '-' === $v ? 0 : memorial_plugin_money( $v );
		if ( ! $p || (int) get_post_meta( $id, $mk, true ) !== $new ) {
			if ( 'price' === $k ) {
				$values['price'] = $new;
			} else {
				$meta[ $mk ] = $new;
			}
			$change[] = $label( $k );
		}
	}

	// Yes / no.
	foreach ( array(
		'from'  => '_mem_price_from',
		'order' => '_mem_on_order',
	) as $k => $mk ) {
		$v = $cell( $k );
		if ( '' === $v ) {
			continue;
		}
		$b = '-' === $v ? false : memorial_plugin_import_bool( $v );
		if ( null === $b ) {
			/* translators: 1: column, 2: value. */
			$errors[] = sprintf( __( '%1$s: “%2$s” — write “yes” or “no”.', 'memorial-plugin' ), $label( $k ), $v );
			continue;
		}
		if ( ! $p || ( '1' === (string) get_post_meta( $id, $mk, true ) ) !== $b ) {
			if ( 'from' === $k ) {
				$values['from'] = $b;
			} else {
				$meta[ $mk ] = $b ? '1' : '';
			}
			$change[] = $label( $k );
		}
	}

	// Visibility: the column, or for a new product the “Publish at once” checkbox.
	$v = $cell( 'status' );
	if ( '' !== $v && '-' !== $v ) {
		$b = memorial_plugin_import_bool( $v );
		if ( null === $b ) {
			/* translators: 1: column, 2: value. */
			$errors[] = sprintf( __( '%1$s: “%2$s” — write “yes” or “no”.', 'memorial-plugin' ), $label( 'status' ), $v );
		} elseif ( ! $p || ( 'publish' === $p->post_status ) !== $b ) {
			$values['status'] = $b ? 'publish' : 'draft';
			$change[]         = $label( 'status' );
		}
	} elseif ( ! $p ) {
		$values['status'] = empty( $ctx['publish'] ) ? 'draft' : 'publish';
	}

	// Texts.
	foreach ( array( 'unit', 'sub' ) as $k ) {
		$v = $cell( $k );
		if ( '' === $v ) {
			continue;
		}
		$new = '-' === $v ? '' : sanitize_text_field( $v );
		if ( ! $p || (string) get_post_meta( $id, '_mem_' . $k, true ) !== $new ) {
			$values[ $k ] = $new;
			$change[]     = $label( $k );
		}
	}
	$v = trim( (string) ( $cells['desc'] ?? '' ) );
	if ( '' !== $v ) {
		$new = '-' === $v ? '' : str_replace( "\r\n", "\n", $v );
		$now = $p ? memorial_plugin_product_desc( $p )['text'] : '';
		if ( ! $p || preg_replace( '/\s+/u', ' ', $now ) !== preg_replace( '/\s+/u', ' ', $new ) ) {
			$values['desc'] = $new;
			$change[]       = $label( 'desc' );
		}
	}

	// Specifications.
	$map    = $p ? memorial_plugin_product_spec_map( $id ) : array();
	$fields = $terms['fields'][ $type ] ?? array();
	foreach ( $cells as $k => $v ) {
		if ( ! str_starts_with( $k, 'spec:' ) || '' === trim( (string) $v ) ) {
			continue;
		}
		$name = substr( $k, 5 );
		$new  = '-' === trim( $v ) ? '' : sanitize_text_field( $v );
		if ( ( $map[ $name ] ?? '' ) !== $new ) {
			$values['specs'][ $name ] = $new;
			$change[]                 = $label( $k );
			if ( $type && '' !== $new && $fields && ! in_array( $name, $fields, true ) ) {
				/* translators: %s: specification. */
				$warn[] = sprintf( __( 'The type has no specification “%s” — it is kept as an extra one.', 'memorial-plugin' ), $name );
			}
		}
	}

	// Photos.
	$photos = null;
	$names  = memorial_plugin_import_list( $cell( 'photos' ) );
	$files  = $ctx['files'] ?? array();
	if ( '-' === $cell( 'photos' ) ) {
		$photos = array();
	} elseif ( $names ) {
		$photos = array();
		foreach ( $names as $name ) {
			$photos[] = array( 'name' => $name ) + memorial_plugin_import_find_photo( $name, $files, $sku );
		}
	} elseif ( '' !== $sku ) {
		// The column is empty: the files in a folder named as the SKU, by name.
		$in = array_values( array_filter( array_keys( $files ), static fn( $path ) => in_array( mb_strtolower( $sku ), array_slice( explode( '/', $path ), 0, -1 ), true ) ) );
		natsort( $in );
		foreach ( $in as $path ) {
			$photos[] = array( 'name' => wp_basename( $path ) ) + memorial_plugin_import_find_photo( $path, $files, $sku );
		}
		$photos = $photos ? $photos : null;
	}
	// Alt texts in the order of the photos: one per line (a semicolon inside a text stays) or separated by “;”.
	$alt_cell = trim( (string) ( $cells['alts'] ?? '' ) );
	$alts     = '' === $alt_cell ? array() : array_map( 'trim', preg_split( str_contains( $alt_cell, "\n" ) ? '/\R/u' : '/;/u', $alt_cell ) );

	$now_ids = $p ? array_map( 'intval', memorial_plugin_product_photos( $id ) ) : array();
	if ( null !== $photos ) {
		foreach ( $photos as $ph ) {
			if ( 'missing' === $ph['source'] ) {
				$errors[] = $ph['error'];
			}
		}
		$ids = array_map( static fn( $ph ) => 'media' === $ph['source'] ? (int) $ph['id'] : -1, $photos );
		if ( $ids !== $now_ids ) {
			$change[] = $label( 'photos' );
		}
		if ( $alts && count( $alts ) !== count( $photos ) ) {
			/* translators: 1: number of alt texts, 2: number of photos. */
			$warn[] = sprintf( __( 'Alt texts: %1$d, photos: %2$d — they are matched in order.', 'memorial-plugin' ), count( $alts ), count( $photos ) );
		}
	}
	// Alt texts of photos that stay: a change of an alt is a change too.
	$alt_of = null !== $photos ? array_map( static fn( $ph ) => 'media' === $ph['source'] ? (int) $ph['id'] : 0, $photos ) : $now_ids;
	foreach ( $alts as $i => $a ) {
		$att = $alt_of[ $i ] ?? 0;
		if ( $att && '' !== $a && (string) get_post_meta( $att, '_wp_attachment_image_alt', true ) !== sanitize_text_field( $a ) ) {
			$change[] = $label( 'alts' );
			break;
		}
	}

	$change = array_values( array_unique( $change ) );
	if ( $errors ) {
		$action = 'error';
	} elseif ( ! $p ) {
		$action = 'create';
	} else {
		$action = $change ? 'update' : 'same';
	}
	return array(
		'sku'      => $sku,
		'title'    => '' !== $title && '-' !== $title ? $title : ( $p ? html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ) : '' ),
		'id'       => $id,
		'type'     => $type,
		'typeNow'  => $cur_type,
		'action'   => $action,
		'values'   => $values,
		'meta'     => $meta,
		'photos'   => $photos,
		'alts'     => $alts,
		'changes'  => $p ? $change : array(),
		'errors'   => $errors,
		'warnings' => $warn,
	);
}

/**
 * Rows of the table as key => cell, with their numbers in the file.
 *
 * @param array $rows Rows (the first is the header).
 * @return array{map:array,items:array<int,array{n:int,cells:array<string,string>}>}
 */
function memorial_plugin_import_rows( $rows ) {
	$map   = memorial_plugin_import_map( $rows[0] ?? array() );
	$items = array();
	foreach ( array_slice( $rows, 1 ) as $i => $r ) {
		$cells = array();
		foreach ( $map['cols'] as $c => $key ) {
			$cells[ $key ] = (string) ( $r[ $c ] ?? '' );
		}
		// Something only in columns the import does not know (a note at the side) — not a product.
		if ( '' === trim( implode( '', $cells ) ) ) {
			continue;
		}
		$items[] = array(
			'n'     => $i + 2,
			'cells' => $cells,
		);
	}
	return array(
		'map'   => $map,
		'items' => $items,
	);
}

/**
 * Files the browser has: [{path, size, hash}] → path => [size, hash].
 *
 * @param mixed $list List.
 * @return array<string,array{size:int,hash:string}>
 */
function memorial_plugin_import_files( $list ) {
	$out = array();
	foreach ( (array) $list as $f ) {
		$path = memorial_plugin_import_path( (string) ( $f['path'] ?? '' ) );
		if ( '' === $path || ! preg_match( '/\.(jpe?g|png|webp|gif|avif)$/i', $path ) || str_contains( $path, '..' ) ) {
			continue;
		}
		$out[ $path ] = array(
			'size' => absint( $f['size'] ?? 0 ),
			'hash' => strtolower( preg_replace( '/[^a-fA-F0-9]/', '', (string) ( $f['hash'] ?? '' ) ) ),
		);
	}
	return $out;
}

/**
 * Preview of an import: the plan of every row; the table waits for the import under a token.
 *
 * @param array  $rows    Rows of the table.
 * @param array  $files   Files: path => [size, hash].
 * @param bool   $publish Publish new products at once.
 * @param string $name    Name of the table file.
 * @return array|WP_Error
 */
function memorial_plugin_import_preview( $rows, $files, $publish, $name = '' ) {
	memorial_plugin_import_terms( true );
	memorial_plugin_import_media_index( true );
	$table = memorial_plugin_import_rows( $rows );
	$map   = $table['map'];
	if ( ! in_array( 'sku', $map['cols'], true ) && ! in_array( 'title', $map['cols'], true ) ) {
		return new WP_Error( 'memorial_plugin_import_head', __( 'The first row of the table must be the headers: SKU, Type, Title… Download the example file.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}
	$ctx   = array(
		'files'   => $files,
		'publish' => $publish,
		'labels'  => $map['labels'],
	);
	$out   = array();
	$need  = array();
	$count = array(
		'create' => 0,
		'update' => 0,
		'same'   => 0,
		'error'  => 0,
	);
	$seen  = array();
	foreach ( $table['items'] as $item ) {
		$plan = memorial_plugin_import_plan( $item['cells'], $ctx );
		if ( '' !== $plan['sku'] ) {
			$k = mb_strtolower( $plan['sku'] );
			if ( isset( $seen[ $k ] ) ) {
				/* translators: %d: row number. */
				$plan['errors'][] = sprintf( __( 'The same SKU is in row %d.', 'memorial-plugin' ), $seen[ $k ] );
				$plan['action']   = 'error';
			}
			$seen[ $k ] = $item['n'];
		}
		++$count[ $plan['action'] ];
		$photos = array(
			'media'   => 0,
			'file'    => 0,
			'missing' => 0,
		);
		foreach ( (array) $plan['photos'] as $ph ) {
			++$photos[ $ph['source'] ];
			if ( 'file' === $ph['source'] && 'error' !== $plan['action'] ) {
				$need[ $ph['path'] ] = true;
			}
		}
		$out[] = array(
			'n'        => $item['n'],
			'sku'      => $plan['sku'],
			'title'    => $plan['title'],
			'action'   => $plan['action'],
			'changes'  => $plan['changes'],
			'errors'   => $plan['errors'],
			'warnings' => $plan['warnings'],
			'photos'   => null === $plan['photos'] ? null : $photos,
		);
	}
	$token = wp_generate_password( 24, false );
	set_transient(
		'memorial_plugin_import_' . $token,
		array(
			'user'     => get_current_user_id(),
			'name'     => sanitize_file_name( (string) $name ),
			'rows'     => $rows,
			'files'    => $files,
			'need'     => array_keys( $need ),
			'uploaded' => array(),
			'publish'  => (bool) $publish,
		),
		6 * HOUR_IN_SECONDS
	);
	return array(
		'token'   => $token,
		'rows'    => $out,
		'count'   => $count,
		'columns' => array_values( $map['labels'] ),
		'unknown' => $map['unknown'],
		'need'    => array_keys( $need ),
	);
}

/**
 * The waiting import of the current user.
 *
 * @param string $token Token.
 * @return array|WP_Error
 */
function memorial_plugin_import_state( $token ) {
	$state = preg_match( '/^[A-Za-z0-9]{24}$/', (string) $token ) ? get_transient( 'memorial_plugin_import_' . $token ) : false;
	if ( ! is_array( $state ) || get_current_user_id() !== (int) $state['user'] ) {
		return new WP_Error( 'memorial_plugin_import_token', __( 'The check of the file is out of date. Check the file again.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}
	return $state;
}

/**
 * Upload one photo of a waiting import (or find the same one in the library).
 *
 * @param string $token Token.
 * @param string $path  Path of the file in the import.
 * @param array  $file  Uploaded file ($_FILES entry).
 * @return array{id:int,reused:bool}|WP_Error
 */
function memorial_plugin_import_upload( $token, $path, $file ) {
	$state = memorial_plugin_import_state( $token );
	if ( is_wp_error( $state ) ) {
		return $state;
	}
	$path = memorial_plugin_import_path( $path );
	if ( ! in_array( $path, $state['need'], true ) ) {
		return new WP_Error( 'memorial_plugin_import_path', __( 'This file is not needed by the import.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}
	$key = 'memorial_plugin_import_' . $token;
	if ( ! empty( $state['uploaded'][ $path ] ) ) {
		return array(
			'id'     => (int) $state['uploaded'][ $path ],
			'reused' => true,
		);
	}
	$hash = (string) ( $state['files'][ $path ]['hash'] ?? '' );
	$id   = memorial_plugin_import_media_by_hash( $hash );
	if ( ! $id ) {
		$check = wp_check_filetype_and_ext( (string) ( $file['tmp_name'] ?? '' ), (string) ( $file['name'] ?? '' ) );
		if ( empty( $check['type'] ) || ! str_starts_with( (string) $check['type'], 'image/' ) ) {
			return new WP_Error( 'memorial_plugin_import_image', __( 'Only photos can be uploaded (JPEG, PNG, WebP, GIF).', 'memorial-plugin' ), array( 'status' => 400 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$file['name'] = wp_basename( $path );
		$id           = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( $hash ) {
			update_post_meta( $id, '_mem_import_hash', $hash );
		}
	}
	$state['uploaded'][ $path ] = (int) $id;
	set_transient( $key, $state, 6 * HOUR_IN_SECONDS );
	return array(
		'id'     => (int) $id,
		'reused' => false,
	);
}

/**
 * Values of a product and of its photos before the import — for the rollback.
 *
 * @param int $id Product ID.
 * @return array
 */
function memorial_plugin_import_snapshot( $id ) {
	$p    = get_post( $id );
	$meta = array();
	foreach ( array( '_mem_sku', '_mem_price', '_mem_price_from', '_mem_price_old', '_mem_unit', '_mem_sub', '_mem_on_order', '_mem_specs', '_mem_gallery', '_thumbnail_id' ) as $k ) {
		$meta[ $k ] = get_post_meta( $id, $k, true );
	}
	return array(
		'title'   => $p->post_title,
		'status'  => $p->post_status,
		'content' => $p->post_content,
		'terms'   => array_map( 'intval', wp_get_object_terms( $id, 'mem_cat', array( 'fields' => 'ids' ) ) ),
		'meta'    => $meta,
	);
}

/**
 * Apply one row.
 *
 * @param array $plan  Plan of the row (memorial_plugin_import_plan).
 * @param array $state Waiting import (uploaded files).
 * @param array $log   Log of the import, changed in place.
 * @return array{id:int,action:string,errors:string[]}
 */
function memorial_plugin_import_apply( $plan, $state, &$log ) {
	if ( in_array( $plan['action'], array( 'error', 'same' ), true ) ) {
		return array(
			'id'     => (int) $plan['id'],
			'action' => $plan['action'],
			'errors' => $plan['errors'],
		);
	}
	$id     = (int) $plan['id'];
	$values = $plan['values'];
	if ( ! $id ) {
		$id = memorial_plugin_cm_create( $plan['type'], 0, $values['title'] ?? '' );
		if ( ! $id ) {
			return array(
				'id'     => 0,
				'action' => 'error',
				'errors' => array( __( 'The product could not be created.', 'memorial-plugin' ) ),
			);
		}
		if ( '' !== $plan['sku'] ) {
			update_post_meta( $id, '_mem_sku', $plan['sku'] );
		}
		$log['created'][] = $id;
		unset( $values['title'] );
	} else {
		if ( ! isset( $log['updated'][ $id ] ) ) {
			$log['updated'][ $id ] = memorial_plugin_import_snapshot( $id );
		}
		if ( $plan['type'] && $plan['type'] !== $plan['typeNow'] ) {
			wp_set_object_terms( $id, array( $plan['type'] ), 'mem_cat' );
		}
	}
	unset( $values['photos'] );
	memorial_plugin_product_save( $id, $values );
	foreach ( $plan['meta'] as $k => $v ) {
		'' === $v || 0 === $v ? delete_post_meta( $id, $k ) : update_post_meta( $id, $k, $v );
	}

	// Photos: the files uploaded for this import or photos of the library.
	$title  = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
	$before = array_map( 'intval', memorial_plugin_product_photos( $id ) );
	$ids    = $before;
	$errors = array();
	if ( null !== $plan['photos'] ) {
		$ids = array();
		foreach ( $plan['photos'] as $ph ) {
			$att = 'media' === $ph['source'] ? (int) $ph['id'] : (int) ( $state['uploaded'][ $ph['path'] ?? '' ] ?? 0 );
			if ( $att ) {
				$ids[] = $att;
			} else {
				/* translators: %s: file name. */
				$errors[] = sprintf( __( 'Photo %s was not uploaded.', 'memorial-plugin' ), $ph['name'] );
			}
		}
		if ( $ids !== $before ) {
			memorial_plugin_product_set_photos( $id, $ids );
		}
	}
	$desc  = memorial_plugin_product_desc( get_post( $id ) )['text'];
	$total = count( $ids );
	foreach ( array_values( $ids ) as $i => $att ) {
		$alt   = sanitize_text_field( (string) ( $plan['alts'][ $i ] ?? '' ) );
		$now   = (string) get_post_meta( $att, '_wp_attachment_image_alt', true );
		$fresh = ! in_array( $att, $before, true );
		/* translators: 1: product title, 2: number of the photo. */
		$named = $total > 1 ? sprintf( __( '%1$s — photo %2$d', 'memorial-plugin' ), $title, $i + 1 ) : $title;
		$alt   = '' !== $alt ? $alt : ( '' === $now ? $named : $now );
		if ( $alt === $now && ! $fresh ) {
			continue;
		}
		if ( ! isset( $log['media'][ $att ] ) ) {
			$a = get_post( $att );


			$log['media'][ $att ] = array(
				'alt'     => $now,
				'title'   => $a ? $a->post_title : '',
				'content' => $a ? $a->post_content : '',
			);
		}
		update_post_meta( $att, '_wp_attachment_image_alt', $alt );
		// A photo new for the product gets the title and the description of the product in the library.
		if ( $fresh ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $att,
						'post_title'   => $named,
						'post_content' => $desc,
					)
				)
			);
		}
	}
	return array(
		'id'     => $id,
		'action' => $plan['action'],
		'errors' => $errors,
	);
}

/**
 * Apply a portion of rows of a waiting import.
 *
 * @param string $token  Token.
 * @param int    $offset First row (0 — the first after the header).
 * @return array|WP_Error
 */
function memorial_plugin_import_run( $token, $offset ) {
	$state = memorial_plugin_import_state( $token );
	if ( is_wp_error( $state ) ) {
		return $state;
	}
	memorial_plugin_import_terms( true );
	memorial_plugin_import_media_index( true );
	$table = memorial_plugin_import_rows( $state['rows'] );
	$log   = get_option( 'memorial_plugin_import_log' );
	if ( 0 === $offset || ! is_array( $log ) || ( $log['token'] ?? '' ) !== $token ) {
		$log = array(
			'token'   => $token,
			'time'    => time(),
			'user'    => get_current_user_id(),
			'name'    => $state['name'],
			'created' => array(),
			'updated' => array(),
			'media'   => array(),
			'done'    => false,
		);
	}
	$ctx  = array(
		'files'   => $state['files'],
		'publish' => $state['publish'],
		'labels'  => $table['map']['labels'],
	);
	$out  = array();
	$part = array_slice( $table['items'], $offset, MEMORIAL_PLUGIN_IMPORT_PORTION );
	foreach ( $part as $item ) {
		$plan  = memorial_plugin_import_plan( $item['cells'], $ctx );
		$res   = memorial_plugin_import_apply( $plan, $state, $log );
		$out[] = array(
			'n'      => $item['n'],
			'sku'    => '' !== $plan['sku'] ? $plan['sku'] : ( $res['id'] ? (string) get_post_meta( $res['id'], '_mem_sku', true ) : '' ),
			'title'  => $plan['title'],
			'id'     => $res['id'],
			'action' => $res['action'],
			'errors' => $res['errors'],
		);
	}
	$next = $offset + count( $part );
	$done = $next >= count( $table['items'] );
	if ( $done ) {
		$log['done']     = true;
		$log['uploaded'] = array_values( $state['uploaded'] );
		memorial_plugin_import_media_index( true );
		if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
			memorial_plugin_page_cache_clear();
		}
	}
	update_option( 'memorial_plugin_import_log', $log, false );
	return array(
		'rows'  => $out,
		'next'  => $next,
		'total' => count( $table['items'] ),
		'done'  => $done,
	);
}

/**
 * Short description of the last import for the screen.
 *
 * @return array|null
 */
function memorial_plugin_import_last() {
	$log = get_option( 'memorial_plugin_import_log' );
	if ( ! is_array( $log ) || empty( $log['time'] ) ) {
		return null;
	}
	return array(
		'time'    => (int) $log['time'],
		'name'    => (string) ( $log['name'] ?? '' ),
		'created' => count( (array) ( $log['created'] ?? array() ) ),
		'updated' => count( (array) ( $log['updated'] ?? array() ) ),
		'rolled'  => ! empty( $log['rolled'] ),
		'canUndo' => empty( $log['rolled'] ) && ( $log['created'] || $log['updated'] || $log['media'] ),
	);
}

/**
 * Roll back the last import: created products — to the trash, updated ones and their photos — as before.
 *
 * @return array|WP_Error Numbers of products.
 */
function memorial_plugin_import_rollback() {
	global $wpdb;
	$log = get_option( 'memorial_plugin_import_log' );
	if ( ! is_array( $log ) || ! empty( $log['rolled'] ) ) {
		return new WP_Error( 'memorial_plugin_import_nothing', __( 'There is no import to roll back.', 'memorial-plugin' ), array( 'status' => 400 ) );
	}
	$trashed = 0;
	foreach ( (array) $log['created'] as $id ) {
		if ( 'mem_product' === get_post_type( $id ) && wp_trash_post( $id ) ) {
			++$trashed;
		}
	}
	foreach ( (array) $log['updated'] as $id => $snap ) {
		$id = (int) $id;
		if ( 'mem_product' !== get_post_type( $id ) ) {
			continue;
		}
		// The texts directly: without a revision and the filters of saving.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- restore of the values before the import.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_title'   => $snap['title'],
				'post_status'  => $snap['status'],
				'post_content' => $snap['content'],
			),
			array( 'ID' => $id )
		);
		clean_post_cache( $id );
		foreach ( $snap['meta'] as $k => $v ) {
			'' === $v || false === $v ? delete_post_meta( $id, $k ) : update_post_meta( $id, $k, wp_slash( $v ) );
		}
		wp_set_object_terms( $id, $snap['terms'], 'mem_cat' );
	}
	foreach ( (array) $log['media'] as $att => $snap ) {
		$att = (int) $att;
		if ( 'attachment' !== get_post_type( $att ) ) {
			continue;
		}
		update_post_meta( $att, '_wp_attachment_image_alt', $snap['alt'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- restore of the values before the import.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_title'   => $snap['title'],
				'post_content' => $snap['content'],
			),
			array( 'ID' => $att )
		);
		clean_post_cache( $att );
	}
	$log['rolled'] = time();
	update_option( 'memorial_plugin_import_log', $log, false );
	if ( function_exists( 'memorial_plugin_page_cache_clear' ) ) {
		memorial_plugin_page_cache_clear();
	}
	return array(
		'trashed'  => $trashed,
		'restored' => count( (array) $log['updated'] ),
	);
}

/**
 * Example rows for the example files: products of the first type of the site.
 *
 * @return array{sheet:array,folders:array<string,string[]>,files:string[]}
 */
function memorial_plugin_import_sample_rows() {
	$types = memorial_plugin_manager_types();
	$type  = $types ? $types[0] : null;
	$tname = $type ? html_entity_decode( $type->name, ENT_QUOTES, 'UTF-8' ) : __( 'Wreaths', 'memorial-plugin' );
	$cats  = $type ? array_values( memorial_plugin_io_cat_paths( (int) $type->term_id ) ) : array();
	$specs = $type ? array_slice( memorial_plugin_type_fields( (int) $type->term_id ), 0, 3 ) : array();
	$yes   = memorial_plugin_io_yes();
	$cols  = memorial_plugin_io_columns();
	$value = static function ( $f, $i ) {
		if ( 'list' === $f['type'] && $f['options'] ) {
			return $f['options'][ $i % count( $f['options'] ) ];
		}
		if ( 'number' === $f['type'] ) {
			return trim( ( 100 + 20 * $i ) . ' ' . $f['unit'] );
		}
		return __( 'example', 'memorial-plugin' );
	};
	$rows  = array(
		array(
			'sku'    => 'OBR-1',
			'type'   => $tname,
			'cats'   => $cats[0] ?? '',
			'title'  => __( 'Example: oval wreath', 'memorial-plugin' ),
			'price'  => 4500,
			'from'   => $yes,
			'old'    => 5200,
			'unit'   => __( 'pcs', 'memorial-plugin' ),
			'sub'    => '',
			'order'  => '',
			'status' => '',
			'desc'   => __( 'An oval funeral wreath of artificial roses and greenery.', 'memorial-plugin' ) . "\n\n" . __( 'A ribbon with an inscription is made on request.', 'memorial-plugin' ),
			'photos' => "obr-1-front.jpg\nobr-1-side.jpg",
			'alts'   => __( 'Oval wreath, front view', 'memorial-plugin' ) . "\n" . __( 'Oval wreath, side view', 'memorial-plugin' ),
		),
		array(
			'sku'    => 'OBR-2',
			'type'   => $tname,
			'cats'   => '',
			'title'  => __( 'Example: price on request', 'memorial-plugin' ),
			'price'  => '',
			'from'   => '',
			'old'    => '',
			'unit'   => '',
			'sub'    => '',
			'order'  => $yes,
			'status' => '',
			'desc'   => __( 'Made to order in 2–3 days.', 'memorial-plugin' ),
			'photos' => 'obr-2.jpg',
			'alts'   => '',
		),
		array(
			'sku'    => 'OBR-3',
			'type'   => $tname,
			'cats'   => $cats[1] ?? ( $cats[0] ?? '' ),
			'title'  => __( 'Example: photos from the folder OBR-3', 'memorial-plugin' ),
			'price'  => 3200,
			'from'   => '',
			'old'    => '',
			'unit'   => '',
			'sub'    => '',
			'order'  => '',
			'status' => '',
			'desc'   => __( 'The Photos column is empty: the photos are taken from the folder foto/OBR-3 of the archive, in the order of the file names.', 'memorial-plugin' ),
			'photos' => '',
			'alts'   => '',
		),
	);
	$lines = array();
	foreach ( $rows as $i => $r ) {
		$line = array_values( $r );
		foreach ( $specs as $f ) {
			$line[] = $value( $f, $i );
		}
		$lines[] = $line;
	}
	$head = array_merge( array_values( $cols ), array_map( static fn( $f ) => memorial_plugin_io_spec_header( $f['label'] ), $specs ) );
	return array(
		'sheet' => array(
			'head'   => $head,
			'rows'   => $lines,
			'widths' => array(
				3  => 34,
				11 => 50,
				12 => 30,
				13 => 40,
			),
		),
		'files' => array( 'foto/obr-1-front.jpg', 'foto/obr-1-side.jpg', 'foto/obr-2.jpg', 'foto/OBR-3/1.jpg', 'foto/OBR-3/2.jpg' ),
	);
}

/**
 * The guide sheet of the example: column — what to write — example.
 *
 * @return array{head:string[],rows:array,widths:array}
 */
function memorial_plugin_import_guide() {
	$c    = memorial_plugin_io_columns();
	$rows = array(
		array( $c['sku'], __( 'The key of the product. A row with the SKU of a product of the site updates it; a new or empty SKU makes a new product.', 'memorial-plugin' ), 'M-1024' ),
		array( $c['type'], __( 'Product type exactly as in “Catalog → Types and categories” (the sheet “Types and categories”). Needed for a new product.', 'memorial-plugin' ), __( 'Wreaths', 'memorial-plugin' ) ),
		array( $c['cats'], __( 'Categories of the type, one per line (Alt+Enter) or separated by “;”. A nested one — with its parent: Live / Roses.', 'memorial-plugin' ), __( 'Oval', 'memorial-plugin' ) ),
		array( $c['title'], __( 'Title of the product. Needed for a new product.', 'memorial-plugin' ), __( 'Wreath “Tenderness”', 'memorial-plugin' ) ),
		array( $c['price'] . ', ' . $c['old'], __( 'Whole roubles. Empty or 0 — “price on request”.', 'memorial-plugin' ), '4500' ),
		array( $c['from'] . ', ' . $c['order'] . ', ' . $c['status'], __( '“yes” or “no”. On the site empty — a new product is hidden, unless “Publish at once” is on.', 'memorial-plugin' ), memorial_plugin_io_yes() ),
		array( $c['unit'] . ', ' . $c['sub'], __( 'Short text.', 'memorial-plugin' ), __( 'pcs', 'memorial-plugin' ) ),
		array( $c['desc'], __( 'Plain text; an empty line starts a new paragraph.', 'memorial-plugin' ), '' ),
		array( $c['photos'], __( 'File names, one per line or separated by “;”; the first is the main photo. Files are taken from the archive (or the selected files), otherwise from the media library by name. Empty — the files of the folder named as the SKU: foto/M-1024/1.jpg, 2.jpg…', 'memorial-plugin' ), "1.jpg\n2.jpg" ),
		array( $c['alts'], __( 'Description of each photo for the blind and search engines, one per line in the order of the photos. Empty — the title of the product.', 'memorial-plugin' ), __( 'Wreath, front view', 'memorial-plugin' ) ),
		array( memorial_plugin_io_spec_header( '…' ), __( 'One column per specification, the name after the colon as in the type.', 'memorial-plugin' ), memorial_plugin_io_spec_header( __( 'Height', 'memorial-plugin' ) ) ),
		array( '—', __( 'An empty cell does not change the field of a product of the site; “-” clears it. Products that are not in the file are not changed.', 'memorial-plugin' ), '-' ),
		array( __( 'Archive', 'memorial-plugin' ), __( 'ZIP: the table in the root and the folder foto with the photos (or folders named as the SKUs).', 'memorial-plugin' ), 'catalog.xlsx, foto/1.jpg, foto/M-1024/1.jpg' ),
	);
	return array(
		'head'   => array( __( 'Column', 'memorial-plugin' ), __( 'What to write', 'memorial-plugin' ), __( 'Example', 'memorial-plugin' ) ),
		'rows'   => $rows,
		'widths' => array( 28, 90, 30 ),
	);
}

/**
 * The sheet of types: type — categories — specifications.
 *
 * @return array{head:string[],rows:array,widths:array}
 */
function memorial_plugin_import_types_sheet() {
	$rows = array();
	foreach ( memorial_plugin_manager_types() as $t ) {
		$specs  = array_map(
			static fn( $f ) => $f['label'] . ( $f['unit'] ? ', ' . $f['unit'] : '' ) . ( $f['options'] ? ' (' . implode( ' / ', $f['options'] ) . ')' : '' ),
			memorial_plugin_type_fields( (int) $t->term_id )
		);
		$rows[] = array(
			html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ),
			implode( "\n", memorial_plugin_io_cat_paths( (int) $t->term_id ) ),
			implode( "\n", $specs ),
		);
	}
	return array(
		'head'   => array( __( 'Type', 'memorial-plugin' ), __( 'Categories', 'memorial-plugin' ), __( 'Specifications', 'memorial-plugin' ) ),
		'rows'   => $rows,
		'widths' => array( 24, 40, 50 ),
	);
}

/**
 * An example photo: a plain picture with a caption (GD); without GD — none.
 *
 * @param string $text Caption.
 * @param int    $i    Number for the colour.
 * @return string JPEG or ''.
 */
function memorial_plugin_import_sample_photo( $text, $i ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return '';
	}
	$colors = array( array( 230, 235, 223 ), array( 242, 239, 230 ), array( 214, 222, 205 ), array( 236, 228, 214 ), array( 225, 230, 240 ) );
	$c      = $colors[ $i % count( $colors ) ];
	$im     = imagecreatetruecolor( 800, 600 );
	imagefill( $im, 0, 0, imagecolorallocate( $im, $c[0], $c[1], $c[2] ) );
	$ink = imagecolorallocate( $im, 63, 77, 54 );
	imagerectangle( $im, 40, 40, 759, 559, $ink );
	imagestring( $im, 5, 360 - 4 * strlen( $text ), 292, $text, $ink );
	ob_start();
	imagejpeg( $im, null, 85 );
	imagedestroy( $im );
	return (string) ob_get_clean();
}

/**
 * Example file: Excel (products, guide, types), CSV or ZIP (Excel + photos).
 *
 * @param string $format xlsx|csv|zip.
 * @return array|WP_Error filename, mime, file (base64).
 */
function memorial_plugin_import_sample( $format ) {
	$s      = memorial_plugin_import_sample_rows();
	$sheets = array(
		__( 'Products', 'memorial-plugin' )             => $s['sheet'],
		__( 'Guide', 'memorial-plugin' )                => memorial_plugin_import_guide(),
		__( 'Types and categories', 'memorial-plugin' ) => memorial_plugin_import_types_sheet(),
	);
	if ( 'csv' === $format ) {
		$bytes = memorial_plugin_io_csv( $s['sheet'] );
		$mime  = 'text/csv;charset=utf-8';
	} else {
		$bytes = memorial_plugin_io_xlsx( $sheets );
		$mime  = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
		if ( ! is_wp_error( $bytes ) && 'zip' === $format ) {
			$files = array( 'catalog-import.xlsx' => $bytes );
			foreach ( $s['files'] as $i => $f ) {
				$jpeg = memorial_plugin_import_sample_photo( strtoupper( preg_replace( '~^foto/|\.jpg$~', '', $f ) ), $i );
				if ( '' !== $jpeg ) {
					$files[ $f ] = $jpeg;
				}
			}
			$bytes = memorial_plugin_io_zip( $files );
			$mime  = 'application/zip';
		}
	}
	if ( is_wp_error( $bytes ) ) {
		return $bytes;
	}
	return array(
		'filename' => 'catalog-import-example.' . $format,
		'mime'     => $mime,
		'file'     => base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a file for the browser.
	);
}

/**
 * Who may import: an administrator or an editor — edits the products of everyone and uploads files.
 *
 * @return bool
 */
function memorial_plugin_import_can() {
	return current_user_can( 'edit_others_posts' ) && current_user_can( 'upload_files' );
}

/**
 * REST: /catalog/import/{preview,media,run,rollback,sample}.
 */
function memorial_plugin_import_rest() {
	$ns = 'memorial-plugin/v1';
	register_rest_route(
		$ns,
		'/catalog/import/preview',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'memorial_plugin_import_can',
			'callback'            => static function ( WP_REST_Request $request ) {
				$file = $request->get_file_params()['table'] ?? null;
				if ( ! is_array( $file ) || ! empty( $file['error'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
					return new WP_Error( 'memorial_plugin_import_file', __( 'Choose the table file (.xlsx or .csv).', 'memorial-plugin' ), array( 'status' => 400 ) );
				}
				$rows = memorial_plugin_import_read( (string) $file['tmp_name'], (string) $file['name'] );
				if ( is_wp_error( $rows ) ) {
					return $rows;
				}
				$files = json_decode( (string) $request->get_param( 'files' ), true );
				return rest_ensure_response( memorial_plugin_import_preview( $rows, memorial_plugin_import_files( is_array( $files ) ? $files : array() ), rest_sanitize_boolean( $request->get_param( 'publish' ) ), (string) $file['name'] ) );
			},
		)
	);
	register_rest_route(
		$ns,
		'/catalog/import/media',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'memorial_plugin_import_can',
			'args'                => array(
				'token' => array(
					'type'     => 'string',
					'required' => true,
				),
				'path'  => array(
					'type'     => 'string',
					'required' => true,
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				$file = $request->get_file_params()['file'] ?? null;
				if ( ! is_array( $file ) || ! empty( $file['error'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
					return new WP_Error( 'memorial_plugin_import_file', __( 'The photo was not received.', 'memorial-plugin' ), array( 'status' => 400 ) );
				}
				$res = memorial_plugin_import_upload( (string) $request['token'], (string) $request['path'], $file );
				return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
			},
		)
	);
	register_rest_route(
		$ns,
		'/catalog/import/run',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'memorial_plugin_import_can',
			'args'                => array(
				'token'  => array(
					'type'     => 'string',
					'required' => true,
				),
				'offset' => array(
					'type'    => 'integer',
					'minimum' => 0,
					'default' => 0,
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				$res = memorial_plugin_import_run( (string) $request['token'], (int) $request['offset'] );
				return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
			},
		)
	);
	register_rest_route(
		$ns,
		'/catalog/import/rollback',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'memorial_plugin_import_can',
			'callback'            => static function () {
				$res = memorial_plugin_import_rollback();
				return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
			},
		)
	);
	register_rest_route(
		$ns,
		'/catalog/import/sample',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => 'memorial_plugin_import_can',
			'args'                => array(
				'format' => array(
					'type'    => 'string',
					'enum'    => array( 'xlsx', 'csv', 'zip' ),
					'default' => 'xlsx',
				),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				$res = memorial_plugin_import_sample( (string) $request['format'] );
				return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
			},
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_import_rest' );

/**
 * Ability “Check an import file”: what the rows of a table would do, without writing anything.
 */
function memorial_plugin_import_ability() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}
	wp_register_ability(
		'memorial-plugin/check-import',
		array(
			'label'               => __( 'Check an import file', 'memorial-plugin' ),
			'description'         => __( 'Reads a table of products (Excel or CSV, base64) and tells what the import would do with each row: create, update, no change or an error. Nothing is written.', 'memorial-plugin' ),
			'category'            => 'memorial',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'name' => array(
						'type'        => 'string',
						'description' => __( 'File name with the extension: .xlsx or .csv.', 'memorial-plugin' ),
					),
					'file' => array(
						'type'        => 'string',
						'description' => __( 'The file, base64.', 'memorial-plugin' ),
					),
				),
				'required'             => array( 'name', 'file' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'count'   => array( 'type' => 'object' ),
					'rows'    => array( 'type' => 'array' ),
					'unknown' => array( 'type' => 'array' ),
				),
			),
			'permission_callback' => 'memorial_plugin_import_can',
			'execute_callback'    => static function ( $input ) {
				$tmp = wp_tempnam( 'memorial-import' );
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- the file of the ability.
				file_put_contents( $tmp, (string) base64_decode( (string) $input['file'], true ) );
				$rows = memorial_plugin_import_read( $tmp, (string) $input['name'] );
				wp_delete_file( $tmp );
				if ( is_wp_error( $rows ) ) {
					return $rows;
				}
				$res = memorial_plugin_import_preview( $rows, array(), false, (string) $input['name'] );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				delete_transient( 'memorial_plugin_import_' . $res['token'] );
				return array(
					'count'   => $res['count'],
					'rows'    => $res['rows'],
					'unknown' => $res['unknown'],
				);
			},
			'meta'                => array(
				'public'       => true,
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'   => false,
					'idempotent' => true,
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_init', 'memorial_plugin_import_ability' );
