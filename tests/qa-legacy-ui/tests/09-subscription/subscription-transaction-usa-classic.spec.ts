/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	storeConfigSubscriptionUsa,
	payPal,
	pcpConfigUsa,
} from '../../resources';
import { subscriptiontransactionsOnClassicCheckout } from './_test-scenarios';
import { subscriptionPayPalClassicCheckoutVaulted } from './_test-data/paypal';

const customer = storeConfigSubscriptionUsa.customer;
test.beforeAll( async ( { utils } ) => {
	test.setTimeout( 3 * 60 * 1000 );
	await utils.configureStore( {
		...storeConfigSubscriptionUsa,
		classicPages: true,
	} );
	await utils.configurePcp( {
		...pcpConfigUsa,
		standardPayments: {
			enableGateway: true,
			subscriptionsMode: 'PayPal Vaulting',
			vaulting: true,
		},
	} );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
} );

// Regular transactions with vaulting enabled

test.describe( 'Regular transactions', () => {} );

test.describe( 'Customer has vaulted payment method', () => {
	// Restore user so in first test he appears without payment methods
	// beforeEach doesn't work in this case because fixture is constructed earlier then beforeEach is executed
	test.beforeAll( async ( { utils } ) => {
		await utils.restoreCustomer( customer );
	} );
	// Restore user so in a next test he appears without payment methods
	test.afterEach( async ( { utils } ) => {
		await utils.restoreCustomer( customer );
	} );

	test.describe( 'Pay with payment method other than vaulted', () => {} );

	test.describe( 'Pay with vaulted payment method', () => {
		subscriptiontransactionsOnClassicCheckout(
			subscriptionPayPalClassicCheckoutVaulted
		);
	} );
} );
