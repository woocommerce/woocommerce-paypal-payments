import ContextHandlerFactory from "./Context/ContextHandlerFactory";

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
            this.ppcpConfig
        );
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

    buildReadyToPayRequest(allowedPaymentMethods, baseRequest) {
        return Object.assign({}, baseRequest, {
            allowedPaymentMethods: allowedPaymentMethods,
        });
    }

    initClient() {
        this.paymentsClient = new google.payments.api.PaymentsClient({
            environment: this.buttonConfig.environment,
            // add merchant info maybe
            paymentDataCallbacks: {
                //onPaymentDataChanged: onPaymentDataChanged,
                onPaymentAuthorized: this.onPaymentAuthorized.bind(this),
            }
        });
    }

    /**
     * Add a Google Pay purchase button
     */
    addButton(baseCardPaymentMethod) {
        console.log('[GooglePayButton] addButton', this.context);

        const wrapper =
            (this.context === 'mini-cart')
                ? this.buttonConfig.button.mini_cart_wrapper
                : this.buttonConfig.button.wrapper;

        const ppcpStyle =
            (this.context === 'mini-cart')
                ? this.ppcpConfig.button.mini_cart_style
                : this.ppcpConfig.button.style;

        const buttonStyle =
            (this.context === 'mini-cart')
                ? this.buttonConfig.button.mini_cart_style
                : this.buttonConfig.button.style;

        jQuery(wrapper).addClass('ppcp-button-' + ppcpStyle.shape);

        const button =
            this.paymentsClient.createButton({
                onClick: this.onButtonClick.bind(this),
                allowedPaymentMethods: [baseCardPaymentMethod],
                buttonColor: buttonStyle.color || 'black',
                buttonType: buttonStyle.type || 'pay',
                buttonSizeMode: 'fill',
            });
        jQuery(wrapper).append(button);
    }

    //------------------------
    // Button click
    //------------------------

    /**
     * Show Google Pay payment sheet when Google Pay payment button is clicked
     */
    async onButtonClick() {
        console.log('[GooglePayButton] onButtonClick', this.context);

        const paymentDataRequest = await this.paymentDataRequest();
        console.log('[GooglePayButton] onButtonClick: paymentDataRequest', paymentDataRequest, this.context);

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
        paymentDataRequest.callbackIntents = ['PAYMENT_AUTHORIZATION'];
        return paymentDataRequest;
    }


    //------------------------
    // Payment process
    //------------------------

    onPaymentAuthorized(paymentData) {
        console.log('[GooglePayButton] onPaymentAuthorized', this.context);
        return this.processPayment(paymentData);
    }

    async processPayment(paymentData) {
        console.log('[GooglePayButton] processPayment', this.context);

        return new Promise(async (resolve, reject) => {
            try {
                let id = await this.contextHandler.createOrder();

                console.log('[GooglePayButton] processPayment: createOrder', id, this.context);

                const confirmOrderResponse = await paypal.Googlepay().confirmOrder({
                    orderId: id,
                    paymentMethodData: paymentData.paymentMethodData
                });

                console.log('[GooglePayButton] processPayment: confirmOrder', confirmOrderResponse, this.context);

                /** Capture the Order on the Server */
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

        console.log('[GooglePayButton] processPaymentResponse', response, this.context);

        return response;
    }

}

export default GooglepayButton;
