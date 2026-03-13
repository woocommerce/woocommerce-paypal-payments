/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import {
	taxSettings,
	pcpConfigGermany,
	pcpConfigMexico,
	pcpConfigUsa,
	storeConfigGermany,
	storeConfigMexico,
	storeConfigUsa,
} from '../../resources';

setup( 'setup:checkout:block;', async ( { utils } ) => {
	await utils.configureStore( { classicPages: false } );
} );

setup( 'setup:checkout:classic;', async ( { utils } ) => {
	await utils.configureStore( { classicPages: true } );
} );

setup( 'setup:tax:inc;', async ( { utils } ) => {
	await utils.configureStore( { taxes: taxSettings.including } );
} );

setup( 'setup:tax:exc;', async ( { utils } ) => {
	await utils.configureStore( { taxes: taxSettings.excluding } );
} );

setup( 'setup:pcp:usa;', async ( { utils } ) => {
	await utils.configureStore( storeConfigUsa );
	await utils.configurePcp( pcpConfigUsa );
} );

setup( 'setup:pcp:germany;', async ( { utils } ) => {
	await utils.configureStore( storeConfigGermany );
	await utils.configurePcp( pcpConfigGermany );
} );

setup( 'setup:pcp:mexico;', async ( { utils } ) => {
	await utils.configureStore( storeConfigMexico );
	await utils.configurePcp( pcpConfigMexico );
} );

setup( 'setup:pcp:update', async ( { utils } ) => {
	await utils.updatePcpPlugin();
} );
