import ContextHandlerFactory from "./Context/ContextHandlerFactory";
import {createAppleErrors} from "./Helper/applePayError";

class ApplepayButton {

    constructor(context, externalHandler, buttonConfig, ppcpConfig) {
        this.isInitialized = false;

        this.context = context;
        this.externalHandler = externalHandler;
        this.buttonConfig = buttonConfig;
        this.ppcpConfig = ppcpConfig;
console.log(buttonConfig)
        console.log(ppcpConfig)
        this.paymentsClient = null;

        this.contextHandler = ContextHandlerFactory.create(
            this.context,
            this.buttonConfig,
            this.ppcpConfig
        );

        //PRODUCT DETAIL PAGE
        if(this.context === 'product') {
            this.productQuantity = document.querySelector('input.qty').value
        }

        this.updatedContactInfo = []
        this.selectedShippingMethod = []
        this.nonce = document.getElementById('woocommerce-process-checkout-nonce').value
    }

    init(config) {
        console.log('[ApplePayButton] init', config);
        if (this.isInitialized) {
            return;
        }
        this.isInitialized = true;
        this.applePayConfig = config;
        const isEligible = this.applePayConfig.isEligible;
        if (isEligible) {
            this.addButton();
            document.querySelector('#btn-appl').addEventListener('click', (evt) => {
                evt.preventDefault()
                this.onButtonClick()
            })
        }
    }

    buildReadyToPayRequest(allowedPaymentMethods, baseRequest) {
        return Object.assign({}, baseRequest, {
            allowedPaymentMethods: allowedPaymentMethods,
        });
    }
    applePaySession(paymentRequest) {
        const session = new ApplePaySession(4, paymentRequest)
        session.begin()
        const ajaxUrl = this.buttonConfig.ajax_url
        const productId = this.buttonConfig.product.id
        if (this.buttonConfig.product.needShipping) {
            session.onshippingmethodselected = this.onshippingmethodselected(ajaxUrl, productId, session)
            session.onshippingcontactselected = this.onshippingcontactselected(ajaxUrl, productId, session)
        }
        session.onvalidatemerchant = this.onvalidatemerchant(session);
        session.onpaymentauthorized = this.onpaymentauthorized(ajaxUrl, productId, session);
    }




    /**
     * Add a Apple Pay purchase button
     */
    addButton() {
        const appleContainer = document.getElementById("applepay-container");
        appleContainer.innerHTML = '<apple-pay-button id="btn-appl" type="buy" locale="en">';

        const wrapper =
            (this.context === 'mini-cart')
                ? this.buttonConfig.button.mini_cart_wrapper
                : this.buttonConfig.button.wrapper;

        const shape =
            (this.context === 'mini-cart')
                ? this.ppcpConfig.button.mini_cart_style.shape
                : this.ppcpConfig.button.style.shape;

        jQuery(wrapper).addClass('ppcp-button-' + shape);
        jQuery(wrapper).append(appleContainer);
    }

    //------------------------
    // Button click
    //------------------------

    /**
     * Show Apple Pay payment sheet when Apple Pay payment button is clicked
     */
    onButtonClick() {
        const paymentDataRequest = this.paymentDataRequest();
        console.log('[ApplePayButton] onButtonClick: paymentDataRequest', paymentDataRequest, this.context);

        this.applePaySession(paymentDataRequest)
    }

    paymentDataRequest() {
        const applepayConfig = this.applePayConfig
        const buttonConfig = this.buttonConfig

        document.querySelector('input.qty').addEventListener('change', event => {
            this.productQuantity = event.currentTarget.value
        })
        this.productQuantity = parseInt(productQuantity)
        const amountWithoutTax = productQuantity * buttonConfig.product.price
        return {
            countryCode: applepayConfig.countryCode,
            merchantCapabilities: applepayConfig.merchantCapabilities,
            supportedNetworks: applepayConfig.supportedNetworks,
            currencyCode: buttonConfig.shop.currencyCode,
            requiredShippingContactFields: ["name", "phone",
                "email", "postalAddress"],
            requiredBillingContactFields: ["name", "phone", "email",
                "postalAddress"],
            total: {
                label: buttonConfig.shop.totalLabel,
                type: "final",
                amount: amountWithoutTax,
            }
        }
    }


    //------------------------
    // Payment process
    //------------------------

    onvalidatemerchant(session) {
        return (applePayValidateMerchantEvent) => {
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
    }
    onshippingmethodselected(ajaxUrl, productId, session) {
        return function (event) {
            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'ppcp_update_shipping_method',
                    shipping_method: event.shippingMethod,
                    product_id: productId,
                    caller_page: 'productDetail',
                    product_quantity: this.productQuantity,
                    simplified_contact: this.updatedContactInfo,
                    'woocommerce-process-checkout-nonce': this.nonce,
                },
                success: (applePayShippingMethodUpdate, textStatus, jqXHR) => {
                    let response = applePayShippingMethodUpdate.data
                    this.selectedShippingMethod = event.shippingMethod
                    //order the response shipping methods, so that the selected shipping method is the first one
                    let orderedShippingMethods = response.newShippingMethods.sort((a, b) => {
                        if (a.label === this.selectedShippingMethod.label) {
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
        };
    }
    onshippingcontactselected(ajaxUrl, productId, session) {
        return function (event) {
            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'ppcp_update_shipping_contact',
                    product_id: productId,
                    caller_page: 'productDetail',
                    product_quantity: this.productQuantity,
                    simplified_contact: event.shippingContact,
                    need_shipping: this.needShipping,
                    'woocommerce-process-checkout-nonce': this.nonce,
                },
                success: (applePayShippingContactUpdate, textStatus, jqXHR) => {
                    let response = applePayShippingContactUpdate.data
                    this.updatedContactInfo = event.shippingContact
                    if (applePayShippingContactUpdate.success === false) {
                        response.errors = createAppleErrors(response.errors)
                    }
                    if (response.newShippingMethods) {
                        this.selectedShippingMethod = response.newShippingMethods[0]
                    }
                    this.completeShippingContactSelection(response)

                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.warn(textStatus, errorThrown)
                    session.abort()
                },
            })
        };
    }

    onpaymentauthorized(ajaxUrl, productId, session) {
        /*return (event) => {
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
        };*/
    }
    /* onPaymentAuthorized(paymentData) {
         console.log('[ApplePayButton] onPaymentAuthorized', this.context);
         return this.processPayment(paymentData);
     }

     async processPayment(paymentData) {
         console.log('[ApplePayButton] processPayment', this.context);

         return new Promise(async (resolve, reject) => {
             try {
                 let id = await this.contextHandler.createOrder();

                 console.log('[ApplePayButton] processPayment: createOrder', id, this.context);

                 const confirmOrderResponse = await paypal.Applepay().confirmOrder({
                     orderId: id,
                     paymentMethodData: paymentData.paymentMethodData
                 });

                 console.log('[ApplePayButton] processPayment: confirmOrder', confirmOrderResponse, this.context);

                 /!** Capture the Order on the Server *!/
                 if (confirmOrderResponse.status === "APPROVED") {

                     let approveFailed = false;
                     await this.contextHandler.approveOrderForContinue({
                         orderID: id
                     }, {
                         restart: () => new Promise((resolve, reject) => {
                             approveFailed = true;
                             resolve();
                         })
                     });

                     if (!approveFailed) {
                         resolve(this.processPaymentResponse('SUCCESS'));
                     } else {
                         resolve(this.processPaymentResponse('ERROR', 'PAYMENT_AUTHORIZATION', 'FAILED TO APPROVE'));
                     }

                 } else {
                     resolve(this.processPaymentResponse('ERROR', 'PAYMENT_AUTHORIZATION', 'TRANSACTION FAILED'));
                 }
             } catch(err) {
                 resolve(this.processPaymentResponse('ERROR', 'PAYMENT_AUTHORIZATION', err.message));
             }
         });
     }

     processPaymentResponse(state, intent = null, message = null) {
         let response = {
             transactionState: state,
         }

         if (intent || message) {
             response.error = {
                 intent: intent,
                 message: message,
             }
         }

         console.log('[ApplePayButton] processPaymentResponse', response, this.context);

         return response;
     }*/

}

export default ApplepayButton;
