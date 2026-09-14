const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

const wpResolvers =
	defaultConfig.find( ( c ) => c.settings?.[ 'import/resolver' ] )?.settings[
		'import/resolver'
	] ?? {};

const tsResolver = Object.keys( wpResolvers ).find( ( key ) =>
	key.includes( 'eslint-import-resolver-typescript' )
);

module.exports = [
	...defaultConfig,
	{
		ignores: [
			'playwright-utils/**',
			'playwright-report/**',
			'storage-states/**',
			'test-results/**',
			'node_modules/**',
		],
	},
	{
		languageOptions: {
			globals: { wc: 'readonly', jQuery: 'readonly' },
		},
		settings: {
			'import/resolver': {
				[ tsResolver ]: {
					project: './jsconfig.json',
					extensions: [ '.js', '.jsx' ],
				},
			},
		},
		rules: {
			'no-console': [ 'error', { allow: [ 'warn', 'error' ] } ],
		},
	},
];
