/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	pcpConfigUsa,
	debitOrCreditCard,
	storeConfigUsa,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup USA classic, Debit or Credit Card', async ( { utils, standardPayments } ) => {
	setup.setTimeout( 6 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( debitOrCreditCard.method );
	await standardPayments.setup( {
		disableAlternativePaymentMethods: [ 'Venmo' ],
	} );
	await utils.updatePcpPlugin();
} );
