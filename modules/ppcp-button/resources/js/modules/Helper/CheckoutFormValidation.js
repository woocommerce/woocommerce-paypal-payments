import Spinner from './Spinner';
import FormValidator from './FormValidator';
import ErrorHandler from '../ErrorHandler';

const validateCheckoutForm = function ( config ) {
	return new Promise( async ( resolve, reject ) => {
		try {
			const spinner = new Spinner();
			const errorHandler = new ErrorHandler(
				config.labels.error.generic,
				document.querySelector( '.woocommerce-notices-wrapper' )
			);

			const formSelector =
				config.context === 'checkout'
					? 'form.checkout'
					: 'form#order_review';
			const formValidator = config.early_checkout_validation_enabled
				? new FormValidator(
						config.ajax.validate_checkout.endpoint,
						config.ajax.validate_checkout.nonce
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
};

export default validateCheckoutForm;
