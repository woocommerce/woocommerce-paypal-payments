import Spinner from "../../../../ppcp-button/resources/js/modules/Helper/Spinner";
import BaseHandler from "./BaseHandler";
import CheckoutActionHandler
    from "../../../../ppcp-button/resources/js/modules/ActionHandler/CheckoutActionHandler";

class CheckoutHandler extends BaseHandler {

    actionHandler() {
        return new CheckoutActionHandler(
            this.ppcpConfig,
            this.errorHandler(),
            new Spinner()
        );
    }

}

export default CheckoutHandler;
