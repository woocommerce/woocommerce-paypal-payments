/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { storeConfigMexico } from '../../../resources';

setup( 'Setup Store with Classic Pages for Mexico', async ( { utils } ) => {
	await utils.configureStore( {
		...storeConfigMexico,
		classicPages: true,
	} );
} );
