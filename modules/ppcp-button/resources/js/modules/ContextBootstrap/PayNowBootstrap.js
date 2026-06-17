import CheckoutBootstrap from './CheckoutBootstrap';
import { isChangePaymentPage } from '../Helper/Subscriptions';

class PayNowBootstrap extends CheckoutBootstrap {
	constructor( gateway, renderer, spinner, errorHandler ) {
		super( gateway, renderer, spinner, errorHandler );
	}

	updateUi() {
		if ( isChangePaymentPage() ) {
			if ( this.vaultRenderer && this.isSavedPayPalTokenSelected() ) {
				if ( ! this.vaultRenderer.isRendered() ) {
					this.vaultRenderer.render(
						( orderID ) => {
							this.approvedVaultOrderId = orderID;
							this.injectVaultOrderIdInput( orderID );
						},
						() => {
							this.approvedVaultOrderId = null;
							this.removeVaultOrderIdInput();
						}
					);
				}
			} else if ( this.vaultRenderer ) {
				this.vaultRenderer.close();
				this.removeVaultOrderIdInput();
			}

			return;
		}

		super.updateUi();
	}
}

export default PayNowBootstrap;
