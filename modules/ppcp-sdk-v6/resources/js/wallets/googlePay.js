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
import { FundingSources } from '../utils/fundingSources';
import { handleError } from '../utils/errorHandler';
import { loadGoogleSdk } from '../utils/scriptLoaders';
import { revealGateway, syncGatewayVisibility } from './gatewayPlacement';
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
 * @param {?Object} [args.gateway] - The { id, wrapper } of the payment-method
 *                                   row, when the wallet is its own gateway.
 * @return {Promise<void>} Resolves once the button is rendered, or skipped.
 */
export async function renderGooglePay( {
	wrapper,
	config,
	context,
	session,
	gateway,
} ) {
	// Own box, because buttonSizeMode 'fill' cannot share the wrapper with the
	// PayPal buttons. Appended before the first await so boot.js's emptiness
	// check skips a redundant later pass.
	const container = document.createElement( 'div' );
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
		loadGoogleSdk( config.google_pay.sdk_url ),
		session.getGooglePayConfig(),
	] );

	const client = new window.google.payments.api.PaymentsClient( {
		environment: config.google_pay.environment,
	} );

	const { result } = await client.isReadyToPay(
		buildReadyToPayRequest( sessionConfig )
	);
	if ( ! result ) {
		// Leave the wrapper empty again so a later render can retry. As a
		// gateway this also leaves the row hidden, which is the point of
		// printing it hidden: an ineligible browser is never offered it.
		container.remove();
		return;
	}

	if ( gateway ) {
		revealGateway( gateway.id );
		syncGatewayVisibility( gateway.id, gateway.wrapper );
	}

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

			// Only after the sheet closes, so it is not covered.
			spinner?.block();

			await payWithWallet( {
				config,
				context,
				session,
				fundingSource: FundingSources.GOOGLEPAY,
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
			// Defaults as in v5: the settings leave these empty when the
			// merchant never picked a value, and Google rejects an empty
			// buttonLocale.
			buttonColor: styles.color || 'black',
			buttonType: styles.type || 'pay',
			buttonLocale: styles.language || 'en',
			buttonRadius: styles.borderRadius,
			buttonSizeMode: 'fill',
			// The button only needs the base card method, as in v5.
			allowedPaymentMethods: [ sessionConfig.allowedPaymentMethods[ 0 ] ],
			onClick: pay,
		} )
	);
}
