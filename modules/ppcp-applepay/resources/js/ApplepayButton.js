import ContextHandlerFactory from "./Context/ContextHandlerFactory";
import {createAppleErrors} from "./Helper/applePayError";
import {setVisible} from '../../../ppcp-button/resources/js/modules/Helper/Hiding';
import {setEnabled} from '../../../ppcp-button/resources/js/modules/Helper/ButtonDisabler';
import FormValidator from "../../../ppcp-button/resources/js/modules/Helper/FormValidator";
import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';

class ApplepayButton {

    constructor(context, externalHandler, buttonConfig, ppcpConfig) {
        this.isInitialized = false;

        this.context = context;
        this.externalHandler = externalHandler;
        this.buttonConfig = buttonConfig;
        this.ppcpConfig = ppcpConfig;
        this.paymentsClient = null;
        this.form_saved = false;

        this.contextHandler = ContextHandlerFactory.create(
            this.context,
            this.buttonConfig,
            this.ppcpConfig
        );

        //PRODUCT DETAIL PAGE
        if(this.context === 'product') {
            this.productQuantity = document.querySelector('input.qty').value
        }

        this.updated_contact_info = []
        this.selectedShippingMethod = []
        this.nonce = document.getElementById('woocommerce-process-checkout-nonce').value
    }

    init(config) {
        console.log('[ApplePayButton] init', config);
        if (this.isInitialized) {
            return;
        }
        this.initEventHandlers();
        this.isInitialized = true;
        this.applePayConfig = config;
        const isEligible = this.applePayConfig.isEligible;
        if (isEligible) {
            this.fetchTransactionInfo().then(() => {
                const isSubscriptionProduct = this.ppcpConfig.data_client_id.has_subscriptions === true;
                if (isSubscriptionProduct) {
                    return;
                }
                this.addButton();
                const id_minicart = "#apple-" + this.buttonConfig.button.mini_cart_wrapper;
                const id = "#apple-" + this.buttonConfig.button.wrapper;

                if (this.context === 'mini-cart') {
                    document.querySelector(id_minicart).addEventListener('click', (evt) => {
                        evt.preventDefault();
                        this.onButtonClick();
                    });
                } else {
                    document.querySelector(id).addEventListener('click', (evt) => {
                        evt.preventDefault();
                        this.onButtonClick();
                    });
                }

                // Listen for changes on any input within the WooCommerce checkout form
                jQuery('form.checkout').on('change', 'input, select, textarea', () => {
                    this.fetchTransactionInfo();
                });
            });
        }
    }
    async fetchTransactionInfo() {
        this.transactionInfo = await this.contextHandler.transactionInfo();
    }
    /**
     * Returns configurations relative to this button context.
     */
    contextConfig() {
        let config = {
            wrapper: this.buttonConfig.button.wrapper,
            ppcpStyle: this.ppcpConfig.button.style,
            //buttonStyle: this.buttonConfig.button.style,
            ppcpButtonWrapper: this.ppcpConfig.button.wrapper
        }

        if (this.context === 'mini-cart') {
            config.wrapper = this.buttonConfig.button.mini_cart_wrapper;
            config.ppcpStyle = this.ppcpConfig.button.mini_cart_style;
            config.buttonStyle = this.buttonConfig.button.mini_cart_style;
            config.ppcpButtonWrapper = this.ppcpConfig.button.mini_cart_wrapper;
        }

        if (['cart-block', 'checkout-block'].indexOf(this.context) !== -1) {
            config.ppcpButtonWrapper = '#express-payment-method-ppcp-gateway';
        }

        return config;
    }
    initEventHandlers() {
        const { wrapper, ppcpButtonWrapper } = this.contextConfig();
        const wrapper_id = '#' + wrapper;

        const syncButtonVisibility = () => {
            const $ppcpButtonWrapper = jQuery(ppcpButtonWrapper);
            setVisible(wrapper_id, $ppcpButtonWrapper.is(':visible'));
            setEnabled(wrapper_id, !$ppcpButtonWrapper.hasClass('ppcp-disabled'));
        }

        jQuery(document).on('ppcp-shown ppcp-hidden ppcp-enabled ppcp-disabled', (ev, data) => {
            if (jQuery(data.selector).is(ppcpButtonWrapper)) {
                syncButtonVisibility();
            }
        });

        syncButtonVisibility();
    }

    applePaySession(paymentRequest) {
        const session = new ApplePaySession(4, paymentRequest)
        session.begin()

        if (this.buttonConfig.product.needShipping) {
            session.onshippingmethodselected = this.onshippingmethodselected(session)
            session.onshippingcontactselected = this.onshippingcontactselected(session)
        }
        session.onvalidatemerchant = this.onvalidatemerchant(session);
        session.onpaymentauthorized = this.onpaymentauthorized(session);
        return session;
    }




    /**
     * Add a Apple Pay purchase button
     */
    addButton() {
        const wrapper =
            (this.context === 'mini-cart')
                ? this.buttonConfig.button.mini_cart_wrapper
                : this.buttonConfig.button.wrapper;
        const shape =
            (this.context === 'mini-cart')
                ? this.ppcpConfig.button.mini_cart_style.shape
                : this.ppcpConfig.button.style.shape;
        const appleContainer = this.context === 'mini-cart' ? document.getElementById("applepay-container-minicart") : document.getElementById("applepay-container");
        const type = this.buttonConfig.button.type;
        const language = this.buttonConfig.button.lang;
        const color = this.buttonConfig.button.color;
        const id = "apple-" + wrapper;
        appleContainer.innerHTML = `<apple-pay-button id="${id}" buttonstyle="${color}" type="${type}" locale="${language}">`;

        jQuery('#' + wrapper).addClass('ppcp-button-' + shape);
        jQuery(wrapper).append(appleContainer);
    }

    //------------------------
    // Button click
    //------------------------

    /**
     * Show Apple Pay payment sheet when Apple Pay payment button is clicked
     */
    async onButtonClick() {
        const paymentDataRequest = this.paymentDataRequest();
        // trigger woocommerce validation if we are in the checkout page
        if (this.context === 'checkout') {
            const checkoutFormSelector = 'form.woocommerce-checkout';
            const errorHandler = new ErrorHandler(
                PayPalCommerceGateway.labels.error.generic,
                document.querySelector('.woocommerce-notices-wrapper')
            );
            try {
                const formData = new FormData(document.querySelector(checkoutFormSelector));
                this.form_saved = Object.fromEntries(formData.entries());
                this.update_request_data_with_form(paymentDataRequest);
            } catch (error) {
                console.error(error);
            }
            const session = this.applePaySession(paymentDataRequest)
            console.log("session", session)
            const formValidator = PayPalCommerceGateway.early_checkout_validation_enabled ?
                new FormValidator(
                    PayPalCommerceGateway.ajax.validate_checkout.endpoint,
                    PayPalCommerceGateway.ajax.validate_checkout.nonce,
                ) : null;
            if (formValidator) {
                try {
                    const errors = await formValidator.validate(document.querySelector(checkoutFormSelector));
                    if (errors.length > 0) {
                        errorHandler.messages(errors);
                        // fire WC event for other plugins
                        jQuery( document.body ).trigger( 'checkout_error' , [ errorHandler.currentHtml() ] );
                        // stop Apple Pay payment sheet from showing
                        session.abort();
                        return;
                    }
                } catch (error) {
                    console.error(error);
                }
            }
            return;
        }
        this.applePaySession(paymentDataRequest)
    }

    update_request_data_with_form(paymentDataRequest) {
        paymentDataRequest.billingContact = this.fill_billing_contact(this.form_saved);
        paymentDataRequest.applicationData = this.fill_application_data(this.form_saved);
        if (!this.buttonConfig.product.needShipping) {
            return;
        }
        paymentDataRequest.shippingContact = this.fill_shipping_contact(this.form_saved);
    }

    paymentDataRequest() {
        const applepayConfig = this.applePayConfig
        const buttonConfig = this.buttonConfig
        let baseRequest = {
            countryCode: applepayConfig.countryCode,
            merchantCapabilities: applepayConfig.merchantCapabilities,
            supportedNetworks: applepayConfig.supportedNetworks,
            requiredShippingContactFields: ["postalAddress"],
            requiredBillingContactFields: ["postalAddress"]
        }
        const paymentDataRequest = Object.assign({}, baseRequest);
        paymentDataRequest.currencyCode = buttonConfig.shop.currencyCode;
        paymentDataRequest.total = {
            label: buttonConfig.shop.totalLabel,
            type: "final",
            amount: this.transactionInfo.totalPrice,
        }

        return paymentDataRequest
    }


    //------------------------
    // Payment process
    //------------------------

    onvalidatemerchant(session) {
        console.log("onvalidatemerchant")
        return (applePayValidateMerchantEvent) => {
            paypal.Applepay().validateMerchant({
                validationUrl: applePayValidateMerchantEvent.validationURL
            })
                .then(validateResult => {
                    session.completeMerchantValidation(validateResult.merchantSession);
                    //call backend to update validation to true
                    jQuery.ajax({
                        url: this.buttonConfig.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'ppcp_validate',
                            validation: true,
                            'woocommerce-process-checkout-nonce': this.nonce,
                        }
                    })
                    console.log('validated')
                })
                .catch(validateError => {
                    console.error(validateError);
                    //call backend to update validation to false
                    jQuery.ajax({
                        url: this.buttonConfig.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'ppcp_validate',
                            validation: false,
                            'woocommerce-process-checkout-nonce': this.nonce,
                        }
                    })
                    session.abort();
                });
        };
    }
    onshippingmethodselected(session) {
        const ajax_url = this.buttonConfig.ajax_url
        console.log('[ApplePayButton] onshippingmethodselected');
        return (event) => {
            const data = this.getShippingMethodData(event);
            jQuery.ajax({
                url: ajax_url,
                method: 'POST',
                data: data,
                success: (applePayShippingMethodUpdate, textStatus, jqXHR) => {
                    let response = applePayShippingMethodUpdate.data
                    if (applePayShippingMethodUpdate.success === false) {
                        response.errors = createAppleErrors(response.errors)
                    }
                    console.log('shipping method update response', response, applePayShippingMethodUpdate)
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
                    session.completeShippingMethodSelection(response)
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.warn(textStatus, errorThrown)
                    session.abort()
                },
            })
        };
    }
    onshippingcontactselected(session) {
        const ajax_url = this.buttonConfig.ajax_url
        console.log('[ApplePayButton] onshippingcontactselected', ajax_url, session)
        return (event) => {
            const data = this.getShippingContactData(event);
            console.log('shipping contact selected', data, event)
            jQuery.ajax({
                url: ajax_url,
                method: 'POST',
                data: data,
                success: (applePayShippingContactUpdate, textStatus, jqXHR) => {
                    let response = applePayShippingContactUpdate.data
                    this.updated_contact_info = event.shippingContact
                    console.log('shipping contact update response', response, applePayShippingContactUpdate, this.updated_contact_info)
                    if (applePayShippingContactUpdate.success === false) {
                        response.errors = createAppleErrors(response.errors)
                    }
                    if (response.newShippingMethods) {
                        this.selectedShippingMethod = response.newShippingMethods[0]
                    }
                    session.completeShippingContactSelection(response)
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.warn(textStatus, errorThrown)
                    session.abort()
                },
            })
        };
    }
    getShippingContactData(event) {
        const product_id = this.buttonConfig.product.id;

        switch (this.context) {
            case 'product':
                return {
                    action: 'ppcp_update_shipping_contact',
                    product_id: product_id,
                    caller_page: 'productDetail',
                    product_quantity: this.productQuantity,
                    simplified_contact: event.shippingContact,
                    need_shipping: this.buttonConfig.product.needShipping,
                    'woocommerce-process-checkout-nonce': this.nonce,
                };
            case 'cart':
            case 'checkout':
            case 'cart-block':
            case 'checkout-block':
            case 'mini-cart':
                return {
                    action: 'ppcp_update_shipping_contact',
                    simplified_contact: event.shippingContact,
                    caller_page: 'cart',
                    need_shipping: this.buttonConfig.product.needShipping,
                    'woocommerce-process-checkout-nonce': this.nonce,
                };
        }
    }
    getShippingMethodData(event) {
        const product_id = this.buttonConfig.product.id;
        switch (this.context) {
            case 'product': return {
                action: 'ppcp_update_shipping_method',
                shipping_method: event.shippingMethod,
                product_id: product_id,
                caller_page: 'productDetail',
                product_quantity: this.productQuantity,
                simplified_contact: this.updated_contact_info,
                'woocommerce-process-checkout-nonce': this.nonce,
            }
            case 'cart':
            case 'checkout':
            case 'cart-block':
            case 'checkout-block':
            case 'mini-cart':
                return {
                    action: 'ppcp_update_shipping_method',
                    shipping_method: event.shippingMethod,
                    caller_page: 'cart',
                    simplified_contact: this.updated_contact_info,
                    'woocommerce-process-checkout-nonce': this.nonce,
                }
        }
    }

    onpaymentauthorized(session) {
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
                            'product_quantity': this.productQuantity,
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
                paypal.Applepay().confirmOrder(
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

    fill_billing_contact(form_saved) {
        return {
            givenName: form_saved.billing_first_name ?? '',
            familyName: form_saved.billing_last_name ?? '',
            emailAddress: form_saved.billing_email  ?? '',
            phoneNumber: form_saved.billing_phone ?? '',
            addressLines: [form_saved.billing_address_1, form_saved.billing_address_2],
            locality: form_saved.billing_city ?? '',
            postalCode: form_saved.billing_postcode ?? '',
            countryCode: form_saved.billing_country ?? '',
            administrativeArea: form_saved.billing_state ?? '',
        }
    }
    fill_shipping_contact(form_saved) {
        if (form_saved.shipping_first_name === "") {
            return this.fill_billing_contact(form_saved)
        }
        return {
            givenName: (form_saved?.shipping_first_name && form_saved.shipping_first_name !== "") ? form_saved.shipping_first_name : form_saved?.billing_first_name,
            familyName: (form_saved?.shipping_last_name && form_saved.shipping_last_name !== "") ? form_saved.shipping_last_name : form_saved?.billing_last_name,
            emailAddress: (form_saved?.shipping_email && form_saved.shipping_email !== "") ? form_saved.shipping_email : form_saved?.billing_email,
            phoneNumber: (form_saved?.shipping_phone && form_saved.shipping_phone !== "") ? form_saved.shipping_phone : form_saved?.billing_phone,
            addressLines: [form_saved.shipping_address_1 ?? '', form_saved.shipping_address_2 ?? ''],
            locality: (form_saved?.shipping_city && form_saved.shipping_city !== "") ? form_saved.shipping_city : form_saved?.billing_city,
            postalCode: (form_saved?.shipping_postcode && form_saved.shipping_postcode !== "") ? form_saved.shipping_postcode : form_saved?.billing_postcode,
            countryCode: (form_saved?.shipping_country && form_saved.shipping_country !== "") ? form_saved.shipping_country : form_saved?.billing_country,
            administrativeArea: (form_saved?.shipping_state && form_saved.shipping_state !== "") ? form_saved.shipping_state : form_saved?.billing_state,
        }
    }

    fill_application_data(form_saved) {
        const jsonString = JSON.stringify(form_saved);
        let utf8Str = encodeURIComponent(jsonString).replace(/%([0-9A-F]{2})/g, (match, p1) => {
            return String.fromCharCode('0x' + p1);
        });

        return btoa(utf8Str);
    }
}

export default ApplepayButton;
