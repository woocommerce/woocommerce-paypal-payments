/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	payLater,
	payPal,
	pcpConfigUsa,
	storeConfigUsa
} from '../../resources';
import {
	payLaterCartIntentAuthorized,
	payLaterCheckoutIntentAuthorized
} from './_test-data/pay-later';
import {
	payPalCartIntentAuthorized,
	payPalCheckoutIntentAuthorized
} from './_test-data/paypal';
import {
	transactionsOnCart,
	transactionsOnCheckout
} from './_test-scenarios';

test.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

test.describe( () => {

	test.beforeAll( async ( { utils, standardPayments } ) => {
		test.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( storeConfigUsa );
		await utils.configurePcp( pcpConfigUsa );
		await utils.pcpPaymentMethodIsEnabled( payPal.method );
		await utils.pcpPaymentMethodIsEnabled( payLater.method );
		await standardPayments.setup( {
			disableAlternativePaymentMethods: [ 'Venmo' ],
			intent: 'Authorize',
		} );
		await utils.updatePcpPlugin();
	} );

	transactionsOnCart( payPalCartIntentAuthorized );
	transactionsOnCart( payLaterCartIntentAuthorized );
	transactionsOnCheckout( payPalCheckoutIntentAuthorized );
	transactionsOnCheckout( payLaterCheckoutIntentAuthorized );
} );
