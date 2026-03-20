/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	payPal,
	pcpConfigUsa,
	storeConfigUsa
} from '../../../resources';
import { specificMerchant } from '../_test-data/paypal';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup USA classic, Specific Merchant', async ( { utils, standardPayments } ) => {
	setup.setTimeout( 5 * 60_000 );
		setup.setTimeout( 6 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			merchant: specificMerchant,
			classicPages: true,
		} );
		await utils.configurePcp( pcpConfigUsa );
		await utils.pcpPaymentMethodIsEnabled( payPal.method );
		await standardPayments.setup( {
			disableAlternativePaymentMethods: [ 'Venmo' ],
		} );
		await utils.updatePcpPlugin();
} );
