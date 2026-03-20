/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import {
	pcpConfigDefault,
	storeConfigClassic,
} from '../../../resources';

setup.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

setup( 'Setup Frontend UI', async ( { utils, customizer } ) => {
	setup.setTimeout( 5 * 60_000 );
	await utils.setupStore();
	await utils.configureStore( storeConfigClassic );
	await utils.configurePcp( pcpConfigDefault );
	await utils.advancedCardProcessing.setup( { enableGateway: true } );
	await customizer.setWooCommerceTermsAndConditions( 'Shop' );
	await utils.updatePcpPlugin();
} );
