<?php
/**
 * Home sections as block markup (from the mem_home setting) and the one-time transfer into the
 * front-page template. See blocks/home/home.php.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * A block comment pair with inner HTML (or a self-closing comment when $html is null).
 *
 * @param string      $name  Block name (without the core/ prefix for core blocks).
 * @param array       $attrs Attributes.
 * @param string|null $html  Inner HTML.
 * @return string
 */
function memorial_plugin_home_block( $name, $attrs = array(), $html = null ) {
	$json = $attrs ? ' ' . serialize_block_attributes( $attrs ) : '';
	if ( null === $html ) {
		return '<!-- wp:' . $name . $json . ' /-->';
	}
	return '<!-- wp:' . $name . $json . ' -->' . "\n" . $html . "\n" . '<!-- /wp:' . $name . ' -->';
}

/**
 * Text from the settings for block content: escaped, with non-breaking spaces, {years} / {founded} /
 * {phone} / {addr} as inline values (always current), line breaks as <br>.
 *
 * @param string $text Text from mem_home.
 * @return string
 */
function memorial_plugin_home_text( $text ) {
	$html = memorial_plugin_nbsp( esc_html( trim( (string) $text ) ) );
	$keys = array(
		'{years}'   => 'years',
		'{founded}' => 'founded',
		'{phone}'   => 'phone-text',
		'{addr}'    => 'address',
	);
	foreach ( $keys as $token => $key ) {
		if ( false !== strpos( $html, $token ) ) {
			$value = memorial_plugin_inline_value_html( $key, array() );
			$html  = str_replace( $token, memorial_plugin_inline_value_span( $key, array(), (string) $value ), $html );
		}
	}
	return nl2br( $html, false );
}

/**
 * Group block (layout “default”, no gaps — like the classic markup).
 *
 * @param string $class Classes.
 * @param string $inner Inner blocks markup.
 * @param array  $extra Extra attributes (tagName, anchor…).
 * @return string
 */
function memorial_plugin_home_group( $class, $inner, $extra = array() ) {
	$tag   = $extra['tagName'] ?? 'div';
	$attrs = array_merge(
		$extra,
		array(
			'className' => $class,
			'layout'    => array( 'type' => 'default' ),
		)
	);
	$id    = ! empty( $extra['anchor'] ) ? ' id="' . esc_attr( $extra['anchor'] ) . '"' : '';
	return memorial_plugin_home_block( 'group', $attrs, '<' . $tag . $id . ' class="wp-block-group ' . esc_attr( $class ) . '">' . $inner . '</' . $tag . '>' );
}

/**
 * Paragraph block.
 *
 * @param string $html  Inner HTML.
 * @param string $class Classes.
 * @return string
 */
function memorial_plugin_home_p( $html, $class = '' ) {
	return memorial_plugin_home_block( 'paragraph', $class ? array( 'className' => $class ) : array(), '<p' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>' . $html . '</p>' );
}

/**
 * Heading block.
 *
 * @param string $html  Inner HTML.
 * @param int    $level Level.
 * @return string
 */
function memorial_plugin_home_h( $html, $level = 2 ) {
	return memorial_plugin_home_block( 'heading', 2 === $level ? array() : array( 'level' => $level ), '<h' . $level . ' class="wp-block-heading">' . $html . '</h' . $level . '>' );
}

/**
 * Laurel decoration block.
 *
 * @param string $class Classes.
 * @param string $kind  Shape.
 * @return string
 */
function memorial_plugin_home_laurel( $class, $kind = 'divider' ) {
	$attrs = array( 'className' => $class );
	if ( 'divider' !== $kind ) {
		$attrs = array_merge( array( 'kind' => $kind ), $attrs );
	}
	return memorial_plugin_home_block( 'memorial-plugin/laurel', $attrs );
}

/**
 * Section heading: eyebrow, laurel divider, H2 (as memorial_plugin_sec_head()).
 *
 * @param array $s Section settings.
 * @return string
 */
function memorial_plugin_home_head( $s ) {
	if ( empty( $s['eyebrow'] ) && empty( $s['title'] ) ) {
		return '';
	}
	$inner = '';
	if ( ! empty( $s['eyebrow'] ) ) {
		$inner .= memorial_plugin_home_p( memorial_plugin_home_text( $s['eyebrow'] ), 'eyebrow' );
	}
	$inner .= memorial_plugin_home_laurel( 'sec-head__div' );
	if ( ! empty( $s['title'] ) ) {
		$inner .= memorial_plugin_home_h( memorial_plugin_home_text( $s['title'] ) );
	}
	return memorial_plugin_home_group( 'sec-head', $inner, array( 'tagName' => 'header' ) );
}

/**
 * Section wrapper.
 *
 * @param string $id    Anchor.
 * @param bool   $alt   Ivory background.
 * @param string $inner Content of .wrap.
 * @param string $wrap  Extra classes of .wrap.
 * @param string $pre   Blocks before .wrap (decorations).
 * @return string
 */
function memorial_plugin_home_section( $id, $alt, $inner, $wrap = '', $pre = '' ) {
	return memorial_plugin_home_group(
		'section' . ( $alt ? ' section--alt' : '' ),
		$pre . memorial_plugin_home_group( trim( 'wrap ' . $wrap ), $inner ),
		array(
			'tagName' => 'section',
			'anchor'  => $id,
		)
	);
}

/**
 * Attachment ID of an image URL (for the Image block), 0 if not in the media library.
 *
 * @param string $url Image URL.
 * @return int
 */
function memorial_plugin_home_image_id( $url ) {
	return function_exists( 'memorial_plugin_attachment_id_by_url' ) ? (int) memorial_plugin_attachment_id_by_url( $url ) : (int) attachment_url_to_postid( $url );
}

/**
 * First screen.
 *
 * @param array $h Hero settings.
 * @return string
 */
function memorial_plugin_home_hero( $h ) {
	$img  = (string) ( $h['img'] ?? '' );
	$text = '';
	if ( ! empty( $h['eyebrow'] ) ) {
		$text .= memorial_plugin_home_p( memorial_plugin_home_text( $h['eyebrow'] ), 'eyebrow' );
	}
	$title = memorial_plugin_home_text( $h['title'] ?? '' );
	if ( ! empty( $h['title_em'] ) ) {
		$title .= ( '' !== $title ? '<br>' : '' ) . '<em>' . memorial_plugin_home_text( $h['title_em'] ) . '</em>';
	}
	$text .= memorial_plugin_home_h( $title, 1 );
	if ( ! empty( $h['lead'] ) ) {
		$text .= memorial_plugin_home_p( memorial_plugin_home_text( $h['lead'] ), 'hero__lead' );
	}
	$trust = array_filter( array_map( static fn( $t ) => trim( (string) ( $t['text'] ?? '' ) ), (array) ( $h['trust'] ?? array() ) ) );
	if ( $trust ) {
		$items = '';
		foreach ( $trust as $t ) {
			$items .= memorial_plugin_home_block( 'list-item', array(), '<li>' . memorial_plugin_home_text( $t ) . '</li>' );
		}
		$text .= memorial_plugin_home_block( 'list', array( 'className' => 'hero__trust lv-list' ), '<ul class="wp-block-list hero__trust lv-list">' . $items . '</ul>' );
	}
	$cta  = memorial_plugin_home_block( 'memorial-plugin/phone-button' );
	$help = function_exists( 'memorial_plugin_home_help_link' ) ? memorial_plugin_home_help_link() : null;
	if ( $help ) {
		$cta .= memorial_plugin_home_block(
			'memorial-plugin/link-button',
			array_filter(
				array(
					'text' => (string) $help[1],
					'page' => (int) url_to_postid( $help[0] ),
					'url'  => url_to_postid( $help[0] ) ? '' : (string) $help[0],
				)
			)
		);
	}
	$text .= memorial_plugin_home_group( 'hero__cta', $cta );
	$text .= memorial_plugin_home_block( 'memorial-plugin/night-line' );

	$inner = memorial_plugin_home_group( 'hero__text', $text );
	if ( '' !== $img ) {
		$id     = memorial_plugin_home_image_id( $img );
		$attrs  = array_filter(
			array(
				'id'              => $id,
				'sizeSlug'        => 'full',
				'linkDestination' => 'none',
				'className'       => 'hero__photo',
			)
		);
		$inner .= memorial_plugin_home_block(
			'image',
			$attrs,
			'<figure class="wp-block-image size-full hero__photo"><img src="' . esc_url( $img ) . '" alt=""' . ( $id ? ' class="wp-image-' . $id . '"' : '' ) . '/></figure>'
		);
	}
	$class = 'hero' . ( '' !== $img ? ' hero--img' : '' ) . ( '' !== $img && 'top' === ( $h['mobile'] ?? 'bg' ) ? ' hero--mtop' : '' );
	return memorial_plugin_home_group(
		$class,
		memorial_plugin_home_laurel( 'hero__branch', 'branch_plain' )
		. memorial_plugin_home_group( 'wrap hero__in', $inner ),
		array( 'tagName' => 'section' )
	);
}

/**
 * Sections in the order of mem_home: key => markup (without the alternating background yet).
 *
 * @param array $o mem_home settings (with defaults).
 * @return array<string,callable> Key => function( bool $alt ): string.
 */
function memorial_plugin_home_sections( $o ) {
	$on = static fn( $key ) => ! empty( $o[ $key ]['show'] );
	$s  = array();

	if ( $on( 'services' ) && memorial_plugin_services_items() ) {
		$s['services'] = static fn( $alt ) => memorial_plugin_home_section( 'uslugi', $alt, memorial_plugin_home_head( $o['services'] ) . memorial_plugin_home_block( 'memorial-plugin/services-grid' ) );
	}
	if ( $on( 'feats' ) && ! empty( $o['feats']['items'] ) ) {
		$s['feats'] = static function ( $alt ) use ( $o ) {
			$items = '';
			foreach ( array_values( $o['feats']['items'] ) as $i => $f ) {
				$icon  = function_exists( 'memorial_plugin_feat_icon' ) ? memorial_plugin_feat_icon( $f ) : '';
				$inner = '';
				if ( ! empty( $f['img'] ) ) {
					$attrs  = array(
						'className'       => 'feat__img',
						'linkDestination' => 'none',
					);
					$inner .= memorial_plugin_home_block( 'image', $attrs, '<figure class="wp-block-image feat__img"><img src="' . esc_url( $f['img'] ) . '" alt=""/></figure>' );
				} elseif ( $icon ) {
					$inner .= memorial_plugin_home_block( 'memorial-plugin/icon', array( 'name' => $icon ) );
				} else {
					$inner .= memorial_plugin_home_p( sprintf( '%02d', $i + 1 ), 'feat__n' );
				}
				if ( ! empty( $f['title'] ) ) {
					$inner .= memorial_plugin_home_h( memorial_plugin_home_text( $f['title'] ), 3 );
				}
				if ( ! empty( $f['text'] ) ) {
					$inner .= memorial_plugin_home_p( memorial_plugin_home_text( $f['text'] ) );
				}
				$items .= memorial_plugin_home_group( 'feat reveal' . ( ! empty( $f['img'] ) ? ' feat--img' : '' ), $inner );
			}
			return memorial_plugin_home_section( 'services', $alt, memorial_plugin_home_head( $o['feats'] ) . memorial_plugin_home_group( 'feats', $items ) );
		};
	}
	if ( $on( 'catalog' ) ) {
		$s['catalog'] = static fn( $alt ) => memorial_plugin_home_section( 'catalog', $alt, memorial_plugin_home_head( $o['catalog'] ) . memorial_plugin_home_block( 'memorial-plugin/cards', memorial_plugin_catalog_page() ? array( 'parent' => (int) memorial_plugin_catalog_page() ) : array() ) );
	}
	if ( $on( 'prices' ) && memorial_plugin_packages() ) {
		$s['prices'] = static fn( $alt ) => memorial_plugin_home_section( 'prices', $alt, memorial_plugin_home_head( $o['prices'] ) . memorial_plugin_home_block( 'memorial-plugin/packages-grid' ) );
	}
	if ( $on( 'stats' ) && ! empty( $o['stats']['items'] ) ) {
		$s['stats'] = static function () use ( $o ) {
			$items = '';
			foreach ( $o['stats']['items'] as $st ) {
				$items .= memorial_plugin_home_block(
					'memorial-plugin/stat',
					array_filter(
						array(
							'number' => (string) ( $st['num'] ?? '' ),
							'label'  => memorial_plugin_t( (string) ( $st['label'] ?? '' ) ),
						)
					)
				);
			}
			return memorial_plugin_home_group( 'stats', memorial_plugin_home_group( 'wrap stats__in stats__in--' . min( 4, count( $o['stats']['items'] ) ), $items ), array( 'tagName' => 'section' ) );
		};
	}
	if ( $on( 'about' ) ) {
		$s['about'] = static function ( $alt ) use ( $o ) {
			$a    = $o['about'];
			$head = '';
			if ( ! empty( $a['eyebrow'] ) ) {
				$head .= memorial_plugin_home_p( memorial_plugin_home_text( $a['eyebrow'] ), 'eyebrow' );
			}
			if ( ! empty( $a['title'] ) ) {
				$head .= memorial_plugin_home_h( memorial_plugin_home_text( $a['title'] ) );
			}
			$head .= memorial_plugin_home_laurel( 'about__div' );
			$text  = '';
			foreach ( preg_split( "/\n\s*\n/", trim( (string) ( $a['text'] ?? '' ) ) ) as $para ) {
				if ( '' !== trim( $para ) ) {
					$text .= memorial_plugin_home_p( memorial_plugin_home_text( $para ) );
				}
			}
			// Text of the front page itself (when the front page is a page).
			$text .= memorial_plugin_home_block( 'post-content', array( 'layout' => array( 'type' => 'default' ) ) );
			return memorial_plugin_home_section(
				'about',
				$alt,
				memorial_plugin_home_group( '', $head ) . memorial_plugin_home_group( 'about__text', $text ),
				'about',
				memorial_plugin_home_laurel( 'sec-branch', 'branch_plain' )
			);
		};
	}
	if ( $on( 'reviews' ) && memorial_plugin_yandex_org() ) {
		$s['reviews'] = static function ( $alt ) use ( $o ) {
			$r     = $o['reviews'];
			$intro = '';
			if ( ! empty( $r['eyebrow'] ) ) {
				$intro .= memorial_plugin_home_p( memorial_plugin_home_text( $r['eyebrow'] ), 'eyebrow' );
			}
			if ( ! empty( $r['title'] ) ) {
				$intro .= memorial_plugin_home_h( memorial_plugin_home_text( $r['title'] ) );
			}
			if ( ! empty( $r['text'] ) ) {
				$intro .= memorial_plugin_home_p( memorial_plugin_home_text( $r['text'] ), 'reviews__text' );
			}
			$intro .= memorial_plugin_home_block( 'memorial-plugin/review-buttons' );
			return memorial_plugin_home_section( 'reviews', $alt, memorial_plugin_home_group( 'reviews__intro', $intro ) . memorial_plugin_home_block( 'memorial-plugin/review-widget' ), 'reviews' );
		};
	}
	if ( $on( 'contacts' ) ) {
		$s['contacts'] = static function ( $alt ) use ( $o ) {
			$c    = $o['contacts'];
			$head = '';
			if ( ! empty( $c['eyebrow'] ) ) {
				$head .= memorial_plugin_home_p( memorial_plugin_home_text( $c['eyebrow'] ), 'eyebrow' );
			}
			if ( ! empty( $c['title'] ) ) {
				$head .= memorial_plugin_home_h( memorial_plugin_home_text( $c['title'] ) );
			}
			$head .= memorial_plugin_home_block( 'memorial-plugin/contacts-list' );
			return memorial_plugin_home_section( 'contacts', $alt, memorial_plugin_home_group( '', $head ) . memorial_plugin_home_block( 'memorial-plugin/maps' ), 'contacts' );
		};
	}
	return $s;
}

/**
 * The whole home page as block markup: first screen, then the sections in the mem_home order with
 * alternating backgrounds (as the classic theme did).
 *
 * @param array|null $o mem_home settings; null — current.
 * @return string
 */
function memorial_plugin_home_markup( $o = null ) {
	$o        = is_array( $o ) ? $o : memorial_plugin_settings( 'mem_home' );
	$out      = array( memorial_plugin_home_hero( (array) ( $o['hero'] ?? array() ) ) );
	$sections = memorial_plugin_home_sections( $o );
	$order    = (array) ( $o['layout'] ?? array_keys( $sections ) );
	$alt      = true;
	foreach ( array_unique( array_merge( $order, array_keys( $sections ) ) ) as $key ) {
		if ( ! isset( $sections[ $key ] ) ) {
			continue;
		}
		$out[] = $sections[ $key ]( $alt );
		// After the sage band with figures the next section is light again.
		$alt = 'stats' === $key ? false : ! $alt;
	}
	return implode( "\n\n", $out );
}

/**
 * One-time transfer: save the theme’s front-page template with the sections as the site’s template.
 */
function memorial_plugin_home_migrate() {
	if ( get_option( 'memorial_plugin_home_migrated' ) || ! current_user_can( 'edit_theme_options' ) || ! wp_is_block_theme() ) {
		return;
	}
	$log   = array(
		'time'   => time(),
		'theme'  => get_stylesheet(),
		'result' => 'no-template',
	);
	$theme = get_block_template( get_stylesheet() . '//front-page', 'wp_template' );
	if ( $theme && false !== strpos( (string) $theme->content, 'memorial-theme/home' ) ) {
		if ( 'custom' === $theme->source ) {
			$log['result'] = 'skipped-user-template-exists';
		} else {
			$content = preg_replace( '~<!-- wp:pattern \{"slug":"memorial-theme/home"\} /-->~', memorial_plugin_home_markup(), (string) $theme->content );
			$id      = wp_insert_post(
				array(
					'post_type'    => 'wp_template',
					'post_status'  => 'publish',
					'post_name'    => 'front-page',
					'post_title'   => $theme->title,
					'post_excerpt' => $theme->description,
					'post_content' => wp_slash( $content ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$log['result'] = 'error: ' . $id->get_error_message();
			} else {
				// Done directly: the wp_theme term is set by tax_input only for users who may assign terms.
				wp_set_object_terms( $id, array( get_stylesheet() ), 'wp_theme' );
				$log['result']      = 'migrated';
				$log['template_id'] = (int) $id;
			}
		}
	}
	update_option( 'memorial_plugin_home_migrated', $log, false );
}
add_action( 'admin_init', 'memorial_plugin_home_migrate' );
