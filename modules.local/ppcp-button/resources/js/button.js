import Renderer from './modules/Renderer';
import SingleProductConfig from './modules/SingleProductConfig';
import UpdateCart from './modules/UpdateCart';
import ErrorHandler from './modules/ErrorHandler';
import CartConfig from './modules/CartConfig';

const bootstrap = ()=> {
    const context = PayPalCommerceGateway.context;
    const errorHandler = new ErrorHandler();
    const defaultConfigurator = new CartConfig(
        PayPalCommerceGateway,
        errorHandler
    );
    // Configure mini cart buttons
    if (document.querySelector(PayPalCommerceGateway.button.mini_cart_wrapper)) {

        const renderer = new Renderer(
            PayPalCommerceGateway.button.mini_cart_wrapper
        );
        renderer.render(defaultConfigurator.configuration())
    }
    jQuery( document.body ).on( 'wc_fragments_loaded wc_fragments_refreshed', () => {
        if (! document.querySelector(PayPalCommerceGateway.button.mini_cart_wrapper)) {
            return;
        }
        const renderer = new Renderer(
            PayPalCommerceGateway.button.mini_cart_wrapper
        );
        renderer.render(defaultConfigurator.configuration())
    } );

    // Configure checkout buttons
    jQuery( document.body ).on( 'updated_checkout', () => {
        const renderer = new Renderer(
            PayPalCommerceGateway.button.order_button_wrapper
        );
        renderer.render(defaultConfigurator.configuration());
    } );

    // Configure context buttons
    if (! document.querySelector(PayPalCommerceGateway.button.wrapper)) {
        return;
    }
    const renderer = new Renderer(
        PayPalCommerceGateway.button.wrapper
    );
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

        jQuery( document.body ).on( 'updated_cart_totals updated_checkout', () => {
            renderer.render(configurator.configuration())
        });
    }
    if (! configurator) {
        console.error('No context for button found.');
        return;
    }

    renderer.render(configurator.configuration());
}
document.addEventListener(
    'DOMContentLoaded',
    () => {

        if (! typeof(PayPalCommerceGateway)) {
            console.error('PayPal button could not be configured.');
            return;
        }

        const script = document.createElement('script');
        script.setAttribute('src', PayPalCommerceGateway.button.url);
        script.addEventListener('load', (event) => {
            bootstrap();
        })
        document.body.append(script);


    }
);