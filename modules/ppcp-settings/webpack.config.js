const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	cache: false,
	...{
		entry: {
			index: path.resolve( process.cwd(), 'resources/js', 'index.js' ),
			switchSettingsUi: path.resolve(
				process.cwd(),
				'resources/js',
				'switchSettingsUi.js'
			),
			style: path.resolve( process.cwd(), 'resources/css', 'style.scss' ),
		},
	},
};
