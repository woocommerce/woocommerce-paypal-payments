class MiniCartBootstap {
    constructor(renderer, configurator) {
        this.renderer = renderer;
        this.configurator = configurator;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        jQuery(document.body).
            on('wc_fragments_loaded wc_fragments_refreshed', () => {
                renderer.render(this.configurator.configuration());
            });

        this.renderer.render(
            PayPalCommerceGateway.button.mini_cart_wrapper,
            this.configurator.configuration(),
        );
    }

    shouldRender() {
        return document.querySelector(PayPalCommerceGateway.button.mini_cart_wrapper);
    }
}

export default MiniCartBootstap;