import SingleProductActionHandler from '@ppcp-button/ActionHandler/SingleProductActionHandler';
import SimulateCart, { isSimulateCartEnabled } from '@ppcp-button/Helper/SimulateCart';
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
		// Simulation is this method's only mechanism for fetching product data;
		// reject early to avoid a pointless AJAX call.
		if ( ! isSimulateCartEnabled( this.ppcpConfig ) ) {
			return Promise.reject( new Error( 'Cart simulation is disabled.' ) );
		}

		const form = document.querySelector( 'form.cart' );
		const variationIdInput = form?.querySelector(
			'input[name="variation_id"]'
		);
		if ( variationIdInput && ! parseInt( variationIdInput.value ) ) {
			return Promise.reject( new Error( 'No variation selected.' ) );
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
