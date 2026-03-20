/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	payUponInvoice,
	pcpConfigGermany,
	storeConfigGermany,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup Germany classic', async ( { utils } ) => {
	setup.setTimeout( 6 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( {
		...storeConfigGermany,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigGermany );
	await utils.pcpPaymentMethodIsEnabled( payUponInvoice.method );
	await utils.updatePcpPlugin();
} );
