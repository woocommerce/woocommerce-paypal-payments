class CartBootstrap {
    constructor(gateway, renderer, configurator) {
        this.gateway = gateway;
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
            this.gateway.button.wrapper,
            this.configurator.configuration(),
        );
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.wrapper) !== null;
    }
}

export default CartBootstrap;