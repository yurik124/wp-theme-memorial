/**
 * Сборка JS плагина и темы на @wordpress/scripts.
 *
 * Отличие от стандартной конфигурации: @wordpress/dataviews и @wordpress/admin-ui собираются ВНУТРЬ
 * бандла (их нет среди общих скриптов WordPress, на которые можно сослаться как на wp.*), а остальные
 * @wordpress/* по-прежнему берутся из WordPress (wp-element, wp-i18n, wp-components…).
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const BUNDLED = [ '@wordpress/dataviews', '@wordpress/dataviews/wp', '@wordpress/admin-ui' ];

const base = Array.isArray( defaultConfig ) ? defaultConfig[ 0 ] : defaultConfig;

module.exports = {
	...base,
	entry: {
		'dataviews-check/index': path.resolve( __dirname, 'memorial-plugin/src/dataviews-check/index.js' ),
	},
	output: {
		...base.output,
		path: path.resolve( __dirname, 'memorial-plugin/build' ),
	},
	plugins: [
		...base.plugins.filter(
			( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				if ( BUNDLED.some( ( name ) => request === name || request.startsWith( name + '/' ) ) ) {
					return false;
				}
				return undefined;
			},
		} ),
	],
};
