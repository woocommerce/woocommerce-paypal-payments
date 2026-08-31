/**
 * The single seam boot.js calls to render a method's button.
 *
 * @package
 */

import { FundingSources } from '../utils/fundingSources';
import { renderGooglePay } from '../wallets/googlePay';
import { renderApplePay } from '../wallets/applePay';
import { methodConfig } from './methodRegistry';

/**
 * The render function for each wallet.
 *
 * Kept out of methodRegistry.js, which holds the rest of the wallet description:
 * boot.js and sdkLoader.js read that module long before anything renders, so it
 * stays import-free rather than dragging a bridge in behind it.
 *
 * Key order is stack order: each bridge appends its container before its first
 * await, so the wallets land in the order started here.
 */
const RENDERERS = {
	[ FundingSources.APPLEPAY ]: renderApplePay,
	[ FundingSources.GOOGLEPAY ]: renderGooglePay,
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
export async function renderMethods( { wrapper, config, context, sessions } ) {
	await Promise.all(
		Object.keys( RENDERERS ).map( ( method ) =>
			renderMethod( method, { wrapper, config, context, sessions } )
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
 * Renders one method into an element the caller already owns.
 *
 * For surfaces that place the button themselves rather than letting
 * resolveTarget() find it, such as a framework owning its own container.
 *
 * `overrides` is how such a surface tells a bridge what it knows better:
 *
 * - `height`, `borderRadius`: sizing that beats the merchant's settings.
 * - `requiresShipping`: replaces the per-context answer PHP sent.
 * - `sheetTotal`: a `{ get() }` reader for Apple Pay, which cannot await one.
 * - `sheetContacts`: a `{ get() }` reader over `{ billing, shipping }` Apple
 *   contacts, prefilling the sheet with an address the surface already has.
 * - `isObsolete()`: asked after each await; the surface has torn this render
 *   down, so stop and leave nothing behind.
 * - `onClick()`: the shopper opened the sheet.
 * - `onUnavailable()`: the method decided not to render, so nothing will appear.
 * - `onSheetClosed()`: the sheet closed without paying.
 * - `onRenderFailed( error )`: raised by the surface's container when this
 *   function rejects, never by a bridge.
 *
 * @param {string}  method           - The wallet's funding source.
 * @param {Object}  args             - The render inputs.
 * @param {Object}  args.wrapper     - The element to render into.
 * @param {Object}  args.config      - The wc_ppcp_sdk_v6 config object.
 * @param {string}  args.context     - The page context.
 * @param {Object}  args.session     - The wallet's payment session.
 * @param {?Object} [args.gateway]   - The { id, wrapper } of the payment-method
 *                                   row, when the wallet is its own gateway.
 * @param {Object}  [args.overrides] - Surface-specific overrides, as above.
 * @return {Promise<void>} Resolves once the wallet has rendered, or skipped.
 */
export async function renderMethodInto(
	method,
	{ wrapper, config, context, session, gateway, overrides }
) {
	const render = RENDERERS[ method ];
	if ( ! render ) {
		return;
	}

	// Bridges read their own funding source from `method`.
	await render( {
		method,
		wrapper,
		config,
		context,
		session,
		gateway,
		overrides,
	} );
}

/**
 * Renders one wallet, if it is switched on and has a session.
 *
 * @param {string} method - The wallet's funding source.
 * @param {Object} args   - The render inputs, as for renderMethods().
 * @return {Promise<void>} Resolves once rendered, or skipped.
 */
async function renderMethod( method, { wrapper, config, context, sessions } ) {
	const settings = methodConfig( config, method );
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

	await renderMethodInto( method, {
		wrapper: target,
		config,
		context,
		session,
		gateway,
	} );
}
