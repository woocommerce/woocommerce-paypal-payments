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

setup( 'Setup USA classic, Debit or Credit Card, Intent Authorized', async ( { utils, standardPayments } ) => {
	setup.setTimeout( 5 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( debitOrCreditCard.method );
	await standardPayments.setup( {
		disableAlternativePaymentMethods: [ 'Venmo' ],
		intent: 'Authorize',
	} );
	await utils.updatePcpPlugin();
} );
