/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	acdc,
	payLater,
	payPal,
	pcpConfigUsa,
	storeConfigUsa
} from '../../resources';
import {
	payLaterClassicCartHorizontalButton,
	payLaterClassicCheckoutHorizontalButton,
	payLaterClassicProductVerticalButton
} from './_test-data/pay-later';
import {
	payPalClassicCartHorizontalButton,
	payPalClassicCheckoutHorizontalButton,
	payPalClassicProductVerticalButton
} from './_test-data/paypal';
import {
	transactionsOnClassicCart,
	transactionsOnClassicCheckout,
	transactionsOnClassicProduct,
} from './_test-scenarios';

test.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		test.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( {
			...pcpConfigUsa,
			standardPayments: {
				disableAlternativePaymentMethods: [ 'Venmo' ],
				classicCartButtonLayout: 'Horizontal',
				classicCheckoutButtonLayout: 'Horizontal',
				singleProductButtonLayout: 'Vertical',
			}
		} );
		await utils.pcpPaymentMethodIsEnabled( payPal.method );
		await utils.pcpPaymentMethodIsEnabled( payLater.method );
		await utils.pcpPaymentMethodIsEnabled( acdc.method );
		await utils.updatePcpPlugin();
	} );

	transactionsOnClassicCart( payPalClassicCartHorizontalButton );
	transactionsOnClassicCart( payLaterClassicCartHorizontalButton );

	transactionsOnClassicCheckout( payPalClassicCheckoutHorizontalButton );
	transactionsOnClassicCheckout( payLaterClassicCheckoutHorizontalButton );

	transactionsOnClassicProduct( payPalClassicProductVerticalButton );
	transactionsOnClassicProduct( payLaterClassicProductVerticalButton );
} );
