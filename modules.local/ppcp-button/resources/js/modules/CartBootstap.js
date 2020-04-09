import Renderer from './Renderer';

class CartBootstrap {
    constructor(configurator) {
        this.configurator = configurator;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        const renderer = new Renderer(PayPalCommerceGateway.button.wrapper);

        jQuery(document.body).on('updated_cart_totals updated_checkout', () => {
            renderer.render(this.configurator.configuration());
        });

        renderer.render(this.configurator.configuration());
    }

    shouldRender() {
        return document.querySelector(PayPalCommerceGateway.button.wrapper);
    }
}

export default CartBootstrap;