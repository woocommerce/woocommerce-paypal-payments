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
import { renderWallets } from './wallets/renderWallets';
import { isWalletEnabled, WALLET_METHODS } from './wallets/walletRegistry';
import { FundingSources } from './utils/fundingSources';
import { createOrder, fetchCartTotal } from './endpointsAdapter';
import { initCardFields } from './cardFields/renderer';
import { initCardButton } from './cardButton/renderCardButton';
import { hasJQuery } from './utils/api';
import { setErrorLabels } from './utils/errorHandler';
import { setVisible } from '@ppcp-button/Helper/Hiding';

// The native WC submit button, labelled "Proceed to PayPal" for the PayPal
// gateway. It is replaced by the v6 PayPal buttons while the PayPal gateway is
// selected, but stays as the submit for cards and every other method.
const PLACE_ORDER_SELECTOR = '#place_order';
const PAYPAL_GATEWAY_ID = 'ppcp-gateway';

( function ( config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	setErrorLabels( config.labels );

	/**
	 * Advanced Card Fields (ACDC): independent of the button render loop
	 * below — it mounts into the existing WC card-form inputs rather than
	 * a button wrapper, and only actually does anything when the card
	 * gateway is enabled on this (checkout) page. Deferred the same way
	 * as renderAll(), since it also queries checkout-form DOM elements.
	 */
	function initCardFieldsSafely() {
		initCardFields( config ).catch( ( error ) => {
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

	// The page-context and mini-cart wrappers are independent render
	// targets; PHP only prints wrappers for enabled locations, so target
	// selection is gated by wrapper presence at render time.
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

	let amount = config.amount;
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
				WALLET_METHODS.includes( method ) &&
				! isWalletEnabled( config, method )
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
			createOrderForFunding: ( fundingSource ) => () =>
				createOrder( config, target.context, fundingSource ),
			payLaterDetails,
		} );

		await renderWallets( {
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

		amount = ( await fetchCartTotal( config ) ) || amount;
		eligibilityPromise = null;
		const current = await ensureEligibility();

		const changed =
			! previous ||
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
	 * Hides the native WC "Proceed to PayPal" button while the PayPal gateway
	 * is selected — the v6 PayPal buttons stand in for it — and restores it for
	 * cards (whose flow submits through it) and every other method. Re-run on
	 * updated_checkout / payment_method_selected because WC rebuilds the
	 * #payment DOM (and this inline style) on each update.
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
		const isPayPalGateway = selected === PAYPAL_GATEWAY_ID;

		setVisible( PLACE_ORDER_SELECTOR, ! isPayPalGateway, true );
	}

	function initialRender() {
		renderAll();
		initCardFieldsSafely();
		initCardButtonSafely();
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
			renderAll
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

		// Total-changing updates: eligibility must be re-checked too.
		jQuery( document.body ).on(
			'updated_cart_totals added_to_cart removed_from_cart',
			() => {
				refreshEligibility().catch( ( error ) => {
					// eslint-disable-next-line no-console
					console.error( '[PPCP SDK v6]', error );
				} );
			}
		);
	}
} )( window.wc_ppcp_sdk_v6 );
