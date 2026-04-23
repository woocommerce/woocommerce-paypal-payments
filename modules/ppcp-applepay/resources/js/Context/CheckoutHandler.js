import Spinner from '@ppcp-button/Helper/Spinner';
import CheckoutActionHandler from '@ppcp-button/ActionHandler/CheckoutActionHandler';
import BaseHandler from './BaseHandler';

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
