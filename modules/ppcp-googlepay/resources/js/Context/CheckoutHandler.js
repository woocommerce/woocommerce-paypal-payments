import Spinner from '../../../../ppcp-button/resources/js/modules/Helper/Spinner';
import BaseHandler from './BaseHandler';
import CheckoutActionHandler from '../../../../ppcp-button/resources/js/modules/ActionHandler/CheckoutActionHandler';
import FormValidator from '../../../../ppcp-button/resources/js/modules/Helper/FormValidator';

class CheckoutHandler extends BaseHandler {
	validateForm() {
		return new Promise( async ( resolve, reject ) => {
			try {
				const spinner = new Spinner();
				const errorHandler = this.errorHandler();

				const formSelector =
					this.ppcpConfig.context === 'checkout'
						? 'form.checkout'
						: 'form#order_review';
				const formValidator = this.ppcpConfig
					.early_checkout_validation_enabled
					? new FormValidator(
							this.ppcpConfig.ajax.validate_checkout.endpoint,
							this.ppcpConfig.ajax.validate_checkout.nonce
					  )
					: null;

				if ( ! formValidator ) {
					resolve();
					return;
				}

				formValidator
					.validate( document.querySelector( formSelector ) )
					.then( ( errors ) => {
						if ( errors.length > 0 ) {
							spinner.unblock();
							errorHandler.clear();
							errorHandler.messages( errors );

							// fire WC event for other plugins
							jQuery( document.body ).trigger( 'checkout_error', [
								errorHandler.currentHtml(),
							] );

							reject();
						} else {
							resolve();
						}
					} );
			} catch ( error ) {
				console.error( error );
				reject();
			}
		} );
	}

	actionHandler() {
		return new CheckoutActionHandler(
			this.ppcpConfig,
			this.errorHandler(),
			new Spinner()
		);
	}
}

export default CheckoutHandler;
