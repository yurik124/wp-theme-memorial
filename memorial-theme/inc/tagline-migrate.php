<?php
/**
 * The line under the site title next to the logo is the site tagline (Settings → General → Tagline),
 * shown by the core Site Tagline block (patterns/header.php).
 *
 * One-time move (memorial_theme_tagline_migrate): an empty tagline gets the text the header used to
 * print (“funeral company” in the site language), and a Header template part saved in the Site Editor
 * gets the Site Tagline block in place of its old paragraph. The log is the memorial_theme_tagline_migrated
 * option; rollback — Appearance → Editor → Patterns → Header → Reset.
 *
 * @package memorial-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Move the header subtitle to the site tagline.
 */
function memorial_theme_tagline_migrate() {
	if ( get_option( 'memorial_theme_tagline_migrated' ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$log = array( 'time' => time() );
	if ( '' === trim( (string) get_option( 'blogdescription' ) ) ) {
		$switched = switch_to_locale( get_locale() ); // the site language, not the language of this user.
		update_option( 'blogdescription', __( 'funeral company', 'memorial-theme' ) );
		if ( $switched ) {
			restore_previous_locale();
		}
		$log['tagline'] = 'set';
	}
	$part = get_block_template( get_stylesheet() . '//header', 'wp_template_part' );
	if ( $part && 'custom' === $part->source && ! empty( $part->wp_id ) ) {
		$content = (string) preg_replace(
			'~<!-- wp:paragraph \{"className":"logo__sub"\} -->\s*<p class="logo__sub">.*?</p>\s*<!-- /wp:paragraph -->~s',
			'<!-- wp:site-tagline {"className":"logo__sub"} /-->',
			$part->content,
			1,
			$count
		);
		if ( $count ) {
			wp_update_post(
				array(
					'ID'           => (int) $part->wp_id,
					'post_content' => wp_slash( $content ),
				)
			);
			$log['header'] = 'updated';
		}
	}
	update_option( 'memorial_theme_tagline_migrated', $log, false );
}
add_action( 'admin_init', 'memorial_theme_tagline_migrate' );
