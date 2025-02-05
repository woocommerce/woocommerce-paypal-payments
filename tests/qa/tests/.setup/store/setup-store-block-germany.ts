/**
 * Internal dependencies
 */
import { test as setup } from '../../../utils';
import { storeConfigDefault } from '../../../resources';

setup( 'Setup Store with Block Pages for Germany', async ( { utils } ) => {
	await utils.configureStore( storeConfigDefault );
} );
