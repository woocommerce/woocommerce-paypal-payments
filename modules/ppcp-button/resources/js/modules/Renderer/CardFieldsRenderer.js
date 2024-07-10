import { show } from '../Helper/Hiding';
import { cardFieldStyles } from '../Helper/CardFieldsHelper';

/**
 * @typedef {'NameField'|'NumberField'|'ExpiryField'|'CVVField'} FieldName
 */

/**
 * @typedef {Object} FieldInfo
 * @property {FieldName}   name    - The field name, a valid property of the CardField instance.
 * @property {HTMLElement} wrapper - The field's wrapper element (parent of `el`).
 * @property {HTMLElement} el      - The current input field, which is replaced by `insertField()`.
 * @property {Object}      options - Rendering options passed to the CardField instance.
 */

/**
 * @typedef {Object} CardFields
 * @property {() => boolean}       isEligible
 * @property {() => Promise}       submit
 * @property {(options: {}) => {}} NameField
 * @property {(options: {}) => {}} NumberField
 * @property {(options: {}) => {}} ExpiryField
 * @property {(options: {}) => {}} CVVField
 */

class CardFieldsRenderer {
	/**
	 * A Map that contains details about all input fields for the card checkout.
	 *
	 * @type {Map<FieldName, FieldInfo>|null}
	 */
	#fields = null;

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

	/**
	 * Returns a Map with details about all form fields for the CardField element.
	 *
	 * @return {Map<FieldName, FieldInfo>}
	 */
	get fieldInfos() {
		if ( ! this.#fields ) {
			this.#fields = new Map();

			const domFields = {
				NameField: 'ppcp-credit-card-gateway-card-name',
				NumberField: 'ppcp-credit-card-gateway-card-number',
				ExpiryField: 'ppcp-credit-card-gateway-card-expiry',
				CVVField: 'ppcp-credit-card-gateway-card-cvc',
			};

			Object.entries( domFields ).forEach( ( [ fieldName, fieldId ] ) => {
				const el = document.getElementById( fieldId );
				if ( ! el ) {
					return;
				}

				const wrapper = el.parentNode;
				const styles = cardFieldStyles( el );
				const options = {
					style: { input: styles },
				};

				if ( el.getAttribute( 'placeholder' ) ) {
					options.placeholder = el.getAttribute( 'placeholder' );
				}

				this.#fields.set( fieldName, {
					name: fieldName,
					wrapper,
					options,
					el,
				} );
			} );
		}

		return this.#fields;
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

		const cardField = this.createInstance( contextConfig );

		if ( cardField.isEligible() ) {
			this.insertAllFields( cardField );
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

				cardField.submit().catch( ( error ) => {
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

	/**
	 * Creates and returns a new CardFields instance.
	 *
	 * @see https://developer.paypal.com/sdk/js/reference/#link-cardfields
	 * @param {Object} contextConfig
	 * @return {CardFields}
	 */
	createInstance( contextConfig ) {
		return window.paypal.CardFields( {
			createOrder: contextConfig.createOrder,
			onApprove( data ) {
				return contextConfig.onApprove( data );
			},
			onError( error ) {
				console.error( error );
				this.spinner.unblock();
			},
		} );
	}

	/**
	 * Links the provided CardField instance to the local DOM.
	 *
	 * Note: If another CardField instance was inserted into the DOM before, that previous instance
	 * will be removed/unlinked in this process.
	 *
	 * @param {CardFields} cardField
	 */
	insertAllFields( cardField ) {
		this.fieldInfos.forEach( ( field ) => {
			this.insertField( cardField, field );
		} );

		document.dispatchEvent( new CustomEvent( 'hosted_fields_loaded' ) );
	}

	/**
	 * Renders a single input field from the CardField-instance inside the current document's
	 * DOM, replacing the previous field.
	 * On first call, this "previous field" is the input element generated by PHP.
	 *
	 * @param {CardFields} cardField
	 * @param {FieldInfo}  field
	 */
	insertField( cardField, field ) {
		if ( 'function' !== typeof cardField[ field.name ] ) {
			console.error( `${ field.name } is no valid CardFields property` );
			return;
		}

		// Remove the previous input field from DOM
		field.el?.remove();

		// Render the CardField input element - a div containing an iframe.
		cardField[ field.name ]( field.options ).render( field.wrapper );

		// Store a reference to the new input field in our Map.
		field.el = field.wrapper.querySelector( 'div[id*="paypal"]' );
	}
}

export default CardFieldsRenderer;
