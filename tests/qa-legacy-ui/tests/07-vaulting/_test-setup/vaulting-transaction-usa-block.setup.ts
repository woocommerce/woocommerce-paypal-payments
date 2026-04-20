/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	acdc,
	payPal,
	pcpConfigVaulting,
	storeConfigUsa,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup Vaulting USA block', async ( { utils, advancedCardProcessing } ) => {
	setup.setTimeout( 6 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( storeConfigUsa );
	await utils.configurePcp( pcpConfigVaulting );
	await utils.pcpPaymentMethodIsEnabled( payPal.method );
	await utils.pcpPaymentMethodIsEnabled( acdc.method );
	await advancedCardProcessing.setup( { vaulting: true } );
	await utils.updatePcpPlugin();
} );
