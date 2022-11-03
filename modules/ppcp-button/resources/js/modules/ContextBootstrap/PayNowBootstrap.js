import CheckoutBootstap from './CheckoutBootstap'
import {isChangePaymentPage} from "../Helper/Subscriptions";

class PayNowBootstrap extends CheckoutBootstap {
    constructor(gateway, renderer, messages, spinner, errorHandler) {
        super(gateway, renderer, messages, spinner, errorHandler)
    }

    updateUi() {
        if (isChangePaymentPage()) {
            return
        }

        super.updateUi();
    }
}

export default PayNowBootstrap;
