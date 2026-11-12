<?php
/**
 * Settings screens on DataForm: every page of memorial_plugin_admin_pages() (Contacts, Prices, Reviews,
 * Halls, Cemeteries, Documents, Analytics, Features…) is rendered by one React app (src/settings) from
 * the same field schema that the plugin used for the classic forms.
 *
 * The schema stays the single source: the app gets it as JSON (memorial_plugin_settings_describe),
 * saving goes through REST and the same sanitizer (memorial_plugin_sanitize), so defaults, validation
 * and the hooks on update_option_* (schedules, notices) work as before.
 *
 * REST: GET/POST memorial-plugin/v1/settings/<option> — manage_options only.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schema field → JSON for the app (labels, help, options; nested fields for groups and lists).
 *
 * @param array $schema Field schema (key => field).
 * @return array<int,array>
 */
function memorial_plugin_settings_describe( $schema ) {
	$out = array();
	foreach ( $schema as $key => $f ) {
		$type = (string) ( $f['type'] ?? 'text' );
		if ( 'hidden' === $type ) {
			continue;
		}
		$field = array(
			'id'    => (string) $key,
			'type'  => $type,
			'label' => (string) ( $f['label'] ?? '' ),
		);
		foreach ( array( 'help', 'placeholder', 'add', 'collapse' ) as $prop ) {
			if ( isset( $f[ $prop ] ) && is_string( $f[ $prop ] ) && '' !== $f[ $prop ] ) {
				$field[ $prop ] = $f[ $prop ];
			}
		}
		if ( isset( $f['rows'] ) ) {
			$field['rows'] = (int) $f['rows'];
		}
		if ( ! empty( $f['open'] ) ) {
			$field['open'] = true;
		}
		if ( ! empty( $f['show_if'] ) && is_array( $f['show_if'] ) ) {
			// Shown only when another field of the same row or group has one of these values.
			$field['showIf'] = array(
				'field' => (string) $f['show_if'][0],
				'in'    => array_map( 'strval', (array) $f['show_if'][1] ),
			);
		}
		if ( ! empty( $f['summary'] ) ) {
			$field['summary'] = array_map( 'strval', (array) $f['summary'] ); // Fields shown in a folded row.
		}
		if ( ! empty( $f['short'] ) ) {
			$field['short'] = (string) $f['short']; // Short label of a checkbox in a folded row.
		}
		if ( ! empty( $f['inline'] ) ) {
			$field['inline'] = true; // Short rows (name and price) in one line, without folding.
		}
		if ( isset( $f['options'] ) && is_array( $f['options'] ) ) {
			$field['options'] = array();
			foreach ( $f['options'] as $value => $label ) {
				$field['options'][] = array(
					'value' => (string) $value,
					'label' => wp_strip_all_tags( (string) $label ),
				);
			}
		}
		if ( 'page' === $type ) {
			$field['options'] = memorial_plugin_settings_pages();
		}
		if ( isset( $f['fields'] ) && is_array( $f['fields'] ) ) {
			$field['fields'] = memorial_plugin_settings_describe( $f['fields'] );
		}
		/**
		 * Filters one field of a settings page for the app (a module can give its own field type and data).
		 *
		 * @param array  $field Field for the app.
		 * @param array  $f     Schema field.
		 * @param string $key   Field key.
		 */
		$out[] = (array) apply_filters( 'memorial_plugin_settings_describe_field', $field, $f, (string) $key );
	}
	return $out;
}

/**
 * Pages for “page” fields: [{value, label}], published and drafts.
 *
 * @return array<int,array{value:string,label:string}>
 */
function memorial_plugin_settings_pages() {
	static $pages = null;
	if ( null === $pages ) {
		$pages = array();
		foreach ( get_pages( array( 'post_status' => array( 'publish', 'draft' ) ) ) as $p ) {
			$pages[] = array(
				'value' => (string) $p->ID,
				'label' => str_repeat( '— ', count( get_post_ancestors( $p ) ) ) . ( '' !== $p->post_title ? $p->post_title : '#' . $p->ID ),
			);
		}
	}
	return $pages;
}

/**
 * Saved value + what the page sent: groups are merged key by key, lists are replaced as a whole.
 *
 * @param array $old Saved value.
 * @param array $new Sanitized value from the page.
 * @return array
 */
function memorial_plugin_settings_merge( $old, $new ) {
	foreach ( $new as $key => $value ) {
		if ( is_array( $value ) && isset( $old[ $key ] ) && is_array( $old[ $key ] ) && $value && ! array_is_list( $value ) && ! array_is_list( $old[ $key ] ) ) {
			$old[ $key ] = memorial_plugin_settings_merge( $old[ $key ], $value );
		} else {
			$old[ $key ] = $value;
		}
	}
	return $old;
}

/**
 * HTML that other modules print around a settings page (tabs, status panels) — shown above the form.
 *
 * @param string $opt Option name.
 * @return string
 */
function memorial_plugin_settings_extra_html( $opt ) {
	ob_start();
	do_action( 'memorial_plugin_admin_tabs', $opt );
	do_action( 'memorial_plugin_admin_after_intro', $opt );
	return trim( (string) ob_get_clean() );
}

/**
 * Everything the app needs for one page.
 *
 * @param string $opt Option name.
 * @return array|WP_Error
 */
function memorial_plugin_settings_page_data( $opt ) {
	$pages = memorial_plugin_admin_pages();
	if ( ! isset( $pages[ $opt ] ) ) {
		return new WP_Error( 'memorial_plugin_unknown_settings', __( 'Unknown settings page.', 'memorial-plugin' ), array( 'status' => 404 ) );
	}
	$p = $pages[ $opt ];
	return array(
		'option' => $opt,
		'title'  => (string) ( $p['h1'] ?? $p['title'] ),
		'intro'  => wp_kses_post( (string) ( $p['intro'] ?? '' ) ),
		// The extra panels (tabs, Rewio status) are printed by PHP next to the app (with their own scripts) — see the container.
		'extra'  => has_action( 'memorial_plugin_admin_tabs' ) || has_action( 'memorial_plugin_admin_after_intro' ),
		'fields' => memorial_plugin_settings_describe( call_user_func( $p['schema'] ) ),
		'value'  => memorial_plugin_settings( $opt ),
	);
}

/**
 * REST routes.
 */
function memorial_plugin_settings_rest() {
	register_rest_route(
		'memorial-plugin/v1',
		'/settings/(?P<option>[a-z_]+)',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => static function ( WP_REST_Request $request ) {
					return rest_ensure_response( memorial_plugin_settings_page_data( (string) $request['option'] ) );
				},
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'value' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$opt   = (string) $request['option'];
					$pages = memorial_plugin_admin_pages();
					if ( ! isset( $pages[ $opt ] ) ) {
						return new WP_Error( 'memorial_plugin_unknown_settings', __( 'Unknown settings page.', 'memorial-plugin' ), array( 'status' => 404 ) );
					}
					$value = memorial_plugin_sanitize( call_user_func( $pages[ $opt ]['schema'] ), (array) $request['value'] );
					// Keys that the page does not show (for example, the old home page layout kept for rollback) stay as they were.
					$old = get_option( $opt );
					update_option( $opt, is_array( $old ) ? memorial_plugin_settings_merge( $old, $value ) : $value );
					return rest_ensure_response( memorial_plugin_settings_page_data( $opt ) );
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'memorial_plugin_settings_rest' );

/**
 * Container of a settings page (the app renders the rest).
 *
 * @param string $opt Option name.
 */
function memorial_plugin_settings_container( $opt ) {
	if ( ! is_readable( MEMORIAL_PLUGIN_DIR . '/build/settings/index.asset.php' ) ) {
		echo '<div class="wrap"><p>' . esc_html__( 'The JavaScript build is missing. Run npm run build.', 'memorial-plugin' ) . '</p></div>';
		return;
	}
	// Panels of other modules (tabs, Rewio status and preview) enqueue their own styles and scripts, so they
	// are printed here as is (only for administrators, by the plugin’s own code) and the app moves them under its header.
	$extra = memorial_plugin_settings_extra_html( $opt );
	if ( '' !== $extra ) {
		echo '<div id="memorial-plugin-settings-extra" class="memorial-plugin-settings__extra" hidden>' . $extra . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup of the plugin’s own admin panels, escaped where it is built.
	}
	echo '<div id="memorial-plugin-settings" class="wrap memorial-plugin-admin" data-option="' . esc_attr( $opt ) . '"></div>';
}

/**
 * Scripts of the settings app (only on the settings pages).
 */
function memorial_plugin_settings_assets() {
	$asset_file = MEMORIAL_PLUGIN_DIR . '/build/settings/index.asset.php';
	if ( ! is_readable( $asset_file ) ) {
		return;
	}
	$asset = require $asset_file;
	wp_enqueue_media(); // Image fields.
	wp_enqueue_script(
		'memorial-plugin-settings',
		MEMORIAL_PLUGIN_URL . '/build/settings/index.js',
		$asset['dependencies'],
		$asset['version'],
		array( 'in_footer' => true )
	);
	wp_set_script_translations( 'memorial-plugin-settings', 'memorial-plugin', MEMORIAL_PLUGIN_DIR . '/languages' );
	wp_add_inline_script(
		'memorial-plugin-settings',
		'window.memorialPluginSettings = ' . wp_json_encode( array( 'siteUrl' => home_url( '/' ) ) ) . ';',
		'before'
	);
	wp_enqueue_style( 'wp-components' );
	if ( is_readable( MEMORIAL_PLUGIN_DIR . '/build/settings/style-index.css' ) ) {
		wp_enqueue_style( 'memorial-plugin-settings', MEMORIAL_PLUGIN_URL . '/build/settings/style-index.css', array( 'wp-components' ), $asset['version'] );
	}
}
