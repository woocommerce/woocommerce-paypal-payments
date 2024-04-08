/**
 * External dependencies
 */
import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';

/**
 * Internal dependencies
 */
import metadata from './block.json';

registerCheckoutBlock({
    metadata,
    component: () => false,
});
