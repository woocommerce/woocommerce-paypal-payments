import BaseHandler from "./BaseHandler";

class CheckoutBlockHandler extends BaseHandler{

    shippingAllowed() {
        return false;
    }

    createOrder() {
        return this.externalHandler.createOrder();
    }

    approveOrder(data, actions) {
        return this.externalHandler.onApprove(data, actions);
    }

}

export default CheckoutBlockHandler;
