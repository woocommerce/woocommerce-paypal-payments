import {
	getCurrentPaymentMethod,
	ORDER_BUTTON_SELECTOR,
	PaymentMethods,
} from '../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState';
import { loadPayPalScript } from '../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';
import { buttonConfiguration, cardFieldsConfiguration } from './Configuration';
import { renderFields } from '../../../ppcp-card-fields/resources/js/Render';
import {
	setVisible,
	setVisibleByClass,
} from '../../../ppcp-button/resources/js/modules/Helper/Hiding';

( function ( { ppcp_add_payment_method } ) {
	/**
	 * Handles payment method change by updating visibility of buttons.
	 */
	function handlePaymentMethodChange() {
		const currentMethod = getCurrentPaymentMethod();
		const paypalButtonSelector = `#ppc-button-${ PaymentMethods.PAYPAL }-save-payment-method`;
		const paypalButton = document.querySelector( paypalButtonSelector );
		const isSubscriptionChangePage =
			ppcp_add_payment_method.is_subscription_change_payment_page;

		if ( paypalButton ) {
			setVisibleByClass(
				ORDER_BUTTON_SELECTOR,
				currentMethod !== PaymentMethods.PAYPAL,
				'ppcp-hidden'
			);

			setVisible(
				paypalButtonSelector,
				currentMethod === PaymentMethods.PAYPAL
			);
		} else if ( isSubscriptionChangePage ) {
			// On subscription change page: always show the order button when no PayPal button found
			setVisibleByClass( ORDER_BUTTON_SELECTOR, true, 'ppcp-hidden' );
		} else {
			// On add payment method page: use standard logic even if PayPal button doesn't exist
			console.log( 'Add payment method page: Using standard logic' );
			setVisibleByClass(
				ORDER_BUTTON_SELECTOR,
				currentMethod !== PaymentMethods.PAYPAL,
				'ppcp-hidden'
			);
		}
	}

	/**
	 * Sets up event listeners for payment method radio buttons.
	 */
	function setupPaymentMethodListeners() {
		document.body.addEventListener( 'click', function ( event ) {
			const target = event.target;

			if (
				target.matches( '.payment_methods input.input-radio' ) ||
				( target.type === 'radio' &&
					target.closest( '.payment_methods' ) )
			) {
				console.log( 'Payment method clicked' );
				handlePaymentMethodChange();
			}
		} );

		document.body.addEventListener( 'change', function ( event ) {
			const target = event.target;

			if (
				target.matches( '.payment_methods input.input-radio' ) ||
				( target.type === 'radio' && target.name === 'payment_method' )
			) {
				console.log( 'Payment method changed via change event' );
				handlePaymentMethodChange();
			}
		} );

		document.body.addEventListener( 'init_add_payment_method', function () {
			handlePaymentMethodChange();
		} );

		handlePaymentMethodChange();
	}

	/**
	 * Main initialization function for PayPal and card fields.
	 */
	async function initializeScript() {
		if ( ppcp_add_payment_method.is_subscription_change_payment_page ) {
			const saveToAccount = document.querySelector(
				'#wc-ppcp-credit-card-gateway-new-payment-method'
			);
			if ( saveToAccount ) {
				saveToAccount.checked = true;
				saveToAccount.disabled = true;
			}
		}

		const errorHandler = new ErrorHandler(
			ppcp_add_payment_method.labels.error.generic,
			document.querySelector( '.woocommerce-notices-wrapper' )
		);
		errorHandler.clear();

		try {
			const config = {
				url_params: {
					'client-id': ppcp_add_payment_method.client_id,
					'merchant-id': ppcp_add_payment_method.merchant_id,
					components: 'buttons,card-fields',
				},
				save_payment_methods: {
					id_token: ppcp_add_payment_method.id_token,
				},
				user: {
					is_logged: ppcp_add_payment_method.user?.is_logged ?? false,
				},
			};

			const paypal = await loadPayPalScript(
				'ppcp-add-payment-method',
				config
			);

			const paypalButtonContainer = document.querySelector(
				`#ppc-button-${ PaymentMethods.PAYPAL }-save-payment-method`
			);

			if ( paypalButtonContainer ) {
				paypal
					.Buttons(
						buttonConfiguration(
							ppcp_add_payment_method,
							errorHandler
						)
					)
					.render(
						`#ppc-button-${ PaymentMethods.PAYPAL }-save-payment-method`
					);
			}

			const cardFields = paypal.CardFields(
				cardFieldsConfiguration( ppcp_add_payment_method, errorHandler )
			);

			if ( cardFields.isEligible() ) {
				renderFields( cardFields );
			}

			const placeOrderButton = document.querySelector( '#place_order' );
			placeOrderButton?.addEventListener( 'click', ( event ) => {
				const cardPaymentToken = document.querySelector(
					'input[name="wc-ppcp-credit-card-gateway-payment-token"]:checked'
				)?.value;
				if (
					getCurrentPaymentMethod() !== 'ppcp-credit-card-gateway' ||
					( cardPaymentToken && cardPaymentToken !== 'new' )
				) {
					return;
				}
				placeOrderButton.disabled = true;
				event.preventDefault();
				cardFields.submit().catch( ( error ) => {
					console.error( error );
					errorHandler.message(
						ppcp_add_payment_method.error_message
					);
					placeOrderButton.disabled = false;
				} );
			} );
		} catch ( error ) {
			console.error( 'Failed to load PayPal script:', error );
			errorHandler.message(
				ppcp_add_payment_method.labels.error.generic ||
					'Failed to load PayPal. Please refresh the page.'
			);
		}
	}

	/**
	 * Initializes the script when DOM is ready.
	 * Handles both scenarios: before and after DOMContentLoaded.
	 */
	function initializeWhenReady() {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', function () {
				setupPaymentMethodListeners();
				initializeScript();
			} );
		} else {
			setupPaymentMethodListeners();
			initializeScript();
		}
	}

	initializeWhenReady();
} )( {
	ppcp_add_payment_method: window.ppcp_add_payment_method,
} );
