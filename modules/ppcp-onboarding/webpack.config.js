const path = require( 'path' );
const CopyPlugin = require( 'copy-webpack-plugin' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		'task-list-fill': path.resolve(
			'./resources/js/task-list/task-list-fill.js'
		),
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
		new CopyPlugin( [
			{
				from: path.resolve( './resources/js' ),
				to: path.resolve( __dirname, 'assets/js' ),
				ignore: [ 'task-list/**/*' ],
			},
			{
				from: path.resolve( './resources/css' ),
				to: path.resolve( __dirname, 'assets/css' ),
			},
		] ),
	],
};
