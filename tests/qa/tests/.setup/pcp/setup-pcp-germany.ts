/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { pcpConfigGermany } from '../../../resources';

setup( 'Setup PCP merchant from Germany', async ( { utils } ) => {
	await utils.configurePcp( pcpConfigGermany );
} );
