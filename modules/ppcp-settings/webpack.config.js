const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	...{
		entry: {
			index: path.resolve( process.cwd(), 'resources/js', 'index.js' ),
			style: path.resolve( process.cwd(), 'resources/css', 'style.scss' ),
		},
	},
};
