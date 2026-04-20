/**
 * Internal dependencies
 */
import { test } from '../../utils';
/**
 * External dependencies
 */
import {
	acdc,
	payPal,
	pcpConfigVaulting,
	storeConfigClassic,
} from '../../resources';
import { transactionsOnClassicCheckout } from '../06-transaction/_test-scenarios';
import {
	vaultingPayPalClassicCheckoutRegular,
	vaultingPayPalClassicCheckoutNotVaulted,
	vaultingPayPalClassicCheckoutVaulted,
} from './_test-data/paypal';
import {
	vaultingAcdcClassicCheckoutRegular,
	vaultingAcdcClassicCheckoutVaulted,
} from './_test-data/acdc';

// Regular transactions with vaulting enabled

transactionsOnClassicCheckout( vaultingAcdcClassicCheckoutRegular );

test.describe( 'Customer has vaulted payment method', () => {
	test.beforeAll( async ( { utils, customerPaymentMethods } ) => {
		// Recreate customer with vaulted payment methods
	} );

	test.describe( 'Pay with payment method other than vaulted', () => {
		//......
	} );

	test.describe( 'Pay with vaulted payment method', () => {
		//......
	} );
} );
