import ErrorHandler from './modules/ErrorHandler';
import CartConfig from './modules/CartConfig';
import MiniCartBootstap from './modules/MiniCartBootstap';
import SingleProductBootstap from './modules/SingleProductBootstap';
import CartBootstrap from './modules/CartBootstap';
import CheckoutBootstap from './modules/CheckoutBootstap';
import Renderer from './modules/Renderer';

const bootstrap = () => {
    const context = PayPalCommerceGateway.context;
    const renderer = new Renderer;
    const errorHandler = new ErrorHandler();
    const defaultConfig = new CartConfig(
        PayPalCommerceGateway,
        errorHandler,
    );

    if (context === 'mini-cart') {
        const miniCartBootstap = new MiniCartBootstap(renderer, defaultConfig);

        miniCartBootstap.init();
    }

    if (context === 'product') {
        const singleProductBootstap = new SingleProductBootstap(renderer);
        const miniCartBootstap = new MiniCartBootstap(renderer, defaultConfig);

        singleProductBootstap.init();
        miniCartBootstap.init();
    }

    if (context === 'cart') {
        const cartBootstrap = new CartBootstrap(renderer, defaultConfig);

        cartBootstrap.init();
    }

    if (context === 'checkout') {
        const checkoutBootstap = new CheckoutBootstap(renderer, defaultConfig);

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