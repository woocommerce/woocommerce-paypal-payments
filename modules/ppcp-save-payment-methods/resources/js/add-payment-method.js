import {
	getCurrentPaymentMethod,
	ORDER_BUTTON_SELECTOR,
	PaymentMethods,
} from '@ppcp-button/Helper/CheckoutMethodState';
import { loadPayPalScript } from '@ppcp-button/Helper/PayPalScriptLoading';
import ErrorHandler from '@ppcp-button/ErrorHandler';
import { buttonConfiguration, cardFieldsConfiguration } from './configuration';
import { renderFields } from '@ppcp-card-fields/Render';
import {
	setVisible,
	setVisibleByClass,
} from '@ppcp-button/Helper/Hiding';

/**
 * Handles payment method change by updating visibility of buttons.
 *
 * @param {Object} addPaymentMethodConfig - Configuration object
 */
export function handlePaymentMethodChange( addPaymentMethodConfig ) {
	const currentMethod = getCurrentPaymentMethod();
	const paypalButtonSelector = `#ppc-button-${ PaymentMethods.PAYPAL }-save-payment-method`;
	const paypalButton = document.querySelector( paypalButtonSelector );
	const isSubscriptionChangePage =
		addPaymentMethodConfig.is_subscription_change_payment_page;

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
		setVisibleByClass(
			ORDER_BUTTON_SELECTOR,
			currentMethod !== PaymentMethods.PAYPAL,
			'ppcp-hidden'
		);
	}
}

/**
 * Sets up event listeners for payment method radio buttons.
 *
 * @param {Object} addPaymentMethodConfig - Configuration object
 */
export function setupPaymentMethodListeners( addPaymentMethodConfig ) {
	document.body.addEventListener( 'click', function ( event ) {
		const target = event.target;

		if (
			target.matches( '.payment_methods input.input-radio' ) ||
			( target.type === 'radio' && target.closest( '.payment_methods' ) )
		) {
			handlePaymentMethodChange( addPaymentMethodConfig );
		}
	} );

	document.body.addEventListener( 'change', function ( event ) {
		const target = event.target;

		if (
			target.matches( '.payment_methods input.input-radio' ) ||
			( target.type === 'radio' && target.name === 'payment_method' )
		) {
			handlePaymentMethodChange( addPaymentMethodConfig );
		}
	} );

	document.body.addEventListener( 'init_add_payment_method', function () {
		handlePaymentMethodChange( addPaymentMethodConfig );
	} );

	handlePaymentMethodChange( addPaymentMethodConfig );
}

/**
 * Main initialization function for PayPal and card fields.
 *
 * @param {Object} addPaymentMethodConfig - Configuration object
 */
export async function initializeScript( addPaymentMethodConfig ) {
	if ( addPaymentMethodConfig.is_subscription_change_payment_page ) {
		const saveToAccount = document.querySelector(
			'#wc-ppcp-credit-card-gateway-new-payment-method'
		);
		if ( saveToAccount ) {
			saveToAccount.checked = true;
			saveToAccount.disabled = true;
		}
	}

	const errorHandler = new ErrorHandler(
		addPaymentMethodConfig.labels.error.generic,
		document.querySelector( '.woocommerce-notices-wrapper' )
	);
	errorHandler.clear();

	try {
		const config = {
			url_params: {
				'client-id': addPaymentMethodConfig.client_id,
				'merchant-id': addPaymentMethodConfig.merchant_id,
				components: 'buttons,card-fields',
			},
			save_payment_methods: {
				id_token: addPaymentMethodConfig.id_token,
			},
			user: {
				is_logged: addPaymentMethodConfig.user?.is_logged ?? false,
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
			await paypal
				.Buttons(
					buttonConfiguration( addPaymentMethodConfig, errorHandler )
				)
				.render(
					`#ppc-button-${ PaymentMethods.PAYPAL }-save-payment-method`
				);
		}

		const cardFields = paypal.CardFields(
			cardFieldsConfiguration( addPaymentMethodConfig, errorHandler )
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
				errorHandler.message( addPaymentMethodConfig.error_message );
				placeOrderButton.disabled = false;
			} );
		} );
	} catch ( error ) {
		console.error( 'Failed to load PayPal script:', error );
		errorHandler.message(
			addPaymentMethodConfig.labels.error.generic ||
				'Failed to load PayPal. Please refresh the page.'
		);
	}
}

/**
 * Initializes the script when DOM is ready.
 * Handles both scenarios: before and after DOMContentLoaded.
 *
 * @param {Object} addPaymentMethodConfig - Configuration object
 */
export function initializeWhenReady( addPaymentMethodConfig ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			setupPaymentMethodListeners( addPaymentMethodConfig );
			initializeScript( addPaymentMethodConfig );
		} );
	} else {
		setupPaymentMethodListeners( addPaymentMethodConfig );
		initializeScript( addPaymentMethodConfig );
	}
}

// Initialize the script on page load (skip in test environment)
if ( typeof window !== 'undefined' && window.ppcp_add_payment_method ) {
	( function ( { addPaymentMethodConfig } ) {
		initializeWhenReady( addPaymentMethodConfig );
	} )( {
		addPaymentMethodConfig: window.ppcp_add_payment_method,
	} );
}
