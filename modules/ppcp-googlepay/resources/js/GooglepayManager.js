
class GooglepayManager {

    constructor(buttonConfig) {

        this.buttonConfig = buttonConfig;

        this.allowedPaymentMethods = null;
        this.merchantInfo = null;
        this.googlePayConfig = null;

        this.isReadyToPayRequest = null;
        this.baseCardPaymentMethod = null;
    }

    init() {
        (async () => {
            let cfg = await this.config();
            console.log('googlePayConfig', this.googlePayConfig);

            this.allowedPaymentMethods = cfg.allowedPaymentMethods;

            this.isReadyToPayRequest = this.buildReadyToPayRequest(this.allowedPaymentMethods, this.googlePayConfig);
            console.log('googleIsReadyToPayRequest', this.isReadyToPayRequest);

            this.baseCardPaymentMethod = this.allowedPaymentMethods[0];

            this.load();
        })();
    }

    async config() {
        console.log('getGooglePayConfig');
        console.log('allowedPaymentMethods', this.allowedPaymentMethods);
        console.log('merchantInfo', this.merchantInfo);

        if (this.allowedPaymentMethods == null || this.merchantInfo == null) {
            this.googlePayConfig = await paypal.Googlepay().config();

            console.log('const googlePayConfig', this.googlePayConfig);

            this.allowedPaymentMethods = this.googlePayConfig.allowedPaymentMethods;
            this.merchantInfo = this.googlePayConfig.merchantInfo;
        }
        return {
            allowedPaymentMethods: this.allowedPaymentMethods,
            merchantInfo: this.merchantInfo,
        };
    }

    buildReadyToPayRequest(allowedPaymentMethods, baseRequest) {
        console.log('allowedPaymentMethods', allowedPaymentMethods);

        return Object.assign({}, baseRequest, {
            allowedPaymentMethods: allowedPaymentMethods,
        });
    }

    /**
     * Initialize Google PaymentsClient after Google-hosted JavaScript has loaded
     * Display a Google Pay payment button after confirmation of the viewer's ability to pay.
     */
    load() {
        console.log('onGooglePayLoaded');

        const paymentsClient = this.client();
        paymentsClient.isReadyToPay(this.isReadyToPayRequest)
            .then((response) => {
                if (response.result) {
                    this.addButton();
                }
            })
            .catch(function(err) {
                console.error(err);
            });
    }

    client() {
        if (window.googlePayClient) {
            return window.googlePayClient;
        }

        window.googlePayClient = new google.payments.api.PaymentsClient({
            environment: 'TEST', // Use 'PRODUCTION' for real transactions
            // add merchant info maybe
            paymentDataCallbacks: {
                //onPaymentDataChanged: onPaymentDataChanged,
                onPaymentAuthorized: this.onPaymentAuthorized,
            }
        });

        return window.googlePayClient;
    }

    /**
     * Add a Google Pay purchase button
     */
    addButton() {
        console.log('addGooglePayButton');

        const paymentsClient = this.client();
        const button =
            paymentsClient.createButton({
                onClick: this.onButtonClick.bind(this),
                allowedPaymentMethods: [this.baseCardPaymentMethod]
            });
        jQuery(this.buttonConfig.button.wrapper).append(button);
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
        const paymentsClient = this.client();
        paymentsClient.loadPaymentData(paymentDataRequest);
    }

    /* Note: the `googlePayConfig` object in this request is the response from `paypal.Googlepay().config()` */
    async paymentDataRequest() {
        let baseRequest = {
            apiVersion: 2,
            apiVersionMinor: 0
        }

        const googlePayConfig = await paypal.Googlepay().config();
        const paymentDataRequest = Object.assign({}, baseRequest);
        paymentDataRequest.allowedPaymentMethods = googlePayConfig.allowedPaymentMethods;
        paymentDataRequest.transactionInfo = this.transactionInfo();
        paymentDataRequest.merchantInfo = googlePayConfig.merchantInfo;
        paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
        return paymentDataRequest;
    }

    transactionInfo() {
        return {
            countryCode: 'US',
            currencyCode: 'USD',
            totalPriceStatus: 'FINAL',
            totalPrice: '2.01' // Your amount
        }
    }

    //------------------------
    // Payment process
    //------------------------

    onPaymentAuthorized(paymentData) {
        console.log('onPaymentAuthorized', paymentData);

        return new Promise((resolve, reject) => {
            this.processPayment(paymentData)
                .then(function (data) {
                    resolve({ transactionState: "SUCCESS" });
                })
                .catch(function (errDetails) {
                    resolve({ transactionState: "ERROR" });
                });
        });
    }

    async processPayment(paymentData) {
        return new Promise(async function (resolve, reject) {
            try {
                // Create the order on your server
                const {id} = await fetch(`/orders`, {
                    method: "POST",
                    body: ''
                    // You can use the "body" parameter to pass optional, additional order information, such as:
                    // amount, and amount breakdown elements like tax, shipping, and handling
                    // item data, such as sku, name, unit_amount, and quantity
                    // shipping information, like name, address, and address type
                });

                console.log('paypal.Googlepay().confirmOrder : paymentData', paymentData);
                const confirmOrderResponse = await paypal.Googlepay().confirmOrder({
                    orderId: id,
                    paymentMethodData: paymentData.paymentMethodData
                });
                console.log('paypal.Googlepay().confirmOrder : confirmOrderResponse', confirmOrderResponse);

                /** Capture the Order on your Server */
                if(confirmOrderResponse.status === "APPROVED"){
                    const response = await fetch(`/capture/${id}`,
                        {
                            method: 'POST',
                        }).then(res => res.json());
                    if(response.capture.status === "COMPLETED")
                        resolve({transactionState: 'SUCCESS'});
                    else
                        resolve({
                            transactionState: 'ERROR',
                            error: {
                                intent: 'PAYMENT_AUTHORIZATION',
                                message: 'TRANSACTION FAILED',
                            }
                        })
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

export default GooglepayManager;
