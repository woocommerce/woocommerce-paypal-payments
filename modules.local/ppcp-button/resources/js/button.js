import Renderer from './modules/Renderer';
import SingleProductConfig from './modules/SingleProductConfig';
import UpdateCart from './modules/UpdateCart';
import ErrorHandler from './modules/ErrorHandler';
import CartConfig from "./modules/CartConfig";

document.addEventListener(
    'DOMContentLoaded',
    () => {
        if (! typeof(PayPalCommerceGateway)) {
            console.error('PayPal button could not be configured.');
            return;
        }
        const context = PayPalCommerceGateway.context;
        const errorHandler = new ErrorHandler();
        const defaultConfigurator = new CartConfig(
            PayPalCommerceGateway,
            errorHandler
        );
        // Configure mini cart buttons
        if (document.querySelector(PayPalCommerceGateway.button.mini_cart_wrapper)) {

            const renderer = new Renderer(
                PayPalCommerceGateway.button.url,
                PayPalCommerceGateway.button.mini_cart_wrapper
            );
            renderer.render(defaultConfigurator.configuration())
        }
        jQuery( document.body ).on( 'wc_fragments_loaded wc_fragments_refreshed', () => {
            if (! document.querySelector(PayPalCommerceGateway.button.mini_cart_wrapper)) {
                return;
            }
            const renderer = new Renderer(
                PayPalCommerceGateway.button.url,
                PayPalCommerceGateway.button.mini_cart_wrapper
            );
            renderer.render(defaultConfigurator.configuration())
        } );

        // Configure context buttons
        if (! document.querySelector(PayPalCommerceGateway.button.wrapper)) {
            return;
        }
        let configurator = null;
        if (context === 'product') {
            if (! document.querySelector('form.cart')) {
                return;
            }
            const updateCart = new UpdateCart(
                PayPalCommerceGateway.ajax.change_cart.endpoint,
                PayPalCommerceGateway.ajax.change_cart.nonce
            );
            configurator = new SingleProductConfig(
                PayPalCommerceGateway,
                updateCart,
                renderer.showButtons.bind(renderer),
                renderer.hideButtons.bind(renderer),
                document.querySelector('form.cart'),
                errorHandler
            );
        }
        if (context === 'cart') {
            configurator = defaultConfigurator;
        }
        if (! configurator) {
            console.error('No context for button found.');
            return;
        }

        const renderer = new Renderer(
            PayPalCommerceGateway.button.url,
            PayPalCommerceGateway.button.wrapper
        );
        renderer.render(configurator.configuration());
    }
);