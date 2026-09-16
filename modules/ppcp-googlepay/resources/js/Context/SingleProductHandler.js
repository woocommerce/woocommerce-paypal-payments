import SingleProductActionHandler from '@ppcp-button/ActionHandler/SingleProductActionHandler';
import SimulateCart, {
	isSimulateCartEnabled,
} from '@ppcp-button/Helper/SimulateCart';
import ErrorHandler from '@ppcp-button/ErrorHandler';
import UpdateCart from '@ppcp-button/Helper/UpdateCart';
import BaseHandler from './BaseHandler';
import TransactionInfo from '@ppcp-googlepay/Helper/TransactionInfo';

class SingleProductHandler extends BaseHandler {
	validateContext() {
		if ( this.ppcpConfig?.locations_with_subscription_product?.product ) {
			return false;
		}
		return true;
	}

	transactionInfo() {
		const form = document.querySelector( 'form.cart' );
		const variationIdInput = form?.querySelector(
			'input[name="variation_id"]'
		);
		if ( variationIdInput && ! parseInt( variationIdInput.value ) ) {
			/*
			 * No variation chosen, so there is no product total to price yet.
			 * Rejecting here left the manager without transaction info and it
			 * skipped the button entirely, so a variable product with no default
			 * attributes never offered Google Pay at all - not even once the
			 * shopper picked one, since nothing re-runs the init on that event.
			 *
			 * Fall back to the cart-based figures purely so the button renders.
			 * ProductButtonGate keeps it disabled until a variation exists, and
			 * onButtonClick() re-reads this method before opening the sheet, so
			 * this total is never the one presented to the shopper.
			 */
			return super.transactionInfo();
		}

		// Simulation is this method's only mechanism for fetching product data;
		// reject early to avoid a pointless AJAX call.
		if ( ! isSimulateCartEnabled( this.ppcpConfig ) ) {
			return Promise.reject(
				new Error( 'Cart simulation is disabled.' )
			);
		}

		const errorHandler = new ErrorHandler(
			this.ppcpConfig.labels.error.generic,
			document.querySelector( '.woocommerce-notices-wrapper' )
		);

		const actionHandler = new SingleProductActionHandler(
			null,
			null,
			form,
			errorHandler
		);

		const hasSubscriptions =
			PayPalCommerceGateway.data_client_id.has_subscriptions &&
			PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled;

		const products = hasSubscriptions
			? actionHandler.getSubscriptionProducts()
			: actionHandler.getProducts();

		return new SimulateCart(
			this.ppcpConfig.ajax.simulate_cart.endpoint,
			this.ppcpConfig.ajax.simulate_cart.nonce
		).simulate( ( data ) => {
			return new TransactionInfo(
				data.total,
				data.shipping_fee,
				data.currency_code,
				data.country_code
			);
		}, products );
	}

	validateForm() {
		return this.actionHandler().updateCart( {
			keepShipping: true,
		} );
	}

	createOrder() {
		return this.actionHandler()
			.configuration()
			.createOrder( null, null, {
				updateCartOptions: {
					keepShipping: true,
				},
			} );
	}

	actionHandler() {
		return new SingleProductActionHandler(
			this.ppcpConfig,
			new UpdateCart(
				this.ppcpConfig.ajax.change_cart.endpoint,
				this.ppcpConfig.ajax.change_cart.nonce
			),
			document.querySelector( 'form.cart' ),
			this.errorHandler()
		);
	}
}

export default SingleProductHandler;
