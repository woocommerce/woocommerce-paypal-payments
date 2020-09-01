import ErrorHandler from '../ErrorHandler';
import CartActionHandler from '../ActionHandler/CartActionHandler';

class MiniCartBootstap {
    constructor(gateway, renderer) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.actionHandler = null;
    }

    init() {

        this.actionHandler = new CartActionHandler(
            PayPalCommerceGateway,
            new ErrorHandler(this.gateway.labels.error.generic),
        );
        this.render();

        jQuery(document.body).on('wc_fragments_loaded wc_fragments_refreshed', () => {
            this.render();
        });
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.mini_cart_wrapper) !==
            null || document.querySelector(this.gateway.hosted_fields.mini_cart_wrapper) !==
        null;
    }

    render() {
        if (!this.shouldRender()) {
            return;
        }

        this.renderer.render(
            this.gateway.button.mini_cart_wrapper,
            this.gateway.hosted_fields.mini_cart_wrapper,
            this.actionHandler.configuration()
        );
    }
}

export default MiniCartBootstap;