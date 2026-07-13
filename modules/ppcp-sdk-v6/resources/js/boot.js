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
import { createSession } from './sessions/createSession';
import { renderButtons } from './components/buttonRenderer';
import { createOrder, fetchCartTotal } from './endpointsAdapter';
import { setErrorLabels } from './utils/errorHandler';

( function ( config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	setErrorLabels( config.labels );

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
		for ( const method of [ 'paypal', 'venmo', 'paylater' ] ) {
			if ( eligibility[ method ] ) {
				sessions.map[ method ] = createSession(
					sdk,
					method,
					config,
					context
				);
			}
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
	 * @param {Object} target - The render target.
	 */
	async function render( target ) {
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

		const methods = [ 'paypal', 'venmo', 'paylater' ];
		const changed =
			! previous ||
			methods.some( ( m ) => previous[ m ] !== current[ m ] );

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

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', renderAll );
	} else {
		renderAll();
	}

	if ( typeof jQuery !== 'undefined' ) {
		// DOM-replacing updates: wrappers arrive empty and need re-rendering.
		jQuery( document.body ).on(
			'updated_checkout wc_fragments_loaded wc_fragments_refreshed',
			renderAll
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
