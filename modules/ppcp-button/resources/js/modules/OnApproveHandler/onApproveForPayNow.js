import {
	getCurrentPaymentMethod,
	PaymentMethods,
} from '../Helper/CheckoutMethodState';
import Spinner from '../Helper/Spinner';

import resumeFlowHelper from '../Helper/ResumeFlowHelper';

const onApprove = ( context, errorHandler ) => {
	return ( data, actions ) => {
		const spinner = Spinner.fullPage();
		spinner.block();
		errorHandler.clear();
		// Both captured before the params are stripped below, since that is what
		// they read.
		const isReturnFromPayPal = resumeFlowHelper.isReturnFromPayPal();
		const cleanUrl = resumeFlowHelper.urlWithoutPayPalParams();

		// Pay Now submits via form (not AJAX), so we can't detect payment errors.
		// Preemptively remove hash params to prevent reload issues.
		if ( resumeFlowHelper.isResumeFlow() ) {
			resumeFlowHelper.cleanHashParams();
		}

		return fetch( context.config.ajax.approve_order.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify( {
				nonce: context.config.ajax.approve_order.nonce,
				order_id: data.orderID,
				funding_source: window.ppcpFundingSource,
			} ),
		} )
			.then( ( res ) => {
				return res.json();
			} )
			.then( ( data ) => {
				if ( ! data.success ) {
					if ( data.data.code === 100 ) {
						errorHandler.message( data.data.message );
					} else {
						errorHandler.genericError();
					}
					if (
						typeof actions !== 'undefined' &&
						typeof actions.restart !== 'undefined'
					) {
						return actions.restart();
					}
					throw new Error( data.data.message );
				}

				// in some cases a different method may get selected,
				// such as when returning from AppSwitch in a different browser and PayPal is not default
				if ( ! getCurrentPaymentMethod().startsWith( 'ppcp-' ) ) {
					jQuery(
						`input[name="payment_method"][value="${ PaymentMethods.PAYPAL }"]`
					).prop( 'checked', true );
				}

				/*
				 * Returning from PayPal — by AppSwitch or by redirect — lands on a
				 * freshly loaded page, rendered before the approved order reached
				 * the session. The checkout fields WooCommerce cannot restore for a
				 * guest, name, email and phone, are therefore empty, and the terms
				 * box is unticked, so submitting now fails validation with the
				 * payment already approved.
				 *
				 * Loading the page again, with the order in the session, lets
				 * CheckoutPayPalAddressPreset fill those fields from the payer and
				 * puts the checkout in continuation mode for the buyer to confirm.
				 * Terms acceptance is deliberately left to them.
				 *
				 * The PayPal params are dropped on the way, or the SDK would treat
				 * the new page as another return and approve again in a loop.
				 */
				if ( isReturnFromPayPal ) {
					// The buyer already asked to pay before leaving, so carry that
					// across the reload and let the restored checkout submit itself.
					resumeFlowHelper.markSubmitAfterReturn();
					window.location.replace( cleanUrl );
					return;
				}

				document.querySelector( '#place_order' ).click();
			} )
			.finally( () => {
				spinner.unblock();
			} );
	};
};

export default onApprove;
