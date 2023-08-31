import {createAppleErrors} from './Helper/applePayError.js';
import {maybeShowButton} from './Helper/maybeShowApplePayButton.js';
import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';
import SingleProductActionHandler
    from '../../../ppcp-button/resources/js/modules/ActionHandler/SingleProductActionHandler';
import UpdateCart from '../../../ppcp-button/resources/js/modules/Helper/UpdateCart';
import {loadPaypalScript} from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

(
    function ({wc_ppcp_applepay, jQuery}) {
        document.addEventListener(
            'DOMContentLoaded',
            () => {
                if (PayPalCommerceGateway) {
                    let bootstrapped = false;
                    const {
                        product: {id, needShipping = true, isVariation = false, price, stock},
                        shop: {countryCode, currencyCode = 'EUR', totalLabel = ''},
                        ajaxUrl
                    } = wc_ppcp_applepay

                    if (!id || !price || !countryCode || !ajaxUrl) {
                        return
                    }
                    let outOfStock = stock === 'outofstock'
                    if (outOfStock || !maybeShowButton()) {
                        return;
                    }
                    const nonce = document.getElementById('woocommerce-process-checkout-nonce').value
                    let productId = id
                    let productQuantity = 1
                    let updatedContactInfo = []
                    let selectedShippingMethod = []
                    let redirectionUrl = ''
                    document.querySelector('input.qty').addEventListener('change', event => {
                        productQuantity = event.currentTarget.value
                    })

                    function disableButton(appleButton) {
                        appleButton.disabled = true;
                        appleButton.classList.add("buttonDisabled");
                    }

                    function enableButton(appleButton) {
                        appleButton.disabled = false;
                        appleButton.classList.remove("buttonDisabled");
                    }

                    if (isVariation) {
                        let appleButton = document.querySelector('#applepay-container');
                        jQuery('.single_variation_wrap').on('hide_variation', function (event, variation) {
                            disableButton(appleButton);
                            return;
                        });
                        jQuery('.single_variation_wrap').on('show_variation', function (event, variation) {
                            // Fired when the user selects all the required dropdowns / attributes
                            // and a final variation is selected / shown
                            if (!variation.is_in_stock) {
                                disableButton(appleButton);
                                return;
                            }
                            if (variation.variation_id) {
                                productId = variation.variation_id
                            }
                            enableButton(appleButton);
                        });
                        disableButton(appleButton);
                    }
                    const amountWithoutTax = productQuantity * price


                    loadPaypalScript(PayPalCommerceGateway, () => {
                        bootstrapped = true;
                        const applepay = paypal.Applepay();
                        applepay.config()
                            .then(applepayConfig => {
                                const appleContainer = document.getElementById("applepay-container");
                                if (applepayConfig.isEligible) {
                                    appleContainer.innerHTML = '<apple-pay-button id="btn-appl" buttonstyle="black" type="buy" locale="en">';
                                    const paymentRequest = {
                                        countryCode: applepayConfig.countryCode,
                                        merchantCapabilities: applepayConfig.merchantCapabilities,
                                        supportedNetworks: applepayConfig.supportedNetworks,
                                        currencyCode: currencyCode,
                                        requiredShippingContactFields: ["name", "phone",
                                            "email", "postalAddress"],
                                        requiredBillingContactFields: ["name", "phone", "email",
                                            "postalAddress"],
                                        total: {
                                            label: totalLabel,
                                            type: "final",
                                            amount: amountWithoutTax,
                                        }
                                    }
                                    let applePaySession = () => {
                                        const session = new ApplePaySession(4, paymentRequest)
                                        session.begin()
                                        if (needShipping) {
                                            session.onshippingmethodselected = function (event) {
                                                jQuery.ajax({
                                                    url: ajaxUrl,
                                                    method: 'POST',
                                                    data: {
                                                        action: 'ppcp_update_shipping_method',
                                                        shipping_method: event.shippingMethod,
                                                        product_id: productId,
                                                        caller_page: 'productDetail',
                                                        product_quantity: productQuantity,
                                                        simplified_contact: updatedContactInfo,
                                                        'woocommerce-process-checkout-nonce': nonce,
                                                    },
                                                    complete: (jqXHR, textStatus) => {
                                                    },
                                                    success: (applePayShippingMethodUpdate, textStatus, jqXHR) => {
                                                        let response = applePayShippingMethodUpdate.data
                                                        selectedShippingMethod = event.shippingMethod
                                                        //order the response shipping methods, so that the selected shipping method is the first one
                                                        let orderedShippingMethods = response.newShippingMethods.sort((a, b) => {
                                                            if (a.label === selectedShippingMethod.label) {
                                                                return -1
                                                            }
                                                            return 1
                                                        })
                                                        //update the response.newShippingMethods with the ordered shipping methods
                                                        response.newShippingMethods = orderedShippingMethods
                                                        if (applePayShippingMethodUpdate.success === false) {
                                                            response.errors = createAppleErrors(response.errors)
                                                        }
                                                        this.completeShippingMethodSelection(response)
                                                    },
                                                    error: (jqXHR, textStatus, errorThrown) => {
                                                        console.warn(textStatus, errorThrown)
                                                        session.abort()
                                                    },
                                                })
                                            }
                                            session.onshippingcontactselected = function (event) {
                                                jQuery.ajax({
                                                    url: ajaxUrl,
                                                    method: 'POST',
                                                    data: {
                                                        action: 'ppcp_update_shipping_contact',
                                                        product_id: productId,
                                                        caller_page: 'productDetail',
                                                        product_quantity: productQuantity,
                                                        simplified_contact: event.shippingContact,
                                                        need_shipping: needShipping,
                                                        'woocommerce-process-checkout-nonce': nonce,
                                                    },
                                                    complete: (jqXHR, textStatus) => {
                                                    },
                                                    success: (applePayShippingContactUpdate, textStatus, jqXHR) => {
                                                        let response = applePayShippingContactUpdate.data
                                                        updatedContactInfo = event.shippingContact
                                                        if (applePayShippingContactUpdate.success === false) {
                                                            response.errors = createAppleErrors(response.errors)
                                                        }
                                                        if (response.newShippingMethods) {
                                                            selectedShippingMethod = response.newShippingMethods[0]
                                                        }
                                                        this.completeShippingContactSelection(response)

                                                    },
                                                    error: (jqXHR, textStatus, errorThrown) => {
                                                        console.warn(textStatus, errorThrown)
                                                        session.abort()
                                                    },
                                                })
                                            }
                                        }
                                        session.onvalidatemerchant = (applePayValidateMerchantEvent) => {
                                            applepay.validateMerchant({
                                                validationUrl: applePayValidateMerchantEvent.validationURL
                                            })
                                                .then(validateResult => {
                                                    session.completeMerchantValidation(validateResult.merchantSession);
                                                    //call backend to update validation to true
                                                    console.log('validated')
                                                })
                                                .catch(validateError => {
                                                    console.error(validateError);
                                                    //call backend to update validation to false
                                                    session.abort();
                                                });
                                        };
                                        session.onpaymentauthorized = (event) => {
                                            function form() {
                                                return document.querySelector('form.cart');
                                            }
                                            const errorHandler = new ErrorHandler(
                                                PayPalCommerceGateway.labels.error.generic,
                                                document.querySelector('.woocommerce-notices-wrapper')
                                            );
                                            const actionHandler = new SingleProductActionHandler(
                                                PayPalCommerceGateway,
                                                new UpdateCart(
                                                    PayPalCommerceGateway.ajax.change_cart.endpoint,
                                                    PayPalCommerceGateway.ajax.change_cart.nonce,
                                                ),
                                                form(),
                                                errorHandler,
                                            );

                                            let createOrderInPayPal = actionHandler.createOrder()
                                            const processInWooAndCapture = async (data) => {
                                                try {
                                                    console.log('processInWooAndCapture', data)
                                                    const billingContact = data.billing_contact
                                                    const shippingContact = data.shipping_contact
                                                    jQuery.ajax({
                                                        url: ajaxUrl,
                                                        method: 'POST',
                                                        data: {
                                                            action: 'ppcp_create_order',
                                                            'product_id': productId,
                                                            'product_quantity': productQuantity,
                                                            'shipping_contact': shippingContact,
                                                            'billing_contact': billingContact,
                                                            'token': event.payment.token,
                                                            'shipping_method': selectedShippingMethod,
                                                            'woocommerce-process-checkout-nonce': nonce,
                                                            'funding_source': 'applepay',
                                                            '_wp_http_referer': '/?wc-ajax=update_order_review',
                                                            'paypal_order_id': data.paypal_order_id,
                                                        },
                                                        complete: (jqXHR, textStatus) => {
                                                        },
                                                        success: (authorizationResult, textStatus, jqXHR) => {
                                                            console.log('success authorizationResult', authorizationResult)
                                                            if (authorizationResult.result === "success") {
                                                                redirectionUrl = authorizationResult.redirect;
                                                                //session.completePayment(ApplePaySession.STATUS_SUCCESS)
                                                                window.location.href = redirectionUrl
                                                            } else {
                                                                //session.completePayment(ApplePaySession.STATUS_FAILURE)
                                                            }
                                                        },
                                                        error: (jqXHR, textStatus, errorThrown) => {
                                                            console.log('error authorizationResult', errorThrown)
                                                            session.completePayment(ApplePaySession.STATUS_FAILURE)
                                                            console.warn(textStatus, errorThrown)
                                                            session.abort()
                                                        },
                                                    })
                                                } catch (error) {
                                                    console.log(error)  // handle error
                                                }
                                            }
                                            createOrderInPayPal([], []).then((orderId) => {
                                                console.log('createOrderInPayPal', orderId)
                                                applepay.confirmOrder(
                                                    {
                                                        orderId: orderId,
                                                        token: event.payment.token,
                                                        billingContact: event.payment.billingContact
                                                    }
                                                ).then(
                                                    () => {
                                                        session.completePayment(ApplePaySession.STATUS_SUCCESS);
                                                        let data = {
                                                            billing_contact: event.payment.billingContact,
                                                            shipping_contact: event.payment.shippingContact,
                                                            paypal_order_id: orderId
                                                        }
                                                        processInWooAndCapture(data)
                                                    }
                                                ).catch(err => {
                                                        console.error('Error confirming order with applepay token');
                                                            session.completePayment(ApplePaySession.STATUS_FAILURE);
                                                            console.error(err);
                                                        }
                                                    );
                                            }).catch((error) => {
                                                console.log(error)
                                                session.abort()
                                            })
                                        };
                                    }
                                    document.querySelector('#btn-appl').addEventListener('click', (evt) => {
                                        evt.preventDefault()
                                        applePaySession()
                                    })
                                }
                            })
                            .catch(applepayConfigError => {
                                console.error(applepayConfigError)
                                console.error('Error while fetching Apple Pay configuration.');
                            });
                    });
                }
            })
    }
)(
    {
        buttonConfig: window.wc_ppcp_applepay,
        ppcpConfig: window.PayPalCommerceGateway,
        jQuery: window.jQuery
    }
)


