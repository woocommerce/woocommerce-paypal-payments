import CartActionHandler from '../ActionHandler/CartActionHandler';
import {disable} from "../Helper/ButtonDisabler";

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

        if (!this.shouldEnable()) {
            this.renderer.disableSmartButtons();
            disable(this.gateway.button.wrapper);
            disable(this.gateway.messages.wrapper);
            return;
        }

        jQuery(document.body).on('wc_fragments_loaded wc_fragments_refreshed', () => {
            this.render();
        });
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.mini_cart_wrapper) !== null
            || document.querySelector(this.gateway.hosted_fields.mini_cart_wrapper) !== null;
    }

    shouldEnable() {
        return this.shouldRender()
            && this.gateway.button.is_disabled !== true;
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
