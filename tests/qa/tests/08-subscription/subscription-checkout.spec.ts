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

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		enableClassicPages: false,
		enableSubscriptionsPlugin: true,
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
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testOrder of vaultingGuest ) {
	testSubscriptionOrderGuest( testOrder );
}

for ( const testOrder of vaultingCustomer ) {
	testSubscriptionOrderCustomer( testOrder );
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

	for ( const testOrder of payPalGuest ) {
		testSubscriptionOrderGuest( testOrder );
	}

	for ( const testOrder of payPalCustomer ) {
		testSubscriptionOrderCustomer( testOrder );
	}
} );
