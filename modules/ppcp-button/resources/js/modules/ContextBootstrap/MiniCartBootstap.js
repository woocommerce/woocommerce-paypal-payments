import CartActionHandler from '../ActionHandler/CartActionHandler';
import {disable, enable} from "../Helper/ButtonDisabler";

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
        this.handleButtonStatus();

        jQuery(document.body).on('wc_fragments_loaded wc_fragments_refreshed', () => {
            this.render();
            this.handleButtonStatus();
        });

        this.renderer.onButtonsInit(this.gateway.button.mini_cart_wrapper, () => {
            this.handleButtonStatus();
        }, true);
    }

    handleButtonStatus() {
        if (!this.shouldEnable()) {
            this.renderer.disableSmartButtons(this.gateway.button.mini_cart_wrapper);
            disable(this.gateway.button.mini_cart_wrapper);
            return;
        }
        this.renderer.enableSmartButtons(this.gateway.button.mini_cart_wrapper);
        enable(this.gateway.button.mini_cart_wrapper);
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.mini_cart_wrapper) !== null
            || document.querySelector(this.gateway.hosted_fields.mini_cart_wrapper) !== null;
    }

    shouldEnable() {
        return this.shouldRender()
            && this.gateway.button.is_mini_cart_disabled !== true;
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
