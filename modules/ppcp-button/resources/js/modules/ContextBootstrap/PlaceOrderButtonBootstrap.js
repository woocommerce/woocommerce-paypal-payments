import {
    getCurrentPaymentMethod,
    ORDER_BUTTON_SELECTOR,
    PaymentMethods
} from "../Helper/CheckoutMethodState";

class PlaceOrderButtonBootstrap {
    constructor(config) {
        this.config = config;
        this.defaultButtonText = null;
    }

    init() {
        jQuery(document.body).on('updated_checkout payment_method_selected', () => {
            this.updateUi();
        });

        this.updateUi();
    }

    updateUi() {
        const button = document.querySelector(ORDER_BUTTON_SELECTOR);
        if (!button) {
            return;
        }

        if (!this.defaultButtonText) {
            this.defaultButtonText = button.innerText;

            if (!this.defaultButtonText) {
                return;
            }
        }

        const currentPaymentMethod = getCurrentPaymentMethod();

        if ([PaymentMethods.PAYPAL, PaymentMethods.CARD_BUTTON].includes(currentPaymentMethod)) {
            button.innerText = this.config.buttonText;
        } else {
            button.innerText = this.defaultButtonText;
        }
    }
}

export default PlaceOrderButtonBootstrap
