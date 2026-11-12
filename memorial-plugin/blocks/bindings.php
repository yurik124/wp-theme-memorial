<?php
/**
 * Shared texts of the site edited right on a page: the Block Bindings source memorial-plugin/settings.
 *
 * A core block (paragraph, heading, button) is bound to a field of a «Memorial → …» settings screen:
 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"memorial-plugin/settings","args":{"key":"mem_reviews:intro"}}}}} -->
 * The site shows the current value of the field; in the editor the text can be changed in place and is saved
 * to the setting together with the page (src/bindings). One text — every place that shows it changes.
 *
 * Key = "<option>:<path>", the path goes through groups by field name and through lists by the row id
 * (`_id`, given to every row when it is saved — core/settings.php), not by its position:
 *   mem_home:services.items.k3j9x0a2qe.title
 * “@first” instead of a row id is the first row of the list — the main phone, the main address:
 *   mem_contacts:phones.@first.num
 * Argument "as": "tel" or "mailto" turns the value into a link address (for the URL of a button).
 * Only text fields of the settings schema can be bound (text, textarea, number, email).
 *
 * Rights: editing needs memorial_edit_texts — editors and administrators (decision of 12.11.2026);
 * the settings screens themselves stay for manage_options. Saving checks the version of the setting, so
 * a settings screen open in another tab cannot silently overwrite the change (and the other way round).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

const MEMORIAL_PLUGIN_BINDINGS_SOURCE = 'memorial-plugin/settings';

/**
 * Editors and administrators may edit the shared texts (no role is changed in the database).
 *
 * @param array $allcaps Capabilities of the user.
 * @return array
 */
function memorial_plugin_texts_cap( $allcaps ) {
	if ( ! empty( $allcaps['edit_others_pages'] ) || ! empty( $allcaps['manage_options'] ) ) {
		$allcaps['memorial_edit_texts'] = true;
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'memorial_plugin_texts_cap' );

/**
 * Field of the settings schema and the place of a key: [schema field, option, path segments] or null.
 * A list segment is the row id.
 *
 * @param string $key Key "<option>:<path>".
 * @return array|null
 */
function memorial_plugin_texts_field( $key ) {
	if ( ! preg_match( '/^(mem_[a-z_]+):((?:[a-z0-9_]+|@first)(?:\.(?:[a-z0-9_]+|@first))*)$/', (string) $key, $m ) ) {
		return null;
	}
	$pages = function_exists( 'memorial_plugin_admin_pages' ) ? memorial_plugin_admin_pages() : array();
	if ( empty( $pages[ $m[1] ]['schema'] ) ) {
		return null;
	}
	$fields = call_user_func( $pages[ $m[1] ]['schema'] );
	$path   = explode( '.', $m[2] );
	$field  = null;
	$last   = count( $path ) - 1;
	for ( $i = 0; $i <= $last; $i++ ) {
		$field = $fields[ $path[ $i ] ] ?? null;
		if ( ! $field ) {
			return null;
		}
		if ( 'repeater' === $field['type'] ) {
			++$i; // The next segment is the row id.
			if ( ! isset( $path[ $i ] ) ) {
				return null;
			}
			$fields = $field['fields'];
		} elseif ( 'group' === $field['type'] ) {
			$fields = $field['fields'];
		} elseif ( $last !== $i ) {
			return null;
		}
	}
	if ( ! $field || ! memorial_plugin_texts_bindable( (string) end( $path ), $field ) ) {
		return null;
	}
	return array( $field, $m[1], $path );
}

/**
 * Can a field be shown and edited on a page: texts, numbers, emails — not links, codes, maps or keys
 * (they belong to the settings screens), and not a field marked 'nobind'.
 *
 * @param string $name  Field name.
 * @param array  $field Schema field.
 * @return bool
 */
function memorial_plugin_texts_bindable( $name, $field ) {
	if ( ! in_array( $field['type'] ?? 'text', array( 'text', 'textarea', 'number', 'email' ), true ) || ! empty( $field['nobind'] ) ) {
		return false;
	}
	if ( preg_match( '/(^|_)(link|url|href|embed|org|code|key|token|map|coords?|slug|id|widget|src)$/', $name ) ) {
		return false;
	}
	return ! preg_match( '#^https?://#', (string) ( $field['placeholder'] ?? '' ) );
}

/**
 * Walk to a value: a reference into the array (created along the way only when $create is set).
 *
 * @param array $data   Option value.
 * @param array $path   Path segments (list segments are row ids).
 * @param array $fields Schema fields of the option.
 * @return mixed|null Value, or null when the place does not exist.
 */
function memorial_plugin_texts_get( $data, $path, $fields ) {
	$node  = $data;
	$total = count( $path );
	for ( $i = 0; $i < $total; $i++ ) {
		$field = $fields[ $path[ $i ] ] ?? array();
		$node  = is_array( $node ) ? ( $node[ $path[ $i ] ] ?? null ) : null;
		if ( 'repeater' === ( $field['type'] ?? '' ) ) {
			++$i;
			$row = null;
			foreach ( is_array( $node ) ? $node : array() as $r ) {
				if ( is_array( $r ) && memorial_plugin_texts_row_is( $r, $path[ $i ] ) ) {
					$row = $r;
					break;
				}
			}
			$node   = $row;
			$fields = $field['fields'];
		} elseif ( 'group' === ( $field['type'] ?? '' ) ) {
			$fields = $field['fields'];
		}
		if ( null === $node ) {
			return null;
		}
	}
	return is_scalar( $node ) ? (string) $node : null;
}

/**
 * Is this the row a key names: by its id; “@first” names the first row of the list, so the loop stops
 * on the first row it looks at (the main phone and the main address are the first ones in Memorial → Contacts).
 *
 * @param array  $row Row.
 * @param string $id  Row id or “@first”.
 * @return bool
 */
function memorial_plugin_texts_row_is( $row, $id ) {
	return '@first' === $id || (string) ( $row['_id'] ?? '' ) === $id;
}

/**
 * The same option with one value replaced (only an existing place; a row is never created).
 *
 * @param array  $data   Option value.
 * @param array  $path   Path segments.
 * @param array  $fields Schema fields.
 * @param string $value  New value.
 * @return array|null Changed option, or null when the place does not exist.
 */
function memorial_plugin_texts_set( $data, $path, $fields, $value ) {
	$seg   = array_shift( $path );
	$field = $fields[ $seg ] ?? array();
	if ( ! is_array( $data ) ) {
		return null;
	}
	if ( ! $path ) {
		$data[ $seg ] = $value;
		return $data;
	}
	if ( 'repeater' === ( $field['type'] ?? '' ) ) {
		$id = array_shift( $path );
		foreach ( (array) ( $data[ $seg ] ?? array() ) as $n => $row ) {
			if ( is_array( $row ) && memorial_plugin_texts_row_is( $row, $id ) ) {
				$new = $path ? memorial_plugin_texts_set( $row, $path, $field['fields'], $value ) : null;
				if ( null === $new ) {
					return null;
				}
				$data[ $seg ][ $n ] = $new;
				return $data;
			}
		}
		return null;
	}
	$new = memorial_plugin_texts_set( $data[ $seg ] ?? array(), $path, $field['fields'] ?? array(), $value );
	if ( null === $new ) {
		return null;
	}
	$data[ $seg ] = $new;
	return $data;
}

/**
 * Raw value of a key as stored (with marks like {years}).
 *
 * @param string $key Key.
 * @return string|null Null when the key does not exist (the row was removed, for example).
 */
function memorial_plugin_texts_value( $key ) {
	$f = memorial_plugin_texts_field( $key );
	if ( ! $f ) {
		return null;
	}
	[, $option, $path] = $f;
	$pages             = memorial_plugin_admin_pages();
	return memorial_plugin_texts_get( memorial_plugin_settings( $option ), $path, call_user_func( $pages[ $option ]['schema'] ) );
}

/**
 * Version of a setting: grows with every change, compared on saving (a change made elsewhere is not overwritten).
 *
 * @param string $option Option name.
 * @return int
 */
function memorial_plugin_texts_version( $option ) {
	return (int) get_option( 'memorial_plugin_ver_' . $option, 0 );
}

/**
 * A settings option changed → its version grows.
 *
 * @param string $option Option name.
 */
function memorial_plugin_texts_bump( $option ) {
	if ( 0 === strpos( (string) $option, 'mem_' ) && function_exists( 'memorial_plugin_admin_pages' ) && isset( memorial_plugin_admin_pages()[ $option ] ) ) {
		update_option( 'memorial_plugin_ver_' . $option, memorial_plugin_texts_version( $option ) + 1, false );
	}
}
add_action( 'updated_option', 'memorial_plugin_texts_bump' );
add_action( 'added_option', 'memorial_plugin_texts_bump' );

/**
 * Check a new value of a field (on the server, whatever the editor did).
 *
 * @param array  $field Schema field.
 * @param string $value New value (plain text).
 * @return string|WP_Error Clean value or the reason.
 */
function memorial_plugin_texts_validate( $field, $value ) {
	$value = trim( (string) $value );
	$max   = (int) ( $field['max'] ?? ( 'textarea' === ( $field['type'] ?? '' ) ? 2000 : 300 ) );
	if ( mb_strlen( $value ) > $max ) {
		/* translators: %d: number of characters. */
		return new WP_Error( 'memorial_plugin_too_long', sprintf( __( 'The text is too long: up to %d characters.', 'memorial-plugin' ), $max ) );
	}
	switch ( $field['format'] ?? $field['type'] ?? 'text' ) {
		case 'phone':
			if ( ! preg_match( '/^\+?[\d\s()\-]{6,24}$/', $value ) ) {
				return new WP_Error( 'memorial_plugin_bad_phone', __( 'A phone number has only digits, spaces, brackets, hyphens and a leading “+”.', 'memorial-plugin' ) );
			}
			return $value;
		case 'number':
			if ( '' !== $value && ! preg_match( '/^\d+$/', $value ) ) {
				return new WP_Error( 'memorial_plugin_bad_number', __( 'Only a whole number without spaces.', 'memorial-plugin' ) );
			}
			return $value;
		case 'email':
			if ( '' !== $value && ! is_email( $value ) ) {
				return new WP_Error( 'memorial_plugin_bad_email', __( 'This is not an email address.', 'memorial-plugin' ) );
			}
			return $value;
		case 'textarea':
			return sanitize_textarea_field( $value );
	}
	return sanitize_text_field( $value );
}

/**
 * Bindable fields for the editor's list (“Connect to…”): [ { key, label } ], with the screen and row names.
 *
 * @return array<int,array{key:string,label:string}>
 */
function memorial_plugin_texts_fields_list() {
	$out = array();
	foreach ( memorial_plugin_admin_pages() as $option => $page ) {
		$walk = static function ( $fields, $data, $prefix, $label ) use ( &$walk, &$out, $option ) {
			foreach ( $fields as $name => $f ) {
				$type = $f['type'] ?? 'text';
				$text = $label . ' → ' . wp_strip_all_tags( (string) ( $f['label'] ?? $name ) );
				if ( 'group' === $type ) {
					$walk( $f['fields'], (array) ( $data[ $name ] ?? array() ), $prefix . $name . '.', $text );
				} elseif ( 'repeater' === $type ) {
					foreach ( (array) ( $data[ $name ] ?? array() ) as $n => $row ) {
						if ( ! is_array( $row ) || empty( $row['_id'] ) ) {
							continue;
						}
						$title = (string) ( $row[ $f['collapse'] ?? '' ] ?? '' );
						$walk( $f['fields'], $row, $prefix . $name . '.' . $row['_id'] . '.', $text . ' ' . ( '' !== $title ? '«' . $title . '»' : '#' . ( $n + 1 ) ) );
					}
				} elseif ( memorial_plugin_texts_bindable( (string) $name, $f ) ) {
					$out[] = array(
						'key'   => $option . ':' . $prefix . $name,
						'label' => mb_strimwidth( $text, 0, 140, '…' ),
					);
				}
			}
		};
		$walk( call_user_func( $page['schema'] ), memorial_plugin_settings( $option ), '', wp_strip_all_tags( (string) $page['title'] ) );
	}
	// The main phone and address: whichever row is the first, even after the rows are reordered.
	array_unshift(
		$out,
		array(
			'key'   => 'mem_contacts:phones.@first.num',
			'label' => __( 'Main phone (the first one in Memorial → Contacts)', 'memorial-plugin' ),
		),
		array(
			'key'   => 'mem_contacts:addrs.@first.addr',
			'label' => __( 'Main address (the first one in Memorial → Contacts)', 'memorial-plugin' ),
		),
		array(
			'key'   => 'mem_contacts:addrs.@first.hours',
			'label' => __( 'Opening hours of the main address', 'memorial-plugin' ),
		)
	);
	return $out;
}

/**
 * The source on the server: the value shown on the site (marks like {years} filled in, escaped).
 */
function memorial_plugin_texts_register_source() {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}
	register_block_bindings_source(
		MEMORIAL_PLUGIN_BINDINGS_SOURCE,
		array(
			'label'              => __( 'Memorial settings', 'memorial-plugin' ),
			'get_value_callback' => static function ( $args ) {
				$raw = memorial_plugin_texts_value( (string) ( $args['key'] ?? '' ) );
				if ( null === $raw ) {
					return null;
				}
				switch ( $args['as'] ?? '' ) {
					case 'tel':
						return 'tel:' . memorial_plugin_tel_of( $raw );
					case 'mailto':
						return 'mailto:' . sanitize_email( $raw );
				}
				return nl2br( esc_html( memorial_plugin_t( $raw ) ), false );
			},
		)
	);
}
add_action( 'init', 'memorial_plugin_texts_register_source' );

/**
 * REST: read and write the shared texts.
 *   GET  memorial-plugin/v1/texts?keys[]=…        → { values: {key: raw|null}, versions: {option: n} }
 *   POST memorial-plugin/v1/texts {values, versions} → the same after the change; 409 when a setting changed meanwhile.
 */
function memorial_plugin_texts_rest() {
	$can = static fn() => current_user_can( 'edit_posts' );
	register_rest_route(
		'memorial-plugin/v1',
		'/texts',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'args'                => array(
					'keys' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
						'maxItems' => 200,
					),
				),
				'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( memorial_plugin_texts_read( (array) $r['keys'] ) ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => static fn() => current_user_can( 'memorial_edit_texts' ),
				'args'                => array(
					'values'   => array(
						'type'     => 'object',
						'required' => true,
					),
					'versions' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
				'callback'            => 'memorial_plugin_texts_write',
			),
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_texts_rest' );

/**
 * Values and versions of keys.
 *
 * @param string[] $keys Keys.
 * @return array
 */
function memorial_plugin_texts_read( $keys ) {
	$values   = array();
	$versions = array();
	foreach ( $keys as $key ) {
		$key            = (string) $key;
		$values[ $key ] = memorial_plugin_texts_value( $key );
		$f              = memorial_plugin_texts_field( $key );
		if ( $f ) {
			$versions[ $f[1] ] = memorial_plugin_texts_version( $f[1] );
		}
	}
	return array(
		'values'   => (object) $values,
		'versions' => (object) $versions,
	);
}

/**
 * Save changed texts: every key checked, every option written once, its version compared first.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function memorial_plugin_texts_write( WP_REST_Request $request ) {
	$values   = (array) $request['values'];
	$versions = (array) $request['versions'];
	$pages    = memorial_plugin_admin_pages();
	$changed  = array();
	$errors   = array();
	foreach ( $values as $key => $value ) {
		$f = memorial_plugin_texts_field( (string) $key );
		if ( ! $f || ! is_scalar( $value ) ) {
			$errors[ $key ] = __( 'This text no longer exists in the settings (the row was removed?).', 'memorial-plugin' );
			continue;
		}
		[$field, $option, $path] = $f;
		$clean                   = memorial_plugin_texts_validate( $field, (string) $value );
		if ( is_wp_error( $clean ) ) {
			$errors[ $key ] = $clean->get_error_message();
			continue;
		}
		if ( isset( $versions[ $option ] ) && memorial_plugin_texts_version( $option ) !== (int) $versions[ $option ] ) {
			return new WP_Error(
				'memorial_plugin_texts_conflict',
				__( 'These settings were changed elsewhere while the page was open (in another tab or by another person). Reload the page to see the current texts, then make the change again.', 'memorial-plugin' ),
				array( 'status' => 409 )
			);
		}
		$data = $changed[ $option ] ?? get_option( $option, array() );
		$new  = memorial_plugin_texts_set( is_array( $data ) ? $data : array(), $path, call_user_func( $pages[ $option ]['schema'] ), $clean );
		if ( null === $new ) {
			$errors[ $key ] = __( 'This text no longer exists in the settings (the row was removed?).', 'memorial-plugin' );
			continue;
		}
		$changed[ $option ] = $new;
	}
	if ( $errors ) {
		return new WP_Error(
			'memorial_plugin_texts_invalid',
			implode( ' ', array_unique( $errors ) ),
			array(
				'status' => 400,
				'fields' => $errors,
			)
		);
	}
	foreach ( $changed as $option => $data ) {
		update_option( $option, $data ); // Goes through the sanitizer of the screen (register_setting), clears the page cache.
	}
	/**
	 * Fires after shared texts were saved from a page.
	 *
	 * @param string[] $keys Changed keys.
	 */
	do_action( 'memorial_plugin_texts_changed', array_keys( $values ) );
	return rest_ensure_response( memorial_plugin_texts_read( array_keys( $values ) ) );
}

/**
 * Editor: the source in the editor (build/bindings) with the list of fields and the right to edit.
 */
function memorial_plugin_texts_editor_assets() {
	$asset_file = MEMORIAL_PLUGIN_DIR . '/build/bindings/index.asset.php';
	if ( ! is_readable( $asset_file ) ) {
		return;
	}
	$asset = require $asset_file;
	wp_enqueue_script( 'memorial-plugin-bindings', MEMORIAL_PLUGIN_URL . '/build/bindings/index.js', $asset['dependencies'], $asset['version'], true );
	wp_set_script_translations( 'memorial-plugin-bindings', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
	wp_add_inline_script(
		'memorial-plugin-bindings',
		'window.memorialPluginBindings = ' . wp_json_encode(
			array(
				'source'  => MEMORIAL_PLUGIN_BINDINGS_SOURCE,
				'canEdit' => current_user_can( 'memorial_edit_texts' ),
				'fields'  => memorial_plugin_texts_fields_list(),
			)
		) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'memorial_plugin_texts_editor_assets' );

/**
 * Ids for the rows of the lists of a stored setting, nothing else changed (keys the screen does not show,
 * missing checkboxes, old values all stay as they are).
 *
 * @param mixed $data   Stored value (or a part of it).
 * @param array $fields Schema fields for it.
 * @return mixed
 */
function memorial_plugin_rows_add_ids( $data, $fields ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	foreach ( $fields as $name => $f ) {
		if ( ! isset( $data[ $name ] ) || ! is_array( $data[ $name ] ) ) {
			continue;
		}
		if ( 'group' === ( $f['type'] ?? '' ) ) {
			$data[ $name ] = memorial_plugin_rows_add_ids( $data[ $name ], $f['fields'] );
		} elseif ( 'repeater' === ( $f['type'] ?? '' ) ) {
			foreach ( $data[ $name ] as $n => $row ) {
				if ( is_array( $row ) ) {
					$row = memorial_plugin_rows_add_ids( $row, $f['fields'] );
					if ( empty( $row['_id'] ) ) {
						$row['_id'] = memorial_plugin_row_id();
					}
					$data[ $name ][ $n ] = $row;
				}
			}
		}
	}
	return $data;
}

/**
 * Once: every row of every saved settings list gets its id. On init, before the settings screens register
 * their sanitizers (admin_init) — the stored value is written back exactly as it is, plus the ids.
 */
function memorial_plugin_texts_ids_migrate() {
	if ( ! is_admin() || get_option( 'memorial_plugin_row_ids' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	update_option( 'memorial_plugin_row_ids', time(), false );
	foreach ( memorial_plugin_admin_pages() as $option => $page ) {
		$value = get_option( $option, null );
		if ( is_array( $value ) ) {
			$new = memorial_plugin_rows_add_ids( $value, call_user_func( $page['schema'] ) );
			if ( $new !== $value ) {
				update_option( $option, $new );
			}
		}
	}
}
add_action( 'init', 'memorial_plugin_texts_ids_migrate', 99 );
