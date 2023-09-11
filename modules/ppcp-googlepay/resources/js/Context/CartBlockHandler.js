import BaseHandler from "./BaseHandler";

class CartBlockHandler extends BaseHandler {

    createOrder() {
        return this.externalHandler.createOrder();
    }

    approveOrder(data, actions) {
        return this.externalHandler.onApprove(data, actions);
    }

}

export default CartBlockHandler;
