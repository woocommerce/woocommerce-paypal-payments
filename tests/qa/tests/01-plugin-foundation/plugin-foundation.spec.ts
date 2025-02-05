/**
 * External dependencies
 */
import {
	testPluginInstallationFromFile,
	testPluginReinstallationFromFile,
	testPluginInstallationFromMarketplace,
	testPluginActivation,
	testPluginDeactivation,
	testPluginRemoval,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { pcpPlugin, customers } from '../../resources';

testPluginInstallationFromFile( 'PCP-1000', pcpPlugin, '@Critical' );

testPluginReinstallationFromFile( 'PCP-1007', pcpPlugin, '@Critical' );

// testPluginInstallationFromMarketplace( 'PCP-1004', pcpPlugin, '@Critical' );

testPluginActivation( 'PCP-2003', pcpPlugin, '@Critical' );

testPluginDeactivation( 'PCP-1006', pcpPlugin, '@Critical' );

testPluginRemoval( 'PCP-1005', pcpPlugin, '@Critical' );

// test.skip( 'PCP-0000 | Paypal is present in WooCommerce payment methods', async ( {
// 	wooCommerceUtils,
// 	requestUtils,
// 	plugins,
// 	wooCommerceSettings,
// } ) => {
// 	const gateway = {
// 		name: 'PayPal',
// 		enabled: false,
// 		description: 'Accept PayPal, Pay Later and alternative payment types.',
// 	};

// 	await plugins.installPluginFromFile( pcpPlugin.zipFilePath );
// 	await requestUtils.activatePlugin( pcpPlugin.slug );
// 	await wooCommerceUtils.createCustomer( customers.usa );

// 	await wooCommerceSettings.visit( 'payments' );
// 	await expect(
// 		wooCommerceSettings.gatewayRow( gateway.name )
// 	).toBeVisible();
// 	await expect(
// 		wooCommerceSettings.gatewayLink( gateway.name )
// 	).toBeVisible();
// 	await expect(
// 		wooCommerceSettings.gatewayToggle( gateway.name )
// 	).toBeVisible();
// 	await expect(
// 		wooCommerceSettings.gatewayDescription(
// 			gateway.name,
// 			gateway.description
// 		)
// 	).toBeVisible();
// 	await expect(
// 		wooCommerceSettings.gatewaySetupButton( gateway.name )
// 	).toBeVisible();
// } );
