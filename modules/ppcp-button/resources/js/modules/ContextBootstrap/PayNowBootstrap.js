import CheckoutBootstrap from './CheckoutBootstrap';
import { isChangePaymentPage } from '../Helper/Subscriptions';
import { setVisible } from '../Helper/Hiding';

class PayNowBootstrap extends CheckoutBootstrap {
	constructor( gateway, renderer, spinner, errorHandler ) {
		super( gateway, renderer, spinner, errorHandler );
	}

	updateUi() {
		if ( isChangePaymentPage() ) {
			if ( this.vaultRenderer && this.isSavedPayPalTokenSelected() ) {
				setVisible( '#ppcp-vault-component', true );
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
				setVisible( '#ppcp-vault-component', false );
				this.vaultRenderer.close();
				this.removeVaultOrderIdInput();
			}

			return;
		}

		super.updateUi();
	}
}

export default PayNowBootstrap;
