<?php
/**
 * Site data for SEO titles and descriptions: one registry, variables in SEOPress, Yoast and Rank Math.
 *
 * The registry memorial_plugin_seo_registry() is a single list “key → label + value function”. Every key becomes
 * the variable %%mem_<key>%% in the SEO plugin, so a title can say
 * “Fences in Belgorod — %%mem_cat_price_ograda%%” and always show the current price.
 * Always registered: the main phone and the years in business; besides them — the separate prices of
 * «Memorial → Prices» with “In the SEO plugin” checked (prices/values.php), or the memorial_plugin_seo_registry filter.
 *
 * SEOPress (main): seopress_titles_template_variables_array + seopress_titles_template_replace_array
 * (the two arrays must stay the same length and order, otherwise SEOPress silently substitutes
 * wrong values) and seopress_get_dynamic_variables (the list in the post meta box).
 * Yoast and Rank Math — the same registry, in case the SEO plugin changes.
 *
 * [mem_…] shortcodes in SEO fields still work (seo/seo-plugins.php) — deprecated, removal in 3.0.0.
 * The content converter replaces them with variables where the registry has a match (`shortcode` key).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * The registry: key => label, value function (gets the post ID, may be 0) and the shortcode it replaces.
 * The main phone and the years always; the separate prices of «Memorial → Prices» only when marked
 * “In the SEO plugin”, so the list of variables of the SEO plugin holds only what the site uses.
 *
 * @return array<string,array{label:string,get:callable,shortcode?:array}>
 */
function memorial_plugin_seo_registry() {
	// Always: the main phone (the first one in Memorial → Contacts) and the years in business.
	$registry = array(
		'phone' => array(
			'label'     => __( 'Main phone number', 'memorial-plugin' ),
			'get'       => static fn() => (string) memorial_plugin_opt( 'phone' ),
			'shortcode' => array( 'mem_phone', array() ),
		),
		'years' => array(
			'label'     => __( 'Years in business', 'memorial-plugin' ),
			'get'       => static function () {
				$years = (int) memorial_plugin_years();
				return $years ? (string) $years : '';
			},
			'shortcode' => array( 'mem_years', array() ),
		),
	);
	// Separate prices marked “In the SEO plugin” (Memorial → Prices).
	foreach ( function_exists( 'memorial_plugin_values' ) ? memorial_plugin_values() : array() as $key => $row ) {
		if ( empty( $row['seo'] ) || isset( $registry[ $key ] ) ) {
			continue;
		}
		$registry[ $key ] = array(
			'label' => '' !== trim( (string) ( $row['name'] ?? '' ) ) ? (string) $row['name'] : $key,
			'get'   => static fn() => memorial_plugin_value_text( $row ),
		);
		if ( 'cat-min' === ( $row['what'] ?? '' ) && empty( $row['bare'] ) ) {
			$term = get_term_by( 'slug', (string) ( $row['section'] ?? '' ), 'mem_cat' );
			if ( $term ) {
				$registry[ $key ]['shortcode'] = array( 'mem_cat_price', array( 'cat' => $term->name ) );
			}
		}
	}
	/**
	 * Filters the SEO data registry.
	 *
	 * @param array $registry Key => label, value function, replaced shortcode.
	 */
	return (array) apply_filters( 'memorial_plugin_seo_registry', $registry );
}

/**
 * Value of a registry key, escaped for an attribute (SEO plugins put it into <title> and meta tags).
 *
 * @param string $key     Registry key.
 * @param int    $post_id Post ID (0 — current).
 * @return string
 */
function memorial_plugin_seo_value( $key, $post_id = 0 ) {
	$def = memorial_plugin_seo_registry()[ $key ] ?? null;
	if ( ! $def || ! is_callable( $def['get'] ) ) {
		return '';
	}
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	return esc_attr( (string) call_user_func( $def['get'], $post_id ) );
}

/**
 * Variable name for a key: %%mem_<key>%%.
 *
 * @param string $key Registry key.
 * @return string
 */
function memorial_plugin_seo_var( $key ) {
	return '%%mem_' . $key . '%%';
}

/**
 * SEOPress: variable names (same order as the values below).
 *
 * @param array $vars Variables.
 * @return array
 */
function memorial_plugin_seopress_vars( $vars ) {
	foreach ( array_keys( memorial_plugin_seo_registry() ) as $key ) {
		$vars[] = memorial_plugin_seo_var( $key );
	}
	return $vars;
}
add_filter( 'seopress_titles_template_variables_array', 'memorial_plugin_seopress_vars' );

/**
 * SEOPress: values (same order as the names above).
 *
 * @param array $values Values.
 * @return array
 */
function memorial_plugin_seopress_values( $values ) {
	foreach ( array_keys( memorial_plugin_seo_registry() ) as $key ) {
		$values[] = memorial_plugin_seo_value( $key );
	}
	return $values;
}
add_filter( 'seopress_titles_template_replace_array', 'memorial_plugin_seopress_values' );

/**
 * SEOPress: the list of variables in the post meta box (insert with a click).
 *
 * @param array $vars Variable => label.
 * @return array
 */
function memorial_plugin_seopress_dynamic( $vars ) {
	foreach ( memorial_plugin_seo_registry() as $key => $def ) {
		$vars[ memorial_plugin_seo_var( $key ) ] = $def['label'];
	}
	return $vars;
}
add_filter( 'seopress_get_dynamic_variables', 'memorial_plugin_seopress_dynamic' );

/**
 * Yoast SEO: the same variables.
 */
function memorial_plugin_seo_yoast() {
	if ( ! function_exists( 'wpseo_register_var_replacement' ) ) {
		return;
	}
	foreach ( memorial_plugin_seo_registry() as $key => $def ) {
		wpseo_register_var_replacement(
			'mem_' . $key,
			static function () use ( $key ) {
				return memorial_plugin_seo_value( $key );
			},
			'advanced',
			$def['label']
		);
	}
}
add_action( 'wpseo_register_extra_replacements', 'memorial_plugin_seo_yoast' );

/**
 * Rank Math: the same variables.
 */
function memorial_plugin_seo_rank_math() {
	if ( ! function_exists( 'rank_math_register_var_replacement' ) ) {
		return;
	}
	foreach ( memorial_plugin_seo_registry() as $key => $def ) {
		rank_math_register_var_replacement(
			'mem_' . $key,
			array(
				'name'        => $def['label'],
				'description' => $def['label'],
				'variable'    => 'mem_' . $key,
				'example'     => memorial_plugin_seo_value( $key ),
			),
			static function () use ( $key ) {
				return memorial_plugin_seo_value( $key );
			}
		);
	}
}
add_action( 'rank_math/vars/register_extra_replacements', 'memorial_plugin_seo_rank_math' );

/**
 * SEO fields of a post that may hold [mem_…] shortcodes (meta key => label).
 *
 * @return array<string,string>
 */
function memorial_plugin_seo_fields() {
	return array(
		'_seopress_titles_title'    => __( 'SEO title (SEOPress)', 'memorial-plugin' ),
		'_seopress_titles_desc'     => __( 'Meta description (SEOPress)', 'memorial-plugin' ),
		'_seopress_social_fb_title' => __( 'Facebook title (SEOPress)', 'memorial-plugin' ),
		'_seopress_social_fb_desc'  => __( 'Facebook description (SEOPress)', 'memorial-plugin' ),
	);
}

/**
 * Shortcodes in an SEO field → registry variables where the registry has a match.
 * Other shortcodes stay (they still work through the compatibility layer).
 *
 * @param string $text Field value.
 * @return string
 */
function memorial_plugin_seo_convert( $text ) {
	if ( false === strpos( (string) $text, '[mem_' ) ) {
		return (string) $text;
	}
	$tags = array();
	foreach ( memorial_plugin_seo_registry() as $def ) {
		if ( ! empty( $def['shortcode'][0] ) ) {
			$tags[] = $def['shortcode'][0];
		}
	}
	return (string) preg_replace_callback(
		'/' . get_shortcode_regex( array_unique( $tags ) ) . '/s',
		static function ( $m ) {
			$atts = shortcode_parse_atts( $m[3] );
			$atts = is_array( $atts ) ? array_map( 'trim', $atts ) : array();
			foreach ( memorial_plugin_seo_registry() as $key => $def ) {
				$tag  = (string) ( $def['shortcode'][0] ?? '' );
				$want = (array) ( $def['shortcode'][1] ?? array() );
				// Same tag, same attributes in any order.
				if ( $tag === $m[2] && count( $want ) === count( $atts ) && ! array_diff_assoc( $want, $atts ) ) {
					return memorial_plugin_seo_var( $key );
				}
			}
			return $m[0];
		},
		(string) $text
	);
}
