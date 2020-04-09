import ErrorHandler from './modules/ErrorHandler';
import CartConfig from './modules/CartConfig';
import CheckoutConfig from "./modules/CheckoutConfig";
import MiniCartBootstap from './modules/MiniCartBootstap';
import SingleProductBootstap from './modules/SingleProductBootstap';
import CartBootstrap from './modules/CartBootstap';
import CheckoutBootstap from './modules/CheckoutBootstap';
import Renderer from './modules/Renderer';

const bootstrap = () => {
    const renderer = new Renderer;
    const errorHandler = new ErrorHandler();
    const cartConfig = new CartConfig(
        PayPalCommerceGateway,
        errorHandler,
    );
    const checkoutConfig = new CheckoutConfig(
        PayPalCommerceGateway,
        errorHandler
    );
    const context = PayPalCommerceGateway.context;

    if (context === 'mini-cart' || context === 'product') {
        const miniCartBootstap = new MiniCartBootstap(
            PayPalCommerceGateway,
            renderer,
            cartConfig,
        );

        miniCartBootstap.init();
    }

    if (context === 'product') {
        const singleProductBootstap = new SingleProductBootstap(
            PayPalCommerceGateway,
            renderer,
        );

        singleProductBootstap.init();
    }

    if (context === 'cart') {
        const cartBootstrap = new CartBootstrap(
            PayPalCommerceGateway,
            renderer,
            cartConfig,
        );

        cartBootstrap.init();
    }

    if (context === 'checkout') {
        const checkoutBootstap = new CheckoutBootstap(
            PayPalCommerceGateway,
            renderer,
            checkoutConfig,
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

        script.setAttribute('src', PayPalCommerceGateway.button.url);
        script.addEventListener('load', (event) => {
            bootstrap();
        });

        document.body.append(script);
    },
);