<?php
/**
 * Fields of products, works and pages in the block editor: the meta is registered for REST and is edited
 * in the editor sidebar (src/editor-meta, PluginDocumentSettingPanel) instead of the classic meta boxes.
 *
 * Product: price, old price, unit, “from”, made to order, line under the title, specifications, extra photos.
 * Work: photos, plot size, year, what was done with prices, “show in examples”.
 * Page: products on the page (catalog category), the “Good to know” question and short answer.
 *
 * The stored format is the same as before (strings “1”/“”, “12,34,56”, [[name, price], …]), so the site
 * output and the old data need no changes.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta of each post type: key => [type, sanitize callback, extra schema].
 *
 * @return array<string,array<string,array>>
 */
function memorial_plugin_editor_meta() {
	$flag  = static fn( $v ) => empty( $v ) ? '' : '1';
	$money = 'memorial_plugin_money';
	$ids   = static fn( $v ) => implode( ',', array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $v ) ) ) ) ) );
	$text  = static fn( $v ) => trim( sanitize_text_field( (string) $v ) );
	return array(
		'mem_product' => array(
			'_mem_price'      => array( 'integer', $money ),
			'_mem_price_old'  => array( 'integer', $money ),
			'_mem_unit'       => array( 'string', $text ),
			'_mem_price_from' => array( 'string', $flag ),
			'_mem_on_order'   => array( 'string', $flag ),
			'_mem_sub'        => array( 'string', $text ),
			'_mem_specs'      => array( 'string', 'memorial_plugin_clean_specs' ),
			'_mem_gallery'    => array( 'string', $ids ),
		),
		'mem_work'    => array(
			'_mem_photos'  => array( 'string', $ids ),
			'_mem_size'    => array( 'string', $text ),
			'_mem_year'    => array( 'integer', 'memorial_plugin_work_year' ),
			'_mem_example' => array( 'string', $flag ),
			'_mem_rows'    => array(
				'array',
				'memorial_plugin_work_rows',
				array(
					'items' => array(
						'type'     => 'array',
						'items'    => array( 'type' => array( 'string', 'integer' ) ),
						'minItems' => 2,
						'maxItems' => 2,
					),
				),
			),
		),
		'page'        => array(
			'_mem_products' => array( 'string', 'memorial_plugin_page_products_value' ),
			'_mem_q'        => array( 'string', $text ),
			'_mem_qa'       => array( 'string', static fn( $v ) => trim( sanitize_textarea_field( (string) $v ) ) ),
		),
	);
}

/**
 * Year of a work: 1990 … next year, otherwise 0 (not shown).
 *
 * @param mixed $v Value.
 * @return int
 */
function memorial_plugin_work_year( $v ) {
	$y = absint( $v );
	return $y >= 1990 && $y <= (int) gmdate( 'Y' ) + 1 ? $y : 0;
}

/**
 * Rows “what was done — price” of a work: [[name, price], …] without empty rows.
 *
 * @param mixed $v Value.
 * @return array<int,array{0:string,1:int}>
 */
function memorial_plugin_work_rows( $v ) {
	$rows = array();
	foreach ( (array) $v as $r ) {
		$r = array_values( (array) $r );
		$n = trim( sanitize_text_field( (string) ( $r[0] ?? '' ) ) );
		$p = absint( preg_replace( '/\D/', '', (string) ( $r[1] ?? '' ) ) );
		if ( '' !== $n || $p ) {
			$rows[] = array( $n, $p );
		}
	}
	return $rows;
}

/**
 * “Products on the page”: '' (none), 'all' (catalog home with category tiles) or a category ID.
 *
 * @param mixed $v Value.
 * @return string
 */
function memorial_plugin_page_products_value( $v ) {
	$v = sanitize_key( (string) $v );
	if ( 'all' === $v ) {
		return 'all';
	}
	$id = absint( $v );
	return $id && term_exists( $id, 'mem_cat' ) ? (string) $id : '';
}

/**
 * Register the meta for REST (the editor reads and saves it with the post).
 */
function memorial_plugin_editor_meta_register() {
	foreach ( memorial_plugin_editor_meta() as $type => $keys ) {
		if ( 'page' !== $type ) {
			add_post_type_support( $type, 'custom-fields' );
		}
		foreach ( $keys as $key => $def ) {
			$schema = array( 'type' => $def[0] );
			if ( isset( $def[2] ) ) {
				$schema = array_merge( $schema, $def[2] );
			}
			register_post_meta(
				$type,
				$key,
				array(
					'type'              => $def[0],
					'single'            => true,
					'default'           => 'array' === $def[0] ? array() : ( 'integer' === $def[0] ? 0 : '' ),
					'sanitize_callback' => $def[1],
					'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
					'show_in_rest'      => array( 'schema' => $schema ),
				)
			);
		}
	}
}
add_action( 'init', 'memorial_plugin_editor_meta_register', 20 );

/**
 * Empty values are not stored (as with the classic boxes): the site checks “is there a value”.
 *
 * @param int    $meta_id    Meta ID.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Key.
 * @param mixed  $meta_value Value.
 */
function memorial_plugin_editor_meta_cleanup( $meta_id, $post_id, $meta_key, $meta_value ) {
	$type = get_post_type( $post_id );
	$all  = memorial_plugin_editor_meta();
	if ( ! isset( $all[ $type ][ $meta_key ] ) ) {
		return;
	}
	// The price stays even when 0 (“on request”): the catalog sorts by it.
	if ( '_mem_price' !== $meta_key && ( '' === $meta_value || 0 === $meta_value || '0' === $meta_value ) ) {
		delete_post_meta( $post_id, $meta_key );
	}
	// The first photo of a work is its featured image (cards, social networks).
	if ( 'mem_work' === $type && '_mem_photos' === $meta_key ) {
		$first = absint( strtok( (string) $meta_value, ',' ) );
		$first ? set_post_thumbnail( $post_id, $first ) : delete_post_thumbnail( $post_id );
	}
}
add_action( 'added_post_meta', 'memorial_plugin_editor_meta_cleanup', 10, 4 );
add_action( 'updated_post_meta', 'memorial_plugin_editor_meta_cleanup', 10, 4 );

/**
 * Default content of a new product and a new work in the block editor.
 *
 * @param array  $args Post type arguments.
 * @param string $type Post type.
 * @return array
 */
function memorial_plugin_editor_templates( $args, $type ) {
	$templates = array(
		'mem_product' => __( 'Description of the product (optional: with text the product gets its own page)…', 'memorial-plugin' ),
		'mem_work'    => __( 'What the customers wanted and what was done…', 'memorial-plugin' ),
	);
	if ( isset( $templates[ $type ] ) ) {
		$args['template'] = array( array( 'core/paragraph', array( 'placeholder' => $templates[ $type ] ) ) );
	}
	return $args;
}
add_filter( 'register_post_type_args', 'memorial_plugin_editor_templates', 10, 2 );

/**
 * Sidebar panels in the block editor.
 */
function memorial_plugin_editor_meta_assets() {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'mem_product', 'mem_work', 'page' ), true ) ) {
		return;
	}
	$asset_file = MEMORIAL_PLUGIN_DIR . '/build/editor-meta/index.asset.php';
	if ( ! is_readable( $asset_file ) ) {
		return;
	}
	$asset = require $asset_file;
	wp_enqueue_script(
		'memorial-plugin-editor-meta',
		MEMORIAL_PLUGIN_URL . '/build/editor-meta/index.js',
		$asset['dependencies'],
		$asset['version'],
		array( 'in_footer' => true )
	);
	wp_set_script_translations( 'memorial-plugin-editor-meta', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
	if ( is_readable( MEMORIAL_PLUGIN_DIR . '/build/editor-meta/style-index.css' ) ) {
		wp_enqueue_style( 'memorial-plugin-editor-meta', MEMORIAL_PLUGIN_URL . '/build/editor-meta/style-index.css', array(), $asset['version'] );
	}
	$cats = array();
	if ( 'page' === $screen->post_type ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'mem_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$cats  = memorial_plugin_editor_cat_options( is_wp_error( $terms ) ? array() : $terms, 0, 0 );
	}
	wp_add_inline_script(
		'memorial-plugin-editor-meta',
		'window.memorialPluginEditorMeta = ' . wp_json_encode(
			array(
				'cats'     => $cats,
				'maxYear'  => (int) gmdate( 'Y' ) + 1,
				'guide'    => function_exists( 'memorial_plugin_guide_lead' ), // “Good to know” is switched on in Memorial → Features.
				'listsUrl' => array(
					'mem_product' => admin_url( 'edit.php?post_type=mem_product&page=mem-products' ),
					'mem_work'    => admin_url( 'edit.php?post_type=mem_work&page=mem-works' ),
				),
			)
		) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'memorial_plugin_editor_meta_assets' );

/**
 * Category tree as options with “— ” indents.
 *
 * @param WP_Term[] $terms  Terms.
 * @param int       $parent Parent ID.
 * @param int       $depth  Depth.
 * @return array<int,array{value:string,label:string}>
 */
function memorial_plugin_editor_cat_options( $terms, $parent, $depth ) {
	$out = array();
	foreach ( $terms as $t ) {
		if ( (int) $t->parent !== $parent ) {
			continue;
		}
		$out[] = array(
			'value' => (string) $t->term_id,
			'label' => str_repeat( '— ', $depth ) . $t->name,
		);
		$out   = array_merge( $out, memorial_plugin_editor_cat_options( $terms, (int) $t->term_id, $depth + 1 ) );
	}
	return $out;
}
