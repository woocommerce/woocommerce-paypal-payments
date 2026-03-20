/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	oxxo,
	pcpConfigMexico,
	storeConfigMexico,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup Mexico classic', async ( { utils } ) => {
	setup.setTimeout( 6 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( {
		...storeConfigMexico,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigMexico );
	await utils.pcpPaymentMethodIsEnabled( oxxo.method );
	await utils.updatePcpPlugin();
} );
