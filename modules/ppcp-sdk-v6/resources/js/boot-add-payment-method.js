/**
 * PayPal SDK v6 Bootstrap for My Account › Add Payment Method.
 *
 * Renders the v6 "save for later" surfaces that replace the v5
 * add-payment-method.js: the PayPal wallet button (createPayPalSavePaymentSession)
 * and the advanced card fields (createCardFieldsSavePaymentSession). The WC
 * "Add payment method" submit button drives the card save; it is hidden while
 * the PayPal method is selected, where the PayPal button is the submit control.
 *
 * @package
 */

import {
	getCurrentPaymentMethod,
	ORDER_BUTTON_SELECTOR,
	PaymentMethods,
} from '@ppcp-button/Helper/CheckoutMethodState';
import { setVisible, setVisibleByClass } from '@ppcp-button/Helper/Hiding';
import { loadSdkV6 } from './sdkLoader';
import { checkVaultEligibility } from './eligibility';
import { createSavePayPalSession } from './sessions/createSaveSession';
import { initCardSaveFields } from './cardFields/saveRenderer';
import { postJson } from './utils/api';
import { handleError, setErrorLabels } from './utils/errorHandler';

( function ( config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	setErrorLabels( config.labels );

	const buttonSelector = config.button.wrapper;

	/**
	 * Requests a PayPal wallet setup token from the existing WC AJAX endpoint.
	 *
	 * The returned promise is passed to session.start() without being awaited
	 * first — awaiting it before the call loses the transient user activation
	 * and breaks the popup. It resolves to a { vaultSetupToken } object,
	 * mirroring the { orderId } shape the one-time session expects.
	 *
	 * @return {Promise<{vaultSetupToken: string}>} The setup token.
	 */
	function createVaultSetupToken() {
		return postJson( config.ajax.create_setup_token ).then( ( data ) => ( {
			vaultSetupToken: data.id,
		} ) );
	}

	/**
	 * Builds the PayPal save button and binds the click handler.
	 *
	 * @param {Object} session - The v6 save payment session.
	 * @return {HTMLElement} The configured button element.
	 */
	function createPayPalButton( session ) {
		const button = document.createElement( 'paypal-button' );
		button.setAttribute( 'type', 'pay' );
		if ( config.button.color_class ) {
			button.className = config.button.color_class;
		}

		button.addEventListener( 'click', async () => {
			try {
				await session.start(
					{ presentationMode: 'auto' },
					createVaultSetupToken()
				);
			} catch ( error ) {
				handleError( error );
			}
		} );

		return button;
	}

	/**
	 * Shows the PayPal button only when PayPal is the selected method and a
	 * button actually rendered; otherwise the WC submit button stays visible
	 * (card save flow, or PayPal ineligible). Replaces the visibility handling
	 * the (now-suppressed) v5 script used to do.
	 */
	function syncVisibility() {
		const isPayPal =
			getCurrentPaymentMethod() === PaymentMethods.PAYPAL;
		// Only hand the submit role to the PayPal button when one actually
		// rendered; otherwise (PayPal ineligible / no wrapper) the buyer must
		// keep the native submit control instead of being left with nothing.
		const hasPayPalButton = Boolean(
			document.querySelector( `${ buttonSelector } paypal-button` )
		);
		const usePayPalButton = isPayPal && hasPayPalButton;
		setVisibleByClass(
			ORDER_BUTTON_SELECTOR,
			! usePayPalButton,
			'ppcp-hidden'
		);
		setVisible( buttonSelector, usePayPalButton );
	}

	async function init() {
		const wrapper = document.querySelector( buttonSelector );

		const sdk = await loadSdkV6( config, 'checkout' );
		const eligibility = await checkVaultEligibility( sdk, {
			currencyCode: config.currency,
		} );

		// Render the PayPal button only when both its wrapper is present and
		// PayPal is eligible.
		if ( wrapper && eligibility.paypal ) {
			const session = createSavePayPalSession( sdk, config );
			wrapper.innerHTML = '';
			wrapper.appendChild( createPayPalButton( session ) );
		}

		// Card saving is independent of the PayPal wrapper; gate it only on
		// card eligibility and the card fields being enabled.
		if ( eligibility.card && config.card_fields?.enabled ) {
			initCardSaveFields( config );
		}
	}

	function setupListeners() {
		document.body.addEventListener( 'change', ( event ) => {
			if ( event.target?.name === 'payment_method' ) {
				syncVisibility();
			}
		} );
		document.body.addEventListener( 'click', ( event ) => {
			if (
				event.target?.matches?.(
					'.payment_methods input.input-radio'
				)
			) {
				syncVisibility();
			}
		} );
	}

	function boot() {
		setupListeners();
		// Always sync visibility once init settles: even if init() rejects
		// (SDK load, client token, eligibility) the page must not be left with
		// unmounted fields and an inconsistent submit control.
		init()
			.catch( ( error ) => handleError( error ) )
			.finally( syncVisibility );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )( window.wc_ppcp_sdk_v6_save );
