import {
    getCurrentPaymentMethod,
    ORDER_BUTTON_SELECTOR,
    PaymentMethods
} from "../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState";

import {setVisible} from "../../../ppcp-button/resources/js/modules/Helper/Hiding";
import {loadPaypalJsScript} from "../../../ppcp-button/resources/js/modules/Helper/ScriptLoading";

loadPaypalJsScript(
    {
        clientId: ppcp_add_payment_method.client_id,
        merchantId: ppcp_add_payment_method.merchant_id,
        dataUserIdToken: ppcp_add_payment_method.id_token,
    },
    {
        createVaultSetupToken: async () => {
            const response = await fetch(ppcp_add_payment_method.ajax.create_setup_token.endpoint, {
                method: "POST",
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    nonce: ppcp_add_payment_method.ajax.create_setup_token.nonce,
                })
            })

            const result = await response.json()

            if(result.data.id) {
                return result.data.id
            }
        },
        onApprove: async ({ vaultSetupToken }) => {
            console.log(vaultSetupToken)

            const response = await fetch(ppcp_add_payment_method.ajax.create_payment_token.endpoint, {
                method: "POST",
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    nonce: ppcp_add_payment_method.ajax.create_payment_token.nonce,
                    vault_setup_token: vaultSetupToken,
                })
            })

            const result = await response.json()
            console.log(result)
        },
        onError: (error) => {
            console.error(error)
        }
    },
    `#ppc-button-${PaymentMethods.PAYPAL}-save-payment-method`
);



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
    }
);

