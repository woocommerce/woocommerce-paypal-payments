class CartBootstrap {
    constructor(renderer, configurator) {
        this.renderer = renderer;
        this.configurator = configurator;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        jQuery(document.body).on('updated_cart_totals updated_checkout', () => {
            this.renderer.render(this.configurator.configuration());
        });

        this.renderer.render(
            PayPalCommerceGateway.button.wrapper,
            this.configurator.configuration(),
        );
    }

    shouldRender() {
        return document.querySelector(PayPalCommerceGateway.button.wrapper);
    }
}

export default CartBootstrap;