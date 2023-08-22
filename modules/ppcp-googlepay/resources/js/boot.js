import {loadPaypalScript} from "../../../ppcp-button/resources/js/modules/Helper/ScriptLoading";

(function ({
   buttonConfig,
   ppcpConfig,
   jQuery
}) {

    const bootstrap = function () {

        let allowedPaymentMethods = null;
        let merchantInfo = null;
        let googlePayConfig = null;

        let isReadyToPayRequest = null;
        let baseCardPaymentMethod = null;

        /* Configure your site's support for payment methods supported by the Google Pay */
        function getGoogleIsReadyToPayRequest(allowedPaymentMethods, baseRequest) {
            console.log('allowedPaymentMethods', allowedPaymentMethods);

            return Object.assign({}, baseRequest, {
                allowedPaymentMethods: allowedPaymentMethods,
            });
        }

        /* Fetch Default Config from PayPal via PayPal SDK */
        async function getGooglePayConfig() {
            console.log('getGooglePayConfig');

            console.log('allowedPaymentMethods', allowedPaymentMethods);
            console.log('merchantInfo', merchantInfo);

            if (allowedPaymentMethods == null || merchantInfo == null) {
                googlePayConfig = await paypal.Googlepay().config();

                console.log('const googlePayConfig', googlePayConfig);

                allowedPaymentMethods = googlePayConfig.allowedPaymentMethods;
                merchantInfo = googlePayConfig.merchantInfo;
            }
            return {
                allowedPaymentMethods,
                merchantInfo,
            };
        }

        /**
         * Initialize Google PaymentsClient after Google-hosted JavaScript has loaded
         * Display a Google Pay payment button after confirmation of the viewer's ability to pay.
         */
        function onGooglePayLoaded() {
            console.log('onGooglePayLoaded');

            const paymentsClient = getGooglePaymentsClient();
            paymentsClient.isReadyToPay(isReadyToPayRequest)
                .then(function(response) {
                    if (response.result) {
                        addGooglePayButton();
                    }
                })
                .catch(function(err) {
                    console.error(err);
                });
        }

        /**
         * Add a Google Pay purchase button
         */
        function addGooglePayButton() {
            console.log('addGooglePayButton');

            const paymentsClient = getGooglePaymentsClient();
            const button =
                paymentsClient.createButton({
                    onClick: onGooglePaymentButtonClicked /* To be defined later */,
                    allowedPaymentMethods: [baseCardPaymentMethod]
                });
            jQuery(buttonConfig.button.wrapper).append(button);
        }

        /* Note: the `googlePayConfig` object in this request is the response from `paypal.Googlepay().config()` */
        async function getGooglePaymentDataRequest() {
            let baseRequest = {
                apiVersion: 2,
                apiVersionMinor: 0
            }

            const googlePayConfig = await paypal.Googlepay().config();
            const paymentDataRequest = Object.assign({}, baseRequest);
            paymentDataRequest.allowedPaymentMethods = googlePayConfig.allowedPaymentMethods;
            paymentDataRequest.transactionInfo = getGoogleTransactionInfo();
            paymentDataRequest.merchantInfo = googlePayConfig.merchantInfo;
            paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
            return paymentDataRequest;
        }

        function getGoogleTransactionInfo(){
            return {
                countryCode: 'US',
                currencyCode: 'USD',
                totalPriceStatus: 'FINAL',
                totalPrice: '2.01' // Your amount
            }
        }

        /**
         * Show Google Pay payment sheet when Google Pay payment button is clicked
         */
        async function onGooglePaymentButtonClicked() {
            console.log('onGooglePaymentButtonClicked');

            const paymentDataRequest = await getGooglePaymentDataRequest();
            const paymentsClient = getGooglePaymentsClient();
            paymentsClient.loadPaymentData(paymentDataRequest);
        }

        function onPaymentAuthorized(paymentData) {
            console.log('onPaymentAuthorized', paymentData);

            return new Promise(function (resolve, reject) {
                processPayment(paymentData)
                    .then(function (data) {
                        resolve({ transactionState: "SUCCESS" });
                    })
                    .catch(function (errDetails) {
                        resolve({ transactionState: "ERROR" });
                    });
            });
        }

        function onPaymentDataChanged() {
            console.log('onPaymentDataChanged');
        }

        async function processPayment(paymentData) {
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

        // Custom
        function getGooglePaymentsClient() {
            if (window.googlePayClient) {
                return window.googlePayClient;
            }

            window.googlePayClient = new google.payments.api.PaymentsClient({
                environment: 'TEST', // Use 'PRODUCTION' for real transactions
                // add merchant info maybe
                paymentDataCallbacks: {
                    //onPaymentDataChanged: onPaymentDataChanged,
                    onPaymentAuthorized: onPaymentAuthorized,
                }
            });

            return window.googlePayClient;
        }

        //------------------------

        setTimeout(async function () {
            let cfg = await getGooglePayConfig();
            console.log('googlePayConfig', googlePayConfig);

            allowedPaymentMethods = cfg.allowedPaymentMethods;

            isReadyToPayRequest = getGoogleIsReadyToPayRequest(allowedPaymentMethods, googlePayConfig);
            console.log('googleIsReadyToPayRequest', isReadyToPayRequest);

            baseCardPaymentMethod = allowedPaymentMethods[0];

            onGooglePayLoaded();

        }, 2000);



    };

    document.addEventListener(
        'DOMContentLoaded',
        () => {
            if (
                (typeof (buttonConfig) === 'undefined') ||
                (typeof (ppcpConfig) === 'undefined')
            ) {
                console.error('PayPal button could not be configured.');
                return;
            }

            let bootstrapped = false;

            loadPaypalScript(ppcpConfig, () => {
                bootstrapped = true;
                bootstrap();
            });
        },
    );

})({
    buttonConfig: window.wc_ppcp_googlepay,
    ppcpConfig: window.PayPalCommerceGateway,
    jQuery: window.jQuery
});
