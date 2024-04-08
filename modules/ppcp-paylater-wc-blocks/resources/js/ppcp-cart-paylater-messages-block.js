/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

const render = () => {};

registerPlugin('ppcp-cart-paylater-messages-block', {
    render,
    scope: 'woocommerce-checkout',
});
