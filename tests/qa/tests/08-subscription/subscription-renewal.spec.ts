/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { merchants, products, storeConfigUsa } from '../../resources';
import { subscriptionRenewal } from './_test-data';
import {
	testFreeTrialSubscriptionRenewal,
	testSubscriptionRenewal,
} from './_test-scenarios';

const {
	vaultingRenewal,
	vaultingFreeTrialRenewal,
	payPalRenewal,
	payPalFreeTrialRenewal,
} = subscriptionRenewal;

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		wpDebugging: false,
		classicPages: false,
		subscription: true,
		products: [
			products.subscription10,
			products.subscriptionFreeTrial,
		],
	} );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret,
		{
			isCasualSeller: false,
			areOptionalPaymentMethodsEnabled: true,
			products: [ 'physical', 'virtual', 'subscriptions' ],
		},
	);
} );

for ( const testData of vaultingRenewal ) {
	testSubscriptionRenewal( testData );
}

for ( const testData of vaultingFreeTrialRenewal ) {
	testFreeTrialSubscriptionRenewal( testData );
}

test.describe( 'PayPal Subscription', () => {
	test.beforeAll( async ( { utils, pcpApi } ) => {
		await pcpApi.updatePcpSettings( {
			savePaypalAndVenmo: false,
			saveCardDetails: false,
		} );
		await utils.configureStore( {
			products: [
				products.subscriptionPayPal,
				products.subscriptionPayPalFreeTrial,
			],
		} );
	} );

	for ( const testData of payPalRenewal ) {
		testSubscriptionRenewal( testData );
	}

	for ( const testData of payPalFreeTrialRenewal ) {
		testFreeTrialSubscriptionRenewal( testData );
	}
});
