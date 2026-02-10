const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const sharedAliases = require( '../../webpack.aliases' );

module.exports = {
	...defaultConfig,
	cache: false,
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve?.alias || {} ),
			...sharedAliases,
		},
	},
	...{
		entry: {
			settings: path.resolve(
				process.cwd(),
				'resources/js',
				'settings.js'
			),
			'dummy-agent': path.resolve(
				process.cwd(),
				'resources/js/dummy-agent',
				'index.js'
			),
			style: path.resolve( process.cwd(), 'resources/css', 'style.scss' ),
		},
	},
};
