<?php
/**
 * The home page and the “Contacts” page are ordinary pages: their blocks live in the page content and
 * are edited in “Pages” like any other page. The templates of the theme only frame the content
 * (front-page.html, page-kontakty.html: header, breadcrumbs, post content, footer).
 *
 * One-time move (memorial_plugin_pages_to_content):
 *  - home: the sections of the front-page template (the site’s own template saved in the Site Editor, or
 *    the sections built from Memorial → Home page) become the content of the front page; the page’s own
 *    text, which the template showed in the “About” section, takes the place of the Post Content block;
 *  - contacts: the contacts block of the page-kontakty template is split into editable blocks
 *    (title, lead text, opening hours, contact cards, maps, the page text, company details).
 * Before saving, the page content is stored as a revision (the way back: “Revisions” of the page); a site
 * template saved in the Site Editor is removed and its content kept in the log. The automatic rollback to
 * the old layout (Memorial → Home page) was removed by the decision of 12.11.2026.
 *
 * The log is the memorial_plugin_pages_to_content option.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Option: log of the move. */
const MEMORIAL_PLUGIN_PAGES_TO_CONTENT = 'memorial_plugin_pages_to_content';

/**
 * The “Contacts” page: the page that the page-kontakty template is for.
 *
 * @return WP_Post|null
 */
function memorial_plugin_contacts_page() {
	$page = get_page_by_path( 'kontakty' );
	return $page instanceof WP_Post && 'page' === $page->post_type ? $page : null;
}

/**
 * Inner blocks of the <main> group of a template.
 *
 * @param string $content Template markup.
 * @return array|null Parsed blocks, null when there is no <main> group.
 */
function memorial_plugin_template_main_blocks( $content ) {
	foreach ( parse_blocks( (string) $content ) as $block ) {
		if ( 'core/group' === $block['blockName'] && 'main' === ( $block['attrs']['tagName'] ?? '' ) ) {
			return $block['innerBlocks'];
		}
	}
	return null;
}

/**
 * Replace the Post Content block (anywhere in the tree) with the given blocks.
 *
 * @param array $blocks Parsed blocks.
 * @param array $with   Blocks to put instead.
 * @return array
 */
function memorial_plugin_replace_post_content( $blocks, $with ) {
	$out = array();
	foreach ( $blocks as $block ) {
		if ( 'core/post-content' === $block['blockName'] ) {
			array_push( $out, ...$with );
			continue;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = memorial_plugin_replace_post_content( $block['innerBlocks'], $with );
			// innerContent holds a null for each inner block; keep their number in step.
			$block['innerContent'] = memorial_plugin_inner_content( $block );
		}
		$out[] = $block;
	}
	return $out;
}

/**
 * The innerContent of a block after its inner blocks changed: the markup before the first and after the
 * last inner block stays, a null stands for each inner block.
 *
 * @param array $block Parsed block.
 * @return array
 */
function memorial_plugin_inner_content( $block ) {
	$parts = array_values( array_filter( (array) $block['innerContent'], 'is_string' ) );
	$open  = $parts[0] ?? '';
	$close = count( $parts ) > 1 ? end( $parts ) : '';
	return array_merge( array( $open ), array_fill( 0, count( $block['innerBlocks'] ), null ), array( $close ) );
}

/**
 * The home sections for the front page content.
 *
 * @param WP_Post $page Front page.
 * @return string Block markup.
 */
function memorial_plugin_home_content( $page ) {
	$template = get_block_template( get_stylesheet() . '//front-page', 'wp_template' );
	$sections = $template ? memorial_plugin_template_main_blocks( $template->content ) : null;
	$markup   = $sections ? serialize_blocks( $sections ) : '';
	// No site template with the sections (the theme’s template only frames the content now) — build them.
	if ( false === strpos( $markup, '<!-- wp:memorial-plugin/' ) || false !== strpos( $markup, 'memorial-theme/home' ) ) {
		$markup = memorial_plugin_home_markup();
	}
	$own = parse_blocks( (string) $page->post_content );
	$own = array_values( array_filter( $own, static fn( $b ) => null !== $b['blockName'] ) );
	return serialize_blocks( memorial_plugin_replace_post_content( parse_blocks( $markup ), $own ) );
}

/**
 * The “Contacts” page as blocks.
 *
 * @param WP_Post $page Contacts page.
 * @return string Block markup.
 */
function memorial_plugin_contacts_content( $page ) {
	$gap   = '"layout":{"type":"default"},"style":{"spacing":{"blockGap":"0"}}';
	$title = esc_html( get_the_title( $page ) );
	$lead  = esc_html__( 'Call — we will tell you what to do and take care of everything.', 'memorial-plugin' );
	$text  = trim( (string) $page->post_content );
	$out   = '<!-- wp:group {"tagName":"header","className":"kontakty__head",' . $gap . '} -->' . "\n"
		. '<header class="wp-block-group kontakty__head"><!-- wp:heading {"level":1} -->' . "\n"
		. '<h1 class="wp-block-heading">' . $title . '</h1>' . "\n<!-- /wp:heading -->\n\n"
		. '<!-- wp:group {"className":"kontakty__lead",' . $gap . '} -->' . "\n"
		. '<div class="wp-block-group kontakty__lead"><!-- wp:memorial-plugin/contacts-badges /-->' . "\n\n"
		. "<!-- wp:paragraph -->\n<p>" . $lead . "</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></header>\n<!-- /wp:group -->\n\n"
		. "<!-- wp:memorial-plugin/contacts-cards /-->\n\n"
		. '<!-- wp:group {"tagName":"section","className":"kontakty__maps",' . $gap . '} -->' . "\n"
		. '<section class="wp-block-group kontakty__maps"><!-- wp:memorial-plugin/maps /--></section>' . "\n<!-- /wp:group -->\n\n";
	if ( '' !== $text ) {
		$out .= '<!-- wp:group {"className":"content kontakty__text",' . $gap . '} -->' . "\n"
			. '<div class="wp-block-group content kontakty__text">' . $text . "</div>\n<!-- /wp:group -->\n\n";
	}
	return $out . "<!-- wp:memorial-plugin/company-details /-->\n";
}

/**
 * Save new content of a page, keeping the current one as a revision.
 *
 * @param WP_Post $page    Page.
 * @param string  $content New block markup.
 * @return array|string Log entry, or an error message.
 */
function memorial_plugin_page_set_content( $page, $content ) {
	$revision = _wp_put_post_revision( $page );
	$saved    = wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => wp_slash( $content ),
		),
		true
	);
	if ( is_wp_error( $saved ) ) {
		return $saved->get_error_message();
	}
	return array(
		'page'     => (int) $page->ID,
		'revision' => is_wp_error( $revision ) ? 0 : (int) $revision,
		'old'      => (string) $page->post_content,
	);
}

/**
 * Remove the site’s own copy of a template (saved in the Site Editor), keeping its content in the log.
 *
 * @param string $slug Template slug.
 * @return array|null Removed template: slug, title and content.
 */
function memorial_plugin_template_reset( $slug ) {
	$template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template' );
	if ( ! $template || 'custom' !== $template->source || empty( $template->wp_id ) ) {
		return null;
	}
	$kept = array(
		'slug'    => $slug,
		'title'   => (string) $template->title,
		'content' => (string) $template->content,
	);
	wp_delete_post( (int) $template->wp_id, true );
	return $kept;
}

/**
 * The move itself.
 */
function memorial_plugin_pages_to_content() {
	if ( get_option( MEMORIAL_PLUGIN_PAGES_TO_CONTENT ) || ! current_user_can( 'edit_theme_options' ) || ! wp_is_block_theme() ) {
		return;
	}
	$log = array(
		'time'      => time(),
		'pages'     => array(),
		'templates' => array(),
	);

	$front = 'page' === get_option( 'show_on_front' ) ? get_post( (int) get_option( 'page_on_front' ) ) : null;
	if ( ! $front instanceof WP_Post ) {
		// The home page shows the latest posts: give it a page of its own (the sections need one).
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => __( 'Home', 'memorial-plugin' ),
				'post_name'   => 'glavnaya',
			),
			true
		);
		if ( ! is_wp_error( $id ) ) {
			$log['front'] = array(
				'created'       => (int) $id,
				'show_on_front' => (string) get_option( 'show_on_front' ),
				'page_on_front' => (int) get_option( 'page_on_front' ),
			);
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $id );
			$front = get_post( (int) $id );
		}
	}
	if ( $front instanceof WP_Post && false === strpos( $front->post_content, '<!-- wp:memorial-plugin/' ) ) {
		$entry = memorial_plugin_page_set_content( $front, memorial_plugin_home_content( $front ) );
		if ( is_array( $entry ) ) {
			$log['pages']['home'] = $entry;
			$kept                 = memorial_plugin_template_reset( 'front-page' );
			if ( $kept ) {
				$log['templates'][] = $kept;
			}
		} else {
			$log['errors'][] = 'home: ' . $entry;
		}
	}

	$contacts = memorial_plugin_contacts_page();
	if ( $contacts && false === strpos( $contacts->post_content, '<!-- wp:memorial-plugin/contacts-cards' ) ) {
		$entry = memorial_plugin_page_set_content( $contacts, memorial_plugin_contacts_content( $contacts ) );
		if ( is_array( $entry ) ) {
			$log['pages']['contacts'] = $entry;
			$kept                     = memorial_plugin_template_reset( 'page-kontakty' );
			if ( $kept ) {
				$log['templates'][] = $kept;
			}
		} else {
			$log['errors'][] = 'contacts: ' . $entry;
		}
	}
	update_option( MEMORIAL_PLUGIN_PAGES_TO_CONTENT, $log, false );
}
add_action( 'admin_init', 'memorial_plugin_pages_to_content', 20 );

/**
 * The home page and “Contacts” open in the page editor with their template, so the sections have the same
 * width and look as on the site, but the header and footer of the template are hidden there
 * (memorial_plugin_pages_hide_title(); they are edited in Appearance → Editor). The title of these pages is
 * a block inside the content, so the separate title field is not shown either.
 *
 * @param array                   $settings Editor settings.
 * @param WP_Block_Editor_Context $context  Editor context.
 * @return array
 */
function memorial_plugin_pages_rendering_mode( $settings, $context ) {
	$post = $context->post ?? null;
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return $settings;
	}
	if ( memorial_plugin_pages_own_layout( $post ) ) {
		$settings['defaultRenderingMode'] = 'template-locked';
	}
	return $settings;
}
add_filter( 'block_editor_settings_all', 'memorial_plugin_pages_rendering_mode', 10, 2 );

/**
 * Pages in the page editor: no header and footer of the template (they are edited in Appearance → Editor);
 * the home page and “Contacts” also have no separate title field (the H1 is a block of the content).
 */
function memorial_plugin_pages_hide_title() {
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	$post   = get_post();
	if ( ! $screen || 'page' !== $screen->post_type || 'post' !== $screen->base || ! $post instanceof WP_Post ) {
		return;
	}
	foreach ( array( 'page' => true, 'own-layout' => memorial_plugin_pages_own_layout( $post ) ) as $name => $on ) {
		if ( $on ) {
			$path = '/assets/css/editor-' . $name . '.css';
			wp_enqueue_style( 'memorial-plugin-editor-' . $name, MEMORIAL_PLUGIN_URL . $path, array(), (string) filemtime( MEMORIAL_PLUGIN_DIR . $path ) );
		}
	}
}
add_action( 'enqueue_block_assets', 'memorial_plugin_pages_hide_title' );

/**
 * Pages whose whole layout is in their content: the home page and “Contacts”.
 *
 * @param WP_Post $post Page.
 * @return bool
 */
function memorial_plugin_pages_own_layout( $post ) {
	if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
		return true;
	}
	$contacts = memorial_plugin_contacts_page();
	return $contacts && (int) $contacts->ID === (int) $post->ID;
}
