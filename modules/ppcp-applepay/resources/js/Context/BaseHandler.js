import ErrorHandler from '../../../../ppcp-button/resources/js/modules/ErrorHandler';
import CartActionHandler from '../../../../ppcp-button/resources/js/modules/ActionHandler/CartActionHandler';

class BaseHandler {
	constructor( buttonConfig, ppcpConfig ) {
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
	}

	isVaultV3Mode() {
		return (
			this.ppcpConfig?.save_payment_methods?.id_token && // vault v3
			! this.ppcpConfig?.data_client_id?.paypal_subscriptions_enabled && // not PayPal Subscriptions mode
			this.ppcpConfig?.can_save_vault_token
		); // vault is enabled
	}

	validateContext() {
		if ( this.ppcpConfig?.locations_with_subscription_product?.cart ) {
			return this.isVaultV3Mode();
		}
		return true;
	}

	shippingAllowed() {
		return this.buttonConfig.product.needShipping;
	}

	transactionInfo() {
		return new Promise( ( resolve, reject ) => {
			const endpoint = this.ppcpConfig.ajax.cart_script_params.endpoint;
			const separator = endpoint.indexOf( '?' ) !== -1 ? '&' : '?';

			fetch( endpoint + separator + 'shipping=1', {
				method: 'GET',
				credentials: 'same-origin',
			} )
				.then( ( result ) => result.json() )
				.then( ( result ) => {
					if ( ! result.success ) {
						return;
					}

					// handle script reload
					const data = result.data;

					resolve( {
						countryCode: data.country_code,
						currencyCode: data.currency_code,
						totalPriceStatus: 'FINAL',
						totalPrice: data.total_str,
						chosenShippingMethods:
							data.chosen_shipping_methods || null,
						shippingPackages: data.shipping_packages || null,
					} );
				} );
		} );
	}

	createOrder() {
		return this.actionHandler().configuration().createOrder( null, null );
	}

	approveOrder( data, actions ) {
		return this.actionHandler().configuration().onApprove( data, actions );
	}

	actionHandler() {
		return new CartActionHandler( this.ppcpConfig, this.errorHandler() );
	}

	errorHandler() {
		return new ErrorHandler(
			this.ppcpConfig.labels.error.generic,
			document.querySelector( '.woocommerce-notices-wrapper' )
		);
	}
}

export default BaseHandler;
