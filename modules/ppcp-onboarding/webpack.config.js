const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		onboarding: path.resolve( './resources/js/onboarding.js' ),
		'task-list-fill': path.resolve(
			'./resources/js/task-list/task-list-fill.js'
		),
		settings: path.resolve( './resources/js/settings.js' ),
	},
	output: {
		path: path.resolve( __dirname, 'assets/js/' ),
		filename: '[name].js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};
