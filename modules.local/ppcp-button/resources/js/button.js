import ErrorHandler from './modules/ErrorHandler';
import CartConfig from './modules/CartConfig';
import MiniCartBootstap from './modules/MiniCartBootstap';
import SingleProductBootstap from './modules/SingleProductBootstap';
import CartBootstrap from './modules/CartBootstap';
import CheckoutBootstap from './modules/CheckoutBootstap';

const bootstrap = () => {
    const context = PayPalCommerceGateway.context;
    const errorHandler = new ErrorHandler();
    const defaultConfig = new CartConfig(
        PayPalCommerceGateway,
        errorHandler,
    );

    if (context === 'mini-cart') {
        const miniCartBootstap = new MiniCartBootstap(defaultConfig);

        miniCartBootstap.init();
    }

    if (context === 'product') {
        const singleProductBootstap = new SingleProductBootstap();
        const miniCartBootstap = new MiniCartBootstap(defaultConfig);

        singleProductBootstap.init();
        miniCartBootstap.init();
    }

    if (context === 'cart') {
        const cartBootstrap = new CartBootstrap(defaultConfig);

        cartBootstrap.init();
    }

    if (context === 'checkout') {
        const checkoutBootstap = new CheckoutBootstap(defaultConfig);

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