import Renderer from './Renderer';

class MiniCartBootstap {
    constructor(configurator) {
        this.configurator = configurator;
    }

    init() {
        const miniCartWrapper = PayPalCommerceGateway.button.mini_cart_wrapper;

        if (!document.querySelector(miniCartWrapper)) {
            return;
        }

        const renderer = new Renderer(miniCartWrapper);

        jQuery(document.body).
            on('wc_fragments_loaded wc_fragments_refreshed', () => {
                renderer.render(this.configurator.configuration());
            });

        renderer.render(this.configurator.configuration());
    }
}

export default MiniCartBootstap;