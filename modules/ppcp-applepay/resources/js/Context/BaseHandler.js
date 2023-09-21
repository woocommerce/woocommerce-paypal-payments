import ErrorHandler from "../../../../ppcp-button/resources/js/modules/ErrorHandler";
import CartActionHandler
    from "../../../../ppcp-button/resources/js/modules/ActionHandler/CartActionHandler";
import onApprove
    from "../../../../ppcp-button/resources/js/modules/OnApproveHandler/onApproveForContinue";

class BaseHandler {

    constructor(buttonConfig, ppcpConfig) {
        this.buttonConfig = buttonConfig;
        this.ppcpConfig = ppcpConfig;
    }

    transactionInfo() {
        return new Promise((resolve, reject) => {

            fetch(
                this.ppcpConfig.ajax.cart_script_params.endpoint,
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
                    const data = result.data;

                    resolve({
                        countryCode: data.country_code,
                        currencyCode: data.currency_code,
                        totalPriceStatus: 'FINAL',
                        totalPrice: data.total_str
                    });

                });
        });
    }

    createOrder() {
        const errorHandler = new ErrorHandler(
            this.ppcpConfig.labels.error.generic,
            document.querySelector('.woocommerce-notices-wrapper')
        );

        const actionHandler = new CartActionHandler(
            this.ppcpConfig,
            errorHandler,
        );

        return actionHandler.configuration().createOrder(null, null);
    }

    approveOrderForContinue(data, actions) {
        const errorHandler = new ErrorHandler(
            this.ppcpConfig.labels.error.generic,
            document.querySelector('.woocommerce-notices-wrapper')
        );

        let onApproveHandler = onApprove({
            config: this.ppcpConfig
        }, errorHandler);

        return onApproveHandler(data, actions);
    }

}

export default BaseHandler;
