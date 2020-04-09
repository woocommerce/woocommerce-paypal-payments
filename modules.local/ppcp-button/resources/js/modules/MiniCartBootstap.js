class MiniCartBootstap {
    constructor(gateway, renderer, configurator) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.configurator = configurator;
    }

    init() {
        this.render();

        jQuery(document.body).on('wc_fragments_loaded wc_fragments_refreshed', () => {
            this.render();
        });
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.mini_cart_wrapper) !== null;
    }

    render() {
        // Compared to the other bootstrapper we need the checker inside the
        // renderer because the mini cart is refreshed with AJAX and the
        // wrapper DOM might not be there from the start
        if (!this.shouldRender()) {
            return;
        }

        this.renderer.render(
            this.gateway.button.mini_cart_wrapper,
            this.configurator.configuration(),
        );
    }
}

export default MiniCartBootstap;