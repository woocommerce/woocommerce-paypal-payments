/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	payPal,
	pcpConfigUsa,
	storeConfigUsa,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup refund USA block', async ( { utils } ) => {
	setup.setTimeout( 6 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( storeConfigUsa );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await utils.updatePcpPlugin();
} );
