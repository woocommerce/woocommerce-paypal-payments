import CheckoutBootstrap from './CheckoutBootstrap';
import { isChangePaymentPage } from '../Helper/Subscriptions';

class PayNowBootstrap extends CheckoutBootstrap {
	constructor( gateway, renderer, spinner, errorHandler ) {
		super( gateway, renderer, spinner, errorHandler );
	}

	updateUi() {
		if ( isChangePaymentPage() ) {
			return;
		}

		super.updateUi();
	}
}

export default PayNowBootstrap;
