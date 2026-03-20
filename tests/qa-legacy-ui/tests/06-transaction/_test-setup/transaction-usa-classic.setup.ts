/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	acdc,
	payLater,
	payPal,
	pcpConfigUsa,
	storeConfigUsa,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup USA classic', async ( { utils, standardPayments } ) => {
	setup.setTimeout( 6 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await utils.pcpPaymentMethodIsEnabled( payLater.method );
	await utils.pcpPaymentMethodIsEnabled( acdc.method );
	await standardPayments.setup( {
		classicCartButtonLayout: 'Vertical',
		disableAlternativePaymentMethods: [ 'Venmo' ],
	} );
	await utils.updatePcpPlugin();
} );
