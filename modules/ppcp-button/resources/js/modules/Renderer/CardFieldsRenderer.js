import { show } from '../Helper/Hiding';
import { renderFields } from '../../../../../ppcp-card-fields/resources/js/Render';

class CardFieldsRenderer {
	constructor(
		defaultConfig,
		errorHandler,
		spinner,
		onCardFieldsBeforeSubmit
	) {
		this.defaultConfig = defaultConfig;
		this.errorHandler = errorHandler;
		this.spinner = spinner;
		this.cardValid = false;
		this.formValid = false;
		this.emptyFields = new Set( [ 'number', 'cvv', 'expirationDate' ] );
		this.currentHostedFieldsInstance = null;
		this.onCardFieldsBeforeSubmit = onCardFieldsBeforeSubmit;
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

		const cardFields = paypal.CardFields( {
			createOrder: contextConfig.createOrder,
			onApprove( data ) {
				return contextConfig.onApprove( data );
			},
			onError( error ) {
				console.error( error );
				this.spinner.unblock();
			},
		} );

		if ( cardFields.isEligible() ) {
			renderFields( cardFields );
			document.dispatchEvent( new CustomEvent( 'hosted_fields_loaded' ) );
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
			.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				this.spinner.block();
				this.errorHandler.clear();

				const paymentToken = document.querySelector(
					'input[name="wc-ppcp-credit-card-gateway-payment-token"]:checked'
				)?.value;
				if ( paymentToken && paymentToken !== 'new' ) {
					document.querySelector( '#place_order' ).click();
					return;
				}

				if (
					typeof this.onCardFieldsBeforeSubmit === 'function' &&
					! this.onCardFieldsBeforeSubmit()
				) {
					this.spinner.unblock();
					return;
				}

				cardFields.submit().catch( ( error ) => {
					this.spinner.unblock();
					console.error( error );
					this.errorHandler.message(
						this.defaultConfig.hosted_fields.labels.fields_not_valid
					);
				} );
			} );
	}

	disableFields() {}
	enableFields() {}
}

export default CardFieldsRenderer;
