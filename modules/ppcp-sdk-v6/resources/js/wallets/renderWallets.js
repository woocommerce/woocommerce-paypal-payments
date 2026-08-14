/**
 * The single seam boot.js calls to render wallet buttons.
 *
 * A second wallet plugs in here without touching the render loop again.
 *
 * @package
 */

import { FundingSources } from '../utils/fundingSources';
import { renderGooglePay } from './googlePay';
import { walletConfig } from './walletRegistry';

/**
 * Renders every wallet that is enabled and eligible for this target.
 *
 * Rejections are left to propagate: renderAll() already logs them, and the
 * PayPal buttons are in the DOM before this runs, so a failing wallet costs
 * only its own button. A render failure is not reported to the shopper, who
 * did not ask for anything yet.
 *
 * @param {Object} args          - The render inputs.
 * @param {Object} args.wrapper  - The button wrapper to render into.
 * @param {Object} args.config   - The wc_ppcp_sdk_v6 config object.
 * @param {string} args.context  - The page context.
 * @param {Object} args.sessions - The payment sessions, keyed by method.
 * @return {Promise<void>} Resolves once the wallets have rendered.
 */
export async function renderWallets( { wrapper, config, context, sessions } ) {
	const settings = walletConfig( config, FundingSources.GOOGLEPAY );
	const session = sessions[ FundingSources.GOOGLEPAY ];
	if ( ! settings?.enabled || ! session ) {
		return;
	}

	// Set only on classic checkout, where Google Pay is a payment-method row
	// rather than an express button.
	const gateway = settings.gateway;

	let target = wrapper;
	if ( gateway ) {
		// One row, one button: the mini-cart target would otherwise resolve the
		// same container and render a second one into it.
		if ( 'checkout' !== context ) {
			return;
		}

		// Absent means the row is not offered on this page, so there is nothing
		// to render into. Already populated means an earlier pass rendered the
		// button; unlike the express wrapper this container is not emptied
		// first, so boot.js's check cannot cover it.
		target = document.querySelector( gateway.wrapper );
		if ( ! target || target.childElementCount > 0 ) {
			return;
		}
	}

	await renderGooglePay( {
		wrapper: target,
		config,
		context,
		session,
		gateway,
	} );
}
