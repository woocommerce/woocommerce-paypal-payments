import {
    getCurrentPaymentMethod,
    ORDER_BUTTON_SELECTOR,
    PaymentMethods
} from "../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState";

import {setVisible} from "../../../ppcp-button/resources/js/modules/Helper/Hiding";
import {loadPaypalJsScript} from "../../../ppcp-button/resources/js/modules/Helper/ScriptLoading";

const init = () => {
    setVisible(ORDER_BUTTON_SELECTOR, getCurrentPaymentMethod() !== PaymentMethods.PAYPAL);
    setVisible(`#ppc-button-${PaymentMethods.PAYPAL}-save-payment-method`, getCurrentPaymentMethod() === PaymentMethods.PAYPAL);
}

document.addEventListener(
    'DOMContentLoaded',
    () => {
        jQuery(document.body).on('click init_add_payment_method', '.payment_methods input.input-radio', function () {
            init()
        });

        loadPaypalJsScript(
            {},
            {},
            `#ppc-button-${PaymentMethods.PAYPAL}-save-payment-method`
        );
    }
);

