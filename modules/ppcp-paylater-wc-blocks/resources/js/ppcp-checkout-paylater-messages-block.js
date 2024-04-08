/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

const render = () => {};

registerPlugin('ppcp-checkout-paylater-messages-block', {
    render,
    scope: 'woocommerce-checkout',
});
