<?php
/**
 * Floating elements as blocks in the footer: the phone bar on mobile, the “Call me back” window
 * and the cookie banner.
 *
 * In a block theme they are inserted at the end of the Footer template part by the Block Hooks API
 * (hooked_block_types), so the theme files do not need to list them. In the Site Editor each of them
 * can be detached in the block’s “Plugins” panel and put back the same way.
 * In a classic theme the window and the banner are printed in wp_footer as before.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Floating blocks: slug => shortcode-free render function and the feature (Memorial → Features) it needs.
 *
 * @return array<string,array{title:string,desc:string,icon:string,render:callable|null,module:string}>
 */
function memorial_plugin_floating_blocks() {
	return array(
		'callback-dialog' => array(
			'title'  => __( 'Call me back window', 'memorial-plugin' ),
			'desc'   => __( 'Hidden window with the call back form; opens from “Call me back” buttons.', 'memorial-plugin' ),
			'icon'   => 'phone',
			'render' => 'memorial_plugin_callback_html',
			'module' => 'callback',
		),
		'cookie-banner'   => array(
			'title'  => __( 'Cookie banner', 'memorial-plugin' ),
			'desc'   => __( 'Cookie notice or consent request (Memorial → Analytics). Without it, Yandex Metrica in the “after consent” mode never loads.', 'memorial-plugin' ),
			'icon'   => 'privacy',
			'render' => 'memorial_plugin_consent_banner_html',
			'module' => 'metrika',
		),
	);
}

/**
 * Is a feature from Memorial → Features switched on.
 *
 * @param string $module Feature key.
 * @return bool
 */
function memorial_plugin_floating_module_on( $module ) {
	$on = get_option( 'mem_modules' );
	return ! is_array( $on ) || ! isset( $on[ $module ] ) || ! empty( $on[ $module ] );
}

/**
 * Register the window and banner blocks.
 */
function memorial_plugin_floating_register() {
	foreach ( memorial_plugin_floating_blocks() as $slug => $def ) {
		if ( ! memorial_plugin_floating_module_on( $def['module'] ) ) {
			continue;
		}
		$render = $def['render'];
		register_block_type(
			'memorial-plugin/' . $slug,
			array(
				'api_version'     => 3,
				'title'           => $def['title'],
				'description'     => $def['desc'],
				'category'        => 'memorial',
				'icon'            => $def['icon'],
				'textdomain'      => 'memorial-plugin',
				'supports'        => array(
					'autoRegister' => true,
					'html'         => false,
					'reusable'     => false,
					'multiple'     => false,
				),
				'render_callback' => static function () use ( $render ) {
					return function_exists( $render ) ? (string) call_user_func( $render ) : '';
				},
			)
		);
	}
}
add_action( 'init', 'memorial_plugin_floating_register', 30 );

/**
 * Block Hooks: the floating blocks go at the end of the Footer template part.
 *
 * @param string[]                        $hooked   Hooked block types.
 * @param string                          $position Position relative to the anchor.
 * @param string                          $anchor   Anchor block type.
 * @param WP_Block_Template|WP_Post|array $context  Where the blocks are inserted.
 * @return string[]
 */
function memorial_plugin_floating_hooks( $hooked, $position, $anchor, $context ) {
	// The filter gets snake_case positions (last_child); block.json spells them lastChild.
	if ( ! in_array( $position, array( 'last_child', 'lastChild' ), true ) || 'core/template-part' !== $anchor ) {
		return $hooked;
	}
	if ( ! $context instanceof WP_Block_Template || 'wp_template_part' !== $context->type || 'footer' !== $context->area ) {
		return $hooked;
	}
	$hooked[] = 'memorial-plugin/mobile-bar';
	foreach ( array_keys( memorial_plugin_floating_blocks() ) as $slug ) {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'memorial-plugin/' . $slug ) ) {
			$hooked[] = 'memorial-plugin/' . $slug;
		}
	}
	return $hooked;
}
add_filter( 'hooked_block_types', 'memorial_plugin_floating_hooks', 10, 4 );
