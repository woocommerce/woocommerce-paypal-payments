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
import { checkEligibility } from './eligibility';
import {
	createSession,
	SUPPORTED_METHODS as METHODS,
} from './sessions/createSession';
import { renderButtons } from './components/buttonRenderer';
import { renderMethods } from './methods/renderMethods';
import { isMethodEnabled, MERCHANT_PRESENTED_METHODS } from './methods/methodRegistry';
import { FundingSources } from './utils/fundingSources';
import { createOrder, fetchCartTotal } from './endpointsAdapter';
import {
	createFreeTrialPayPalSession,
	createVaultSetupToken,
} from './sessions/freeTrialSave';
import { initCardFields } from './cardFields/renderer';
import { initCardButton } from './cardButton/renderCardButton';
import { hasJQuery } from './utils/api';
import { watchViewedTotal } from './utils/viewedTotal';
import { initProductButtonGate } from './utils/productButtonGate';
import { setErrorLabels } from './utils/errorHandler';
import { isFreeTrialCart } from './utils/freeTrial';
import { setVisible } from '@ppcp-button/Helper/Hiding';
import { debounce } from '@ppcp-blocks/Helper/debounce';
import {
	initMessages,
	renderMessages,
	updateMessagesAmount,
} from './messages/renderer';

// The native WC submit button, replaced by the v6 buttons while the PayPal
// gateway is selected and left as the submit for every other method.
const PLACE_ORDER_SELECTOR = '#place_order';
const PAYPAL_GATEWAY_ID = 'ppcp-gateway';

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
			isFreeTrialCart( config, amount ) &&
			FREE_TRIAL_CONTEXTS.includes( context )
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
			if (
				method === FundingSources.CARD &&
				! config.card_button?.enabled
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
	 * Wallets render after renderButtons(), which empties the wrapper first,
	 * and they append rather than replace.
	 *
	 * @param {Object} target - The render target.
	 */
	async function renderTarget( target ) {
		const wrapper = document.querySelector( target.wrapperSelector );
		if ( ! wrapper || wrapper.childElementCount > 0 ) {
			return;
		}

		const { map, payLaterDetails } = await ensureSessions( target.context );

		renderButtons( {
			wrapper,
			sessions: map,
			styles: config.button_styles[ target.context ] || {},
			createOrderForFunding: ( fundingSource ) =>
				isFreeTrialSave( target.context ) &&
				fundingSource === FundingSources.PAYPAL
					? // Free trial starts the save session with a setup token.
					  () => createVaultSetupToken( config )
					: () => createOrder( config, target.context, fundingSource ),
			payLaterDetails,
			payLaterEnabled: Boolean(
				config.pay_later_button?.[ target.context ]
			),
		} );

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

		amount = ( await fetchCartTotal( config ) ) || amount;
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
			for ( const key of Object.keys( sessionPromises ) ) {
				delete sessionPromises[ key ];
			}
			for ( const target of targets ) {
				const wrapper = document.querySelector(
					target.wrapperSelector
				);
				if ( wrapper ) {
					wrapper.innerHTML = '';
				}
			}
		}

		renderAll();
	}

	/**
	 * Whether a saved PayPal token (not "Use a new payment method") is selected.
	 * Such a payment is charged via the native Place Order button — server-side, or
	 * through the vault component's in-page approval where eligible — not the v6
	 * express button, which would start a new PayPal flow instead.
	 *
	 * @return {boolean} True when a saved ppcp-gateway token is selected.
	 */
	function isSavedPayPalTokenSelected() {
		const checked = document.querySelector(
			'input[name="wc-ppcp-gateway-payment-token"]:checked'
		);
		return Boolean( checked && checked.value && checked.value !== 'new' );
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

	/**
	 * Hides the native WC "Proceed to PayPal" button while the PayPal gateway
	 * is selected with a NEW payment method — the v6 PayPal buttons stand in for
	 * it — and restores it for cards, saved PayPal tokens (charged via Place
	 * Order), and every other method. The v6 express button is hidden for a saved
	 * token so it does not compete with it. Re-run on updated_checkout /
	 * payment_method_selected / token change because WC rebuilds the #payment DOM
	 * (and this inline style) on each update.
	 */
	function syncPlaceOrderButton() {
		if (
			! hasJQuery() ||
			! [ 'checkout', 'pay-now' ].includes( config.page_context )
		) {
			return;
		}

		const selected = document.querySelector(
			'input[name="payment_method"]:checked'
		)?.value;
		const useExpress =
			selected === PAYPAL_GATEWAY_ID && ! isSavedPayPalTokenSelected();

		setVisible( PLACE_ORDER_SELECTOR, ! useExpress, true );
		if ( config.wrapper ) {
			setVisible( config.wrapper, useExpress );
		}
	}

	function initMessagesSafely() {
		initMessages( config, sdkPageType ).catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.error( '[PPCP SDK v6]', error );
		} );
	}

	/**
	 * A product page prices the product on display, not the cart, so its
	 * message tracks the product form — quantity and variation — through
	 * the same watcher Apple Pay reads.
	 */
	function trackProductTotal() {
		if ( 'product' !== config.page_context ) {
			return;
		}

		watchViewedTotal( config, 'product' ).subscribe( updateMessagesAmount );
	}

	function initialRender() {
		renderAll();
		initCardFieldsSafely();
		initCardButtonSafely();
		initMessagesSafely();
		trackProductTotal();
		initProductButtonGate( config );
		syncPlaceOrderButton();
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

		// WC rebuilds #place_order on these too, and the selected method can
		// change without a DOM rebuild, so re-sync the button on both.
		jQuery( document.body ).on(
			'updated_checkout payment_method_selected',
			syncPlaceOrderButton
		);

		// Switching between a saved PayPal token and "new" flips whether the
		// express button or Place Order should show, without a DOM rebuild.
		jQuery( document ).on(
			'change',
			'input[name="wc-ppcp-gateway-payment-token"]',
			syncPlaceOrderButton
		);

		// Total-changing updates: eligibility must be re-checked too, and the
		// message re-priced.
		jQuery( document.body ).on(
			'updated_cart_totals added_to_cart removed_from_cart updated_checkout',
			refreshEligibilityDebounced
		);
	}
} )( window.wc_ppcp_sdk_v6 );
