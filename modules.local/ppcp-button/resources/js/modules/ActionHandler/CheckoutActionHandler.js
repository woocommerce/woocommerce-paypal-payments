import onApprove from '../OnApproveHandler/onApproveForPayNow.js';
import {payerData} from "../Helper/PayerData";

class CheckoutActionHandler {

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
                    payer,
                    bn_code:bnCode
                })
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (!data.success) {
                    throw Error(data.data);
                }
                return data.data.id;
            });
        }
        return {
            createOrder,
            onApprove:onApprove(this, this.errorHandler),
            onError: (error) => {
                this.errorHandler.message(error);
            }
        }
    }
}

export default CheckoutActionHandler;
