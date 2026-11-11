<?php
/**
 * Content converter: [mem_…] shortcodes in posts → blocks and inline values. Run by a button, never automatically.
 *
 * Screen: Memorial → Convert to blocks (src/convert). It lists the posts that still contain shortcodes,
 * shows a diff before converting, converts the chosen posts in batches and can roll a post back.
 *
 * What changes:
 *  - a Shortcode block (or a paragraph) with only block-level shortcodes → the plugin blocks
 *    ([mem_cta title="…"] → <!-- wp:memorial-plugin/cta {"title":"…"} /-->);
 *  - inline shortcodes in text ([mem_cat_price cat="…"], [mem_years]…) → inline values
 *    (blocks/content/inline-values.php);
 *  - shortcodes in SEOPress fields → %%mem_…%% variables where the SEO registry has a match
 *    (seo/seo-registry.php).
 * Anything else stays as it is. Only the touched parts of the content change — the rest is kept byte for byte.
 *
 * Rollback: before saving, the current content is stored as a revision; its ID is kept in the post meta
 * _memorial_plugin_converted, and “Roll back” restores that revision.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Posts per conversion request. */
const MEMORIAL_PLUGIN_CONVERT_BATCH = 10;

/**
 * Block-level shortcode tags => block slug.
 *
 * @return array<string,string>
 */
function memorial_plugin_convert_block_tags() {
	$tags = array();
	foreach ( memorial_plugin_content_blocks() as $slug => $def ) {
		$tags[ $def['tag'] ] = $slug;
	}
	return $tags;
}

/**
 * Inline shortcode tags => inline value key.
 *
 * @return array<string,string>
 */
function memorial_plugin_convert_inline_tags() {
	$tags = array();
	foreach ( memorial_plugin_inline_values() as $key => $def ) {
		$tags[ $def['tag'] ] = $key;
	}
	return $tags;
}

/**
 * Regular expression for the given shortcode tags (as WordPress parses them).
 *
 * @param string[] $tags Tags.
 * @return string Pattern with delimiters.
 */
function memorial_plugin_convert_regex( $tags ) {
	return '/' . get_shortcode_regex( $tags ) . '/s';
}

/**
 * One block-level shortcode → serialized plugin block.
 *
 * @param string $tag  Shortcode tag.
 * @param string $atts Raw attribute string.
 * @return string Block markup or '' if the tag is not a block-level one.
 */
function memorial_plugin_convert_shortcode_to_block( $tag, $atts ) {
	$slug = memorial_plugin_convert_block_tags()[ $tag ] ?? '';
	if ( '' === $slug ) {
		return '';
	}
	$def    = memorial_plugin_content_blocks()[ $slug ];
	$parsed = shortcode_parse_atts( $atts );
	$parsed = is_array( $parsed ) ? $parsed : array();
	$attrs  = array();
	foreach ( $def['attrs'] as $name => $attr ) {
		$keys = array_merge( array( $attr['sc'] ), $attr['alias'] ?? array() );
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $parsed ) ) {
				$value = memorial_plugin_content_block_attr_value( $attr, $parsed[ $key ] );
				if ( $value !== $attr['default'] ) {
					$attrs[ $name ] = $value;
				}
				break;
			}
		}
	}
	return serialize_block(
		array(
			'blockName'    => 'memorial-plugin/' . $slug,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
}

/**
 * Text that consists only of block-level shortcodes → blocks (one per line), or null if there is anything else.
 *
 * @param string $text Inner text of a Shortcode block or paragraph.
 * @return string|null
 */
function memorial_plugin_convert_block_text( $text ) {
	$tags = array_keys( memorial_plugin_convert_block_tags() );
	if ( ! preg_match_all( memorial_plugin_convert_regex( $tags ), $text, $all, PREG_SET_ORDER ) ) {
		return null;
	}
	$rest = trim( wp_strip_all_tags( preg_replace( memorial_plugin_convert_regex( $tags ), '', $text ) ) );
	if ( '' !== str_replace( "\u{00A0}", '', $rest ) ) {
		return null;
	}
	$blocks = array();
	foreach ( $all as $m ) {
		if ( '[' === $m[1] && ']' === $m[6] ) {
			return null; // [[escaped]] shortcode — leave the text alone.
		}
		$blocks[] = memorial_plugin_convert_shortcode_to_block( $m[2], $m[3] );
	}
	return implode( "\n\n", $blocks );
}

/**
 * Inline shortcodes in a piece of HTML → inline value spans.
 *
 * @param string $html HTML of a text block.
 * @return string
 */
function memorial_plugin_convert_inline( $html ) {
	$map = memorial_plugin_convert_inline_tags();
	return (string) preg_replace_callback(
		memorial_plugin_convert_regex( array_keys( $map ) ),
		static function ( $m ) use ( $map ) {
			if ( '[' === $m[1] && ']' === $m[6] ) {
				return $m[0];
			}
			$atts = shortcode_parse_atts( $m[3] );
			$atts = is_array( $atts ) ? array_map( 'strval', $atts ) : array();
			if ( isset( $m[5] ) && '' !== $m[5] ) {
				$atts['content'] = wp_strip_all_tags( $m[5] );
			}
			$key  = $map[ $m[2] ];
			$text = memorial_plugin_inline_value_html( $key, $atts );
			// The value for editors only (“section not found”) or nothing — keep the shortcode text visible.
			if ( null === $text || '' === trim( wp_strip_all_tags( $text ) ) || false !== strpos( $text, '<mark' ) ) {
				$text = esc_html( $m[0] );
			}
			return memorial_plugin_inline_value_span( $key, $atts, $text );
		},
		$html
	);
}

/**
 * Convert post content. Only the changed parts differ from the original.
 *
 * @param string $content Post content.
 * @return string
 */
function memorial_plugin_convert_content( $content ) {
	// 1. Shortcode blocks and paragraphs that hold only block-level shortcodes.
	$content = (string) preg_replace_callback(
		'~<!-- wp:(shortcode|paragraph)(?:\s+\{.*?\})?\s*-->\s*(.*?)\s*<!-- /wp:\1 -->~s',
		static function ( $m ) {
			$inner = 'paragraph' === $m[1] ? preg_replace( '~^<p\b[^>]*>(.*)</p>$~s', '$1', trim( $m[2] ) ) : $m[2];
			$out   = memorial_plugin_convert_block_text( (string) $inner );
			return null === $out ? $m[0] : $out;
		},
		$content
	);

	// 2. Inline shortcodes — in text, outside block comments and outside Shortcode blocks.
	$parts = preg_split( '~(<!-- wp:shortcode\s*-->.*?<!-- /wp:shortcode -->|<!--.*?-->)~s', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $parts as $i => $part ) {
		if ( 1 === $i % 2 || false === strpos( $part, '[mem_' ) ) {
			continue;
		}
		$parts[ $i ] = memorial_plugin_convert_inline( $part );
	}
	return implode( '', $parts );
}

/**
 * Shortcodes still in the content: tag => count.
 *
 * @param string $content Post content.
 * @return array<string,int>
 */
function memorial_plugin_convert_count( $content ) {
	$tags = array_merge( array_keys( memorial_plugin_convert_block_tags() ), array_keys( memorial_plugin_convert_inline_tags() ) );
	$out  = array();
	if ( preg_match_all( memorial_plugin_convert_regex( $tags ), (string) $content, $all, PREG_SET_ORDER ) ) {
		foreach ( $all as $m ) {
			$out[ $m[2] ] = ( $out[ $m[2] ] ?? 0 ) + 1;
		}
	}
	ksort( $out );
	return $out;
}

/**
 * Post types the converter looks at.
 *
 * @return string[]
 */
function memorial_plugin_convert_post_types() {
	$types = array_values( get_post_types( array( 'show_ui' => true ) ) );
	return array_values( array_diff( $types, array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles' ) ) );
}

/**
 * Posts that contain [mem_ shortcodes or were converted (can be rolled back).
 *
 * @return array<int,array>
 */
function memorial_plugin_convert_items() {
	global $wpdb;
	$types    = memorial_plugin_convert_post_types();
	$statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );
	$in_types = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$in_stats = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are built above.
	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- admin screen, fresh data needed.
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($in_types) AND post_status IN ($in_stats)
			AND ( post_content LIKE %s
				OR ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s )
				OR ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value LIKE %s ) )
			ORDER BY post_type, menu_order, post_title",
			array_merge(
				$types,
				$statuses,
				array(
					'%' . $wpdb->esc_like( '[mem_' ) . '%',
					'_memorial_plugin_converted',
					$wpdb->esc_like( '_seopress_' ) . '%',
					'%' . $wpdb->esc_like( '[mem_' ) . '%',
				)
			)
		)
	);
	// phpcs:enable
	$items = array();
	foreach ( array_map( 'intval', (array) $ids ) as $id ) {
		if ( ! current_user_can( 'edit_post', $id ) ) {
			continue;
		}
		$post  = get_post( $id );
		$text  = memorial_plugin_convert_text( $post );
		$count = memorial_plugin_convert_count( $text );
		$conv  = get_post_meta( $id, '_memorial_plugin_converted', true );
		$type  = get_post_type_object( $post->post_type );
		if ( ! $count && ! $conv ) {
			continue;
		}
		$items[] = array(
			'id'         => $id,
			'title'      => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'type'       => $post->post_type,
			'type_label' => $type ? $type->labels->singular_name : $post->post_type,
			'status'     => $post->post_status,
			'link'       => (string) get_permalink( $post ),
			'edit'       => (string) get_edit_post_link( $post, 'raw' ),
			'shortcodes' => $count,
			'total'      => array_sum( $count ),
			'pending'    => memorial_plugin_convert_text( $post, true ) !== $text,
			'converted'  => is_array( $conv ) ? (int) ( $conv['time'] ?? 0 ) : 0,
		);
	}
	return $items;
}

/**
 * SEO fields of a post that hold [mem_ shortcodes: meta key => value.
 *
 * @param int $id Post ID.
 * @return array<string,string>
 */
function memorial_plugin_convert_seo_meta( $id ) {
	$out = array();
	foreach ( array_keys( memorial_plugin_seo_fields() ) as $key ) {
		$value = get_post_meta( $id, $key, true );
		if ( is_string( $value ) && false !== strpos( $value, '[mem_' ) ) {
			$out[ $key ] = $value;
		}
	}
	return $out;
}

/**
 * Everything the converter changes in a post as one text (content, then SEO fields) — for counting and diff.
 *
 * @param WP_Post $post      Post.
 * @param bool    $converted Text after converting.
 * @return string
 */
function memorial_plugin_convert_text( $post, $converted = false ) {
	$text = $converted ? memorial_plugin_convert_content( $post->post_content ) : (string) $post->post_content;
	foreach ( memorial_plugin_convert_seo_meta( $post->ID ) as $key => $value ) {
		$label = memorial_plugin_seo_fields()[ $key ];
		$text .= "\n\n— " . $label . ":\n" . ( $converted ? memorial_plugin_seo_convert( $value ) : $value );
	}
	return $text;
}

/**
 * Preview: diff of the current and converted content.
 *
 * @param int $id Post ID.
 * @return array{changed:bool,diff:string,before:array,after:array}
 */
function memorial_plugin_convert_preview( $id ) {
	$post   = get_post( $id );
	$before = memorial_plugin_convert_text( $post );
	$after  = memorial_plugin_convert_text( $post, true );
	$diff   = '';
	if ( $before !== $after ) {
		$diff = (string) wp_text_diff(
			$before,
			$after,
			array(
				'title_left'      => __( 'Now', 'memorial-plugin' ),
				'title_right'     => __( 'After converting', 'memorial-plugin' ),
				'show_split_view' => true,
			)
		);
	}
	return array(
		'changed' => $before !== $after,
		'diff'    => $diff,
		'before'  => memorial_plugin_convert_count( $before ),
		'after'   => memorial_plugin_convert_count( $after ),
	);
}

/**
 * Convert one post: store the current content as a revision, then save the converted content.
 *
 * @param int $id Post ID.
 * @return string Result: converted, unchanged, no-revision or error message.
 */
function memorial_plugin_convert_post( $id ) {
	$post = get_post( $id );
	if ( ! $post ) {
		return 'not-found';
	}
	$after = memorial_plugin_convert_content( $post->post_content );
	$meta  = array();
	foreach ( memorial_plugin_convert_seo_meta( $id ) as $key => $value ) {
		$new = memorial_plugin_seo_convert( $value );
		if ( $new !== $value ) {
			$meta[ $key ] = array( $value, $new );
		}
	}
	if ( $after === $post->post_content && ! $meta ) {
		return 'unchanged';
	}
	$revision = _wp_put_post_revision( $post );
	if ( ! $revision || is_wp_error( $revision ) ) {
		return 'no-revision';
	}
	if ( $after !== $post->post_content ) {
		$saved = wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => wp_slash( $after ),
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved->get_error_message();
		}
	}
	foreach ( $meta as $key => $pair ) {
		update_post_meta( $id, $key, wp_slash( $pair[1] ) );
	}
	update_post_meta(
		$id,
		'_memorial_plugin_converted',
		array(
			'revision' => (int) $revision,
			'meta'     => array_map( static fn( $pair ) => $pair[0], $meta ),
			'time'     => time(),
			'user'     => get_current_user_id(),
		)
	);
	return 'converted';
}

/**
 * Roll back a converted post to the content it had before converting.
 *
 * @param int $id Post ID.
 * @return string Result: restored, not-converted or no-revision.
 */
function memorial_plugin_convert_rollback( $id ) {
	$conv = get_post_meta( $id, '_memorial_plugin_converted', true );
	$rev  = is_array( $conv ) ? (int) ( $conv['revision'] ?? 0 ) : 0;
	if ( ! $rev ) {
		return 'not-converted';
	}
	$revision = wp_get_post_revision( $rev );
	if ( ! $revision || (int) $revision->post_parent !== (int) $id ) {
		return 'no-revision';
	}
	wp_restore_post_revision( $rev, array( 'post_content' ) );
	foreach ( (array) ( $conv['meta'] ?? array() ) as $key => $value ) {
		update_post_meta( $id, $key, wp_slash( (string) $value ) );
	}
	delete_post_meta( $id, '_memorial_plugin_converted' );
	return 'restored';
}

/**
 * Permission for one post.
 *
 * @param WP_REST_Request $request Request with id.
 * @return bool
 */
function memorial_plugin_convert_can_edit( WP_REST_Request $request ) {
	return current_user_can( 'edit_post', (int) $request['id'] );
}

/**
 * REST routes of the converter (memorial-plugin/v1/convert…).
 */
function memorial_plugin_convert_rest() {
	$ns = 'memorial-plugin/v1';
	register_rest_route(
		$ns,
		'/convert',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
				'callback'            => static function () {
					return rest_ensure_response( memorial_plugin_convert_items() );
				},
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
				'args'                => array(
					'ids' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
						'maxItems' => MEMORIAL_PLUGIN_CONVERT_BATCH,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$out = array();
					foreach ( array_unique( array_map( 'absint', (array) $request['ids'] ) ) as $id ) {
						$out[ $id ] = current_user_can( 'edit_post', $id ) ? memorial_plugin_convert_post( $id ) : 'forbidden';
					}
					return rest_ensure_response( $out );
				},
			),
		)
	);
	register_rest_route(
		$ns,
		'/convert/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => 'memorial_plugin_convert_can_edit',
			'callback'            => static function ( WP_REST_Request $request ) {
				return rest_ensure_response( memorial_plugin_convert_preview( (int) $request['id'] ) );
			},
		)
	);
	register_rest_route(
		$ns,
		'/convert/(?P<id>\d+)/rollback',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'memorial_plugin_convert_can_edit',
			'callback'            => static function ( WP_REST_Request $request ) {
				return rest_ensure_response( array( 'result' => memorial_plugin_convert_rollback( (int) $request['id'] ) ) );
			},
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_convert_rest' );

/**
 * Screen Memorial → Convert to blocks.
 */
function memorial_plugin_convert_menu() {
	$hook = add_submenu_page(
		'mem-contacts',
		__( 'Convert to blocks', 'memorial-plugin' ),
		__( 'Convert to blocks', 'memorial-plugin' ),
		'edit_pages',
		'memorial-plugin-convert',
		'memorial_plugin_convert_page'
	);
	if ( $hook ) {
		add_action( 'load-' . $hook, 'memorial_plugin_convert_assets' );
	}
}
add_action( 'admin_menu', 'memorial_plugin_convert_menu', 20 );

/**
 * Scripts and styles of the screen (only there).
 */
function memorial_plugin_convert_assets() {
	add_action(
		'admin_enqueue_scripts',
		static function () {
			$asset_file = MEMORIAL_PLUGIN_DIR . '/build/convert/index.asset.php';
			if ( ! is_readable( $asset_file ) ) {
				return;
			}
			$asset = require $asset_file;
			wp_enqueue_script(
				'memorial-plugin-convert',
				MEMORIAL_PLUGIN_URL . '/build/convert/index.js',
				$asset['dependencies'],
				$asset['version'],
				array( 'in_footer' => true )
			);
			wp_set_script_translations( 'memorial-plugin-convert', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
			wp_add_inline_script(
				'memorial-plugin-convert',
				'window.memorialPluginConvert = ' . wp_json_encode( array( 'batch' => MEMORIAL_PLUGIN_CONVERT_BATCH ) ) . ';',
				'before'
			);
			wp_enqueue_style( 'wp-components' );
			// The diff table looks like on the Revisions screen.
			wp_enqueue_style( 'revisions' );
		}
	);
}

/**
 * Container for the screen app.
 */
function memorial_plugin_convert_page() {
	if ( ! is_readable( MEMORIAL_PLUGIN_DIR . '/build/convert/index.asset.php' ) ) {
		echo '<div class="wrap"><p>' . esc_html__( 'The JavaScript build is missing. Run npm run build.', 'memorial-plugin' ) . '</p></div>';
		return;
	}
	echo '<div id="memorial-plugin-convert" class="wrap"></div>';
}
