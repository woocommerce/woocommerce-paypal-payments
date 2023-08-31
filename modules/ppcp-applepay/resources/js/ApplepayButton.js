import ContextHandlerFactory from "./Context/ContextHandlerFactory";

class ApplepayButton {

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
        console.log('[ApplePayButton] init', config);
        if (this.isInitialized) {
            return;
        }
        this.isInitialized = true;

        this.applePayConfig = config;
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

    buildReadyToPayRequest(allowedPaymentMethods, baseRequest) {
        return Object.assign({}, baseRequest, {
            allowedPaymentMethods: allowedPaymentMethods,
        });
    }

    initClient() {
        this.paymentsClient = new apple.payments.api.PaymentsClient({
            environment: 'TEST', // TODO: Use 'PRODUCTION' for real transactions
            // add merchant info maybe
            paymentDataCallbacks: {
                //onPaymentDataChanged: onPaymentDataChanged,
                onPaymentAuthorized: this.onPaymentAuthorized.bind(this),
            }
        });
    }

    /**
     * Add a Apple Pay purchase button
     */
    addButton(baseCardPaymentMethod) {
        console.log('[ApplePayButton] addButton', this.context);

        const wrapper =
            (this.context === 'mini-cart')
                ? this.buttonConfig.button.mini_cart_wrapper
                : this.buttonConfig.button.wrapper;

        const shape =
            (this.context === 'mini-cart')
                ? this.ppcpConfig.button.mini_cart_style.shape
                : this.ppcpConfig.button.style.shape;

        jQuery(wrapper).addClass('ppcp-button-' + shape);

        const button =
            this.paymentsClient.createButton({
                onClick: this.onButtonClick.bind(this),
                allowedPaymentMethods: [baseCardPaymentMethod],
                buttonType: 'pay',
                buttonSizeMode: 'fill',
            });
        jQuery(wrapper).append(button);
    }

    //------------------------
    // Button click
    //------------------------

    /**
     * Show Apple Pay payment sheet when Apple Pay payment button is clicked
     */
    async onButtonClick() {
        console.log('[ApplePayButton] onButtonClick', this.context);

        const paymentDataRequest = await this.paymentDataRequest();
        console.log('[ApplePayButton] onButtonClick: paymentDataRequest', paymentDataRequest, this.context);

        this.paymentsClient.loadPaymentData(paymentDataRequest);
    }

    async paymentDataRequest() {
        let baseRequest = {
            apiVersion: 2,
            apiVersionMinor: 0
        }

        const applePayConfig = this.applePayConfig;
        const paymentDataRequest = Object.assign({}, baseRequest);
        paymentDataRequest.allowedPaymentMethods = applePayConfig.allowedPaymentMethods;
        paymentDataRequest.transactionInfo = await this.contextHandler.transactionInfo();
        paymentDataRequest.merchantInfo = applePayConfig.merchantInfo;
        paymentDataRequest.callbackIntents = ['PAYMENT_AUTHORIZATION'];
        return paymentDataRequest;
    }


    //------------------------
    // Payment process
    //------------------------

    onPaymentAuthorized(paymentData) {
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

        console.log('[ApplePayButton] processPaymentResponse', response, this.context);

        return response;
    }

}

export default ApplepayButton;
