#!/usr/bin/env node
/**
 * External dependencies
 */
const { execSync } = require( 'child_process' );

// Baseline site URL for wp-env's home/siteurl DB options (not wp-config
// constants — a constant would re-trigger the pre_option_home/siteurl
// filters and permanently win over any later `wp option update`, including
// the ngrok-enabled shards' own override in the reusable workflow). Must
// stay in sync with the WP_BASE_URL baseline in .env.ci / .env.example.e2e:
// non-ngrok shards and local dev navigate against that value, so WordPress's
// own home/siteurl need to match it here.
const baseUrl = process.env.WP_BASE_URL || 'http://mywp.site';

const commands = [
	{
		description: 'Set home URL',
		command: `wp-env run tests-cli -- wp option update home "${ baseUrl }"`,
	},
	{
		description: 'Set site URL',
		command: `wp-env run tests-cli -- wp option update siteurl "${ baseUrl }"`,
	},
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
