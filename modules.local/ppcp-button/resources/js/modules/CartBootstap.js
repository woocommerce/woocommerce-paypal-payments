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

        this.render();

        jQuery(document.body).on('updated_cart_totals updated_checkout', () => {
            this.render();
        });
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.wrapper) !== null;
    }

    render() {
        this.renderer.render(
            this.gateway.button.wrapper,
            this.configurator.configuration(),
        );
    }
}

export default CartBootstrap;