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
