import ErrorHandler from "../../../../ppcp-button/resources/js/modules/ErrorHandler";
import CartActionHandler
    from "../../../../ppcp-button/resources/js/modules/ActionHandler/CartActionHandler";

class BaseHandler {

    constructor(buttonConfig, ppcpConfig, externalHandler) {
        this.buttonConfig = buttonConfig;
        this.ppcpConfig = ppcpConfig;
        this.externalHandler = externalHandler;
    }

    shippingAllowed() {
        return true;
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
        return this.actionHandler().configuration().createOrder(null, null);
    }

    approveOrder(data, actions) {
        return this.actionHandler().configuration().onApprove(data, actions);
    }

    actionHandler() {
        return new CartActionHandler(
            this.ppcpConfig,
            this.errorHandler(),
        );
    }

    errorHandler() {
        return new ErrorHandler(
            this.ppcpConfig.labels.error.generic,
            document.querySelector('.woocommerce-notices-wrapper')
        );
    }

}

export default BaseHandler;
