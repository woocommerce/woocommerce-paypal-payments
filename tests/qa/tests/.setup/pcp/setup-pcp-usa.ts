/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { pcpConfigUsa } from '../../../resources';

setup( 'Setup PCP merchant from USA', async ( { utils } ) => {
	await utils.configurePcp( pcpConfigUsa );
} );
