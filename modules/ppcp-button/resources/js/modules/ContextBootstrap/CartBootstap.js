import CartActionHandler from '../ActionHandler/CartActionHandler';
import BootstrapHelper from "../Helper/BootstrapHelper";

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
        if (this.shouldRender()) {
            this.render();
            this.handleButtonStatus();
        }

        jQuery(document.body).on('updated_cart_totals updated_checkout', () => {
            if (this.shouldRender()) {
                this.render();
                this.handleButtonStatus();
            }

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

                // handle script reload
                const newParams = result.data.url_params;
                const reloadRequired = JSON.stringify(this.gateway.url_params) !== JSON.stringify(newParams);

                if (reloadRequired) {
                    this.gateway.url_params = newParams;
                    jQuery(this.gateway.button.wrapper).trigger('ppcp-reload-buttons');
                }

                // handle button status
                const newData = {};
                if (result.data.button) {
                    newData.button = result.data.button;
                }
                if (result.data.messages) {
                    newData.messages = result.data.messages;
                }
                if (newData) {
                    BootstrapHelper.updateScriptData(this, newData);
                    this.handleButtonStatus();
                }

                jQuery(document.body).trigger('ppcp_cart_total_updated', [result.data.amount]);
            });
        });
    }

    handleButtonStatus() {
        BootstrapHelper.handleButtonStatus(this);
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.wrapper) !== null;
    }

    shouldEnable() {
        return BootstrapHelper.shouldEnable(this);
    }

    render() {
        if (!this.shouldRender()) {
            return;
        }

        const actionHandler = new CartActionHandler(
            PayPalCommerceGateway,
            this.errorHandler,
        );

        if(
            PayPalCommerceGateway.data_client_id.has_subscriptions
            && PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled
        ) {
            this.renderer.render(actionHandler.subscriptionsConfiguration());

            if(!PayPalCommerceGateway.subscription_product_allowed) {
                this.gateway.button.is_disabled = true;
                this.handleButtonStatus();
            }

            return;
        }

        this.renderer.render(
            actionHandler.configuration()
        );

        jQuery(document.body).trigger('ppcp_cart_rendered');
    }
}

export default CartBootstrap;
