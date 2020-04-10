import onApprove from './onApproveForContinue.js';

class CartActionHandler {

    constructor(config, errorHandler) {
        this.config = config;
        this.errorHandler = errorHandler;
    }

    configuration() {
        const createOrder = (data, actions) => {
            return fetch(this.config.ajax.create_order.endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    nonce: this.config.ajax.create_order.nonce,
                    purchase_units: [],
                }),
            }).then(function(res) {
                return res.json();
            }).then(function(data) {
                if (!data.success) {
                    //Todo: Error handling
                    return;
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