<?php
/**
 * The robots.txt of WordPress (it is virtual: no file in the root of the site) gets two lines:
 *  - Clean-param (Yandex): addresses with ad tags (utm_…, yclid, gclid…) and the catalog sorting ?sort=
 *    are the same page — Yandex does not index them as duplicates. The directive is cross-section in Yandex
 *    (it may stand anywhere), so no group “User-agent: Yandex” is needed — such a group would make Yandex
 *    ignore the rules of “User-agent: *”; other search engines skip the line;
 *  - Sitemap: the sitemap of SEOPress when it is on, otherwise the sitemap of WordPress.
 * Nothing is added while the site asks search engines not to index it (Settings → Reading).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parameters that do not change the page.
 *
 * @return string[]
 */
function memorial_plugin_robots_clean_params() {
	/**
	 * Filters the address parameters for Clean-param.
	 *
	 * @param string[] $params Parameters.
	 */
	return (array) apply_filters( 'memorial_plugin_robots_clean_params', array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'yclid', 'gclid', 'fbclid', 'ysclid', 'sort' ) );
}

/**
 * Address of the sitemap: SEOPress (when its sitemap is on) or WordPress.
 *
 * @return string
 */
function memorial_plugin_robots_sitemap() {
	$seopress = get_option( 'seopress_xml_sitemap_option_name' );
	$toggle   = get_option( 'seopress_toggle' );
	if ( defined( 'SEOPRESS_VERSION' ) && ! empty( $seopress['seopress_xml_sitemap_general_enable'] ) && ! empty( $toggle['toggle-xml-sitemap'] ) ) {
		return home_url( '/sitemaps.xml' );
	}
	return function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled() ? home_url( '/wp-sitemap.xml' ) : '';
}

add_filter(
	'robots_txt',
	static function ( $output, $is_public ) {
		if ( ! $is_public ) {
			return $output;
		}
		$output = rtrim( (string) $output ) . "\n\nClean-param: " . implode( '&', memorial_plugin_robots_clean_params() ) . "\n";
		$map    = memorial_plugin_robots_sitemap();
		if ( '' !== $map && false === stripos( $output, 'Sitemap:' ) ) {
			$output .= "\nSitemap: " . $map . "\n";
		}
		return $output;
	},
	20,
	2
);
