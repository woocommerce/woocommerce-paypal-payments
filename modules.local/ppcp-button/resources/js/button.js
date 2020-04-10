import MiniCartBootstap from './modules/MiniCartBootstap';
import SingleProductBootstap from './modules/SingleProductBootstap';
import CartBootstrap from './modules/CartBootstap';
import CheckoutBootstap from './modules/CheckoutBootstap';
import Renderer from './modules/Renderer';

const bootstrap = () => {
    const renderer = new Renderer(PayPalCommerceGateway);
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

        script.setAttribute('src', PayPalCommerceGateway.button.url);
        script.addEventListener('load', (event) => {
            bootstrap();
        });

        document.body.append(script);
    },
);