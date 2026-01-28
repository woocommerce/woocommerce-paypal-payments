#!/usr/bin/env node
/**
 * External dependencies
 */
const { execSync } = require( 'child_process' );

const commands = [
	{
		description: 'Install storefront theme',
		command: 'wp-env run tests-cli -- wp theme install storefront',
	},
	{
		description: 'Activate storefront theme',
		command: 'wp-env run tests-cli -- wp theme activate storefront',
	},
	{
		description: 'Install WooCommerce',
		command: 'wp-env run tests-cli -- wp plugin install woocommerce',
	},
	{
		description: 'Activate WooCommerce',
		command: 'wp-env run tests-cli -- wp plugin activate woocommerce',
	},
	{
		description: 'Install WooCommerce Payments',
		command:
			'wp-env run tests-cli -- wp plugin install woocommerce-payments',
	},
	{
		description: 'Update URL structure',
		command:
			'wp-env run tests-cli -- wp rewrite structure "/%postname%/" --hard',
	},
	{
		description: 'Update Blog Name',
		command:
			'wp-env run tests-cli -- wp option update blogname "WooCommerce PayPal Payments E2E Test Suite"',
	},
	{
		description: 'Set the store as live',
		command:
			'wp-env run tests-cli -- wp option update woocommerce_coming_soon "no"',
	},
	{
		description: 'PayPal - Set new UI (merchant flag)',
		command:
			'wp-env run tests-cli -- wp option update woocommerce-ppcp-is-new-merchant "yes"',
	},
	{
		description: 'PayPal - Set new UI (disable old UI)',
		command:
			'wp-env run tests-cli -- wp option update woocommerce_ppcp-settings-should-use-old-ui "no"',
	},
];

console.log( 'Starting test environment setup...\n' );

commands.forEach( ( item, index ) => {
	try {
		console.log( `${ index + 1 }. ${ item.description }` );
		execSync( item.command, { stdio: 'inherit' } );
		console.log( '✅ Success\n' );
	} catch ( error ) {
		console.error( `❌ Failed: ${ item.description }` );
		console.error( `Command: ${ item.command }` );
		console.error( `Error: ${ error.message }\n` );
	}
} );

console.log( '🎉 Test environment setup complete!' );
