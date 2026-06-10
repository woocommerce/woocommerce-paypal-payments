/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { products } from '../../resources';
import { subscriptionClassicCheckout } from './_test-data';
import { testSubscriptionClassicCheckout } from './_test-scenarios';

const { vaultingGuest, vaultingCustomer, payPalGuest, payPalCustomer } =
	subscriptionClassicCheckout;

const { testSubscriptionOrderGuest, testSubscriptionOrderCustomer } =
	testSubscriptionClassicCheckout;

test.beforeAll( async ( { utils, pcpApi } ) => {
	await pcpApi.updatePcpSettings( {
		savePaypalAndVenmo: true,
		saveCardDetails: true,
	} );
	await utils.configureStore( {
		enableClassicPages: true,
		enableSubscriptionsPlugin: true,
		products: [ products.subscription100, products.subscriptionFreeTrial ],
	} );
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
