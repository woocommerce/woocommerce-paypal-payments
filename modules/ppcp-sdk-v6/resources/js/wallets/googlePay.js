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
import { hasJQuery } from '../utils/api';
import { handleError } from '../utils/errorHandler';
import { loadGoogleSdk } from '../utils/scriptLoaders';
import {
	buildPaymentDataRequest,
	buildReadyToPayRequest,
} from './googlePayRequest';
import { googlePayPayer, googlePayShippingAddress } from './walletContacts';
import { payWithWallet } from './walletPayment';
import { resolveWalletTotal } from './walletTotal';

/**
 * Renders the Google Pay button and wires its click to a payment.
 *
 * @param {Object} args         - The render inputs.
 * @param {Object} args.wrapper - The button wrapper to render into.
 * @param {Object} args.config  - The wc_ppcp_sdk_v6 config object.
 * @param {string} args.context - The page context.
 * @param {Object} args.session - The v6 Google Pay payment session.
 * @return {Promise<void>} Resolves once the button is rendered, or skipped.
 */
export async function renderGooglePay( { wrapper, config, context, session } ) {
	// Claim the wrapper before the first await. boot.js skips wrappers that
	// already have children, so a concurrent render no-ops instead of adding a
	// second button, and an eligibility wipe landing mid-load leaves this
	// filling a detached node. It also gives buttonSizeMode 'fill' its own
	// block-level box rather than the wrapper it shares with the PayPal
	// buttons.
	const container = document.createElement( 'div' );
	wrapper.appendChild( container );

	await loadGoogleSdk( config.google_pay.sdk_url );

	// Sequential, not competing: the first fetches PayPal's config, the second
	// shapes it into a Google request and throws when passed nothing.
	const sessionConfig = session.formatConfigForPaymentRequest(
		await session.getGooglePayConfig()
	);

	const client = new window.google.payments.api.PaymentsClient( {
		environment: config.google_pay.environment,
	} );

	const { result } = await client.isReadyToPay(
		buildReadyToPayRequest( sessionConfig )
	);
	if ( ! result ) {
		// Leave the wrapper empty again so a later render can retry.
		container.remove();
		return;
	}

	const spinner = hasJQuery() ? Spinner.fullPage() : null;
	let paying = false;

	/**
	 * Opens the payment sheet and pays with what it returns.
	 *
	 * @return {Promise<void>} Resolves once the payment finished or failed.
	 */
	async function pay() {
		// Google fires onClick per tap and v5 guards nothing, so every tap
		// there re-opens the sheet.
		if ( paying ) {
			return;
		}
		paying = true;

		try {
			// Resolved before the sheet opens: the sheet must display the same
			// total that createOrder() then charges.
			const { total, purchaseUnits } = await resolveWalletTotal(
				config,
				context
			);

			const paymentData = await client.loadPaymentData(
				buildPaymentDataRequest( sessionConfig, {
					countryCode: config.buyer_country,
					currencyCode: config.currency,
					total,
				} )
			);

			// The sheet is closed by now, so nothing can be reported into it:
			// failures below surface as WooCommerce notices behind a spinner.
			spinner?.block();

			await payWithWallet( {
				config,
				context,
				session,
				fundingSource: 'googlepay',
				purchaseUnits,
				confirmData: {
					paymentMethodData: paymentData.paymentMethodData,
				},
				contact: {
					payer: googlePayPayer( paymentData ),
					shippingAddress: googlePayShippingAddress( paymentData ),
				},
			} );
		} catch ( error ) {
			// The buyer dismissing the sheet is not a failure to report.
			if ( error?.statusCode !== 'CANCELED' ) {
				handleError( error );
			}
		} finally {
			paying = false;
			spinner?.unblock();
		}
	}

	const styles = config.google_pay.styles?.[ context ] || {};

	container.appendChild(
		client.createButton( {
			buttonColor: styles.color,
			buttonType: styles.type,
			buttonLocale: styles.language,
			buttonRadius: styles.borderRadius,
			buttonSizeMode: 'fill',
			// The button only needs the base card method, as in v5.
			allowedPaymentMethods: [ sessionConfig.allowedPaymentMethods[ 0 ] ],
			onClick: pay,
		} )
	);
}
