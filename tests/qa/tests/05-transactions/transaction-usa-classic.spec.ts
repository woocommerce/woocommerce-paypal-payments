/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	merchants,
	storeConfigUsa,
	gateways,
	taxSettings,
	wpDebuggingPlugin,
} from '../../resources';
import {
	transactionsOnClassicCart,
	transactionsOnClassicCheckout,
	transactionsOnClassicProduct,
} from './_test-scenarios';
import {
	venmoClassicCartUsa,
	venmoClassicCheckoutUsa,
	venmoClassicProductUsa,
} from './_test-data/venmo';
import {
	payPalCheckoutExcludingTax,
	payPalClassicCheckout,
	payPalClassicCheckoutIntentAuthorized,
} from './_test-data/paypal';
import {
	acdcClassicCheckout,
	acdcClassicCheckoutIntentAuthorized,
	acdcClassicCheckoutExcludingTax,
	acdcClassicCheckout3ds,
} from './_test-data/acdc';
import { fastlaneClassicCheckout } from './_test-data/fastlane';

const { payPal, venmo, acdc, fastlane } = gateways;

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ venmo.id ]: { id: venmo.id, enabled: true },
		[ acdc.id ]: { id: acdc.id, enabled: true },
		[ fastlane.id ]: { id: fastlane.id, enabled: false },
	} );
} );

transactionsOnClassicCheckout( payPalClassicCheckout );
transactionsOnClassicCheckout( acdcClassicCheckout );

/**
 * Venmo is eligible only for USA/USD
 */
// NOT TESTABLE AT THE MOMENT
// transactionsOnClassicCheckout( venmoClassicCheckoutUsa );
// transactionsOnClassicCart( venmoClassicCartUsa );
// transactionsOnClassicProduct( venmoClassicProductUsa );

// Excluding Tax
test.describe( () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	transactionsOnClassicCheckout( payPalCheckoutExcludingTax );
	transactionsOnClassicCheckout( acdcClassicCheckoutExcludingTax );

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );

// Intent Authorized
test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: true } );
	} );

	transactionsOnClassicCheckout( payPalClassicCheckoutIntentAuthorized );
	transactionsOnClassicCheckout( acdcClassicCheckoutIntentAuthorized );

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: false } );
	} );
} );

// ACDC 3DS
test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( { threeDSecure: 'always-3d-secure' } );
	} );

	transactionsOnClassicCheckout( acdcClassicCheckout3ds );

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( { threeDSecure: 'no-3d-secure' } );
	} );
} );

/**
 * Fastlane is eligible only for USA/USD
 */
// NOT TESTABLE AT THE MOMENT BECAUSE OF BUGS:
// https://inpsyde.atlassian.net/browse/PCP-4625
// https://inpsyde.atlassian.net/browse/PCP-4623

// Fastlane
// test.describe( () => {
// 	test.beforeAll( async ( { pcpApi } ) => {
// 		await pcpApi.updatePcpPaymentMethods( {
// 			[ fastlane.id ]: { id: fastlane.id, enabled: true },
// 		} );
// 	} );

// 	transactionsOnClassicCheckout( fastlaneClassicCheckout );

// 	test.afterAll( async ( { pcpApi } ) => {
// 		await pcpApi.updatePcpPaymentMethods( {
// 			[ fastlane.id ]: { id: fastlane.id, enabled: false },
// 		} );
// 	} );
// } );
