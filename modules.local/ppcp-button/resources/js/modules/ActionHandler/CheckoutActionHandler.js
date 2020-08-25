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


            const errorHandler = this.errorHandler;
            const formValues = jQuery('form.checkout').serialize();

            return fetch(this.config.ajax.create_order.endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    nonce: this.config.ajax.create_order.nonce,
                    payer,
                    bn_code:bnCode,
                    context:this.config.context,
                    form:formValues
                })
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (!data.success) {
                    errorHandler.message(data.data, true);
                    return;
                }
                return data.data.id;
            });
        }
        return {
            createOrder,
            onApprove:onApprove(this, this.errorHandler),
            onError: (error) => {
                this.errorHandler.genericError();
            }
        }
    }
}

export default CheckoutActionHandler;
