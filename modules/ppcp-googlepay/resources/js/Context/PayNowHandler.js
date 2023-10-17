import Spinner from "../../../../ppcp-button/resources/js/modules/Helper/Spinner";
import BaseHandler from "./BaseHandler";
import CheckoutActionHandler
    from "../../../../ppcp-button/resources/js/modules/ActionHandler/CheckoutActionHandler";

class PayNowHandler extends BaseHandler {

    shippingAllowed() {
        return false;
    }

    transactionInfo() {
        return new Promise(async (resolve, reject) => {
            const data = this.ppcpConfig['pay_now'];

            resolve({
                countryCode: data.country_code,
                currencyCode: data.currency_code,
                totalPriceStatus: 'FINAL',
                totalPrice: data.total_str
            });
        });
    }

    actionHandler() {
        return new CheckoutActionHandler(
            this.ppcpConfig,
            this.errorHandler(),
            new Spinner()
        );
    }

}

export default PayNowHandler;
