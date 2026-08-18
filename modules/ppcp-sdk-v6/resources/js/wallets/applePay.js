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
 *
 * @package
 */

import Spinner from '@ppcp-button/Helper/Spinner';
import { hasJQuery } from '../utils/api';
import { handleError } from '../utils/errorHandler';
import { loadAppleSdk } from '../utils/scriptLoaders';
import { revealGateway, syncGatewayVisibility } from './gatewayPlacement';
import { APPLE_PAY_VERSION, buildApplePayRequest } from './applePayRequest';
import { watchSheetTotal } from './applePaySheetTotal';
import { recordDomainValidation } from './applePayValidation';
import { applePayPayer, applePayShippingAddress } from './walletContacts';
import { payWithWallet } from './walletPayment';
import { resolveWalletTotal } from './walletTotal';

/**
 * The funding_source the WC AJAX endpoints know Apple Pay by.
 *
 * Not FundingSources.APPLEPAY ('applepay', the SDK's spelling): ApplepayModule's
 * ppcp_create_order_request_body_data filter matches this underscored form, and an
 * express-context order that misses it loses its payment_source.
 */
const FUNDING_SOURCE = 'apple_pay';

/**
 * Renders the Apple Pay button and wires its click to a payment.
 *
 * @param {Object}  args           - The render inputs.
 * @param {Object}  args.wrapper   - The button wrapper to render into.
 * @param {Object}  args.config    - The wc_ppcp_sdk_v6 config object.
 * @param {string}  args.context   - The page context.
 * @param {Object}  args.session   - The v6 Apple Pay payment session.
 * @param {?Object} [args.gateway] - The { id, wrapper } of the payment-method
 *                                 row, when the wallet is its own gateway.
 * @return {Promise<void>} Resolves once the button is rendered, or skipped.
 */
export async function renderApplePay( {
	wrapper,
	config,
	context,
	session,
	gateway,
} ) {
	// Answers off a native global, before anything is fetched: an incapable browser
	// loads no SDK and leaves the DOM untouched, so the gateway row stays hidden.
	if ( ! isDeviceEligible() ) {
		return;
	}

	// Own box, appended before the first await so the wrapper is non-empty by the
	// time boot.js checks it and skips a redundant second render pass.
	const container = document.createElement( 'div' );
	wrapper.appendChild( container );

	// Apple's SDK is only needed for the <apple-pay-button> element below, so it
	// loads alongside PayPal's config rather than before it.
	const [ , applePayConfig ] = await Promise.all( [
		loadAppleSdk( config.apple_pay.sdk_url ),
		session.config(),
	] );

	// The narrow client-side veto: merchant capability and product status are
	// already gated by ApplePayConfig server-side. Only an explicit refusal counts,
	// so an absent field never withholds the button from every shopper.
	if ( false === applePayConfig?.isEligible ) {
		container.remove();
		return;
	}

	// Lowercases the networks and camelCases the capabilities into the spellings
	// Apple's payment request expects.
	const sessionConfig =
		session.formatConfigForPaymentRequest( applePayConfig );

	if ( gateway ) {
		revealGateway( gateway.id );
		syncGatewayVisibility( {
			methodId: gateway.id,
			wrapperSelector: gateway.wrapper,
			expressSelector: config.wrapper,
		} );
	}

	const sheetTotal = watchSheetTotal( config, context );
	const spinner = hasJQuery() ? Spinner.fullPage() : null;
	let paying = false;

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

		const appleSession = new window.ApplePaySession(
			APPLE_PAY_VERSION,
			buildApplePayRequest( sessionConfig, {
				currencyCode: config.currency,
				total,
				displayName: config.apple_pay.display_name,
				context,
			} )
		);

		appleSession.onvalidatemerchant = ( event ) => {
			validateMerchant( appleSession, event );
		};

		appleSession.onpaymentauthorized = ( event ) => {
			authorizePayment( appleSession, event );
		};

		// A dismissal is not a failure to report, but it must release `paying` or
		// the button could never open a second sheet.
		appleSession.oncancel = () => {
			paying = false;
			spinner?.unblock();
			refreshCartUi();
		};

		// Presents the sheet, and only then asks for merchant validation.
		appleSession.begin();
	}

	/**
	 * Refreshes the mini-cart fragments after an abandoned sheet.
	 *
	 * On the product page authorizing adds the product to the real cart, so a
	 * shopper who then dismisses the sheet must still see the cart they now have.
	 */
	function refreshCartUi() {
		if ( context === 'product' && hasJQuery() ) {
			jQuery( document.body ).trigger( 'wc_fragment_refresh' );
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
				displayName: config.apple_pay.display_name,
				validationUrl: event.validationURL,
			} );

			appleSession.completeMerchantValidation( merchantSession );

			recordDomainValidation( config, true );
		} catch ( error ) {
			recordDomainValidation( config, false );

			// Nothing can be paid without a validated merchant, and the usual
			// cause (an unregistered domain) is not something the shopper can
			// act on, so close the sheet rather than leave it open.
			paying = false;
			appleSession.abort();
			handleError( error );
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
		// between the sheet closing and payWithWallet's redirect.
		spinner?.block();

		try {
			// This is what adds a viewed product to the real cart. Its total is
			// discarded: the shopper must be charged what the sheet showed them.
			const { purchaseUnits } = await resolveWalletTotal(
				config,
				context
			);

			await payWithWallet( {
				config,
				context,
				session,
				fundingSource: FUNDING_SOURCE,
				// Undefined on the express path, where the order belongs to the
				// PayPal gateway as Venmo's and Pay Later's do.
				paymentMethod: gateway?.id,
				purchaseUnits,
				confirmData: {
					token: event.payment.token,
					billingContact: event.payment.billingContact,
					shippingContact: event.payment.shippingContact,
				},
				contact: {
					payer: applePayPayer( event.payment ),
					shippingAddress: applePayShippingAddress( event.payment ),
				},
			} );

			// Dismisses the sheet. The spinner stays up: payWithWallet has
			// already started the redirect or submitted the checkout form.
			appleSession.completePayment(
				window.ApplePaySession.STATUS_SUCCESS
			);
		} catch ( error ) {
			// How Apple shows the shopper that the payment failed.
			appleSession.completePayment(
				window.ApplePaySession.STATUS_FAILURE
			);

			paying = false;
			spinner?.unblock();
			refreshCartUi();
			handleError( error );
		}
	}

	const styles = config.apple_pay.styles?.[ context ] || {};

	container.appendChild( createButton( styles, config.button_height, pay ) );
}

/**
 * Whether this browser and device can present an Apple Pay sheet.
 *
 * canMakePayments() reports device capability only, not whether a card is set up,
 * and throws rather than returning false on some configurations.
 *
 * @return {boolean} False on anything that is not a capable Apple browser.
 */
function isDeviceEligible() {
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
 * @param {Object}   styles  - The button styles for this context.
 * @param {string}   height  - The height every payment button shares.
 * @param {Function} onClick - The click handler.
 * @return {HTMLElement} The button element.
 */
function createButton( styles, height, onClick ) {
	const button = document.createElement( 'apple-pay-button' );

	// Defaulted because settings leave these empty until the merchant picks a value,
	// and Apple renders nothing at all for an empty locale.
	button.setAttribute( 'buttonstyle', styles.color || 'black' );
	button.setAttribute( 'type', styles.type || 'pay' );
	button.setAttribute( 'locale', styles.language || 'en' );

	// Custom properties, not attributes: the element takes its size and shape from
	// its shadow DOM. A height is set at all because Apple's intrinsic one is far
	// shorter than the rest of the checkout controls. Undefaulted, unlike the
	// attributes above, since PHP always sends both.
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
