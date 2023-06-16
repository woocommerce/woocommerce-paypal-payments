import CartActionHandler from '../ActionHandler/CartActionHandler';
import {setVisible} from "../Helper/Hiding";

class CartBootstrap {
    constructor(gateway, renderer, messages, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.messages = messages;
        this.errorHandler = errorHandler;
        this.lastAmount = this.gateway.messages.amount;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        this.render();

        jQuery(document.body).on('updated_cart_totals updated_checkout', () => {
            this.render();

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

                const newParams = result.data.url_params;
                const reloadRequired = this.gateway.url_params.intent !== newParams.intent;

                // TODO: should reload the script instead
                setVisible(this.gateway.button.wrapper, !reloadRequired)

                if (this.lastAmount !== result.data.amount) {
                    this.lastAmount = result.data.amount;
                    this.messages.renderWithAmount(this.lastAmount);
                }
            });
        });
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.wrapper) !== null;
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

        this.messages.renderWithAmount(this.lastAmount);
    }
}

export default CartBootstrap;
