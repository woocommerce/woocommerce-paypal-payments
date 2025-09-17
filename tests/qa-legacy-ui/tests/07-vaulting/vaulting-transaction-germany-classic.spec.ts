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
	vaultingAcdcClassicCheckoutReguar,
	vaultingAcdcClassicCheckoutVaulted,
} from './_test-data/acdc';

test.beforeAll( async ( { utils, advancedCardProcessing } ) => {
	await utils.configureStore( storeConfigClassic );
	await utils.configurePcp( pcpConfigVaulting );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await utils.pcpPaymentMethodIsEnabled( acdc.method );
	await advancedCardProcessing.setup( { vaulting: true } );
} );

// Regular transactions with vaulting enabled

transactionsOnClassicCheckout( vaultingAcdcClassicCheckoutReguar );

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
