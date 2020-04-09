import Renderer from './Renderer';

class MiniCartBootstap {
    constructor(configurator) {
        this.configurator = configurator;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        const renderer = new Renderer(PayPalCommerceGateway.button.mini_cart_wrapper);

        jQuery(document.body).
            on('wc_fragments_loaded wc_fragments_refreshed', () => {
                renderer.render(this.configurator.configuration());
            });

        renderer.render(this.configurator.configuration());
    }

    shouldRender() {
        return document.querySelector(PayPalCommerceGateway.button.mini_cart_wrapper)
    }
}

export default MiniCartBootstap;