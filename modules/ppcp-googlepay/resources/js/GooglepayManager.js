import SingleProductActionHandler from '../../../ppcp-button/resources/js/modules/ActionHandler/SingleProductActionHandler';
import CartActionHandler from '../../../ppcp-button/resources/js/modules/ActionHandler/CartActionHandler';
import UpdateCart from '../../../ppcp-button/resources/js/modules/Helper/UpdateCart';
import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';
import SimulateCart from "../../../ppcp-button/resources/js/modules/Helper/SimulateCart";
import onApprove from '../../../ppcp-button/resources/js/modules/OnApproveHandler/onApproveForContinue';
import CheckoutActionHandler
    from "../../../ppcp-button/resources/js/modules/ActionHandler/CheckoutActionHandler";
import Spinner from "../../../ppcp-button/resources/js/modules/Helper/Spinner";

class GooglepayManager {

    constructor(buttonConfig, ppcpConfig) {

        this.buttonConfig = buttonConfig;
        this.ppcpConfig = ppcpConfig;

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
                onPaymentAuthorized: this.onPaymentAuthorized.bind(this),
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
                allowedPaymentMethods: [this.baseCardPaymentMethod],
                buttonType: 'pay',
                buttonSizeMode: 'fill',
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
        paymentDataRequest.transactionInfo = await this.transactionInfo();
        paymentDataRequest.merchantInfo = googlePayConfig.merchantInfo;
        paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
        return paymentDataRequest;
    }

    async transactionInfo() {

//-------------
        const errorHandler = new ErrorHandler(
            this.ppcpConfig.labels.error.generic,
            document.querySelector('.woocommerce-notices-wrapper')
        );

        //== cart & checkout page ==

        return new Promise((resolve, reject) => {

            fetch(
                this.ppcpConfig.ajax.cart_script_params.endpoint,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                }
            )
                .then(result => result.json())
                .then(result => {
                    if (! result.success) {
                        return;
                    }

                    // handle script reload
                    const data = result.data;

                    resolve({
                        countryCode: 'US', // data.country_code,
                        currencyCode: 'USD', // data.currency_code,
                        totalPriceStatus: 'FINAL',
                        totalPrice: data.amount // Your amount
                    });

                });
        });


        // == product page ==
        // function form() {
        //     return document.querySelector('form.cart');
        // }
        //
        // const actionHandler = new SingleProductActionHandler(
        //     null,
        //     null,
        //     form(),
        //     errorHandler,
        // );
        //
        // const hasSubscriptions = PayPalCommerceGateway.data_client_id.has_subscriptions
        //     && PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled;
        //
        // const products = hasSubscriptions
        //     ? actionHandler.getSubscriptionProducts()
        //     : actionHandler.getProducts();
        //
        // return new Promise((resolve, reject) => {
        //     (new SimulateCart(
        //         this.ppcpConfig.ajax.simulate_cart.endpoint,
        //         this.ppcpConfig.ajax.simulate_cart.nonce,
        //     )).simulate((data) => {
        //
        //         resolve({
        //             countryCode: data.country_code,
        //             currencyCode: data.currency_code,
        //             totalPriceStatus: 'FINAL',
        //             totalPrice: data.total_str // Your amount
        //         });
        //
        //     }, products);
        // });
//-------------

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

//-------------
                console.log('ppcpConfig:', this.ppcpConfig);

                const errorHandler = new ErrorHandler(
                    this.ppcpConfig.labels.error.generic,
                    document.querySelector('.woocommerce-notices-wrapper')
                );

                // == checkout page ==
                // const spinner = new Spinner();
                //
                // const actionHandler = new CheckoutActionHandler(
                //     this.ppcpConfig,
                //     errorHandler,
                //     spinner
                // );
                //
                // let id = await actionHandler.configuration().createOrder(null, null);

                // == cart page ==
                const actionHandler = new CartActionHandler(
                    this.ppcpConfig,
                    errorHandler,
                );

                let id = await actionHandler.configuration().createOrder(null, null);

                // == product page ==
                // function form() {
                //     return document.querySelector('form.cart');
                // }
                // const actionHandler = new SingleProductActionHandler(
                //     this.ppcpConfig,
                //     new UpdateCart(
                //         this.ppcpConfig.ajax.change_cart.endpoint,
                //         this.ppcpConfig.ajax.change_cart.nonce,
                //     ),
                //     form(),
                //     errorHandler,
                // );
                //
                // const createOrderInPayPal = actionHandler.createOrder();
                //
                // let id = await createOrderInPayPal(null, null);

                console.log('PayPal Order ID:', id);

//-------------

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

                    const approveOrderAndContinue = onApprove({
                        config: this.ppcpConfig
                    }, errorHandler);

                    console.log('approveOrderAndContinue', {
                        orderID: id
                    });

                    await approveOrderAndContinue({
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

                    // if (response.capture.status === "COMPLETED")
                    //     resolve({transactionState: 'SUCCESS'});
                    // else
                    //     resolve({
                    //         transactionState: 'ERROR',
                    //         error: {
                    //             intent: 'PAYMENT_AUTHORIZATION',
                    //             message: 'TRANSACTION FAILED',
                    //         }
                    //     })

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
