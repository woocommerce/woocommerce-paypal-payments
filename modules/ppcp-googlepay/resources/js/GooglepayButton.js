import ContextHandlerFactory from "./Context/ContextHandlerFactory";
import {setVisible} from '../../../ppcp-button/resources/js/modules/Helper/Hiding';
import {setEnabled} from '../../../ppcp-button/resources/js/modules/Helper/ButtonDisabler';
import widgetBuilder from "../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder";
import UpdatePaymentData from "./Helper/UpdatePaymentData";

class GooglepayButton {

    constructor(context, externalHandler, buttonConfig, ppcpConfig) {
        this.isInitialized = false;

        this.context = context;
        this.externalHandler = externalHandler;
        this.buttonConfig = buttonConfig;
        this.ppcpConfig = ppcpConfig;

        this.paymentsClient = null;

        this.contextHandler = ContextHandlerFactory.create(
            this.context,
            this.buttonConfig,
            this.ppcpConfig,
            this.externalHandler
        );

        this.log = function() {
            if ( this.buttonConfig.is_debug ) {
                console.log('[GooglePayButton]', ...arguments);
            }
        }
    }

    init(config) {
        if (this.isInitialized) {
            return;
        }
        this.isInitialized = true;

        if (!this.validateConfig()) {
            return;
        }

        this.googlePayConfig = config;
        this.allowedPaymentMethods = config.allowedPaymentMethods;
        this.baseCardPaymentMethod = this.allowedPaymentMethods[0];

        this.initClient();
        this.initEventHandlers();

        this.paymentsClient.isReadyToPay(
            this.buildReadyToPayRequest(this.allowedPaymentMethods, config)
        )
            .then((response) => {
                if (response.result) {
                    this.addButton(this.baseCardPaymentMethod);
                }
            })
            .catch(function(err) {
                console.error(err);
            });
    }

    reinit() {
        if (!this.googlePayConfig) {
            return;
        }

        this.isInitialized = false;
        this.init(this.googlePayConfig);
    }

    validateConfig() {
        if ( ['PRODUCTION', 'TEST'].indexOf(this.buttonConfig.environment) === -1) {
            console.error('[GooglePayButton] Invalid environment.', this.buttonConfig.environment);
            return false;
        }

        if ( !this.contextHandler ) {
            console.error('[GooglePayButton] Invalid context handler.', this.contextHandler);
            return false;
        }

        return true;
    }

    /**
     * Returns configurations relative to this button context.
     */
    contextConfig() {
        let config = {
            wrapper: this.buttonConfig.button.wrapper,
            ppcpStyle: this.ppcpConfig.button.style,
            buttonStyle: this.buttonConfig.button.style,
            ppcpButtonWrapper: this.ppcpConfig.button.wrapper
        }

        if (this.context === 'mini-cart') {
            config.wrapper = this.buttonConfig.button.mini_cart_wrapper;
            config.ppcpStyle = this.ppcpConfig.button.mini_cart_style;
            config.buttonStyle = this.buttonConfig.button.mini_cart_style;
            config.ppcpButtonWrapper = this.ppcpConfig.button.mini_cart_wrapper;

            // Handle incompatible types.
            if (config.buttonStyle.type === 'buy') {
                config.buttonStyle.type = 'pay';
            }
        }

        if (['cart-block', 'checkout-block'].indexOf(this.context) !== -1) {
            config.ppcpButtonWrapper = '#express-payment-method-ppcp-gateway';
        }

        return config;
    }

    initClient() {
        const callbacks = {
            onPaymentAuthorized: this.onPaymentAuthorized.bind(this)
        }

        if ( this.buttonConfig.shipping.enabled && this.contextHandler.shippingAllowed() ) {
            callbacks['onPaymentDataChanged'] = this.onPaymentDataChanged.bind(this);
        }

        this.paymentsClient = new google.payments.api.PaymentsClient({
            environment: this.buttonConfig.environment,
            // add merchant info maybe
            paymentDataCallbacks: callbacks
        });
    }

    initEventHandlers() {
        const { wrapper, ppcpButtonWrapper } = this.contextConfig();

        const syncButtonVisibility = () => {
            const $ppcpButtonWrapper = jQuery(ppcpButtonWrapper);
            setVisible(wrapper, $ppcpButtonWrapper.is(':visible'));
            setEnabled(wrapper, !$ppcpButtonWrapper.hasClass('ppcp-disabled'));
        }

        jQuery(document).on('ppcp-shown ppcp-hidden ppcp-enabled ppcp-disabled', (ev, data) => {
            if (jQuery(data.selector).is(ppcpButtonWrapper)) {
                syncButtonVisibility();
            }
        });

        syncButtonVisibility();
    }

    buildReadyToPayRequest(allowedPaymentMethods, baseRequest) {
        return Object.assign({}, baseRequest, {
            allowedPaymentMethods: allowedPaymentMethods,
        });
    }

    /**
     * Add a Google Pay purchase button
     */
    addButton(baseCardPaymentMethod) {
        this.log('addButton', this.context);

        const { wrapper, ppcpStyle, buttonStyle } = this.contextConfig();

        this.waitForWrapper(wrapper, () => {
            jQuery(wrapper).addClass('ppcp-button-' + ppcpStyle.shape);

            const button =
                this.paymentsClient.createButton({
                    onClick: this.onButtonClick.bind(this),
                    allowedPaymentMethods: [baseCardPaymentMethod],
                    buttonColor: buttonStyle.color || 'black',
                    buttonType: buttonStyle.type || 'pay',
                    buttonLocale: buttonStyle.language || 'en',
                    buttonSizeMode: 'fill',
                });

            jQuery(wrapper).append(button);
        });
    }

    waitForWrapper(selector, callback, delay = 100, timeout = 2000) {
        const startTime = Date.now();
        const interval = setInterval(() => {
            const el = document.querySelector(selector);
            const timeElapsed = Date.now() - startTime;

            if (el) {
                clearInterval(interval);
                callback(el);
            } else if (timeElapsed > timeout) {
                clearInterval(interval);
            }
        }, delay);
    }

    //------------------------
    // Button click
    //------------------------

    /**
     * Show Google Pay payment sheet when Google Pay payment button is clicked
     */
    async onButtonClick() {
        this.log('onButtonClick', this.context);

        const paymentDataRequest = await this.paymentDataRequest();
        this.log('onButtonClick: paymentDataRequest', paymentDataRequest, this.context);

        window.ppcpFundingSource = 'googlepay'; // Do this on another place like on create order endpoint handler.

        this.paymentsClient.loadPaymentData(paymentDataRequest);
    }

    async paymentDataRequest() {
        let baseRequest = {
            apiVersion: 2,
            apiVersionMinor: 0
        }

        const googlePayConfig = this.googlePayConfig;
        const paymentDataRequest = Object.assign({}, baseRequest);
        paymentDataRequest.allowedPaymentMethods = googlePayConfig.allowedPaymentMethods;
        paymentDataRequest.transactionInfo = await this.contextHandler.transactionInfo();
        paymentDataRequest.merchantInfo = googlePayConfig.merchantInfo;

        if ( this.buttonConfig.shipping.enabled && this.contextHandler.shippingAllowed() ) {
            paymentDataRequest.callbackIntents = ["SHIPPING_ADDRESS",  "SHIPPING_OPTION", "PAYMENT_AUTHORIZATION"];
            paymentDataRequest.shippingAddressRequired = true;
            paymentDataRequest.shippingAddressParameters = this.shippingAddressParameters();
            paymentDataRequest.shippingOptionRequired = true;
        } else {
            paymentDataRequest.callbackIntents = ['PAYMENT_AUTHORIZATION'];
        }

        return paymentDataRequest;
    }

    //------------------------
    // Shipping processing
    //------------------------

    shippingAddressParameters() {
        return {
            allowedCountryCodes: this.buttonConfig.shipping.countries,
            phoneNumberRequired: true
        };
    }

    onPaymentDataChanged(paymentData) {
        this.log('onPaymentDataChanged', this.context);
        this.log('paymentData', paymentData);

        return new Promise(async (resolve, reject) => {
            let paymentDataRequestUpdate = {};

            const updatedData = await (new UpdatePaymentData(this.buttonConfig.ajax.update_payment_data)).update(paymentData);
            const transactionInfo = await this.contextHandler.transactionInfo();

            this.log('onPaymentDataChanged:updatedData', updatedData);
            this.log('onPaymentDataChanged:transactionInfo', transactionInfo);

            updatedData.country_code = transactionInfo.countryCode;
            updatedData.currency_code = transactionInfo.currencyCode;
            updatedData.total_str = transactionInfo.totalPrice;

            // Handle unserviceable address.
            if(!updatedData.shipping_options || !updatedData.shipping_options.shippingOptions.length) {
                paymentDataRequestUpdate.error = this.unserviceableShippingAddressError();
                resolve(paymentDataRequestUpdate);
                return;
            }

            switch (paymentData.callbackTrigger) {
                case 'INITIALIZE':
                case 'SHIPPING_ADDRESS':
                    paymentDataRequestUpdate.newShippingOptionParameters = updatedData.shipping_options;
                    paymentDataRequestUpdate.newTransactionInfo = this.calculateNewTransactionInfo(updatedData);
                    break;
                case 'SHIPPING_OPTION':
                    paymentDataRequestUpdate.newTransactionInfo = this.calculateNewTransactionInfo(updatedData);
                    break;
            }

            resolve(paymentDataRequestUpdate);
        });
    }

    unserviceableShippingAddressError() {
        return {
            reason: "SHIPPING_ADDRESS_UNSERVICEABLE",
            message: "Cannot ship to the selected address",
            intent: "SHIPPING_ADDRESS"
        };
    }

    calculateNewTransactionInfo(updatedData) {
        return {
            countryCode: updatedData.country_code,
            currencyCode: updatedData.currency_code,
            totalPriceStatus: 'FINAL',
            totalPrice: updatedData.total_str
        };
    }


    //------------------------
    // Payment process
    //------------------------

    onPaymentAuthorized(paymentData) {
        this.log('onPaymentAuthorized', this.context);
        return this.processPayment(paymentData);
    }

    async processPayment(paymentData) {
        this.log('processPayment', this.context);

        return new Promise(async (resolve, reject) => {
            try {
                let id = await this.contextHandler.createOrder();

                this.log('processPayment: createOrder', id, this.context);

                const confirmOrderResponse = await widgetBuilder.paypal.Googlepay().confirmOrder({
                    orderId: id,
                    paymentMethodData: paymentData.paymentMethodData
                });

                this.log('processPayment: confirmOrder', confirmOrderResponse, this.context);

                /** Capture the Order on the Server */
                if (confirmOrderResponse.status === "APPROVED") {

                    let approveFailed = false;
                    await this.contextHandler.approveOrder({
                        orderID: id
                    }, { // actions mock object.
                        restart: () => new Promise((resolve, reject) => {
                            approveFailed = true;
                            resolve();
                        }),
                        order: {
                            get: () => new Promise((resolve, reject) => {
                                resolve(null);
                            })
                        }
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

        this.log('processPaymentResponse', response, this.context);

        return response;
    }

}

export default GooglepayButton;
