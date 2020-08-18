import MiniCartBootstap from './modules/ContextBootstrap/MiniCartBootstap';
import SingleProductBootstap from './modules/ContextBootstrap/SingleProductBootstap';
import CartBootstrap from './modules/ContextBootstrap/CartBootstap';
import CheckoutBootstap from './modules/ContextBootstrap/CheckoutBootstap';
import Renderer from './modules/Renderer/Renderer';
import ErrorHandler from './modules/ErrorHandler';
import CreditCardRenderer from "./modules/Renderer/CreditCardRenderer";
import dataClientIdAttributeHandler from "./modules/DataClientIdAttributeHandler";

const bootstrap = () => {
    const errorHandler = new ErrorHandler(PayPalCommerceGateway.labels.error.generic);
    const creditCardRenderer = new CreditCardRenderer(PayPalCommerceGateway, errorHandler);
    const renderer = new Renderer(creditCardRenderer, PayPalCommerceGateway);
    const context = PayPalCommerceGateway.context;
    if (context === 'mini-cart' || context === 'product') {
        const miniCartBootstrap = new MiniCartBootstap(
            PayPalCommerceGateway,
            renderer
        );

        miniCartBootstrap.init();
    }

    if (context === 'product') {
        const singleProductBootstrap = new SingleProductBootstap(
            PayPalCommerceGateway,
            renderer,
        );

        singleProductBootstrap.init();
    }

    if (context === 'cart') {
        const cartBootstrap = new CartBootstrap(
            PayPalCommerceGateway,
            renderer,
        );

        cartBootstrap.init();
    }

    if (context === 'checkout') {
        const checkoutBootstap = new CheckoutBootstap(
            PayPalCommerceGateway,
            renderer
        );

        checkoutBootstap.init();
    }
};
document.addEventListener(
    'DOMContentLoaded',
    () => {
        if (!typeof (PayPalCommerceGateway)) {
            console.error('PayPal button could not be configured.');
            return;
        }
        const script = document.createElement('script');

        script.addEventListener('load', (event) => {
            bootstrap();
        });
        script.setAttribute('src', PayPalCommerceGateway.button.url);
        Object.entries(PayPalCommerceGateway.script_attributes).forEach(
            (keyValue) => {
                script.setAttribute(keyValue[0], keyValue[1]);
            }
        );

        if (PayPalCommerceGateway.data_client_id.set_attribute) {
            dataClientIdAttributeHandler(script, PayPalCommerceGateway.data_client_id);
            return;
        }

        document.body.append(script);
    },
);