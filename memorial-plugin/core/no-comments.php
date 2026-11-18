<?php
/**
 * No comments, pingbacks and XML-RPC (owner, 18.11.2026): the site has no comment form, but bots send comments
 * straight to wp-comments-post.php and the REST API, and use xmlrpc.php for pingbacks and password guessing.
 *
 *  - comments and pings are closed on every post, whatever the post says; new comments are refused;
 *  - XML-RPC is switched off, the X-Pingback header is gone;
 *  - xmlrpc.php is closed in .htaccess (Apache) between our markers, so bots do not even start WordPress;
 *    the rule is written once (memorial_plugin_xmlrpc_block) and removed when the plugin is uninstalled.
 * The comments that are already in the base stay as they are.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'rest_allow_anonymous_comments', '__return_false' );
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );

add_filter(
	'wp_headers',
	static function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

/* A comment sent straight to wp-comments-post.php: refused before it is saved. */
add_action(
	'pre_comment_on_post',
	static function () {
		wp_die( esc_html__( 'Comments are closed.', 'memorial-plugin' ), '', array( 'response' => 403 ) );
	}
);

/* The REST API: no new comments from anybody. */
add_filter(
	'rest_pre_insert_comment',
	static fn() => new WP_Error( 'rest_comment_closed', __( 'Comments are closed.', 'memorial-plugin' ), array( 'status' => 403 ) )
);

/* No comment support for posts and pages: the editor does not offer the “Discussion” panel. */
add_action(
	'init',
	static function () {
		foreach ( array( 'post', 'page', 'attachment' ) as $type ) {
			remove_post_type_support( $type, 'comments' );
			remove_post_type_support( $type, 'trackbacks' );
		}
	},
	100
);

/**
 * The rule that closes xmlrpc.php (Apache 2.4).
 *
 * @param bool $on Add (true) or remove.
 * @return bool Written.
 */
function memorial_plugin_xmlrpc_rule( $on = true ) {
	$htaccess = ABSPATH . '.htaccess';
	if ( ! file_exists( $htaccess ) || ! wp_is_writable( $htaccess ) ) {
		return false;
	}
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$rules = $on ? array( '<Files "xmlrpc.php">', '<IfModule mod_authz_core.c>', 'Require all denied', '</IfModule>', '</Files>' ) : array();
	return (bool) insert_with_markers( $htaccess, 'Memorial xmlrpc', $rules );
}

add_action(
	'admin_init',
	static function () {
		if ( get_option( 'memorial_plugin_xmlrpc_block' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( 'memorial_plugin_xmlrpc_block', memorial_plugin_xmlrpc_rule() ? 1 : -1, false );
	}
);
