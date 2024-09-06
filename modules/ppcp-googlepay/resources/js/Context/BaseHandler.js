import ErrorHandler from '../../../../ppcp-button/resources/js/modules/ErrorHandler';
import CartActionHandler from '../../../../ppcp-button/resources/js/modules/ActionHandler/CartActionHandler';
import TransactionInfo from '../Helper/TransactionInfo';

class BaseHandler {
	constructor( buttonConfig, ppcpConfig, externalHandler ) {
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.externalHandler = externalHandler;
	}

	validateContext() {
		if ( this.ppcpConfig?.locations_with_subscription_product?.cart ) {
			return false;
		}
		return true;
	}

	shippingAllowed() {
		// Status of the shipping settings in WooCommerce.
		return this.buttonConfig.shipping.configured;
	}

	transactionInfo() {
		return new Promise( ( resolve, reject ) => {
			fetch( this.ppcpConfig.ajax.cart_script_params.endpoint, {
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
					const transaction = new TransactionInfo(
						data.total,
						data.shipping_fee,
						data.currency_code,
						data.country_code
					);

					resolve( transaction );
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
