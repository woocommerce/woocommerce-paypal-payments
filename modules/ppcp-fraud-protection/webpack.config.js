const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	cache: false,
	...{
		entry: {
			'recaptcha-handler': path.resolve(
				process.cwd(),
				'resources/js',
				'recaptcha-handler.js'
			),
		},
	},
};
