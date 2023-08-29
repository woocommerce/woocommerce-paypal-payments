import ContextHandlerFactory from "./Context/ContextHandlerFactory";

class GooglepayButton {

    constructor(context, handler, buttonConfig, ppcpConfig) {
        this.isInitialized = false;

        this.context = context;
        this.handler = handler;
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

        this.googlePayConfig = config;
        console.log('googlePayConfig', this.googlePayConfig);

        this.allowedPaymentMethods = config.allowedPaymentMethods;

        this.isReadyToPayRequest = this.buildReadyToPayRequest(this.allowedPaymentMethods, config);
        console.log('googleIsReadyToPayRequest', this.isReadyToPayRequest);

        this.baseCardPaymentMethod = this.allowedPaymentMethods[0];

        this.initClient();

        this.paymentsClient.isReadyToPay(this.isReadyToPayRequest)
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
        console.log('allowedPaymentMethods', allowedPaymentMethods);

        return Object.assign({}, baseRequest, {
            allowedPaymentMethods: allowedPaymentMethods,
        });
    }

    initClient() {
        this.paymentsClient = new google.payments.api.PaymentsClient({
            environment: 'TEST', // TODO: Use 'PRODUCTION' for real transactions
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
        console.log('addGooglePayButton');

        const wrapper =
            (this.context === 'mini-cart')
                ? this.buttonConfig.button.mini_cart_wrapper
                : this.buttonConfig.button.wrapper;

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
     * Show Google Pay payment sheet when Google Pay payment button is clicked
     */
    async onButtonClick() {
        console.log('onGooglePaymentButtonClicked');

        const paymentDataRequest = await this.paymentDataRequest();
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
        paymentDataRequest.transactionInfo = await this.transactionInfo();
        paymentDataRequest.merchantInfo = googlePayConfig.merchantInfo;
        paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
        return paymentDataRequest;
    }

    async transactionInfo() {
        return this.contextHandler.transactionInfo();
    }


    //------------------------
    // Payment process
    //------------------------

    onPaymentAuthorized(paymentData) {
        console.log('onPaymentAuthorized', paymentData);

        return new Promise((resolve, reject) => {
            this.processPayment(paymentData)
                .then(function (data) {
                    console.log('resolve: ', data);
                    resolve(data);
                })
                .catch(function (errDetails) {
                    console.log('resolve: ERROR', errDetails);
                    resolve({ transactionState: "ERROR" });
                });
        });
    }

    async processPayment(paymentData) {
        console.log('processPayment');

        return new Promise(async (resolve, reject) => {
            try {
                console.log('ppcpConfig:', this.ppcpConfig);

                let id = await this.contextHandler.createOrder();

                console.log('PayPal Order ID:', id);
                console.log('paypal.Googlepay().confirmOrder : paymentData', {
                    orderId: id,
                    paymentMethodData: paymentData.paymentMethodData
                });

                const confirmOrderResponse = await paypal.Googlepay().confirmOrder({
                    orderId: id,
                    paymentMethodData: paymentData.paymentMethodData
                });
                console.log('paypal.Googlepay().confirmOrder : confirmOrderResponse', confirmOrderResponse);

                /** Capture the Order on your Server */
                if (confirmOrderResponse.status === "APPROVED") {
                    console.log('onApprove', this.ppcpConfig);

                    let approveFailed = false;
                    await this.contextHandler.approveOrderForContinue({
                        orderID: id
                    }, {
                        restart: () => new Promise((resolve, reject) => {
                            approveFailed = true;
                            resolve();
                        })
                    });

                    console.log('approveFailed', approveFailed);

                    if (approveFailed) {
                        resolve({
                            transactionState: 'ERROR',
                            error: {
                                intent: 'PAYMENT_AUTHORIZATION',
                                message: 'FAILED TO APPROVE',
                            }
                        })
                    }

                    resolve({transactionState: 'SUCCESS'});

                } else {
                    resolve({
                        transactionState: 'ERROR',
                        error: {
                            intent: 'PAYMENT_AUTHORIZATION',
                            message: 'TRANSACTION FAILED',
                        }
                    })
                }
            } catch(err) {
                resolve({
                    transactionState: 'ERROR',
                    error: {
                        intent: 'PAYMENT_AUTHORIZATION',
                        message: err.message,
                    }
                })
            }
        });
    }

}

export default GooglepayButton;
