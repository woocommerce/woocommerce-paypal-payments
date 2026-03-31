/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import { setupWooCommerce } from 'utils/helpers/woocommerce.helper';

setup.describe( async () => {
	await setupWooCommerce();
} );
