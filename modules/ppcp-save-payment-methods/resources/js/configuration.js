import {
	getCurrentPaymentMethod,
	PaymentMethods,
} from '@ppcp-button/Helper/CheckoutMethodState';

const CONTEXTS = {
	CHECKOUT: 'checkout',
};

/**
 * Makes an API request with proper error handling
 *
 * @param {string} endpoint       - API endpoint URL
 * @param {string} nonce          - WordPress nonce for security
 * @param {Object} additionalData - Additional data to send in the request body
 * @return {Promise<Object>} Response JSON
 * @throws {Error} If the request fails
 */
async function makeApiRequest( endpoint, nonce, additionalData = {} ) {
	try {
		const response = await fetch( endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( {
				nonce,
				...additionalData,
			} ),
		} );

		if ( ! response.ok ) {
			throw new Error( `HTTP error status: ${ response.status }` );
		}

		return await response.json();
	} catch ( error ) {
		console.error( 'API request failed:', error );
		throw error;
	}
}

/**
 * Handles subscription payment method change
 *
 * @param {Object} config         - Configuration object from ppcp_add_payment_method
 * @param {string} subscriptionId - Subscription ID
 * @param {string} paymentTokenId - WooCommerce payment token ID
 * @return {Promise<boolean>} True if successful, false otherwise
 */
async function handleSubscriptionPaymentChange(
	config,
	subscriptionId,
	paymentTokenId
) {
	if ( ! subscriptionId || ! paymentTokenId ) {
		return false;
	}

	try {
		const result = await makeApiRequest(
			config.ajax.subscription_change_payment_method.endpoint,
			config.ajax.subscription_change_payment_method.nonce,
			{
				subscription_id: subscriptionId,
				payment_method: getCurrentPaymentMethod(),
				wc_payment_token_id: paymentTokenId,
			}
		);

		if ( result.success === true ) {
			const redirectUrl = `${ config.view_subscriptions_page }/${ subscriptionId }`;
			window.location.href = redirectUrl;
			return true;
		}

		return false;
	} catch ( error ) {
		console.error( 'Subscription payment change failed:', error );
		return false;
	}
}

/**
 * Redirects to the payment methods page
 *
 * @param {string} url - URL to redirect to
 */
function redirectToPaymentMethods( url ) {
	if ( url && typeof url === 'string' ) {
		window.location.href = url;
	}
}

/**
 * Triggers the checkout form submission
 */
function submitCheckoutForm() {
	const placeOrderButton = document.querySelector( '#place_order' );
	if ( placeOrderButton ) {
		placeOrderButton.click();
	} else {
		console.error( 'Place order button (#place_order) not found in DOM' );
	}
}

/**
 * Creates a vault setup token
 *
 * @param {Object}      config                     - Configuration object
 * @param {Object}      errorHandler               - Error handler object
 * @param {Object}      options                    - Additional options for setup token creation
 * @param {string|null} options.paymentMethod      - Payment method type
 * @param {string|null} options.verificationMethod - Verification method
 * @return {Promise<string|undefined>} Setup token ID or undefined on error
 */
async function createVaultSetupToken( config, errorHandler, options = {} ) {
	const { paymentMethod = null, verificationMethod = null } = options;

	try {
		const additionalData = {};

		if ( paymentMethod ) {
			additionalData.payment_method = paymentMethod;
		}

		if ( verificationMethod ) {
			additionalData.verification_method = verificationMethod;
		}

		const result = await makeApiRequest(
			config.ajax.create_setup_token.endpoint,
			config.ajax.create_setup_token.nonce,
			additionalData
		);

		if ( result.data?.id ) {
			return result.data.id;
		}

		throw new Error( 'Setup token ID not found in response' );
	} catch ( error ) {
		console.error( 'Create vault setup token failed:', error );
		errorHandler?.message( config.error_message );
		return undefined;
	}
}

/**
 * Handles approval flow for payment method addition
 *
 * @param {Object}      config                  - Configuration object
 * @param {Object}      errorHandler            - Error handler object
 * @param {string}      vaultSetupToken         - Vault setup token from PayPal
 * @param {Object}      options                 - Additional options
 * @param {string|null} options.paymentMethod   - Payment method type
 * @param {string|null} options.context         - Context of the flow
 * @param {boolean}     options.isFreeTrialCart - Whether this is a free trial cart
 * @return {Promise<void>}
 */
async function handleApproval(
	config,
	errorHandler,
	vaultSetupToken,
	options = {}
) {
	const {
		paymentMethod = null,
		context = null,
		isFreeTrialCart = false,
	} = options;

	try {
		const additionalData = {
			vault_setup_token: vaultSetupToken,
		};

		if ( paymentMethod ) {
			additionalData.payment_method = paymentMethod;
		}

		if ( isFreeTrialCart ) {
			additionalData.is_free_trial_cart = true;
		}

		const result = await makeApiRequest(
			config.ajax.create_payment_token.endpoint,
			config.ajax.create_payment_token.nonce,
			additionalData
		);

		if ( result.success !== true ) {
			throw new Error( 'Payment token creation failed' );
		}

		if ( context === CONTEXTS.CHECKOUT ) {
			submitCheckoutForm();
			return;
		}

		if ( config.is_subscription_change_payment_page ) {
			const subscriptionId = config.subscription_id_to_change_payment;

			if ( subscriptionId && result.data ) {
				const changed = await handleSubscriptionPaymentChange(
					config,
					subscriptionId,
					result.data
				);

				if ( changed ) {
					return;
				}
			}

			// If subscription change failed or wasn't applicable, return early
			return;
		}

		// Default: redirect to payment methods page
		redirectToPaymentMethods( config.payment_methods_page );
	} catch ( error ) {
		console.error( 'Approval handling failed:', error );
		errorHandler?.message( config.error_message );
	}
}

/**
 * Handles approval flow for guest payment method addition
 *
 * @param {Object} config          - Configuration object
 * @param {string} vaultSetupToken - Vault setup token from PayPal
 * @return {Promise<void>}
 */
async function handleGuestApproval( config, vaultSetupToken ) {
	try {
		const result = await makeApiRequest(
			config.ajax.create_payment_token_for_guest.endpoint,
			config.ajax.create_payment_token_for_guest.nonce,
			{
				vault_setup_token: vaultSetupToken,
			}
		);

		if ( result.success === true ) {
			submitCheckoutForm();
			return;
		}

		throw new Error( 'Guest payment token creation failed' );
	} catch ( error ) {
		console.error( 'Guest approval failed:', error );
	}
}

/**
 * Generic error handler for PayPal button events
 *
 * @param {Error}  error        - Error object
 * @param {Object} errorHandler - Error handler object
 * @param {string} errorMessage - Error message to display
 */
function handleError( error, errorHandler, errorMessage ) {
	console.error( error );
	errorHandler?.message( errorMessage );
}

/**
 * Configuration for PayPal button payment method addition
 *
 * @param {Object} addPaymentMethodConfig - Configuration from server
 * @param {Object} errorHandler           - Error handler object
 * @return {Object} Button configuration object
 */
export function buttonConfiguration( addPaymentMethodConfig, errorHandler ) {
	return {
		createVaultSetupToken: async () => {
			return await createVaultSetupToken(
				addPaymentMethodConfig,
				errorHandler
			);
		},
		onApprove: async ( { vaultSetupToken } ) => {
			return await handleApproval(
				addPaymentMethodConfig,
				errorHandler,
				vaultSetupToken
			);
		},
		onError: ( error ) => {
			handleError(
				error,
				errorHandler,
				addPaymentMethodConfig.error_message
			);
		},
	};
}

/**
 * Configuration for card fields payment method addition
 *
 * @param {Object} addPaymentMethodConfig - Configuration from server
 * @param {Object} errorHandler           - Error handler object
 * @return {Object} Card fields configuration object
 */
export function cardFieldsConfiguration(
	addPaymentMethodConfig,
	errorHandler
) {
	return {
		createVaultSetupToken: async () => {
			return await createVaultSetupToken(
				addPaymentMethodConfig,
				errorHandler,
				{
					paymentMethod: PaymentMethods.CARDS,
					verificationMethod:
						addPaymentMethodConfig.verification_method,
				}
			);
		},
		onApprove: async ( { vaultSetupToken } ) => {
			const isFreeTrialCart =
				addPaymentMethodConfig?.is_free_trial_cart ?? false;
			const context = addPaymentMethodConfig?.context ?? null;

			return await handleApproval(
				addPaymentMethodConfig,
				errorHandler,
				vaultSetupToken,
				{
					paymentMethod: PaymentMethods.CARDS,
					context,
					isFreeTrialCart,
				}
			);
		},
		onError: ( error ) => {
			handleError(
				error,
				errorHandler,
				addPaymentMethodConfig.error_message
			);
		},
	};
}

/**
 * Configuration for guest checkout payment method addition
 *
 * @param {Object} addPaymentMethodConfig - Configuration from server
 * @return {Object} Guest payment method configuration object
 */
export function addPaymentMethodConfiguration( addPaymentMethodConfig ) {
	return {
		createVaultSetupToken: async () => {
			try {
				const result = await makeApiRequest(
					addPaymentMethodConfig.ajax.create_setup_token.endpoint,
					addPaymentMethodConfig.ajax.create_setup_token.nonce,
					{
						payment_method: getCurrentPaymentMethod(),
					}
				);

				if ( result.data?.id ) {
					return result.data.id;
				}

				throw new Error( 'Setup token ID not found in response' );
			} catch ( error ) {
				console.error( 'Create setup token failed:', error );
				return undefined;
			}
		},
		onApprove: async ( { vaultSetupToken } ) => {
			return await handleGuestApproval(
				addPaymentMethodConfig,
				vaultSetupToken
			);
		},
		onError: ( error ) => {
			console.error( error );
		},
	};
}
