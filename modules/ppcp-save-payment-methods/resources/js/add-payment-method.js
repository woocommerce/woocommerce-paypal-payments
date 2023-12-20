import {
    getCurrentPaymentMethod,
    ORDER_BUTTON_SELECTOR,
    PaymentMethods
} from "../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState";
import {loadScript} from "@paypal/paypal-js";
import {
    setVisible,
    setVisibleByClass
} from "../../../ppcp-button/resources/js/modules/Helper/Hiding";
import ErrorHandler from "../../../ppcp-button/resources/js/modules/ErrorHandler";
import {cardFieldStyles} from "../../../ppcp-button/resources/js/modules/Helper/CardFieldsHelper";

const errorHandler = new ErrorHandler(
    PayPalCommerceGateway.labels.error.generic,
    document.querySelector('.woocommerce-notices-wrapper')
);

const init = () => {
    setVisibleByClass(ORDER_BUTTON_SELECTOR, getCurrentPaymentMethod() !== PaymentMethods.PAYPAL, 'ppcp-hidden');
    setVisible(`#ppc-button-${PaymentMethods.PAYPAL}-save-payment-method`, getCurrentPaymentMethod() === PaymentMethods.PAYPAL);
}

document.addEventListener(
    'DOMContentLoaded',
    () => {
        jQuery(document.body).on('click init_add_payment_method', '.payment_methods input.input-radio', function () {
            init()
        });

        setTimeout(() => {
            loadScript({
                clientId: ppcp_add_payment_method.client_id,
                merchantId: ppcp_add_payment_method.merchant_id,
                dataUserIdToken: ppcp_add_payment_method.id_token,
                components: 'buttons,card-fields',
            })
                .then((paypal) => {
                    errorHandler.clear();

                    paypal.Buttons(
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
                                if (result.data.id) {
                                    return result.data.id
                                }

                                errorHandler.message(ppcp_add_payment_method.error_message);
                            },
                            onApprove: async ({vaultSetupToken}) => {
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

                                const result = await response.json();
                                if(result.success === true) {
                                    window.location.href = ppcp_add_payment_method.payment_methods_page;
                                    return;
                                }

                                errorHandler.message(ppcp_add_payment_method.error_message);
                            },
                            onError: (error) => {
                                console.error(error)
                                errorHandler.message(ppcp_add_payment_method.error_message);
                            }
                        },
                    ).render(`#ppc-button-${PaymentMethods.PAYPAL}-save-payment-method`);

                    const cardField = paypal.CardFields({
                        createVaultSetupToken: async () => {
                            const response = await fetch(ppcp_add_payment_method.ajax.create_setup_token.endpoint, {
                                method: "POST",
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    nonce: ppcp_add_payment_method.ajax.create_setup_token.nonce,
                                    payment_method: PaymentMethods.CARDS,
                                    verification_method: ppcp_add_payment_method.verification_method
                                })
                            })

                            const result = await response.json()
                            if (result.data.id) {
                                return result.data.id
                            }

                            errorHandler.message(ppcp_add_payment_method.error_message);
                        },
                        onApprove: async ({vaultSetupToken}) => {
                            const response = await fetch(ppcp_add_payment_method.ajax.create_payment_token.endpoint, {
                                method: "POST",
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    nonce: ppcp_add_payment_method.ajax.create_payment_token.nonce,
                                    vault_setup_token: vaultSetupToken,
                                    payment_method: PaymentMethods.CARDS
                                })
                            })

                            const result = await response.json();
                            if(result.success === true) {
                                window.location.href = ppcp_add_payment_method.payment_methods_page;
                                return;
                            }

                            errorHandler.message(ppcp_add_payment_method.error_message);
                        },
                        onError: (error) => {
                            console.error(error)
                            errorHandler.message(ppcp_add_payment_method.error_message);
                        }
                    });

                    if (cardField.isEligible()) {
                        const nameField = document.getElementById('ppcp-credit-card-gateway-card-name');
                        if (nameField) {
                            let styles = cardFieldStyles(nameField);
                            cardField.NameField({style: {'input': styles}}).render(nameField.parentNode);
                            nameField.hidden = true;
                        }

                        const numberField = document.getElementById('ppcp-credit-card-gateway-card-number');
                        if (numberField) {
                            let styles = cardFieldStyles(numberField);
                            cardField.NumberField({style: {'input': styles}}).render(numberField.parentNode);
                            numberField.hidden = true;
                        }

                        const expiryField = document.getElementById('ppcp-credit-card-gateway-card-expiry');
                        if (expiryField) {
                            let styles = cardFieldStyles(expiryField);
                            cardField.ExpiryField({style: {'input': styles}}).render(expiryField.parentNode);
                            expiryField.hidden = true;
                        }

                        const cvvField = document.getElementById('ppcp-credit-card-gateway-card-cvc');
                        if (cvvField) {
                            let styles = cardFieldStyles(cvvField);
                            cardField.CVVField({style: {'input': styles}}).render(cvvField.parentNode);
                            cvvField.hidden = true;
                        }
                    }

                    document.querySelector('#place_order').addEventListener("click", (event) => {
                        event.preventDefault();

                        cardField.submit()
                            .catch((error) => {
                                console.error(error)
                            });
                    });
                })
        }, 1000)
    }
);

