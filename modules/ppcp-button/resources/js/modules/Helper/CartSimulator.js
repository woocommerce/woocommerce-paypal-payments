import SingleProductActionHandler from "../ActionHandler/SingleProductActionHandler";
import SimulateCart from "./SimulateCart";
import BootstrapHelper from "./BootstrapHelper";
import {strAddWord, strRemoveWord} from "./Utils";
import merge from "deepmerge";

class CartSimulator {

    constructor(bootstrap) {
        this.bootstrap = bootstrap;
    }

    /**
     * @return
     */
    simulate() {
        const gatewaySettings = this.bootstrap.gateway;

        if (!gatewaySettings.simulate_cart.enabled) {
            return;
        }

        const actionHandler = new SingleProductActionHandler(
            null,
            null,
            this.bootstrap.form(),
            this.bootstrap.errorHandler,
        );

        const hasSubscriptions = PayPalCommerceGateway.data_client_id.has_subscriptions
            && PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled;

        const products = hasSubscriptions
            ? actionHandler.getSubscriptionProducts()
            : actionHandler.getProducts();

        (new SimulateCart(
            gatewaySettings.ajax.simulate_cart.endpoint,
            gatewaySettings.ajax.simulate_cart.nonce,
        )).simulate((data) => {

            // Trigger event with simulated total.
            jQuery(document.body).trigger('ppcp_product_total_updated', [data.total]);

            // Hide and show fields.
            let newData = {};
            if (typeof data.button.is_disabled === 'boolean') {
                newData = merge(newData, {button: {is_disabled: data.button.is_disabled}});
            }
            if (typeof data.messages.is_hidden === 'boolean') {
                newData = merge(newData, {messages: {is_hidden: data.messages.is_hidden}});
            }
            if (newData) {
                BootstrapHelper.updateScriptData(this.bootstrap, newData);
            }

            // Check if single product buttons enabled.
            if ( gatewaySettings.single_product_buttons_enabled !== '1' ) {
                return;
            }

            // Update funding sources.
            let enableFunding = gatewaySettings.url_params['enable-funding'] || '';
            let enableFundingBefore = enableFunding;

            let disableFunding = gatewaySettings.url_params['disable-funding'] || '';
            let disableFundingBefore = disableFunding;

            for (const [fundingSource, funding] of Object.entries(data.funding)) {
                if (funding.enabled === true) {
                    enableFunding = strAddWord(enableFunding, fundingSource);
                    disableFunding = strRemoveWord(disableFunding, fundingSource);
                } else if (funding.enabled === false) {
                    enableFunding = strRemoveWord(enableFunding, fundingSource);
                    disableFunding = strAddWord(disableFunding, fundingSource);
                }
            }

            // Detect and update funding changes and reload buttons.
            if (
                (enableFunding !== enableFundingBefore) ||
                (disableFunding !== disableFundingBefore)
            ) {
                gatewaySettings.url_params['enable-funding'] = enableFunding;
                gatewaySettings.url_params['disable-funding'] = disableFunding;
                jQuery(gatewaySettings.button.wrapper).trigger('ppcp-reload-buttons');
            }

            this.bootstrap.handleButtonStatus(false);

        }, products);
    }
}

export default CartSimulator;
