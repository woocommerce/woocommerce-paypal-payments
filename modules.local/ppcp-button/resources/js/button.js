import Renderer from './modules/Renderer';
import SingleProductConfig from './modules/SingleProductConfig';
import UpdateCart from './modules/UpdateCart';
import ErrorHandler from './modules/ErrorHandler';

document.addEventListener(
    'DOMContentLoaded',
    () => {
        if (! typeof(PayPalCommerceGateway)) {
            console.error('PayPal button could not be configured.');
            return;
        }
        if (! document.querySelector(PayPalCommerceGateway.button.wrapper)) {
            console.error('No wrapper for PayPal button found.');
            return;
        }
        const context = PayPalCommerceGateway.context;
        if (context === 'product' && ! document.querySelector('form.cart') ) {
            return;
        }
        const errorHandler = new ErrorHandler();
        const renderer = new Renderer({
            url: PayPalCommerceGateway.button.url,
            wrapper:PayPalCommerceGateway.button.wrapper
        });
    const updateCart = new UpdateCart(
        PayPalCommerceGateway.ajax.change_cart.endpoint,
        PayPalCommerceGateway.ajax.change_cart.nonce
    );
    let configurator = null;
    if (context === 'product') {
        configurator = new SingleProductConfig(
            PayPalCommerceGateway,
            updateCart,
            renderer.showButtons.bind(renderer),
            renderer.hideButtons.bind(renderer),
            document.querySelector('form.cart'),
            errorHandler
        );
    }
    if (! configurator) {
        console.error('No context for button found.');
        return;
    }
    renderer.render(configurator.configuration());
    }
);