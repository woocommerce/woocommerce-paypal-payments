import Spinner from '../../../../ppcp-button/resources/js/modules/Helper/Spinner';
import BaseHandler from './BaseHandler';
import CheckoutActionHandler from '../../../../ppcp-button/resources/js/modules/ActionHandler/CheckoutActionHandler';
import TransactionInfo from '../Helper/TransactionInfo';

class PayNowHandler extends BaseHandler {
	validateContext() {
		if ( this.ppcpConfig?.locations_with_subscription_product?.payorder ) {
			return false;
		}
		return true;
	}

	transactionInfo() {
		return new Promise( async ( resolve, reject ) => {
			const data = this.ppcpConfig.pay_now;

			const transaction = new TransactionInfo(
				data.total,
				data.shipping_fee,
				data.currency_code,
				data.country_code
			);

			resolve( transaction );
		} );
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
