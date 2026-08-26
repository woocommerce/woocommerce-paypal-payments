/**
 * Internal dependencies
 */
import { test as setup, setupWooCommerce } from '../../utils';

setup.describe( 'setup:wc;', async () => {
	await setupWooCommerce();
} );
