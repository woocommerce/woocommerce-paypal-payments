import onApprove from '../OnApproveHandler/onApproveForContinue.js';
import {payerData} from "../Helper/PayerData";
import {PaymentMethods} from "../Helper/CheckoutMethodState";

class CartActionHandler {

    constructor(config, errorHandler) {
        this.config = config;
        this.errorHandler = errorHandler;
    }

    configuration() {
        const createOrder = (data, actions) => {
            const payer = payerData();
            const bnCode = typeof this.config.bn_codes[this.config.context] !== 'undefined' ?
                this.config.bn_codes[this.config.context] : '';
            return fetch(this.config.ajax.create_order.endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    nonce: this.config.ajax.create_order.nonce,
                    purchase_units: [],
                    payment_method: PaymentMethods.PAYPAL,
                    funding_source: window.ppcpFundingSource,
                    bn_code:bnCode,
                    payer,
                    context:this.config.context
                }),
            }).then(function(res) {
                return res.json();
            }).then(function(data) {
                if (!data.success) {
                    console.error(data);
                    throw Error(data.data.message);
                }
                return data.data.id;
            });
        };

        return {
            createOrder,
            onApprove: onApprove(this, this.errorHandler),
            onError: (error) => {
                this.errorHandler.genericError();
            }
        };
    }
}

export default CartActionHandler;
