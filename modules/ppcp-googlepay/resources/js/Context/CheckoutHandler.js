import Spinner from "../../../../ppcp-button/resources/js/modules/Helper/Spinner";
import CheckoutActionHandler
    from "../../../../ppcp-button/resources/js/modules/ActionHandler/CheckoutActionHandler";
import ErrorHandler from "../../../../ppcp-button/resources/js/modules/ErrorHandler";
import BaseHandler from "./BaseHandler";

class CheckoutHandler extends BaseHandler {

    createOrder() {
        const errorHandler = new ErrorHandler(
            this.ppcpConfig.labels.error.generic,
            document.querySelector('.woocommerce-notices-wrapper')
        );

        const spinner = new Spinner();

        const actionHandler = new CheckoutActionHandler(
            this.ppcpConfig,
            errorHandler,
            spinner
        );

        return actionHandler.configuration().createOrder(null, null);
    }

}

export default CheckoutHandler;
