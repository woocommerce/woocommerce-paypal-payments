import SingleProductActionHandler from '../../../../ppcp-button/resources/js/modules/ActionHandler/SingleProductActionHandler';
import SimulateCart from '../../../../ppcp-button/resources/js/modules/Helper/SimulateCart';
import ErrorHandler from '../../../../ppcp-button/resources/js/modules/ErrorHandler';
import UpdateCart from '../../../../ppcp-button/resources/js/modules/Helper/UpdateCart';
import BaseHandler from './BaseHandler';

class SingleProductHandler extends BaseHandler {
	validateContext() {
		if ( this.ppcpConfig?.locations_with_subscription_product?.product ) {
			return this.isVaultV3Mode();
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
				resolve( {
					countryCode: data.country_code,
					currencyCode: data.currency_code,
					totalPriceStatus: 'FINAL',
					totalPrice: data.total,
				} );
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

	products() {
		return this.actionHandler().getProducts();
	}
}

export default SingleProductHandler;
