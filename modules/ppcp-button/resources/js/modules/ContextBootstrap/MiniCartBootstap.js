import CartActionHandler from '../ActionHandler/CartActionHandler';

class MiniCartBootstap {
    constructor(gateway, renderer, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.errorHandler = errorHandler;
        this.actionHandler = null;
    }

    init() {

        this.actionHandler = new CartActionHandler(
            PayPalCommerceGateway,
            this.errorHandler,
        );
        this.render();

        jQuery(document.body).on('wc_fragments_loaded wc_fragments_refreshed', () => {
            this.render();
        });
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.mini_cart_wrapper) !== null
            || document.querySelector(this.gateway.hosted_fields.mini_cart_wrapper) !== null;
    }

    render() {
        if (!this.shouldRender()) {
            return;
        }

        this.renderer.render(
            this.actionHandler.configuration(),
            {
                button: {
                    wrapper: this.gateway.button.mini_cart_wrapper,
                    style: this.gateway.button.mini_cart_style,
                },
            }
        );
    }
}

export default MiniCartBootstap;
