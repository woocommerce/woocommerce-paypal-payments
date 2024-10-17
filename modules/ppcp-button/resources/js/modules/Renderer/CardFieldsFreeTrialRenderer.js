import { show } from '../Helper/Hiding';
import { renderFields } from '../../../../../ppcp-card-fields/resources/js/Render';
import {
	addPaymentMethodConfiguration,
	cardFieldsConfiguration,
} from '../../../../../ppcp-save-payment-methods/resources/js/Configuration';

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
        const dccGatewayLi = document.querySelector(
            '.wc_payment_method.payment_method_ppcp-credit-card-gateway'
        );
        if (dccGatewayLi.style.display === 'none' || dccGatewayLi.style.display === '') {
            dccGatewayLi.style.display = 'block';
        }

		this.errorHandler.clear();

		let cardFields = paypal.CardFields(
			addPaymentMethodConfiguration( this.defaultConfig )
		);
		if ( this.defaultConfig.user.is_logged ) {
			cardFields = paypal.CardFields(
				cardFieldsConfiguration( this.defaultConfig, this.errorHandler )
			);
		}

		if ( cardFields.isEligible() ) {
			renderFields( cardFields );
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
