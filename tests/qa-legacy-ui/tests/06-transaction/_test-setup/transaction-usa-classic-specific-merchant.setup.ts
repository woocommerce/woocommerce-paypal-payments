/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	payPal,
	pcpConfigUsa,
	storeConfigUsa,
	merchants,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup USA classic, Specific Merchant', async ( { utils, standardPayments } ) => {
	setup.setTimeout( 6 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( {
		...storeConfigUsa,
		merchant: merchants.noReferenceTransaction,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await standardPayments.setup( {
		disableAlternativePaymentMethods: [ 'Venmo' ],
	} );
	await utils.updatePcpPlugin();
} );
