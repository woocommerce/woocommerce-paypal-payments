const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	cache: false,
    resolve: {
        alias: {
            '@ppcp-settings': path.resolve(__dirname, './resources/js'),
            '@ppcp-blocks': path.resolve(__dirname, '../ppcp-blocks/resources/js'),
            '@ppcp-button': path.resolve(__dirname, '../ppcp-button/resources/js/modules')
        },
    },
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
