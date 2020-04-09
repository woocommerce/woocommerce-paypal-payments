class MiniCartBootstap {
    constructor(gateway, renderer, configurator) {
        this.gateway = gateway;
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
            this.gateway.button.mini_cart_wrapper,
            this.configurator.configuration(),
        );
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.mini_cart_wrapper);
    }
}

export default MiniCartBootstap;