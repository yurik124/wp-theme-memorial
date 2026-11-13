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
		'inline-value/index': path.resolve( __dirname, 'memorial-plugin/src/inline-value/index.js' ),
		'settings/index': path.resolve( __dirname, 'memorial-plugin/src/settings/index.js' ),
		'collections/index': path.resolve( __dirname, 'memorial-plugin/src/collections/index.js' ),
		'catalog/index': path.resolve( __dirname, 'memorial-plugin/src/catalog/index.js' ),
		'commands/index': path.resolve( __dirname, 'memorial-plugin/src/commands/index.js' ),
		'editor-meta/index': path.resolve( __dirname, 'memorial-plugin/src/editor-meta/index.js' ),
		'block-controls/index': path.resolve( __dirname, 'memorial-plugin/src/block-controls/index.js' ),
		'bindings/index': path.resolve( __dirname, 'memorial-plugin/src/bindings/index.js' ),
	},
	output: {
		...base.output,
		path: path.resolve( __dirname, 'memorial-plugin/build' ),
	},
	module: {
		...base.module,
		rules: [
			...base.module.rules,
			// The package says “sideEffects: false”, so webpack would drop `import '…/build-style/style.css'`
			// as unused: its stylesheet is a side effect we need (filters, chips, footer of DataViews).
			{
				test: /[\\/]@wordpress[\\/](dataviews|admin-ui)[\\/]build-style[\\/].*\.css$/,
				sideEffects: true,
			},
		],
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
