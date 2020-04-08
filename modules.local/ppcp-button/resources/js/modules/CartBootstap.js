import Renderer from './Renderer';

class CartBootstrap {
    constructor(configurator) {
        this.configurator = configurator;
    }

    init() {
        const buttonWrapper = PayPalCommerceGateway.button.wrapper;

        if (!document.querySelector(buttonWrapper)) {
            return;
        }

        const renderer = new Renderer(buttonWrapper);

        jQuery(document.body).on('updated_cart_totals updated_checkout', () => {
            renderer.render(this.configurator.configuration());
        });

        renderer.render(this.configurator.configuration());
    }
}

export default CartBootstrap;