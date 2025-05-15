/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { storeConfigGermany } from '../../../resources';

setup( 'Setup Store with Classic Pages for Germany', async ( { utils } ) => {
	await utils.configureStore( {
		...storeConfigGermany,
		classicPages: true,
	} );
} );
