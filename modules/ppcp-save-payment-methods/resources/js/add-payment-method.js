import {
    getCurrentPaymentMethod,
    ORDER_BUTTON_SELECTOR,
    PaymentMethods
} from "../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState";

import {
    setVisible,
    setVisibleByClass
} from "../../../ppcp-button/resources/js/modules/Helper/Hiding";
import {
    loadPaypalJsScriptPromise
} from "../../../ppcp-button/resources/js/modules/Helper/ScriptLoading";

const init = () => {
    setVisibleByClass(ORDER_BUTTON_SELECTOR, getCurrentPaymentMethod() !== PaymentMethods.PAYPAL, 'ppcp-hidden');
    setVisible(`#ppc-button-${PaymentMethods.PAYPAL}-save-payment-method`, getCurrentPaymentMethod() === PaymentMethods.PAYPAL);

    if(getCurrentPaymentMethod() === PaymentMethods.PAYPAL) {
        loadPaypalJsScriptPromise({
            clientId: ppcp_add_payment_method.client_id,
            merchantId: ppcp_add_payment_method.merchant_id,
            dataUserIdToken: ppcp_add_payment_method.id_token
        })
            .then((paypal) => {
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

                            const result = await response.json()
                            console.log(result)
                        },
                        onError: (error) => {
                            console.error(error)
                        }
                    },
                ).render(`#ppc-button-${PaymentMethods.PAYPAL}-save-payment-method`);
            })
            .catch((error) => {
                console.error(error);
            });
    }

    if(getCurrentPaymentMethod() === PaymentMethods.CARDS) {
        loadPaypalJsScriptPromise({
            clientId: ppcp_add_payment_method.client_id,
            merchantId: ppcp_add_payment_method.merchant_id,
            dataUserIdToken: ppcp_add_payment_method.id_token,
            components: 'card-fields',
        }, true)
            .then((paypal) => {
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
                                payment_method: PaymentMethods.CARDS
                            })
                        })

                        const result = await response.json()
                        if (result.data.id) {
                            return result.data.id
                        }
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

                        const result = await response.json()
                        console.log(result)
                    },
                    onError: (error) => {
                        console.error(error)
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
            .catch((error) => {
                console.error(error)
            })
    }
}

const cardFieldStyles = (field) => {
    const allowedProperties = [
        'appearance',
        'color',
        'direction',
        'font',
        'font-family',
        'font-size',
        'font-size-adjust',
        'font-stretch',
        'font-style',
        'font-variant',
        'font-variant-alternates',
        'font-variant-caps',
        'font-variant-east-asian',
        'font-variant-ligatures',
        'font-variant-numeric',
        'font-weight',
        'letter-spacing',
        'line-height',
        'opacity',
        'outline',
        'padding',
        'padding-bottom',
        'padding-left',
        'padding-right',
        'padding-top',
        'text-shadow',
        'transition',
        '-moz-appearance',
        '-moz-osx-font-smoothing',
        '-moz-tap-highlight-color',
        '-moz-transition',
        '-webkit-appearance',
        '-webkit-osx-font-smoothing',
        '-webkit-tap-highlight-color',
        '-webkit-transition',
    ];

    const stylesRaw = window.getComputedStyle(field);
    const styles = {};
    Object.values(stylesRaw).forEach((prop) => {
        if (!stylesRaw[prop] || !allowedProperties.includes(prop)) {
            return;
        }
        styles[prop] = '' + stylesRaw[prop];
    });

    return styles;
}

document.addEventListener(
    'DOMContentLoaded',
    () => {
        jQuery(document.body).on('click init_add_payment_method', '.payment_methods input.input-radio', function () {
            init()
        });
    }
);

