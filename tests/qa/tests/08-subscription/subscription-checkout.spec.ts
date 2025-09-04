/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { merchants, products, storeConfigUsa } from '../../resources';
import { subscriptionCheckout } from './_test-data';
import { testSubscriptionCheckout } from './_test-scenarios';

const { vaultingGuest, vaultingCustomer, payPalGuest, payPalCustomer } =
	subscriptionCheckout;

const { testSubscriptionOrderGuest, testSubscriptionOrderCustomer } =
	testSubscriptionCheckout;

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: false,
		subscription: true,
		products: [ products.subscription100, products.subscriptionFreeTrial ],
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
		}
	);
} );

test.afterAll( async ( { wooCommerceApi } ) => {
	await wooCommerceApi.deleteAllSubscriptions();
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testData of vaultingGuest ) {
	testSubscriptionOrderGuest( testData );
}

for ( const testData of vaultingCustomer ) {
	testSubscriptionOrderCustomer( testData );
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

	for ( const testData of payPalGuest ) {
		testSubscriptionOrderGuest( testData );
	}

	for ( const testData of payPalCustomer ) {
		testSubscriptionOrderCustomer( testData );
	}
} );
