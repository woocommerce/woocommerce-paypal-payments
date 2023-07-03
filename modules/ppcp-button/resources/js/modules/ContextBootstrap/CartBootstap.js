import CartActionHandler from '../ActionHandler/CartActionHandler';
import {setVisible} from "../Helper/Hiding";
import {disable, enable} from "../Helper/ButtonDisabler";

class CartBootstrap {
    constructor(gateway, renderer, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.errorHandler = errorHandler;

        this.renderer.onButtonsInit(this.gateway.button.wrapper, () => {
            this.handleButtonStatus();
        }, true);
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        this.render();
        this.handleButtonStatus();

        jQuery(document.body).on('updated_cart_totals updated_checkout', () => {
            this.render();
            this.handleButtonStatus();

            fetch(
                this.gateway.ajax.cart_script_params.endpoint,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                }
            )
            .then(result => result.json())
            .then(result => {
                if (! result.success) {
                    return;
                }

                const newParams = result.data;
                const reloadRequired = this.gateway.url_params.intent !== newParams.intent;

                // TODO: should reload the script instead
                setVisible(this.gateway.button.wrapper, !reloadRequired)
            });
        });
    }

    handleButtonStatus() {
        if (!this.shouldEnable()) {
            this.renderer.disableSmartButtons(this.gateway.button.wrapper);
            disable(this.gateway.button.wrapper);
            disable(this.gateway.messages.wrapper);
            return;
        }
        this.renderer.enableSmartButtons(this.gateway.button.wrapper);
        enable(this.gateway.button.wrapper);
        enable(this.gateway.messages.wrapper);
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.wrapper) !== null;
    }

    shouldEnable() {
        return this.shouldRender()
            && this.gateway.button.is_disabled !== true;
    }

    render() {
        const actionHandler = new CartActionHandler(
            PayPalCommerceGateway,
            this.errorHandler,
        );

        if(
            PayPalCommerceGateway.data_client_id.has_subscriptions
            && PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled
        ) {
            this.renderer.render(actionHandler.subscriptionsConfiguration());
            return;
        }

        this.renderer.render(
            actionHandler.configuration()
        );
    }
}

export default CartBootstrap;
