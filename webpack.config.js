const path = require( 'path' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const modulesAssets = {
	'ppcp-admin-notices': [ 'js/boot-admin.js', 'css/styles.scss' ],
	'ppcp-applepay': [
		'js/boot.js',
		'js/boot-block.js',
		'js/boot-admin.js',
		'css/styles.scss',
	],
	'ppcp-axo': [
		'js/boot.js',
		'js/Insights/EndCheckoutTracker.js',
		'css/styles.scss',
	],
	'ppcp-axo-block': [
		'js/index.js',
		'js/plugins/PayPalInsightsLoader.js',
		'css/gateway.scss',
	],
	'ppcp-blocks': [
		'js/checkout-block.js',
		'js/advanced-card-checkout-block.js',
		'css/gateway.scss',
		'css/gateway-editor.scss',
	],
	'ppcp-button': [
		'js/button.js',
		'css/hosted-fields.scss',
		'css/gateway.scss',
	],
	'ppcp-card-fields': [],
	'ppcp-compat': [ 'js/tracking-compat.js' ],
	'ppcp-fraud-protection': [ 'js/recaptcha-handler.js' ],
	'ppcp-googlepay': [
		'js/boot.js',
		'js/boot-block.js',
		'js/boot-admin.js',
		'css/styles.scss',
	],
	'ppcp-local-alternative-payment-methods': [
		'js/bancontact-payment-method.js',
		'js/blik-payment-method.js',
		'js/eps-payment-method.js',
		'js/ideal-payment-method.js',
		'js/mybank-payment-method.js',
		'js/p24-payment-method.js',
		'js/trustly-payment-method.js',
		'js/multibanco-payment-method.js',
		'js/pwc-payment-method.js',
		'css/gateway.scss',
	],
	'ppcp-order-tracking': [
		'js/order-edit-page.js',
		'css/order-edit-page.scss',
	],
	'ppcp-paylater-block': [ 'js/paylater-block.js', 'css/edit.scss' ],
	'ppcp-paylater-configurator': [
		'js/paylater-configurator.js',
		'css/paylater-configurator.scss',
	],
	'ppcp-paylater-wc-blocks': [
		'js/CartPayLaterMessagesBlock/cart-paylater-block.js',
		'js/CartPayLaterMessagesBlock/cart-paylater-block-inserter.js',
		'js/CheckoutPayLaterMessagesBlock/checkout-paylater-block.js',
	],
	'ppcp-paypal-subscriptions': [ 'js/paypal-subscription.js' ],
	'ppcp-save-payment-methods': [ 'js/add-payment-method.js' ],
	'ppcp-settings': [ 'js/index.js', 'css/styles.scss' ],
	'ppcp-wc-gateway': [
		'js/common.js',
		'js/gateway-settings.js',
		'js/fraudnet.js',
		'js/oxxo.js',
		'js/void-button.js',
		'css/gateway-settings.scss',
		'css/common.scss',
	],
};

const entries = {};
const aliases = {};
for ( const [ moduleId, assets ] of Object.entries( modulesAssets ) ) {
	for ( const relativePath of assets ) {
		const name =
			moduleId +
			'-' +
			relativePath
				.replace( /\.jsx?$/g, '' )
				.replace( /\.scss$/g, '' )
				.split( '/' )
				.join( '-' );
		const assetPath = `./modules/${ moduleId }/resources/${ relativePath }`;

		entries[ name ] = assetPath;
	}

	aliases[ '@' + moduleId ] = path.resolve(
		__dirname,
		`./modules/${ moduleId }/resources/js`
	);
	if ( moduleId === 'ppcp-button' ) {
		aliases[ '@' + moduleId ] += `/modules`;
	}
}

module.exports = {
	...defaultConfig,
	resolve: {
		...defaultConfig.resolve,
		alias: aliases,
	},
	entry: entries,
	output: {
		publicPath: './',
		path: __dirname + '/assets',
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
