import { show } from '../Helper/Hiding';
import ErrorHandler from '../ErrorHandler';
import RenderCardFields from '../../../../../ppcp-save-payment-methods/resources/js/RenderCardFields';
import Configuration from '../../../../../ppcp-save-payment-methods/resources/js/Configuration';

class CardFieldsFreeTrialRenderer {
	constructor( defaultConfig, errorHandler, spinner ) {
		this.defaultConfig = defaultConfig;
		this.errorHandler = errorHandler;
		this.spinner = spinner;
	}

	render( wrapper, contextConfig ) {
		if (
			( this.defaultConfig.context !== 'checkout' &&
				this.defaultConfig.context !== 'pay-now' ) ||
			wrapper === null ||
			document.querySelector( wrapper ) === null
		) {
			return;
		}

		const buttonSelector = wrapper + ' button';

		const gateWayBox = document.querySelector(
			'.payment_box.payment_method_ppcp-credit-card-gateway'
		);
		if ( ! gateWayBox ) {
			return;
		}

		const oldDisplayStyle = gateWayBox.style.display;
		gateWayBox.style.display = 'block';

		const hideDccGateway = document.querySelector( '#ppcp-hide-dcc' );
		if ( hideDccGateway ) {
			hideDccGateway.parentNode.removeChild( hideDccGateway );
		}

		const errorHandler = new ErrorHandler(
			this.defaultConfig.labels.error.generic,
			document.querySelector( '.woocommerce-notices-wrapper' )
		);
		errorHandler.clear();

		const configuration = new Configuration(
			this.defaultConfig,
			errorHandler
		);

		const cardFields = paypal.CardFields(
			configuration.cardFieldsConfiguration()
		);

		if ( cardFields.isEligible() ) {
			const renderCardFields = new RenderCardFields( cardFields );
			renderCardFields.render();
		}

		gateWayBox.style.display = oldDisplayStyle;

		show( buttonSelector );

		if ( this.defaultConfig.cart_contains_subscription ) {
			const saveToAccount = document.querySelector(
				'#wc-ppcp-credit-card-gateway-new-payment-method'
			);
			if ( saveToAccount ) {
				saveToAccount.checked = true;
				saveToAccount.disabled = true;
			}
		}

		document
			.querySelector( buttonSelector )
			?.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				this.spinner.block();
				this.errorHandler.clear();

				cardFields.submit().catch( ( error ) => {
					console.error( error );
				} );
			} );
	}

	disableFields() {}
	enableFields() {}
}

export default CardFieldsFreeTrialRenderer;
