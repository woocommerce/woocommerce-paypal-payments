const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	cache: false,
	...{
		entry: {
			'ppcp-admin-settings': path.resolve(
				process.cwd(),
				'resources/js',
				'ppcp-admin-settings.js'
			),
			switchSettingsUi: path.resolve(
				process.cwd(),
				'resources/js',
				'switchSettingsUi.js'
			),
			style: path.resolve( process.cwd(), 'resources/css', 'style.scss' ),
		},
	},
};
