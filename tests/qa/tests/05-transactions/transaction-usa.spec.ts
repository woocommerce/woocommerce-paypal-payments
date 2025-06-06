/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	merchants,
	storeConfigUsa,
	gateways,
	taxSettings,
} from '../../resources';
import {
	transactionsOnCheckout,
	transactionsOnPayByLink,
} from './_test-scenarios';
import {
	payPalCheckout,
	payPalCheckoutExcludingTax,
	payPalCheckoutIntentAuthorized,
} from './_test-data/paypal';
import {
	acdcCheckout,
	acdcCheckoutExcludingTax,
	acdcCheckoutIntentAuthorized,
	acdcCheckout3ds,
	acdcPayByLink,
	acdcPayByLink3ds,
	acdcPayByLinkExcludingTax,
	acdcPayByLinkIntentAuthorized,
} from './_test-data/acdc';
import { fastlaneCheckout } from './_test-data/fastlane';

const { payPal, venmo, acdc, fastlane } = gateways;

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigUsa );
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

transactionsOnCheckout( payPalCheckout );
transactionsOnCheckout( acdcCheckout );
transactionsOnPayByLink( acdcPayByLink );

// Excluding Tax
test.describe( () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	transactionsOnCheckout( payPalCheckoutExcludingTax );
	transactionsOnCheckout( acdcCheckoutExcludingTax );
	transactionsOnPayByLink( acdcPayByLinkExcludingTax );

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );

// Intent Authorized
test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: true } );
	} );

	transactionsOnCheckout( payPalCheckoutIntentAuthorized );
	transactionsOnCheckout( acdcCheckoutIntentAuthorized );
	transactionsOnPayByLink( acdcPayByLinkIntentAuthorized );

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( { authorizeOnly: false } );
	} );
} );

// ACDC 3DS
test.describe( () => {
	test.beforeAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			threeDSecure: 'always-3d-secure',
		} );
	} );

	transactionsOnCheckout( acdcCheckout3ds );
	transactionsOnPayByLink( acdcPayByLink3ds );

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpPaymentMethods( {
			threeDSecure: 'no-3d-secure',
		} );
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

// 	transactionsOnCheckout( fastlaneCheckout );

// 	test.afterAll( async ( { pcpApi } ) => {
// 		await pcpApi.updatePcpPaymentMethods( {
// 			[ fastlane.id ]: { id: fastlane.id, enabled: false },
// 		} );
// 	} );
// } );
