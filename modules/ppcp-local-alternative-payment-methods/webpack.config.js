const path = require( 'path' );
const isProduction = process.env.NODE_ENV === 'production';

const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	devtool: isProduction ? 'source-map' : 'eval-source-map',
	mode: isProduction ? 'production' : 'development',
	target: 'web',
	plugins: [ new DependencyExtractionWebpackPlugin() ],
	entry: {
		'bancontact-payment-method': path.resolve(
			'./resources/js/bancontact-payment-method.js'
		),
		'blik-payment-method': path.resolve(
			'./resources/js/blik-payment-method.js'
		),
		'eps-payment-method': path.resolve(
			'./resources/js/eps-payment-method.js'
		),
		'ideal-payment-method': path.resolve(
			'./resources/js/ideal-payment-method.js'
		),
		'mybank-payment-method': path.resolve(
			'./resources/js/mybank-payment-method.js'
		),
		'p24-payment-method': path.resolve(
			'./resources/js/p24-payment-method.js'
		),
		'trustly-payment-method': path.resolve(
			'./resources/js/trustly-payment-method.js'
		),
		'multibanco-payment-method': path.resolve(
			'./resources/js/multibanco-payment-method.js'
		),
	},
	output: {
		path: path.resolve( __dirname, 'assets/' ),
		filename: 'js/[name].js',
	},
	module: {
		rules: [
			{
				test: /\.js?$/,
				exclude: /node_modules/,
				loader: 'babel-loader',
			},
			{
				test: /\.scss$/,
				exclude: /node_modules/,
				use: [
					{
						loader: 'file-loader',
						options: {
							name: 'css/[name].css',
						},
					},
					{ loader: 'sass-loader' },
				],
			},
		],
	},
};
