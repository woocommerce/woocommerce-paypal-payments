import Spinner from "../../../../ppcp-button/resources/js/modules/Helper/Spinner";
import CheckoutActionHandler
    from "../../../../ppcp-button/resources/js/modules/ActionHandler/CheckoutActionHandler";
import ErrorHandler from "../../../../ppcp-button/resources/js/modules/ErrorHandler";
import onApprove
    from "../../../../ppcp-button/resources/js/modules/OnApproveHandler/onApproveForContinue";

class CheckoutHandler {

    constructor(buttonConfig, ppcpConfig) {
        console.log('NEW CheckoutHandler');

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
                        countryCode: 'US', // data.country_code,
                        currencyCode: 'USD', // data.currency_code,
                        totalPriceStatus: 'FINAL',
                        totalPrice: data.amount // Your amount
                    });

                });
        });
    }

    createOrder() {
        const errorHandler = new ErrorHandler(
            this.ppcpConfig.labels.error.generic,
            document.querySelector('.woocommerce-notices-wrapper')
        );

        const spinner = new Spinner();

        const actionHandler = new CheckoutActionHandler(
            this.ppcpConfig,
            errorHandler,
            spinner
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

export default CheckoutHandler;
