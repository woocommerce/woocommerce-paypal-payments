/**
 * Google Pay bridge: renders the button and drives the payment sheet.
 *
 * The only DOM-aware wallet file in this slice. The v6 SDK's
 * googlepay-payments component neither loads Google's pay.js nor touches
 * PaymentsClient, so the merchant owns the button and the sheet, and hands
 * the resulting payment data back to the session.
 *
 * @package
 */

import Spinner from '@ppcp-button/Helper/Spinner';
import { releaseCartShipping } from '../endpointsAdapter';
import { hasJQuery } from '../utils/api';
import { refreshCartUi } from '../utils/cartUi';
import { handleError } from '../utils/errorHandler';
import { loadGoogleSdk } from '../utils/scriptLoaders';
import { revealWalletGateway } from './gatewayPlacement';
import { renderIsObsolete } from './renderOverrides';
import {
	buildPaymentDataRequest,
	buildReadyToPayRequest,
} from './googlePayRequest';
import { buildPaymentDataCallbacks } from './googlePayShipping';
import { walletButtonStyle } from './walletButtonStyle';
import {
	googlePayPayer,
	googlePayShippingAddress,
	googlePayWcBillingAddress,
	googlePayWcShippingAddress,
} from './walletContacts';
import { payWithSession } from './sessionPayment';
import { methodConfig, methodFundingSource } from './methodRegistry';
import {
	createShippingController,
	methodShippingCountries,
	methodShippingRequired,
} from './methodShipping';
import { resolveContextTotal } from './contextTotal';

// The element Google sizes, inside the wrapper createButton() hands back when
// buttonSizeMode is 'fill'. Without that mode it is the returned node itself.
const GOOGLE_BUTTON_SELECTOR = '.gpay-card-info-container';

/**
 * Renders the Google Pay button and wires its click to a payment.
 *
 * @param {Object}  args             - The render inputs.
 * @param {string}  args.method      - The wallet's funding source.
 * @param {Object}  args.wrapper     - The button wrapper to render into.
 * @param {Object}  args.config      - The wc_ppcp_sdk_v6 config object.
 * @param {string}  args.context     - The page context.
 * @param {Object}  args.session     - The v6 Google Pay payment session.
 * @param {?Object} [args.gateway]   - The { id, wrapper } of the payment-method
 *                                   row, when the wallet is its own gateway.
 * @param {Object}  [args.overrides] - Surface-specific overrides, as described
 *                                   by renderMethodInto().
 * @return {Promise<void>} Resolves once the button is rendered, or skipped.
 */
export async function renderGooglePay( {
	method,
	wrapper,
	config,
	context,
	session,
	gateway,
	overrides = {},
} ) {
	// Own box, because buttonSizeMode 'fill' cannot share the wrapper with the
	// PayPal buttons — and sizes the button to that box, so the shared height goes
	// here. Appended before the first await so boot.js's emptiness check skips a
	// redundant later pass.
	const settings = methodConfig( config, method );

	const container = document.createElement( 'div' );
	container.style.height = overrides.height || config.button_height;
	wrapper.appendChild( container );

	// Independent: fetching PayPal's config does not need Google's global,
	// which is first required by the PaymentsClient below.
	//
	// Used as returned, without session.formatConfigForPaymentRequest(): that
	// helper renames a parameters.supportedNetworks key that this config does
	// not have, so it overwrites the correct allowedCardNetworks with
	// undefined (and drops countryCode), which makes isReadyToPay throw
	// DEVELOPER_ERROR. getGooglePayConfig() already returns a Google-shaped
	// request config, as v5's config() does.
	const [ , sessionConfig ] = await Promise.all( [
		loadGoogleSdk( settings.sdk_url ),
		session.getGooglePayConfig(),
	] );

	if ( renderIsObsolete( overrides ) ) {
		container.remove();
		return;
	}

	const requiresShipping =
		overrides.requiresShipping ?? methodShippingRequired( config, context );
	const shipping = createShippingController( { config } );

	const clientOptions = { environment: settings.environment };

	if ( requiresShipping ) {
		clientOptions.paymentDataCallbacks = buildPaymentDataCallbacks( {
			config,
			currencyCode: config.currency,
			countryCode: config.merchant_country,
			shipping,
		} );
	}

	const client = new window.google.payments.api.PaymentsClient(
		clientOptions
	);

	const { result } = await client.isReadyToPay(
		buildReadyToPayRequest( sessionConfig )
	);
	if ( renderIsObsolete( overrides ) ) {
		container.remove();
		return;
	}

	if ( ! result ) {
		// Leave the wrapper empty again so a later render can retry. As a
		// gateway this also leaves the row hidden, which is the point of
		// printing it hidden: an ineligible browser is never offered it.
		container.remove();
		overrides.onUnavailable?.();
		return;
	}

	revealWalletGateway( gateway, config );

	const spinner = hasJQuery() ? Spinner.fullPage() : null;
	let paying = false;

	/**
	 * Opens the payment sheet and pays with what it returns.
	 *
	 * @return {Promise<void>} Resolves once the payment finished or failed.
	 */
	async function pay() {
		// Google fires onClick per tap, so a double tap opens two sheets.
		if ( paying ) {
			return;
		}
		paying = true;

		// Claims the surface's express UI; onSheetClosed() releases it again.
		overrides.onClick?.();

		try {
			// Resolved before the sheet opens: the sheet must display the same
			// total that createOrder() then charges.
			const { total, purchaseUnits } = await resolveContextTotal(
				config,
				context
			);

			const paymentData = await client.loadPaymentData(
				buildPaymentDataRequest( sessionConfig, {
					countryCode: config.merchant_country,
					currencyCode: config.currency,
					total,
					requiresShipping,
					countries: methodShippingCountries( config ),
				} )
			);

			// Only after the sheet closes, so it is not covered.
			spinner?.block();

			// Before the order exists, because it is built from the WC customer
			// rather than the address posted with the approval. Throws rather
			// than charge a total the sheet never showed.
			if ( requiresShipping ) {
				await shipping.commit(
					googlePayWcShippingAddress( paymentData ),
					googlePayWcBillingAddress( paymentData )
				);
			}

			await payWithSession( {
				config,
				context,
				session,
				fundingSource: methodFundingSource( method ),
				// Only when it is its own row: on the express path the order
				// belongs to the PayPal gateway, as Venmo's and Pay Later's do,
				// so this stays undefined and the endpoints apply that default.
				paymentMethod: gateway?.id,
				purchaseUnits,
				confirmData: {
					paymentMethodData: paymentData.paymentMethodData,
				},
				contact: {
					payer: googlePayPayer( paymentData ),
					shippingAddress: googlePayShippingAddress( paymentData ),
					shippingRateId: shipping.current()?.selectedId,
				},
			} );
		} catch ( error ) {
			// A dismissed sheet is not a failure to report, but one that priced
			// shipping has already written to the real cart and pinned its rate.
			if ( error?.statusCode === 'CANCELED' ) {
				if ( requiresShipping ) {
					await releaseCartShipping( config ).catch( () => {} );
					refreshCartUi( context );
				}
			} else {
				handleError( error );
			}

			// Only here, not in `finally`: a successful payment has already
			// started the redirect.
			overrides.onSheetClosed?.();
		} finally {
			paying = false;
			spinner?.unblock();
		}
	}

	// renderMethod() only reaches this bridge when PHP styled this context.
	const styles = walletButtonStyle( settings.styles[ context ] );

	// Google's buttonRadius is an integer, where the block control is unitless.
	const borderRadius =
		overrides.borderRadius === undefined
			? styles.borderRadius
			: Number( overrides.borderRadius );

	const button = client.createButton( {
		buttonColor: styles.color,
		buttonType: styles.type,
		buttonLocale: styles.language,
		buttonRadius: borderRadius,
		buttonSizeMode: 'fill',
		// createButton() styles from one method; the request carries them all.
		allowedPaymentMethods: [ sessionConfig.allowedPaymentMethods[ 0 ] ],
		onClick: pay,
	} );

	// 'fill' widens the button to its container, but Google's own min-width
	// overflows a narrow column such as the blocks express row. Set inline so
	// it beats their stylesheet.
	const sized = button.matches?.( GOOGLE_BUTTON_SELECTOR )
		? button
		: button.querySelector?.( GOOGLE_BUTTON_SELECTOR );
	sized?.style.setProperty( 'min-width', '0' );

	container.appendChild( button );
}
