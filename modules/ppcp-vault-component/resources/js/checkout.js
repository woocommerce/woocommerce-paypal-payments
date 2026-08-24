/**
 * Standalone classic-checkout boot for the PayPal saved-payment "vault component".
 *
 * In the v5 stack this renderer rides on the smart-button bundle (button.js) and
 * is driven by CheckoutBootstrap. The SDK v6 integration replaces the smart button
 * with a no-op on the pages it owns, so that bundle — and with it this renderer —
 * never loads, leaving the printed `#ppcp-vault-component` container empty. This
 * entry runs the same self-contained VaultRenderer independently, so the saved
 * PayPal selector keeps working under v6. It is enqueued only when the v5 smart
 * button is suppressed (see VaultComponentModule), so the two never both render it.
 *
 * @package
 */

import VaultRenderer from '@ppcp-button/Renderer/VaultRenderer';

( function ( config ) {
	'use strict';

	if ( ! config || ! config.vault_component?.is_eligible ) {
		return;
	}

	const gatewayId = config.gateway_id || 'ppcp-gateway';
	const renderer = new VaultRenderer( config );

	function isPayPalSelected() {
		return (
			document.querySelector( 'input[name="payment_method"]:checked' )
				?.value === gatewayId
		);
	}

	function checkoutForm() {
		return (
			document.querySelector( 'form.checkout' ) ||
			document.querySelector( 'form#order_review' )
		);
	}

	/**
	 * Records the vault-approved PayPal order on the checkout form so the gateway
	 * captures that order on Place Order (mirrors v5's injectVaultOrderIdInput).
	 *
	 * @param {string} orderId - The approved PayPal order id.
	 */
	function injectOrderId( orderId ) {
		const form = checkoutForm();
		if ( ! form ) {
			return;
		}
		let input = form.querySelector( 'input[name="paypal_order_id"]' );
		if ( ! input ) {
			input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = 'paypal_order_id';
			form.appendChild( input );
		}
		input.value = orderId;
	}

	function removeOrderId() {
		document.querySelector( 'input[name="paypal_order_id"]' )?.remove();
	}

	function updateUi() {
		// A $0 free-trial cart uses the save-without-purchase flow; the order-based
		// vault component would create a $0 order PayPal rejects, so skip it there.
		const show = isPayPalSelected() && ! config.is_free_trial_cart;

		if ( show && ! renderer.isRendered() ) {
			renderer.render(
				( orderId ) => injectOrderId( orderId ),
				() => removeOrderId()
			);
		} else if ( ! show ) {
			renderer.close();
			removeOrderId();
		}
	}

	function onUpdatedCheckout() {
		// WC rebuilds the payment DOM (container included) on each update, so drop
		// the stale instance and any recorded order id before re-evaluating.
		renderer.reset();
		removeOrderId();
		updateUi();
	}

	function init() {
		if ( typeof jQuery !== 'undefined' ) {
			// onUpdatedCheckout already re-runs updateUi, so updateUi only needs
			// binding to the events onUpdatedCheckout does not cover.
			jQuery( document.body ).on( 'updated_checkout', onUpdatedCheckout );
			jQuery( document.body ).on( 'payment_method_selected', updateUi );
			jQuery( document ).on(
				'change',
				'input[name="wc-ppcp-gateway-payment-token"]',
				updateUi
			);
		}
		updateUi();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )( window.ppcp_vault_component );
