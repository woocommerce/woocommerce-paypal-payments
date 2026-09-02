/**
 * Apple Pay bridge: renders the button and drives the payment sheet.
 *
 * Three Apple constraints shape this file. Safari only constructs an
 * ApplePaySession inside the click handler itself, so the total must be known
 * before the click (applePaySheetTotal.js) and nothing may be awaited on the way
 * to begin(). The sheet then reports through native events rather than a promise,
 * so approval arrives in onpaymentauthorized and the outcome goes back through
 * completePayment(). And merchant validation is the merchant's to perform, which
 * the session's validateMerchant() answers.
 */

import Spinner from '@ppcp-button/Helper/Spinner';
import { releaseCartShipping } from '../endpointsAdapter';
import { hasJQuery } from '../utils/api';
import { refreshCartUi } from '../utils/cartUi';
import { logError } from '../utils/diagnostics';
import { handleError } from '../utils/errorHandler';
import { loadScript } from '../utils/scriptLoaders';
import { revealMethodGateway } from '../methods/gatewayPlacement';
import { renderIsObsolete } from '../methods/renderOverrides';
import { APPLE_PAY_VERSION, buildApplePayRequest } from './applePayRequest';
import { watchSheetTotal } from './applePaySheetTotal';
import { applePayFailure, attachShippingHandlers } from './applePayShipping';
import { recordDomainValidation } from './applePayValidation';
import { buttonStyle } from '../methods/buttonStyle';
import {
	applePayPayer,
	applePayShippingAddress,
	applePayWcBillingAddress,
	applePayWcShippingAddress,
} from './walletContacts';
import { payWithSession } from '../methods/sessionPayment';
import { methodConfig, methodFundingSource } from '../methods/methodRegistry';
import {
	createShippingController,
	methodShippingRequired,
} from '../methods/methodShipping';
import { resolveContextTotal } from '../methods/contextTotal';

/**
 * Renders the Apple Pay button and wires its click to a payment.
 *
 * @param {Object}  args             - The render inputs.
 * @param {string}  args.method      - The wallet's funding source.
 * @param {Object}  args.wrapper     - The button wrapper to render into.
 * @param {Object}  args.config      - The wc_ppcp_sdk_v6 config object.
 * @param {string}  args.context     - The page context.
 * @param {Object}  args.session     - The v6 Apple Pay payment session.
 * @param {?Object} [args.gateway]   - The { id, wrapper } of the payment-method
 *                                   row, when the wallet is its own gateway.
 * @param {Object}  [args.overrides] - Surface-specific overrides, as described
 *                                   by renderMethodInto().
 * @return {Promise<void>} Resolves once the button is rendered, or skipped.
 */
export async function renderApplePay( {
	method,
	wrapper,
	config,
	context,
	session,
	gateway,
	overrides = {},
} ) {
	// Answers off a native global, before anything is fetched: an incapable browser
	// loads no SDK and leaves the DOM untouched, so the gateway row stays hidden.
	if ( ! isDeviceEligible() ) {
		overrides.onUnavailable?.();
		return;
	}

	const settings = methodConfig( config, method );

	// Own box, appended before the first await so the wrapper is non-empty by the
	// time boot.js checks it and skips a redundant second render pass.
	const container = document.createElement( 'div' );
	wrapper.appendChild( container );

	// Apple's SDK is only needed for the <apple-pay-button> element below, so it
	// loads alongside PayPal's config rather than before it.
	const [ , applePayConfig ] = await Promise.all( [
		// The applepay-payments bundle ships this URL but only loads it for its
		// basic session, which presents its own sheet; the custom session this
		// module drives does not, so the load is ours. No global to verify
		// afterwards, unlike Google's: eligibility is the native
		// window.ApplePaySession, checked above.
		loadScript( settings.sdk_url ),
		session.config(),
	] );

	if ( renderIsObsolete( overrides ) ) {
		container.remove();
		return;
	}

	// The narrow client-side veto: merchant capability and product status are
	// already gated by ApplePayConfig server-side. Only an explicit refusal counts,
	// so an absent field never withholds the button from every shopper.
	if ( false === applePayConfig?.isEligible ) {
		container.remove();
		overrides.onUnavailable?.();
		return;
	}

	revealMethodGateway( gateway, config );

	// Synchronous either way: Safari refuses a sheet opened after an await.
	const sheetTotal = overrides.sheetTotal ?? watchSheetTotal( config, context );

	// Without contacts the sheet opens on the shopper's own default.
	const sheetContacts = overrides.sheetContacts ?? { get: () => ( {} ) };
	const requiresShipping =
		overrides.requiresShipping ?? methodShippingRequired( config, context );
	const shipping = createShippingController( { config } );
	const spinner = hasJQuery() ? Spinner.fullPage() : null;
	let paying = false;

	/**
	 * The cart holding what is being bought, resolved once per sheet.
	 *
	 * On the product page this is what adds the viewed product, and an empty cart
	 * offers no rates for the sheet to show. Started rather than awaited at click
	 * time, because Safari refuses to present a sheet after an await.
	 */
	let cartReady = null;

	/**
	 * Opens the payment sheet and pays with what it returns.
	 *
	 * Deliberately not async: everything up to begin() must run in the same task
	 * as the click, or Safari refuses to present the sheet.
	 */
	function pay() {
		// A second tap while a sheet is open would replace the live session.
		if ( paying ) {
			return;
		}

		const total = sheetTotal.get();
		if ( ! total ) {
			// Refusing beats charging a total the shopper never saw.
			handleError(
				new Error( 'Apple Pay could not determine the order total.' )
			);
			return;
		}

		paying = true;

		// Claims the surface's express UI; onSheetClosed() releases it again.
		overrides.onClick?.();

		cartReady = resolveContextTotal( config, context );

		// Claimed here so a rejection nothing has awaited yet stays handled.
		cartReady.catch( () => {} );

		// Read at click time, so a late address edit still reaches the sheet.
		const contacts = sheetContacts.get();

		const request = buildApplePayRequest( applePayConfig, {
			// Stands in when PayPal's config carries no country of its own.
			countryCode: config.merchant_country,
			currencyCode: config.currency,
			total,
			displayName: settings.display_name,
			requiresShipping,
			shippingContact: contacts.shipping,
			billingContact: contacts.billing,
		} );

		// Apple rejects a malformed request (currency, supportedNetworks) by
		// throwing synchronously, so `paying` must be released here or the
		// button would silently ignore every later tap.
		try {
			const appleSession = new window.ApplePaySession(
				APPLE_PAY_VERSION,
				request
			);

			if ( requiresShipping ) {
				attachShippingHandlers( appleSession, {
					config,
					displayName: settings.display_name,
					shipping,
					cartReady,
				} );
			}

			appleSession.onvalidatemerchant = ( event ) => {
				validateMerchant( appleSession, event );
			};

			appleSession.onpaymentauthorized = ( event ) => {
				authorizePayment( appleSession, event );
			};

			// A dismissal is not a failure to report, but it must release
			// `paying` or the button could never open a second sheet. It also
			// drops the rate this sheet pinned server-side.
			appleSession.oncancel = () => {
				paying = false;
				spinner?.unblock();

				if ( requiresShipping ) {
					releaseCartShipping( config ).catch( () => {} );
				}

				refreshCartUi( context );
				overrides.onSheetClosed?.();
			};

			// Presents the sheet, and only then asks for merchant validation.
			appleSession.begin();
		} catch ( error ) {
			paying = false;
			spinner?.unblock();
			handleError( error );
			overrides.onSheetClosed?.();
		}
	}

	/**
	 * Answers Apple's request to validate the merchant.
	 *
	 * @param {Object} appleSession - The ApplePaySession awaiting validation.
	 * @param {Object} event        - The onvalidatemerchant event.
	 * @return {Promise<void>} Resolves once the session was told the outcome.
	 */
	async function validateMerchant( appleSession, event ) {
		try {
			// Already decoded by the v6 session, so it goes straight to Apple.
			const { merchantSession } = await session.validateMerchant( {
				displayName: settings.display_name,
				validationUrl: event.validationURL,
			} );

			appleSession.completeMerchantValidation( merchantSession );

			recordDomainValidation( settings, true );
		} catch ( error ) {
			recordDomainValidation( settings, false );

			// PayPal answers this one straight to the browser, so the reason
			// exists nowhere else.
			logError( config, 'apple-pay-merchant-validation-failed', {
				message: error.message,
			} );

			// Nothing can be paid without a validated merchant, and the usual
			// cause (an unregistered domain) is not something the shopper can
			// act on, so close the sheet rather than leave it open.
			paying = false;
			appleSession.abort();
			handleError( error );
			overrides.onSheetClosed?.();
		}
	}

	/**
	 * Pays for the authorized sheet.
	 *
	 * @param {Object} appleSession - The ApplePaySession awaiting a result.
	 * @param {Object} event        - The onpaymentauthorized event.
	 * @return {Promise<void>} Resolves once the sheet was told the outcome.
	 */
	async function authorizePayment( appleSession, event ) {
		// Blocked while the sheet is still up, so no idle page shows in the moment
		// between the sheet closing and payWithSession's redirect.
		spinner?.block();

		try {
			// Its units price the order; its total is ignored, because the sheet
			// already described the same basket via the simulate endpoint.
			const { purchaseUnits } = await cartReady;

			// Before the order exists, because it is built from the WC customer
			// rather than the address posted with the approval. Throws rather
			// than charge a total the sheet never showed.
			if ( requiresShipping ) {
				await shipping.commit(
					applePayWcShippingAddress( event.payment ),
					applePayWcBillingAddress( event.payment )
				);
			}

			await payWithSession( {
				config,
				context,
				session,
				fundingSource: methodFundingSource( method ),
				// Undefined on the express path, where the order belongs to the
				// PayPal gateway as Venmo's and Pay Later's do.
				paymentMethod: gateway?.id,
				purchaseUnits,
				// No shippingContact: an express order is created with
				// GET_FROM_FILE (see ShippingPreferenceFactory), and supplying an
				// address for one fails with APPROVE_APPLE_PAY_VALIDATION_ERROR.
				confirmData: {
					token: event.payment.token,
					billingContact: event.payment.billingContact,
				},
				contact: {
					payer: applePayPayer( event.payment ),
					shippingAddress: applePayShippingAddress( event.payment ),
					shippingRateId: shipping.current()?.selectedId,
				},
			} );

			// Dismisses the sheet. The spinner stays up: payWithSession has
			// already started the redirect or submitted the checkout form.
			appleSession.completePayment(
				window.ApplePaySession.STATUS_SUCCESS
			);
		} catch ( error ) {
			// Apple's generic sheet wording fits every branch equally, so
			// record which one this was.
			logError( config, 'apple-pay-authorization-failed', {
				message: error.message,
				status: error.status,
				endpoint: error.endpoint,
				body: error.bodySnippet,
			} );

			// Apple still has the sheet up, so it can tell the shopper why.
			appleSession.completePayment(
				applePayFailure( error, window.ApplePaySession.STATUS_FAILURE )
			);

			paying = false;
			spinner?.unblock();
			refreshCartUi( context );
			handleError( error );
			overrides.onSheetClosed?.();
		}
	}

	// renderMethod() only reaches this bridge when PHP styled this context.
	const styles = buttonStyle( settings.styles[ context ] );

	// The block sizing controls arrive unitless; Apple wants a CSS length.
	if ( overrides.borderRadius !== undefined ) {
		styles.borderRadius = `${ Number( overrides.borderRadius ) }px`;
	}

	container.appendChild(
		createButton( styles, overrides.height || config.button_height, pay )
	);
}

/**
 * Whether this browser and device can present an Apple Pay sheet.
 *
 * canMakePayments() reports device capability only, not whether a card is set up,
 * and throws rather than returning false on some configurations.
 *
 * @return {boolean} False on anything that is not a capable Apple browser.
 */
export function isDeviceEligible() {
	try {
		return !! window.ApplePaySession?.canMakePayments();
	} catch ( error ) {
		return false;
	}
}

/**
 * Creates the <apple-pay-button> element.
 *
 * A custom element registered by Apple's SDK, not something the PayPal SDK
 * renders, so the styling is applied as attributes and custom properties.
 *
 * @param {Object}   styles  - The resolved button styles for this context.
 * @param {string}   height  - The height every payment button shares.
 * @param {Function} onClick - The click handler.
 * @return {HTMLElement} The button element.
 */
function createButton( styles, height, onClick ) {
	const button = document.createElement( 'apple-pay-button' );

	button.setAttribute( 'buttonstyle', styles.color );
	button.setAttribute( 'type', styles.type );
	button.setAttribute( 'locale', styles.language );

	// Custom properties, not attributes: the element takes its size and shape from
	// its shadow DOM. A height is set at all because Apple's intrinsic one is far
	// shorter than the rest of the checkout controls.
	button.style.setProperty( '--apple-pay-button-height', height );
	button.style.setProperty(
		'--apple-pay-button-border-radius',
		styles.borderRadius
	);
	button.style.display = 'block';
	button.style.height = height;

	button.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		onClick();
	} );

	return button;
}
