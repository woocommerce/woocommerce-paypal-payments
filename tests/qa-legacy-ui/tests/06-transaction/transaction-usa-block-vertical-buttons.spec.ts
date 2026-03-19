/**
 * Internal dependencies
 */
import { test } from '../../utils';
/**
 * External dependencies
 */
import {
	acdc,
	payLater,
	payPal,
	pcpConfigUsa,
	storeConfigUsa,
} from '../../resources';
import {
	transactionsOnProduct,
} from './_test-scenarios';
import {
	payPalProductVerticalButton,
} from './_test-data/paypal';
import {
	payLaterProductVerticalButton,
} from './_test-data/pay-later';

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
		await utils.pcpPaymentMethodIsEnabled( acdc.method );
		await standardPayments.setup( {
			disableAlternativePaymentMethods: [ 'Venmo' ],
			singleProductButtonLayout: 'Vertical',
		} );
		await utils.updatePcpPlugin();
	} );

	transactionsOnProduct( payPalProductVerticalButton );
	transactionsOnProduct( payLaterProductVerticalButton );
} );
