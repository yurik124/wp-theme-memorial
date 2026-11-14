<?php
/**
 * Update of the site data to 2.1.0 — once, on the first admin visit of an administrator after the update.
 *
 * 2.0 kept everything needed to roll back to Memorial Core 1.15 and the old theme (memorial_speshu): the
 * old folders, the settings of the removed screens and the logs of the one-time moves. The moves are done
 * on the site and 2.1.0 has no rollback any more (the way back is a backup of the site), so:
 *  - the WebP choice of the removed “Memorial → Design” screen moves to its own option
 *    (memorial_plugin_webp), then the old settings and the logs of the moves are deleted;
 *  - the folders of the old plugin and theme (memorial-core, memorial_speshu) and the copies the
 *    deployment kept next to the new ones (.memorial-plugin-old, .memorial-theme-old) are deleted —
 *    only when the old plugin and theme are not active and WordPress can write the files directly.
 * The result is in the memorial_plugin_upgrade_210 option.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Options of the removed screens and the logs of the one-time moves of 2.0. */
const MEMORIAL_PLUGIN_OLD_OPTIONS = array(
	'mem_speed',
	'mem_design',
	'mem_ver_15',
	'mem_home_texts_v2',
	'mem_desc_cleaned',
	'mem_specs_migrated',
	'mem_wcards_migrated',
	'mem_cards_seeded',
	'mem_photos_migrated',
	'memorial_plugin_switchover',
	'memorial_plugin_after_switch',
	'memorial_plugin_speed_removed',
	'memorial_plugin_cleanup',
	'memorial_plugin_home_migrated',
	'memorial_plugin_tiles_to_cards',
	'memorial_plugin_prices_pkg_merge',
	'memorial_plugin_lead_ip_removed',
	'memorial_plugin_lead_mail_moved',
	'memorial_plugin_metrika_load_migrated',
	'memorial_plugin_metrika_confirm',
	'memorial_plugin_types_setup_1113',
);

/**
 * Run the update once.
 */
function memorial_plugin_upgrade() {
	if ( version_compare( (string) get_option( 'memorial_plugin_db', '0' ), '2.1.0', '>=' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
		return;
	}
	$log = array( 'time' => time() );

	// WebP: the only value of the old “Design” screen still in use (pages/media.php).
	$design = get_option( 'mem_design' );
	if ( false === get_option( 'memorial_plugin_webp', false ) ) {
		update_option( 'memorial_plugin_webp', empty( $design['img']['webp'] ) ? 0 : 1, false );
	}

	foreach ( MEMORIAL_PLUGIN_OLD_OPTIONS as $name ) {
		if ( false !== get_option( $name, false ) ) {
			delete_option( $name );
			$log['options'][] = $name;
		}
	}

	$log['folders'] = memorial_plugin_upgrade_old_folders();

	update_option( 'memorial_plugin_upgrade_210', $log, false );
	update_option( 'memorial_plugin_db', '2.1.0', false );
}
add_action( 'admin_init', 'memorial_plugin_upgrade', 5 );

/**
 * Delete the folders of the old plugin and theme and the copies the deployment kept.
 *
 * @return array<string,string> Folder => what happened.
 */
function memorial_plugin_upgrade_old_folders() {
	global $wp_filesystem;
	$plugins = WP_PLUGIN_DIR;
	$themes  = get_theme_root();
	$folders = array(
		$plugins . '/memorial-core'         => 'memorial-core/memorial-core.php',
		$themes . '/memorial_speshu'        => 'memorial_speshu',
		$plugins . '/.memorial-plugin-old'  => '',
		$themes . '/.memorial-theme-old'    => '',
	);
	$out     = array();
	$present = array_filter( array_keys( $folders ), 'is_dir' );
	if ( ! $present ) {
		return $out;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	if ( 'direct' !== get_filesystem_method( array(), WP_CONTENT_DIR ) || ! WP_Filesystem() ) {
		return array( 'all' => 'no direct file access' );
	}
	$active_plugins = (array) get_option( 'active_plugins', array() );
	foreach ( $present as $dir ) {
		$what = $folders[ $dir ];
		if ( '' !== $what && ( in_array( $what, $active_plugins, true ) || get_stylesheet() === $what || get_template() === $what ) ) {
			$out[ basename( $dir ) ] = 'active, kept';
			continue;
		}
		$out[ basename( $dir ) ] = $wp_filesystem->delete( $dir, true ) ? 'deleted' : 'not deleted';
	}
	return $out;
}

/**
 * Once: the old codes of catalog prices in the descriptions of catalog sections ([mem_cat_price …],
 * [mem_cat_count …]) become site values {price:code}. A code is a row of “Memorial → Prices → Separate
 * prices” (the existing one with the same section and kind, otherwise a new row). A code whose form has
 * no site value (“from” without ₽ and the like) stays as it is. The old descriptions are in the log
 * memorial_plugin_desc_values.
 */
function memorial_plugin_upgrade_descriptions() {
	if ( get_option( 'memorial_plugin_desc_values' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() || ! taxonomy_exists( 'mem_cat' ) ) {
		return;
	}
	$log   = array( 'time' => time() );
	$terms = get_terms(
		array(
			'taxonomy'   => 'mem_cat',
			'hide_empty' => false,
		)
	);
	$opt   = get_option( 'mem_prices' );
	$opt   = is_array( $opt ) ? $opt : array();
	$items = (array) ( $opt['other']['items'] ?? memorial_plugin_prices( 'other' )['items'] ?? array() );
	$codes = array_filter( array_map( static fn( $r ) => is_array( $r ) ? strtolower( trim( (string) ( $r['code'] ?? '' ) ) ) : '', $items ) );
	$added = false;
	$regex = '/' . get_shortcode_regex( array( 'mem_cat_price', 'mem_cat_count' ) ) . '/s';
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		if ( false === strpos( $term->description, '[mem_cat_' ) ) {
			continue;
		}
		$desc = preg_replace_callback(
			$regex,
			static function ( $m ) use ( $term, &$items, &$codes, &$added ) {
				$tag = $m[2];
				$a   = shortcode_parse_atts( $m[3] );
				$a   = is_array( $a ) ? $a : array();
				$val = trim( (string) ( $a['cat'] ?? '' ) );
				$t   = '' === $val ? $term : memorial_plugin_resolve_cat( $val );
				if ( ! $t && '' !== $val ) {
					$t = get_term_by( 'name', $val, 'mem_cat' );
				}
				if ( ! $t || is_wp_error( $t ) ) {
					return $m[0];
				}
				$no = static fn( $k, $def ) => function_exists( 'memorial_plugin_sc_no' ) && memorial_plugin_sc_no( $a[ $k ] ?? $def );
				if ( 'mem_cat_count' === $tag ) {
					$what = 'cat-count';
					$bare = $no( 'slovo', 'да' ) || $no( 'word', '1' );
				} else {
					$what     = preg_match( '/^(макс|max)/u', mb_strtolower( (string) ( $a['cena'] ?? '' ) ) ) || ! $no( 'max', '0' ) ? 'cat-max' : 'cat-min';
					$no_word  = $no( 'ot', 'да' ) || $no( 'prefix', '1' );
					$no_rub   = $no( 'rub', 'да' );
					if ( $no_word !== $no_rub ) {
						return $m[0]; // No site value prints that form.
					}
					$bare = $no_word;
				}
				foreach ( $items as $r ) {
					if ( is_array( $r ) && ( $r['what'] ?? '' ) === $what && ( $r['section'] ?? '' ) === $t->slug && (bool) ( $r['bare'] ?? false ) === $bare && '' !== trim( (string) ( $r['code'] ?? '' ) ) ) {
						return '{price:' . trim( (string) $r['code'] ) . '}';
					}
				}
				$suffix = array(
					'cat-min'   => 'ot',
					'cat-max'   => 'do',
					'cat-count' => 'modeli',
				)[ $what ];
				$base   = str_replace( '-', '_', sanitize_title( $t->slug ) ) . '_' . $suffix . ( $bare ? '_chislo' : '' );
				$code   = $base;
				for ( $i = 2; in_array( $code, $codes, true ); $i++ ) {
					$code = $base . $i;
				}
				$codes[] = $code;
				$names   = array(
					/* translators: %s: catalog section. */
					'cat-min'   => __( '%s — lowest price', 'memorial-plugin' ),
					/* translators: %s: catalog section. */
					'cat-max'   => __( '%s — highest price', 'memorial-plugin' ),
					/* translators: %s: catalog section. */
					'cat-count' => __( '%s — number of models', 'memorial-plugin' ),
				);
				$items[] = array(
					'name'    => sprintf( $names[ $what ], $t->name ),
					'what'    => $what,
					'section' => $t->slug,
					'bare'    => $bare ? 1 : 0,
					'seo'     => 0,
					'code'    => $code,
					'_id'     => memorial_plugin_row_id(),
				);
				$added   = true;
				return '{price:' . $code . '}';
			},
			$term->description
		);
		if ( $desc !== $term->description ) {
			$log['terms'][ $term->term_id ] = $term->description;
			wp_update_term( $term->term_id, 'mem_cat', array( 'description' => $desc ) );
		}
	}
	if ( $added ) {
		$opt['other']          = (array) ( $opt['other'] ?? array() );
		$opt['other']['items'] = array_values( $items );
		update_option( 'mem_prices', $opt );
	}
	update_option( 'memorial_plugin_desc_values', $log, false );
}
add_action( 'admin_init', 'memorial_plugin_upgrade_descriptions', 6 );
