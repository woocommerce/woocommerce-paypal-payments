/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { storeConfigUsa } from '../../../resources';

setup( 'Setup Store with Classic Pages for USA', async ( { utils } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
} );
