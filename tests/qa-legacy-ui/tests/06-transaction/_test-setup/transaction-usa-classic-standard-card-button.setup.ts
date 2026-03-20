/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	pcpConfigUsa,
	standardCardButton,
	storeConfigUsa,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup USA classic, Standard Card Button', async ( { utils, standardPayments } ) => {
	setup.setTimeout( 6 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( standardCardButton.method );
	await standardPayments.setup( {
		disableAlternativePaymentMethods: [ 'Venmo' ],
	} );
	await utils.updatePcpPlugin();
} );
