import SingleProductActionHandler from '../../../../ppcp-button/resources/js/modules/ActionHandler/SingleProductActionHandler';
import SimulateCart from '../../../../ppcp-button/resources/js/modules/Helper/SimulateCart';
import ErrorHandler from '../../../../ppcp-button/resources/js/modules/ErrorHandler';
import UpdateCart from '../../../../ppcp-button/resources/js/modules/Helper/UpdateCart';
import BaseHandler from './BaseHandler';
import TransactionInfo from '../Helper/TransactionInfo';

class SingleProductHandler extends BaseHandler {
	validateContext() {
		if ( this.ppcpConfig?.locations_with_subscription_product?.product ) {
			return false;
		}
		return true;
	}

	transactionInfo() {
		const errorHandler = new ErrorHandler(
			this.ppcpConfig.labels.error.generic,
			document.querySelector( '.woocommerce-notices-wrapper' )
		);

		function form() {
			return document.querySelector( 'form.cart' );
		}

		const actionHandler = new SingleProductActionHandler(
			null,
			null,
			form(),
			errorHandler
		);

		const hasSubscriptions =
			PayPalCommerceGateway.data_client_id.has_subscriptions &&
			PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled;

		const products = hasSubscriptions
			? actionHandler.getSubscriptionProducts()
			: actionHandler.getProducts();

		return new Promise( ( resolve, reject ) => {
			new SimulateCart(
				this.ppcpConfig.ajax.simulate_cart.endpoint,
				this.ppcpConfig.ajax.simulate_cart.nonce
			).simulate( ( data ) => {
				const transaction = new TransactionInfo(
					data.total,
					data.shipping_fee,
					data.currency_code,
					data.country_code
				);

				resolve( transaction );
			}, products );
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
