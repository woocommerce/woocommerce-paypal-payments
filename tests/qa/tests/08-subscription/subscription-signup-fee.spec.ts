/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { products } from '../../resources';
import { subscriptionSignUpFee } from './_test-data';
import { testSubscriptionSignUpFee } from './_test-scenarios';

const { vaultingGuest, vaultingCustomer, payPalGuest, payPalCustomer } =
	subscriptionSignUpFee;

const { testSignUpFeeOrderGuest, testSignUpFeeOrderCustomer } =
	testSubscriptionSignUpFee;

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( {
		enableClassicPages: false,
		enableSubscriptionsPlugin: true,
		products: [ products.subscriptionSignUpFee ],
	} );
} );

for ( const testOrder of vaultingGuest ) {
	testSignUpFeeOrderGuest( testOrder );
}

for ( const testOrder of vaultingCustomer ) {
	testSignUpFeeOrderCustomer( testOrder );
}

test.describe( 'PayPal Subscription sign-up fee', () => {
	test.beforeAll( async ( { utils, pcpApi } ) => {
		await pcpApi.updatePcpSettings( {
			savePaypalAndVenmo: false,
			saveCardDetails: false,
		} );
		await utils.configureStore( {
			products: [ products.subscriptionPayPalSignUpFee ],
		} );
	} );

	test.afterAll( async ( { pcpApi } ) => {
		await pcpApi.updatePcpSettings( {
			savePaypalAndVenmo: true,
			saveCardDetails: true,
		} );
	} );

	for ( const testOrder of payPalGuest ) {
		testSignUpFeeOrderGuest( testOrder );
	}

	for ( const testOrder of payPalCustomer ) {
		testSignUpFeeOrderCustomer( testOrder );
	}
} );
