/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import {
	pcpConfigGermany,
	pcpConfigMexico,
	pcpConfigUsa,
	storeConfigGermany,
	storeConfigMexico,
	storeConfigUsa,
} from '../../resources';

setup.describe( () => {
	setup.beforeAll( async ( { utils } ) => {
		await utils.resetEnvironment();
		await utils.createStorageStates();
	} );

	setup( 'setup:env:reset;', async ( { utils } ) => {
		setup.setTimeout( 4 * 60_000 );
		await utils.setupStore();
	} );

	setup( 'setup:pcp:usa;', async ( { utils } ) => {
		setup.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( storeConfigUsa );
		await utils.configurePcp( pcpConfigUsa );
	} );

	setup( 'setup:pcp:germany;', async ( { utils } ) => {
		setup.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( storeConfigGermany );
		await utils.configurePcp( pcpConfigGermany );
	} );

	setup( 'setup:pcp:mexico;', async ( { utils } ) => {
		setup.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( storeConfigMexico );
		await utils.configurePcp( pcpConfigMexico );
	} );
} );

setup( 'setup:pcp:update;', async ( { utils } ) => {
	await utils.updatePcpPlugin();
} );

setup( 'setup:classic:pages;', async ( { utils } ) => {
	await utils.configureStore( {
		classicPages: true,
	} );
} );

setup( 'setup:block:pages;', async ( { utils } ) => {
	await utils.configureStore( {
		classicPages: false,
	} );
} );
