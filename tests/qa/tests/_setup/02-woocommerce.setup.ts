/**
 * Internal dependencies
 */
import { test as setup, setupWooCommerce } from '../../utils';

setup.use( { screencastOptions: null } );

setup.describe( 'setup:wc;', async () => {
	await setupWooCommerce();
} );
