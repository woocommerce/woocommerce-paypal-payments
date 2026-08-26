/**
 * The single seam boot.js calls to render wallet buttons.
 *
 * @package
 */

import { FundingSources } from '../utils/fundingSources';
import { renderGooglePay } from './googlePay';
import { renderApplePay } from './applePay';
import { walletConfig } from './walletRegistry';

/**
 * The render function for each wallet.
 *
 * Kept out of walletRegistry.js, which holds the rest of the wallet description:
 * boot.js and sdkLoader.js read that module long before anything renders, so it
 * stays import-free rather than dragging a bridge in behind it.
 */
const RENDERERS = {
	[ FundingSources.GOOGLEPAY ]: renderGooglePay,
	[ FundingSources.APPLEPAY ]: renderApplePay,
};

// The pages that print a payment-method list a wallet can own a row in. PHP
// mirrors this and already refuses a gateway elsewhere.
const CONTEXTS_WITH_GATEWAY_ROWS = [ 'checkout', 'pay-now' ];

/**
 * Renders every wallet that is switched on and has somewhere to render.
 *
 * The wallets are started together rather than in sequence, so a slow (or broken)
 * one does not hold up the others. Rejections are left to propagate: renderAll()
 * already logs them, and the PayPal buttons are in the DOM before this runs, so a
 * failing wallet costs only its own button. A render failure is not reported to the
 * shopper, who did not ask for anything yet.
 *
 * @param {Object} args          - The render inputs.
 * @param {Object} args.wrapper  - The button wrapper to render into.
 * @param {Object} args.config   - The wc_ppcp_sdk_v6 config object.
 * @param {string} args.context  - The page context.
 * @param {Object} args.sessions - The payment sessions, keyed by method.
 * @return {Promise<void>} Resolves once the wallets have rendered.
 */
export async function renderWallets( { wrapper, config, context, sessions } ) {
	await Promise.all(
		Object.keys( RENDERERS ).map( ( method ) =>
			renderWallet( method, { wrapper, config, context, sessions } )
		)
	);
}

/**
 * Resolves the element a wallet renders into, or null when it must not render.
 *
 * @param {Object}  wrapper - The shared express-button wrapper.
 * @param {?Object} gateway - The wallet's { id, wrapper }, when it is its own row.
 * @param {string}  context - The page context.
 * @return {?Object} The target element, or null to skip this target.
 */
function resolveTarget( wrapper, gateway, context ) {
	if ( ! gateway ) {
		return wrapper;
	}

	// One row, one button: on a page with rows, the mini-cart target would
	// otherwise resolve the same container and render a second button into it.
	if ( ! CONTEXTS_WITH_GATEWAY_ROWS.includes( context ) ) {
		return null;
	}

	// Absent means the row is not offered here. Populated means an earlier pass
	// already rendered the button: this container is never emptied first, so
	// boot.js's own emptiness check cannot cover it.
	const target = document.querySelector( gateway.wrapper );
	if ( ! target || target.childElementCount > 0 ) {
		return null;
	}

	return target;
}

/**
 * Renders one wallet, if it is switched on and has a session.
 *
 * @param {string} method - The wallet's funding source.
 * @param {Object} args   - The render inputs, as for renderWallets().
 * @return {Promise<void>} Resolves once rendered, or skipped.
 */
async function renderWallet( method, { wrapper, config, context, sessions } ) {
	const settings = walletConfig( config, method );
	const session = sessions[ method ];

	// `enabled` is true as soon as *any* context wants the wallet, so the
	// per-context answer is whether PHP sent styles for this one. Gating on
	// `enabled` put an unstyled button on every other target.
	if ( ! session || ! settings?.styles?.[ context ] ) {
		return;
	}

	// PHP sets this on classic checkout alone, where the wallet is a
	// payment-method row rather than an express button.
	const gateway = settings.gateway;

	const target = resolveTarget( wrapper, gateway, context );
	if ( ! target ) {
		return;
	}

	await RENDERERS[ method ]( {
		// Passed down so a bridge never has to name itself: the funding source
		// and the config subtree both follow from it.
		method,
		wrapper: target,
		config,
		context,
		session,
		gateway,
	} );
}
