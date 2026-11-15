<?php
/**
 * Catalog ↔ file: the SKU of a product and the table of products for Excel (.xlsx) and CSV.
 *
 * SKU (_mem_sku) — the key of a product in the file: the import finds a product by it and updates it
 * instead of creating a copy. Every product has one: a new product gets “M-<ID>” when it is saved for
 * the first time, the products made before 2.2 get it once (memorial_plugin_sku_1, log of the products in
 * memorial_plugin_sku_log — for a rollback). The owner can change it in the product window.
 *
 * The table: one row per product; lists (categories, photos, alt texts) — one value per line of a cell
 * (in Excel Alt+Enter), a semicolon works too. Specifications — one column each, “Spec: <name>”.
 * The same table is written by the export and read by the import (catalog-import.php).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * SKU as stored: one line, no tags, at most 64 characters.
 *
 * @param mixed $v Value.
 * @return string
 */
function memorial_plugin_sku_clean( $v ) {
	return trim( mb_substr( trim( sanitize_text_field( (string) $v ) ), 0, 64 ) );
}

/**
 * Product with this SKU (not in the trash), apart from $exclude.
 *
 * @param string $sku     SKU.
 * @param int    $exclude Product ID to skip.
 * @return int Product ID or 0.
 */
function memorial_plugin_product_by_sku( $sku, $exclude = 0 ) {
	global $wpdb;
	$sku = memorial_plugin_sku_clean( $sku );
	if ( '' === $sku ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a lookup by a meta value, no cache needed.
	$id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			WHERE p.post_type = 'mem_product' AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
			AND m.meta_key = '_mem_sku' AND m.meta_value = %s AND p.ID <> %d ORDER BY p.ID LIMIT 1",
			$sku,
			(int) $exclude
		)
	);
	return (int) $id;
}

/**
 * The error of a SKU typed for a product: empty is allowed (the SKU stays as it was), a SKU of another
 * product is not.
 *
 * @param mixed $sku SKU.
 * @param int   $id  Product ID (0 — a new one).
 * @return WP_Error|null
 */
function memorial_plugin_sku_error( $sku, $id = 0 ) {
	$sku   = memorial_plugin_sku_clean( $sku );
	$other = '' !== $sku ? memorial_plugin_product_by_sku( $sku, (int) $id ) : 0;
	if ( ! $other ) {
		return null;
	}
	return new WP_Error(
		'memorial_plugin_sku_taken',
		/* translators: 1: SKU, 2: title of the other product. */
		sprintf( __( 'The SKU %1$s is already used by “%2$s”.', 'memorial-plugin' ), $sku, get_the_title( $other ) ),
		array( 'status' => 400 )
	);
}

/**
 * A product saved for the first time gets the SKU “M-<ID>”.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function memorial_plugin_sku_assign( $post_id, $post ) {
	if ( 'mem_product' !== $post->post_type || in_array( $post->post_status, array( 'auto-draft', 'inherit', 'trash' ), true ) ) {
		return;
	}
	if ( '' === (string) get_post_meta( $post_id, '_mem_sku', true ) ) {
		update_post_meta( $post_id, '_mem_sku', 'M-' . $post_id );
	}
}
add_action( 'wp_after_insert_post', 'memorial_plugin_sku_assign', 10, 2 );

/**
 * Once: the SKU “M-<ID>” for every product made before 2.2. The flag is set before the run, the log
 * (option memorial_plugin_sku_log, not autoloaded) lists the products that got a SKU — to roll back,
 * delete their _mem_sku.
 */
function memorial_plugin_sku_migrate() {
	if ( get_option( 'memorial_plugin_sku_1' ) ) {
		return;
	}
	update_option( 'memorial_plugin_sku_1', 1 );
	$log = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'mem_product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	) as $id ) {
		if ( '' === (string) get_post_meta( $id, '_mem_sku', true ) ) {
			add_post_meta( $id, '_mem_sku', 'M-' . $id, true );
			$log[] = (int) $id;
		}
	}
	update_option(
		'memorial_plugin_sku_log',
		array(
			'time' => time(),
			'ids'  => $log,
		),
		false
	);
}
add_action( 'init', 'memorial_plugin_sku_migrate', 20 );

/**
 * Columns of the table: key => header. Specifications follow as “Spec: <name>”.
 *
 * @return array<string,string>
 */
function memorial_plugin_io_columns() {
	return array(
		'sku'    => __( 'SKU', 'memorial-plugin' ),
		'type'   => __( 'Type', 'memorial-plugin' ),
		'cats'   => __( 'Categories', 'memorial-plugin' ),
		'title'  => __( 'Product title', 'memorial-plugin' ),
		'price'  => __( 'Price', 'memorial-plugin' ),
		'from'   => __( 'Price “from”', 'memorial-plugin' ),
		'old'    => __( 'Old price', 'memorial-plugin' ),
		'unit'   => __( 'Unit', 'memorial-plugin' ),
		'sub'    => __( 'Line under the title', 'memorial-plugin' ),
		'order'  => __( 'Made to order', 'memorial-plugin' ),
		'status' => __( 'On the site', 'memorial-plugin' ),
		'desc'   => __( 'Description', 'memorial-plugin' ),
		'photos' => __( 'Photos', 'memorial-plugin' ),
		'alts'   => __( 'Alt', 'memorial-plugin' ),
	);
}

/**
 * Header of a specification column.
 *
 * @param string $label Specification.
 * @return string
 */
function memorial_plugin_io_spec_header( $label ) {
	/* translators: %s: name of a specification, e.g. Height. */
	return sprintf( __( 'Spec: %s', 'memorial-plugin' ), $label );
}

/**
 * “Yes” in the table.
 *
 * @return string
 */
function memorial_plugin_io_yes() {
	return __( 'yes', 'memorial-plugin' );
}

/**
 * Categories of a type as paths below the type: term ID => “Live / Roses”.
 *
 * @param int $type Type ID.
 * @return array<int,string>
 */
function memorial_plugin_io_cat_paths( $type ) {
	$out  = array();
	$walk = static function ( $parent, $prefix ) use ( &$walk, &$out ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'mem_cat',
				'parent'     => $parent,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
			$path                      = $prefix . html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
			$out[ (int) $t->term_id ] = $path;
			$walk( (int) $t->term_id, $path . ' / ' );
		}
	};
	$walk( (int) $type, '' );
	return $out;
}

/**
 * File name of a photo as the table shows it: the uploaded file without “-scaled”.
 *
 * @param int $att Attachment ID.
 * @return string
 */
function memorial_plugin_io_photo_name( $att ) {
	$file = (string) get_attached_file( $att );
	return '' !== $file ? preg_replace( '/-scaled(\.[a-z0-9]+)$/i', '$1', wp_basename( $file ) ) : '';
}

/**
 * The table of products.
 *
 * @param array $args type — only this type (0 — all), ids — only these products.
 * @return array{head:string[],keys:string[],rows:array<int,array<int,string|int>>}
 */
function memorial_plugin_io_table( $args = array() ) {
	$only_type = (int) ( $args['type'] ?? 0 );
	$only_ids  = array_filter( array_map( 'absint', (array) ( $args['ids'] ?? array() ) ) );
	$types     = memorial_plugin_manager_types();
	$type_pos  = array();
	$names     = array();
	$paths     = array();
	$spec_cols = array();
	foreach ( $types as $i => $t ) {
		$tid              = (int) $t->term_id;
		$type_pos[ $tid ] = $i;
		$names[ $tid ]    = html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
		$paths[ $tid ]    = memorial_plugin_io_cat_paths( $tid );
		if ( ! $only_type || $only_type === $tid ) {
			foreach ( memorial_plugin_type_fields( $tid ) as $f ) {
				$spec_cols[ $f['label'] ] = true;
			}
		}
	}
	$query = array(
		'post_type'      => 'mem_product',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page' => -1,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'ID'         => 'ASC',
		),
	);
	if ( $only_ids ) {
		$query['post__in'] = $only_ids;
	}
	$posts = array();
	foreach ( get_posts( $query ) as $p ) {
		$type = memorial_plugin_product_type( $p->ID );
		if ( $only_type && $type !== $only_type ) {
			continue;
		}
		foreach ( array_keys( memorial_plugin_product_spec_map( $p->ID ) ) as $label ) {
			if ( '' !== $label ) {
				$spec_cols[ $label ] = true;
			}
		}
		$posts[] = array( $p, $type );
	}
	// By type as in “Types and categories”, inside a type — as on the site.
	usort(
		$posts,
		static fn( $a, $b ) => array( $type_pos[ $a[1] ] ?? PHP_INT_MAX, $a[0]->menu_order, $a[0]->ID ) <=> array( $type_pos[ $b[1] ] ?? PHP_INT_MAX, $b[0]->menu_order, $b[0]->ID )
	);
	$cols  = memorial_plugin_io_columns();
	$specs = array_keys( $spec_cols );
	$yes   = memorial_plugin_io_yes();
	$rows  = array();
	foreach ( $posts as [ $p, $type ] ) {
		$m      = static fn( $k ) => (string) get_post_meta( $p->ID, $k, true );
		$cats   = wp_get_object_terms( $p->ID, 'mem_cat', array( 'fields' => 'ids' ) );
		$photos = memorial_plugin_product_photos( $p->ID );
		$desc   = memorial_plugin_product_desc( $p );
		$row    = array(
			'sku'    => $m( '_mem_sku' ),
			'type'   => $names[ $type ] ?? '',
			'cats'   => implode( "\n", array_filter( array_map( static fn( $c ) => $paths[ $type ][ (int) $c ] ?? '', is_wp_error( $cats ) ? array() : $cats ) ) ),
			'title'  => html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ),
			'price'  => (int) $m( '_mem_price' ) ? (int) $m( '_mem_price' ) : '',
			'from'   => '1' === $m( '_mem_price_from' ) ? $yes : '',
			'old'    => (int) $m( '_mem_price_old' ) ? (int) $m( '_mem_price_old' ) : '',
			'unit'   => $m( '_mem_unit' ),
			'sub'    => $m( '_mem_sub' ),
			'order'  => $m( '_mem_on_order' ) ? $yes : '',
			'status' => 'publish' === $p->post_status ? $yes : __( 'no', 'memorial-plugin' ),
			'desc'   => $desc['text'],
			'photos' => implode( "\n", array_map( 'memorial_plugin_io_photo_name', $photos ) ),
			'alts'   => implode( "\n", array_map( static fn( $a ) => str_replace( array( "\r", "\n" ), ' ', (string) get_post_meta( $a, '_wp_attachment_image_alt', true ) ), $photos ) ),
		);
		$map    = memorial_plugin_product_spec_map( $p->ID );
		$line   = array_values( $row );
		foreach ( $specs as $s ) {
			$line[] = (string) ( $map[ $s ] ?? '' );
		}
		$rows[] = $line;
	}
	return array(
		'head' => array_merge( array_values( $cols ), array_map( 'memorial_plugin_io_spec_header', $specs ) ),
		'keys' => array_merge( array_keys( $cols ), array_map( static fn( $s ) => 'spec:' . $s, $specs ) ),
		'rows' => $rows,
	);
}

/**
 * CSV: semicolon, UTF-8 with BOM — Excel opens it as is. A value starting with = + - @ gets an apostrophe,
 * so Excel does not run it as a formula (the import removes the apostrophe).
 *
 * @param array $sheet head and rows.
 * @return string
 */
function memorial_plugin_io_csv( $sheet ) {
	$csv = "\xEF\xBB\xBF";
	foreach ( array_merge( array( $sheet['head'] ), $sheet['rows'] ) as $row ) {
		$csv .= implode(
			';',
			array_map(
				static function ( $v ) {
					$v = (string) $v;
					if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) && '-' !== $v ) {
						$v = "'" . $v;
					}
					return '"' . str_replace( '"', '""', $v ) . '"';
				},
				$row
			)
		) . "\r\n";
	}
	return $csv;
}

/**
 * Column letter of a column number (0 — A).
 *
 * @param int $n Column number.
 * @return string
 */
function memorial_plugin_io_col( $n ) {
	$s = '';
	for ( ++$n; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
		$s = chr( 65 + ( $n - 1 ) % 26 ) . $s;
	}
	return $s;
}

/**
 * Text for XML: without the characters XML does not allow.
 *
 * @param string $v Text.
 * @return string
 */
function memorial_plugin_io_xml( $v ) {
	$v = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $v );
	return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/**
 * Excel workbook (.xlsx) without libraries: ZIP of a few XML files. Strings are written in the cells
 * (inline), whole numbers — as numbers. The first row is bold and stays on top when scrolling.
 *
 * @param array $sheets name => [ head, rows, widths? ].
 * @return string|WP_Error Bytes of the file.
 */
function memorial_plugin_io_xlsx( $sheets ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'memorial_plugin_xlsx', __( 'The server cannot make Excel files (no ZIP support). Export to CSV.', 'memorial-plugin' ) );
	}
	$files = array(
		'_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
		'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE6EBDF"/><bgColor indexed="64"/></patternFill></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
			. '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
			. '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
	);
	$types = '';
	$book  = '';
	$rels  = '';
	$i     = 0;
	foreach ( $sheets as $name => $sheet ) {
		++$i;
		$xml    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$xml   .= '<sheetViews><sheetView workbookViewId="0"' . ( 1 === $i ? ' tabSelected="1"' : '' ) . '><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
		$widths = $sheet['widths'] ?? array();
		$xml   .= '<cols>';
		foreach ( $sheet['head'] as $c => $h ) {
			$w    = $widths[ $c ] ?? min( 60, max( 12, mb_strlen( (string) $h ) + 4 ) );
			$xml .= '<col min="' . ( $c + 1 ) . '" max="' . ( $c + 1 ) . '" width="' . (int) $w . '" customWidth="1"/>';
		}
		$xml .= '</cols><sheetData>';
		foreach ( array_merge( array( $sheet['head'] ), $sheet['rows'] ) as $r => $row ) {
			$xml .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( $row ) as $c => $v ) {
				$ref = memorial_plugin_io_col( $c ) . ( $r + 1 );
				if ( is_int( $v ) && $r ) {
					$xml .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
				} elseif ( '' !== (string) $v ) {
					// Text cells are formatted as text (@): Excel does not turn “1-2” into a date when edited.
					$xml .= '<c r="' . $ref . '" t="inlineStr" s="' . ( $r ? 2 : 1 ) . '"><is><t xml:space="preserve">' . memorial_plugin_io_xml( $v ) . '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}
		$xml .= '</sheetData></worksheet>';

		$files[ 'xl/worksheets/sheet' . $i . '.xml' ] = $xml;
		$types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		$book  .= '<sheet name="' . memorial_plugin_io_xml( mb_substr( preg_replace( '~[\\\\/?*\[\]:]~', ' ', (string) $name ), 0, 31 ) ) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
		$rels  .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
	}
	$files['[Content_Types].xml']        = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $types . '</Types>';
	$files['xl/workbook.xml']            = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $book . '</sheets></workbook>';
	$files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '<Relationship Id="rId' . ( $i + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';

	return memorial_plugin_io_zip( $files );
}

/**
 * ZIP of files given as name => contents.
 *
 * @param array<string,string> $files Files.
 * @return string|WP_Error Bytes of the archive.
 */
function memorial_plugin_io_zip( $files ) {
	if ( ! function_exists( 'wp_tempnam' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$tmp = wp_tempnam( 'memorial-io' );
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
		wp_delete_file( $tmp );
		return new WP_Error( 'memorial_plugin_zip', __( 'Could not make the file.', 'memorial-plugin' ) );
	}
	foreach ( $files as $name => $body ) {
		$zip->addFromString( $name, $body );
	}
	$zip->close();
	$bytes = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local temporary file.
	wp_delete_file( $tmp );
	return $bytes;
}

/**
 * Export: the table of products as a file.
 *
 * @param array $input type, ids, format (csv|xlsx).
 * @return array|WP_Error filename, count, mime, file (base64); csv — the text of a CSV.
 */
function memorial_plugin_io_export( $input = array() ) {
	$sheet  = memorial_plugin_io_table( $input );
	$format = 'xlsx' === ( $input['format'] ?? 'csv' ) ? 'xlsx' : 'csv';
	$name   = 'catalog-' . gmdate( 'Y-m-d' ) . '.' . $format;
	if ( 'csv' === $format ) {
		$csv = memorial_plugin_io_csv( $sheet );
		return array(
			'filename' => $name,
			'count'    => count( $sheet['rows'] ),
			'mime'     => 'text/csv;charset=utf-8',
			'csv'      => $csv,
			'file'     => base64_encode( $csv ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a file for the browser.
		);
	}
	$sheet['widths'] = array_map(
		static fn( $k ) => array(
			'title'  => 34,
			'desc'   => 50,
			'photos' => 30,
			'alts'   => 40,
			'cats'   => 22,
			'sub'    => 24,
		)[ $k ] ?? null,
		$sheet['keys']
	);
	$sheet['widths'] = array_filter( $sheet['widths'] );
	$bytes           = memorial_plugin_io_xlsx( array( __( 'Products', 'memorial-plugin' ) => $sheet ) );
	if ( is_wp_error( $bytes ) ) {
		return $bytes;
	}
	return array(
		'filename' => $name,
		'count'    => count( $sheet['rows'] ),
		'mime'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'file'     => base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a file for the browser.
	);
}
