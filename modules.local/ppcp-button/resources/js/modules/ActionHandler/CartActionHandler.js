import onApprove from '../OnApproveHandler/onApproveForContinue.js';
import {payerData} from "../Helper/PayerData";

class CartActionHandler {

    constructor(config, errorHandler) {
        this.config = config;
        this.errorHandler = errorHandler;
    }

    configuration() {
        const createOrder = (data, actions) => {
            const payer = payerData();
            return fetch(this.config.ajax.create_order.endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    nonce: this.config.ajax.create_order.nonce,
                    purchase_units: [],
                    payer
                }),
            }).then(function(res) {
                return res.json();
            }).then(function(data) {
                if (!data.success) {
                    throw Error(data.data);
                }
                return data.data.id;
            });
        };

        return {
            createOrder,
            onApprove: onApprove(this),
            onError: (error) => {
                this.errorHandler.message(error);
            }
        };
    }
}

export default CartActionHandler;
