import ErrorHandler from '../ErrorHandler';
import CartActionHandler from '../ActionHandler/CartActionHandler';

class MiniCartBootstap {
    constructor(gateway, renderer) {
        this.gateway = gateway;
        this.renderer = renderer;
    }

    init() {
        this.render();

        jQuery(document.body).on('wc_fragments_loaded wc_fragments_refreshed', () => {
            this.render();
        });
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.mini_cart_wrapper) !==
            null;
    }

    render() {
        if (!this.shouldRender()) {
            return;
        }

        const actionHandler = new CartActionHandler(
            PayPalCommerceGateway,
            new ErrorHandler(),
        );

        this.renderer.render(
            this.gateway.button.mini_cart_wrapper,
            null,
            actionHandler.configuration()
        );
    }
}

export default MiniCartBootstap;