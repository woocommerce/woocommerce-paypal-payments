/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { pcpConfigMexico } from '../../../resources';

setup( 'Setup PCP merchant from Mexico', async ( { utils } ) => {
	await utils.configurePcp( pcpConfigMexico );
} );
