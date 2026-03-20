/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	acdc,
	pcpConfigUsa,
	storeConfigUsa,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup USA classic, ACDC 3DS', async ( { utils, advancedCardProcessing } ) => {
	setup.setTimeout( 6 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( acdc.method );
	await advancedCardProcessing.setup( {
		enableGateway: true,
		threeDSecure: 'Always trigger 3D Secure',
	} );
	await utils.updatePcpPlugin();
} );
