/**
 * PayPal SDK v6 Bootstrap.
 *
 * Loads the SDK, checks eligibility, creates payment sessions,
 * and renders Web Component buttons.
 *
 * Renders into independent targets: the page-context wrapper (product,
 * cart, checkout) and the mini-cart wrapper, which can coexist on the
 * same page. The SDK instance and client token are created once; the
 * amount-sensitive eligibility is refreshed when the cart total changes.
 *
 * @package
 */

import { loadSdkV6 } from './sdkLoader';
import { onSdkInstanceChange } from './tokenRefresh';
import { checkEligibility, checkSubscriptionEligibility } from './eligibility';
import {
	createSession,
	SUPPORTED_METHODS as METHODS,
} from './sessions/createSession';
import { renderButtons } from './components/buttonRenderer';
import { renderMethods } from './methods/renderMethods';
import {
	isMethodEnabled,
	methodConfig,
	MERCHANT_PRESENTED_METHODS,
} from './methods/methodRegistry';
import { FundingSources } from './utils/fundingSources';
import {
	createOrder,
	createSubscription,
	fetchCartTotal,
} from './endpointsAdapter';
import {
	createFreeTrialPayPalSession,
	createVaultSetupToken,
} from './sessions/freeTrialSave';
import { createSubscriptionSession } from './sessions/subscriptionSession';
import {
	isNativeSubscription,
	subscriptionButtonAllowed,
} from './utils/nativeSubscription';
import { initCardFields } from './cardFields/renderer';
import { initCardButton } from './cardButton/renderCardButton';
import { hasJQuery } from './utils/api';
import { watchViewedTotal } from './utils/viewedTotal';
import { watchProductAmount } from './messages/productAmount';
import { initProductButtonGate } from './utils/productButtonGate';
import { setErrorLabels } from './utils/errorHandler';
import { isFreeTrialCart } from './utils/freeTrial';
import { onBlockCartTotalChange } from './utils/blockCartTotal';
import {
	placeExpressButtons,
	setExpressButtonsFailed,
} from './methods/gatewayPlacement';
import { debounce } from '@ppcp-blocks/Helper/debounce';
import {
	initMessages,
	renderMessages,
	updateMessagesAmount,
} from './messages/renderer';

// The native WC submit button, replaced by the v6 buttons while the PayPal
// gateway is selected and left as the submit for every other method.
const PLACE_ORDER_SELECTOR = '#place_order';

const ELIGIBILITY_REFRESH_DEBOUNCE_MS = 300;

( function ( config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	setErrorLabels( config.labels );

	// A $0 free-trial subscription is vaulted through the PayPal save flow, which
	// needs a form to submit afterwards.
	const FREE_TRIAL_CONTEXTS = [ 'checkout', 'pay-now' ];
	function isFreeTrialSave( context ) {
		return (
			isFreeTrialFlow( context ) &&
			FREE_TRIAL_CONTEXTS.includes( context )
		);
	}

	/**
	 * Whether the free-trial save flow applies to a context. A native PayPal
	 * Subscription never takes it: its plan carries the trial.
	 *
	 * @param {string} context - The target context.
	 * @return {boolean} True when a $0 cart is vaulted instead of purchased.
	 */
	function isFreeTrialFlow( context ) {
		return (
			! isNativeSubscription( config, context ) &&
			isFreeTrialCart( config, amount )
		);
	}

	/**
	 * Submits the checkout (or pay-for-order) form after the free-trial save
	 * flow has stored the token, so the gateway places the $0 order.
	 */
	function submitCheckoutForm() {
		if ( hasJQuery() ) {
			const form = jQuery( 'form.checkout, form#order_review' );
			if ( form.length ) {
				form.trigger( 'submit' );
				return;
			}
		}
		document.querySelector( PLACE_ORDER_SELECTOR )?.click();
	}

	/**
	 * Advanced Card Fields (ACDC): mounts into the existing WC card-form inputs
	 * rather than a button wrapper, so it runs outside the render loop below.
	 * Deferred like renderAll(), since it also queries checkout-form DOM.
	 */
	function initCardFieldsSafely() {
		initCardFields( config, () => amount ).catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.error( '[PPCP SDK v6]', error );
		} );
	}

	/**
	 * Renders on its own pass, not via renderTarget(): with the checkout smart
	 * button switched off there is no express wrapper to draw into, and BCDC
	 * can still be on.
	 */
	function initCardButtonSafely() {
		initCardButton( config, ensureSessions ).catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.error( '[PPCP SDK v6]', error );
		} );
	}

	// PHP only prints wrappers for enabled locations, so targets are selected by
	// wrapper presence at render time.
	const targets = [];
	if ( config.page_context ) {
		targets.push( {
			context: config.page_context,
			wrapperSelector: config.wrapper,
		} );
	}
	targets.push( {
		context: 'mini-cart',
		wrapperSelector: config.mini_cart_wrapper,
	} );

	const sdkPageType = config.page_context || 'mini-cart';

	const messagesFollowCartTotal = 'product' !== config.page_context;

	let amount = config.amount;
	// Kept apart from amount, which is the product price on a product page.
	let cartTotal = null;
	let refreshPromise = Promise.resolve();
	let eligibilityPromise = null;
	const sessionPromises = {};
	const renderPromises = {};

	function ensureEligibility() {
		if ( ! eligibilityPromise ) {
			eligibilityPromise = ( async () => {
				const sdk = await loadSdkV6( config, sdkPageType );
				return checkEligibility( sdk, {
					currencyCode: config.currency,
					countryCode: config.buyer_country,
					amount,
				} );
			} )().catch( ( error ) => {
				eligibilityPromise = null;
				throw error;
			} );
		}
		return eligibilityPromise;
	}

	function ensureSessions( context ) {
		if ( ! sessionPromises[ context ] ) {
			sessionPromises[ context ] = createSessions( context ).catch(
				( error ) => {
					delete sessionPromises[ context ];
					throw error;
				}
			);
		}
		return sessionPromises[ context ];
	}

	/**
	 * Creates the payment sessions for one render target.
	 *
	 * @param {string} context - The target context.
	 * @return {Promise<Object>} Sessions keyed by method, plus payLaterDetails.
	 */
	async function createSessions( context ) {
		const sdk = await loadSdkV6( config, sdkPageType );

		// Native PayPal Subscription: only PayPal has a subscription session, and
		// its button creates the subscription (see renderInto). Where it may not
		// render, the context offers no PayPal button at all.
		if ( isNativeSubscription( config, context ) ) {
			if ( ! subscriptionButtonAllowed( config, context ) ) {
				return { payLaterDetails: null, map: {} };
			}

			const eligible = await checkSubscriptionEligibility( sdk, {
				currencyCode: config.currency,
			} );

			return {
				payLaterDetails: null,
				map: eligible
					? {
							[ FundingSources.PAYPAL ]:
								createSubscriptionSession(
									sdk,
									config,
									context
								),
					  }
					: {},
			};
		}

		// Free trial: only the PayPal save session, whose approval stores a token
		// and submits the checkout form (see renderTarget's createOrderForFunding).
		if ( isFreeTrialSave( context ) ) {
			return {
				payLaterDetails: null,
				map: {
					[ FundingSources.PAYPAL ]: createFreeTrialPayPalSession(
						sdk,
						config,
						{ onComplete: submitCheckoutForm }
					),
				},
			};
		}

		const eligibility = await ensureEligibility();

		const sessions = {
			payLaterDetails: eligibility.payLaterDetails,
			map: {},
		};

		for ( const method of METHODS ) {
			if ( ! eligibility[ method ] ) {
				continue;
			}

			// A wallet's SDK component is only requested when the wallet is
			// enabled, so without it the session factory does not exist and
			// calling it would take every button on the page down.
			if (
				MERCHANT_PRESENTED_METHODS.includes( method ) &&
				! isMethodEnabled( config, method )
			) {
				continue;
			}

			// Same reason as the wallets: paypal-guest-payments is only
			// requested where the card button renders.
			if ( method === FundingSources.CARD && ! config.card_button?.row ) {
				continue;
			}

			// Venmo renders only where the merchant offers it; without any such
			// location, venmo-payments is not requested.
			if (
				method === FundingSources.VENMO &&
				! config.venmo_button?.[ context ]
			) {
				continue;
			}

			sessions.map[ method ] = createSession(
				sdk,
				method,
				config,
				context
			);
		}

		return sessions;
	}

	/**
	 * Renders buttons into a target if its wrapper is present and empty.
	 *
	 * The SDK and client token are only loaded once a wrapper exists, so
	 * pages without buttons never hit the token endpoint. Wrappers that
	 * still contain buttons are left alone (WC AJAX updates that replace
	 * the surrounding DOM deliver the wrapper empty again).
	 *
	 * A page-context pass that leaves the wrapper empty, whether it threw or found
	 * nothing to render, hands PayPal's row back to "Place order".
	 *
	 * @param {Object} target - The render target.
	 */
	async function renderTarget( target ) {
		const wrapper = document.querySelector( target.wrapperSelector );
		if ( ! wrapper ) {
			return;
		}

		// The block mini-cart keeps its wrapper when the cart drops to $0 in place.
		if ( target.context === 'mini-cart' && parseFloat( cartTotal ) <= 0 ) {
			wrapper.replaceChildren();
			return;
		}

		if ( wrapper.childElementCount > 0 ) {
			return;
		}

		// A free trial can only be saved where a checkout form follows; elsewhere
		// (e.g. the block mini-cart) the wrapper stays empty.
		if (
			isFreeTrialFlow( target.context ) &&
			! FREE_TRIAL_CONTEXTS.includes( target.context )
		) {
			return;
		}

		try {
			await renderInto( wrapper, target );
		} finally {
			if ( target.context === config.page_context ) {
				setExpressButtonsFailed( wrapper.childElementCount === 0 );
			}
		}
	}

	/**
	 * Builds the function whose promise starts a session: the subscription to
	 * approve, the free trial's setup token, or else the order to pay.
	 *
	 * @param {string} context       - The target context.
	 * @param {string} fundingSource - The funding source of the button.
	 * @return {() => Promise<Object>} Resolves to what session.start() expects.
	 */
	function startPromiseFor( context, fundingSource ) {
		if ( isNativeSubscription( config, context ) ) {
			return () => createSubscription( config, context );
		}

		if (
			isFreeTrialSave( context ) &&
			fundingSource === FundingSources.PAYPAL
		) {
			return () => createVaultSetupToken( config );
		}

		return () => createOrder( config, context, fundingSource );
	}

	/**
	 * Renders a target's buttons into its wrapper.
	 *
	 * Wallets render after renderButtons(), which empties the wrapper first,
	 * and they append rather than replace.
	 *
	 * @param {HTMLElement} wrapper - The target's wrapper.
	 * @param {Object}      target  - The render target.
	 */
	async function renderInto( wrapper, target ) {
		const { map, payLaterDetails } = await ensureSessions( target.context );

		renderButtons( {
			wrapper,
			sessions: map,
			styles: config.button_styles[ target.context ] || {},
			createOrderForFunding: ( fundingSource ) =>
				startPromiseFor( target.context, fundingSource ),
			payLaterDetails,
			payLaterEnabled: Boolean(
				config.pay_later_button?.[ target.context ]
			),
			buttonType: isNativeSubscription( config, target.context )
				? 'subscribe'
				: undefined,
		} );

		// Wallets cannot carry a native subscription.
		if ( isNativeSubscription( config, target.context ) ) {
			return;
		}

		await renderMethods( {
			wrapper,
			config,
			context: target.context,
			sessions: map,
		} );
	}

	/**
	 * Queues a render for a target, so passes never overlap.
	 *
	 * The emptiness check in renderTarget() straddles an await, so two
	 * overlapping passes would both pass it; the later one's renderButtons()
	 * would then wipe the earlier one's wallet button while that render was
	 * still in flight, leaving it to finish into a detached node.
	 *
	 * Each call still gets its own pass rather than sharing the in-flight one,
	 * because refreshEligibility() blanks the wrapper before re-rendering and
	 * must not be handed back a render that started before the flip. A pass
	 * that finds the wrapper already populated returns immediately.
	 *
	 * @param {Object} target - The render target.
	 * @return {Promise<void>} Resolves once this target's pass is done.
	 */
	function render( target ) {
		const pending = renderPromises[ target.context ] || Promise.resolve();

		// Swallowed so one failed pass does not block every later one.
		renderPromises[ target.context ] = pending
			.catch( () => {} )
			.then( () => renderTarget( target ) );

		return renderPromises[ target.context ];
	}

	function renderAll() {
		for ( const target of targets ) {
			render( target ).catch( ( error ) => {
				// eslint-disable-next-line no-console
				console.error( '[PPCP SDK v6]', error );
			} );
		}
	}

	/**
	 * Re-checks eligibility with a fresh cart total and re-renders.
	 *
	 * Pay Later eligibility is amount-sensitive, so cached sessions go
	 * stale when the cart total changes. The SDK instance and client
	 * token stay cached; only eligibility and sessions are rebuilt, and
	 * buttons are redrawn only when the eligible method set changed.
	 */
	async function refreshEligibility() {
		const previous = eligibilityPromise
			? await eligibilityPromise.catch( () => null )
			: null;

		// Read before the new total lands, to compare against it below.
		const wasFreeTrial = isFreeTrialCart( config, amount );

		cartTotal = ( await fetchCartTotal( config ) ) || cartTotal;
		amount = cartTotal || amount;
		if ( messagesFollowCartTotal ) {
			updateMessagesAmount( amount );
		}
		eligibilityPromise = null;
		const current = await ensureEligibility();

		// The buttons capture the free-trial choice (order vs setup token) in their
		// session, so a total crossing zero needs a redraw even when the eligible
		// set is identical.
		const freeTrialChanged =
			wasFreeTrial !== isFreeTrialCart( config, amount );

		const changed =
			! previous ||
			freeTrialChanged ||
			METHODS.some( ( m ) => previous[ m ] !== current[ m ] );

		if ( changed ) {
			discardSessions(
				targets.map( ( target ) => target.wrapperSelector )
			);
		}

		renderAll();
	}

	/**
	 * Serialises refreshEligibility() passes, same chain idiom as render().
	 *
	 * It is needed because the debounce coalesces events but cannot stop one pass
     * starting while another awaits the network, and overlapping passes may leave the
     * buttons on the older result.
	 *
	 * @return {Promise<void>} Resolves once this pass is done.
	 */
	function queueRefreshEligibility() {
		refreshPromise = refreshPromise
			.catch( () => {} )
			.then( () => refreshEligibility() );

		return refreshPromise.catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.error( '[PPCP SDK v6]', error );
		} );
	}

	const refreshEligibilityDebounced = debounce(
		queueRefreshEligibility,
		ELIGIBILITY_REFRESH_DEBOUNCE_MS
	);

	function initMessagesSafely() {
		initMessages( config, sdkPageType ).catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.error( '[PPCP SDK v6]', error );
		} );
	}

	/**
	 * A product page prices the product on display, not the cart, so its
	 * message tracks the product form — quantity and variation — which it
	 * reads from the page.
	 *
	 * Apple Pay prices the same product through the cart-simulation endpoint,
	 * because a sheet total has to match what is charged. A message does not,
	 * so it is not worth a request per quantity change; a store that wants the
	 * simulated figure here can filter it back on.
	 */
	function trackProductTotal() {
		if ( 'product' !== config.page_context ) {
			return;
		}

		if ( config.messages?.use_cart_simulation ) {
			watchViewedTotal( config, 'product' ).subscribe(
				updateMessagesAmount
			);

			return;
		}

		watchProductAmount( config, updateMessagesAmount );
	}

	/**
	 * A render pass skips a wrapper that is not empty.
	 *
	 * @param {Array<?string>} wrapperSelectors - The wrappers to empty.
	 */
	function discardSessions( wrapperSelectors ) {
		for ( const key of Object.keys( sessionPromises ) ) {
			delete sessionPromises[ key ];
		}

		for ( const selector of wrapperSelectors.filter( Boolean ) ) {
			const wrapper = document.querySelector( selector );
			if ( wrapper ) {
				wrapper.innerHTML = '';
			}
		}
	}

	function renderWithNewSessions() {
		discardSessions( [
			...targets.map( ( target ) => target.wrapperSelector ),
			...MERCHANT_PRESENTED_METHODS.map(
				( method ) => methodConfig( config, method )?.gateway?.wrapper
			),
			config.card_button?.row && config.card_button.wrapper,
		] );

		renderAll();
		initCardButtonSafely();
	}

	function initialRender() {
		renderAll();
		initCardFieldsSafely();
		initCardButtonSafely();
		initMessagesSafely();
		trackProductTotal();
		initProductButtonGate( config );
		placeExpressButtons( config );
		onSdkInstanceChange( renderWithNewSessions );
		onBlockCartTotalChange( refreshEligibilityDebounced );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initialRender );
	} else {
		initialRender();
	}

	if ( hasJQuery() ) {
		// DOM-replacing updates: wrappers arrive empty and need re-rendering.
		jQuery( document.body ).on(
			'updated_checkout wc_fragments_loaded wc_fragments_refreshed',
			() => {
				renderAll();

				// A separate pass: messages target .ppcp-messages placeholders,
				// not the button wrappers refreshEligibility() blanks.
				renderMessages( config, sdkPageType ).catch( ( error ) => {
					// eslint-disable-next-line no-console
					console.error( '[PPCP SDK v6]', error );
				} );
			}
		);

		// The same DOM replacement rebuilds the card button's row and restores
		// the hide-style PHP printed, so it needs rendering and revealing again.
		jQuery( document.body ).on( 'updated_checkout', initCardButtonSafely );

		// Total-changing updates: eligibility must be re-checked too, and the
		// message re-priced.
		jQuery( document.body ).on(
			'updated_cart_totals added_to_cart removed_from_cart updated_checkout',
			refreshEligibilityDebounced
		);
	}
} )( window.wc_ppcp_sdk_v6 );
